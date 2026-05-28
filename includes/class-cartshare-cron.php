<?php
/**
 * WP-Cron cleanup job for CartShare.
 *
 * Registers a callback on the 'cartshare_cleanup_event' action that deletes
 * expired cart rows from the database.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Cron
 *
 * Hooks into the scheduled 'cartshare_cleanup_event' WP-Cron action and
 * delegates expired-row deletion to CartShare_DB::delete_expired().
 */
class CartShare_Cron {

	/**
	 * CartShare_DB instance.
	 *
	 * @var CartShare_DB
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param CartShare_DB $db CartShare database instance.
	 */
	public function __construct( CartShare_DB $db ) {
		$this->db = $db;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'cartshare_cleanup_event', array( $this, 'run_cleanup' ) );
	}

	/**
	 * Delete all expired cart rows.
	 *
	 * Called by the 'cartshare_cleanup_event' WP-Cron action.
	 *
	 * @return int Number of rows deleted.
	 */
	public function run_cleanup(): int {
		return $this->db->delete_expired();
	}
}
