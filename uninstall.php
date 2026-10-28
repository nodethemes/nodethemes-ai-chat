<?php
/**
 * Uninstall: data is kept unless "Delete all ... when the plugin is deleted" is ticked
 * (Settings → Privacy & data), so knowledge and conversations survive a reinstall.
 *
 * @package NodeThemes_AI_Chat
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'ntac_sweep' );
wp_clear_scheduled_hook( 'ntac_daily' );

$ntac_settings = get_option( 'ntac_settings', array() );
if ( is_array( $ntac_settings ) && isset( $ntac_settings['remove_data'] ) && 'yes' === $ntac_settings['remove_data'] ) {
	global $wpdb;
	foreach ( array( 'conversations', 'messages', 'knowledge' ) as $ntac_t ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ntac_{$ntac_t}" ); // phpcs:ignore WordPress.DB
	}
	foreach ( array( 'ntac_settings', 'ntac_keys', 'ntac_usage', 'ntac_month', 'ntac_db_version', 'ntac_worker_secret', 'ntac_models_openai', 'ntac_models_anthropic', 'ntac_models_gemini' ) as $ntac_o ) {
		delete_option( $ntac_o );
	}
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ntac\\_lock\\_%'" ); // phpcs:ignore WordPress.DB
}
delete_site_transient( 'ntac_free_update' );
