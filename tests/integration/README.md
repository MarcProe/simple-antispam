# Integration Tests

This directory contains integration tests for the Math CAPTCHA plugin that
test the plugin with a real YOURLS installation.

YOURLS only supports MySQL/MariaDB (there is no SQLite support in core), so
all real-installation tests run against MySQL. See
[`INTEGRATION_TESTING.md`](../../INTEGRATION_TESTING.md) in the repository
root for the full documentation; this is the short version.

## Test Options

### 1. GitHub Actions Workflow (Recommended)

`.github/workflows/integration-test.yml`:

- Starts the MySQL server preinstalled on the `ubuntu-latest` runner
- Downloads YOURLS 1.9.2, installs it with YOURLS' own installer functions,
  and activates the plugin
- Serves YOURLS with PHP's built-in web server plus a router script that
  emulates the `.htaccess` rewrite rules (`yourls-loader.php`)
- Uses curl to log in, scrape the CAPTCHA question and `add_url` nonce, and
  submit `action=add` to `admin/admin-ajax.php`
- Tests:
  - CAPTCHA field appears on the form
  - Correct CAPTCHA answer allows URL shortening
  - The short URL redirects correctly
  - Wrong CAPTCHA answer is rejected (`error:captcha_wrong`)
  - Missing CAPTCHA answer is rejected (`error:captcha_missing`)

Runs on pushes and pull requests to `main`, or manually via
`workflow_dispatch`.

### 2. Local Testing

`test-local.sh` mirrors the CI flow on your machine. It needs PHP 8.x with
`pdo_mysql`, a running MySQL server, the `mysql` CLI, `curl`, and `wget`:

```bash
# Defaults: MySQL at 127.0.0.1 with root/root, port 8080
./test-local.sh

# Custom settings
MYSQL_USER=me MYSQL_PASS=secret PORT=8888 ./test-local.sh
```

It creates and afterwards drops a scratch database (`yourls_captcha_test`).

### 3. Standalone PHP Checks

`IntegrationTest.php` runs unit-style checks against the mocked YOURLS API
from `tests/bootstrap.php` — no server or database required. It is executed
in CI as part of the *Unit Tests* job.

```bash
php IntegrationTest.php
# or, from the repository root
composer run integration-test
```

### 4. Docker (manual, interactive)

`docker-compose.yml` starts the official `yourls` image plus MySQL with this
plugin mounted:

```bash
docker compose up -d
# open http://localhost:8080/admin/  (login: admin / password)
```

Complete the YOURLS install screen on first run, then activate
"Math CAPTCHA" on the *Plugins* page.

## Troubleshooting

- **CAPTCHA question not found**: `active_plugins` in `yourls_options` must be
  a PHP-serialized array — activate via `yourls_update_option()`, not a raw
  SQL string insert
- **Nonce errors**: the nonce is tied to the logged-in user; use the same
  credentials for the page fetch and the AJAX submit
- **Correct answer rejected**: the expected answer lives in the PHP session;
  share the curl cookie jar (`-b`/`-c`) between requests
