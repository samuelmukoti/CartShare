---
phase: 01-code-review
reviewed: 2025-09-04T16:10:00Z
depth: standard
files_reviewed: 8
files_reviewed_list:
  - assets/css/admin.css
  - includes/class-cartshare-activator.php
  - includes/class-cartshare-analytics.php
  - includes/class-cartshare-plugin.php
  - includes/class-cartshare-rest.php
  - templates/admin-analytics.php
  - uninstall.php
  - includes/class-cartshare-db.php
findings:
  critical: 1
  warning: 2
  info: 1
  total: 4
status: issues_found
---

# Phase 01 (analytics dashboard, in-flight): Code Review Report

**Reviewed:** 2025-09-04T16:10:00Z
**Depth:** standard
**Files Reviewed:** 8
**Status:** issues_found

## Summary

Reviewed the staged Phase 8 "CartShare Analytics" work (events table, `CartShare_Analytics`, REST analytics hooks, dashboard template, CSS, uninstall) plus the shared DB layer for cross-file consistency. All files pass `php -l`. Escaping in the admin template is consistently correct and the dashboard's capability check is fine. One genuine security bug was found: the REST delete endpoint is unauthenticated (`permission_callback` `__return_true`), which lets any unauthenticated visitor delete other users' carts by guessing/leaking a 32-char token. (An initially suspected format-string arity bug in `CartShare_DB::insert()` was investigated and retracted — 7 keys / 7 tokens, correct.)

## Critical Issues

### CR-01: Unauthenticated DELETE /delete/{token} — any visitor can delete other users' carts

**File:** `includes/class-cartshare-rest.php:79-86`
**Issue:** The `cartshare/v1/delete/{token}` route uses `'permission_callback' => '__return_true'`. The docblock above `delete()` claims "Ownership is enforced inside CartShare_DB::delete_by_token()", but that ownership check depends on `get_current_user_id()` / `WC()->session->get_customer_id()` — both of which are empty for an unauthenticated request. So a stranger who obtains (or brute-forces) a 32-char token can destroy a logged-in user's saved cart. Compare: `/save` and `/share/email` correctly require the `wp_rest` nonce, and `/list` requires login — only `/delete` is wide open.
**Fix:**
```php
register_rest_route(
    'cartshare/v1',
    '/delete/(?P<token>[A-Za-z0-9]{32})',
    array(
        'methods'             => WP_REST_Server::DELETABLE,
        'callback'            => array( $this, 'delete' ),
        'permission_callback' => array( $this, 'check_save_nonce' ),
    )
);
```
Also make `delete()` treat "no user_id and no guest_id" as a 403 so a nonce-holding anonymous caller can't delete anything by token alone.

## Warnings

### WR-01: `CartShare_REST::save()` / `share_email()` / `record_*` construct fresh `CartShare_Analytics()` instances instead of reusing the singleton's wired instance

**File:** `includes/class-cartshare-rest.php:210-214, 262-264, 317-325`
**Issue:** `CartShare_Plugin::boot()` stores the analytics instance in `CartShare_Plugin::instance()->analytics`, but `CartShare_REST` builds `new CartShare_Analytics()` at each call site. It works today because the class is stateless, but any future per-instance state (caches, rate limits, batching) silently diverges between the wired instance and the ad-hoc ones. It also defeats the single source of truth the plugin class was clearly designed to provide.
**Fix:** In `CartShare_REST`, accept the analytics instance in the constructor (or read `CartShare_Plugin::instance()->analytics` with a `null` fallback to `new CartShare_Analytics()`), and use `$this->analytics` in all three call sites.

### WR-02: Conversion attribution is lost for guest restores on sites where the WC session isn't available at `checkout_order_processed`

**File:** `includes/class-cartshare-analytics.php:152-175`
**Issue:** `record_restore()` only stashes the event ID when `WC()->session` is truthy. For guests restored via the public share link, WooCommerce session availability depends on whether the session has been initialized in that request; if it isn't, the stash is silently skipped and the subsequent `attribute_order()` finds nothing — so guest restore→order conversions (likely the majority of share-link traffic) never count. There is no fallback (e.g., storing the token in a cookie the checkout form can carry back).
**Fix:** At minimum, call `wc_load_session()` / ensure session before the `WC()->session` check in `record_restore()`, and log/trace the skip. Longer term, persist the restore token in a short-lived first-party cookie read back in `attribute_order()` as a fallback.

## Info

### IN-01: Verify `CartShare_DB::insert()` format-string arity whenever `$data` keys change

**File:** `includes/class-cartshare-db.php:57-68`
**Issue:** The 7-key `$data` / 7-token `$format` pairing is correct today, but it is the exact class of bug that silently produces `WP-DB-Error` on insert (and the duplicate-token retry would mask it as "token collision"). A one-line assertion in tests or a `count($data) === count($format)` guard would make this failure loud.
**Fix:** Add a unit test asserting an insert with all 8 realistic value shapes (null user_id, null guest_id, null name, null expires_at) succeeds against the fake wpdb with format-count validation.

---

## Non-findings (checked and clean)

- `templates/admin-analytics.php`: all dynamic output is escaped (`esc_html`, `esc_url`, `esc_attr`); inline bar width is a rounded numeric string — safe. Double capability check in controller + template.
- `class-cartshare-activator.php`: events table schema uses `dbDelta()` correctly (2-space `PRIMARY KEY  (id)`, collation on its own line), `maybe_upgrade()` idempotent on version compare.
- `uninstall.php`: drops both tables, guards on `WP_UNINSTALL_PLUGIN`.
- `class-cartshare-analytics.php` reporting queries: table names interpolated from `$wpdb->prefix` (safe), all user-influenced values go through `$wpdb->prepare`. `current_range()` whitelists against `RANGES`.
- `admin.css`: no issues; responsive breakpoints reasonable.

**Review note on workflow:** This review ran with an explicit file scope because the repo has no `.planning/` phase structure. The working tree also carries an unresolved merge conflict in `tests/bootstrap.php` (stale `git stash` collision) that currently blocks PHPUnit — resolve it before re-running the suite.

---

_Reviewed: 2025-09-04T16:10:00Z_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: standard_
