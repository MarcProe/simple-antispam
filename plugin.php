<?php
/*
Plugin Name: Math CAPTCHA
Plugin URI: https://github.com/MarcProe/simple-antispam
Description: Adds a simple math question CAPTCHA to prevent automated URL submissions.
Version: 1.3
Author: MarcProe
Author URI: https://github.com/MarcProe
*/

declare(strict_types=1);

if (!defined('YOURLS_ABSPATH')) {
    die();
}

// Constants for magic numbers and error codes
define('MATH_CAPTCHA_MIN', 1);
define('MATH_CAPTCHA_MAX', 99);
define('MATH_CAPTCHA_ERROR_MISSING', 'error:captcha_missing');
define('MATH_CAPTCHA_ERROR_WRONG', 'error:captcha_wrong');
define('MATH_CAPTCHA_ERROR_CSRF', 'error:csrf');

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'use_strict_mode'      => true,
        'cookie_secure'        => true,
        'cookie_httponly'      => true,
        'cookie_samesite'      => 'Strict',
        'sid_length'           => 48,
        'sid_bits_per_character' => 6,
    ]);
}

function math_captcha_generate_question(): void
{
    $num1 = rand(MATH_CAPTCHA_MIN, MATH_CAPTCHA_MAX);
    $num2 = rand(MATH_CAPTCHA_MIN, MATH_CAPTCHA_MAX);
    $_SESSION['math_captcha_question'] = "$num1 + $num2";
    $_SESSION['math_captcha_answer']   = $num1 + $num2;
    $_SESSION['math_captcha_ip']       = $_SERVER['REMOTE_ADDR'];
}

function math_captcha_get_question(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['math_captcha_answer'])) {
        math_captcha_generate_question();
    }
    return (string) ($_SESSION['math_captcha_question'] ?? '');
}

function math_captcha_verify(string $user_answer): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['math_captcha_answer'])) {
        return false;
    }
    // Verify IP to prevent session hijacking
    if (isset($_SESSION['math_captcha_ip']) && $_SESSION['math_captcha_ip'] !== $_SERVER['REMOTE_ADDR']) {
        unset($_SESSION['math_captcha_question'], $_SESSION['math_captcha_answer']);
        return false;
    }
    $correct = (int) $_SESSION['math_captcha_answer'];
    $given   = (int) $user_answer;
    unset($_SESSION['math_captcha_question'], $_SESSION['math_captcha_answer'], $_SESSION['math_captcha_ip']);
    return $given === $correct;
}

function math_captcha_add_field_to_form(): void
{
    $question = math_captcha_get_question();
    ?>
    <div id="math-captcha-field" role="group" aria-labelledby="math-captcha-label math-captcha-question">
        <label id="math-captcha-label" for="math-captcha-answer"><strong><?php echo yourls_esc_html(yourls__('Math CAPTCHA')); ?></strong></label>:
        <span id="math-captcha-question" aria-live="polite"> <?php echo yourls_esc_html($question); ?> = </span>
        <input
            type="text"
            id="math-captcha-answer"
            name="math_captcha_answer"
            class="text"
            size="10"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="off"
            aria-describedby="math-captcha-question"
            placeholder="<?php echo yourls_esc_attr(yourls__('Answer')); ?>"
        />
        <?php yourls_nonce_field('math_captcha_nonce'); ?>
    </div>
    <?php
}
yourls_add_action('html_addnew', 'math_captcha_add_field_to_form');

/**
 * Validates the CAPTCHA request and returns the user answer or error type.
 * @return string|null Returns the validated answer, 'csrf' for CSRF error, or null for bookmarklet requests.
 */
function math_captcha_validate_request(): ?string
{
    // Bookmarklet requests have no form, skip CAPTCHA
    if (isset($_GET['u']) || isset($_GET['up'])) {
        return null;
    }

    // Verify CSRF nonce
    if (!yourls_verify_nonce('math_captcha_nonce', $_REQUEST['math_captcha_nonce'] ?? '')) {
        return 'csrf';
    }

    // Validate and sanitize the answer
    $raw_answer = $_REQUEST['math_captcha_answer'] ?? '';
    if (!is_string($raw_answer)) {
        return '';
    }

    $user_answer = filter_var($raw_answer, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => MATH_CAPTCHA_MIN, 'max_range' => MATH_CAPTCHA_MAX * 2],
    ]);

    return $user_answer !== false ? (string) $user_answer : '';
}

yourls_add_filter('shunt_add_new_link', 'math_captcha_verify_on_add', 10, 4);

/**
 * @param mixed $shunt
 * @param string $url
 * @param string|null $keyword
 * @param string|null $title
 * @return array|mixed
 */
function math_captcha_verify_on_add($shunt, string $url, ?string $keyword, ?string $title): array|mixed
{
    $validation_result = math_captcha_validate_request();

    if ($validation_result === null) {
        return $shunt; // Bookmarklet: skip CAPTCHA
    }

    if ($validation_result === 'csrf') {
        return [
            'status'     => 'fail',
            'code'       => MATH_CAPTCHA_ERROR_CSRF,
            'message'    => yourls__('Invalid security token.'),
            'errorCode'  => '403',
            'statusCode' => '403',
        ];
    }

    if ($validation_result === '') {
        return [
            'status'     => 'fail',
            'code'       => MATH_CAPTCHA_ERROR_MISSING,
            'message'    => yourls__('Please solve the math CAPTCHA to shorten URLs.'),
            'errorCode'  => '400',
            'statusCode' => '400',
        ];
    }

    if (!math_captcha_verify($validation_result)) {
        math_captcha_generate_question();
        return [
            'status'     => 'fail',
            'code'       => MATH_CAPTCHA_ERROR_WRONG,
            'message'    => yourls__('Incorrect answer. Please try again.'),
            'errorCode'  => '400',
            'statusCode' => '400',
        ];
    }

    return $shunt;
}

function math_captcha_add_css(): void
{
    ?>
    <style>
    #math-captcha-field { margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; }
    #math-captcha-question { font-weight: bold; color: #5d4037; }
    #math-captcha-answer { width: 80px; margin-left: 10px; }
    </style>
    <?php
}
yourls_add_action('admin_page_before_form', 'math_captcha_add_css');

function math_captcha_add_js(): void
{
    ?>
    <script>
    jQuery(document).ready(function($) {
        if (typeof add_link !== 'function') { return; }

        var _orig = add_link;
        add_link = function() {
            var answer = $('#math-captcha-answer').val();
            if (!answer) {
                feedback('Please solve the math CAPTCHA to shorten URLs.', 'fail');
                return false;
            }

            // Use local variable to avoid global pollution
            var _origGetJSON = $.getJSON;
            $.getJSON = function(url, data, callback) {
                if (data && data.action === 'add') {
                    data = $.extend({}, data, { math_captcha_answer: answer });
                }
                return _origGetJSON.call(this, url, data, callback);
            };

            try {
                return _orig.apply(this, arguments);
            } finally {
                $.getJSON = _origGetJSON;
            }
        };
    });
    </script>
    <?php
}
yourls_add_action('admin_page_before_table', 'math_captcha_add_js');
