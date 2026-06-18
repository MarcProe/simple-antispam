<?php
/*
Plugin Name: Math CAPTCHA
Plugin URI: https://github.com/MarcProe/simple-antispam
Description: Adds a simple math question CAPTCHA to prevent automated URL submissions. Users must solve a simple addition problem (numbers below 100) to shorten a URL.
Version: 1.0
Author: MarcProe
Author URI: https://github.com/MarcProe
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

// Start session - YOURLS doesn't start sessions by default, so we must do it
// We need to start the session early, before any output
if ( session_status() === PHP_SESSION_NONE ) {
    session_start();
}

// Generate a new math question and store it in session
function math_captcha_generate_question() {
    $num1 = rand(1, 99);
    $num2 = rand(1, 99);
    $question = "$num1 + $num2";
    $answer = $num1 + $num2;
    
    $_SESSION['math_captcha_question'] = $question;
    $_SESSION['math_captcha_answer'] = $answer;
    
    return $question;
}

// Get the current math question from session
function math_captcha_get_question() {
    if (isset($_SESSION['math_captcha_question'])) {
        return $_SESSION['math_captcha_question'];
    }
    return math_captcha_generate_question();
}

// Verify the user's answer
function math_captcha_verify($user_answer) {
    if (!isset($_SESSION['math_captcha_answer'])) {
        return false;
    }
    
    $correct_answer = $_SESSION['math_captcha_answer'];
    // Sanitize the user's answer - only allow digits
    $user_answer = yourls_sanitize_int( $user_answer );
    
    // Clear the question after verification
    unset($_SESSION['math_captcha_question']);
    unset($_SESSION['math_captcha_answer']);
    
    return ($user_answer === $correct_answer);
}

// Add math question field to the add new URL form
function math_captcha_add_field_to_form() {
    $question = math_captcha_get_question();
    echo '<div id="math-captcha-field">';
    echo '<label for="math-captcha-answer"><strong>' . yourls_esc_html( yourls__( 'Math CAPTCHA' ) ) . '</strong></label>:';
    echo '<span id="math-captcha-question"> ' . yourls_esc_html($question) . ' = </span>';
    echo '<input type="text" id="math-captcha-answer" name="math_captcha_answer" class="text" size="10" placeholder="' . yourls_esc_attr( yourls__( 'Answer' ) ) . '" />';
    echo '</div>';
}

// Add the field to the HTML form
yourls_add_action( 'html_addnew', 'math_captcha_add_field_to_form' );

// Intercept URL creation and verify CAPTCHA
// We use the shunt_add_new_link filter to short-circuit the add_new_link function
yourls_add_filter( 'shunt_add_new_link', 'math_captcha_verify_on_add', 10, 4 );

function math_captcha_verify_on_add($shunt, $url, $keyword, $title) {
    // Check if this is a bookmarklet request (no CAPTCHA field in form)
    // Bookmarklets are identified by the presence of 'u' or 'up' in GET parameters
    $is_bookmarklet = isset($_GET['u']) || isset($_GET['up']);
    
    // For bookmarklets, we might want to skip CAPTCHA or handle differently
    // For now, we'll skip CAPTCHA for bookmarklets as they don't have a form to display the question
    // This can be changed if needed by adding CAPTCHA support to bookmarklets
    if ($is_bookmarklet) {
        // Skip CAPTCHA for bookmarklets
        return $shunt;
    }
    
    // Get the user's answer from POST or GET
    $user_answer = '';
    if (isset($_POST['math_captcha_answer'])) {
        $user_answer = $_POST['math_captcha_answer'];
    } elseif (isset($_GET['math_captcha_answer'])) {
        $user_answer = $_GET['math_captcha_answer'];
    }
    
    // If no answer provided, return error
    if (empty($user_answer)) {
        $return = array(
            'status' => 'fail',
            'code' => 'error:captcha_missing',
            'message' => yourls__( 'Please solve the math CAPTCHA to shorten URLs.' ),
            'errorCode' => '400',
            'statusCode' => '400',
        );
        return $return;
    }
    
    // Verify the answer
    if (!math_captcha_verify($user_answer)) {
        // Generate a new question for the next attempt
        math_captcha_generate_question();
        
        $return = array(
            'status' => 'fail',
            'code' => 'error:captcha_wrong',
            'message' => yourls__( 'Incorrect answer to the math question. Please try again.' ),
            'errorCode' => '400',
            'statusCode' => '400',
        );
        return $return;
    }
    
    // Answer is correct, allow the URL to be added
    return $shunt;
}

// Add CSS for the CAPTCHA field
function math_captcha_add_css() {
    echo '<style type="text/css">';
    echo '#math-captcha-field { margin-top: 10px; padding: 10px; background: #fff8e1; border: 1px solid #ffc107; border-radius: 4px; }';
    echo '#math-captcha-question { font-weight: bold; color: #5d4037; }';
    echo '#math-captcha-answer { width: 80px; margin-left: 10px; }';
    echo '</style>';
}

yourls_add_action( 'admin_page_before_form', 'math_captcha_add_css' );

// Add inline JavaScript for the CAPTCHA - we'll hook into the form submission
function math_captcha_add_js() {
    echo '<script type="text/javascript">';
    echo 'jQuery(document).ready(function($) {';
    echo '    // Modify the AJAX request to include CAPTCHA answer';
    echo '    var original_ajax_url = ajaxurl;';
    echo '    ';
    echo '    // Override the add_link function to include CAPTCHA answer';
    echo '    if (typeof add_link === "function") {';
    echo '        var original_add_link = add_link;';
    echo '        add_link = function() {';
    echo '            var captcha_answer = $("#math-captcha-answer").val();';
    echo '            ';
    echo '            // If CAPTCHA field exists and is empty, show error';
    echo '            if ($("#math-captcha-answer").length > 0 && !captcha_answer) {';
    echo '                feedback("Please solve the math CAPTCHA to shorten URLs.", "fail");';
    echo '                return false;';
    echo '            }';
    echo '            ';
    echo '            // Get the original parameters';
    echo '            var newurl = $("#add-url").val();';
    echo '            var nonce = $("#nonce-add").val();';
    echo '            var keyword = $("#add-keyword").val();';
    echo '            var nextid = parseInt($("#main_table tbody tr[id^=\"id-\"]").length) + 1;';
    echo '            ';
    echo '            if ( !newurl || newurl == "http://" || newurl == "https://" ) {';
    echo '                return;';
    echo '            }';
    echo '            ';
    echo '            // Disable button and show loading';
    echo '            if( $("#add-button").hasClass("disabled") ) {';
    echo '                return false;';
    echo '            }';
    echo '            add_loading("#add-button");';
    echo '            ';
    echo '            // Make AJAX request with CAPTCHA answer';
    echo '            $.getJSON(';
    echo '                ajaxurl,';
    echo '                {action:"add", url: newurl, keyword: keyword, nonce: nonce, rowid: nextid, math_captcha_answer: captcha_answer},';
    echo '                function(data){';
    echo '                    if(data.status == "success") {';
    echo '                        $("#main_table tbody").prepend( data.html ).trigger("update");';
    echo '                        $("#nourl_found").css("display", "none");';
    echo '                        zebra_table();';
    echo '                        increment_counter();';
    echo '                        toggle_share_fill_boxes( data.url.url, data.shorturl, data.url.title );';
    echo '                    }';
    echo '                    ';
    echo '                    add_link_reset();';
    echo '                    end_loading("#add-button");';
    echo '                    end_disable("#add-button");';
    echo '                    ';
    echo '                    feedback(data.message, data.status);';
    echo '                    ';
    echo '                    // If CAPTCHA was wrong, reload to get new question';
    echo '                    if (data.code === "error:captcha_wrong" || data.code === "error:captcha_missing") {';
    echo '                        setTimeout(function() {';
    echo '                            location.reload();';
    echo '                        }, 2000);';
    echo '                    }';
    echo '                }';
    echo '            );';
    echo '        };';
    echo '    }';
    echo '});';
    echo '</script>';
}

yourls_add_action( 'admin_page_before_form', 'math_captcha_add_js' );
