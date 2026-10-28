<?php
/**
 * Free / Pro split and the monthly AI-reply allowance of the free plugin.
 *
 * The Pro code (Telegram, WhatsApp, learning, unlimited replies) lives in the separate
 * "NodeThemes AI Chat Pro" add-on. It announces itself through the ntac_pro_installed filter
 * and answers ntac_is_pro only while its license is active.
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pro gate.
 */
final class NTAC_Pro {

	const UPGRADE_URL = 'https://nodethemes.com/product/nodethemes-ai-chat-pro/';
	const FREE_LIMIT  = 50; // AI replies per calendar month in the free plugin.

	/**
	 * Is the Pro add-on installed and active?
	 *
	 * @return bool
	 */
	public static function has_code() {
		return (bool) apply_filters( 'ntac_pro_installed', false );
	}

	/**
	 * Installed AND licensed.
	 *
	 * @return bool
	 */
	public static function active() {
		return (bool) apply_filters( 'ntac_is_pro', false );
	}

	/**
	 * Upgrade link (the license page when the add-on is installed but not activated).
	 *
	 * @param string $feature Campaign.
	 * @return string
	 */
	public static function url( $feature = '' ) {
		if ( self::has_code() && ! self::active() ) {
			return admin_url( 'admin.php?page=ntac-license' );
		}
		return add_query_arg(
			array(
				'utm_source'   => 'plugin',
				'utm_medium'   => 'lock',
				'utm_campaign' => $feature,
			),
			self::UPGRADE_URL
		);
	}

	/**
	 * AI replies allowed per month (0 = unlimited).
	 *
	 * @return int
	 */
	public static function limit() {
		return self::active() ? 0 : (int) apply_filters( 'ntac_free_monthly_limit', self::FREE_LIMIT );
	}

	/**
	 * This month's counters.
	 *
	 * @return array ym, ai, missed.
	 */
	public static function usage() {
		$u  = get_option( 'ntac_month', array() );
		$ym = wp_date( 'Y-m' );
		if ( ! is_array( $u ) || ! isset( $u['ym'] ) || $u['ym'] !== $ym ) {
			$u = array(
				'ym'     => $ym,
				'ai'     => 0,
				'missed' => 0,
			);
		}
		return $u;
	}

	/**
	 * Count one AI reply or one missed reply.
	 *
	 * @param string $what ai|missed.
	 */
	public static function count( $what ) {
		$u          = self::usage();
		$u[ $what ] = (int) $u[ $what ] + 1;
		update_option( 'ntac_month', $u, false );
	}

	/**
	 * May the AI answer one more message this month?
	 *
	 * @return bool
	 */
	public static function can_reply() {
		$l = self::limit();
		return 0 === $l || (int) self::usage()['ai'] < $l;
	}

	/**
	 * Pro features list (for upsell boxes).
	 *
	 * @return array
	 */
	public static function features() {
		return array(
			__( 'Unlimited AI replies (the free plugin includes 50 per month)', 'nodethemes-ai-chat' ),
			__( 'Telegram: your own bot, or answer from your personal account with Telegram Business', 'nodethemes-ai-chat' ),
			__( 'WhatsApp: official WhatsApp Cloud API, or your normal WhatsApp by QR code through your own gateway', 'nodethemes-ai-chat' ),
			__( 'Human-like replies: reading pause, typing indicator and natural typing speed', 'nodethemes-ai-chat' ),
			__( 'Learns from your answers: every reply you give can become a new knowledge entry (you approve it)', 'nodethemes-ai-chat' ),
			__( 'Get questions on your Telegram and answer them right there', 'nodethemes-ai-chat' ),
			__( 'Answers about your WooCommerce products (price, stock, link)', 'nodethemes-ai-chat' ),
			__( 'Backup AI provider when the main one fails', 'nodethemes-ai-chat' ),
		);
	}

	/**
	 * Locked button.
	 *
	 * @param string $label   Label.
	 * @param string $feature Campaign.
	 * @return string
	 */
	public static function lock( $label, $feature ) {
		return '<a class="ntac-btn ntac-locked" href="' . esc_url( self::url( $feature ) ) . '" target="_blank" rel="noopener">&#128274; ' . esc_html( $label ) . ' <span class="ntac-protag">PRO</span></a>';
	}

	/**
	 * Upsell box.
	 *
	 * @param string $title Title.
	 * @param string $text  Text.
	 */
	public static function upsell_box( $title = '', $text = '' ) {
		echo '<div class="ntac-card ntac-pro-box"><h2>&#128274; ' . esc_html( $title ? $title : __( 'Pro feature', 'nodethemes-ai-chat' ) ) . '</h2>';
		if ( $text ) {
			echo '<p class="ntac-help">' . esc_html( $text ) . '</p>';
		}
		echo '<ul class="ntac-list">';
		foreach ( self::features() as $f ) {
			echo '<li>' . esc_html( $f ) . '</li>';
		}
		echo '</ul>';
		if ( self::has_code() ) {
			echo '<p><a class="ntac-btn primary" href="' . esc_url( admin_url( 'admin.php?page=ntac-license' ) ) . '">' . esc_html__( 'Activate your Pro license', 'nodethemes-ai-chat' ) . '</a></p>';
		} else {
			echo '<p><a class="ntac-btn primary" target="_blank" rel="noopener" href="' . esc_url( self::url( 'box' ) ) . '">' . esc_html__( 'See NodeThemes AI Chat Pro', 'nodethemes-ai-chat' ) . '</a></p>';
			echo '<p class="ntac-help">' . esc_html__( 'Already bought Pro? Install the "NodeThemes AI Chat Pro" add-on, then enter your key under AI Chat → License.', 'nodethemes-ai-chat' ) . '</p>';
		}
		echo '</div>';
	}
}
