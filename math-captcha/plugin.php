<?php
/*
Plugin Name: Math CAPTCHA
Plugin URI: https://github.com/MarcProe/simple-antispam
Description: Adds a simple math question CAPTCHA to prevent automated URL submissions.
Version: 1.1
Author: MarcProe
Author URI: https://github.com/MarcProe
*/

if ( !defined( 'YOURLS_ABSPATH' ) ) die();

if ( session_status() === PHP_SESSION_NONE ) {
    session_start();
}

function math_captcha_generate_question() {
    $num1 = rand( 1, 99 );
    $num2 = rand( 1, 99 );
    $_SESSION['math_captcha_question'] = "$num1 + $num2";
    $_SESSION['math_captcha_answer']   = $num1 + $num2;
}

function math_captcha_get_question() {
    if ( !isset( $_SESSION['math_captcha_answer'] ) ) {
        math_captcha_generate_question();
    }
    return $_SESSION['math_captcha_question'];
}

function math_captcha_verify( $user_answer ) {
    if ( !isset( $_SESSION['math_captcha_answer'] ) ) {
        return false;
    }
    $correct = (int) $_SESSION['math_captcha_answer'];
    $given   = (int) $user_answer;
    unset( $_SESSION['math_captcha_question'], $_SESSION['math_captcha_answer'] );
    return $given === $correct;
}

function math_captcha_add_field_to_form() {
    $question = math_captcha_get_question();
    ?>
    <div id="math-captcha-field">
        <label for="math-captcha-answer"><strong><?php echo yourls_esc_html( yourls__( 'Math CAPTCHA' ) ); ?></strong></label>:
        <span id="math-captcha-question"> <?php echo yourls_esc_html( $question ); ?> = </span>
        <input type="text" id="math-captcha-answer" name="math_captcha_answer" class="text" size="10" placeholder="<?php echo yourls_esc_attr( yourls__( 'Answer' ) ); ?>" />
    </div>
    <?php
}
yourls_add_action( 'html_addnew', 'math_captcha_add_field_to_form' );

yourls_add_filter( 'shunt_add_new_link', 'math_captcha_verify_on_add', 10, 4 );

function math_captcha_verify_on_add( $shunt, $url, $keyword, $title ) {
    // Bookmarklet requests have no form, skip CAPTCHA
    if ( isset( $_GET['u'] ) || isset( $_GET['up'] ) ) {
        return $shunt;
    }

    $user_answer = isset( $_REQUEST['math_captcha_answer'] ) ? $_REQUEST['math_captcha_answer'] : '';

    if ( $user_answer === '' ) {
        return array(
            'status'     => 'fail',
            'code'       => 'error:captcha_missing',
            'message'    => yourls__( 'Please solve the math CAPTCHA to shorten URLs.' ),
            'errorCode'  => '400',
            'statusCode' => '400',
        );
    }

    if ( !math_captcha_verify( $user_answer ) ) {
        math_captcha_generate_question();
        return array(
            'status'     => 'fail',
            'code'       => 'error:captcha_wrong',
            'message'    => yourls__( 'Incorrect answer. Please try again.' ),
            'errorCode'  => '400',
            'statusCode' => '400',
        );
    }

    return $shunt;
}

function math_captcha_add_css() {
    ?>
    <style>
    #math-captcha-field { margin-top: 10px; padding: 10px; background: #fff8e1; border: 1px solid #ffc107; border-radius: 4px; }
    #math-captcha-question { font-weight: bold; color: #5d4037; }
    #math-captcha-answer { width: 80px; margin-left: 10px; }
    </style>
    <?php
}
yourls_add_action( 'admin_page_before_form', 'math_captcha_add_css' );

function math_captcha_add_js() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        if ( typeof add_link !== 'function' ) { return; }

        add_link = function() {
            var captcha_answer = $('#math-captcha-answer').val();
            if ( !captcha_answer ) {
                feedback( 'Please solve the math CAPTCHA to shorten URLs.', 'fail' );
                return false;
            }

            var newurl  = $('#add-url').val();
            var nonce   = $('#nonce-add').val();
            var keyword = $('#add-keyword').val();
            var nextid  = parseInt( $('#main_table tbody tr[id^="id-"]').length ) + 1;

            if ( !newurl || newurl === 'http://' || newurl === 'https://' ) { return; }
            if ( $('#add-button').hasClass('disabled') ) { return false; }

            add_loading( '#add-button' );

            $.getJSON(
                ajaxurl,
                { action: 'add', url: newurl, keyword: keyword, nonce: nonce, rowid: nextid, math_captcha_answer: captcha_answer },
                function( data ) {
                    if ( data.status === 'success' ) {
                        $('#main_table tbody').prepend( data.html ).trigger( 'update' );
                        $('#nourl_found').css( 'display', 'none' );
                        zebra_table();
                        increment_counter();
                        toggle_share_fill_boxes( data.url.url, data.shorturl, data.url.title );
                    }
                    add_link_reset();
                    end_loading( '#add-button' );
                    end_disable( '#add-button' );
                    feedback( data.message, data.status );

                    if ( data.code === 'error:captcha_wrong' || data.code === 'error:captcha_missing' ) {
                        setTimeout( function() { location.reload(); }, 2000 );
                    }
                }
            );
        };
    });
    </script>
    <?php
}
yourls_add_action( 'admin_page_before_table', 'math_captcha_add_js' );
