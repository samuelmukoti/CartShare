<?php
/**
 * CartShare restore-cart landing page template.
 *
 * Served when a visitor arrives at /?cartshare_restore={token}.
 * Shows a branded preview of the shared cart and a "Restore Cart"
 * confirmation before any cart data is modified — if the visitor's current
 * cart is non-empty they see a warning that their existing items will be
 * replaced (E2E scenario 6).
 *
 * Variables available from CartShare_Frontend::maybe_restore_cart():
 *   $cart_row  array|null  DB row from cartshare_carts (null if not found/expired).
 *   $token     string      The validated 32-char alphanumeric token.
 *   $logo      array       { url, alt } from get_brand_logo(); empty url = wordmark.
 *   $colors    array       { primary, buttonBg, buttonText } admin colours.
 *   $preview   array|null  build_restore_preview() output (null when $cart_row is null).
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name = get_bloginfo( 'name' );
$home_url  = home_url( '/' );
$shop_url  = wc_get_page_permalink( 'shop' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php printf( /* translators: %s: site name */ esc_html__( 'Open Shared Cart - %s', 'cartshare' ), esc_html( $site_name ) ); ?></title>
	<?php wp_head(); ?>
	<style>
		/*
		 * Everything is scoped under .csl (and body.csl-body for the page
		 * shell) with enough specificity to win over theme styles that
		 * wp_head() pulls in, since this page renders outside the theme.
		 */
		html body.csl-body {
			--csl-brand: <?php echo esc_html( $colors['buttonBg'] ); ?>;
			--csl-brand-ink: <?php echo esc_html( $colors['buttonText'] ); ?>;
			--csl-accent: <?php echo esc_html( $colors['primary'] ); ?>;
			--csl-brand-soft: #eef2ff;
			--csl-brand-soft: color-mix(in srgb, var(--csl-brand) 10%, #fff);
			--csl-brand-line: color-mix(in srgb, var(--csl-brand) 22%, #fff);
			--csl-ink: #0f172a;
			--csl-ink-2: #475569;
			--csl-ink-3: #94a3b8;
			--csl-line: #e2e8f0;
			--csl-surface: #fff;
			--csl-bg: #f8fafc;
			--csl-radius: 20px;
			--csl-font: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;

			margin: 0;
			min-height: 100vh;
			background-color: var(--csl-bg);
			background-image:
				radial-gradient(900px 480px at 12% -8%, color-mix(in srgb, var(--csl-brand) 18%, transparent), transparent 70%),
				radial-gradient(760px 420px at 100% 0%, color-mix(in srgb, var(--csl-accent) 14%, transparent), transparent 70%);
			background-repeat: no-repeat;
			color: var(--csl-ink);
			font-family: var(--csl-font);
			font-size: 16px;
			line-height: 1.55;
			-webkit-font-smoothing: antialiased;
		}

		.csl, .csl *, .csl *::before, .csl *::after { box-sizing: border-box; }
		.csl h1, .csl h2, .csl p, .csl ul, .csl li, .csl figure { margin: 0; padding: 0; }
		.csl ul { list-style: none; }
		.csl a { color: inherit; text-decoration: none; }
		.csl img { display: block; max-width: 100%; height: auto; }
		.csl svg { display: block; flex-shrink: 0; }

		.csl { display: flex; flex-direction: column; min-height: 100vh; font-family: var(--csl-font); }

		/* Header ------------------------------------------------------------ */
		.csl .csl-header {
			display: flex; align-items: center; justify-content: space-between; gap: 16px;
			width: 100%; max-width: 1120px; margin: 0 auto; padding: 24px 24px 0;
		}
		.csl .csl-brand { display: inline-flex; align-items: center; min-height: 44px; }
		.csl .csl-brand img { max-height: 48px; width: auto; max-width: 220px; object-fit: contain; }
		.csl .csl-wordmark { font-size: 22px; font-weight: 800; letter-spacing: -0.02em; color: var(--csl-ink); }
		.csl .csl-secure {
			display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px;
			border: 1px solid var(--csl-line); border-radius: 999px; background: rgba(255,255,255,.7);
			color: var(--csl-ink-2); font-size: 13px; font-weight: 600; white-space: nowrap;
			backdrop-filter: blur(6px);
		}

		/* Layout ------------------------------------------------------------ */
		.csl .csl-main { flex: 1; width: 100%; max-width: 1120px; margin: 0 auto; padding: 40px 24px 56px; }
		.csl .csl-grid {
			display: grid; gap: 24px; align-items: start;
			grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
			grid-template-areas: "intro intro" "items summary";
		}
		.csl .csl-intro {
			grid-area: intro; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 48px;
			align-items: center; margin-bottom: 8px;
		}
		.csl .csl-intro-text { max-width: 680px; }
		.csl .csl-items-card { grid-area: items; }
		.csl .csl-summary { grid-area: summary; position: sticky; top: 24px; }

		.csl .csl-card {
			background: var(--csl-surface); border: 1px solid var(--csl-line); border-radius: var(--csl-radius);
			box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 12px 32px -12px rgba(15,23,42,.14);
		}

		/* Intro ------------------------------------------------------------- */
		.csl .csl-eyebrow {
			display: inline-flex; align-items: center; gap: 8px; margin-bottom: 18px; padding: 6px 12px 6px 8px;
			border-radius: 999px; background: var(--csl-brand-soft); border: 1px solid var(--csl-brand-line);
			color: var(--csl-brand); font-size: 13px; font-weight: 700; letter-spacing: .01em;
		}
		.csl .csl-eyebrow-icon {
			display: grid; place-items: center; width: 24px; height: 24px; border-radius: 999px;
			background: var(--csl-brand); color: var(--csl-brand-ink);
		}
		.csl h1.csl-title {
			font-family: var(--csl-font); font-size: clamp(32px, 4.4vw, 48px); line-height: 1.08;
			font-weight: 800; letter-spacing: -0.035em; color: var(--csl-ink); text-wrap: balance;
		}
		.csl .csl-lede { margin-top: 14px; font-size: 18px; color: var(--csl-ink-2); max-width: 580px; }
		.csl .csl-cart-name {
			display: inline-flex; align-items: center; gap: 8px; margin-top: 18px; padding: 8px 14px;
			border-radius: 12px; background: var(--csl-surface); border: 1px solid var(--csl-line);
			font-size: 15px; color: var(--csl-ink-2);
		}
		.csl .csl-cart-name strong { color: var(--csl-ink); font-weight: 700; }

		/* Hero collage ------------------------------------------------------ */
		.csl .csl-collage { position: relative; width: 320px; height: 230px; margin-right: 8px; }
		.csl .csl-tile {
			position: absolute; width: 150px; height: 150px; border-radius: 26px; overflow: hidden;
			background: var(--csl-surface); border: 5px solid var(--csl-surface);
			box-shadow: 0 24px 48px -18px rgba(15,23,42,.35), 0 2px 6px rgba(15,23,42,.06);
		}
		.csl .csl-tile img { width: 100%; height: 100%; object-fit: cover; border-radius: 20px; }
		.csl .csl-tile-1 { left: 0; top: 46px; transform: rotate(-8deg); }
		.csl .csl-tile-2 { left: 86px; top: 4px; transform: rotate(3deg); z-index: 2; }
		.csl .csl-tile-3 { left: 170px; top: 58px; transform: rotate(10deg); z-index: 1; }
		.csl .csl-collage-chip {
			position: absolute; left: 50%; bottom: -8px; z-index: 3; transform: translateX(-50%);
			display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; white-space: nowrap;
			border-radius: 999px; background: var(--csl-ink); color: #fff;
			font-size: 14px; font-weight: 700; box-shadow: 0 14px 30px -12px rgba(15,23,42,.55);
		}
		.csl .csl-collage-chip .woocommerce-Price-amount { color: #fff; }
		.csl .csl-collage-chip i { width: 4px; height: 4px; border-radius: 999px; background: rgba(255,255,255,.5); }
		@media (prefers-reduced-motion: no-preference) {
			.csl .csl-tile-2 { animation: csl-float 6s ease-in-out infinite; }
			@keyframes csl-float { 0%, 100% { transform: rotate(3deg) translateY(0); } 50% { transform: rotate(3deg) translateY(-6px); } }
		}

		/* Items ------------------------------------------------------------- */
		.csl .csl-items-card { padding: 8px 0; }
		.csl .csl-items-head {
			display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
			padding: 18px 24px 10px;
		}
		.csl h2.csl-h2 { font-family: var(--csl-font); font-size: 17px; font-weight: 700; letter-spacing: -0.01em; color: var(--csl-ink); }
		.csl .csl-muted { color: var(--csl-ink-3); font-size: 14px; }
		.csl .csl-item {
			display: grid; grid-template-columns: 76px minmax(0, 1fr) auto; gap: 16px; align-items: center;
			padding: 16px 24px; border-top: 1px solid var(--csl-line);
		}
		.csl .csl-item:first-child { border-top: 0; }
		.csl .csl-thumb {
			position: relative; width: 76px; height: 76px; border-radius: 14px; overflow: hidden;
			background: var(--csl-bg); border: 1px solid var(--csl-line);
		}
		.csl .csl-thumb img { width: 100%; height: 100%; object-fit: cover; }
		.csl .csl-thumb-empty { display: grid; place-items: center; width: 100%; height: 100%; color: var(--csl-ink-3); }
		.csl .csl-qty {
			position: absolute; top: 5px; right: 5px; min-width: 22px; height: 22px; padding: 0 6px;
			display: grid; place-items: center; border-radius: 999px;
			background: var(--csl-ink); color: #fff; font-size: 12px; font-weight: 700; line-height: 1;
			box-shadow: 0 0 0 2px var(--csl-surface);
		}
		.csl .csl-item-name { display: block; font-weight: 650; color: var(--csl-ink); line-height: 1.35; }
		.csl a.csl-item-name:hover { color: var(--csl-brand); }
		.csl .csl-item-meta { margin-top: 4px; font-size: 14px; color: var(--csl-ink-2); }
		.csl .csl-item-meta del { color: var(--csl-ink-3); margin-right: 4px; }
		.csl .csl-item-meta ins { text-decoration: none; }
		.csl .csl-item-total { font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
		.csl .csl-badge {
			display: inline-flex; align-items: center; margin-top: 6px; padding: 2px 8px; border-radius: 999px;
			font-size: 12px; font-weight: 700;
		}
		.csl .csl-badge-sale { background: #ecfdf5; color: #047857; }
		.csl .csl-badge-warn { background: #fef3c7; color: #92400e; }
		.csl .csl-badge-off { background: #f1f5f9; color: #64748b; }
		.csl .csl-item.is-muted .csl-thumb,
		.csl .csl-item.is-muted .csl-item-name,
		.csl .csl-item.is-muted .csl-item-total { opacity: .5; }

		/* Summary ----------------------------------------------------------- */
		.csl .csl-summary { padding: 24px; }
		.csl .csl-rows { display: grid; gap: 12px; margin-top: 16px; }
		.csl .csl-row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; font-size: 15px; color: var(--csl-ink-2); }
		.csl .csl-row strong { color: var(--csl-ink); font-variant-numeric: tabular-nums; }
		.csl .csl-row-total { padding-top: 14px; border-top: 1px dashed var(--csl-line); font-size: 17px; }
		.csl .csl-row-total span { color: var(--csl-ink); font-weight: 700; }
		.csl .csl-row-total strong { font-size: 22px; letter-spacing: -0.02em; }
		.csl .csl-coupon {
			display: flex; align-items: center; gap: 10px; margin-top: 16px; padding: 10px 12px;
			border: 1px dashed var(--csl-brand-line); border-radius: 12px; background: var(--csl-brand-soft);
			font-size: 14px; color: var(--csl-ink-2);
		}
		.csl .csl-coupon code {
			font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; font-weight: 700;
			color: var(--csl-brand); background: none; padding: 0; letter-spacing: .04em;
		}
		.csl .csl-coupon svg { color: var(--csl-brand); }
		.csl .csl-note { margin-top: 12px; font-size: 13px; color: var(--csl-ink-3); }

		.csl .csl-alert {
			display: flex; gap: 12px; margin-top: 20px; padding: 14px; border-radius: 14px;
			background: #fffbeb; border: 1px solid #fde68a; color: #78350f; font-size: 14px; line-height: 1.5;
		}
		.csl .csl-alert svg { margin-top: 1px; color: #d97706; }
		.csl .csl-alert strong { display: block; color: #78350f; font-weight: 700; margin-bottom: 2px; }

		.csl .csl-actions { display: grid; gap: 10px; margin-top: 22px; }
		.csl button.csl-btn,
		.csl a.csl-btn {
			display: inline-flex; align-items: center; justify-content: center; gap: 10px;
			width: 100%; min-height: 54px; margin: 0; padding: 14px 20px;
			border: 0; border-radius: 14px; box-shadow: none;
			font-family: var(--csl-font); font-size: 16px; font-weight: 700; line-height: 1.2; letter-spacing: 0;
			text-align: center; text-decoration: none; text-transform: none; cursor: pointer;
			transition: transform .15s ease, box-shadow .2s ease, background-color .2s ease, filter .2s ease;
		}
		.csl .csl-btn-primary {
			background: var(--csl-brand); color: var(--csl-brand-ink);
			box-shadow: 0 10px 24px -10px color-mix(in srgb, var(--csl-brand) 70%, transparent), inset 0 1px 0 rgba(255,255,255,.18);
		}
		.csl button.csl-btn-primary,
		.csl a.csl-btn-primary { background: var(--csl-brand); color: var(--csl-brand-ink); }
		.csl .csl-btn-primary:hover { filter: brightness(1.06); transform: translateY(-1px); }
		.csl .csl-btn-primary:active { transform: translateY(0); }
		.csl .csl-btn-primary svg { transition: transform .2s ease; }
		.csl .csl-btn-primary:hover svg { transform: translateX(3px); }
		.csl button.csl-btn[disabled] { opacity: .75; cursor: progress; transform: none; }
		.csl a.csl-btn-ghost { background: transparent; color: var(--csl-ink-2); min-height: 46px; font-weight: 600; }
		.csl a.csl-btn-ghost:hover { background: var(--csl-bg); color: var(--csl-ink); }
		.csl .csl-btn:focus-visible { outline: 3px solid color-mix(in srgb, var(--csl-brand) 45%, transparent); outline-offset: 2px; }

		.csl .csl-spinner {
			display: none; width: 18px; height: 18px; border-radius: 999px;
			border: 2px solid currentColor; border-right-color: transparent; animation: csl-spin .7s linear infinite;
		}
		.csl .is-loading .csl-spinner { display: inline-block; }
		.csl .is-loading .csl-btn-arrow { display: none; }
		@keyframes csl-spin { to { transform: rotate(360deg); } }

		.csl .csl-trust {
			display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 16px; margin-top: 18px;
			font-size: 13px; color: var(--csl-ink-3);
		}
		.csl .csl-trust span { display: inline-flex; align-items: center; gap: 6px; }

		/* Empty / expired state ------------------------------------------- */
		.csl .csl-empty { max-width: 520px; margin: 24px auto 0; padding: 48px 40px 40px; text-align: center; }
		.csl .csl-empty-icon {
			display: grid; place-items: center; width: 72px; height: 72px; margin: 0 auto 22px; border-radius: 22px;
			background: var(--csl-brand-soft); color: var(--csl-brand); border: 1px solid var(--csl-brand-line);
		}
		.csl .csl-empty h1.csl-title { font-size: clamp(26px, 3.4vw, 34px); }
		.csl .csl-empty .csl-lede { margin: 12px auto 0; font-size: 16px; }
		.csl .csl-empty .csl-actions { max-width: 320px; margin: 28px auto 0; }

		/* Footer ------------------------------------------------------------ */
		.csl .csl-footer {
			width: 100%; max-width: 1120px; margin: 0 auto; padding: 0 24px 32px;
			text-align: center; font-size: 13px; color: var(--csl-ink-3);
		}
		.csl .csl-footer a { color: var(--csl-ink-2); font-weight: 600; }
		.csl .csl-footer a:hover { color: var(--csl-brand); }

		/* Entrance animation --------------------------------------------- */
		@media (prefers-reduced-motion: no-preference) {
			.csl .csl-rise { animation: csl-rise .55s cubic-bezier(.2,.7,.2,1) both; }
			.csl .csl-rise-2 { animation-delay: .08s; }
			.csl .csl-rise-3 { animation-delay: .16s; }
			@keyframes csl-rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
		}

		/* Responsive ------------------------------------------------------ */
		@media (max-width: 880px) {
			.csl .csl-grid { grid-template-columns: minmax(0, 1fr); grid-template-areas: "intro" "items" "summary"; }
			.csl .csl-summary { position: static; }
			.csl .csl-intro { grid-template-columns: minmax(0, 1fr); }
			.csl .csl-collage { display: none; }
		}
		@media (max-width: 560px) {
			.csl .csl-header { padding: 16px 16px 0; }
			.csl .csl-brand img { max-height: 40px; max-width: 170px; }
			.csl .csl-secure-text { display: none; }
			.csl .csl-secure { padding: 8px; }
			.csl .csl-main { padding: 28px 16px 40px; }
			.csl .csl-lede { font-size: 16px; }
			.csl .csl-items-head { padding: 16px 16px 8px; }
			.csl .csl-item { grid-template-columns: 64px minmax(0, 1fr) auto; gap: 12px; padding: 14px 16px; }
			.csl .csl-thumb { width: 64px; height: 64px; border-radius: 12px; }
			.csl .csl-summary { padding: 20px 16px; }
			.csl .csl-empty { padding: 36px 20px 28px; }
		}
	</style>
</head>
<body class="csl-body">
<?php wp_body_open(); ?>

<div class="csl">

	<header class="csl-header">
		<a class="csl-brand" href="<?php echo esc_url( $home_url ); ?>">
			<?php if ( '' !== $logo['url'] ) : ?>
				<img src="<?php echo esc_url( $logo['url'] ); ?>" alt="<?php echo esc_attr( $logo['alt'] ); ?>">
			<?php else : ?>
				<span class="csl-wordmark"><?php echo esc_html( $site_name ); ?></span>
			<?php endif; ?>
		</a>
		<span class="csl-secure" title="<?php esc_attr_e( 'Secure shared cart', 'cartshare' ); ?>">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
			<span class="csl-secure-text"><?php esc_html_e( 'Secure shared cart', 'cartshare' ); ?></span>
		</span>
	</header>

	<main class="csl-main">

	<?php if ( null === $cart_row ) : ?>

		<section class="csl-card csl-empty csl-rise">
			<div class="csl-empty-icon">
				<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
			</div>
			<h1 class="csl-title"><?php esc_html_e( 'This cart link has expired', 'cartshare' ); ?></h1>
			<p class="csl-lede"><?php esc_html_e( 'This cart link is invalid or has expired. Please ask the sender to share a new link.', 'cartshare' ); ?></p>
			<div class="csl-actions">
				<a href="<?php echo esc_url( $shop_url ); ?>" class="csl-btn csl-btn-primary"><?php esc_html_e( 'Continue Shopping', 'cartshare' ); ?></a>
				<a href="<?php echo esc_url( $home_url ); ?>" class="csl-btn csl-btn-ghost">
					<?php
					/* translators: %s: site name */
					printf( esc_html__( 'Go to %s', 'cartshare' ), esc_html( $site_name ) );
					?>
				</a>
			</div>
		</section>

	<?php else : ?>

		<?php
		$cart_name = ! empty( $cart_row['name'] ) ? $cart_row['name'] : '';

		// Detect whether the visitor already has items in their cart so we can
		// warn them before overwriting (E2E scenario 6: non-empty cart confirmation).
		$is_non_empty  = false;
		$current_count = 0;
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( null !== WC()->cart && ! WC()->cart->is_empty() ) {
			$is_non_empty  = true;
			$current_count = (int) WC()->cart->get_cart_contents_count();
		}

		$expires_in = '';
		if ( ! empty( $cart_row['expires_at'] ) ) {
			$expires_in = human_time_diff( time(), strtotime( $cart_row['expires_at'] ) );
		}

		// Build the form action URL (current page URL with the token parameter).
		$form_action = add_query_arg( 'cartshare_restore', esc_attr( $token ), $home_url );
		?>

		<div class="csl-grid">

			<section class="csl-intro csl-rise">
				<div class="csl-intro-text">
				<span class="csl-eyebrow">
					<span class="csl-eyebrow-icon">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12v9H4v-9"/><path d="M2 7h20v5H2z"/><path d="M12 21V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
					</span>
					<?php
					/* translators: %s: site name */
					printf( esc_html__( 'A shared cart from %s', 'cartshare' ), esc_html( $site_name ) );
					?>
				</span>
				<h1 class="csl-title"><?php esc_html_e( 'Someone shared a cart with you!', 'cartshare' ); ?></h1>
				<p class="csl-lede"><?php esc_html_e( 'Everything they picked out is ready and waiting. Review the items below, then open the cart to start shopping.', 'cartshare' ); ?></p>
				<?php if ( $cart_name ) : ?>
					<p class="csl-cart-name">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
						<?php
						// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $cart_name is esc_html()-escaped; surrounding <strong> tags are safe static HTML.
						printf(
							/* translators: %s: saved cart name wrapped in <strong> */
							esc_html__( 'Cart: %s', 'cartshare' ),
							'<strong>' . esc_html( $cart_name ) . '</strong>'
						);
						// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</p>
				<?php endif; ?>
				</div>

				<?php
				// Up to three product images for the hero collage.
				$collage = array();
				foreach ( $preview['items'] as $preview_item ) {
					if ( '' !== $preview_item['image'] && count( $collage ) < 3 ) {
						$collage[] = $preview_item['image'];
					}
				}
				?>
				<?php if ( $collage ) : ?>
					<div class="csl-collage" aria-hidden="true">
						<?php foreach ( $collage as $n => $tile_image ) : ?>
							<div class="csl-tile csl-tile-<?php echo esc_attr( (string) ( $n + 1 ) ); ?>"><?php echo wp_kses_post( $tile_image ); ?></div>
						<?php endforeach; ?>
						<span class="csl-collage-chip">
							<?php
							/* translators: %s: number of items */
							echo esc_html( sprintf( _n( '%s item', '%s items', $preview['item_count'], 'cartshare' ), number_format_i18n( $preview['item_count'] ) ) );
							?>
							<i></i>
							<?php echo wp_kses_post( wc_price( $preview['subtotal'] ) ); ?>
						</span>
					</div>
				<?php endif; ?>
			</section>

			<section class="csl-card csl-items-card csl-rise csl-rise-2" aria-labelledby="csl-items-title">
				<div class="csl-items-head">
					<h2 class="csl-h2" id="csl-items-title"><?php esc_html_e( "What's inside", 'cartshare' ); ?></h2>
					<span class="csl-muted">
						<?php
						$line_count = count( $preview['items'] );
						/* translators: %d: number of products in the shared cart */
						printf( esc_html( _n( '%d product', '%d products', $line_count, 'cartshare' ) ), (int) $line_count );
						?>
					</span>
				</div>
				<ul>
					<?php foreach ( $preview['items'] as $item ) : ?>
						<?php $is_muted = 'available' !== $item['status']; ?>
						<li class="csl-item<?php echo $is_muted ? ' is-muted' : ''; ?>">
							<div class="csl-thumb">
								<?php if ( '' !== $item['image'] ) : ?>
									<?php echo wp_kses_post( $item['image'] ); ?>
								<?php else : ?>
									<span class="csl-thumb-empty">
										<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.7z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
									</span>
								<?php endif; ?>
								<span class="csl-qty" aria-label="<?php esc_attr_e( 'Quantity', 'cartshare' ); ?>"><?php echo esc_html( (string) $item['quantity'] ); ?></span>
							</div>

							<div>
								<?php if ( '' !== $item['url'] ) : ?>
									<a class="csl-item-name" href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $item['name'] ); ?></a>
								<?php else : ?>
									<span class="csl-item-name"><?php echo esc_html( $item['name'] ); ?></span>
								<?php endif; ?>

								<?php if ( 'unavailable' === $item['status'] ) : ?>
									<span class="csl-badge csl-badge-off"><?php esc_html_e( 'Will be skipped', 'cartshare' ); ?></span>
								<?php else : ?>
									<p class="csl-item-meta">
										<?php if ( $item['regular_price'] > $item['unit_price'] ) : ?>
											<del><?php echo wp_kses_post( wc_price( $item['regular_price'] ) ); ?></del>
										<?php endif; ?>
										<ins><?php echo wp_kses_post( wc_price( $item['unit_price'] ) ); ?></ins>
										&times; <?php echo esc_html( (string) $item['quantity'] ); ?>
									</p>
									<?php if ( 'out_of_stock' === $item['status'] ) : ?>
										<span class="csl-badge csl-badge-warn"><?php esc_html_e( 'Out of stock', 'cartshare' ); ?></span>
									<?php elseif ( $item['regular_price'] > $item['unit_price'] ) : ?>
										<span class="csl-badge csl-badge-sale"><?php esc_html_e( 'On sale', 'cartshare' ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</div>

							<div class="csl-item-total">
								<?php if ( 'unavailable' !== $item['status'] ) : ?>
									<?php echo wp_kses_post( wc_price( $item['line_total'] ) ); ?>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<aside class="csl-card csl-summary csl-rise csl-rise-3" aria-labelledby="csl-summary-title">
				<h2 class="csl-h2" id="csl-summary-title"><?php esc_html_e( 'Cart summary', 'cartshare' ); ?></h2>

				<div class="csl-rows">
					<div class="csl-row">
						<span><?php esc_html_e( 'Items', 'cartshare' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( $preview['item_count'] ) ); ?></strong>
					</div>
					<div class="csl-row csl-row-total">
						<span><?php esc_html_e( 'Subtotal', 'cartshare' ); ?></span>
						<strong><?php echo wp_kses_post( wc_price( $preview['subtotal'] ) ); ?></strong>
					</div>
				</div>

				<?php foreach ( $preview['coupons'] as $coupon_code ) : ?>
					<div class="csl-coupon">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9a3 3 0 0 0 0 6v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3a3 3 0 0 0 0-6V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z"/><path d="M13 5v2M13 17v2M13 11v2"/></svg>
						<span>
							<?php
							// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- the coupon code is esc_html()-escaped; <code> is static markup.
							printf(
								/* translators: %s: coupon code */
								esc_html__( 'Code %s will be applied', 'cartshare' ),
								'<code>' . esc_html( strtoupper( $coupon_code ) ) . '</code>'
							);
							// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</span>
					</div>
				<?php endforeach; ?>

				<p class="csl-note"><?php esc_html_e( 'Prices shown are current. Shipping and taxes are calculated at checkout.', 'cartshare' ); ?></p>

				<?php if ( $is_non_empty ) : ?>
					<div class="csl-alert" role="note">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
						<div>
							<strong><?php esc_html_e( 'Heads up!', 'cartshare' ); ?></strong>
							<?php
							/* translators: %d: number of items currently in the visitor's cart */
							printf( esc_html( _n( 'You have %d item in your cart. Opening this shared cart will replace it.', 'You have %d items in your cart. Opening this shared cart will replace them.', $current_count, 'cartshare' ) ), (int) $current_count );
							?>
						</div>
					</div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="cartshare-restore-form" id="csl-restore-form">
					<?php wp_nonce_field( 'cartshare_restore_' . $token ); ?>

					<div class="csl-actions">
						<button type="submit" class="csl-btn csl-btn-primary">
							<span class="csl-spinner" aria-hidden="true"></span>
							<span>
								<?php
								if ( $is_non_empty ) {
									esc_html_e( 'Open Cart & Replace Mine', 'cartshare' );
								} else {
									esc_html_e( 'Open Cart', 'cartshare' );
								}
								?>
							</span>
							<svg class="csl-btn-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
						</button>

						<a href="<?php echo esc_url( $is_non_empty ? wc_get_cart_url() : $shop_url ); ?>" class="csl-btn csl-btn-ghost">
							<?php
							if ( $is_non_empty ) {
								esc_html_e( 'Keep my current cart', 'cartshare' );
							} else {
								esc_html_e( 'Continue Shopping', 'cartshare' );
							}
							?>
						</a>
					</div>
				</form>

				<div class="csl-trust">
					<span>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
						<?php esc_html_e( 'Secure link', 'cartshare' ); ?>
					</span>
					<?php if ( '' !== $expires_in ) : ?>
						<span>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
							<?php
							/* translators: %s: human-readable time until the link expires, e.g. "30 days" */
							printf( esc_html__( 'Expires in %s', 'cartshare' ), esc_html( $expires_in ) );
							?>
						</span>
					<?php endif; ?>
				</div>
			</aside>

		</div>

		<script>
			( function () {
				var form = document.getElementById( 'csl-restore-form' );
				if ( ! form ) {
					return;
				}
				form.addEventListener( 'submit', function () {
					var btn = form.querySelector( 'button[type="submit"]' );
					btn.classList.add( 'is-loading' );
					btn.setAttribute( 'disabled', 'disabled' );
				} );
			}() );
		</script>

	<?php endif; ?>

	</main>

	<footer class="csl-footer">
		&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
		<a href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
	</footer>

</div><!-- /.csl -->

<?php wp_footer(); ?>
</body>
</html>
