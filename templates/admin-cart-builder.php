<?php
/**
 * Admin cart builder page template for CartShare.
 *
 * Included by CartShare_Admin::render_cart_builder_page(). Renders the
 * pre-filled cart builder tool under WooCommerce → Cart Builder.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Capability check — belt-and-braces; already verified by the controller.
// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers manage_woocommerce.
if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'cartshare' ) );
}
?>

<div class="wrap cartshare-cart-builder">
	<h1><?php esc_html_e( 'Cart Builder', 'cartshare' ); ?></h1>

	<!-- ======================================================
		PRODUCT SEARCH
		====================================================== -->
	<div class="cartshare-section">
		<h2><?php esc_html_e( 'Add Products', 'cartshare' ); ?></h2>
		<div class="cartshare-field-group">
			<label for="cartshare-product-search"><?php esc_html_e( 'Search products', 'cartshare' ); ?></label>
			<input
				type="text"
				id="cartshare-product-search"
				class="regular-text"
				placeholder="<?php esc_attr_e( 'Type to search products…', 'cartshare' ); ?>"
				autocomplete="off"
			>
			<div id="cartshare-search-results" class="cartshare-autocomplete-dropdown"></div>
		</div>
	</div>

	<!-- ======================================================
		ITEM LIST
		====================================================== -->
	<div class="cartshare-section">
		<h2><?php esc_html_e( 'Cart Items', 'cartshare' ); ?></h2>
		<table id="cartshare-item-list" class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'cartshare' ); ?></th>
					<th><?php esc_html_e( 'Variation', 'cartshare' ); ?></th>
					<th><?php esc_html_e( 'Qty', 'cartshare' ); ?></th>
					<th><?php esc_html_e( 'Remove', 'cartshare' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<!-- Populated dynamically by admin-cart-builder.js -->
			</tbody>
		</table>
	</div>

	<!-- ======================================================
		CUSTOMER SEARCH
		====================================================== -->
	<div class="cartshare-section">
		<h2><?php esc_html_e( 'Assign Customer (Optional)', 'cartshare' ); ?></h2>
		<div class="cartshare-field-group">
			<label for="cartshare-customer-search"><?php esc_html_e( 'Search customers', 'cartshare' ); ?></label>
			<input
				type="text"
				id="cartshare-customer-search"
				class="regular-text"
				placeholder="<?php esc_attr_e( 'Type to search customers…', 'cartshare' ); ?>"
				autocomplete="off"
			>
			<input type="hidden" id="cartshare-customer-id" value="">
			<div id="cartshare-customer-results" class="cartshare-autocomplete-dropdown"></div>
		</div>
	</div>

	<!-- ======================================================
		GENERATE BUTTON
		====================================================== -->
	<div class="cartshare-section">
		<button id="cartshare-generate-btn" class="button button-primary">
			<?php esc_html_e( 'Generate Cart Link', 'cartshare' ); ?>
		</button>
		<div class="cartshare-inline-error" style="display:none;"></div>
	</div>

	<!-- ======================================================
		SHARE LINK OUTPUT
		====================================================== -->
	<div id="cartshare-link-container" style="display:none;" class="cartshare-section">
		<h2><?php esc_html_e( 'Share Link', 'cartshare' ); ?></h2>
		<div class="cartshare-field-group cartshare-share-url-row">
			<input
				type="text"
				id="cartshare-share-url"
				class="large-text"
				readonly
				value=""
			>
			<button id="cartshare-copy-link-btn" class="cartshare-copy-link button">
				<?php esc_html_e( 'Copy Link', 'cartshare' ); ?>
			</button>
		</div>
	</div>
</div>
