<?php
/**
 * Plugin activation handler.
 *
 * Creates the custom database table, schedules the daily cleanup cron event,
 * and records the current DB schema version on option.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Activator
 *
 * Fired during plugin activation.
 */
class CartShare_Activator {

	/**
	 * Current DB schema version.
	 *
	 * Bump this constant whenever the table structure changes so that
	 * existing installs know to run dbDelta() again on upgrade.
	 *
	 * 1.1.0 — added the {prefix}cartshare_events analytics log table.
	 */
	const DB_VERSION = '1.1.0';

	/**
	 * Run all activation tasks.
	 *
	 * - Creates / upgrades the {prefix}cartshare_carts table via dbDelta().
	 * - Schedules the daily cartshare_cleanup_event if it is not already queued.
	 * - Updates the cartshare_db_version option.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_table();
		self::create_events_table();
		self::schedule_cleanup();
		self::flush_rewrite_rules();
		update_option( 'cartshare_db_version', self::DB_VERSION );
	}

	/**
	 * Upgrade the schema on existing installs when DB_VERSION has changed.
	 *
	 * The activation hook only fires when a plugin is (re)activated, so sites
	 * that update the plugin via WordPress.org / Composer never re-run
	 * activate(). This method is called on every admin request from
	 * CartShare_Plugin::boot(); the dbDelta() calls only run when the stored
	 * cartshare_db_version option lags behind the bundled DB_VERSION, so the
	 * happy path is a single get_option() comparison.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::DB_VERSION === get_option( 'cartshare_db_version' ) ) {
			return;
		}

		self::create_table();
		self::create_events_table();
		update_option( 'cartshare_db_version', self::DB_VERSION );
	}

	/**
	 * Create or upgrade the cartshare_carts table using dbDelta().
	 *
	 * dbDelta() compares the supplied SQL against the existing table and adds
	 * any missing columns or indexes — it never removes them, so existing data
	 * is always safe. The function lives in wp-admin/includes/upgrade.php which
	 * is NOT loaded automatically on the front-end, so we require it explicitly.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	private static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'cartshare_carts';
		$charset_collate = $wpdb->get_charset_collate();

		/*
		 * IMPORTANT: dbDelta() is extremely picky about SQL formatting:
		 *   - Each field definition must end with a comma on its own line.
		 *   - PRIMARY KEY, UNIQUE KEY, and KEY lines must follow the fields.
		 *   - There must be exactly two spaces between "CREATE TABLE" and the
		 *     table name (one in the "CREATE TABLE" keyword pair is fine — but
		 *     many devs miss the two spaces before the opening paren).
		 *   - The closing ");" must be on its own line immediately followed by
		 *     the collation string.
		 */
		$sql = "CREATE TABLE {$table_name} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token VARCHAR(32) NOT NULL,
  user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  guest_id VARCHAR(255) NULL DEFAULT NULL,
  name VARCHAR(255) NULL DEFAULT NULL,
  cart_data LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY token (token),
  KEY user_id (user_id),
  KEY guest_id (guest_id),
  KEY expires_at (expires_at)
) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create or upgrade the cartshare_events analytics log table via dbDelta().
	 *
	 * One row is written per share/restore action. The table is intentionally
	 * privacy-light: only the WordPress user_id of logged-in users is stored —
	 * guest actions record a NULL user_id, so no guest session ID, IP, or other
	 * PII ever lands here. order_id is back-filled when a restored cart results
	 * in a placed order, which powers the restore-to-order conversion metric.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	private static function create_events_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'cartshare_events';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type VARCHAR(20) NOT NULL,
  channel VARCHAR(20) NULL DEFAULT NULL,
  token VARCHAR(32) NULL DEFAULT NULL,
  user_id BIGINT UNSIGNED NULL DEFAULT NULL,
  order_id BIGINT UNSIGNED NULL DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  KEY event_type (event_type),
  KEY created_at (created_at),
  KEY token (token)
) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Schedule the daily cron cleanup event if it is not already scheduled.
	 *
	 * Uses the WordPress daily recurrence so that expired cart rows are
	 * removed automatically. The event callback is registered in
	 * CartShare_Cron::init_hooks().
	 *
	 * @return void
	 */
	private static function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'cartshare_cleanup_event' ) ) {
			wp_schedule_event( time(), 'daily', 'cartshare_cleanup_event' );
		}
	}

	/**
	 * Flush rewrite rules so My Account endpoint is available immediately.
	 *
	 * CartShare_MyAccount registers the 'saved-carts' rewrite endpoint on
	 * init — activation fires before that hook runs in the request where the
	 * plugin is first activated, so we flush here to avoid a 404 on the first
	 * visit to /my-account/saved-carts/.
	 *
	 * @return void
	 */
	private static function flush_rewrite_rules() {
		flush_rewrite_rules();
	}
}
