# Integration Testing for Math CAPTCHA Plugin

This document describes the integration testing strategy for the Math CAPTCHA plugin for YOURLS.

## Overview

The integration test verifies that the plugin works correctly with a **real YOURLS installation** by:

1. Installing YOURLS 1.9.2 against a MySQL database
2. Installing and activating the plugin
3. Driving the admin interface with curl (login, nonce, AJAX form submission)
4. Verifying CAPTCHA validation works correctly
5. Driving the admin interface with a real browser (Playwright + Chromium),
   verifying the plugin's JavaScript hook and capturing screenshots of every
   stage

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
   - Log in through the CSRF-protected login form (fetch the login page, scrape its nonce, post the credentials with it)
   - Extract the CAPTCHA question and the `add_url` nonce from the HTML
   - Submit `action=add` to `admin/admin-ajax.php` with the correct answer and verify the URL is shortened
   - Verify the short URL redirect (via the `Location` header)
   - Verify a wrong answer is rejected with `error:captcha_wrong`
   - Verify a missing answer is rejected with `error:captcha_missing`
8. Runs the Playwright screenshot capture (see below) against the same
   YOURLS instance and uploads the screenshots as the
   `integration-test-screenshots` workflow artifact (kept for 30 days)

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

# Also capture browser screenshots (requires Node.js)
SCREENSHOTS=1 ./tests/integration/test-local.sh
```

The script creates (and afterwards drops) a scratch database (default
`yourls_captcha_test`) and cleans up its temporary files on exit. With
`SCREENSHOTS=1` it additionally runs the Playwright screenshot capture (see
below) and saves the images to `tests/integration/screenshots/`.

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

### 5. Browser Screenshots (Playwright)

**File**: `tests/integration/screenshots.mjs`

Drives a real Chromium browser (via [Playwright](https://playwright.dev))
against a running YOURLS instance and captures screenshots of the CAPTCHA at
every stage:

| Screenshot | Shows |
|---|---|
| `01-login-page.png` | YOURLS admin login page |
| `02-admin-page-with-captcha.png` | The shortening form with the CAPTCHA field |
| `03-captcha-field-closeup.png` | Close-up of the CAPTCHA field |
| `04-wrong-answer-rejected.png` | Error feedback for a wrong answer |
| `05-correct-answer-shortened.png` | Successful shorten with the correct answer |
| `06-missing-answer-rejected.png` | Client-side feedback when the answer is empty |
| `07-mobile-form.png` | The CAPTCHA field on a 390px mobile viewport |

Because the flows run through a real browser, this also exercises the
plugin's JavaScript hook (the `add_link` wrapper that injects the answer into
YOURLS' AJAX request) — something the curl-based tests cannot cover. The
script fails if the CAPTCHA field is missing, a wrong answer is not rejected,
or a correct answer does not shorten the URL.

**To use** (with a YOURLS instance prepared as in the workflow or local
script):

```bash
cd tests/integration
npm install                     # installs Playwright
npx playwright install chromium # if Chromium is not already available
node screenshots.mjs
```

Configuration via environment variables: `BASE_URL` (default
`http://localhost:8080`), `ADMIN_USER`, `ADMIN_PASS`, `SCREENSHOT_DIR`
(default `tests/integration/screenshots`), and `CHROMIUM_PATH` to point at an
existing Chromium executable.

In CI the screenshots are uploaded as the `integration-test-screenshots`
workflow artifact. The images embedded in [README.md](README.md) (stored in
`docs/screenshots/`) were produced by this script.

> **Tip**: capture the screenshots with `YOURLS_DEBUG` set to `false`,
> otherwise YOURLS prints SQL debug output at the bottom of every page.

## Test Cases Covered

### Positive Tests
1. CAPTCHA field appears on the URL shortening form
2. CAPTCHA question has the expected format (`X + Y`)
3. Correct CAPTCHA answer allows the URL to be shortened
4. Short URL redirects to the correct destination

### Negative Tests
5. Wrong CAPTCHA answer is rejected with `error:captcha_wrong`
6. Missing CAPTCHA answer is rejected with `error:captcha_missing`

### Browser Tests (Playwright screenshot capture)
7. CAPTCHA field renders in a real browser
8. The JavaScript hook injects the answer into YOURLS' AJAX `add` request
9. Wrong answer shows the error feedback bar
10. Correct answer adds the new short URL to the admin table
11. Empty answer is blocked client-side before any request is sent
12. CAPTCHA field renders on a mobile viewport

### Edge Cases (covered by the standalone checks / unit tests)
13. Bookmarklet requests bypass the CAPTCHA (via `u` or `up` GET parameters)
14. Session-based CAPTCHA (a new question is generated after each attempt)

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
- The login form is CSRF-protected: the tests fetch `/admin/` once to scrape
  the login nonce, then post `username`/`password`/`nonce`. The resulting
  auth cookie and the PHP session (which stores the CAPTCHA answer) are kept
  in a curl cookie jar shared across the subsequent requests
- Submitting a new URL through `admin/admin-ajax.php` requires `action=add`
  plus a valid `add_url` nonce, which the tests scrape from the admin page
  HTML (`nonce-add`)

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
2. Performance/security test steps

## License

All test files are part of the Math CAPTCHA plugin and are licensed under the
MIT License, the same as YOURLS itself.
