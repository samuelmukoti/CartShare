<?php
/**
 * CartShare share-by-email HTML body template.
 *
 * Included by CartShare_Email when sending a share-by-email message.
 * The CartShare_Email class replaces the {placeholder} tokens before passing
 * this output to wp_mail().  All dynamic values that arrive via the
 * placeholder map are already sanitized by the caller before replacement.
 *
 * Placeholders (replaced by CartShare_Email::render_template()):
 *   {sender}    – Display name of the person who shared the cart.
 *   {message}   – Optional personal message from the sender (may be empty).
 *   {share_url} – Full URL of the tokenized share link.
 *   {logo}      – Absolute URL of the site logo image (may be empty string).
 *
 * Colors are driven by admin options at render time so the template itself
 * contains only the placeholder tokens; the email class substitutes live
 * values before sending.
 *
 * @package CartShare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Resolve branding options at template-include time.
// These values are used inline in the HTML/CSS below.
$header_color = sanitize_hex_color( get_option( 'cartshare_color_primary', '#4f46e5' ) );
$header_color = '' === $header_color ? '#4f46e5' : $header_color;
$button_bg    = sanitize_hex_color( get_option( 'cartshare_color_button_bg', '#4f46e5' ) );
$button_bg    = '' === $button_bg ? '#4f46e5' : $button_bg;
$button_text  = sanitize_hex_color( get_option( 'cartshare_color_button_text', '#ffffff' ) );
$button_text  = '' === $button_text ? '#ffffff' : $button_text;
$footer_color = sanitize_hex_color( get_option( 'cartshare_color_primary', '#4f46e5' ) );
$footer_color = '' === $footer_color ? '#4f46e5' : $footer_color;
$site_name    = get_bloginfo( 'name' );
$site_url     = home_url( '/' );

?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo esc_html( $site_name ); ?></title>
	<!--[if mso]>
	<noscript>
		<xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
	</noscript>
	<![endif]-->
	<style type="text/css">
		body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
		table, td { mso-table-lspace: 0; mso-table-rspace: 0; }
		img { -ms-interpolation-mode: bicubic; }
		img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
		table { border-collapse: collapse !important; }
		body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
		a[x-apple-data-detectors] {
			color: inherit !important;
			text-decoration: none !important;
			font-size: inherit !important;
			font-family: inherit !important;
			font-weight: inherit !important;
			line-height: inherit !important;
		}
		@media screen and (max-width: 600px) {
			.email-container { width: 100% !important; }
			.fluid { max-width: 100% !important; height: auto !important; }
			.stack-column, .stack-column-center { display: block !important; width: 100% !important; max-width: 100% !important; }
		}
	</style>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

<center>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f4f4;">
<tr>
<td align="center" style="padding:24px 10px;">

	<!-- Email container -->
	<table role="presentation" class="email-container" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

		<!-- Header / logo band -->
		<tr>
			<td align="center" style="background-color:<?php echo esc_attr( $header_color ); ?>;padding:28px 40px;">
				<?php if ( '{logo}' !== '' ) : /* Replaced at send time; empty if no logo set. */ ?>
				<img
					src="{logo}"
					alt="<?php echo esc_attr( $site_name ); ?>"
					width="160"
					style="max-width:160px;height:auto;display:block;margin:0 auto;"
				>
				<?php endif; ?>
				<p style="margin:<?php echo '{logo}' !== '' ? '12px' : '0'; ?> 0 0;font-size:20px;font-weight:700;color:#ffffff;text-align:center;">
					<?php echo esc_html( $site_name ); ?>
				</p>
			</td>
		</tr>

		<!-- Body copy -->
		<tr>
			<td style="padding:36px 40px 24px;">

				<h1 style="margin:0 0 16px;font-size:22px;font-weight:700;color:#1a1a1a;line-height:1.3;">
					<?php esc_html_e( 'Someone shared a cart with you!', 'cartshare' ); ?>
				</h1>

				<p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#444;">
					<?php
					// {sender} is a placeholder; CartShare_Email::render_template() replaces it with esc_html() output before sending.
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
					printf(
						/* translators: %s: sender display name */
						esc_html__( '%s has saved a cart and wants to share it with you.', 'cartshare' ),
						'<strong>{sender}</strong>'
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</p>

				<!-- Optional personal message -->
				<!-- BEGIN:message -->
				<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:16px 0;">
					<tr>
						<td style="background:#f8f8f8;border-left:4px solid <?php echo esc_attr( $header_color ); ?>;padding:14px 18px;border-radius:0 4px 4px 0;">
							<p style="margin:0;font-size:14px;line-height:1.6;color:#555;font-style:italic;">
								&ldquo;{message}&rdquo;
							</p>
						</td>
					</tr>
				</table>
				<!-- END:message -->

				<p style="margin:0 0 28px;font-size:15px;line-height:1.6;color:#444;">
					<?php esc_html_e( 'Click the button below to view the cart and restore it with one click.', 'cartshare' ); ?>
				</p>

				<!-- CTA button -->
				<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:0 auto 32px;">
					<tr>
						<td align="center" style="border-radius:6px;background-color:<?php echo esc_attr( $button_bg ); ?>;">
							<a
								href="{share_url}"
								target="_blank"
								style="
									display:inline-block;
									padding:14px 32px;
									font-size:15px;
									font-weight:700;
									color:<?php echo esc_attr( $button_text ); ?>;
									text-decoration:none;
									border-radius:6px;
									background-color:<?php echo esc_attr( $button_bg ); ?>;
									mso-padding-alt:0;
									letter-spacing:0.02em;
								"
							>
								<?php esc_html_e( 'View &amp; Restore Cart', 'cartshare' ); ?>
							</a>
						</td>
					</tr>
				</table>

				<!-- Fallback URL -->
				<p style="margin:0 0 8px;font-size:12px;color:#888;line-height:1.5;">
					<?php esc_html_e( 'Or copy this link into your browser:', 'cartshare' ); ?>
				</p>
				<p style="margin:0;font-size:12px;color:#888;word-break:break-all;">
					<a href="{share_url}" style="color:<?php echo esc_attr( $header_color ); ?>;">{share_url}</a>
				</p>

			</td>
		</tr>

		<!-- Divider -->
		<tr>
			<td style="padding:0 40px;">
				<hr style="border:none;border-top:1px solid #ebebeb;margin:0;">
			</td>
		</tr>

		<!-- Footer -->
		<tr>
			<td style="padding:20px 40px 28px;text-align:center;">
				<p style="margin:0 0 6px;font-size:12px;color:#aaa;">
					<?php
					// href uses esc_url(), inline color uses esc_attr(), site name uses esc_html() — all dynamic values are escaped.
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
					printf(
						/* translators: %s: site name wrapped in a link */
						esc_html__( 'You received this email because someone used the Save &amp; Share Cart feature on %s.', 'cartshare' ),
						'<a href="' . esc_url( $site_url ) . '" style="color:' . esc_attr( $footer_color ) . ';text-decoration:none;">' . esc_html( $site_name ) . '</a>'
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</p>
				<p style="margin:0;font-size:11px;color:#ccc;">
					&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?>
				</p>
			</td>
		</tr>

	</table><!-- /email-container -->

</td>
</tr>
</table>
</center>

</body>
</html>
