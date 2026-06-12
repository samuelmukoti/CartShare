<?php
/**
 * Integration tests for the CartShare REST /restore/{token} endpoint.
 *
 * These tests run against a real WordPress + WooCommerce installation
 * (WP_TESTS_DIR must be set).  They exercise the restore lifecycle:
 * DB lookup → expiry check → CartShare_Cart::restore() → response.
 *
 * Test matrix:
 *  1. Valid token → 200, warnings array present, cart restored.
 *  2. Missing / non-existent token (valid format) → 404.
 *  3. Expired token → 404 (no existence leakage).
 *  4. Malformed token (fails REST router regex) → 404 from REST router.
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
 * Class Test_CartShare_REST_Restore
 *
 * Integration tests for GET cartshare/v1/restore/{token}.
 */
class Test_CartShare_REST_Restore extends WP_UnitTestCase {

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

		// Ensure CartShare table exists in the test DB.
		CartShare_Activator::activate();

		// Boot CartShare classes + REST routes.
		if ( class_exists( 'CartShare_Plugin' ) ) {
			CartShare_Plugin::instance()->boot();
		}

		// Fresh REST server with CartShare routes registered.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init', $wp_rest_server );

		$this->db = new CartShare_DB();

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

		if ( null !== WC()->cart ) {
			WC()->cart->empty_cart();
		}

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helper: sample cart data
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
					'product_id'     => 99,
					'variation_id'   => 0,
					'quantity'       => 2,
					'variation'      => array(),
					'cart_item_data' => array(),
				),
			),
			'coupons' => array( 'SAVE10' ),
		);
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * A valid, non-expired token must return HTTP 200 with a warnings array.
	 *
	 * The response must include:
	 *  - "warnings" — array (may be non-empty if products are unavailable in test DB)
	 *  - "redirect_url" — a non-empty URL string
	 *
	 * Note: the restore itself may produce warnings if product #99 does not exist
	 * in the test database.  That is the expected graceful-degradation behaviour.
	 * The important assertions are on the status code and response shape.
	 */
	public function test_restore_valid_token_returns_200_with_warnings_array() {
		// Initialise WooCommerce cart so restore can execute.
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( null === WC()->cart ) {
			$this->markTestSkipped( 'WooCommerce cart not initialised.' );
		}

		// Insert a valid (non-expired) cart row.
		$token = $this->db->insert( $this->sample_cart_data(), null, null, 86400, null );
		$this->assertIsString( $token, 'Insert must return a token string.' );

		$request  = new WP_REST_Request( 'GET', '/cartshare/v1/restore/' . $token );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'warnings', $data, 'Response must contain a warnings key.' );
		$this->assertIsArray( $data['warnings'], 'warnings must be an array.' );
		$this->assertArrayHasKey( 'redirect_url', $data, 'Response must contain a redirect_url.' );
		$this->assertNotEmpty( $data['redirect_url'], 'redirect_url must not be empty.' );
	}

	/**
	 * A token that does not exist in the DB (valid 32-char format) must return 404.
	 *
	 * The error must not reveal whether the token ever existed (no leakage).
	 */
	public function test_restore_nonexistent_token_returns_404() {
		// Craft a valid-format token that was never inserted.
		$token = str_repeat( 'a', 31 ) . 'Z'; // 32 alphanumeric chars, not in DB.

		$request  = new WP_REST_Request( 'GET', '/cartshare/v1/restore/' . $token );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data, 'Error response must have a code key.' );
		$this->assertSame( 'cartshare_not_found', $data['code'] );
	}

	/**
	 * An expired token (expires_at in the past) must return 404.
	 *
	 * The handler must treat expired carts the same as missing ones so that
	 * callers cannot infer existence from the error code or message.
	 */
	public function test_restore_expired_token_returns_404() {
		// Insert a row that expired one hour ago (negative TTL).
		$token = $this->db->insert( $this->sample_cart_data(), null, null, -3600, null );
		$this->assertIsString( $token, 'Insert must return a token string even for past-expiry TTL.' );

		$request  = new WP_REST_Request( 'GET', '/cartshare/v1/restore/' . $token );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertSame( 'cartshare_not_found', $data['code'] );
	}

	/**
	 * A malformed token (does not match the route regex) must produce a 404.
	 *
	 * The route is registered as `/restore/(?P<token>[A-Za-z0-9]{32})`.  Any URL
	 * segment that fails this regex is unmatched by the REST router, which returns
	 * `rest_no_route` / HTTP 404 before the handler is ever invoked.
	 *
	 * Tested variants:
	 *  - Token with hyphens (invalid chars).
	 *  - Token shorter than 32 characters.
	 */
	public function test_restore_malformed_token_returns_404_from_router() {
		// Variant 1: token contains hyphens (illegal characters).
		$request  = new WP_REST_Request( 'GET', '/cartshare/v1/restore/bad-token-with-hyphens' );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 404, $response->get_status(), 'Token with hyphens should 404 at router.' );

		// Variant 2: token is only 10 chars (too short).
		$request  = new WP_REST_Request( 'GET', '/cartshare/v1/restore/shorttoken' );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 404, $response->get_status(), 'Short token should 404 at router.' );
	}
}
