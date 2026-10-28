<?php
/**
 * Plugin Name:       NodeThemes AI Chat - AI Customer Support Chat
 * Plugin URI:        https://nodethemes.com/
 * Description:       An AI assistant that answers your customers in a live chat on your website, using your own OpenAI / Claude / Gemini API key and what you tell it about your business. Questions it cannot answer are passed to you. The Pro add-on adds Telegram, WhatsApp, learning from your answers and unlimited AI replies.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            NodeThemes
 * Author URI:        https://nodethemes.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       nodethemes-ai-chat
 * Update URI:        https://nodethemes.com/nodethemes-ai-chat
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'NTAC_VERSION' ) ) {
	return;
}

define( 'NTAC_VERSION', '1.0.0' );
define( 'NTAC_DB_VERSION', '1' );
define( 'NTAC_FILE', __FILE__ );
define( 'NTAC_DIR', plugin_dir_path( __FILE__ ) );
define( 'NTAC_URL', plugin_dir_url( __FILE__ ) );
define( 'NTAC_BASENAME', plugin_basename( __FILE__ ) );

foreach ( array( 'util', 'db', 'settings', 'pro', 'ai', 'knowledge', 'channels', 'engine', 'queue', 'rest', 'widget', 'admin', 'privacy' ) as $ntac_f ) {
	require_once NTAC_DIR . 'includes/class-ntac-' . $ntac_f . '.php';
}
unset( $ntac_f );

// Free updates from nodethemes.com (no license needed).
require_once NTAC_DIR . 'includes/class-ntac-updater.php';
NTAC_Free_Updater::boot( NTAC_FILE, NTAC_VERSION, 'https://nodethemes.com/' );

register_activation_hook( __FILE__, array( 'NTAC_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NTAC_Core', 'deactivate' ) );

/**
 * Boot.
 */
final class NTAC_Core {

	/**
	 * Start every module.
	 */
	public static function boot() {
		NTAC_DB::maybe_upgrade();
		NTAC_Settings::init();
		NTAC_Channels::init();
		NTAC_Queue::init();
		NTAC_Rest::init();
		NTAC_Widget::init();
		NTAC_Privacy::init();
		if ( is_admin() ) {
			NTAC_Admin::init();
		}
		do_action( 'ntac_loaded' ); // The Pro add-on hooks in here.
	}

	/**
	 * Activation: tables and schedules.
	 */
	public static function activate() {
		NTAC_DB::install();
		NTAC_Queue::schedule();
	}

	/**
	 * Deactivation: stop the schedules.
	 */
	public static function deactivate() {
		NTAC_Queue::unschedule();
	}
}
add_action( 'plugins_loaded', array( 'NTAC_Core', 'boot' ) );
