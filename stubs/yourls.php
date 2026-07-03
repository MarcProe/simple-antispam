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
