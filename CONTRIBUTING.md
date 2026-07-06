# Contributing

Thanks for your interest in improving the Math CAPTCHA plugin! This is a small,
single-file YOURLS plugin, so contributing is deliberately lightweight.

## Getting started

```bash
composer install
```

If you are on PHP 8.4+, the Psalm dev dependency caps at PHP 8.3, so use:

```bash
composer install --ignore-platform-req=php
```

## Development workflow

- Branch off `main`, and open pull requests against `main`.
- The entire plugin lives in [`plugin.php`](plugin.php) (PHP logic plus embedded
  JS and CSS). Keep it PSR-12 clean and Psalm-clean.
- If you change the form markup, update the assertions in
  `tests/MathCaptchaTest.php` — they check the field ids, the question, the
  `Answer` placeholder, and the accessibility attributes.
- The plugin `Version:` header in `plugin.php` is asserted by the unit tests, so
  bump both together.

## Running the checks

```bash
# Unit tests + standalone checks (no database needed)
vendor/bin/phpunit tests/MathCaptchaTest.php
php tests/integration/IntegrationTest.php

# Lint / static analysis
vendor/bin/phpcs          # PSR-12 (plugin.php)
vendor/bin/psalm          # errorLevel 4
```

The full end-to-end flow (a real YOURLS install against MySQL, plus Playwright
screenshots) is documented in [INTEGRATION_TESTING.md](INTEGRATION_TESTING.md),
and deeper architecture / environment notes live in
[docs/DEV_NOTES.md](docs/DEV_NOTES.md).

## Pull requests

- Keep changes focused; one logical change per PR.
- Make sure `phpcs`, `psalm`, and the unit tests pass locally before pushing —
  CI runs all of them (see the workflows under `.github/workflows/`).
- Describe what changed and why. If behavior or markup changed, mention how you
  verified it.

## Reporting bugs and requesting features

Please open an issue using one of the templates. For security issues, do **not**
open a public issue — see [SECURITY.md](SECURITY.md).
