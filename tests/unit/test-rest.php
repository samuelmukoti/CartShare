<?php
/**
 * Unit tests for CartShare_REST::save().
 *
 * Tests the admin cart-builder path: explicit items bypass WC()->cart,
 * source tag passes through to CartShare_DB::insert(), and sanitize_items()
 * strips invalid entries and clamps quantities.
 *
 * @package CartShare\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * CartShare_DB stub that records arguments passed to insert() and returns a
 * fixed token so tests can inspect what CartShare_REST passes down.
 *
 * Extends CartShare_DB to satisfy the type hint on CartShare_REST::__construct().
 * All other CartShare_DB methods are inherited but never called in these tests.
 */
class Stub_CartShare_DB_REST extends CartShare_DB {

	/** @var array|null Cart data from the last insert() call. */
	public $last_cart_data = null;

	/** @var string|null Source from the last insert() call. */
	public $last_source = null;

	/** @var int|null User ID from the last insert() call. */
	public $last_user_id = null;

	/** @var string Fixed token returned by insert(). */
	public $token = 'aabbccdd11223344aabbccdd11223344';

	/**
	 * Record arguments and return the fixed token.
	 *
	 * @param array       $cart_data   Cart payload.
	 * @param int|null    $user_id     WordPress user ID.
	 * @param string|null $guest_id    Guest session ID.
	 * @param int|null    $ttl_seconds TTL in seconds.
	 * @param string|null $name        Cart name.
	 * @param string|null $source      Source tag.
	 * @return string
	 */
	public function insert( array $cart_data, ?int $user_id, ?string $guest_id, ?int $ttl_seconds, ?string $name, ?string $source = null ) {
		$this->last_cart_data = $cart_data;
		$this->last_source    = $source;
		$this->last_user_id   = $user_id;
		return $this->token;
	}
}

/**
 * Class Test_CartShare_REST_Save
 *
 * Tests CartShare_REST::save() for the admin cart-builder explicit-items path.
 */
class Test_CartShare_REST_Save extends TestCase {

	/** @var Stub_CartShare_DB_REST */
	protected $stub_db;

	/** @var CartShare_REST */
	protected $rest;

	/**
	 * Set up a fresh REST handler and DB stub before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		CartShare_Test_State::reset();
		$GLOBALS['cartshare_wc_instance'] = null;
		// CartShare_DB's inherited methods reference $GLOBALS['wpdb']; provide a
		// minimal stub so construction doesn't fatal even though our override
		// never delegates to the parent insert().
		$GLOBALS['wpdb'] = new Stub_WPDB_Activator();

		$this->stub_db = new Stub_CartShare_DB_REST();
		$this->rest    = new CartShare_REST( $this->stub_db );
	}

	/**
	 * Restore global state after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		CartShare_Test_State::reset();
		$GLOBALS['cartshare_wc_instance'] = null;
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a WP_REST_Request stub pre-populated with the given parameters.
	 *
	 * @param array $params Key => value pairs to set on the request.
	 * @return WP_REST_Request
	 */
	private function make_request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request();
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		return $request;
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * When an explicit items array is supplied save() must use those items and
	 * must not touch WC()->cart (i.e. the WooCommerce cart session stays null).
	 */
	public function test_save_with_explicit_items() {
		$items = array(
			array(
				'product_id' => 10,
				'quantity'   => 2,
			),
		);

		$request  = $this->make_request( array( 'items' => $items ) );
		$response = $this->rest->save( $request );

		// WC()->cart must never have been accessed.
		$this->assertNull( WC()->cart );

		// DB insert must have been called with the provided product.
		$this->assertNotNull( $this->stub_db->last_cart_data );
		$stored_items = $this->stub_db->last_cart_data['items'];
		$this->assertCount( 1, $stored_items );
		$this->assertSame( 10, $stored_items[0]['product_id'] );
		$this->assertSame( 2, $stored_items[0]['quantity'] );

		// Response must contain the token.
		$data = $response->get_data();
		$this->assertSame( $this->stub_db->token, $data['token'] );
	}

	/**
	 * When source='admin-created' is included in the request, save() must pass
	 * that value through to CartShare_DB::insert().
	 */
	public function test_save_with_source_tag() {
		$items = array(
			array(
				'product_id' => 5,
				'quantity'   => 1,
			),
		);

		$request = $this->make_request(
			array(
				'items'  => $items,
				'source' => 'admin-created',
			)
		);

		$this->rest->save( $request );

		$this->assertSame( 'admin-created', $this->stub_db->last_source );
	}

	/**
	 * sanitize_items() (exercised via save()) must discard entries that have no
	 * product_id and must clamp quantity to a minimum of 1.
	 */
	public function test_sanitize_items_strips_invalid() {
		$items = array(
			// Missing product_id — must be stripped.
			array(
				'quantity' => 3,
			),
			// quantity === 0 — must be clamped to 1.
			array(
				'product_id' => 7,
				'quantity'   => 0,
			),
			// Valid entry — must pass through unchanged.
			array(
				'product_id' => 12,
				'quantity'   => 4,
			),
		);

		$request = $this->make_request( array( 'items' => $items ) );
		$this->rest->save( $request );

		$stored_items = $this->stub_db->last_cart_data['items'];

		// The entry without product_id must have been removed.
		$this->assertCount( 2, $stored_items );

		// First surviving item had quantity 0; must be clamped to 1.
		$this->assertSame( 7, $stored_items[0]['product_id'] );
		$this->assertSame( 1, $stored_items[0]['quantity'] );

		// Second surviving item passes through with its original quantity.
		$this->assertSame( 12, $stored_items[1]['product_id'] );
		$this->assertSame( 4, $stored_items[1]['quantity'] );
	}
}
