#!/bin/bash

# Local integration test script for Math CAPTCHA plugin
# This script sets up YOURLS locally and tests the plugin

set -e

echo "=== Math CAPTCHA Plugin - Local Integration Test ==="
echo ""

# Check requirements
echo "Checking requirements..."
command -v php >/dev/null 2>&1 || { echo "PHP is required"; exit 1; }
command -v sqlite3 >/dev/null 2>&1 || { echo "SQLite3 is required"; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "curl is required"; exit 1; }

php -m | grep -q sqlite || { echo "PHP SQLite extension is required"; exit 1; }

# Create temporary directory
TEST_DIR=$(mktemp -d)
echo "Test directory: $TEST_DIR"

# Cleanup function
cleanup() {
    echo "Cleaning up..."
    if [ -f "$TEST_DIR/php-server.pid" ]; then
        kill $(cat "$TEST_DIR/php-server.pid") 2>/dev/null || true
    fi
    rm -rf "$TEST_DIR"
    echo "Done"
}

trap cleanup EXIT

# Download YOURLS
echo "Downloading YOURLS..."
cd "$TEST_DIR"
wget -q https://github.com/YOURLS/YOURLS/archive/refs/tags/1.9.2.tar.gz -O yourls.tar.gz
tar -xzf yourls.tar.gz
mv YOURLS-1.9.2 yourls

# Create user directory structure
mkdir -p yourls/user
mkdir -p yourls/user/plugins
mkdir -p yourls/user/data

# Copy plugin
PLUGIN_DIR=$(cd "$(dirname "$0")/../../" && pwd)
cp -r "$PLUGIN_DIR" yourls/user/plugins/math-captcha

# Create config
cat > yourls/user/config.php << 'EOF'
<?php
/**
 * YOURLS configuration for local testing
 */

define( 'YOURLS_DB_DRIVER', 'sqlite' );
define( 'YOURLS_DB_FILE', __DIR__ . '/data/yourls.db' );
define( 'YOURLS_SITE', 'http://localhost:8080' );
define( 'YOURLS_HOURS_OFFSET', 0 );
define( 'YOURLS_UNIQUE_URLS', true );
define( 'YOURLS_PRIVATE', false );
define( 'YOURLS_COOKIEKEY', 'local_test_key_' . md5(time()) );
define( 'YOURLS_DEBUG', false );
define( 'YOURLS_VERSION', '1.9.2' );
define( 'YOURLS_ABSPATH', dirname( __FILE__ ).'/' );
define( 'YOURLS_DB_USER', '' );
define( 'YOURLS_DB_PASS', '' );
define( 'YOURLS_DB_NAME', '' );
define( 'YOURLS_DB_HOST', '' );

date_default_timezone_set( 'UTC' );
EOF

# Initialize database
echo "Initializing database..."
cd yourls
touch user/data/yourls.db
chmod 777 user/data/yourls.db

# YOURLS 1.9.2 doesn't include a separate SQL file in the tarball
# The schema is embedded in the installer. We'll create it manually.
echo "Creating database schema..."
php -r "
\$db = new SQLite3('user/data/yourls.db');

// Create the tables manually based on YOURLS schema
\$tables = [
    \"CREATE TABLE IF NOT EXISTS yourls_url (\n        keyword VARCHAR(200) PRIMARY KEY,\n        url TEXT NOT NULL,\n        title TEXT,\n        timestamp DATETIME,\n        ip VARCHAR(45),\n        clicks MEDIUMINT UNSIGNED DEFAULT 0\n    )\",
    \"CREATE TABLE IF NOT EXISTS yourls_options (\n        option_name VARCHAR(255) PRIMARY KEY,\n        option_value TEXT\n    )\",
    \"CREATE TABLE IF NOT EXISTS yourls_log (\n        click_id INTEGER PRIMARY KEY AUTOINCREMENT,\n        keyword VARCHAR(200) NOT NULL,\n        ip VARCHAR(45),\n        click_time DATETIME,\n        referrer TEXT,\n        user_agent TEXT,\n        country_code CHAR(2)\n    )\"
];

foreach (\$tables as \$sql) {
    if (\$db->exec(\$sql) === false) {
        echo \"SQL Error: \" . \$db->lastErrorMsg() . \"\\n\";
        exit(1);
    }
}

echo \"Database schema created successfully\\n\";
"

# Activate our plugin
echo "Activating plugin..."
php -r "
\$db = new SQLite3('user/data/yourls.db');
\$db->exec(\"INSERT OR REPLACE INTO yourls_options (option_name, option_value) VALUES ('active_plugins', 'math-captcha/plugin.php')\");
echo \"Plugin activated in database\\n\";
"

# Verify
sqlite3 user/data/yourls.db ".tables"

# Start PHP server
echo "Starting PHP server on port 8080..."
php -S localhost:8080 -t yourls > "$TEST_DIR/php-server.log" 2>&1 &
echo $! > "$TEST_DIR/php-server.pid"

# Wait for server
for i in {1..15}; do
    if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200"; then
        echo "Server is running"
        break
    fi
    sleep 1
    echo -n "."
done

echo ""

# Test 1: Main page
echo "Test 1: Main page..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200"; then
    echo "\u2713 Main page loads"
else
    echo "\u2717 Main page failed"
    exit 1
fi

# Test 2: Admin page and CAPTCHA
echo "Test 2: Admin page and CAPTCHA..."
curl -s -c "$TEST_DIR/cookies.txt" -b "$TEST_DIR/cookies.txt" \
    -o "$TEST_DIR/admin.html" \
    http://localhost:8080/admin/

if grep -q "Math CAPTCHA" "$TEST_DIR/admin.html"; then
    echo "\u2713 CAPTCHA field found"
else
    echo "\u26a0 CAPTCHA field not found in admin page (might be on add form)"
fi

# Extract CAPTCHA question
QUESTION=$(grep -oP '<span id="math-captcha-question">\s*\K[0-9]+ \+ [0-9]+' "$TEST_DIR/admin.html" || echo "")

if [ -z "$QUESTION" ]; then
    echo "Trying admin/index.php..."
    curl -s -c "$TEST_DIR/cookies.txt" -b "$TEST_DIR/cookies.txt" \
        -o "$TEST_DIR/addnew.html" \
        http://localhost:8080/admin/index.php
    
    QUESTION=$(grep -oP '<span id="math-captcha-question">\s*\K[0-9]+ \+ [0-9]+' "$TEST_DIR/addnew.html" || echo "")
fi

if [ -z "$QUESTION" ]; then
    echo "\u2717 Could not find CAPTCHA question"
    echo "Admin page content:"
    head -50 "$TEST_DIR/admin.html"
    exit 1
fi

echo "\u2713 Found CAPTCHA: $QUESTION"

# Calculate answer
NUM1=$(echo "$QUESTION" | awk '{print $1}')
NUM2=$(echo "$QUESTION" | awk '{print $3}')
ANSWER=$((NUM1 + NUM2))
echo "  Answer: $ANSWER"

# Test 3: Submit URL with correct CAPTCHA
echo "Test 3: Submit URL with correct CAPTCHA..."
RESPONSE=$(curl -s -c "$TEST_DIR/cookies2.txt" -b "$TEST_DIR/cookies.txt" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -H "X-Requested-With: XMLHttpRequest" \
    --data-urlencode "url=https://github.com/MarcProe/simple-antispam" \
    --data-urlencode "keyword=test123" \
    --data-urlencode "title=Test+URL" \
    --data-urlencode "math_captcha_answer=$ANSWER" \
    http://localhost:8080/admin/ajax.php)

if echo "$RESPONSE" | grep -q "shorturl"; then
    echo "\u2713 URL shortened successfully"
    KEYWORD=$(echo "$RESPONSE" | grep -oP '"keyword":"\K[^"]+' || echo "")
    echo "  Keyword: $KEYWORD"
else
    echo "\u2717 URL shortening failed"
    echo "  Response: $RESPONSE"
    exit 1
fi

# Test 4: Verify short URL
echo "Test 4: Verify short URL..."
FINAL_URL=$(curl -s -L -o /dev/null -w "%{url_effective}" http://localhost:8080/$KEYWORD)
if echo "$FINAL_URL" | grep -q "github.com/MarcProe/simple-antispam"; then
    echo "\u2713 Short URL redirects correctly"
else
    echo "\u2717 Short URL redirect failed"
    echo "  Got: $FINAL_URL"
    exit 1
fi

# Test 5: Wrong CAPTCHA
echo "Test 5: Wrong CAPTCHA..."
curl -s -c "$TEST_DIR/cookies3.txt" -b "$TEST_DIR/cookies3.txt" \
    -o /dev/null \
    http://localhost:8080/admin/

RESPONSE=$(curl -s -c "$TEST_DIR/cookies4.txt" -b "$TEST_DIR/cookies3.txt" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -H "X-Requested-With: XMLHttpRequest" \
    --data-urlencode "url=https://example.com/wrong" \
    --data-urlencode "keyword=wrong" \
    --data-urlencode "title=Wrong" \
    --data-urlencode "math_captcha_answer=99999" \
    http://localhost:8080/admin/ajax.php)

if echo "$RESPONSE" | grep -q "error:captcha_wrong"; then
    echo "\u2713 Wrong CAPTCHA rejected"
else
    echo "\u2717 Wrong CAPTCHA not rejected"
    echo "  Response: $RESPONSE"
    exit 1
fi

# Test 6: Missing CAPTCHA
echo "Test 6: Missing CAPTCHA..."
curl -s -c "$TEST_DIR/cookies5.txt" -b "$TEST_DIR/cookies5.txt" \
    -o /dev/null \
    http://localhost:8080/admin/

RESPONSE=$(curl -s -c "$TEST_DIR/cookies6.txt" -b "$TEST_DIR/cookies5.txt" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -H "X-Requested-With: XMLHttpRequest" \
    --data-urlencode "url=https://example.com/missing" \
    --data-urlencode "keyword=missing" \
    --data-urlencode "title=Missing" \
    http://localhost:8080/admin/ajax.php)

if echo "$RESPONSE" | grep -q "error:captcha_missing"; then
    echo "\u2713 Missing CAPTCHA rejected"
else
    echo "\u2717 Missing CAPTCHA not rejected"
    echo "  Response: $RESPONSE"
    exit 1
fi

echo ""
echo "=== All tests passed! ==="
echo ""
echo "Test directory: $TEST_DIR (will be cleaned up on exit)"
