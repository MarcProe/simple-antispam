# Integration Testing for Math CAPTCHA Plugin

This document describes the integration testing strategy for the Math CAPTCHA plugin for YOURLS.

## Overview

The integration test verifies that the plugin works correctly with a **real YOURLS installation** by:

1. Installing YOURLS 1.9.2 against a MySQL database
2. Installing and activating the plugin
3. Driving the admin interface with curl (login, nonce, AJAX form submission)
4. Verifying CAPTCHA validation works correctly

> **Note**: YOURLS only supports MySQL/MariaDB. There is no SQLite support in
> YOURLS core, so every test environment here uses MySQL.

## Test Solutions Provided

### 1. GitHub Actions Workflow (Primary Solution)

**File**: `.github/workflows/integration-test.yml`

**How it works**:

1. Sets up PHP 8.2 with `mysqli`, `pdo_mysql`, `mbstring`, `gd`, `curl`
2. Starts the MySQL server that is preinstalled on `ubuntu-latest` runners and creates a `yourls` database
3. Downloads YOURLS 1.9.2 and copies the plugin into `user/plugins/math-captcha`
4. Writes a `user/config.php` with MySQL credentials and a test admin user
5. Creates the YOURLS tables with YOURLS' own installer (`yourls_create_sql_tables()`) and activates the plugin via `yourls_update_option('active_plugins', ...)`
6. Starts PHP's built-in web server with a router script that emulates the YOURLS `.htaccess` rules (real files are served directly, everything else goes to `yourls-loader.php`)
7. Uses curl to:
   - Log in to the admin page (YOURLS accepts `username`/`password` request parameters)
   - Extract the CAPTCHA question and the `add_url` nonce from the HTML
   - Submit `action=add` to `admin/admin-ajax.php` with the correct answer and verify the URL is shortened
   - Verify the short URL redirect (via the `Location` header)
   - Verify a wrong answer is rejected with `error:captcha_wrong`
   - Verify a missing answer is rejected with `error:captcha_missing`

**To use**: runs automatically on pushes and pull requests to `main`, or trigger it manually via the Actions UI (`workflow_dispatch`).

### 2. Local Shell Script

**File**: `tests/integration/test-local.sh`

Runs the same flow as the GitHub Actions workflow on your machine.

**Requirements**:
- PHP 8.x with `pdo_mysql` and `mysqli`
- A running MySQL server and the `mysql` CLI client
- `curl`, `wget`, `tar`

**To use**:
```bash
chmod +x tests/integration/test-local.sh

# Defaults: MySQL at 127.0.0.1 with root/root, port 8080
./tests/integration/test-local.sh

# Or with custom settings
MYSQL_USER=me MYSQL_PASS=secret PORT=8888 ./tests/integration/test-local.sh
```

The script creates (and afterwards drops) a scratch database (default
`yourls_captcha_test`) and cleans up its temporary files on exit.

### 3. Standalone PHP Integration Checks

**File**: `tests/integration/IntegrationTest.php`

Unit-style checks that exercise the plugin's functions against the mocked
YOURLS API from `tests/bootstrap.php` — no web server or database needed.
They run in CI as part of the *Unit Tests* job.

**To use**:
```bash
php tests/integration/IntegrationTest.php
# or
composer run integration-test
```

### 4. Docker-Based Manual Testing

**File**: `tests/integration/docker-compose.yml`

Brings up the official `yourls` image plus MySQL with the plugin directory
mounted, for interactive testing in a browser.

**To use**:
```bash
cd tests/integration
docker compose up -d
# open http://localhost:8080/admin/  (login: admin / password)
```

On first run, complete the YOURLS install screen, then activate
"Math CAPTCHA" on the *Plugins* page. The mounted plugin directory is your
live checkout, so code changes are visible on reload.

## Test Cases Covered

### Positive Tests
1. CAPTCHA field appears on the URL shortening form
2. CAPTCHA question has the expected format (`X + Y`)
3. Correct CAPTCHA answer allows the URL to be shortened
4. Short URL redirects to the correct destination

### Negative Tests
5. Wrong CAPTCHA answer is rejected with `error:captcha_wrong`
6. Missing CAPTCHA answer is rejected with `error:captcha_missing`

### Edge Cases (covered by the standalone checks / unit tests)
7. Bookmarklet requests bypass the CAPTCHA (via `u` or `up` GET parameters)
8. Session-based CAPTCHA (a new question is generated after each attempt)

## Technical Details

### Why MySQL?

YOURLS core only supports MySQL/MariaDB (its database layer builds a
`mysql:` PDO DSN). GitHub's `ubuntu-latest` runners ship with a MySQL
server preinstalled (`root`/`root`), so no service container is needed.

### Why PHP's built-in web server?

- No Apache/Nginx setup needed; a single command
- The router script (`router.php`) replicates the `.htaccess` rewrite rules:
  requests for real files and directories are served normally, everything
  else is handled by `yourls-loader.php` (which resolves short URLs)

### How authentication works in the tests

- `user/config.php` defines a test admin in `$yourls_user_passwords` and sets
  `YOURLS_NO_HASH_PASSWORD` so YOURLS doesn't try to rewrite the config file
- YOURLS accepts `username` and `password` as request parameters, so curl can
  authenticate on each request while a cookie jar preserves the PHP session
  that stores the CAPTCHA answer
- Submitting a new URL through `admin/admin-ajax.php` requires `action=add`
  plus a valid `nonce`, which the tests scrape from the admin page HTML
  (`id="nonce-add"`)

## Troubleshooting

### Debugging tips

1. **Server logs**: the workflow prints `/tmp/php-server.log` on failure; the
   local script keeps it in its temp directory until exit
2. **Inspect the admin HTML**:
   ```bash
   curl -s --data-urlencode "username=test-admin" --data-urlencode "password=test-password" \
     http://localhost:8080/admin/ | grep -C5 "math-captcha"
   ```
3. **Check the database**:
   ```bash
   mysql -uroot -proot yourls -e "SELECT option_name, option_value FROM yourls_options;"
   ```

### Common issues

- **CAPTCHA question not found**: check `active_plugins` in `yourls_options` —
  it must be a PHP-serialized array containing `math-captcha/plugin.php`
  (which is why the setup goes through `yourls_update_option()` rather than
  inserting a raw string)
- **`nonce` errors from admin-ajax**: the nonce is tied to the logged-in user;
  make sure the same credentials are used for the page fetch and the submit
- **CAPTCHA verification fails with the right answer**: the answer lives in
  the PHP session — the cookie jar (`-b`/`-c`) must be shared between the
  admin page request and the submit request

## Future Enhancements

1. Matrix-test multiple PHP and YOURLS versions
2. Browser automation (Playwright) for testing the JavaScript form hook
3. Performance/security test steps

## License

All test files are part of the Math CAPTCHA plugin and are licensed under the
MIT License, the same as YOURLS itself.
