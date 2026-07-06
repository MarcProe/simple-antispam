# Developer Notes

Working notes for developing and testing the Math CAPTCHA plugin. For user-facing
docs see [README.md](../README.md); for the testing strategy see
[INTEGRATION_TESTING.md](../INTEGRATION_TESTING.md).

## Overview

A YOURLS anti-spam plugin that makes the user solve a random `X + Y` addition
problem before a URL can be shortened. Everything lives in a single
[`plugin.php`](../plugin.php):

- The question/answer are generated with `rand(1, 99)` and stored in the PHP
  session.
- Server-side verification hooks the `shunt_add_new_link` filter and returns an
  `error:captcha_missing` / `error:captcha_wrong` failure array when the answer
  is absent or incorrect.
- A JavaScript hook wraps YOURLS' `add_link()` to inject the answer into the
  AJAX `add` request and to block submission client-side when the field is empty.
- Bookmarklet requests (`?u=` / `?up=`) bypass the CAPTCHA — they have no form.
- All user-visible strings go through `yourls__()` (translation-ready).

## Repository layout

| Path | Purpose |
|---|---|
| `plugin.php` | The entire plugin (PHP logic + embedded JS + CSS) |
| `tests/MathCaptchaTest.php` | PHPUnit unit tests against the mocked YOURLS API |
| `tests/bootstrap.php` | Mocks the YOURLS functions the plugin calls |
| `tests/integration/IntegrationTest.php` | Standalone unit-style checks, no DB/server |
| `tests/integration/test-local.sh` | Full curl flow against a real YOURLS + MySQL |
| `tests/integration/screenshots.mjs` | Playwright browser capture (7 stages) |
| `tests/integration/docker-compose.yml` | Interactive manual testing |
| `docs/screenshots/` | Screenshots captured by the suite, embedded in the README |
| `stubs/yourls.php` | Function stubs so Psalm can analyse the plugin |

## Running the tests

```bash
# Unit tests + standalone checks (no database needed)
composer install
vendor/bin/phpunit tests/MathCaptchaTest.php
php tests/integration/IntegrationTest.php

# Lint / static analysis
vendor/bin/phpcs          # PSR-12 (plugin.php)
vendor/bin/psalm          # errorLevel 4

# Full integration flow (needs PHP + MySQL + curl/wget)
./tests/integration/test-local.sh
SCREENSHOTS=1 ./tests/integration/test-local.sh   # also capture browser screenshots
```

## CI workflows

| Workflow | What it does |
|---|---|
| `integration-test.yml` | Unit tests, standalone checks, full YOURLS install + curl flow, Playwright screenshots (uploaded as an artifact) |
| `psalm.yml` | Psalm security scan → SARIF → code scanning |
| `phpcs.yml` | PHP_CodeSniffer (PSR-12) |
| `codacy.yml` | Codacy security scan → SARIF |

GitHub Actions run on the Node 24 runtime: `actions/checkout@v5`,
`actions/setup-node@v5`, `actions/upload-artifact@v6` (v5 of upload-artifact
still declared node20), `github/codeql-action@v4`.

## Contributing

- Branch off `main`; open PRs against `main`.
- Keep `plugin.php` PSR-12 clean (`vendor/bin/phpcs`) and Psalm-clean.
- If you change the form markup, update the assertions in
  `tests/MathCaptchaTest.php` (they check the field ids, the question, the
  `Answer` placeholder, and the accessibility attributes).
- The plugin `Version:` header in `plugin.php` is asserted by the unit tests —
  bump both together.

## Environment gotchas

Hard-won notes for running the integration/screenshot flow, especially inside a
restricted or ephemeral CI/cloud sandbox.

- **PHP version pin.** Dev dependencies (Psalm) cap PHP at 8.3. On PHP 8.4+ use
  `composer install --ignore-platform-req=php`.
- **YOURLS is MySQL-only.** There is no SQLite support in core; every real
  installation test needs MySQL/MariaDB. On a fresh box: install
  `mariadb-server`, and if there is no init system, start it with `mysqld_safe`.
  To create a TCP `root@127.0.0.1` account, restart once with
  `--skip-grant-tables`, `SET PASSWORD`, then restart normally.
- **Ephemeral containers.** If the container is suspended/reclaimed between
  sessions, background services (MySQL, the PHP built-in server) are gone and
  must be restarted, though the MySQL data directory usually persists on disk.
- **Fetching YOURLS without GitHub access.** If the network policy blocks the
  YOURLS release tarball on GitHub, the YOURLS source can be pulled from the
  official `yourls` Docker image (e.g. via a Docker Hub mirror); the app lives
  under `usr/src/yourls` in one of the image layers. Note that YOURLS' bundled
  `aura/sql` has a `connect()` method that clashes with a newer PDO signature on
  PHP 8.4 — rename it locally if the installer fatals.
- **Playwright.** Point at the preinstalled Chromium with
  `CHROMIUM_PATH=/opt/pw-browsers/chromium` (or let Playwright download one).
  When an HTTP proxy is present, set `NO_PROXY=localhost,127.0.0.1` and drive the
  site via `http://localhost:8080` (a `localhost` hostname behaved better than a
  raw `127.0.0.1` IP through the proxy).
- **Screenshots.** Set `YOURLS_DEBUG` to `false` in `user/config.php` before
  capturing, otherwise YOURLS prints SQL debug output at the bottom of every
  page. The feedback message is a `jquery.notifyBar` (`#__notifyBar`) that slides
  down over ~400ms; wait until it is fully expanded before taking the shot, or it
  will be captured collapsed and appear empty.

## Ideas / roadmap

Done:

- **Supply-chain / security.** A Dependabot config (`.github/dependabot.yml`)
  for the `github-actions` and `composer` ecosystems; `codacy-analysis-cli-action`
  pinned off `@master` to a commit SHA (with a `# v4.4.7` version comment so
  Dependabot can still bump it); a least-privilege `permissions: contents: read`
  block added to `integration-test.yml` (the other three workflows already had
  one).

Not started — candidate follow-ups:

1. **Compliance / community health.** A `LICENSE` file (the project is MIT);
   `SECURITY.md`, `CONTRIBUTING.md`, a PR template, issue templates, `CODEOWNERS`.
2. **i18n.** The plugin is translation-*ready* but not yet translatable: add a
   `Text Domain` header, call `yourls_load_custom_textdomain()`, and ship a
   `.pot` template (optionally with a CI check that it is not stale).
3. **CI robustness.** A PHP version matrix (7.1–8.3) to back the `composer.json`
   `>=7.1` claim; Composer dependency caching; `concurrency:` to cancel
   superseded runs.
