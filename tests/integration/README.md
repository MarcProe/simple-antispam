# Integration Tests

This directory contains integration tests for the Math CAPTCHA plugin that test the plugin with a real YOURLS installation.

## Test Options

### 1. GitHub Actions Workflow (Recommended)

The repository includes a GitHub Actions workflow at `.github/workflows/integration-test.yml` that:

- Sets up PHP with SQLite support
- Downloads and installs YOURLS
- Installs the plugin
- Starts PHP's built-in web server
- Uses curl to automate form submissions
- Tests:
  - CAPTCHA field appears on the form
  - Correct CAPTCHA answer allows URL shortening
  - Wrong CAPTCHA answer is rejected
  - Missing CAPTCHA answer is rejected
  - Short URLs work correctly

**No external services are required** - everything runs in the GitHub Actions runner using:
- PHP's built-in SQLite database
- PHP's built-in web server
- curl for HTTP requests

### 2. Local Testing with Docker

For local testing, you can use Docker:

```bash
# Build and run the test environment
docker-compose -f tests/integration/docker-compose.yml up --build
```

This will:
- Set up a container with PHP and SQLite
- Install YOURLS
- Install the plugin
- Run the integration tests

### 3. Manual Local Testing

To test manually on your local machine:

```bash
# Navigate to the integration test directory
cd tests/integration

# Run the integration test script
php IntegrationTest.php
```

This runs unit-style tests that verify the plugin functions work correctly with mocked YOURLS functions.

## Requirements

- PHP 7.1+ (8.2 recommended)
- PHP extensions: sqlite, pdo_sqlite, mbstring, gd, curl
- Composer (for dependencies)
- curl (for HTTP requests in GitHub Actions)
- SQLite3 CLI (for database initialization)

## How It Works

### GitHub Actions Flow:

1. **Setup**: Install PHP with required extensions
2. **Install YOURLS**: Download YOURLS 1.9.2 and extract it
3. **Configure**: Create a SQLite-based config file
4. **Initialize DB**: Load YOURLS schema into SQLite
5. **Install Plugin**: Copy plugin files to YOURLS user/plugins/ directory
6. **Start Server**: Launch PHP's built-in web server on port 8080
7. **Test CAPTCHA**: 
   - Load admin page
   - Extract CAPTCHA question from HTML
   - Calculate correct answer
   - Submit form with answer
   - Verify URL was shortened
8. **Test Validation**:
   - Submit with wrong answer (should fail)
   - Submit without answer (should fail)
9. **Verify**: Check that short URL redirects correctly

### Key Features:

- **No external services**: Uses SQLite (file-based) and PHP's built-in server
- **Free**: All components are open source and free
- **Automated**: Full end-to-end test without manual intervention
- **Reliable**: Tests the actual web interface, not just PHP functions

## Troubleshooting

### If tests fail in GitHub Actions:

1. Check the workflow logs for errors
2. Look at the server logs (displayed in the failure output)
3. Verify the database was created correctly
4. Check that the plugin files are in the correct location

### Common Issues:

- **SQLite permissions**: Ensure the data directory is writable
- **Port conflicts**: PHP server uses port 8080
- **Session issues**: Cookies must be preserved between requests
- **YOURLS version**: Tested with YOURLS 1.9.2

## Customization

You can customize the test by modifying:

- `integration-test.yml`: Change PHP version, YOURLS version, test parameters
- `IntegrationTest.php`: Add more test cases or modify existing ones
- Create a `docker-compose.yml` for local Docker-based testing
