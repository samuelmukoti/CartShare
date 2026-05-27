<?php
/**
 * WooCommerce Blocks integration for CartShare.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CartShare_Blocks_Integration
 *
 * Registers CartShare's block-cart script with WooCommerce Blocks
 * so the Save & Share button is injected into the Cart block
 * without requiring a build step.
 *
 * Implements IntegrationInterface so WooCommerce Blocks enqueues
 * the script in the correct block context and passes CartShareData
 * to the frontend.
 */
class CartShare_Blocks_Integration implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {

	/**
	 * All share-channel slugs recognised by this plugin.
	 *
	 * Kept in sync with CartShare_Frontend::$all_channels.
	 *
	 * @var string[]
	 */
	private $all_channels = array(
		'email',
		'copy_link',
		'print',
		'facebook',
		'messenger',
		'whatsapp',
		'twitter',
		'linkedin',
		'skype',
	);

	/**
	 * Return the unique integration name used by WooCommerce Blocks.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'cartshare';
	}

	/**
	 * Register the block-cart script and localise it with CartShareData.
	 *
	 * Uses the same CartShareData object as the classic-cart path so both
	 * share the same popup logic and configuration.
	 *
	 * @return void
	 */
	public function initialize(): void {
		wp_register_script(
			'cartshare-block-cart',
			CARTSHARE_URL . 'assets/js/block-cart.js',
			array( 'wp-element', 'wp-plugins', 'wc-blocks-checkout' ),
			CARTSHARE_VERSION,
			true
		);

		wp_localize_script(
			'cartshare-block-cart',
			'CartShareData',
			array(
				'restUrl'     => rest_url( 'cartshare/v1' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'channels'    => $this->get_enabled_channels(),
				'labels'      => array(
					'save'         => esc_html__( 'Save & Share Cart', 'cartshare' ),
					'saving'       => esc_html__( 'Saving…', 'cartshare' ),
					'copyLink'     => esc_html__( 'Copy Link', 'cartshare' ),
					'copied'       => esc_html__( 'Copied!', 'cartshare' ),
					'email'        => esc_html__( 'Email', 'cartshare' ),
					'print'        => esc_html__( 'Print', 'cartshare' ),
					'facebook'     => esc_html__( 'Facebook', 'cartshare' ),
					'messenger'    => esc_html__( 'Messenger', 'cartshare' ),
					'whatsapp'     => esc_html__( 'WhatsApp', 'cartshare' ),
					'twitter'      => esc_html__( 'X / Twitter', 'cartshare' ),
					'linkedin'     => esc_html__( 'LinkedIn', 'cartshare' ),
					'skype'        => esc_html__( 'Skype', 'cartshare' ),
					'close'        => esc_html__( 'Close', 'cartshare' ),
					'confirmTitle' => esc_html__( 'Replace your current cart?', 'cartshare' ),
					'confirmMsg'   => esc_html__( 'Your current cart items will be replaced. Continue?', 'cartshare' ),
					'confirmYes'   => esc_html__( 'Yes, restore', 'cartshare' ),
					'confirmNo'    => esc_html__( 'Cancel', 'cartshare' ),
					'errorEmpty'   => esc_html__( 'Your cart is empty. Add items before sharing.', 'cartshare' ),
					'errorGeneric' => esc_html__( 'Something went wrong. Please try again.', 'cartshare' ),
					'emailLabel'   => esc_html__( 'Recipient email', 'cartshare' ),
					'emailMsg'     => esc_html__( 'Message (optional)', 'cartshare' ),
					'emailSend'    => esc_html__( 'Send', 'cartshare' ),
				),
				'colors'      => $this->get_color_vars(),
				'buttonLabel' => esc_html( get_option( 'cartshare_button_label', __( 'Save & Share Cart', 'cartshare' ) ) ),
			)
		);
	}

	/**
	 * Return the script handles to enqueue on the frontend in block context.
	 *
	 * @return string[]
	 */
	public function get_script_handles(): array {
		return array( 'cartshare-block-cart' );
	}

	/**
	 * Return the script handles to enqueue in the block editor.
	 *
	 * CartShare has no editor-specific block assets.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles(): array {
		return array();
	}

	/**
	 * Return inline data to pass to the block frontend scripts.
	 *
	 * Data is already localised via wp_localize_script(); nothing extra needed.
	 *
	 * @return array
	 */
	public function get_script_data(): array {
		return array();
	}

	/**
	 * Return the slugs of all channels currently enabled in admin settings.
	 *
	 * An option value of '0' means the channel is disabled; any other value
	 * (including the default '1' when the option has never been saved) means enabled.
	 *
	 * @return string[]
	 */
	private function get_enabled_channels(): array {
		$enabled = array();
		foreach ( $this->all_channels as $channel ) {
			if ( '0' !== get_option( 'cartshare_channel_' . $channel, '1' ) ) {
				$enabled[] = $channel;
			}
		}
		return $enabled;
	}

	/**
	 * Return admin-configured colour values for use as CSS custom properties.
	 *
	 * Falls back to sensible defaults when options have not been saved yet.
	 *
	 * @return string[]
	 */
	private function get_color_vars(): array {
		$primary     = sanitize_hex_color( get_option( 'cartshare_color_primary', '#4f46e5' ) );
		$button_bg   = sanitize_hex_color( get_option( 'cartshare_color_button_bg', '#4f46e5' ) );
		$button_text = sanitize_hex_color( get_option( 'cartshare_color_button_text', '#ffffff' ) );

		return array(
			'primary'    => $primary ?: '#4f46e5',
			'buttonBg'   => $button_bg ?: '#4f46e5',
			'buttonText' => $button_text ?: '#ffffff',
		);
	}
}
