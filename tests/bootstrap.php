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

// Start session for tests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load the plugin
require_once __DIR__ . '/../plugin.php';
