<?php
/**
 * Unit tests for CartShare_Analytics.
 *
 * @package CartShare\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * In-memory wpdb fake covering the subset of the API used by CartShare_Analytics.
 *
 * Stores rows for the events and carts tables and answers the specific COUNT /
 * GROUP BY / SELECT queries the analytics class issues by pattern-matching the
 * already-prepared SQL.
 */
class Fake_Analytics_WPDB {

	/** @var string Table prefix. */
	public $prefix = 'wp_';

	/** @var string Last DB error. */
	public $last_error = '';

	/** @var int Last insert ID. */
	public $insert_id = 0;

	/** @var array[] Events table rows. */
	public $events = array();

	/** @var array[] Carts table rows. */
	public $carts = array();

	/** @var int Auto-increment counter. */
	private $next_id = 1;

	/**
	 * Insert a row into the events or carts store.
	 *
	 * @param string     $table  Table name.
	 * @param array      $data   Row data.
	 * @param array|null $format Ignored.
	 * @return int
	 */
	public function insert( $table, $data, $format = null ) {
		$data['id']      = $this->next_id++;
		$this->insert_id = $data['id'];

		if ( false !== strpos( $table, 'cartshare_events' ) ) {
			$this->events[] = $data;
		} elseif ( false !== strpos( $table, 'cartshare_carts' ) ) {
			$this->carts[] = $data;
		}
		return 1;
	}

	/**
	 * Update matching events rows (used for order attribution).
	 *
	 * @param string $table        Table name.
	 * @param array  $data         Columns to set.
	 * @param array  $where        WHERE conditions.
	 * @param mixed  $format       Ignored.
	 * @param mixed  $where_format Ignored.
	 * @return int
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$updated = 0;
		foreach ( $this->events as &$row ) {
			$match = true;
			foreach ( $where as $k => $v ) {
				if ( ! isset( $row[ $k ] ) || (int) $row[ $k ] !== (int) $v ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				foreach ( $data as $k => $v ) {
					$row[ $k ] = $v;
				}
				++$updated;
			}
		}
		unset( $row );
		return $updated;
	}

	/**
	 * Substitute %s/%d placeholders. Accepts a trailing array of args (as wpdb does).
	 *
	 * @param string $sql     SQL with placeholders.
	 * @param mixed  ...$args Values, or a single array of values.
	 * @return string
	 */
	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$i = 0;
		return preg_replace_callback(
			'/%[sd]/',
			function ( $m ) use ( &$i, $args ) {
				$v = $args[ $i++ ] ?? '';
				return '%s' === $m[0] ? "'" . addslashes( (string) $v ) . "'" : (int) $v;
			},
			$sql
		);
	}

	/**
	 * Answer the COUNT(*) queries for events and carts.
	 *
	 * @param string $sql Prepared SQL.
	 * @return string Count as string (as real wpdb returns).
	 */
	public function get_var( $sql ) {
		if ( false !== strpos( $sql, 'cartshare_events' ) ) {
			return (string) count( $this->filter_events( $sql ) );
		}
		if ( false !== strpos( $sql, 'cartshare_carts' ) ) {
			return (string) count( $this->filter_carts( $sql ) );
		}
		return '0';
	}

	/**
	 * Answer the GROUP BY channel and SELECT cart_data queries.
	 *
	 * @param string $sql    Prepared SQL.
	 * @param string $output Ignored.
	 * @return array
	 */
	public function get_results( $sql, $output = 'OBJECT' ) {
		if ( false !== strpos( $sql, 'GROUP BY channel' ) ) {
			$totals = array();
			foreach ( $this->filter_events( $sql ) as $row ) {
				$channel = (string) ( $row['channel'] ?? '' );
				if ( '' === $channel ) {
					continue;
				}
				$totals[ $channel ] = ( $totals[ $channel ] ?? 0 ) + 1;
			}
			arsort( $totals );
			$out = array();
			foreach ( $totals as $channel => $total ) {
				$out[] = array(
					'channel' => $channel,
					'total'   => $total,
				);
			}
			return $out;
		}

		if ( false !== strpos( $sql, 'cart_data' ) ) {
			$out = array();
			foreach ( $this->filter_carts( $sql ) as $row ) {
				$out[] = array( 'cart_data' => $row['cart_data'] );
			}
			return $out;
		}

		return array();
	}

	/**
	 * Apply event_type / order_id / created_at filters parsed from the SQL.
	 *
	 * @param string $sql Prepared SQL.
	 * @return array[]
	 */
	private function filter_events( $sql ) {
		$type           = null;
		$converted_only = ( false !== strpos( $sql, 'order_id IS NOT NULL' ) );
		$since          = null;

		if ( false !== strpos( $sql, 'GROUP BY channel' ) ) {
			$type = 'save';
		} elseif ( preg_match( "/event_type = '([^']+)'/", $sql, $m ) ) {
			$type = $m[1];
		}
		if ( preg_match( "/created_at >= '([^']+)'/", $sql, $m ) ) {
			$since = $m[1];
		}

		return array_filter(
			$this->events,
			function ( $row ) use ( $type, $converted_only, $since ) {
				if ( null !== $type && ( $row['event_type'] ?? '' ) !== $type ) {
					return false;
				}
				if ( $converted_only && empty( $row['order_id'] ) ) {
					return false;
				}
				if ( null !== $since && (string) $row['created_at'] < $since ) {
					return false;
				}
				return true;
			}
		);
	}

	/**
	 * Apply the optional created_at filter to cart rows.
	 *
	 * @param string $sql Prepared SQL.
	 * @return array[]
	 */
	private function filter_carts( $sql ) {
		$since = null;
		if ( preg_match( "/created_at >= '([^']+)'/", $sql, $m ) ) {
			$since = $m[1];
		}
		if ( null === $since ) {
			return $this->carts;
		}
		return array_filter(
			$this->carts,
			function ( $row ) use ( $since ) {
				return (string) $row['created_at'] >= $since;
			}
		);
	}
}

/**
 * WooCommerce session stub with get/set backed by an array.
 */
class Fake_WC_Session {
	/** @var array */
	private $data = array();

	/**
	 * Get a session value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return $this->data[ $key ] ?? $default;
	}

	/**
	 * Set a session value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->data[ $key ] = $value;
	}
}

/**
 * Class Test_CartShare_Analytics
 */
class Test_CartShare_Analytics extends TestCase {

	/** @var Fake_Analytics_WPDB */
	protected $wpdb;

	/** @var CartShare_Analytics */
	protected $analytics;

	/**
	 * Fresh fake wpdb + analytics instance per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->wpdb           = new Fake_Analytics_WPDB();
		$GLOBALS['wpdb']      = $this->wpdb;
		$this->analytics      = new CartShare_Analytics();
		$GLOBALS['cartshare_wc_instance'] = null;
	}

	/**
	 * Clean up globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		$GLOBALS['cartshare_wc_instance'] = null;
		parent::tearDown();
	}

	/**
	 * Insert a cart row directly with an explicit created_at and product list.
	 *
	 * @param int[]  $product_ids Product IDs to embed in the cart payload.
	 * @param string $created_at  UTC datetime.
	 * @return void
	 */
	private function seed_cart( array $product_ids, string $created_at ) {
		$items = array();
		foreach ( $product_ids as $pid ) {
			$items[] = array( 'product_id' => $pid, 'quantity' => 1 );
		}
		$this->wpdb->carts[] = array(
			'id'         => count( $this->wpdb->carts ) + 1,
			'cart_data'  => wp_json_encode( array( 'items' => $items, 'coupons' => array() ) ),
			'created_at' => $created_at,
		);
	}

	/**
	 * record() stores a valid event and returns its ID.
	 */
	public function test_record_returns_id_and_stores_row() {
		$id = $this->analytics->record( 'save', 'email', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 5 );

		$this->assertSame( 1, $id );
		$this->assertCount( 1, $this->wpdb->events );
		$this->assertSame( 'save', $this->wpdb->events[0]['event_type'] );
		$this->assertSame( 'email', $this->wpdb->events[0]['channel'] );
		$this->assertSame( 5, $this->wpdb->events[0]['user_id'] );
	}

	/**
	 * Guest events (no user) store a NULL user_id — no guest PII persisted.
	 */
	public function test_record_anonymizes_guest_user_id() {
		$this->analytics->record_save( 'copy_link', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', null );
		$this->assertNull( $this->wpdb->events[0]['user_id'] );

		// A zero user ID is also treated as a guest.
		$this->analytics->record_save( 'copy_link', 'cccccccccccccccccccccccccccccccc', 0 );
		$this->assertNull( $this->wpdb->events[1]['user_id'] );
	}

	/**
	 * record() rejects unknown event types.
	 */
	public function test_record_rejects_invalid_type() {
		$this->assertFalse( $this->analytics->record( 'bogus', null, null, 1 ) );
		$this->assertCount( 0, $this->wpdb->events );
	}

	/**
	 * range_to_since() returns null for all-time and a past cutoff for windows.
	 */
	public function test_range_to_since() {
		$this->assertNull( $this->analytics->range_to_since( 'all' ) );

		$since = $this->analytics->range_to_since( '7d' );
		$this->assertIsString( $since );
		$this->assertLessThan( gmdate( 'Y-m-d H:i:s' ), $since );

		// Unknown ranges fall back to the 30-day default (non-null cutoff).
		$this->assertIsString( $this->analytics->range_to_since( 'nope' ) );
	}

	/**
	 * get_summary() reports saved, restored, conversions, rate, and channels.
	 */
	public function test_get_summary_aggregates_metrics() {
		$now = gmdate( 'Y-m-d H:i:s' );

		// Two saved carts.
		$this->seed_cart( array( 10, 20 ), $now );
		$this->seed_cart( array( 10 ), $now );

		// Share events across channels.
		$this->analytics->record_save( 'email', 'tok1', 1 );
		$this->analytics->record_save( 'email', 'tok2', null );
		$this->analytics->record_save( 'copy_link', 'tok3', 2 );

		// Two restores; one will convert to an order.
		$r1 = $this->analytics->record( 'restore', null, 'tok1', 1 );
		$this->analytics->record( 'restore', null, 'tok2', null );

		// Attribute an order to the first restore.
		$this->wpdb->update( 'wp_cartshare_events', array( 'order_id' => 99 ), array( 'id' => $r1 ) );

		$summary = $this->analytics->get_summary( 'all' );

		$this->assertSame( 2, $summary['total_saved'] );
		$this->assertSame( 2, $summary['total_restored'] );
		$this->assertSame( 1, $summary['conversions'] );
		$this->assertSame( 50.0, $summary['conversion_rate'] );
		$this->assertSame( 2, $summary['saves_by_channel']['email'] );
		$this->assertSame( 1, $summary['saves_by_channel']['copy_link'] );
	}

	/**
	 * Conversion rate is 0 (not a divide-by-zero) when there are no restores.
	 */
	public function test_conversion_rate_zero_without_restores() {
		$summary = $this->analytics->get_summary( 'all' );
		$this->assertSame( 0.0, $summary['conversion_rate'] );
		$this->assertSame( 0, $summary['total_restored'] );
	}

	/**
	 * get_top_products() counts carts-containing-each-product, deduped per cart.
	 */
	public function test_get_top_products_tallies_carts_containing_product() {
		$now = gmdate( 'Y-m-d H:i:s' );

		// Product 10 appears in two carts; product 20 in one. Duplicate 10 in a
		// single cart must only count once for that cart.
		$this->seed_cart( array( 10, 10, 20 ), $now );
		$this->seed_cart( array( 10 ), $now );

		$top = $this->analytics->get_top_products( 'all', 10 );

		$this->assertSame( 10, $top[0]['product_id'] );
		$this->assertSame( 2, $top[0]['count'] );
		$this->assertSame( 20, $top[1]['product_id'] );
		$this->assertSame( 1, $top[1]['count'] );
	}

	/**
	 * The created_at cutoff excludes rows older than the window.
	 */
	public function test_range_filter_excludes_old_rows() {
		$now = gmdate( 'Y-m-d H:i:s' );
		$old = gmdate( 'Y-m-d H:i:s', time() - ( 60 * DAY_IN_SECONDS ) );

		$this->seed_cart( array( 1 ), $now );
		$this->seed_cart( array( 2 ), $old );

		$this->assertSame( 2, $this->analytics->get_summary( 'all' )['total_saved'] );
		$this->assertSame( 1, $this->analytics->get_summary( '7d' )['total_saved'] );
	}

	/**
	 * record_restore() stashes the event ID on the session and attribute_order()
	 * back-fills the order, lifting the conversion count.
	 */
	public function test_restore_attribution_via_session() {
		// Wire a fake WooCommerce session into the WC() stub.
		WC()->session = new Fake_WC_Session();

		$event_id = $this->analytics->record_restore( 'tok1', 7 );
		$this->assertSame( $event_id, WC()->session->get( CartShare_Analytics::SESSION_KEY ) );

		$this->analytics->attribute_order( 1234 );

		// The restore row now carries the order ID, and the session is cleared.
		$this->assertSame( 1234, $this->wpdb->events[0]['order_id'] );
		$this->assertNull( WC()->session->get( CartShare_Analytics::SESSION_KEY ) );

		$summary = $this->analytics->get_summary( 'all' );
		$this->assertSame( 1, $summary['conversions'] );
		$this->assertSame( 100.0, $summary['conversion_rate'] );
	}
}
