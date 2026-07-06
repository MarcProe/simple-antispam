<?php
/*
Plugin Name: Math CAPTCHA
Plugin URI: https://github.com/MarcProe/simple-antispam
Description: Adds a simple math question CAPTCHA to prevent automated URL submissions.
Version: 1.2
Author: MarcProe
Author URI: https://github.com/MarcProe
*/

if (!defined('YOURLS_ABSPATH')) {
    die();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function math_captcha_generate_question(): void
{
    $num1 = rand(1, 99);
    $num2 = rand(1, 99);
    $_SESSION['math_captcha_question'] = "$num1 + $num2";
    $_SESSION['math_captcha_answer']   = $num1 + $num2;
}

function math_captcha_get_question(): string
{
    if (!isset($_SESSION['math_captcha_answer'])) {
        math_captcha_generate_question();
    }
    return (string) $_SESSION['math_captcha_question'];
}

function math_captcha_verify(string $user_answer): bool
{
    if (!isset($_SESSION['math_captcha_answer'])) {
        return false;
    }
    $correct = (int) $_SESSION['math_captcha_answer'];
    $given   = (int) $user_answer;
    unset($_SESSION['math_captcha_question'], $_SESSION['math_captcha_answer']);
    return $given === $correct;
}

function math_captcha_add_field_to_form(): void
{
    $question = math_captcha_get_question();
    ?>
    <div id="math-captcha-field" role="group" aria-labelledby="math-captcha-label">
        <label id="math-captcha-label" for="math-captcha-answer"><strong><?php echo yourls_esc_html(yourls__('Math CAPTCHA')); ?></strong></label>:
        <span id="math-captcha-question"> <?php echo yourls_esc_html($question); ?> = </span>
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
    </div>
    <?php
}
yourls_add_action('html_addnew', 'math_captcha_add_field_to_form');

yourls_add_filter('shunt_add_new_link', 'math_captcha_verify_on_add', 10, 4);

/**
 * @param mixed $shunt
 * @param string $url
 * @param string $keyword
 * @param string $title
 * @return mixed
 */
function math_captcha_verify_on_add($shunt, $url, $keyword, $title)
{
    // Bookmarklet requests have no form, skip CAPTCHA
    if (isset($_GET['u']) || isset($_GET['up'])) {
        return $shunt;
    }

    $raw_answer  = $_REQUEST['math_captcha_answer'] ?? '';
    $user_answer = is_string($raw_answer) ? $raw_answer : '';

    if ($user_answer === '') {
        return array(
            'status'     => 'fail',
            'code'       => 'error:captcha_missing',
            'message'    => yourls__('Please solve the math CAPTCHA to shorten URLs.'),
            'errorCode'  => '400',
            'statusCode' => '400',
        );
    }

    if (!math_captcha_verify($user_answer)) {
        math_captcha_generate_question();
        return array(
            'status'     => 'fail',
            'code'       => 'error:captcha_wrong',
            'message'    => yourls__('Incorrect answer. Please try again.'),
            'errorCode'  => '400',
            'statusCode' => '400',
        );
    }

    return $shunt;
}

function math_captcha_add_css(): void
{
    ?>
    <style>
    #math-captcha-field { margin-top: 10px; padding: 10px; background: #fff8e1; border: 1px solid #ffc107; border-radius: 4px; }
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
        if ( typeof add_link !== 'function' ) { return; }

        var _orig = add_link;
        add_link = function() {
            var answer = $('#math-captcha-answer').val();
            if ( !answer ) {
                feedback( 'Please solve the math CAPTCHA to shorten URLs.', 'fail' );
                return false;
            }

            var _origGetJSON = $.getJSON;
            $.getJSON = function( url, data, callback ) {
                if ( data && data.action === 'add' ) {
                    data.math_captcha_answer = answer;
                }
                return _origGetJSON.call( this, url, data, callback );
            };

            try {
                return _orig.apply( this, arguments );
            } finally {
                $.getJSON = _origGetJSON;
            }
        };
    });
    </script>
    <?php
}
yourls_add_action('admin_page_before_table', 'math_captcha_add_js');
