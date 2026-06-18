<?php
/**
 * Math CAPTCHA Plugin Test Suite
 * 
 * @covers MathCaptcha
 */

class MathCaptchaTest extends PHPUnit\Framework\TestCase
{
    /**
     * Set up test fixtures
     */
    protected function setUp(): void
    {
        // Clear session before each test
        $_SESSION = array();
        
        // Reset global hooks
        global $yourls_actions, $yourls_filters;
        $yourls_actions = array();
        $yourls_filters = array();
        
        // Reload the plugin to register hooks
        require_once __DIR__ . '/../plugin.php';
    }

    /**
     * Test that plugin header is valid
     */
    public function testPluginHeader()
    {
        $plugin_file = file_get_contents(__DIR__ . '/../plugin.php');
        
        $this->assertStringContainsString('Plugin Name: Math CAPTCHA', $plugin_file);
        $this->assertStringContainsString('Version: 1.1', $plugin_file);
        $this->assertStringContainsString('Description:', $plugin_file);
        $this->assertStringContainsString('Author: MarcProe', $plugin_file);
    }

    /**
     * Test that direct file access is blocked
     */
    public function testDirectAccessBlocked()
    {
        // This is tested by the die() statement in the plugin
        // We can't easily test this without complex output buffering
        // But we can verify the check exists
        $plugin_file = file_get_contents(__DIR__ . '/../plugin.php');
        $this->assertStringContainsString("if ( !defined( 'YOURLS_ABSPATH' ) ) die();", $plugin_file);
    }

    /**
     * Test question generation
     */
    public function testGenerateQuestion()
    {
        // Call the function
        math_captcha_generate_question();
        
        // Verify session variables are set
        $this->assertArrayHasKey('math_captcha_question', $_SESSION);
        $this->assertArrayHasKey('math_captcha_answer', $_SESSION);
        
        // Verify question format
        $question = $_SESSION['math_captcha_question'];
        $this->assertMatchesRegularExpression('/^\d+ \+ \d+$/', $question);
        
        // Verify answer is correct
        $parts = explode(' + ', $question);
        $expected_answer = (int)$parts[0] + (int)$parts[1];
        $this->assertEquals($expected_answer, $_SESSION['math_captcha_answer']);
        
        // Verify numbers are in range 1-99
        $this->assertGreaterThanOrEqual(1, (int)$parts[0]);
        $this->assertLessThanOrEqual(99, (int)$parts[0]);
        $this->assertGreaterThanOrEqual(1, (int)$parts[1]);
        $this->assertLessThanOrEqual(99, (int)$parts[1]);
    }

    /**
     * Test getting existing question
     */
    public function testGetExistingQuestion()
    {
        // Set up a known question
        $_SESSION['math_captcha_question'] = '10 + 20';
        $_SESSION['math_captcha_answer'] = 30;
        
        $question = math_captcha_get_question();
        
        $this->assertEquals('10 + 20', $question);
    }

    /**
     * Test getting question when none exists
     */
    public function testGetNewQuestion()
    {
        // Session is empty
        $question = math_captcha_get_question();
        
        // Should have generated a new question
        $this->assertArrayHasKey('math_captcha_question', $_SESSION);
        $this->assertEquals($question, $_SESSION['math_captcha_question']);
    }

    /**
     * Test answer verification with correct answer
     */
    public function testVerifyCorrectAnswer()
    {
        // Set up a known question
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        
        $result = math_captcha_verify('12');
        
        $this->assertTrue($result);
        
        // Session should be cleared
        $this->assertArrayNotHasKey('math_captcha_question', $_SESSION);
        $this->assertArrayNotHasKey('math_captcha_answer', $_SESSION);
    }

    /**
     * Test answer verification with wrong answer
     */
    public function testVerifyWrongAnswer()
    {
        // Set up a known question
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        
        $result = math_captcha_verify('13');
        
        $this->assertFalse($result);
        
        // Session should be cleared
        $this->assertArrayNotHasKey('math_captcha_question', $_SESSION);
        $this->assertArrayNotHasKey('math_captcha_answer', $_SESSION);
    }

    /**
     * Test answer verification with no session
     */
    public function testVerifyNoSession()
    {
        $result = math_captcha_verify('12');
        
        $this->assertFalse($result);
    }

    /**
     * Test answer verification with non-numeric input
     */
    public function testVerifyNonNumericInput()
    {
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        
        $result = math_captcha_verify('abc');
        
        $this->assertFalse($result);
    }

    /**
     * Test answer verification with SQL injection attempt
     */
    public function testVerifyInjectionAttempt()
    {
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        
        $result = math_captcha_verify("' OR '1'='1");
        
        $this->assertFalse($result);
    }

    /**
     * Test form field output
     */
    public function testFormFieldOutput()
    {
        // Set up a known question
        $_SESSION['math_captcha_question'] = '10 + 20';
        $_SESSION['math_captcha_answer'] = 30;
        
        // Capture output
        ob_start();
        math_captcha_add_field_to_form();
        $output = ob_get_clean();
        
        // Verify output contains expected elements
        $this->assertStringContainsString('math-captcha-field', $output);
        $this->assertStringContainsString('Math CAPTCHA', $output);
        $this->assertStringContainsString('10 + 20 =', $output);
        $this->assertStringContainsString('math-captcha-answer', $output);
        $this->assertStringContainsString('Answer', $output);
    }

    /**
     * Test CSS output
     */
    public function testCssOutput()
    {
        ob_start();
        math_captcha_add_css();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('math-captcha-field', $output);
        $this->assertStringContainsString('background: #fff8e1', $output);
        $this->assertStringContainsString('border: 1px solid #ffc107', $output);
    }

    /**
     * Test JavaScript output
     */
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

    /**
     * Test hook registration
     */
    public function testHookRegistration()
    {
        global $yourls_actions, $yourls_filters;
        
        // Verify actions are registered
        $this->assertArrayHasKey('html_addnew', $yourls_actions);
        $this->assertContains('math_captcha_add_field_to_form', $yourls_actions['html_addnew']);
        
        $this->assertArrayHasKey('admin_page_before_form', $yourls_actions);
        $this->assertContains('math_captcha_add_css', $yourls_actions['admin_page_before_form']);
        
        $this->assertArrayHasKey('admin_page_before_table', $yourls_actions);
        $this->assertContains('math_captcha_add_js', $yourls_actions['admin_page_before_table']);
        
        // Verify filter is registered
        $this->assertArrayHasKey('shunt_add_new_link', $yourls_filters);
        $this->assertArrayHasKey(10, $yourls_filters['shunt_add_new_link']);
        $this->assertEquals('math_captcha_verify_on_add', $yourls_filters['shunt_add_new_link'][10]['function']);
    }

    /**
     * Test verification filter with missing answer
     */
    public function testVerificationFilterMissingAnswer()
    {
        // Set up a known question
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        
        // Simulate request without answer
        $_REQUEST = array();
        
        $result = math_captcha_verify_on_add('shunt', 'http://example.com', '', '');
        
        $this->assertEquals('fail', $result['status']);
        $this->assertEquals('error:captcha_missing', $result['code']);
        $this->assertEquals('Please solve the math CAPTCHA to shorten URLs.', $result['message']);
    }

    /**
     * Test verification filter with wrong answer
     */
    public function testVerificationFilterWrongAnswer()
    {
        // Set up a known question
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        
        // Simulate request with wrong answer
        $_REQUEST = array('math_captcha_answer' => '13');
        
        $result = math_captcha_verify_on_add('shunt', 'http://example.com', '', '');
        
        $this->assertEquals('fail', $result['status']);
        $this->assertEquals('error:captcha_wrong', $result['code']);
        $this->assertEquals('Incorrect answer. Please try again.', $result['message']);
    }

    /**
     * Test verification filter with correct answer
     */
    public function testVerificationFilterCorrectAnswer()
    {
        // Set up a known question
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        
        // Simulate request with correct answer
        $_REQUEST = array('math_captcha_answer' => '12');
        
        $result = math_captcha_verify_on_add('shunt', 'http://example.com', '', '');
        
        // Should return the shunt value (not modified)
        $this->assertEquals('shunt', $result);
    }

    /**
     * Test bookmarklet bypass
     */
    public function testBookmarkletBypass()
    {
        // Set up a known question
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        
        // Simulate bookmarklet request (has 'u' parameter)
        $_GET['u'] = 'http://example.com';
        $_REQUEST = array(); // No CAPTCHA answer
        
        $result = math_captcha_verify_on_add('shunt', 'http://example.com', '', '');
        
        // Should bypass CAPTCHA for bookmarklets
        $this->assertEquals('shunt', $result);
    }

    /**
     * Test bookmarklet bypass with 'up' parameter
     */
    public function testBookmarkletBypassUpParameter()
    {
        // Set up a known question
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        
        // Simulate new-style bookmarklet request
        $_GET['up'] = 'http://';
        $_GET['us'] = '//';
        $_GET['ur'] = 'example.com';
        $_REQUEST = array(); // No CAPTCHA answer
        
        $result = math_captcha_verify_on_add('shunt', 'http://example.com', '', '');
        
        // Should bypass CAPTCHA for bookmarklets
        $this->assertEquals('shunt', $result);
    }

    /**
     * Test multiple question generations
     */
    public function testMultipleQuestionGenerations()
    {
        $questions = array();
        
        for ($i = 0; $i < 10; $i++) {
            math_captcha_generate_question();
            $questions[] = $_SESSION['math_captcha_question'];
        }
        
        // All questions should be unique (very unlikely to have duplicates in 10 tries)
        $unique_questions = array_unique($questions);
        $this->assertCount(10, $unique_questions);
    }

    /**
     * Test that answer is always positive
     */
    public function testAnswerAlwaysPositive()
    {
        for ($i = 0; $i < 100; $i++) {
            math_captcha_generate_question();
            $answer = $_SESSION['math_captcha_answer'];
            
            $this->assertGreaterThanOrEqual(2, $answer); // Minimum: 1 + 1 = 2
            $this->assertLessThanOrEqual(198, $answer); // Maximum: 99 + 99 = 198
        }
    }

    /**
     * Test session cleanup after verification
     */
    public function testSessionCleanup()
    {
        math_captcha_generate_question();
        
        $this->assertArrayHasKey('math_captcha_question', $_SESSION);
        $this->assertArrayHasKey('math_captcha_answer', $_SESSION);
        
        math_captcha_verify('12'); // Will fail but should still cleanup
        
        $this->assertArrayNotHasKey('math_captcha_question', $_SESSION);
        $this->assertArrayNotHasKey('math_captcha_answer', $_SESSION);
    }
}
