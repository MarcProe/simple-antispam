<?php
/**
 * Docker setup script for YOURLS + Math CAPTCHA integration test
 * This script initializes YOURLS with SQLite and activates the plugin
 */

// Check if YOURLS is already installed
if (file_exists(__DIR__ . '/user/config.php')) {
    echo "YOURLS already configured\n";
} else {
    // Create config
    $config = <<<'EOF'
<?php
/**
 * YOURLS configuration for Docker testing
 */

// SQLite database
define( 'YOURLS_DB_DRIVER', 'sqlite' );
define( 'YOURLS_DB_FILE', __DIR__ . '/user/data/yourls.db' );

// Site settings
define( 'YOURLS_SITE', 'http://' . $_SERVER['HTTP_HOST'] );
define( 'YOURLS_HOURS_OFFSET', 0 );
define( 'YOURLS_UNIQUE_URLS', true );

// Security
define( 'YOURLS_PRIVATE', false );
define( 'YOURLS_COOKIEKEY', 'docker_test_key_' . md5(time()) );

// Debug
define( 'YOURLS_DEBUG', false );

// Version
define( 'YOURLS_VERSION', '1.9.2' );
define( 'YOURLS_ABSPATH', __DIR__ . '/' );

// Not used with SQLite
define( 'YOURLS_DB_USER', '' );
define( 'YOURLS_DB_PASS', '' );
define( 'YOURLS_DB_NAME', '' );
define( 'YOURLS_DB_HOST', '' );

// Timezone
date_default_timezone_set( 'UTC' );
EOF;
    
    file_put_contents(__DIR__ . '/user/config.php', $config);
    echo "Config created\n";
}

// Create data directory
if (!is_dir(__DIR__ . '/user/data')) {
    mkdir(__DIR__ . '/user/data', 0777, true);
    echo "Data directory created\n";
}

// Initialize database
$dbFile = __DIR__ . '/user/data/yourls.db';
if (!file_exists($dbFile)) {
    // Create empty database
    touch($dbFile);
    chmod($dbFile, 0777);
    
    // Load schema
    $db = new SQLite3($dbFile);
    $schema = file_get_contents(__DIR__ . '/sql/yourls.sql');
    $db->exec($schema);
    
    // Activate our plugin
    $db->exec("INSERT OR REPLACE INTO yourls_options (option_name, option_value) VALUES ('active_plugins', 'math-captcha/plugin.php')");
    
    echo "Database initialized with Math CAPTCHA plugin\n";
} else {
    echo "Database already exists\n";
}

// Check if plugin directory exists
$pluginDir = __DIR__ . '/user/plugins/math-captcha';
if (is_dir($pluginDir) && file_exists($pluginDir . '/plugin.php')) {
    echo "Math CAPTCHA plugin is installed\n";
} else {
    echo "ERROR: Math CAPTCHA plugin not found at $pluginDir\n";
}

echo "\n=== Setup Complete ===\n";
echo "YOURLS with Math CAPTCHA is ready at http://" . $_SERVER['HTTP_HOST'] . "\n";
echo "Admin interface: http://" . $_SERVER['HTTP_HOST'] . "/admin/\n";
