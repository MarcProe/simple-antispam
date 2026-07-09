#!/bin/bash

# Local integration test script for the Math CAPTCHA plugin.
#
# Mirrors .github/workflows/integration-test.yml: installs a real YOURLS
# against a local MySQL server, activates the plugin, and exercises the
# CAPTCHA through the admin interface with curl.
#
# Requirements:
#   - PHP 8.x with pdo_mysql and mysqli extensions
#   - A running MySQL server and the mysql CLI client
#   - curl, wget, tar
#
# Configuration (via environment variables):
#   MYSQL_HOST (default 127.0.0.1), MYSQL_USER (default root),
#   MYSQL_PASS (default root), TEST_DB (default yourls_captcha_test),
#   PORT (default 8080), YOURLS_VERSION (default 1.9.2)

set -e

MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASS="${MYSQL_PASS:-root}"
TEST_DB="${TEST_DB:-yourls_captcha_test}"
PORT="${PORT:-8080}"
YOURLS_VERSION="${YOURLS_VERSION:-1.9.2}"

ADMIN_USER="test-admin"
ADMIN_PASS="test-password"
BASE_URL="http://localhost:${PORT}"

echo "=== Math CAPTCHA Plugin - Local Integration Test ==="
echo ""

# Check requirements
echo "Checking requirements..."
command -v php >/dev/null 2>&1 || { echo "PHP is required"; exit 1; }
command -v mysql >/dev/null 2>&1 || { echo "mysql client is required"; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "curl is required"; exit 1; }
php -m | grep -qi pdo_mysql || { echo "PHP pdo_mysql extension is required"; exit 1; }

mysql_cmd() {
    mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASS" "$@"
}

mysql_cmd -e "SELECT 1;" >/dev/null || { echo "Cannot connect to MySQL at ${MYSQL_HOST}"; exit 1; }

# Create temporary directory
TEST_DIR=$(mktemp -d)
YOURLS_DIR="$TEST_DIR/yourls"
echo "Test directory: $TEST_DIR"

# Cleanup function
cleanup() {
    echo "Cleaning up..."
    if [ -f "$TEST_DIR/php-server.pid" ]; then
        kill "$(cat "$TEST_DIR/php-server.pid")" 2>/dev/null || true
    fi
    mysql_cmd -e "DROP DATABASE IF EXISTS \`${TEST_DB}\`;" 2>/dev/null || true
    rm -rf "$TEST_DIR"
    echo "Done"
}

trap cleanup EXIT

# Create database
mysql_cmd -e "DROP DATABASE IF EXISTS \`${TEST_DB}\`; CREATE DATABASE \`${TEST_DB}\`;"

# Download YOURLS
echo "Downloading YOURLS ${YOURLS_VERSION}..."
cd "$TEST_DIR"
wget -q "https://github.com/YOURLS/YOURLS/archive/refs/tags/${YOURLS_VERSION}.tar.gz" -O yourls.tar.gz
tar -xzf yourls.tar.gz
mv "YOURLS-${YOURLS_VERSION}" yourls

# Copy plugin
PLUGIN_DIR=$(cd "$(dirname "$0")/../../plugins/math-captcha" && pwd)
mkdir -p "$YOURLS_DIR/user/plugins"
cp -r "$PLUGIN_DIR" "$YOURLS_DIR/user/plugins/math-captcha"

# Create config
cat > "$YOURLS_DIR/user/config.php" << EOF
<?php
/* YOURLS configuration for local integration testing */
define( 'YOURLS_DB_USER', '${MYSQL_USER}' );
define( 'YOURLS_DB_PASS', '${MYSQL_PASS}' );
define( 'YOURLS_DB_NAME', '${TEST_DB}' );
define( 'YOURLS_DB_HOST', '${MYSQL_HOST}' );
define( 'YOURLS_DB_PREFIX', 'yourls_' );
define( 'YOURLS_SITE', '${BASE_URL}' );
define( 'YOURLS_HOURS_OFFSET', 0 );
define( 'YOURLS_LANG', '' );
define( 'YOURLS_UNIQUE_URLS', true );
define( 'YOURLS_PRIVATE', true );
define( 'YOURLS_COOKIEKEY', 'local-integration-test-cookie-key' );
define( 'YOURLS_NO_HASH_PASSWORD', true );
define( 'YOURLS_DEBUG', true );
define( 'YOURLS_URL_CONVERT', 36 );
\$yourls_user_passwords = [ '${ADMIN_USER}' => '${ADMIN_PASS}' ];
\$yourls_reserved_URL = [ 'admin' ];
date_default_timezone_set( 'UTC' );
EOF

# Install YOURLS database and activate the plugin
echo "Installing YOURLS database..."
cat > "$TEST_DIR/install-yourls.php" << EOF
<?php
define( 'YOURLS_INSTALLING', true );
require '${YOURLS_DIR}/includes/load-yourls.php';
require_once YOURLS_INC . '/functions-install.php';

\$result = yourls_create_sql_tables();
print_r( \$result );
if ( ! empty( \$result['error'] ) ) {
    exit( 1 );
}

yourls_update_option( 'active_plugins', array( 'math-captcha/plugin.php' ) );
echo "Plugin activated\n";
EOF
php "$TEST_DIR/install-yourls.php"

# Router: emulate the YOURLS .htaccess rewrite rules for PHP's built-in server
cat > "$YOURLS_DIR/router.php" << 'EOF'
<?php
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( $path !== '/' && file_exists( __DIR__ . $path ) ) {
    return false;
}
require __DIR__ . '/yourls-loader.php';
EOF

# Start server
echo "Starting PHP web server on port ${PORT}..."
php -S "localhost:${PORT}" -t "$YOURLS_DIR" "$YOURLS_DIR/router.php" > "$TEST_DIR/php-server.log" 2>&1 &
echo $! > "$TEST_DIR/php-server.pid"

for i in {1..15}; do
    code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/admin/" || true)
    if [ "$code" = "200" ]; then
        echo "Server is running"
        break
    fi
    sleep 1
    if [ "$i" = "15" ]; then
        echo "Server did not come up"
        tail -50 "$TEST_DIR/php-server.log"
        exit 1
    fi
done

extract_nonce() {
    grep -oP '<input[^>]*nonce-add[^>]*>' "$1" | grep -oP 'value="\K[^"]+' | head -1
}

fetch_admin() {
    # $1 = cookie jar, $2 = output file. Logs in (the login form is
    # CSRF-protected, so grab its nonce first) and returns the admin page.
    curl -s -c "$1" -o "$2.login" "${BASE_URL}/admin/"
    local login_nonce
    login_nonce=$(grep -oP '<input[^>]*name="nonce"[^>]*>' "$2.login" | grep -oP 'value="\K[^"]+' | head -1)
    [ -n "$login_nonce" ] || { echo "FAIL: login nonce not found"; exit 1; }

    # A successful login answers with a redirect and sets the auth cookie;
    # fetch the admin page with a plain GET afterwards.
    curl -s -b "$1" -c "$1" \
        --data-urlencode "username=${ADMIN_USER}" \
        --data-urlencode "password=${ADMIN_PASS}" \
        --data-urlencode "nonce=${login_nonce}" \
        --data-urlencode "submit=submit" \
        -o /dev/null \
        "${BASE_URL}/admin/"

    curl -s -b "$1" -c "$1" -o "$2" "${BASE_URL}/admin/"
}

submit_add() {
    # $1 = cookie jar (holds the auth session), remaining args are form fields
    local jar="$1"; shift
    curl -s -b "$jar" \
        --data-urlencode "action=add" \
        "$@" \
        "${BASE_URL}/admin/admin-ajax.php"
}

# --- Test 1: CAPTCHA appears on admin page, correct answer accepted ---
echo ""
echo "Test 1: submit URL with correct CAPTCHA answer"
fetch_admin "$TEST_DIR/cookies1.txt" "$TEST_DIR/admin1.html"

question=$(grep -oP '<span id="math-captcha-question">\s*\K[0-9]+ \+ [0-9]+' "$TEST_DIR/admin1.html" || true)
nonce=$(extract_nonce "$TEST_DIR/admin1.html")

[ -n "$question" ] || { echo "FAIL: CAPTCHA question not found on admin page"; exit 1; }
[ -n "$nonce" ] || { echo "FAIL: add-url nonce not found on admin page"; exit 1; }

num1=$(echo "$question" | awk '{print $1}')
num2=$(echo "$question" | awk '{print $3}')
answer=$((num1 + num2))
echo "CAPTCHA: $question = $answer"

response=$(submit_add "$TEST_DIR/cookies1.txt" \
    --data-urlencode "nonce=$nonce" \
    --data-urlencode "url=https://github.com/MarcProe/simple-antispam" \
    --data-urlencode "keyword=test123" \
    --data-urlencode "title=Test URL" \
    --data-urlencode "math_captcha_answer=$answer")
echo "Response: $response"
echo "$response" | grep -q '"status":"success"' || { echo "FAIL: expected successful add"; exit 1; }
echo "PASS"

# --- Test 2: short URL redirects ---
echo ""
echo "Test 2: short URL redirects to target"
redirect=$(curl -s -o /dev/null -w "%{redirect_url}" "${BASE_URL}/test123")
echo "Redirects to: $redirect"
echo "$redirect" | grep -q "github.com/MarcProe/simple-antispam" || { echo "FAIL: redirect broken"; exit 1; }
echo "PASS"

# --- Test 3: wrong CAPTCHA rejected ---
echo ""
echo "Test 3: wrong CAPTCHA answer rejected"
fetch_admin "$TEST_DIR/cookies3.txt" "$TEST_DIR/admin3.html"
nonce=$(extract_nonce "$TEST_DIR/admin3.html")

# 99999 can never be correct: operands are 1-99, so the max answer is 198
response=$(submit_add "$TEST_DIR/cookies3.txt" \
    --data-urlencode "nonce=$nonce" \
    --data-urlencode "url=https://example.com/wrong" \
    --data-urlencode "keyword=wrongkw" \
    --data-urlencode "math_captcha_answer=99999")
echo "Response: $response"
echo "$response" | grep -q "error:captcha_wrong" || { echo "FAIL: expected error:captcha_wrong"; exit 1; }
echo "PASS"

# --- Test 4: missing CAPTCHA rejected ---
echo ""
echo "Test 4: missing CAPTCHA answer rejected"
fetch_admin "$TEST_DIR/cookies4.txt" "$TEST_DIR/admin4.html"
nonce=$(extract_nonce "$TEST_DIR/admin4.html")

response=$(submit_add "$TEST_DIR/cookies4.txt" \
    --data-urlencode "nonce=$nonce" \
    --data-urlencode "url=https://example.com/missing" \
    --data-urlencode "keyword=missingkw")
echo "Response: $response"
echo "$response" | grep -q "error:captcha_missing" || { echo "FAIL: expected error:captcha_missing"; exit 1; }
echo "PASS"

# --- Optional: screenshots with a real browser (SCREENSHOTS=1) ---
if [ "${SCREENSHOTS:-0}" = "1" ]; then
    echo ""
    echo "Capturing screenshots with Playwright..."
    SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
    command -v node >/dev/null 2>&1 || { echo "FAIL: node is required for SCREENSHOTS=1"; exit 1; }
    [ -d "$SCRIPT_DIR/node_modules/playwright" ] || (cd "$SCRIPT_DIR" && npm install)
    # Debug output would clutter the screenshots; the config is re-read on
    # every request, so no server restart is needed.
    sed -i "s/define( 'YOURLS_DEBUG', true );/define( 'YOURLS_DEBUG', false );/" "$YOURLS_DIR/user/config.php"
    BASE_URL="$BASE_URL" ADMIN_USER="$ADMIN_USER" ADMIN_PASS="$ADMIN_PASS" \
        SCREENSHOT_DIR="${SCREENSHOT_DIR:-$SCRIPT_DIR/screenshots}" \
        node "$SCRIPT_DIR/screenshots.mjs"
fi

echo ""
echo "=== All integration tests passed ==="
