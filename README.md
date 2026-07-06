# Math CAPTCHA Plugin for YOURLS

A simple anti-spam plugin for YOURLS that requires users to solve a basic math addition problem before they can shorten a URL. This helps prevent automated bot submissions while keeping the user experience simple and accessible.

## Features

- **Simple Math Question**: Generates random addition problems with numbers between 1 and 99
- **Session-Based**: Each user gets a unique question that persists until solved
- **AJAX Support**: Works seamlessly with YOURLS' AJAX form submissions
- **Error Handling**: Provides clear feedback when the answer is incorrect or missing
- **Styling**: Includes CSS styling to make the CAPTCHA field visible and user-friendly
- **Accessible**: The answer field is programmatically tied to the question
  (`aria-describedby`) and requests a numeric keypad on mobile (`inputmode`)
- **Clean Code**: Well-structured, readable, and maintainable

## Screenshots

The screenshots below are captured automatically by the integration test suite
(see [Testing](#testing)) against a real YOURLS 1.9.2 installation.

The CAPTCHA field on the URL shortening form:

![Admin page with the Math CAPTCHA field](docs/screenshots/02-admin-page-with-captcha.png)

Close-up of the CAPTCHA field:

![Math CAPTCHA field close-up](docs/screenshots/03-captcha-field-closeup.png)

A wrong answer is rejected with an error message:

![Wrong answer rejected](docs/screenshots/04-wrong-answer-rejected.png)

With the correct answer, the URL is shortened as usual:

![Correct answer accepted, URL shortened](docs/screenshots/05-correct-answer-shortened.png)

Leaving the answer empty is blocked client-side before any request is sent:

![Missing answer rejected client-side](docs/screenshots/06-missing-answer-rejected.png)

The field also renders sensibly on a mobile viewport:

![CAPTCHA field on a mobile viewport](docs/screenshots/07-mobile-form.png)

## Installation

1. Download the plugin or clone this repository
2. Copy the `math-captcha` folder to your YOURLS `user/plugins/` directory
3. Go to your YOURLS admin panel
4. Navigate to **Plugins** page
5. Activate the **Math CAPTCHA** plugin

## Usage

Once activated, the plugin automatically adds a math question field to the URL shortening form. Users must:

1. Enter the URL they want to shorten
2. Solve the math question (e.g., "25 + 37 = ")
3. Enter the answer in the provided field
4. Click "Shorten The URL"

If the answer is incorrect or missing, the plugin will display an error message and generate a new question.

**Note on Bookmarklets**: By default, CAPTCHA is skipped for bookmarklet requests since they don't display a form. If you want to enable CAPTCHA for bookmarklets, you would need to modify the plugin to handle bookmarklet-specific logic.

## Requirements

- YOURLS 1.7 or higher
- PHP 5.4 or higher (uses `session_status()`)
- JavaScript enabled in the browser (for AJAX form submissions)
- PHP sessions must be enabled on your server

## Customization

You can modify the plugin behavior by editing the `plugin.php` file:

- **Change number range**: Edit the `math_captcha_generate_question()` function to change the range of numbers (currently 1-99)
- **Change styling**: Modify the CSS in the `math_captcha_add_css()` function
- **Change error messages**: Update the messages in the `math_captcha_verify_on_add()` function

## Testing

The plugin ships with a full test suite:

- **Unit tests** (`tests/MathCaptchaTest.php`): PHPUnit tests against a mocked
  YOURLS API — `composer install && composer test`
- **Integration tests** (`tests/integration/`): install a real YOURLS with the
  plugin activated and exercise the CAPTCHA through the admin interface
- **Browser screenshots** (`tests/integration/screenshots.mjs`): a Playwright
  script drives Chromium through the login, wrong-answer, and correct-answer
  flows, verifying the plugin's JavaScript hook and capturing the screenshots
  shown above

Everything runs automatically in GitHub Actions on every push and pull
request; the screenshots are uploaded as a workflow artifact. See
[INTEGRATION_TESTING.md](INTEGRATION_TESTING.md) for the full documentation.

## Files

- `plugin.php` - Main plugin file with all the PHP logic, embedded JavaScript, and CSS
- `README.md` - This documentation file
- `docs/screenshots/` - Screenshots captured by the integration test suite
- `tests/` - Unit and integration tests (see [INTEGRATION_TESTING.md](INTEGRATION_TESTING.md))

## How It Works

1. **Question Generation**: When the form is displayed, the plugin generates a random addition problem and stores both the question and answer in the user's session.

2. **Form Integration**: The plugin adds a new field to the URL shortening form displaying the math question and an input for the answer.

3. **Verification**: When the form is submitted, the plugin intercepts the request via the `shunt_add_new_link` filter and verifies the user's answer against the stored answer in the session.

4. **Result Handling**: 
   - If correct: The URL is shortened normally
   - If incorrect or missing: An error is returned and a new question is generated

## Security Notes

- The plugin uses PHP sessions to store the question and answer, which are server-side and not visible to the client
- The answer is only valid for the current session, preventing replay attacks
- A new question is generated after each attempt (successful or not)
- The plugin sanitizes all output to prevent XSS attacks

## License

This plugin is released under the [MIT License](LICENSE), the same as YOURLS itself. Feel free to use, modify, and distribute as needed.

## Support

For issues, questions, or suggestions, please open an issue on the [GitHub repository](https://github.com/MarcProe/simple-antispam).
