<?php
/**
 * Math CAPTCHA Plugin Test Suite
 *
 * @covers MathCaptcha
 */

class MathCaptchaTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $_SESSION = array();
        $_GET     = array();
        $_REQUEST = array();

        global $yourls_actions, $yourls_filters;
        $yourls_actions = array();
        $yourls_filters = array();

        // require_once is a no-op after the first include, so re-register hooks directly
        yourls_add_action( 'html_addnew', 'math_captcha_add_field_to_form' );
        yourls_add_action( 'admin_page_before_form', 'math_captcha_add_css' );
        yourls_add_action( 'admin_page_before_table', 'math_captcha_add_js' );
        yourls_add_filter( 'shunt_add_new_link', 'math_captcha_verify_on_add', 10, 4 );
    }

    protected function tearDown(): void
    {
        $_GET     = array();
        $_REQUEST = array();
    }

    public function testPluginHeader()
    {
        $plugin_file = file_get_contents(__DIR__ . '/../plugin.php');

        $this->assertStringContainsString('Plugin Name: Math CAPTCHA', $plugin_file);
        $this->assertStringContainsString('Version: 1.1', $plugin_file);
        $this->assertStringContainsString('Description:', $plugin_file);
        $this->assertStringContainsString('Author: MarcProe', $plugin_file);
    }

    public function testDirectAccessBlocked()
    {
        $plugin_file = file_get_contents(__DIR__ . '/../plugin.php');
        $this->assertStringContainsString("if (!defined('YOURLS_ABSPATH')) {
    die();
}", $plugin_file);
    }

    public function testGenerateQuestion()
    {
        math_captcha_generate_question();

        $this->assertArrayHasKey('math_captcha_question', $_SESSION);
        $this->assertArrayHasKey('math_captcha_answer', $_SESSION);

        $question = $_SESSION['math_captcha_question'];
        $this->assertMatchesRegularExpression('/^\d+ \+ \d+$/', $question);

        $parts = explode(' + ', $question);
        $expected_answer = (int)$parts[0] + (int)$parts[1];
        $this->assertEquals($expected_answer, $_SESSION['math_captcha_answer']);

        $this->assertGreaterThanOrEqual(1, (int)$parts[0]);
        $this->assertLessThanOrEqual(99, (int)$parts[0]);
        $this->assertGreaterThanOrEqual(1, (int)$parts[1]);
        $this->assertLessThanOrEqual(99, (int)$parts[1]);
    }

    public function testGetExistingQuestion()
    {
        $_SESSION['math_captcha_question'] = '10 + 20';
        $_SESSION['math_captcha_answer'] = 30;

        $question = math_captcha_get_question();

        $this->assertEquals('10 + 20', $question);
    }

    public function testGetNewQuestion()
    {
        $question = math_captcha_get_question();

        $this->assertArrayHasKey('math_captcha_question', $_SESSION);
        $this->assertEquals($question, $_SESSION['math_captcha_question']);
    }

    public function testVerifyCorrectAnswer()
    {
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;

        $result = math_captcha_verify('12');

        $this->assertTrue($result);
        $this->assertArrayNotHasKey('math_captcha_question', $_SESSION);
        $this->assertArrayNotHasKey('math_captcha_answer', $_SESSION);
    }

    public function testVerifyWrongAnswer()
    {
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;

        $result = math_captcha_verify('13');

        $this->assertFalse($result);
        $this->assertArrayNotHasKey('math_captcha_question', $_SESSION);
        $this->assertArrayNotHasKey('math_captcha_answer', $_SESSION);
    }

    public function testVerifyNoSession()
    {
        $result = math_captcha_verify('12');
        $this->assertFalse($result);
    }

    public function testVerifyNonNumericInput()
    {
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;

        $result = math_captcha_verify('abc');
        $this->assertFalse($result);
    }

    public function testVerifyInjectionAttempt()
    {
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;

        $result = math_captcha_verify("' OR '1'='1");
        $this->assertFalse($result);
    }

    public function testFormFieldOutput()
    {
        $_SESSION['math_captcha_question'] = '10 + 20';
        $_SESSION['math_captcha_answer'] = 30;

        ob_start();
        math_captcha_add_field_to_form();
        $output = ob_get_clean();

        $this->assertStringContainsString('math-captcha-field', $output);
        $this->assertStringContainsString('Math CAPTCHA', $output);
        $this->assertStringContainsString('10 + 20', $output);
        $this->assertStringContainsString('math-captcha-answer', $output);
        $this->assertStringContainsString('Answer', $output);
    }

    public function testFormFieldAccessibilityAttributes()
    {
        $_SESSION['math_captcha_question'] = '10 + 20';
        $_SESSION['math_captcha_answer'] = 30;

        ob_start();
        math_captcha_add_field_to_form();
        $output = ob_get_clean();

        // The input must be programmatically tied to the question so screen
        // readers announce the problem to solve.
        $this->assertStringContainsString('aria-describedby="math-captcha-question"', $output);
        $this->assertStringContainsString('id="math-captcha-question"', $output);
        // A numeric input mode brings up the number keypad on mobile.
        $this->assertStringContainsString('inputmode="numeric"', $output);
        // A CAPTCHA answer should never be autofilled.
        $this->assertStringContainsString('autocomplete="off"', $output);
    }

    public function testCssOutput()
    {
        ob_start();
        math_captcha_add_css();
        $output = ob_get_clean();

        $this->assertStringContainsString('math-captcha-field', $output);
        $this->assertStringContainsString('background: #fff8e1', $output);
        $this->assertStringContainsString('border: 1px solid #ffc107', $output);
    }

    public function testJsOutput()
    {
        ob_start();
        math_captcha_add_js();
        $output = ob_get_clean();

        $this->assertStringContainsString('add_link', $output);
        $this->assertStringContainsString('math-captcha-answer', $output);
        $this->assertStringContainsString('$.getJSON', $output);
        $this->assertStringContainsString('math_captcha_answer', $output);
    }

    public function testHookRegistration()
    {
        global $yourls_actions, $yourls_filters;

        $this->assertArrayHasKey('html_addnew', $yourls_actions);
        $this->assertContains('math_captcha_add_field_to_form', $yourls_actions['html_addnew']);

        $this->assertArrayHasKey('admin_page_before_form', $yourls_actions);
        $this->assertContains('math_captcha_add_css', $yourls_actions['admin_page_before_form']);

        $this->assertArrayHasKey('admin_page_before_table', $yourls_actions);
        $this->assertContains('math_captcha_add_js', $yourls_actions['admin_page_before_table']);

        $this->assertArrayHasKey('shunt_add_new_link', $yourls_filters);
        $this->assertArrayHasKey(10, $yourls_filters['shunt_add_new_link']);
        $this->assertEquals('math_captcha_verify_on_add', $yourls_filters['shunt_add_new_link'][10]['function']);
    }

    public function testVerificationFilterMissingAnswer()
    {
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        $_REQUEST = array();

        $result = math_captcha_verify_on_add('shunt', 'http://example.com', '', '');

        $this->assertEquals('fail', $result['status']);
        $this->assertEquals('error:captcha_missing', $result['code']);
        $this->assertEquals('Please solve the math CAPTCHA to shorten URLs.', $result['message']);
    }

    public function testVerificationFilterWrongAnswer()
    {
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        $_REQUEST = array('math_captcha_answer' => '13');

        $result = math_captcha_verify_on_add('shunt', 'http://example.com', '', '');

        $this->assertEquals('fail', $result['status']);
        $this->assertEquals('error:captcha_wrong', $result['code']);
        $this->assertEquals('Incorrect answer. Please try again.', $result['message']);
    }

    public function testVerificationFilterCorrectAnswer()
    {
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        $_REQUEST = array('math_captcha_answer' => '12');

        $result = math_captcha_verify_on_add('shunt', 'http://example.com', '', '');

        $this->assertEquals('shunt', $result);
    }

    public function testBookmarkletBypass()
    {
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        $_GET['u'] = 'http://example.com';
        $_REQUEST = array();

        $result = math_captcha_verify_on_add('shunt', 'http://example.com', '', '');

        $this->assertEquals('shunt', $result);
    }

    public function testBookmarkletBypassUpParameter()
    {
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        $_GET['up'] = 'http://';
        $_REQUEST = array();

        $result = math_captcha_verify_on_add('shunt', 'http://example.com', '', '');

        $this->assertEquals('shunt', $result);
    }

    public function testQuestionGenerationFormat()
    {
        for ($i = 0; $i < 20; $i++) {
            math_captcha_generate_question();
            $question = $_SESSION['math_captcha_question'];
            $answer   = $_SESSION['math_captcha_answer'];

            $this->assertMatchesRegularExpression('/^\d+ \+ \d+$/', $question);

            $parts = explode(' + ', $question);
            $this->assertGreaterThanOrEqual(1, (int)$parts[0]);
            $this->assertLessThanOrEqual(99, (int)$parts[0]);
            $this->assertGreaterThanOrEqual(1, (int)$parts[1]);
            $this->assertLessThanOrEqual(99, (int)$parts[1]);
            $this->assertEquals((int)$parts[0] + (int)$parts[1], $answer);
        }
    }

    public function testAnswerAlwaysPositive()
    {
        for ($i = 0; $i < 100; $i++) {
            math_captcha_generate_question();
            $answer = $_SESSION['math_captcha_answer'];

            $this->assertGreaterThanOrEqual(2, $answer);   // min: 1 + 1
            $this->assertLessThanOrEqual(198, $answer);    // max: 99 + 99
        }
    }

    public function testSessionCleanup()
    {
        math_captcha_generate_question();

        $this->assertArrayHasKey('math_captcha_question', $_SESSION);
        $this->assertArrayHasKey('math_captcha_answer', $_SESSION);

        math_captcha_verify('12');

        $this->assertArrayNotHasKey('math_captcha_question', $_SESSION);
        $this->assertArrayNotHasKey('math_captcha_answer', $_SESSION);
    }
}
