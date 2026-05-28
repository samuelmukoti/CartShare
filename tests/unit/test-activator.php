<?php
/**
 * Unit tests for CartShare_Activator and CartShare_Deactivator.
 *
 * @package CartShare\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Minimal wpdb stub for the activator tests.
 *
 * Only the methods called by CartShare_Activator::create_table() need to be
 * present; all others are intentionally omitted.
 */
class Stub_WPDB_Activator {

	/** @var string WordPress table prefix. */
	public $prefix = 'wp_';

	/** @var string Last database error. */
	public $last_error = '';

	/**
	 * Return an empty charset/collate string.
	 *
	 * @return string
	 */
	public function get_charset_collate() {
		return '';
	}

	/**
	 * Stub insert — always succeeds.
	 *
	 * @param string     $table  Table name.
	 * @param array      $data   Data array.
	 * @param array|null $format Ignored.
	 * @return int
	 */
	public function insert( $table, $data, $format = null ) {
		return 1;
	}

	/**
	 * Stub prepare — returns the raw SQL unchanged.
	 *
	 * @param string $sql SQL statement.
	 * @return string
	 */
	public function prepare( $sql ) {
		return $sql;
	}
}

/**
 * Class Test_CartShare_Activator
 *
 * Tests that CartShare_Activator::activate() creates the expected DB schema
 * and schedules the cleanup cron event, and that CartShare_Deactivator::deactivate()
 * removes that event.
 */
class Test_CartShare_Activator extends TestCase {

	/**
	 * Reset test state and prime globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		CartShare_Test_State::reset();
		$GLOBALS['wpdb']                  = new Stub_WPDB_Activator();
		$GLOBALS['cartshare_wc_instance'] = null;
	}

	/**
	 * Restore globals after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		CartShare_Test_State::reset();
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * activate() must call dbDelta() with SQL that creates the expected table
	 * structure.
	 */
	public function test_activate_creates_table_with_expected_schema() {
		CartShare_Activator::activate();

		$this->assertGreaterThan( 0, count( CartShare_Test_State::$dbdelta_sql ) );

		$sql = CartShare_Test_State::$dbdelta_sql[0];

		$this->assertStringContainsString( 'cartshare_carts', $sql );
		$this->assertStringContainsString( 'token', $sql );
		$this->assertStringContainsString( 'cart_data', $sql );
		$this->assertStringContainsString( 'expires_at', $sql );
		$this->assertStringContainsString( 'UNIQUE KEY', $sql );
		$this->assertStringContainsString( 'PRIMARY KEY', $sql );
	}

	/**
	 * activate() must schedule the cartshare_cleanup_event cron hook.
	 */
	public function test_activate_schedules_cron_event() {
		CartShare_Activator::activate();

		$ts = wp_next_scheduled( 'cartshare_cleanup_event' );

		$this->assertIsInt( $ts );
		$this->assertGreaterThan( 0, $ts );
	}

	/**
	 * deactivate() must remove the cartshare_cleanup_event that activate() scheduled.
	 */
	public function test_deactivate_clears_cron_event() {
		CartShare_Activator::activate();

		$this->assertNotFalse( wp_next_scheduled( 'cartshare_cleanup_event' ) );

		CartShare_Deactivator::deactivate();

		$this->assertFalse( wp_next_scheduled( 'cartshare_cleanup_event' ) );
	}
}
