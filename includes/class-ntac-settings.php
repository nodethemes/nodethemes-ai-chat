<?php
/**
 * Options, encrypted API keys and saving.
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings.
 */
final class NTAC_Settings {

	const OPT  = 'ntac_settings';
	const KEYS = 'ntac_keys';

	/**
	 * Cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_ntac_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'wp_ajax_ntac_test_ai', array( __CLASS__, 'ajax_test' ) );
		add_action( 'wp_ajax_ntac_list_models', array( __CLASS__, 'ajax_models' ) );
	}

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'provider'          => 'openai',
			'model_openai'      => 'gpt-4o-mini',
			'model_anthropic'   => 'claude-haiku-5-5',
			'model_gemini'      => 'gemini-2.5-flash',
			'daily_cap'         => 500,
			'daily_budget'      => 3,
			'assistant_name'    => '',
			'business'          => '',
			'instructions'      => '',
			'handoff_topics'    => '',
			'lang'              => 'auto',
			'lang_custom'       => '',
			'tone'              => 'friendly',
			'disclose_first'    => 'yes',
			'offline_msg'       => '',
			'widget_on'         => 'yes',
			'widget_title'      => '',
			'widget_sub'        => '',
			'welcome'           => '',
			'notice'            => '',
			'color'             => '#4f46e5',
			'position'          => 'right',
			'mobile'            => 'yes',
			'ask_contact'       => 'no',
			'hide_ids'          => '',
			'notify_email_on'   => 'yes',
			'notify_email'      => '',
			'pause_after_human' => 60,
			'retention'         => 180,
			'remove_data'       => 'no',
		);
	}

	/**
	 * All settings.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$s           = get_option( self::OPT, array() );
			self::$cache = array_merge( self::defaults(), is_array( $s ) ? $s : array() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $k Key.
	 * @return mixed
	 */
	public static function get( $k ) {
		$a = self::all();
		return isset( $a[ $k ] ) ? $a[ $k ] : null;
	}

	/**
	 * Yes/no setting.
	 *
	 * @param string $k Key.
	 * @return bool
	 */
	public static function on( $k ) {
		return 'yes' === self::get( $k );
	}

	/**
	 * Save all settings.
	 *
	 * @param array $arr Settings.
	 */
	public static function save( $arr ) {
		update_option( self::OPT, $arr, false );
		self::$cache = null;
	}

	/* ------------------------------------------------------------------ texts with translated defaults */

	/**
	 * Assistant name.
	 *
	 * @return string
	 */
	public static function assistant_name() {
		$n = trim( (string) self::get( 'assistant_name' ) );
		/* translators: %s: site name */
		return '' !== $n ? $n : sprintf( __( '%s Assistant', 'nodethemes-ai-chat' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	}

	/**
	 * Text with a default.
	 *
	 * @param string $k Key.
	 * @return string
	 */
	public static function text( $k ) {
		$v = trim( (string) self::get( $k ) );
		if ( '' !== $v ) {
			return $v;
		}
		$d = array(
			'offline_msg'  => __( 'Thanks for your message! A team member will reply as soon as possible.', 'nodethemes-ai-chat' ),
			'widget_title' => __( 'Chat with us', 'nodethemes-ai-chat' ),
			'widget_sub'   => __( 'We usually reply within seconds', 'nodethemes-ai-chat' ),
			'welcome'      => __( 'Hi! 👋 How can I help you today?', 'nodethemes-ai-chat' ),
			'notice'       => __( 'You are chatting with our AI assistant. A team member can join if needed.', 'nodethemes-ai-chat' ),
		);
		return isset( $d[ $k ] ) ? $d[ $k ] : '';
	}

	/**
	 * Where handoff e-mails go.
	 *
	 * @return string
	 */
	public static function notify_email() {
		$e = sanitize_email( (string) self::get( 'notify_email' ) );
		return $e ? $e : (string) get_option( 'admin_email' );
	}

	/* ------------------------------------------------------------------ API keys (encrypted at rest) */

	/**
	 * Providers.
	 *
	 * @return array
	 */
	public static function providers() {
		return array(
			'openai'    => array(
				'label' => 'OpenAI (ChatGPT)',
				'const' => 'NTAC_OPENAI_KEY',
				'url'   => 'https://platform.openai.com/api-keys',
				'steps' => __( 'Sign in, press "Create new secret key", copy it and add a few dollars of credit under Billing. The API is prepaid and separate from a ChatGPT subscription.', 'nodethemes-ai-chat' ),
			),
			'anthropic' => array(
				'label' => 'Anthropic (Claude)',
				'const' => 'NTAC_ANTHROPIC_KEY',
				'url'   => 'https://console.anthropic.com/settings/keys',
				'steps' => __( 'Sign in, press "Create Key", copy it and add credit under Billing. The API is prepaid and separate from a Claude subscription.', 'nodethemes-ai-chat' ),
			),
			'gemini'    => array(
				'label' => 'Google Gemini',
				'const' => 'NTAC_GEMINI_KEY',
				'url'   => 'https://aistudio.google.com/apikey',
				'steps' => __( 'Sign in with a Google account and press "Create API key". There is a free tier with daily limits; enable billing on the same page for more.', 'nodethemes-ai-chat' ),
			),
		);
	}

	/**
	 * Encrypt / decrypt with the site's auth salt.
	 *
	 * @param string $v       Value.
	 * @param bool   $decrypt Decrypt.
	 * @return string
	 */
	public static function crypt( $v, $decrypt = false ) {
		if ( '' === $v ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		if ( ! $decrypt ) {
			if ( function_exists( 'openssl_encrypt' ) ) {
				$iv = random_bytes( 16 );
				return 'enc:' . base64_encode( $iv . openssl_encrypt( $v, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			}
			return 'plain:' . base64_encode( $v ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		}
		if ( 0 === strpos( $v, 'enc:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $v, 4 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			if ( ! $raw || strlen( $raw ) < 17 ) {
				return '';
			}
			$out = openssl_decrypt( substr( $raw, 16 ), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
			return false === $out ? '' : $out;
		}
		if ( 0 === strpos( $v, 'plain:' ) ) {
			return (string) base64_decode( substr( $v, 6 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		}
		return '';
	}

	/**
	 * API key for a provider (wp-config constant wins).
	 *
	 * @param string $provider Provider.
	 * @return string
	 */
	public static function api_key( $provider ) {
		$p = self::providers();
		if ( ! isset( $p[ $provider ] ) ) {
			return '';
		}
		if ( defined( $p[ $provider ]['const'] ) ) {
			return trim( (string) constant( $p[ $provider ]['const'] ) );
		}
		$k = get_option( self::KEYS, array() );
		return isset( $k[ $provider ] ) ? trim( self::crypt( $k[ $provider ], true ) ) : '';
	}

	/**
	 * Has a key.
	 *
	 * @param string $provider Provider.
	 * @return bool
	 */
	public static function has_key( $provider ) {
		return '' !== self::api_key( $provider );
	}

	/**
	 * Key comes from wp-config.php?
	 *
	 * @param string $provider Provider.
	 * @return bool
	 */
	public static function key_in_config( $provider ) {
		$p = self::providers();
		return isset( $p[ $provider ] ) && defined( $p[ $provider ]['const'] );
	}

	/**
	 * Store a key.
	 *
	 * @param string $provider Provider.
	 * @param string $key      Key ('' removes it).
	 */
	private static function set_key( $provider, $key ) {
		$k = get_option( self::KEYS, array() );
		if ( ! is_array( $k ) ) {
			$k = array();
		}
		if ( '' === $key ) {
			unset( $k[ $provider ] );
		} else {
			$k[ $provider ] = self::crypt( $key );
		}
		update_option( self::KEYS, $k, false );
	}

	/**
	 * Masked key.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function mask( $key ) {
		$n = strlen( $key );
		return $n > 10 ? substr( $key, 0, 4 ) . str_repeat( '•', 8 ) . substr( $key, -4 ) : str_repeat( '•', max( 4, $n ) );
	}

	/**
	 * Model for a provider.
	 *
	 * @param string $provider Provider.
	 * @return string
	 */
	public static function model( $provider = '' ) {
		$provider = $provider ? $provider : self::get( 'provider' );
		$m        = trim( (string) self::get( 'model_' . $provider ) );
		$d        = self::defaults();
		return '' !== $m ? $m : ( isset( $d[ 'model_' . $provider ] ) ? $d[ 'model_' . $provider ] : '' );
	}

	/**
	 * Suggested models.
	 *
	 * @param string $provider Provider.
	 * @return array
	 */
	public static function suggested_models( $provider ) {
		$m = array(
			'openai'    => array( 'gpt-4o-mini', 'gpt-4.1-mini', 'gpt-4o', 'gpt-4.1', 'gpt-5-mini' ),
			'anthropic' => array( 'claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-haiku-4-5' ),
			'gemini'    => array( 'gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.5-pro' ),
		);
		$known = get_option( 'ntac_models_' . $provider, array() );
		return array_values( array_unique( array_merge( isset( $m[ $provider ] ) ? $m[ $provider ] : array(), is_array( $known ) ? $known : array() ) ) );
	}

	/* ------------------------------------------------------------------ saving */

	/**
	 * Fields per tab (key => type).
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'ai'       => array(
				'provider'        => 'provider',
				'model_openai'    => 'model',
				'model_anthropic' => 'model',
				'model_gemini'    => 'model',
				'daily_cap'       => 'int',
				'daily_budget'    => 'float',
			),
			'business' => array(
				'assistant_name' => 'text',
				'business'       => 'long',
				'instructions'   => 'long',
				'handoff_topics' => 'long',
				'lang'           => 'lang',
				'lang_custom'    => 'text',
				'tone'           => 'tone',
				'disclose_first' => 'yes',
				'offline_msg'    => 'long',
			),
			'widget'   => array(
				'widget_on'    => 'yes',
				'widget_title' => 'text',
				'widget_sub'   => 'text',
				'welcome'      => 'long',
				'notice'       => 'long',
				'color'        => 'color',
				'position'     => 'position',
				'mobile'       => 'yes',
				'ask_contact'  => 'yes',
				'hide_ids'     => 'ids',
			),
			'notify'   => array(
				'notify_email_on'   => 'yes',
				'notify_email'      => 'email',
				'pause_after_human' => 'int',
			),
			'privacy'  => array(
				'retention'   => 'int',
				'remove_data' => 'yes',
			),
		);
	}

	/**
	 * Sanitize one value.
	 *
	 * @param string $type Type.
	 * @param mixed  $v    Value.
	 * @return mixed
	 */
	private static function clean( $type, $v ) {
		$v = is_string( $v ) ? $v : '';
		switch ( $type ) {
			case 'provider':
				return isset( self::providers()[ $v ] ) ? $v : 'openai';
			case 'model':
				return preg_replace( '/[^A-Za-z0-9._\/:-]/', '', $v );
			case 'int':
				return max( 0, (int) $v );
			case 'float':
				return max( 0, round( (float) $v, 2 ) );
			case 'long':
				return sanitize_textarea_field( $v );
			case 'lang':
				return in_array( $v, array( 'auto', 'site', 'custom' ), true ) ? $v : 'auto';
			case 'tone':
				return in_array( $v, array( 'friendly', 'professional', 'short' ), true ) ? $v : 'friendly';
			case 'yes':
				return 'yes' === $v ? 'yes' : 'no';
			case 'color':
				$c = sanitize_hex_color( $v );
				return $c ? $c : '#4f46e5';
			case 'position':
				return 'left' === $v ? 'left' : 'right';
			case 'email':
				return sanitize_email( $v );
			case 'ids':
				return implode( ',', array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $v ) ) ) );
			default:
				return sanitize_text_field( $v );
		}
	}

	/**
	 * Save handler (admin-post).
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'nodethemes-ai-chat' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ntac_save_settings' );
		$tab    = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'ai';
		$fields = self::fields();
		$s      = self::all();
		if ( isset( $fields[ $tab ] ) ) {
			foreach ( $fields[ $tab ] as $k => $type ) {
				$raw     = isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in clean().
				$s[ $k ] = self::clean( $type, $raw );
			}
			if ( 'ai' === $tab ) {
				foreach ( array_keys( self::providers() ) as $p ) {
					$new = isset( $_POST[ 'key_' . $p ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ 'key_' . $p ] ) ) ) : '';
					if ( '' !== $new && false === strpos( $new, '•' ) ) {
						self::set_key( $p, $new );
					}
					if ( ! empty( $_POST[ 'remove_key_' . $p ] ) ) {
						self::set_key( $p, '' );
					}
				}
			}
			self::save( $s );
		}
		do_action( 'ntac_save_settings_tab', $tab );
		set_transient( 'ntac_flash_' . get_current_user_id(), array( 'ok', __( 'Settings saved.', 'nodethemes-ai-chat' ) ), 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=ntac-settings&tab=' . $tab ) );
		exit;
	}

	/**
	 * AJAX: test the AI connection.
	 */
	public static function ajax_test() {
		check_ajax_referer( 'ntac_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'nodethemes-ai-chat' ) ), 403 );
		}
		$prov  = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : self::get( 'provider' );
		$key   = isset( $_POST['key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['key'] ) ) ) : '';
		$model = isset( $_POST['model'] ) ? preg_replace( '/[^A-Za-z0-9._\/:-]/', '', sanitize_text_field( wp_unslash( $_POST['model'] ) ) ) : '';
		if ( false !== strpos( $key, '•' ) ) {
			$key = '';
		}
		$r = NTAC_AI::request(
			'You are a connection test. Answer with the single word OK.',
			'Say OK.',
			array(
				'provider'   => $prov,
				'key'        => $key,
				'model'      => $model,
				'max_tokens' => 20,
				'count'      => false,
			)
		);
		if ( ! $r['ok'] ) {
			wp_send_json_error( array( 'message' => $r['error'] ) );
		}
		/* translators: %s: model */
		wp_send_json_success( array( 'message' => sprintf( __( 'Connected. Model %s answered.', 'nodethemes-ai-chat' ), $r['model'] ) ) );
	}

	/**
	 * AJAX: list the models of a provider.
	 */
	public static function ajax_models() {
		check_ajax_referer( 'ntac_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'nodethemes-ai-chat' ) ), 403 );
		}
		$prov = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$key  = isset( $_POST['key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['key'] ) ) ) : '';
		if ( false !== strpos( $key, '•' ) ) {
			$key = '';
		}
		$r = NTAC_AI::list_models( $prov, $key );
		if ( ! $r['ok'] ) {
			wp_send_json_error( array( 'message' => $r['error'] ) );
		}
		update_option( 'ntac_models_' . $prov, array_slice( $r['models'], 0, 80 ), false );
		wp_send_json_success( array( 'models' => $r['models'] ) );
	}
}
