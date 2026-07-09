<?php
/**
 * Psalm stubs for YOURLS core functions used by this plugin.
 * These are never executed; they exist only to give Psalm type information.
 */

/**
 * @param string $hook
 * @param callable $function
 * @return void
 */
function yourls_add_action(string $hook, $function): void {}

/**
 * @param string $hook
 * @param callable $function
 * @param int $priority
 * @param int $accepted_args
 * @return void
 */
function yourls_add_filter(string $hook, $function, int $priority = 10, int $accepted_args = 1): void {}

/**
 * @param string $text
 * @param string $domain
 * @return string
 */
function yourls__(string $text, string $domain = 'default'): string { return $text; }

/**
 * @param string $text
 * @return string
 */
function yourls_esc_html(string $text): string { return $text; }

/**
 * @param string $text
 * @return string
 */
function yourls_esc_attr(string $text): string { return $text; }

/**
 * @param string $int
 * @return string
 */
function yourls_sanitize_int(string $int): string { return $int; }

/**
 * @param string $name
 * @param mixed $default
 * @return mixed
 */
function yourls_get_option(string $name, $default = null) { return $default; }

/**
 * @param string $name
 * @param mixed $value
 * @return bool
 */
function yourls_update_option(string $name, $value): bool { return true; }

/**
 * @param string $page
 * @param string $title
 * @param callable $function
 * @return void
 */
function yourls_register_plugin_page(string $page, string $title, callable $function): void {}

/**
 * @param string $action
 * @return string
 */
function yourls_create_nonce(string $action): string { return ''; }

/**
 * @param string $action
 * @param string $nonce
 * @return bool
 */
function yourls_verify_nonce(string $action, string $nonce): bool { return true; }
