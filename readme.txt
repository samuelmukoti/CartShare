=== WP CartShare Pro ===
Contributors: Samuel Mukoti <sam@melivo.com>
Tags: woocommerce, cart, share, save cart, share cart
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Persist and share WooCommerce carts via secure tokenized URLs — with 9 sharing channels, a My Account dashboard, and full admin control.

== Description ==

**WP CartShare Pro** lets shoppers persist their current WooCommerce cart to a secure, tokenized URL so it can be restored later or shared with others in seconds.

= Key Features =

* **Save any cart** — Works for both guests (via WooCommerce session) and registered users. Carts are stored securely with a unique, opaque 32-character token — no internal IDs are ever exposed.
* **9 Sharing channels** — Email, Copy Link, Print, Facebook, Messenger, WhatsApp, X/Twitter, LinkedIn, and Skype. Each channel can be individually enabled or disabled in the admin settings.
* **One-click restore** — Visiting a share link restores all cart items and coupons into any session with graceful fallbacks for deleted products, out-of-stock items, and expired coupons.
* **My Account dashboard** — Registered users get a dedicated "Saved Carts" tab where they can name, restore, and delete saved carts.
* **Fully configurable admin panel** — Tabbed settings for General (redirect destination, expiration), Sharing (per-channel toggles), Email (template branding), Appearance (popup colors), and History (paginated cart log with delete).
* **Scheduled cleanup** — A daily WP-Cron job automatically purges expired cart rows to keep the database tidy.
* **Classic & Block cart support** — The "Save & Share" button appears in both the classic shortcode cart and the WooCommerce Block-based cart, without any build step.
* **HPOS compatible** — Declares full compatibility with WooCommerce High-Performance Order Storage.
* **Translation-ready** — Every user-facing string uses the `cartshare` text domain with a bundled `.pot` template.

= How It Works =

1. A shopper adds products to their cart and clicks the **Save & Share Cart** button.
2. The plugin serializes the cart (items, quantities, variations, cart item data, and applied coupons), generates a unique token, and stores it in a custom database table with a configurable expiry.
3. The shopper receives a shareable URL containing only the opaque token.
4. Anyone with the link can click **Restore** to load those items into their own cart. The plugin re-applies all items and coupons, recomputes totals at current prices, and shows a notice for anything that could not be restored.

= Security =

* Every PHP file is guarded with an `ABSPATH` check.
* All state-changing REST endpoints require a valid WordPress nonce.
* Admin endpoints additionally enforce `manage_woocommerce` capability.
* All database queries use `$wpdb->prepare()`.
* All output is escaped (`esc_html`, `esc_attr`, `esc_url`).
* All user input is sanitized before use.

= Requirements =

* WordPress 6.2 or later
* WooCommerce 7.0 or later
* PHP 7.4 or later

== Installation ==

1. Upload the `cartshare` folder to the `/wp-content/plugins/` directory, or install the plugin directly from the WordPress Plugins screen.
2. Ensure **WooCommerce** is installed and activated.
3. Activate **WP CartShare Pro** through the Plugins menu in WordPress Admin.
4. The plugin will automatically create its database table and schedule the daily cleanup cron event.
5. Navigate to **WooCommerce → WP CartShare Pro** to configure setup and settings.

= Using WP-CLI =

```bash
wp plugin activate cartshare
```

= Configuring Expiration =

By default, saved carts expire after 30 days. You can adjust this under **WooCommerce → WP CartShare Pro → General → Cart Expiration**.

> **Note:** If you have disabled WP-Cron (`DISABLE_WP_CRON = true`), you must configure a system-level cron alternative (e.g., a server cron job calling `wp cron event run cartshare_cleanup_event`) to ensure expired carts are purged.

== Frequently Asked Questions ==

= Does this work with the WooCommerce Cart block? =

Yes. The "Save & Share" button is injected into the WooCommerce Cart block using the `IntegrationInterface` API — no JSX or build step required.

= Can guests save and share carts? =

Yes. Guest ownership is tied to the WooCommerce session cookie, so a guest who saves a cart can manage it within the same session. Any visitor — logged in or not — can restore a cart via a valid share link.

= Is the share link public? =

The restore endpoint is intentionally public so that share links work without requiring the recipient to have an account. The link contains only an opaque 32-character alphanumeric token; no internal IDs or personal data are exposed.

= What happens if a product is deleted or goes out of stock before the link is visited? =

The plugin gracefully skips items it cannot restore and displays a notice listing which items could not be added. The remaining items are still restored.

= What happens if a coupon expires? =

The coupon is skipped and a notice is shown. The rest of the cart is still restored normally.

= Can users name their saved carts? =

Yes. When saving, a user can optionally provide a name for the cart. Names are managed in the **My Account → Saved Carts** tab.

= How do I delete all saved carts? =

Individual carts can be deleted from **My Account → Saved Carts** (by the cart owner) or from **WooCommerce → WP CartShare Pro → History** (by administrators). Expired carts are purged automatically by the daily WP-Cron job. Uninstalling the plugin removes the entire table and all associated options.

= Is the plugin compatible with HPOS? =

Yes. WP CartShare Pro declares full compatibility with WooCommerce High-Performance Order Storage (HPOS / custom order tables). The plugin never touches order data, so compatibility is unconditional.

= Does the plugin require Composer or npm? =

No. The plugin runs directly from a ZIP install with no build step. Frontend code is plain JavaScript (no JSX) and CSS3.

= How is the cart data stored? =

Cart data is stored as JSON in a `LONGTEXT` column in a custom database table (`{prefix}cartshare_carts`). PHP `serialize()`/`unserialize()` is never used, eliminating any PHP object-injection risk.

== Screenshots ==

1. The "Save & Share Cart" popup with all 9 sharing channels enabled.
2. The My Account → Saved Carts dashboard for registered users.
3. The Admin Settings page — General tab.
4. The Admin Settings page — Sharing tab with per-channel toggles.
5. The Admin Settings page — Appearance tab with popup color pickers.
6. The Admin Settings page — History tab with a paginated cart log.
7. The printable cart view.

== Changelog ==

= 1.0.1 =
* Redesigned the shared-cart page: your store logo and brand colors, a preview of every product with current prices, and a clear summary before opening.
* Shared links now say "Open Cart" instead of "Restore Cart".
* Fixed the Save & Share button showing "&amp;" after saving a cart.
* Fixed a crash when opening an expired or invalid share link.
* Deleted or expired coupon codes in a shared cart no longer show WooCommerce errors; valid codes still apply.

= 1.0.0 =
* Initial public release.
* Cart save and restore via secure 32-character tokenized URLs.
* 9 sharing channels: Email, Copy Link, Print, Facebook, Messenger, WhatsApp, X/Twitter, LinkedIn, Skype.
* My Account "Saved Carts" tab for registered users.
* Admin settings with 5 tabs: General, Sharing, Email, Appearance, History.
* First-run onboarding and setup checklist.
* Guest cart support via WooCommerce session.
* Daily WP-Cron cleanup of expired cart rows.
* Classic shortcode cart and WooCommerce Cart block support.
* HPOS compatibility declaration.
* Translation-ready with bundled `.pot` file.
* Graceful degradation for deleted products, out-of-stock items, removed variations, and expired coupons.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
