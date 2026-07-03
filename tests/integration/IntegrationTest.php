<?php
/**
 * Integration test for Math CAPTCHA plugin with YOURLS
 * 
 * This test can be run standalone to verify the plugin works with YOURLS
 * without requiring a full web server setup.
 */

require_once __DIR__ . '/../../stubs/yourls.php';
require_once __DIR__ . '/../../plugin.php';

/**
 * Mock YOURLS functions for testing
 */
class IntegrationTest
{
    private static $hooks = array(
        'actions' => array(),
        'filters' => array()
    );
    
    private static $options = array();
    private static $urlData = array();
    
    public static function setup(): void
    {
        // Reset state
        self::$hooks = array('actions' => array(), 'filters' => array());
        self::$options = array();
        self::$urlData = array();
        
        $_SESSION = array();
        $_GET = array();
        $_POST = array();
        $_REQUEST = array();
        $_COOKIE = array();
        
        // Mock YOURLS constants
        if (!defined('YOURLS_ABSPATH')) {
            define('YOURLS_ABSPATH', __DIR__ . '/../../');
        }
        if (!defined('YOURLS_VERSION')) {
            define('YOURLS_VERSION', '1.9.2');
        }
    }
    
    public static function yourls_add_action(string $hook, $function): void
    {
        if (!isset(self::$hooks['actions'][$hook])) {
            self::$hooks['actions'][$hook] = array();
        }
        self::$hooks['actions'][$hook][] = $function;
    }
    
    public static function yourls_add_filter(string $hook, $function, int $priority = 10, int $accepted_args = 1): void
    {
        if (!isset(self::$hooks['filters'][$hook])) {
            self::$hooks['filters'][$hook] = array();
        }
        self::$hooks['filters'][$hook][$priority] = array(
            'function' => $function,
            'args' => $accepted_args
        );
    }
    
    public static function yourls__($text, $domain = 'default'): string
    {
        return $text;
    }
    
    public static function yourls_esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    public static function yourls_esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    public static function yourls_get_option(string $name, $default = null)
    {
        return self::$options[$name] ?? $default;
    }
    
    public static function yourls_update_option(string $name, $value): bool
    {
        self::$options[$name] = $value;
        return true;
    }
    
    public static function addUrl(string $url, string $keyword = '', string $title = ''): array
    {
        // Simulate adding a URL to YOURLS
        $short = 'test' . count(self::$urlData);
        self::$urlData[$short] = array(
            'url' => $url,
            'keyword' => $keyword ?: $short,
            'title' => $title
        );
        
        return array(
            'status' => 'success',
            'shorturl' => $short,
            'url' => $url,
            'keyword' => $keyword ?: $short,
            'title' => $title
        );
    }
    
    public static function run(): int
    {
        self::setup();
        
        $tests = array(
            'testPluginRegistration' => 'Plugin registration',
            'testCaptchaGeneration' => 'CAPTCHA generation',
            'testCaptchaVerification' => 'CAPTCHA verification',
            'testFormIntegration' => 'Form integration',
            'testFilterHook' => 'Filter hook (shunt_add_new_link)',
            'testBookmarkletBypass' => 'Bookmarklet bypass',
        );
        
        $passed = 0;
        $failed = 0;
        
        echo "\n=== Math CAPTCHA Integration Tests ===\n\n";
        
        foreach ($tests as $method => $description) {
            try {
                echo "Running: $description... ";
                self::$method();
                echo "✓ PASS\n";
                $passed++;
            } catch (Exception $e) {
                echo "✗ FAIL\n";
                echo "  Error: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
        
        echo "\n=== Results ===\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "Total: " . ($passed + $failed) . "\n\n";
        
        return $failed > 0 ? 1 : 0;
    }
    
    private static function testPluginRegistration(): void
    {
        // The plugin should register its hooks
        // We need to include the plugin file which registers hooks
        // But we already included it at the top
        
        // Check that hooks were registered
        // Note: The plugin file registers hooks when included
        // We need to check the global hooks array
        global $yourls_actions, $yourls_filters;
        
        // These should be set by the plugin
        if (!isset($yourls_actions['html_addnew'])) {
            throw new Exception('html_addnew action not registered');
        }
        
        if (!in_array('math_captcha_add_field_to_form', $yourls_actions['html_addnew'])) {
            throw new Exception('math_captcha_add_field_to_form not in html_addnew');
        }
        
        if (!isset($yourls_filters['shunt_add_new_link'])) {
            throw new Exception('shunt_add_new_link filter not registered');
        }
    }
    
    private static function testCaptchaGeneration(): void
    {
        math_captcha_generate_question();
        
        if (!isset($_SESSION['math_captcha_question'])) {
            throw new Exception('Question not generated');
        }
        
        if (!isset($_SESSION['math_captcha_answer'])) {
            throw new Exception('Answer not generated');
        }
        
        $question = $_SESSION['math_captcha_question'];
        $answer = $_SESSION['math_captcha_answer'];
        
        if (!preg_match('/^[0-9]+ \+ [0-9]+$/', $question)) {
            throw new Exception("Invalid question format: $question");
        }
        
        $parts = explode(' + ', $question);
        $expected = (int)$parts[0] + (int)$parts[1];
        
        if ($expected !== $answer) {
            throw new Exception("Answer mismatch: expected $expected, got $answer");
        }
    }
    
    private static function testCaptchaVerification(): void
    {
        // Set up a known question
        $_SESSION['math_captcha_question'] = '10 + 20';
        $_SESSION['math_captcha_answer'] = 30;
        
        // Test correct answer
        $result = math_captcha_verify('30');
        if (!$result) {
            throw new Exception('Correct answer not verified');
        }
        
        // Session should be cleared
        if (isset($_SESSION['math_captcha_question'])) {
            throw new Exception('Question not cleared after verification');
        }
        
        // Set up again for wrong answer test
        $_SESSION['math_captcha_question'] = '5 + 7';
        $_SESSION['math_captcha_answer'] = 12;
        
        // Test wrong answer
        $result = math_captcha_verify('13');
        if ($result) {
            throw new Exception('Wrong answer incorrectly verified');
        }
        
        // Session should still be cleared
        if (isset($_SESSION['math_captcha_question'])) {
            throw new Exception('Question not cleared after wrong verification');
        }
    }
    
    private static function testFormIntegration(): void
    {
        // Generate a question
        math_captcha_generate_question();
        
        // Capture output
        ob_start();
        math_captcha_add_field_to_form();
        $output = ob_get_clean();
        
        if (!strpos($output, 'math-captcha-field')) {
            throw new Exception('CAPTCHA field not in output');
        }
        
        if (!strpos($output, 'Math CAPTCHA')) {
            throw new Exception('CAPTCHA label not in output');
        }
        
        if (!strpos($output, 'math-captcha-answer')) {
            throw new Exception('CAPTCHA input not in output');
        }
        
        if (!strpos($output, $_SESSION['math_captcha_question'])) {
            throw new Exception('CAPTCHA question not in output');
        }
    }
    
    private static function testFilterHook(): void
    {
        // Set up a CAPTCHA
        $_SESSION['math_captcha_question'] = '8 + 9';
        $_SESSION['math_captcha_answer'] = 17;
        
        // Test with correct answer
        $_REQUEST['math_captcha_answer'] = '17';
        
        $result = math_captcha_verify_on_add('shunt', 'http://example.com', 'test', 'Test');
        
        if ($result !== 'shunt') {
            throw new Exception('Correct CAPTCHA should return shunt');
        }
        
        // Test with wrong answer
        $_SESSION['math_captcha_question'] = '1 + 1';
        $_SESSION['math_captcha_answer'] = 2;
        $_REQUEST['math_captcha_answer'] = '3';
        
        $result = math_captcha_verify_on_add('shunt', 'http://example.com', 'test', 'Test');
        
        if (!isset($result['status']) || $result['status'] !== 'fail') {
            throw new Exception('Wrong CAPTCHA should return fail status');
        }
        
        if (!isset($result['code']) || $result['code'] !== 'error:captcha_wrong') {
            throw new Exception('Wrong CAPTCHA should return error:captcha_wrong');
        }
        
        // Test with missing answer
        $_SESSION['math_captcha_question'] = '2 + 2';
        $_SESSION['math_captcha_answer'] = 4;
        unset($_REQUEST['math_captcha_answer']);
        
        $result = math_captcha_verify_on_add('shunt', 'http://example.com', 'test', 'Test');
        
        if (!isset($result['status']) || $result['status'] !== 'fail') {
            throw new Exception('Missing CAPTCHA should return fail status');
        }
        
        if (!isset($result['code']) || $result['code'] !== 'error:captcha_missing') {
            throw new Exception('Missing CAPTCHA should return error:captcha_missing');
        }
    }
    
    private static function testBookmarkletBypass(): void
    {
        // Set up a CAPTCHA
        $_SESSION['math_captcha_question'] = '5 + 5';
        $_SESSION['math_captcha_answer'] = 10;
        
        // Test with bookmarklet parameter 'u'
        $_GET['u'] = 'http://example.com';
        unset($_REQUEST['math_captcha_answer']);
        
        $result = math_captcha_verify_on_add('shunt', 'http://example.com', 'test', 'Test');
        
        if ($result !== 'shunt') {
            throw new Exception('Bookmarklet with u parameter should bypass CAPTCHA');
        }
        
        // Test with bookmarklet parameter 'up'
        unset($_GET['u']);
        $_GET['up'] = 'http://';
        
        $result = math_captcha_verify_on_add('shunt', 'http://example.com', 'test', 'Test');
        
        if ($result !== 'shunt') {
            throw new Exception('Bookmarklet with up parameter should bypass CAPTCHA');
        }
    }
}

// Run the tests if this file is executed directly
if (php_sapi_name() === 'cli') {
    exit(IntegrationTest::run());
}
