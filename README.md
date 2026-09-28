# CartShare

> 

A WordPress plugin scaffolded by DevStation.

## Toolchain

PHP 8.3 + Composer + PHPUnit, with a one-command WordPress + MySQL preview via docker compose.

## Quickstart

Local preview (rootless Docker in your DevStation Pro container):

```bash
docker compose up -d
# WordPress will be available at http://localhost:8080
# Complete the 30-second install, then activate "CartShare" under Plugins.
```

The plugin source is bind-mounted into `wp-content/plugins/cartshare/`, so edits in this repo are picked up live by WordPress.

## Hosted preview

You can also publish a preview through DevStation's Live Preview Hosting:

1. Open the Test Center → Live Preview.
2. Click **Publish** to ship a snapshot of this repo to the preview service.
3. The compose file is reused as-is; the preview service deploys it and gives you a public URL.

## Development

```bash
# PHP deps (if you use composer for autoload / tests)
composer install

# Tests
vendor/bin/phpunit
```

## Releasing

Run the release script from an up-to-date, clean `main`:

```bash
bin/release.sh patch            # or: minor | major | 1.2.3 | 1.3.0-rc.1
bin/release.sh minor --dry-run  # preview the file changes only
```

It bumps the version everywhere (`cartshare.php` header + `CARTSHARE_VERSION`, `readme.txt` `Stable tag`, the POT, the test bootstrap), adds a `readme.txt` changelog entry drafted from PRs merged since the last tag, runs WPCS + PHPUnit, opens a `release/vX.Y.Z` PR, waits for CI, merges it, then tags `vX.Y.Z` on `main`. Useful flags: `--edit` (tweak the changelog in `$EDITOR`), `--notes FILE`, `--upgrade-notice "text"`, `--no-merge` (stop at the PR), `-y` (no prompt). If anything fails before the push, local changes are rolled back.

The pushed tag triggers `.github/workflows/release.yml`, which re-checks that the tag matches the plugin version, runs WPCS + PHPUnit, builds `cartshare-X.Y.Z.zip` (excluding everything in `.distignore`), and publishes a GitHub Release with the zip, a SHA-256 checksum, and the changelog entry. Tags with a suffix (e.g. `v1.2.3-rc.1`) are published as pre-releases.

## Project intent

The AI agent reads `AGENTS.md` for context about what this plugin does and how to make changes. Update it as the project evolves.
