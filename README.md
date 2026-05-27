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

## Project intent

The AI agent reads `AGENTS.md` for context about what this plugin does and how to make changes. Update it as the project evolves.
