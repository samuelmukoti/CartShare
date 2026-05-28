<?php
/**
 * Integration tests for the CartShare REST /save endpoint.
 *
 * These tests run against a real WordPress + WooCommerce installation
 * (WP_TESTS_DIR must be set).  They exercise the full request lifecycle:
 * permission callback → save handler → CartShare_DB insert → response.
 *
 * Test matrix:
 *  1. Happy path — valid nonce, non-empty cart → 200 + 32-char token + DB row.
 *  2. Missing nonce — no X-WP-Nonce header → 403.
 *  3. Empty cart — valid nonce but no cart items → 400.
 *
 * @package CartShare\Tests\Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Integration tests require the WordPress test framework (WP_TESTS_DIR).
// When running the unit testsuite without WP_TESTS_DIR this file is skipped
// gracefully so it produces 0 tests rather than a fatal error.
if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	return;
}

/**
 * Class Test_CartShare_REST_Save
 *
 * Integration tests for POST cartshare/v1/save.
 */
class Test_CartShare_REST_Save extends WP_UnitTestCase {

	/**
	 * REST server instance for this test run.
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

		// Guarantee CartShare classes + REST routes are wired up.
		// The plugin's plugins_loaded callback may have fired before the test
		// bootstrap required cartshare.php, so we boot explicitly.
		if ( class_exists( 'CartShare_Plugin' ) ) {
			CartShare_Plugin::instance()->boot();
		}

		// Initialise a fresh REST server and register all routes.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init', $wp_rest_server );

		$this->db = new CartShare_DB();

		// Start each test as an anonymous user.
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

		// Leave the cart empty so other tests start clean.
		if ( null !== WC()->cart ) {
			WC()->cart->empty_cart();
		}

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helper: initialise WooCommerce cart
	// -------------------------------------------------------------------------

	/**
	 * Attempt to initialise the WooCommerce cart and return whether it succeeded.
	 *
	 * Marks the test as skipped when the cart cannot be set up — this prevents
	 * a confusing failure when running in an environment where WooCommerce's
	 * session handler is unavailable (e.g. very minimal WP-CLI stubs).
	 *
	 * @return bool True when the cart is ready.
	 */
	private function init_wc_cart(): bool {
		if ( ! function_exists( 'wc_load_cart' ) ) {
			$this->markTestSkipped( 'wc_load_cart() is not available — WooCommerce not fully loaded.' );
			return false; // Never reached; silences static analysis warnings.
		}

		wc_load_cart();

		if ( null === WC()->cart ) {
			$this->markTestSkipped( 'WooCommerce cart could not be initialised.' );
			return false;
		}

		WC()->cart->empty_cart();

		return true;
	}

	/**
	 * Create and save a minimal WC_Product_Simple and return its ID.
	 *
	 * @return int Product ID.
	 */
	private function create_test_product(): int {
		$product = new WC_Product_Simple();
		$product->set_name( 'CartShare Integration Test Product' );
		$product->set_regular_price( '12.00' );
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );

		return $product->save();
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * Happy path: authenticated request with valid nonce and non-empty cart.
	 *
	 * Expects:
	 *  - HTTP 200
	 *  - Response body contains a 32-char alphanumeric token
	 *  - Response body contains a share_url
	 *  - Corresponding row exists in {prefix}cartshare_carts
	 */
	public function test_save_happy_path_returns_200_with_token_and_db_row() {
		$this->init_wc_cart();

		// Create a product and add it to the cart.
		$product_id = $this->create_test_product();
		$added      = WC()->cart->add_to_cart( $product_id );

		if ( false === $added ) {
			$this->markTestSkipped( 'add_to_cart() returned false — product or WC session not available.' );
		}

		// Log in and create a valid wp_rest nonce.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );
		$nonce = wp_create_nonce( 'wp_rest' );

		$request = new WP_REST_Request( 'POST', '/cartshare/v1/save' );
		$request->add_header( 'X-WP-Nonce', $nonce );

		$response = $this->server->dispatch( $request );

		// Assert HTTP 200.
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		// Assert token shape.
		$this->assertArrayHasKey( 'token', $data, 'Response must contain a token.' );
		$this->assertSame( 32, strlen( $data['token'] ), 'Token must be exactly 32 characters.' );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{32}$/', $data['token'], 'Token must be alphanumeric.' );

		// Assert share_url present.
		$this->assertArrayHasKey( 'share_url', $data, 'Response must contain a share_url.' );
		$this->assertStringContainsString( $data['token'], $data['share_url'], 'share_url must embed the token.' );

		// Assert DB row was created.
		$row = $this->db->find_by_token( $data['token'] );
		$this->assertNotNull( $row, 'A row must be present in cartshare_carts after save.' );
		$this->assertSame( $data['token'], $row['token'] );
	}

	/**
	 * Request without an X-WP-Nonce header must be rejected with HTTP 403.
	 *
	 * No DB row should be created.
	 */
	public function test_save_missing_nonce_returns_403() {
		$request = new WP_REST_Request( 'POST', '/cartshare/v1/save' );
		// Deliberately omit X-WP-Nonce header.

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Saving an empty cart must be rejected with HTTP 400 and a descriptive code.
	 *
	 * The response body must carry the cartshare_empty_cart error code so the
	 * frontend can surface a meaningful message.  No DB row must be inserted.
	 */
	public function test_save_empty_cart_returns_400() {
		$this->init_wc_cart();
		// Cart is empty — nothing was added.

		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );
		$nonce = wp_create_nonce( 'wp_rest' );

		$request = new WP_REST_Request( 'POST', '/cartshare/v1/save' );
		$request->add_header( 'X-WP-Nonce', $nonce );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data, 'Error response must carry a code key.' );
		$this->assertSame( 'cartshare_empty_cart', $data['code'] );
	}
}
