<?php
/**
 * Runs the assistant in the background so webhooks and the chat answer at once.
 *
 * Order of preference: finish the HTTP response and keep working (PHP-FPM / LiteSpeed),
 * otherwise a non-blocking signed request to this site. A sweep every few minutes and the
 * chat's own polling pick up anything that was missed (for example when the host blocks
 * requests to itself).
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Queue.
 */
final class NTAC_Queue {

	/**
	 * Conversations to run at shutdown.
	 *
	 * @var int[]
	 */
	private static $todo = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( 'ntac_sweep', array( __CLASS__, 'sweep' ) );
		add_action( 'ntac_daily', array( __CLASS__, 'daily' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Five-minute schedule.
	 *
	 * @param array $s Schedules.
	 * @return array
	 */
	public static function schedules( $s ) {
		$s['ntac_five_minutes'] = array(
			'interval' => 300,
			'display'  => 'Every 5 minutes (NodeThemes AI Chat)',
		);
		return $s;
	}

	/**
	 * Make sure the events exist.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'ntac_sweep' ) ) {
			wp_schedule_event( time() + 300, 'ntac_five_minutes', 'ntac_sweep' );
		}
		if ( ! wp_next_scheduled( 'ntac_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ntac_daily' );
		}
	}

	/**
	 * Remove the events.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( 'ntac_sweep' );
		wp_clear_scheduled_hook( 'ntac_daily' );
	}

	/**
	 * Secret for signed worker requests.
	 *
	 * @return string
	 */
	private static function secret() {
		$s = get_option( 'ntac_worker_secret' );
		if ( ! $s ) {
			$s = wp_generate_password( 48, false, false );
			update_option( 'ntac_worker_secret', $s, false );
		}
		return (string) $s;
	}

	/**
	 * Signature for a worker request.
	 *
	 * @param int $conv_id Conversation.
	 * @param int $time    Timestamp.
	 * @return string
	 */
	public static function sign( $conv_id, $time ) {
		return hash_hmac( 'sha256', (int) $conv_id . '|' . (int) $time, self::secret() );
	}

	/**
	 * Start working on a conversation after this request.
	 *
	 * @param int $conv_id Conversation.
	 */
	public static function kick( $conv_id ) {
		if ( ! self::$todo ) {
			add_action( 'shutdown', array( __CLASS__, 'at_shutdown' ), 1 );
		}
		self::$todo[ (int) $conv_id ] = (int) $conv_id;
	}

	/**
	 * Shutdown: hand the work to a background request, or finish the response and work here.
	 */
	public static function at_shutdown() {
		$todo       = self::$todo;
		self::$todo = array();
		if ( ! $todo ) {
			return;
		}
		$inline = (bool) apply_filters( 'ntac_inline_worker', function_exists( 'fastcgi_finish_request' ) || function_exists( 'litespeed_finish_request' ) );
		if ( $inline ) {
			ignore_user_abort( true );
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			} else {
				litespeed_finish_request();
			}
			foreach ( $todo as $id ) {
				self::run( $id );
			}
			return;
		}
		foreach ( $todo as $id ) {
			self::spawn( $id );
		}
	}

	/**
	 * Non-blocking signed request to the worker endpoint.
	 *
	 * @param int $conv_id Conversation.
	 */
	public static function spawn( $conv_id ) {
		$t = time();
		wp_remote_post(
			rest_url( 'ntac/v1/worker' ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => array(
					'conv' => (int) $conv_id,
					't'    => $t,
					'sig'  => self::sign( $conv_id, $t ),
				),
			)
		);
	}

	/**
	 * Run the engine for one conversation, once at a time.
	 *
	 * @param int $conv_id Conversation.
	 * @return bool False when another process is already working on it.
	 */
	public static function run( $conv_id ) {
		$conv_id = (int) $conv_id;
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		ignore_user_abort( true );
		for ( $pass = 0; $pass < 3; $pass++ ) {
			if ( ! self::lock( $conv_id ) ) {
				return false;
			}
			try {
				NTAC_Engine::process( $conv_id );
			} finally {
				self::unlock( $conv_id );
			}
			// A message may have arrived after the engine's last look but before the lock was released.
			$conv = NTAC_DB::conv( $conv_id );
			if ( ! $conv || ! $conv->ai_on || ! NTAC_DB::pending( $conv ) ) {
				break;
			}
		}
		return true;
	}

	/**
	 * Is someone working on this conversation right now?
	 *
	 * @param int $conv_id Conversation.
	 * @return bool
	 */
	public static function busy( $conv_id ) {
		wp_cache_delete( 'ntac_lock_' . (int) $conv_id, 'options' );
		$v = (int) get_option( 'ntac_lock_' . (int) $conv_id, 0 );
		return $v && $v > time() - 180;
	}

	/**
	 * Take the lock.
	 *
	 * @param int $conv_id Conversation.
	 * @return bool
	 */
	private static function lock( $conv_id ) {
		$k = 'ntac_lock_' . $conv_id;
		if ( add_option( $k, time(), '', false ) ) {
			return true;
		}
		wp_cache_delete( $k, 'options' );
		$v = (int) get_option( $k, 0 );
		if ( $v && $v < time() - 180 ) {
			delete_option( $k );
			return add_option( $k, time(), '', false );
		}
		return false;
	}

	/**
	 * Release the lock.
	 *
	 * @param int $conv_id Conversation.
	 */
	private static function unlock( $conv_id ) {
		delete_option( 'ntac_lock_' . $conv_id );
	}

	/**
	 * Safety net: answer conversations that were missed.
	 */
	public static function sweep() {
		$start = time();
		foreach ( NTAC_DB::waiting( 45, 5 ) as $id ) {
			if ( time() - $start > 90 ) {
				break;
			}
			self::run( $id );
		}
	}

	/**
	 * Daily housekeeping: delete old conversations.
	 */
	public static function daily() {
		NTAC_DB::purge( (int) NTAC_Settings::get( 'retention' ) );
	}
}
