<?php
/**
 * Plugin deactivation handler.
 *
 * Clears the scheduled cleanup cron event so that WP-Cron does not fire
 * cartshare_cleanup_event while the plugin is inactive.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Deactivator
 *
 * Fired during plugin deactivation.
 */
class CartShare_Deactivator {

	/**
	 * Run all deactivation tasks.
	 *
	 * Clears the daily cartshare_cleanup_event from WP-Cron so no cleanup
	 * jobs fire while the plugin is deactivated. The scheduled event is
	 * re-created the next time the plugin is activated.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'cartshare_cleanup_event' );
	}
}
