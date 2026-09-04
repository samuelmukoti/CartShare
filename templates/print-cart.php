<?php
/**
 * CartShare print cart view.
 *
 * Included by CartShare_Frontend::maybe_print_cart() on template_redirect
 * when ?cartshare_print=<token> is detected.  Outputs a standalone HTML page
 * (no site nav/sidebar) and triggers window.print() automatically.
 *
 * Variables available in this template:
 *   $cart_row  array|null  DB row returned by CartShare_DB::find_by_token().
 *   $token     string      The raw token from the query string (sanitized).
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Resolve cart data from the row (may be null if token invalid/expired).
$cart_items   = array();
$cart_coupons = array();
$row_found    = false;

if ( ! empty( $cart_row ) && is_array( $cart_row ) ) {
	$decoded = json_decode( $cart_row['cart_data'], true );
	if ( is_array( $decoded ) ) {
		$cart_items   = isset( $decoded['items'] ) && is_array( $decoded['items'] ) ? $decoded['items'] : array();
		$cart_coupons = isset( $decoded['coupons'] ) && is_array( $decoded['coupons'] ) ? $decoded['coupons'] : array();
		$row_found    = true;
	}
}

// Admin-configured header / footer text.
$print_header = wp_kses_post( get_option( 'cartshare_print_header', '' ) );
$print_footer = wp_kses_post( get_option( 'cartshare_print_footer', '' ) );
$site_name    = esc_html( get_bloginfo( 'name' ) );

// Build line items with live product data where available.
$line_items  = array();
$grand_total = 0.0;

foreach ( $cart_items as $item ) {
	$product_id   = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
	$variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
	$quantity     = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;

	$product_id_to_load = $variation_id ? $variation_id : $product_id;
	$product            = $product_id_to_load ? wc_get_product( $product_id_to_load ) : null;

	if ( $product ) {
		$name     = $product->get_name();
		$price    = (float) $product->get_price();
		$subtotal = $price * $quantity;
	} else {
		/* translators: %d: product ID that could not be found */
		$name     = sprintf( __( 'Product #%d (unavailable)', 'cartshare' ), $product_id );
		$price    = 0.0;
		$subtotal = 0.0;
	}

	$grand_total += $subtotal;
	$line_items[] = array(
		'name'     => $name,
		'quantity' => $quantity,
		'price'    => $price,
		'subtotal' => $subtotal,
		'product'  => $product,
	);
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( sprintf( /* translators: %s: site name */ __( 'Cart — %s', 'cartshare' ), $site_name ) ); ?></title>
	<style>
		*, *::before, *::after { box-sizing: border-box; }

		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
			font-size: 14px;
			color: #1a1a1a;
			background: #fff;
			margin: 0;
			padding: 24px 32px;
		}

		.cartshare-print-header,
		.cartshare-print-footer {
			margin-bottom: 24px;
			padding-bottom: 16px;
			border-bottom: 2px solid #e0e0e0;
			font-size: 13px;
			color: #555;
		}

		.cartshare-print-footer {
			margin-top: 24px;
			padding-top: 16px;
			padding-bottom: 0;
			border-top: 2px solid #e0e0e0;
			border-bottom: none;
		}

		h1 {
			font-size: 22px;
			margin: 0 0 20px;
			font-weight: 700;
		}

		table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 20px;
		}

		th, td {
			text-align: left;
			padding: 8px 10px;
			border-bottom: 1px solid #e0e0e0;
			vertical-align: top;
		}

		th {
			background: #f5f5f5;
			font-weight: 600;
			font-size: 12px;
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}

		td.col-qty,
		th.col-qty,
		td.col-price,
		th.col-price,
		td.col-subtotal,
		th.col-subtotal {
			text-align: right;
		}

		.cartshare-total-row {
			text-align: right;
			margin-top: 8px;
		}

		.cartshare-total-row strong {
			font-size: 16px;
		}

		.cartshare-coupons {
			margin-bottom: 16px;
			font-size: 13px;
			color: #555;
		}

		.cartshare-error {
			padding: 24px;
			background: #fff3f3;
			border: 1px solid #e88;
			border-radius: 4px;
			color: #c00;
			font-size: 15px;
		}

		@media print {
			body { padding: 0; }
			.cartshare-no-print { display: none !important; }
		}
	</style>
</head>
<body>

	<?php if ( $print_header ) : ?>
		<div class="cartshare-print-header">
			<?php echo wp_kses_post( $print_header ); ?>
		</div>
	<?php endif; ?>

	<h1><?php esc_html_e( 'Saved Cart', 'cartshare' ); ?></h1>

	<?php if ( ! $row_found ) : ?>

		<div class="cartshare-error">
			<?php esc_html_e( 'This cart link is invalid or has expired. Please ask the sender for a new link.', 'cartshare' ); ?>
		</div>

	<?php else : ?>

		<?php if ( empty( $line_items ) ) : ?>

			<p><?php esc_html_e( 'This cart is empty.', 'cartshare' ); ?></p>

		<?php else : ?>

			<table>
				<thead>
					<tr>
						<th class="col-name"><?php esc_html_e( 'Product', 'cartshare' ); ?></th>
						<th class="col-qty"><?php esc_html_e( 'Qty', 'cartshare' ); ?></th>
						<th class="col-price"><?php esc_html_e( 'Price', 'cartshare' ); ?></th>
						<th class="col-subtotal"><?php esc_html_e( 'Subtotal', 'cartshare' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $line_items as $line ) : ?>
						<tr>
							<td class="col-name"><?php echo esc_html( $line['name'] ); ?></td>
							<td class="col-qty"><?php echo esc_html( $line['quantity'] ); ?></td>
							<td class="col-price"><?php echo wp_kses_post( wc_price( $line['price'] ) ); ?></td>
							<td class="col-subtotal"><?php echo wp_kses_post( wc_price( $line['subtotal'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="cartshare-total-row">
				<strong>
					<?php
					printf(
						/* translators: %s: formatted total price */
						esc_html__( 'Total: %s', 'cartshare' ),
						wp_kses_post( wc_price( $grand_total ) )
					);
					?>
				</strong>
			</div>

		<?php endif; ?>

		<?php if ( ! empty( $cart_coupons ) ) : ?>
			<div class="cartshare-coupons">
				<?php esc_html_e( 'Coupons applied:', 'cartshare' ); ?>
				<?php
				$escaped_coupons = array_map( 'esc_html', $cart_coupons );
				echo implode( ', ', $escaped_coupons ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value already escaped.
				?>
			</div>
		<?php endif; ?>

	<?php endif; ?>

	<?php if ( $print_footer ) : ?>
		<div class="cartshare-print-footer">
			<?php echo wp_kses_post( $print_footer ); ?>
		</div>
	<?php endif; ?>

	<script>
		window.addEventListener( 'load', function () {
			window.print();
		} );
	</script>

</body>
</html>
<?php
