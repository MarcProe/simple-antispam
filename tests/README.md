# Test Suite

This directory holds the shared test harness for the repository's plugins: the
`bootstrap.php` that mocks the YOURLS API and auto-loads every plugin under
`plugins/`, plus the `integration/` suite that exercises a real YOURLS install.

Each plugin's own PHPUnit tests live alongside its code, e.g. the Math CAPTCHA
tests are in `plugins/math-captcha/tests/MathCaptchaTest.php`.

## Requirements

- PHP 7.3 or higher (required by PHPUnit 9)
- PHPUnit 9.0 or higher
- Composer (for dependency management)

## Installation

```bash
# Install PHPUnit via Composer
composer install

# Or install PHPUnit globally
pear install phpunit/PHPUnit
```

## Running Tests

### Using Composer
```bash
composer test
```

### Using PHPUnit Directly
```bash
phpunit
```

### With Verbose Output
```bash
phpunit --verbose
```

### With Code Coverage
```bash
phpunit --coverage-text
```

### With HTML Coverage Report
```bash
phpunit --coverage-html coverage
```

## Test Cases

The test suite covers the following functionality:

### Core Functionality Tests
- ✅ Plugin header validation
- ✅ Direct file access protection
- ✅ Session management

### Question Generation Tests
- ✅ Question format validation (e.g., "25 + 37")
- ✅ Answer correctness verification
- ✅ Number range validation (1-99)
- ✅ Multiple unique question generation
- ✅ Answer range validation (2-198)

### Verification Tests
- ✅ Correct answer acceptance
- ✅ Wrong answer rejection
- ✅ Missing session handling
- ✅ Non-numeric input handling
- ✅ SQL injection attempt handling
- ✅ Session cleanup after verification

### Output Tests
- ✅ Form field HTML output
- ✅ CSS styling output
- ✅ JavaScript output

### Integration Tests
- ✅ Hook registration verification
- ✅ Filter registration verification
- ✅ Bookmarklet bypass (GET 'u' parameter)
- ✅ Bookmarklet bypass (GET 'up' parameter)
- ✅ Missing answer error handling
- ✅ Wrong answer error handling
- ✅ Correct answer processing

## Test Structure

```
tests/
├── bootstrap.php       # Shared bootstrap - mocks YOURLS + auto-loads every plugin
├── README.md           # This file
└── integration/        # Integration tests against a real YOURLS install,
                        # incl. Playwright browser screenshots (see its README)

plugins/
└── math-captcha/
    └── tests/
        └── MathCaptchaTest.php  # Unit tests for the Math CAPTCHA plugin
```

## Writing New Tests

To add new tests:

1. Add a new test method to `MathCaptchaTest.php`
2. Follow the existing naming convention: `testMethodName()`
3. Use PHPUnit assertions: `$this->assert*()`
4. Set up test fixtures in `setUp()` method

Example:
```php
public function testNewFeature()
{
    // Arrange
    $_SESSION['math_captcha_answer'] = 42;
    
    // Act
    $result = some_function();
    
    // Assert
    $this->assertTrue($result);
}
```

## Continuous Integration

For CI integration, add the following to your workflow:

```yaml
- name: Run PHPUnit Tests
  run: composer test
```

## Code Coverage

To generate code coverage reports:

```bash
# Install Xdebug
pecl install xdebug

# Generate coverage report
phpunit --coverage-html coverage

# View report
open coverage/index.html
```

## Troubleshooting

### "Class PHPUnit\Framework\TestCase not found"
Run: `composer install`

### Session-related test failures
Ensure PHP sessions are enabled and the session save path is writable.

### Permission errors
Ensure the tests directory and its contents are readable by the web server user.
