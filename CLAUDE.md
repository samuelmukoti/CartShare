# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

CartShare ("Save & Share Cart") is a WooCommerce plugin that persists carts and shares them via secure tokenized URLs. The repo root **is** the plugin directory — `docker compose` bind-mounts `./` into `wp-content/plugins/cartshare/` inside the WordPress container. See `AGENTS.md` for the project's first-touch context; if a repo ever looks like a full WordPress site (root `wp-content/` with nested plugins), the scaffold is wrong.

## Commands

- Boot local WordPress + MySQL at http://localhost:8080: `docker compose up -d`
- Tear down (add `-v` to wipe volumes): `docker compose down`
- Install PHP deps: `composer install`
- Run all tests: `vendor/bin/phpunit`
- Run only unit tests (no WP install needed): `vendor/bin/phpunit --testsuite unit`
- Run only integration tests (requires `WP_TESTS_DIR` pointing at the WordPress test library + WooCommerce): `WP_TESTS_DIR=/path/to/wp-tests vendor/bin/phpunit --testsuite integration`
- Set up the integration environment (WP core + test library + WooCommerce into `/tmp`, no svn needed): `bin/install-wp-tests.sh <db> <user> <pass> <host:port> [wp-version|latest] [wc-version|latest]`, then use `WP_TESTS_DIR=/tmp/wordpress-tests-lib`. WooCommerce ≥ 11 requires WP 7.0, so pair WP 6.2 with WC `8.2.0`.
- Run a single test file: `vendor/bin/phpunit tests/unit/test-token.php`
- Run a single test method: `vendor/bin/phpunit --filter test_method_name tests/unit/test-token.php`
- Lint (WPCS): `vendor/bin/phpcs --standard=WordPress --extensions=php --ignore=vendor/,node_modules/ .`

## Architecture

Entry point `cartshare.php` defines `CARTSHARE_PATH` / `CARTSHARE_URL` / `CARTSHARE_VERSION`, registers activation/deactivation hooks, declares HPOS (custom_order_tables) compatibility with WooCommerce, and on `plugins_loaded` calls `CartShare_Plugin::instance()->boot()` only if `WooCommerce` is loaded. Text domain loads on `init` (WP 6.7+ requirement).

`CartShare_Plugin` (`includes/class-cartshare-plugin.php`) is the singleton orchestrator. `boot()` is idempotent and does two passes:

1. `load_dependencies()` — requires Phase 1 files (`db`, `token`) unconditionally and `file_exists()`-guards Phase 2–7 files (`cart`, `rest`, `frontend`, `blocks-integration`, `myaccount`, `admin`, `email`, `cron`) so phases can be added incrementally without breaking earlier work.
2. `init_subsystems()` — instantiates each subsystem with `class_exists()` guards, passing `$this->db` where needed and calling `init_hooks()` on each. `CartShare_Email` is loaded but instantiated on-demand by `CartShare_REST::share_email()`, not wired here.

When adding a new subsystem class, mirror both the `file_exists` require in `load_dependencies()` and the `class_exists` instantiation in `init_subsystems()`.

## Tests

`tests/bootstrap.php` has two paths controlled by the `WP_TESTS_DIR` env var:

- **Unit path (default)**: defines `ABSPATH` and the `CARTSHARE_*` constants, stubs the WordPress/WooCommerce functions the plugin uses (`wp_generate_password`, `sanitize_text_field`, `wp_json_encode`, `current_time`, `WP_Error`, `is_wp_error`, `WC()`, `wc_load_cart`, `__`, `dbDelta`, `wp_schedule_event`/`wp_next_scheduled`/`wp_clear_scheduled_hook`, `update_option`/`get_option`, `absint`, `flush_rewrite_rules`), and requires `token`, `db`, `cart`, `activator`, `deactivator` directly. Side-effects from stubs are captured in `CartShare_Test_State` (call `::reset()` in setUp). **When unit tests need a new WP/WC function, add the stub to `tests/bootstrap.php`** rather than mocking inline.
- **Integration path**: when `WP_TESTS_DIR` is set, loads the real `wordpress-develop` test bootstrap, then WooCommerce, then `cartshare.php`. Skips all unit stubs.

PHPUnit config (`phpunit.xml.dist`) defines two testsuites — `unit` (`tests/unit/`) and `integration` (`tests/integration/`).

## Conventions

- WordPress PHP Coding Standards (WPCS) — CI runs PHPCS with the `WordPress` ruleset.
- Run tests before claiming a change is complete; prefer the smallest change that makes a test pass.
- No unrelated formatting churn.
