<?php
/**
 * Unit tests for CartShare_Token.
 *
 * @package CartShare\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_CartShare_Token
 *
 * Tests the static token generation helpers in CartShare_Token.
 */
class Test_CartShare_Token extends TestCase {

	/**
	 * Token must be exactly 32 characters long.
	 */
	public function test_generate_returns_32_chars() {
		$token = CartShare_Token::generate();
		$this->assertSame( 32, strlen( $token ) );
	}

	/**
	 * Token must contain only alphanumeric characters.
	 */
	public function test_generate_is_alphanumeric() {
		$token = CartShare_Token::generate();
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]+$/', $token );
	}

	/**
	 * 1000 generated tokens must all be unique.
	 */
	public function test_generate_returns_unique_tokens_over_1000_calls() {
		$tokens = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$tokens[] = CartShare_Token::generate();
		}
		$this->assertCount( 1000, array_unique( $tokens ) );
	}
}
