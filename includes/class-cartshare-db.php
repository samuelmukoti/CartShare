<?php
/**
 * Database CRUD wrapper for CartShare.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_DB
 *
 * Provides all database operations for the {prefix}cartshare_carts table.
 * All queries use $wpdb->prepare() or $wpdb->insert() placeholders.
 * Cart payloads are stored as JSON — never PHP serialize().
 * All timestamps are stored in UTC.
 */
class CartShare_DB {

	/**
	 * Return the full table name including the WordPress prefix.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'cartshare_carts';
	}

	/**
	 * Insert a new cart row and return its token.
	 *
	 * Generates a token via CartShare_Token::generate(), inserts the row, and
	 * retries once on a (theoretical) duplicate-token collision.
	 *
	 * @param array       $cart_data   Serialized cart array (items + coupons).
	 * @param int|null    $user_id     WordPress user ID, or null for guests.
	 * @param string|null $guest_id    WooCommerce session customer ID, or null for logged-in users.
	 * @param int|null    $ttl_seconds Time-to-live in seconds; null means no expiry.
	 * @param string|null $name        Optional human-readable cart name.
	 *
	 * @return string|\WP_Error The 32-character token on success, or WP_Error on failure.
	 */
	public function insert( array $cart_data, ?int $user_id, ?string $guest_id, ?int $ttl_seconds, ?string $name ) {
		global $wpdb;

		$token = CartShare_Token::generate();

		$expires_at = null;
		if ( null !== $ttl_seconds ) {
			$expires_at = gmdate( 'Y-m-d H:i:s', time() + $ttl_seconds );
		}

		$data = array(
			'token'      => $token,
			'user_id'    => $user_id ?: null,
			'guest_id'   => $guest_id ?: null,
			'name'       => $name ? sanitize_text_field( $name ) : null,
			'cart_data'  => wp_json_encode( $cart_data ),
			'created_at' => current_time( 'mysql', true ),
			'expires_at' => $expires_at,
		);

		$format = array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' );

		$result = $wpdb->insert( $this->table(), $data, $format );

		// Retry once on duplicate token (collision probability is negligible but handled per spec).
		if ( false === $result ) {
			if ( $wpdb->last_error && false !== strpos( $wpdb->last_error, 'Duplicate entry' ) ) {
				$token         = CartShare_Token::generate();
				$data['token'] = $token;
				$result        = $wpdb->insert( $this->table(), $data, $format );
			}

			if ( false === $result ) {
				return new WP_Error(
					'cartshare_db_insert_failed',
					__( 'Failed to save cart. Please try again.', 'cartshare' )
				);
			}
		}

		return $token;
	}

	/**
	 * Find a cart row by its token.
	 *
	 * @param string $token The 32-character alphanumeric token.
	 *
	 * @return array|null The cart row as an associative array, or null if not found.
	 */
	public function find_by_token( string $token ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE token = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$token
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * List all cart rows belonging to a registered user.
	 *
	 * @param int $user_id WordPress user ID.
	 *
	 * @return array Array of associative cart row arrays (may be empty).
	 */
	public function list_for_user( int $user_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE user_id = %d ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * List all cart rows belonging to a guest session.
	 *
	 * @param string $guest_id WooCommerce session customer ID.
	 *
	 * @return array Array of associative cart row arrays (may be empty).
	 */
	public function list_for_guest( string $guest_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE guest_id = %s ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$guest_id
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Delete a cart row by token, enforcing ownership.
	 *
	 * At least one of $user_id or $guest_id must match the stored row.
	 *
	 * @param string      $token    The 32-character token.
	 * @param int|null    $user_id  WordPress user ID of the requesting user, or null.
	 * @param string|null $guest_id WooCommerce session ID of the requesting guest, or null.
	 *
	 * @return bool True if a row was deleted, false if not found or ownership check failed.
	 */
	public function delete_by_token( string $token, ?int $user_id, ?string $guest_id ): bool {
		global $wpdb;

		$row = $this->find_by_token( $token );

		if ( null === $row ) {
			return false;
		}

		// Ownership check: the caller must own the row via user_id or guest_id.
		$owns_by_user  = $user_id && (int) $row['user_id'] === $user_id;
		$owns_by_guest = $guest_id && $row['guest_id'] === $guest_id;

		if ( ! $owns_by_user && ! $owns_by_guest ) {
			return false;
		}

		$deleted = $wpdb->delete(
			$this->table(),
			array( 'token' => $token ),
			array( '%s' )
		);

		return (bool) $deleted;
	}

	/**
	 * Return all cart rows whose expiry has passed.
	 *
	 * @return array Array of associative cart row arrays.
	 */
	public function find_expired(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT * FROM {$this->table()} WHERE expires_at IS NOT NULL AND expires_at < UTC_TIMESTAMP()", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Delete all cart rows whose expiry has passed.
	 *
	 * @return int Number of rows deleted.
	 */
	public function delete_expired(): int {
		global $wpdb;

		$deleted = $wpdb->query(
			"DELETE FROM {$this->table()} WHERE expires_at IS NOT NULL AND expires_at < UTC_TIMESTAMP()" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		);

		return (int) $deleted;
	}
}
