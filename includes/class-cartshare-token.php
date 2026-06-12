<?php
/**
 * Token generation and validation for CartShare.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Token
 *
 * Provides static helpers for generating and validating alphanumeric cart tokens.
 */
class CartShare_Token {

	/**
	 * Generate a 32-character alphanumeric token.
	 *
	 * Uses wp_generate_password with special_chars disabled so the result is
	 * URL-safe and contains only [A-Za-z0-9] characters.
	 *
	 * @return string 32-character alphanumeric token.
	 */
	public static function generate(): string {
		return wp_generate_password( 32, false );
	}

	/**
	 * Check whether a string matches the expected token format.
	 *
	 * A valid token is exactly 32 characters long and contains only
	 * alphanumeric characters (A-Z, a-z, 0-9).
	 *
	 * @param string $token The token string to validate.
	 * @return bool True if the token matches the expected format, false otherwise.
	 */
	public static function is_valid_format( string $token ): bool {
		return (bool) preg_match( '/^[A-Za-z0-9]{32}$/', $token );
	}
}
