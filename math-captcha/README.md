# Math CAPTCHA Plugin for YOURLS

A simple anti-spam plugin for YOURLS that requires users to solve a basic math addition problem before they can shorten a URL. This helps prevent automated bot submissions while keeping the user experience simple and accessible.

## Features

- **Simple Math Question**: Generates random addition problems with numbers between 1 and 99
- **Session-Based**: Each user gets a unique question that persists until solved
- **AJAX Support**: Works seamlessly with YOURLS' AJAX form submissions
- **Error Handling**: Provides clear feedback when the answer is incorrect or missing
- **Styling**: Includes CSS styling to make the CAPTCHA field visible and user-friendly
- **Clean Code**: Well-structured, readable, and maintainable

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

## Files

- `plugin.php` - Main plugin file with all the PHP logic, embedded JavaScript, and CSS
- `README.md` - This documentation file

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

This plugin is released under the MIT License, the same as YOURLS itself. Feel free to use, modify, and distribute as needed.

## Support

For issues, questions, or suggestions, please open an issue on the [GitHub repository](https://github.com/MarcProe/simple-antispam).
