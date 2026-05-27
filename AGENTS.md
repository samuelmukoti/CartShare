# CartShare

> 

This file is the agent's first-touch context for the project. Keep it short, current, and honest.

## Toolchain

**WordPress Plugin** — PHP 8.3 + Composer + PHPUnit, with a one-command WordPress + MySQL preview via docker compose.

## Project shape

This repo **is** a WordPress plugin. The root contains the plugin's main `.php` file (with the `Plugin Name:` header), `readme.txt`, and supporting subdirectories. When you boot the docker-compose stack the repo root is bind-mounted into `wp-content/plugins/cartshare/` inside the WordPress container — so every file at the repo root ends up inside the plugin's directory in WordPress.

If you encounter a repo that looks like a full WordPress site (a `wp-content/` at the root, multiple plugins nested inside), this scaffold is **not** the right shape — the user picked the wrong toolchain and should switch to `php` instead.

## Conventions

- Follow the WordPress PHP Coding Standards (WPCS). The CI runs PHPCS with the `WordPress` ruleset.
- Run tests before claiming any change is complete.
- Prefer the smallest change that makes the test pass.
- Don't introduce unrelated formatting churn.

## Commands

- Boot WordPress + MySQL: `docker compose up -d`
- Tear down: `docker compose down -v`
- Install deps: `composer install`
- Test: `vendor/bin/phpunit`
- Lint: `vendor/bin/phpcs --standard=WordPress .`

## Project structure

(Describe the layout as the plugin grows — main file, includes/, assets/, languages/, tests/.)

## Open questions

(Track ambiguous decisions here. The agent reads them and asks the user.)
