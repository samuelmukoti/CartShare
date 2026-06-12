<?php
/**
 * Plugin uninstall handler.
 *
 * Fired when the user clicks "Delete" for this plugin from the Plugins screen.
 * WordPress loads this file directly, so we guard with WP_UNINSTALL_PLUGIN to
 * prevent direct execution outside of the uninstall context.
 *
 * Tasks performed:
 *  1. Drop the {prefix}cartshare_carts custom table.
 *  2. Delete all plugin options whose names begin with 'cartshare_'.
 *  3. Clear the scheduled cleanup cron event as a safety net (in case
 *     deactivation was skipped).
 *
 * @package CartShare
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Drop the custom carts table.
$table_name = $wpdb->prefix . 'cartshare_carts';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name uses $wpdb->prefix, safe.
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// 2. Delete all options whose names start with 'cartshare_'.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'cartshare_' ) . '%'
	)
);

// 3. Clear the scheduled cron event as a safety net.
wp_clear_scheduled_hook( 'cartshare_cleanup_event' );
