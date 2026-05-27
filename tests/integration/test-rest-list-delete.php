<?php
/**
 * Integration tests for CartShare REST /list and /delete/{token} endpoints.
 *
 * These tests run against a real WordPress + WooCommerce installation
 * (WP_TESTS_DIR must be set).  They exercise authentication boundaries and
 * ownership enforcement:
 *
 *   GET  /list
 *     - Anonymous request → 401.
 *     - Logged-in user → 200, only that user's own carts returned.
 *
 *   DELETE /delete/{token}
 *     - Owner deletes own cart → 200 + {deleted: true}.
 *     - User A tries to delete User B's cart → 403.
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
 * Class Test_CartShare_REST_ListDelete
 *
 * Integration tests for GET /list and DELETE /delete/{token}.
 */
class Test_CartShare_REST_ListDelete extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * CartShare database wrapper.
	 *
	 * @var CartShare_DB
	 */
	protected $db;

	/**
	 * Set up the REST server and CartShare prerequisites before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Ensure CartShare table exists.
		CartShare_Activator::activate();

		// Boot CartShare classes + REST routes.
		if ( class_exists( 'CartShare_Plugin' ) ) {
			CartShare_Plugin::instance()->boot();
		}

		// Fresh REST server with all routes registered.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init', $wp_rest_server );

		$this->db = new CartShare_DB();

		// Start anonymous.
		wp_set_current_user( 0 );
	}

	/**
	 * Restore global state after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helper: minimal cart data
	// -------------------------------------------------------------------------

	/**
	 * Return a minimal cart_data array suitable for DB inserts.
	 *
	 * @return array
	 */
	private function sample_cart_data(): array {
		return array(
			'items'   => array(
				array(
					'product_id'     => 1,
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
	// /list tests
	// -------------------------------------------------------------------------

	/**
	 * An anonymous (unauthenticated) request to GET /list must return HTTP 401.
	 *
	 * The check_logged_in permission callback returns WP_Error with status 401
	 * when is_user_logged_in() is false.
	 */
	public function test_list_anonymous_request_returns_401() {
		// Ensure no user is set.
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/cartshare/v1/list' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * A logged-in user must only see their own saved carts, not those of other users.
	 *
	 * Inserts one cart for User A and one for User B, then asserts that User A's
	 * GET /list response contains exactly one cart (their own).
	 */
	public function test_list_logged_in_user_sees_only_own_carts() {
		// Create two distinct users.
		$user_a_id = $this->factory->user->create();
		$user_b_id = $this->factory->user->create();

		// Insert one cart row owned by User A, one by User B.
		$token_a = $this->db->insert( $this->sample_cart_data(), $user_a_id, null, 86400, 'Cart A' );
		$token_b = $this->db->insert( $this->sample_cart_data(), $user_b_id, null, 86400, 'Cart B' );

		$this->assertIsString( $token_a );
		$this->assertIsString( $token_b );

		// Log in as User A.
		wp_set_current_user( $user_a_id );

		$request  = new WP_REST_Request( 'GET', '/cartshare/v1/list' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$carts = $response->get_data();
		$this->assertIsArray( $carts );
		$this->assertCount( 1, $carts, 'User A must see exactly one cart.' );
		$this->assertSame( $token_a, $carts[0]['token'], 'The returned cart must be the one owned by User A.' );

		// Confirm User B's cart token is NOT in the list.
		$returned_tokens = array_column( $carts, 'token' );
		$this->assertNotContains( $token_b, $returned_tokens, "User B's cart must not appear in User A's list." );
	}

	// -------------------------------------------------------------------------
	// /delete tests
	// -------------------------------------------------------------------------

	/**
	 * A user can successfully delete their own cart and receive HTTP 200.
	 *
	 * After deletion the cart row must no longer exist in the database.
	 */
	public function test_delete_own_cart_returns_200_and_removes_db_row() {
		$user_id = $this->factory->user->create();

		// Insert a cart owned by this user.
		$token = $this->db->insert( $this->sample_cart_data(), $user_id, null, 86400, null );
		$this->assertIsString( $token );

		// Log in as the owner.
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'DELETE', '/cartshare/v1/delete/' . $token );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'deleted', $data );
		$this->assertTrue( $data['deleted'] );

		// Verify the row is gone from the DB.
		$row = $this->db->find_by_token( $token );
		$this->assertNull( $row, 'Cart row must be removed from the database after deletion.' );
	}

	/**
	 * User A cannot delete a cart that belongs to User B — must return HTTP 403.
	 *
	 * The ownership check in CartShare_REST::delete() compares the caller's
	 * user_id against the stored user_id in the cart row.  A mismatch returns
	 * false from CartShare_DB::delete_by_token(), which the handler maps to 403.
	 * The row must remain in the database.
	 */
	public function test_delete_other_user_cart_returns_403() {
		$user_a_id = $this->factory->user->create();
		$user_b_id = $this->factory->user->create();

		// Insert a cart owned by User B.
		$token = $this->db->insert( $this->sample_cart_data(), $user_b_id, null, 86400, null );
		$this->assertIsString( $token );

		// Log in as User A (NOT the owner).
		wp_set_current_user( $user_a_id );

		$request  = new WP_REST_Request( 'DELETE', '/cartshare/v1/delete/' . $token );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );

		// Confirm the row still exists — User B's cart must not have been deleted.
		$row = $this->db->find_by_token( $token );
		$this->assertNotNull( $row, "User B's cart must still exist after a rejected delete attempt." );
	}
}
