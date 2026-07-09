<?php
/**
 * Test bootstrap for Math CAPTCHA plugin
 */

// Define YOURLS constants that the plugin expects
if (!defined('YOURLS_ABSPATH')) {
    define('YOURLS_ABSPATH', true);
}

// Mock YOURLS functions
if (!function_exists('yourls__')) {
    function yourls__( $text, $domain = 'default' ) {
        return $text;
    }
}

if (!function_exists('yourls_esc_html')) {
    function yourls_esc_html( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if (!function_exists('yourls_esc_attr')) {
    function yourls_esc_attr( $text ) {
        return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
    }
}

if (!function_exists('yourls_sanitize_int')) {
    function yourls_sanitize_int( $int ) {
        return (int) preg_replace( '/[^0-9]/', '', strval( $int ) );
    }
}

if (!function_exists('yourls_add_action')) {
    function yourls_add_action( $hook, $function ) {
        // For testing, we just store the hooks
        global $yourls_actions;
        if (!isset($yourls_actions[$hook])) {
            $yourls_actions[$hook] = array();
        }
        $yourls_actions[$hook][] = $function;
    }
}

if (!function_exists('yourls_add_filter')) {
    function yourls_add_filter( $hook, $function, $priority = 10, $args = 1 ) {
        // For testing, we just store the filters
        global $yourls_filters;
        if (!isset($yourls_filters[$hook])) {
            $yourls_filters[$hook] = array();
        }
        $yourls_filters[$hook][$priority] = array('function' => $function, 'args' => $args);
    }
}

if (!function_exists('yourls_get_option')) {
    function yourls_get_option( $name, $default = null ) {
        global $yourls_options;
        return isset($yourls_options[$name]) ? $yourls_options[$name] : $default;
    }
}

if (!function_exists('yourls_update_option')) {
    function yourls_update_option( $name, $value ) {
        global $yourls_options;
        $yourls_options[$name] = $value;
        return true;
    }
}

if (!function_exists('yourls_register_plugin_page')) {
    function yourls_register_plugin_page( $page, $title, $function ) {
        // For testing, we just store the registered pages
        global $yourls_plugin_pages;
        if (!isset($yourls_plugin_pages)) {
            $yourls_plugin_pages = array();
        }
        $yourls_plugin_pages[$page] = array('title' => $title, 'function' => $function);
    }
}

if (!function_exists('yourls_create_nonce')) {
    function yourls_create_nonce( $action ) {
        return 'test-nonce-' . $action;
    }
}

if (!function_exists('yourls_verify_nonce')) {
    function yourls_verify_nonce( $action, $nonce ) {
        return true; // For testing, always return true
    }
}

// Start session for tests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize global arrays for storing hooks and options
if (!isset($yourls_actions)) {
    $yourls_actions = array();
}
if (!isset($yourls_filters)) {
    $yourls_filters = array();
}
if (!isset($yourls_options)) {
    $yourls_options = array();
}
if (!isset($yourls_plugin_pages)) {
    $yourls_plugin_pages = array();
}

// Load every plugin in the monorepo so its hooks and functions are available
// to the test suite. New plugins added under plugins/ are picked up automatically.
foreach (glob(__DIR__ . '/../plugins/*/plugin.php') as $plugin_file) {
    require_once $plugin_file;
}
