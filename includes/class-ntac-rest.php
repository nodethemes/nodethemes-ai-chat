<?php
/**
 * REST API: the website chat (public), the background worker (signed) and the inbox (admins).
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST.
 */
final class NTAC_Rest {

	const NS = 'ntac/v1';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Capability for the inbox.
	 *
	 * @return string
	 */
	public static function inbox_cap() {
		return (string) apply_filters( 'ntac_inbox_capability', 'manage_options' );
	}

	/**
	 * Routes.
	 */
	public static function routes() {
		$public = '__return_true';
		register_rest_route(
			self::NS,
			'/chat/send',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'chat_send' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NS,
			'/chat/poll',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'chat_poll' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::NS,
			'/worker',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'worker' ),
				'permission_callback' => $public, // Verified by HMAC signature inside.
			)
		);

		$admin = function () {
			return current_user_can( self::inbox_cap() );
		};
		register_rest_route(
			self::NS,
			'/admin/conversations',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'admin_list' ),
				'permission_callback' => $admin,
			)
		);
		register_rest_route(
			self::NS,
			'/admin/conversation/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'admin_get' ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'admin_delete' ),
					'permission_callback' => $admin,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/admin/conversation/(?P<id>\d+)/(?P<action>reply|ai|done|learn)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'admin_action' ),
				'permission_callback' => $admin,
			)
		);
	}

	/* ------------------------------------------------------------------ public chat */

	/**
	 * Visitor token → conversation id on the "web" channel.
	 *
	 * @param string $token Token from the browser.
	 * @return string '' when the token is not valid.
	 */
	private static function visitor( $token ) {
		$token = (string) $token;
		if ( ! preg_match( '/^[a-f0-9]{32,64}$/', $token ) ) {
			return '';
		}
		return 'v' . substr( hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ), 0, 40 );
	}

	/**
	 * Error response.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return WP_REST_Response
	 */
	private static function fail( $code, $message, $status = 400 ) {
		return new WP_REST_Response(
			array(
				'ok'      => false,
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Messages for the browser.
	 *
	 * @param array $rows Rows.
	 * @return array
	 */
	private static function public_msgs( $rows ) {
		$out = array();
		foreach ( $rows as $m ) {
			if ( 'system' === $m->role ) {
				continue;
			}
			$out[] = array(
				'id'   => (int) $m->id,
				'role' => 'customer' === $m->role ? 'me' : ( 'admin' === $m->role ? 'agent' : 'bot' ),
				'text' => (string) $m->body,
				'time' => strtotime( $m->created . ' UTC' ),
			);
		}
		return $out;
	}

	/**
	 * POST /chat/send.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function chat_send( $req ) {
		if ( ! NTAC_Settings::on( 'widget_on' ) ) {
			return self::fail( 'off', __( 'The chat is switched off.', 'nodethemes-ai-chat' ), 403 );
		}
		$ext = self::visitor( $req->get_param( 'token' ) );
		if ( '' === $ext ) {
			return self::fail( 'token', __( 'Please reload the page and try again.', 'nodethemes-ai-chat' ) );
		}
		$text = NTAC_Util::clean_text( (string) $req->get_param( 'text' ), 1500 );
		if ( '' === $text ) {
			return self::fail( 'empty', __( 'Please type a message.', 'nodethemes-ai-chat' ) );
		}
		$ip = NTAC_Util::ip();
		if ( NTAC_Util::limited( 'ip_' . $ip, 20, 60 ) || NTAC_Util::limited( 'v_' . $ext, 8, 60 ) || NTAC_Util::limited( 'ip_day_' . $ip, 300, DAY_IN_SECONDS ) ) {
			return self::fail( 'slow', __( 'You are sending messages too quickly. Please wait a moment.', 'nodethemes-ai-chat' ), 429 );
		}
		$data = array();
		$name = sanitize_text_field( (string) $req->get_param( 'name' ) );
		$mail = sanitize_email( (string) $req->get_param( 'email' ) );
		if ( '' !== $name ) {
			$data['name'] = NTAC_Util::cut( $name, 80, '' );
		}
		if ( $mail ) {
			$data['contact'] = $mail;
		}
		$page = esc_url_raw( (string) $req->get_param( 'page' ) );
		if ( $page && wp_parse_url( $page, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			$data['msg_meta'] = array( 'page' => NTAC_Util::cut( $page, 300, '' ) );
		}
		$r = NTAC_Engine::incoming( 'web', $ext, $text, $data );
		if ( ! $r['msg_id'] ) {
			return self::fail( 'save', __( 'Your message could not be saved. Please try again.', 'nodethemes-ai-chat' ), 500 );
		}
		return new WP_REST_Response(
			array(
				'ok' => true,
				'id' => (int) $r['msg_id'],
			)
		);
	}

	/**
	 * GET /chat/poll.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function chat_poll( $req ) {
		$ext = self::visitor( $req->get_param( 'token' ) );
		if ( '' === $ext ) {
			return self::fail( 'token', __( 'Please reload the page and try again.', 'nodethemes-ai-chat' ) );
		}
		if ( NTAC_Util::limited( 'poll_' . NTAC_Util::ip(), 240, 60 ) ) {
			return self::fail( 'slow', 'Too many requests.', 429 );
		}
		$conv = NTAC_DB::conv_by_ext( 'web', $ext );
		if ( ! $conv ) {
			return new WP_REST_Response(
				array(
					'ok'       => true,
					'messages' => array(),
					'typing'   => false,
				)
			);
		}
		$after = max( 0, (int) $req->get_param( 'after' ) );

		// Background requests blocked by the host? Answer from this request instead.
		if ( $conv->ai_on && ! NTAC_Queue::busy( $conv->id ) ) {
			$pending = NTAC_DB::pending( $conv );
			if ( $pending && strtotime( end( $pending )->created . ' UTC' ) < time() - 6 ) {
				NTAC_Queue::run( $conv->id );
				$conv = NTAC_DB::conv( $conv->id );
			}
		}
		$typing = ! empty( $conv->meta['typing_until'] ) && (int) $conv->meta['typing_until'] > time();
		return new WP_REST_Response(
			array(
				'ok'       => true,
				'messages' => self::public_msgs( NTAC_DB::msgs( $conv->id, $after, 100, true ) ),
				'typing'   => $typing,
			)
		);
	}

	/**
	 * POST /worker (signed by this site).
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function worker( $req ) {
		$id  = (int) $req->get_param( 'conv' );
		$t   = (int) $req->get_param( 't' );
		$sig = (string) $req->get_param( 'sig' );
		if ( ! $id || abs( time() - $t ) > 300 || ! hash_equals( NTAC_Queue::sign( $id, $t ), $sig ) ) {
			return self::fail( 'sig', 'Bad signature.', 403 );
		}
		NTAC_Queue::run( $id );
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	/* ------------------------------------------------------------------ inbox */

	/**
	 * Conversation for the inbox.
	 *
	 * @param object $c Conversation.
	 * @return array
	 */
	public static function conv_out( $c ) {
		$paused = $c->paused_until && strtotime( $c->paused_until . ' UTC' ) > time();
		return array(
			'id'           => (int) $c->id,
			'channel'      => $c->channel,
			'channelLabel' => NTAC_Channels::label( $c->channel ),
			'name'         => '' !== $c->name ? $c->name : __( 'Visitor', 'nodethemes-ai-chat' ) . ' #' . (int) $c->id,
			'contact'      => (string) $c->contact,
			'ai'           => (bool) $c->ai_on,
			'paused'       => $paused ? strtotime( $c->paused_until . ' UTC' ) : 0,
			'needs'        => (bool) $c->needs_admin,
			'unread'       => (int) $c->unread,
			'preview'      => (string) $c->last_preview,
			'ago'          => NTAC_Util::ago( $c->last_at ),
			'canSend'      => (bool) NTAC_Channels::get( $c->channel ),
		);
	}

	/**
	 * GET /admin/conversations.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function admin_list( $req ) {
		$rows = NTAC_DB::conv_list(
			array(
				'filter' => sanitize_key( (string) $req->get_param( 'filter' ) ),
				'search' => sanitize_text_field( (string) $req->get_param( 'search' ) ),
			)
		);
		return new WP_REST_Response(
			array(
				'conversations' => array_map( array( __CLASS__, 'conv_out' ), $rows ),
				'counts'        => NTAC_DB::counts(),
			)
		);
	}

	/**
	 * GET /admin/conversation/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function admin_get( $req ) {
		$c = NTAC_DB::conv( (int) $req['id'] );
		if ( ! $c ) {
			return self::fail( 'not_found', __( 'Conversation not found.', 'nodethemes-ai-chat' ), 404 );
		}
		if ( $c->unread ) {
			NTAC_DB::conv_update( $c->id, array( 'unread' => 0 ) );
		}
		$msgs = array();
		foreach ( NTAC_DB::msgs( $c->id, max( 0, (int) $req->get_param( 'after' ) ), 300 ) as $m ) {
			$meta   = json_decode( (string) $m->meta, true );
			$msgs[] = array(
				'id'     => (int) $m->id,
				'role'   => $m->role,
				'text'   => (string) $m->body,
				'status' => $m->status,
				'error'  => (string) $m->error,
				'time'   => wp_date( 'j M, H:i', strtotime( $m->created . ' UTC' ) ),
				'page'   => is_array( $meta ) && ! empty( $meta['page'] ) ? (string) $meta['page'] : '',
				'via'    => is_array( $meta ) && ! empty( $meta['via'] ) ? (string) $meta['via'] : '',
			);
		}
		return new WP_REST_Response(
			array(
				'conversation' => self::conv_out( $c ),
				'messages'     => $msgs,
			)
		);
	}

	/**
	 * DELETE /admin/conversation/{id}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function admin_delete( $req ) {
		NTAC_DB::conv_delete( (int) $req['id'] );
		return new WP_REST_Response( array( 'ok' => true ) );
	}

	/**
	 * POST /admin/conversation/{id}/{action}.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return WP_REST_Response
	 */
	public static function admin_action( $req ) {
		$c = NTAC_DB::conv( (int) $req['id'] );
		if ( ! $c ) {
			return self::fail( 'not_found', __( 'Conversation not found.', 'nodethemes-ai-chat' ), 404 );
		}
		switch ( $req['action'] ) {
			case 'reply':
				$r = NTAC_Engine::admin_reply( $c->id, (string) $req->get_param( 'text' ) );
				if ( is_wp_error( $r ) ) {
					return self::fail( $r->get_error_code(), $r->get_error_message(), 400 );
				}
				break;
			case 'ai':
				$on = (bool) (int) $req->get_param( 'on' );
				NTAC_DB::conv_update(
					$c->id,
					array(
						'ai_on'        => $on ? 1 : 0,
						'paused_until' => null,
					)
				);
				if ( $on ) {
					// Mark what is waiting as handled: the AI starts with the next message.
					$p = NTAC_DB::pending( $c );
					if ( $p ) {
						NTAC_DB::conv_update( $c->id, array( 'handled_id' => (int) end( $p )->id ) );
					}
				}
				break;
			case 'done':
				NTAC_DB::conv_update(
					$c->id,
					array(
						'needs_admin' => 0,
						'unread'      => 0,
					)
				);
				break;
			case 'learn':
				$res = apply_filters( 'ntac_learn_from_message', null, $c, (int) $req->get_param( 'msg' ) );
				if ( null === $res ) {
					return self::fail( 'pro', __( 'Saving answers as knowledge is a Pro feature.', 'nodethemes-ai-chat' ), 403 );
				}
				if ( is_wp_error( $res ) ) {
					return self::fail( $res->get_error_code(), $res->get_error_message(), 400 );
				}
				return new WP_REST_Response(
					array(
						'ok'      => true,
						'message' => (string) $res,
					)
				);
		}
		return new WP_REST_Response(
			array(
				'ok'           => true,
				'conversation' => self::conv_out( NTAC_DB::conv( $c->id ) ),
			)
		);
	}
}
