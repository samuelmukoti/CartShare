<?php
/**
 * Analytics event log + admin dashboard for CartShare.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Analytics
 *
 * Two responsibilities:
 *
 *  1. Event logging — record() writes one row to {prefix}cartshare_events per
 *     share/restore action. Only logged-in user IDs are stored; guest actions
 *     record a NULL user_id so no guest PII is ever persisted.
 *  2. Reporting — get_summary() / get_top_products() aggregate the event log
 *     (and the carts table) over a 7-day, 30-day, or all-time window, and the
 *     dashboard renders them under WooCommerce → CartShare Analytics.
 *
 * Restore-to-order conversion is attributed by stashing the restore event ID in
 * the WooCommerce session and back-filling order_id when that session places an
 * order (woocommerce_checkout_order_processed).
 */
class CartShare_Analytics {

	/**
	 * WooCommerce session key holding the restore event ID awaiting attribution.
	 */
	const SESSION_KEY = 'cartshare_restore_event';

	/**
	 * Supported reporting ranges mapped to their length in days (0 = all time).
	 *
	 * @var array<string,int>
	 */
	const RANGES = array(
		'7d'  => 7,
		'30d' => 30,
		'all' => 0,
	);

	/**
	 * Event types accepted by record().
	 *
	 * @var string[]
	 */
	private static $event_types = array( 'save', 'restore' );

	/**
	 * Return the events table name including the WordPress prefix.
	 *
	 * @return string
	 */
	private function events_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cartshare_events';
	}

	/**
	 * Return the carts table name including the WordPress prefix.
	 *
	 * @return string
	 */
	private function carts_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cartshare_carts';
	}

	/**
	 * Register WordPress / WooCommerce hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'attribute_order' ) );
	}

	// -------------------------------------------------------------------------
	// Event recording
	// -------------------------------------------------------------------------

	/**
	 * Record an analytics event.
	 *
	 * Guest actions (user_id null or 0) are stored with a NULL user_id so the
	 * log never contains guest-identifying data.
	 *
	 * @param string      $type    Event type — 'save' or 'restore'.
	 * @param string|null $channel Share channel (e.g. 'email', 'copy_link'); null for restores.
	 * @param string|null $token   Cart token the event relates to, if any.
	 * @param int|null    $user_id WordPress user ID for logged-in users, or null/0 for guests.
	 *
	 * @return int|false The new event ID on success, false on invalid type or DB failure.
	 */
	public function record( string $type, ?string $channel = null, ?string $token = null, ?int $user_id = null ) {
		if ( ! in_array( $type, self::$event_types, true ) ) {
			return false;
		}

		global $wpdb;

		$data = array(
			'event_type' => $type,
			'channel'    => $channel ? sanitize_key( $channel ) : null,
			'token'      => $token ? sanitize_text_field( $token ) : null,
			'user_id'    => $user_id && $user_id > 0 ? $user_id : null,
			'order_id'   => null,
			'created_at' => current_time( 'mysql', true ),
		);

		$format = array( '%s', '%s', '%s', '%d', '%d', '%s' );

		$result = $wpdb->insert( $this->events_table(), $data, $format ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Record a share event and return its ID.
	 *
	 * @param string   $channel Share channel slug.
	 * @param string   $token   Cart token.
	 * @param int|null $user_id WordPress user ID, or null for guests.
	 *
	 * @return int|false
	 */
	public function record_save( string $channel, string $token, ?int $user_id ) {
		return $this->record( 'save', $channel, $token, $user_id );
	}

	/**
	 * Record a restore event and stash its ID in the WooCommerce session so a
	 * subsequent order can be attributed back to it.
	 *
	 * @param string   $token   Cart token being restored.
	 * @param int|null $user_id WordPress user ID, or null for guests.
	 *
	 * @return int|false The restore event ID, or false on failure.
	 */
	public function record_restore( string $token, ?int $user_id ) {
		$event_id = $this->record( 'restore', null, $token, $user_id );

		if ( $event_id && function_exists( 'WC' ) ) {
			// Ensure the session store is initialised before checking it — on the
			// public restore request the WC session may not have been loaded yet,
			// and silently skipping the stash would lose restore-to-order
			// attribution (guests are the majority of share-link traffic).
			if ( ! WC()->session && function_exists( 'wc_load_session' ) ) {
				wc_load_session();
			}

			if ( WC()->session ) {
				WC()->session->set( self::SESSION_KEY, $event_id );
			}
		}

		return $event_id;
	}

	/**
	 * Back-fill order_id on the restore event tied to the current session.
	 *
	 * Hooked to woocommerce_checkout_order_processed: if the shopper restored a
	 * cart earlier in this session, the order is recorded against that restore
	 * event, which powers the restore-to-order conversion metric. The session
	 * key is cleared so a single restore converts at most one order.
	 *
	 * @param int $order_id The newly created WooCommerce order ID.
	 * @return void
	 */
	public function attribute_order( $order_id ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$event_id = (int) WC()->session->get( self::SESSION_KEY );
		if ( $event_id <= 0 ) {
			return;
		}

		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->events_table(),
			array( 'order_id' => (int) $order_id ),
			array( 'id' => $event_id ),
			array( '%d' ),
			array( '%d' )
		);

		WC()->session->set( self::SESSION_KEY, null );
	}

	// -------------------------------------------------------------------------
	// Reporting
	// -------------------------------------------------------------------------

	/**
	 * Translate a range key into a UTC cutoff datetime (NULL for all-time).
	 *
	 * @param string $range One of the keys in self::RANGES.
	 * @return string|null  'Y-m-d H:i:s' UTC cutoff, or null for the all-time range.
	 */
	public function range_to_since( string $range ): ?string {
		$days = self::RANGES[ $range ] ?? self::RANGES['30d'];
		if ( 0 === $days ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
	}

	/**
	 * Build the headline metrics for a reporting range.
	 *
	 * @param string $range One of the keys in self::RANGES.
	 *
	 * @return array{
	 *     total_saved:int,
	 *     total_restored:int,
	 *     conversions:int,
	 *     conversion_rate:float,
	 *     saves_by_channel:array<string,int>
	 * }
	 */
	public function get_summary( string $range ): array {
		$since          = $this->range_to_since( $range );
		$total_restored = $this->count_events( 'restore', $since, false );
		$conversions    = $this->count_events( 'restore', $since, true );

		return array(
			'total_saved'      => $this->count_carts( $since ),
			'total_restored'   => $total_restored,
			'conversions'      => $conversions,
			'conversion_rate'  => $total_restored > 0 ? round( ( $conversions / $total_restored ) * 100, 1 ) : 0.0,
			'saves_by_channel' => $this->get_saves_by_channel( $since ),
		);
	}

	/**
	 * Count events of a type since a cutoff, optionally only converted restores.
	 *
	 * @param string      $type           Event type.
	 * @param string|null $since          UTC cutoff, or null for all time.
	 * @param bool        $converted_only When true, only count rows with order_id set.
	 * @return int
	 */
	private function count_events( string $type, ?string $since, bool $converted_only ): int {
		global $wpdb;

		$table = $this->events_table();
		$where = 'WHERE event_type = %s';
		$args  = array( $type );
		if ( $converted_only ) {
			$where .= ' AND order_id IS NOT NULL';
		}
		if ( null !== $since ) {
			$where .= ' AND created_at >= %s';
			$args[] = $since;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $args ) );
	}

	/**
	 * Count carts created since a cutoff (the true "carts saved" figure).
	 *
	 * @param string|null $since UTC cutoff, or null for all time.
	 * @return int
	 */
	private function count_carts( ?string $since ): int {
		global $wpdb;

		$table = $this->carts_table();

		if ( null === $since ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $since ) );
	}

	/**
	 * Tally share events grouped by channel since a cutoff.
	 *
	 * @param string|null $since UTC cutoff, or null for all time.
	 * @return array<string,int> Channel slug => share count, ordered high to low.
	 */
	public function get_saves_by_channel( ?string $since ): array {
		global $wpdb;

		$table = $this->events_table();
		$where = "WHERE event_type = 'save' AND channel IS NOT NULL";
		$args  = array();
		if ( null !== $since ) {
			$where .= ' AND created_at >= %s';
			$args[] = $since;
		}

		$sql = "SELECT channel, COUNT(*) AS total FROM {$table} {$where} GROUP BY channel ORDER BY total DESC";

		if ( empty( $args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $sql, ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		}

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['channel'] ] = (int) $row['total'];
		}
		return $out;
	}

	/**
	 * Most-shared products: tally how many saved carts contained each product.
	 *
	 * Derived from the carts table (the authoritative record of what was saved)
	 * rather than the event log, since the cart payload holds the product IDs.
	 * Carts that have since expired and been pruned naturally drop out.
	 *
	 * @param string $range One of the keys in self::RANGES.
	 * @param int    $limit Maximum number of products to return.
	 *
	 * @return array<int,array{product_id:int,name:string,count:int}>
	 */
	public function get_top_products( string $range, int $limit = 10 ): array {
		global $wpdb;

		$since = $this->range_to_since( $range );
		$table = $this->carts_table();

		if ( null === $since ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( "SELECT cart_data FROM {$table}", ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT cart_data FROM {$table} WHERE created_at >= %s", $since ), ARRAY_A );
		}

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$cart  = json_decode( (string) $row['cart_data'], true );
			$items = ( is_array( $cart ) && isset( $cart['items'] ) ) ? (array) $cart['items'] : array();

			// One product is counted at most once per cart (carts-containing-it).
			$seen = array();
			foreach ( $items as $item ) {
				$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
				if ( $product_id <= 0 || isset( $seen[ $product_id ] ) ) {
					continue;
				}
				$seen[ $product_id ]   = true;
				$counts[ $product_id ] = ( $counts[ $product_id ] ?? 0 ) + 1;
			}
		}

		arsort( $counts );
		$counts = array_slice( $counts, 0, max( 0, $limit ), true );

		$out = array();
		foreach ( $counts as $product_id => $count ) {
			$name  = function_exists( 'get_the_title' ) ? (string) get_the_title( $product_id ) : '';
			$out[] = array(
				'product_id' => (int) $product_id,
				/* translators: %d: product ID used when the product title is unavailable. */
				'name'       => '' !== $name ? $name : sprintf( __( 'Product #%d', 'cartshare' ), $product_id ),
				'count'      => (int) $count,
			);
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Admin dashboard
	// -------------------------------------------------------------------------

	/**
	 * Register the CartShare Analytics submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'CartShare Analytics', 'cartshare' ),
			__( 'CartShare Analytics', 'cartshare' ),
			// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers manage_woocommerce.
			'manage_woocommerce',
			'cartshare-analytics',
			array( $this, 'render_dashboard' )
		);
	}

	/**
	 * Enqueue the shared admin stylesheet on the analytics page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'cartshare-analytics' ) ) {
			return;
		}

		wp_enqueue_style(
			'cartshare-admin',
			CARTSHARE_URL . 'assets/css/admin.css',
			array(),
			CARTSHARE_VERSION
		);
	}

	/**
	 * Resolve the requested range from the query string to a valid key.
	 *
	 * @return string
	 */
	private function current_range(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only range filter, no state change.
		$range = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '30d';
		return isset( self::RANGES[ $range ] ) ? $range : '30d';
	}

	/**
	 * Render the analytics dashboard via the admin-analytics template.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers manage_woocommerce.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cartshare' ) );
		}

		$range        = $this->current_range();
		$summary      = $this->get_summary( $range );
		$top_products = $this->get_top_products( $range, 10 );
		$ranges       = array(
			'7d'  => __( 'Last 7 days', 'cartshare' ),
			'30d' => __( 'Last 30 days', 'cartshare' ),
			'all' => __( 'All time', 'cartshare' ),
		);

		$template = CARTSHARE_PATH . 'templates/admin-analytics.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Human-readable label for a channel slug, falling back to a tidy title.
	 *
	 * @param string $channel Channel slug.
	 * @return string
	 */
	public function channel_label( string $channel ): string {
		$labels = array(
			'copy_link' => __( 'Copy link', 'cartshare' ),
			'email'     => __( 'Email', 'cartshare' ),
			'print'     => __( 'Print', 'cartshare' ),
			'facebook'  => __( 'Facebook', 'cartshare' ),
			'messenger' => __( 'Messenger', 'cartshare' ),
			'whatsapp'  => __( 'WhatsApp', 'cartshare' ),
			'twitter'   => __( 'X / Twitter', 'cartshare' ),
			'linkedin'  => __( 'LinkedIn', 'cartshare' ),
			'skype'     => __( 'Skype', 'cartshare' ),
		);

		return $labels[ $channel ] ?? ucwords( str_replace( '_', ' ', $channel ) );
	}
}
