<?php
/**
 * Analytics dashboard template for CartShare.
 *
 * Included by CartShare_Analytics::render_dashboard() under
 * WooCommerce → CartShare Analytics.
 *
 * Expects these variables from the controller:
 *
 * @var CartShare_Analytics  $this         The analytics controller (for labels).
 * @var string               $range        Active range key (7d|30d|all).
 * @var array<string,string> $ranges       Range key => human label.
 * @var array                $summary      Headline metrics from get_summary().
 * @var array                $top_products Most-shared products from get_top_products().
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

$base_url = admin_url( 'admin.php?page=cartshare-analytics' );
?>
<div class="wrap cartshare-admin cartshare-analytics">
	<h1><?php esc_html_e( 'CartShare Analytics', 'cartshare' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Measure how cart sharing is driving conversions across your store.', 'cartshare' ); ?>
	</p>

	<h2 class="screen-reader-text"><?php esc_html_e( 'Filter by date range', 'cartshare' ); ?></h2>
	<ul class="subsubsub cartshare-range-filter">
		<?php
		$last = array_key_last( $ranges );
		foreach ( $ranges as $key => $label ) :
			$is_active = ( $key === $range );
			$url       = esc_url( add_query_arg( 'range', $key, $base_url ) );
			?>
			<li>
				<a href="<?php echo esc_url( $url ); ?>"<?php echo $is_active ? ' class="current" aria-current="page"' : ''; ?>>
					<?php echo esc_html( $label ); ?>
				</a><?php echo ( $key !== $last ) ? ' |' : ''; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="cartshare-stat-grid">
		<div class="cartshare-stat-card">
			<span class="cartshare-stat-value"><?php echo esc_html( number_format_i18n( $summary['total_saved'] ) ); ?></span>
			<span class="cartshare-stat-label"><?php esc_html_e( 'Carts saved', 'cartshare' ); ?></span>
		</div>
		<div class="cartshare-stat-card">
			<span class="cartshare-stat-value"><?php echo esc_html( number_format_i18n( $summary['total_restored'] ) ); ?></span>
			<span class="cartshare-stat-label"><?php esc_html_e( 'Carts restored', 'cartshare' ); ?></span>
		</div>
		<div class="cartshare-stat-card">
			<span class="cartshare-stat-value"><?php echo esc_html( number_format_i18n( $summary['conversions'] ) ); ?></span>
			<span class="cartshare-stat-label"><?php esc_html_e( 'Restored carts ordered', 'cartshare' ); ?></span>
		</div>
		<div class="cartshare-stat-card cartshare-stat-card--accent">
			<span class="cartshare-stat-value"><?php echo esc_html( number_format_i18n( $summary['conversion_rate'], 1 ) ); ?>%</span>
			<span class="cartshare-stat-label"><?php esc_html_e( 'Restore-to-order rate', 'cartshare' ); ?></span>
		</div>
	</div>

	<div class="cartshare-report-columns">
		<div class="cartshare-report-card">
			<h2><?php esc_html_e( 'Top sharing channels', 'cartshare' ); ?></h2>
			<?php if ( empty( $summary['saves_by_channel'] ) ) : ?>
				<p class="cartshare-empty"><?php esc_html_e( 'No shares recorded in this period yet.', 'cartshare' ); ?></p>
			<?php else : ?>
				<?php $channel_max = max( $summary['saves_by_channel'] ); ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Channel', 'cartshare' ); ?></th>
							<th scope="col" class="cartshare-num"><?php esc_html_e( 'Shares', 'cartshare' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $summary['saves_by_channel'] as $channel => $count ) : ?>
							<tr>
								<td>
									<span class="cartshare-bar-label"><?php echo esc_html( $this->channel_label( (string) $channel ) ); ?></span>
									<span class="cartshare-bar" style="width: <?php echo esc_attr( (string) ( $channel_max > 0 ? round( ( $count / $channel_max ) * 100 ) : 0 ) ); ?>%;" aria-hidden="true"></span>
								</td>
								<td class="cartshare-num"><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="cartshare-report-card">
			<h2><?php esc_html_e( 'Most-shared products', 'cartshare' ); ?></h2>
			<?php if ( empty( $top_products ) ) : ?>
				<p class="cartshare-empty"><?php esc_html_e( 'No saved carts in this period yet.', 'cartshare' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Product', 'cartshare' ); ?></th>
							<th scope="col" class="cartshare-num"><?php esc_html_e( 'In carts', 'cartshare' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_products as $product ) : ?>
							<tr>
								<td>
									<?php
									$edit_link = get_edit_post_link( $product['product_id'] );
									if ( $edit_link ) :
										?>
										<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $product['name'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $product['name'] ); ?>
									<?php endif; ?>
								</td>
								<td class="cartshare-num"><?php echo esc_html( number_format_i18n( $product['count'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<p class="description cartshare-analytics-note">
		<?php esc_html_e( 'Guest activity is counted anonymously — no guest identifiers are stored. The restore-to-order rate reflects orders placed after a shared cart was restored in the same session.', 'cartshare' ); ?>
	</p>
</div>
