<?php
/**
 * Small helpers.
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Helpers.
 */
final class NTAC_Util {

	/**
	 * Multibyte-safe length.
	 *
	 * @param string $s Text.
	 * @return int
	 */
	public static function len( $s ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $s, 'UTF-8' ) : strlen( (string) $s );
	}

	/**
	 * Multibyte-safe cut.
	 *
	 * @param string $s        Text.
	 * @param int    $n        Max characters.
	 * @param string $ellipsis Suffix when cut.
	 * @return string
	 */
	public static function cut( $s, $n, $ellipsis = '…' ) {
		$s = (string) $s;
		if ( self::len( $s ) <= $n ) {
			return $s;
		}
		$c = function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $n, 'UTF-8' ) : substr( $s, 0, $n );
		return rtrim( $c ) . $ellipsis;
	}

	/**
	 * Decode JSON that may be wrapped in a code fence or extra words.
	 *
	 * @param string $s Text.
	 * @return array|null
	 */
	public static function json_loose( $s ) {
		$s = trim( (string) $s );
		$s = preg_replace( '/^```[a-zA-Z]*\s*|\s*```$/m', '', $s );
		$d = json_decode( $s, true );
		if ( is_array( $d ) ) {
			return $d;
		}
		$a = strpos( $s, '{' );
		$b = strrpos( $s, '}' );
		if ( false !== $a && false !== $b && $b > $a ) {
			$d = json_decode( substr( $s, $a, $b - $a + 1 ), true );
			if ( is_array( $d ) ) {
				return $d;
			}
		}
		return null;
	}

	/**
	 * Current UTC time for the database.
	 *
	 * @return string
	 */
	public static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Visitor IP (REMOTE_ADDR only: forwarded headers can be faked).
	 *
	 * @return string
	 */
	public static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) apply_filters( 'ntac_client_ip', $ip );
	}

	/**
	 * Simple fixed-window rate limit.
	 *
	 * @param string $bucket Name.
	 * @param int    $max    Allowed hits per window.
	 * @param int    $window Seconds.
	 * @return bool True when the limit is exceeded.
	 */
	public static function limited( $bucket, $max, $window ) {
		$k = 'ntac_rl_' . md5( $bucket . '|' . floor( time() / $window ) );
		$n = (int) get_transient( $k );
		if ( $n >= $max ) {
			return true;
		}
		set_transient( $k, $n + 1, $window + 5 );
		return false;
	}

	/**
	 * Clean message text: valid UTF-8, no control characters, length limit. Messages are kept as plain
	 * text (a customer may write "I <3 it" or paste code) and are always escaped when shown.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 * @return string
	 */
	public static function clean_text( $text, $max = 2000 ) {
		$text = wp_check_invalid_utf8( (string) $text, true );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
		$text = trim( $text );
		return self::cut( $text, $max, '' );
	}

	/**
	 * Human time-ago for the admin.
	 *
	 * @param string $mysql_utc UTC time.
	 * @return string
	 */
	public static function ago( $mysql_utc ) {
		$t = strtotime( $mysql_utc . ' UTC' );
		if ( ! $t ) {
			return '';
		}
		/* translators: %s: time difference */
		return sprintf( __( '%s ago', 'nodethemes-ai-chat' ), human_time_diff( $t, time() ) );
	}
}
