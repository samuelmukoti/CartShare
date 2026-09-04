---
phase: 01-code-review
fixed_at: 2025-09-04T16:25:00Z
review_path: .planning/phases/01-code-review/01-REVIEW.md
iteration: 1
findings_in_scope: 3
fixed: 3
skipped: 0
status: all_fixed
---

# Phase 01: Code Review Fix Report

**Fixed at:** 2025-09-04T16:25:00Z
**Source review:** `.planning/phases/01-code-review/01-REVIEW.md`
**Iteration:** 1

**Summary:**
- Findings in scope: 3 (1 critical, 2 warnings — default `critical_warning` scope; IN-01 is informational and out of scope)
- Fixed: 3
- Skipped: 0
- Verification ran in the **main checkout** (the isolated worktree has no `vendor/`, so PHPUnit could not run there; per-fix verification was `php -l` + re-read, and the full suite was run in the main checkout after the fast-forward).

## Fixed Issues

### CR-01: Unauthenticated `DELETE /delete/{token}` — any visitor can delete other users' carts

**Files modified:** `includes/class-cartshare-rest.php`
**Commit:** `aba7b9c`
**Applied fix:**
1. Route `permission_callback` changed from `__return_true` to `array( $this, 'check_save_nonce' )` — the same wp_rest nonce gate `/save` and `/share/email` already use.
2. Added a guard at the top of `delete()`: when the caller resolves to neither a logged-in user nor a WooCommerce guest session, return a 403 `WP_Error`. This closes the token-only deletion path even for a nonce-holding anonymous caller, and makes the docblock's ownership claim actually enforceable.

### WR-01: Ad-hoc `new CartShare_Analytics()` in REST handlers bypasses the singleton-wired instance

**Files modified:** `includes/class-cartshare-rest.php`
**Commit:** `afc8dee`
**Applied fix:** Added a private `get_analytics(): ?CartShare_Analytics` accessor on `CartShare_REST` that (a) returns the singleton's wired `CartShare_Plugin::instance()->analytics` when it exists, (b) falls back to constructing an instance otherwise, and (c) returns `null` when the class doesn't exist (isolated contexts). All three call sites (`save`, `restore`, `share_email`) now route through it, preserving the graceful-skip semantics of the old `class_exists` guards.

### WR-02: Guest restore→order attribution silently drops when the WC session isn't initialized

**Files modified:** `includes/class-cartshare-analytics.php`
**Commit:** `7701ce3`
**Applied fix:** In `record_restore()`, when `WC()->session` is falsy, call `wc_load_session()` (guarded by `function_exists`) before the stash check, then stash if the session is available. Guests restoring via the public share link are the majority of share-link traffic, so the attribution stash now happens for them. The class file was untracked (dropped by a stale-stash collision) and was committed in `bdc04b8` before this fix could be applied in the worktree.

**Note:** this fix is logic-adjacent (session initialization timing in a WC context that cannot be unit-tested here). Status is `fixed`, but it is flagged for a manual verification pass in the docker WordPress env: trigger a guest restore from a share link and confirm `attribute_order()` picks the event up on a subsequent order.

## Out-of-scope / additional notes

- **IN-01** (verify wpdb insert format arity): informational only, not in the default `critical_warning` fix scope.
- **`tests/bootstrap.php`** still contained raw merge-conflict markers after the earlier "resolution" commit (`923ab93` committed the conflicted state verbatim). Properly resolved to the union of both sides in `5397392` — `php -l` clean, PHPUnit green (38 tests, 81 assertions).

---

_Fixed: 2025-09-04T16:25:00Z_
_Fixer: the agent (gsd-code-fixer)_
_Iteration: 1_
