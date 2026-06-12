<?php
/**
 * Unit tests for CartShare sharing settings and channel configuration.
 *
 * @package CartShare\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_CartShare_Sharing_Settings
 *
 * Tests option persistence for share message, channel enable/disable toggles,
 * and the CartShare_Frontend::get_enabled_channels() filtering logic.
 */
class Test_CartShare_Sharing_Settings extends TestCase {

	/**
	 * Reset captured side-effects before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		CartShare_Test_State::reset();
	}

	/**
	 * update_option / get_option round-trip must persist the share message.
	 *
	 * @return void
	 */
	public function test_share_message_option_saves_and_retrieves() {
		update_option( 'cartshare_share_message', 'Hello' );
		$this->assertSame( 'Hello', get_option( 'cartshare_share_message' ) );
	}

	/**
	 * WhatsApp channel must be enabled by default when no option has been saved.
	 *
	 * get_option returns the supplied default ('1') when the key is absent,
	 * which the frontend interprets as "enabled".
	 *
	 * @return void
	 */
	public function test_whatsapp_channel_enabled_by_default() {
		$this->assertSame( '1', get_option( 'cartshare_channel_whatsapp', '1' ) );
	}

	/**
	 * sanitize_text_field must strip script tags; the sanitized value must
	 * survive an update_option / get_option round-trip without script tags.
	 *
	 * @return void
	 */
	public function test_share_message_is_sanitized() {
		$sanitized = sanitize_text_field( '<script>xss</script>' );
		$this->assertSame( 'xss', $sanitized );

		update_option( 'cartshare_share_message', $sanitized );
		$retrieved = get_option( 'cartshare_share_message' );
		$this->assertStringNotContainsString( '<script>', $retrieved );
	}

	/**
	 * When whatsapp is disabled via its option, get_enabled_channels() must
	 * exclude it from the returned array.
	 *
	 * Uses ReflectionMethod to call the private method without altering the
	 * production class interface.
	 *
	 * @return void
	 */
	public function test_get_enabled_channels_excludes_disabled() {
		update_option( 'cartshare_channel_whatsapp', '0' );

		$frontend = new CartShare_Frontend();
		$method   = new ReflectionMethod( CartShare_Frontend::class, 'get_enabled_channels' );
		$method->setAccessible( true );

		$channels = $method->invoke( $frontend );

		$this->assertNotContains( 'whatsapp', $channels );
	}
}
