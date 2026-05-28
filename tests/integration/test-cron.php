<?php
/**
 * Integration tests for the CartShare WP-Cron cleanup event.
 *
 * These tests run against a real WordPress database (WP_TESTS_DIR must be set).
 * They verify that manually firing the 'cartshare_cleanup_event' action:
 *
 *   - Deletes rows whose expires_at is in the past.
 *   - Leaves rows whose expires_at is in the future (or NULL) untouched.
 *
 * The CartShare_Cron class is bootstrapped by CartShare_Plugin::boot().  Its
 * 'cartshare_cleanup_event' hook is added during boot, so do_action() correctly
 * invokes CartShare_Cron::run_cleanup() → CartShare_DB::delete_expired().
 *
 * @package CartShare\Tests\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Integration tests require the WordPress test framework (WP_TESTS_DIR).
if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	return;
}

/**
 * Class Test_CartShare_Cron
 *
 * Integration tests for the cartshare_cleanup_event cron callback.
 */
class Test_CartShare_Cron extends WP_UnitTestCase {

	/**
	 * CartShare database wrapper used for inserting and verifying test rows.
	 *
	 * @var CartShare_DB
	 */
	protected $db;

	/**
	 * Set up the CartShare database table and boot the plugin before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Ensure {prefix}cartshare_carts exists in the test DB.
		CartShare_Activator::activate();

		// Boot CartShare (idempotent) so CartShare_Cron::init_hooks() registers
		// the 'cartshare_cleanup_event' action callback.
		if ( class_exists( 'CartShare_Plugin' ) ) {
			CartShare_Plugin::instance()->boot();
		}

		$this->db = new CartShare_DB();
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Return a minimal cart_data array for DB inserts.
	 *
	 * @return array
	 */
	private function sample_cart_data(): array {
		return array(
			'items'   => array(
				array(
					'product_id'     => 42,
					'variation_id'   => 0,
					'quantity'       => 1,
					'variation'      => array(),
					'cart_item_data' => array(),
				),
			),
			'coupons' => array(),
		);
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * Firing cartshare_cleanup_event removes expired rows and leaves valid ones.
	 *
	 * Inserts:
	 *  - One row with TTL of -3 600 s (expired one hour ago).
	 *  - One row with TTL of +86 400 s (expires tomorrow).
	 *  - One row with no expiry (null TTL).
	 *
	 * After do_action('cartshare_cleanup_event'):
	 *  - The expired row must not be found.
	 *  - The future-expiry row must still be found.
	 *  - The no-expiry row must still be found.
	 */
	public function test_cleanup_event_removes_expired_rows_and_leaves_valid_ones() {
		$cart_data = $this->sample_cart_data();

		// Insert row that expired 1 hour ago.
		$expired_token = $this->db->insert( $cart_data, null, null, -3600, 'Expired Cart' );
		$this->assertIsString( $expired_token, 'Insert must return a string token.' );

		// Insert row that expires tomorrow.
		$future_token = $this->db->insert( $cart_data, null, null, 86400, 'Future Cart' );
		$this->assertIsString( $future_token );

		// Insert row with no expiry.
		$no_expiry_token = $this->db->insert( $cart_data, null, null, null, 'No Expiry Cart' );
		$this->assertIsString( $no_expiry_token );

		// Confirm all three rows exist before the cleanup runs.
		$this->assertNotNull( $this->db->find_by_token( $expired_token ), 'Expired row must exist before cleanup.' );
		$this->assertNotNull( $this->db->find_by_token( $future_token ), 'Future row must exist before cleanup.' );
		$this->assertNotNull( $this->db->find_by_token( $no_expiry_token ), 'No-expiry row must exist before cleanup.' );

		// Manually fire the scheduled cleanup event.
		do_action( 'cartshare_cleanup_event' );

		// Expired row must be deleted.
		$this->assertNull(
			$this->db->find_by_token( $expired_token ),
			'Expired cart row must be deleted by the cleanup event.'
		);

		// Future-expiry row must be untouched.
		$this->assertNotNull(
			$this->db->find_by_token( $future_token ),
			'Future-expiry cart row must NOT be deleted by the cleanup event.'
		);

		// No-expiry row must be untouched.
		$this->assertNotNull(
			$this->db->find_by_token( $no_expiry_token ),
			'No-expiry cart row must NOT be deleted by the cleanup event.'
		);
	}
}
