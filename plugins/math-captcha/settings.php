<?php

/**
 * Settings page for Math CAPTCHA plugin
 */

if (!defined('YOURLS_ABSPATH')) {
    die();
}

/**
 * Settings page handler
 */
function math_captcha_settings_page(): void
{
    // Save settings if form was submitted
    if (
        isset($_POST['math_captcha_save']) &&
        isset($_POST['nonce']) &&
        yourls_verify_nonce('math_captcha_save_settings', $_POST['nonce'])
    ) {
        $min = isset($_POST['math_captcha_min']) ? (int) $_POST['math_captcha_min'] : 1;
        $max = isset($_POST['math_captcha_max']) ? (int) $_POST['math_captcha_max'] : 49;

        // Validate inputs
        if ($min < 0) {
            $min = 0;
        }
        if ($max < $min) {
            $max = $min + 1;
        }

        yourls_update_option('math_captcha_min', $min);
        yourls_update_option('math_captcha_max', $max);

        echo '<div class="notice notice-success"><strong>Settings saved.</strong></div>';
    }

    // Get current settings
    $min = yourls_get_option('math_captcha_min', 1);
    $max = yourls_get_option('math_captcha_max', 49);

    echo '<div class="wrap">';
    echo '<h1>Math CAPTCHA Settings</h1>';
    echo '<form method="post" action="">';
    echo '<input type="hidden" name="nonce" value="' .
        yourls_esc_attr(yourls_create_nonce('math_captcha_save_settings')) . '" />';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="math_captcha_min">Minimum Number</label></th>';
    echo '<td>';
    echo '<input type="number" id="math_captcha_min" name="math_captcha_min" value="' .
        yourls_esc_attr((string) $min) . '" min="0" max="99" />';
    echo '<p class="description">The minimum number to use in addition problems ';
    echo '(default: 1).</p>';
    echo '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="math_captcha_max">Maximum Number</label></th>';
    echo '<td>';
    echo '<input type="number" id="math_captcha_max" name="math_captcha_max" value="' .
        yourls_esc_attr((string) $max) . '" min="1" max="99" />';
    echo '<p class="description">The maximum number to use in addition problems ';
    echo '(default: 49). Must be greater than or equal to the minimum.</p>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit"><input type="submit" name="math_captcha_save" ';
    echo 'class="button-primary" value="Save Settings" /></p>';
    echo '</form>';
    echo '</div>';
}
