<?php
/**
 * The chat bubble on the website.
 *
 * The page HTML contains no visitor data (only settings), so page caching plugins can cache it.
 * Messages are loaded through the REST API, which caching plugins do not cache.
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Widget.
 */
final class NTAC_Widget {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Should the bubble show on this request?
	 *
	 * @return bool
	 */
	public static function visible() {
		if ( ! NTAC_Settings::on( 'widget_on' ) || is_admin() || is_feed() || is_embed() ) {
			return false;
		}
		$hide = array_filter( array_map( 'absint', explode( ',', (string) NTAC_Settings::get( 'hide_ids' ) ) ) );
		if ( $hide && is_singular() && in_array( (int) get_queried_object_id(), $hide, true ) ) {
			return false;
		}
		return (bool) apply_filters( 'ntac_show_widget', true );
	}

	/**
	 * Scripts and settings.
	 */
	public static function enqueue() {
		if ( ! self::visible() ) {
			return;
		}
		wp_enqueue_style( 'ntac-widget', NTAC_URL . 'assets/widget.css', array(), NTAC_VERSION );
		wp_enqueue_script( 'ntac-widget', NTAC_URL . 'assets/widget.js', array(), NTAC_VERSION, true );
		$color = NTAC_Settings::get( 'color' );
		wp_add_inline_style( 'ntac-widget', '#ntac-root{--ntac-c:' . esc_attr( $color ? $color : '#4f46e5' ) . '}' . ( NTAC_Settings::on( 'mobile' ) ? '' : '@media(max-width:600px){#ntac-root{display:none!important}}' ) );
		wp_localize_script(
			'ntac-widget',
			'ntacChat',
			array(
				'api'      => esc_url_raw( rest_url( 'ntac/v1/chat/' ) ),
				'title'    => NTAC_Settings::text( 'widget_title' ),
				'sub'      => NTAC_Settings::text( 'widget_sub' ),
				'welcome'  => NTAC_Settings::text( 'welcome' ),
				'notice'   => NTAC_Settings::text( 'notice' ),
				'position' => 'left' === NTAC_Settings::get( 'position' ) ? 'left' : 'right',
				'contact'  => NTAC_Settings::on( 'ask_contact' ),
				'name'     => NTAC_Settings::assistant_name(),
				'i18n'     => array(
					'open'        => __( 'Open chat', 'nodethemes-ai-chat' ),
					'close'       => __( 'Close chat', 'nodethemes-ai-chat' ),
					'placeholder' => __( 'Type your message…', 'nodethemes-ai-chat' ),
					'send'        => __( 'Send', 'nodethemes-ai-chat' ),
					'typing'      => __( 'typing…', 'nodethemes-ai-chat' ),
					'error'       => __( 'Message not sent. Please check your connection and try again.', 'nodethemes-ai-chat' ),
					'yourName'    => __( 'Your name', 'nodethemes-ai-chat' ),
					'yourEmail'   => __( 'Your e-mail (so we can reply if you leave)', 'nodethemes-ai-chat' ),
					'start'       => __( 'Start chat', 'nodethemes-ai-chat' ),
					'agent'       => __( 'Team', 'nodethemes-ai-chat' ),
					'messages'    => __( 'Chat messages', 'nodethemes-ai-chat' ),
				),
			)
		);
	}
}
