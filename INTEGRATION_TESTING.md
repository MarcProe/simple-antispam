# Integration Testing for Math CAPTCHA Plugin

This document describes the integration testing strategy for the Math CAPTCHA plugin for YOURLS.

## Overview

The integration test verifies that the plugin works correctly with a real YOURLS installation by:
1. Installing YOURLS
2. Installing the plugin
3. Creating a short URL through the web interface using automation
4. Verifying CAPTCHA validation works correctly

## Test Solutions Provided

### 1. GitHub Actions Workflow (Primary Solution)

**File**: `.github/workflows/integration-test.yml`

**Features**:
- ✅ Fully automated
- ✅ No external services required
- ✅ Free (uses GitHub Actions free tier)
- ✅ Tests the actual web interface
- ✅ Uses PHP's built-in SQLite database
- ✅ Uses PHP's built-in web server
- ✅ Uses curl for form automation

**How it works**:
1. Sets up PHP 8.2 with required extensions (sqlite, pdo_sqlite, mbstring, gd, curl)
2. Downloads YOURLS 1.9.2
3. Creates a SQLite database
4. Configures YOURLS to use SQLite
5. Copies the plugin to YOURLS
6. Starts PHP's built-in web server on port 8080
7. Uses curl to:
   - Load the admin page
   - Extract the CAPTCHA question from the HTML
   - Calculate the correct answer
   - Submit the form with the URL and CAPTCHA answer
   - Verify the URL was shortened
   - Test wrong CAPTCHA rejection
   - Test missing CAPTCHA rejection
   - Verify the short URL redirects correctly

**To use**:
- The workflow runs automatically on push and pull requests to main
- Can be triggered manually via GitHub Actions UI
- No configuration needed

### 2. Local Shell Script

**File**: `tests/integration/test-local.sh`

**Features**:
- ✅ Runs on local machine
- ✅ Same tests as GitHub Actions
- ✅ No external services required
- ✅ Easy to debug

**Requirements**:
- PHP 7.1+ (8.2 recommended)
- PHP extensions: sqlite, pdo_sqlite, mbstring, gd, curl
- SQLite3 CLI
- curl

**To use**:
```bash
# Make executable
chmod +x tests/integration/test-local.sh

# Run the test
./tests/integration/test-local.sh
```

### 3. PHP Integration Test Suite

**File**: `tests/integration/IntegrationTest.php`

**Features**:
- ✅ Unit-style integration tests
- ✅ Mocks YOURLS functions
- ✅ Tests plugin logic without web server
- ✅ Fast execution

**To use**:
```bash
# Run directly
php tests/integration/IntegrationTest.php

# Or via Composer (if configured)
composer run integration-test
```

### 4. Docker-Based Testing

**Files**:
- `tests/integration/docker-compose.yml`
- `tests/integration/docker-setup.php`

**Features**:
- ✅ Containerized environment
- ✅ Consistent across different systems
- ✅ Easy to modify and extend

**To use**:
```bash
cd tests/integration
docker-compose up --build
```

## Test Cases Covered

All test methods verify the following scenarios:

### Positive Tests
1. ✅ CAPTCHA field appears on the URL shortening form
2. ✅ CAPTCHA question is generated correctly (format: "X + Y")
3. ✅ Correct CAPTCHA answer allows URL to be shortened
4. ✅ Short URL redirects to the correct destination

### Negative Tests
5. ✅ Wrong CAPTCHA answer is rejected with `error:captcha_wrong`
6. ✅ Missing CAPTCHA answer is rejected with `error:captcha_missing`

### Edge Cases
7. ✅ Bookmarklet requests bypass CAPTCHA (via `u` or `up` GET parameters)
8. ✅ Session-based CAPTCHA (new question after each attempt)
9. ✅ CAPTCHA field styling is applied
10. ✅ JavaScript AJAX form submission works

## Technical Details

### Why SQLite?

- **No external database server needed**: SQLite stores everything in a single file
- **Built into PHP**: No additional dependencies
- **Fast**: Perfect for testing
- **Cross-platform**: Works on Linux, macOS, Windows
- **Free**: No cost, no service to maintain

### Why PHP's Built-in Web Server?

- **No external web server needed**: Built into PHP 5.4+
- **Simple to start/stop**: Single command
- **Good enough for testing**: Handles all our test cases
- **Cross-platform**: Works everywhere PHP runs
- **Free**: No cost, no service to maintain

### Why curl?

- **Available everywhere**: Pre-installed on most systems
- **Scriptable**: Easy to use in shell scripts
- **Reliable**: Mature, well-tested tool
- **Supports cookies**: Can maintain sessions between requests
- **Free**: Open source, no cost

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    GitHub Actions Runner                       │
├─────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐   │
│  │   PHP 8.2    │    │  SQLite DB   │    │   curl       │   │
│  │  + Extensions│    │  (file-based)│    │  (HTTP client)│   │
│  └──────┬───────┘    └──────┬───────┘    └──────┬───────┘   │
│         │                   │                   │            │
│         ▼                   ▼                   ▼            │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              YOURLS + Math CAPTCHA Plugin              │   │
│  │  ┌─────────────┐  ┌─────────────────────────────────┐ │   │
│  │  │  Web Server │  │  Plugin (adds CAPTCHA to forms)   │ │   │
│  │  │  (port 8080)│  │  - Generates math questions       │ │   │
│  │  └──────┬──────┘  │  - Validates answers              │ │   │
│  │         │        │  - Blocks spam bots               │ │   │
│  │         ▼        └─────────────────────────────────┘ │   │
│  │  ┌─────────────┐                                      │   │
│  │  │  Database    │  SQLite file: /tmp/yourls-test/    │   │
│  │  │  (SQLite)    │    data/yourls.db                  │   │
│  │  └─────────────┘                                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────┘
```

## Customization

### Changing PHP Version

Edit `.github/workflows/integration-test.yml`:
```yaml
- name: Set up PHP
  uses: shivammathur/setup-php@v2
  with:
    php-version: '8.1'  # Change to desired version
```

### Changing YOURLS Version

Edit the download URL in the workflow:
```yaml
wget -q https://github.com/YOURLS/YOURLS/archive/refs/tags/1.9.2.tar.gz
# Change 1.9.2 to desired version
```

### Adding More Tests

Add additional curl commands to the workflow or shell script:
```bash
# Example: Test specific keyword
curl -s --data-urlencode "url=..." --data-urlencode "keyword=custom" ...
```

## Troubleshooting

### Common Issues

#### 1. SQLite Permissions
**Symptom**: Database creation fails
**Solution**: Ensure the data directory is writable
```bash
chmod 777 /tmp/yourls-test/data
```

#### 2. Port 8080 Already in Use
**Symptom**: PHP server fails to start
**Solution**: Change the port in the workflow/script
```bash
php -S localhost:8081 -t /tmp/yourls-test
```

#### 3. CAPTCHA Question Not Found
**Symptom**: Test fails because CAPTCHA question can't be extracted
**Solution**: Check if the plugin is activated and the form is being loaded correctly

#### 4. Session/Cookie Issues
**Symptom**: CAPTCHA verification fails even with correct answer
**Solution**: Ensure cookies are being preserved between requests (use `-b` and `-c` flags with curl)

### Debugging Tips

1. **View server logs**:
   ```bash
   tail -f /tmp/php-server.log
   ```

2. **Inspect HTML output**:
   ```bash
   curl -s http://localhost:8080/admin/ | grep -A5 -B5 "math-captcha"
   ```

3. **Check database**:
   ```bash
   sqlite3 /tmp/yourls-test/data/yourls.db ".tables"
   sqlite3 /tmp/yourls-test/data/yourls.db "SELECT * FROM yourls_options;"
   ```

4. **Manual testing**:
   - Open http://localhost:8080/admin/ in a browser
   - Check if CAPTCHA field appears
   - Try submitting with and without CAPTCHA

## Cost Analysis

| Component | Cost | Notes |
|-----------|------|-------|
| GitHub Actions | Free | 2,000 minutes/month free for public repos |
| PHP | Free | Open source |
| SQLite | Free | Built into PHP |
| PHP Web Server | Free | Built into PHP |
| curl | Free | Pre-installed on most systems |
| YOURLS | Free | Open source |
| **Total** | **Free** | No external services needed |

## Performance

- **Test duration**: ~2-3 minutes (GitHub Actions)
- **Resource usage**: Minimal (PHP + SQLite + curl)
- **Parallelism**: Can run multiple test jobs if needed

## Security Considerations

- **Isolated environment**: Each test run gets fresh containers/resources
- **No persistent data**: All data is temporary and cleaned up
- **No external access**: PHP server binds to localhost only
- **No credentials**: SQLite doesn't require authentication

## Future Enhancements

Potential improvements for the test suite:

1. **Test with MySQL**: Add a test job that uses MySQL instead of SQLite
2. **Test with different PHP versions**: Matrix strategy in GitHub Actions
3. **Test with different YOURLS versions**: Test compatibility
4. **Browser automation**: Use Playwright or Selenium for real browser testing
5. **Performance testing**: Measure response times
6. **Security testing**: Add security scan steps

## Contributing

To add new integration tests:

1. Add test cases to `.github/workflows/integration-test.yml`
2. Or add functions to `tests/integration/IntegrationTest.php`
3. Or extend `tests/integration/test-local.sh`

All test methods should follow the same pattern:
- Set up the test scenario
- Execute the action
- Verify the expected outcome
- Clean up

## License

All test files are part of the Math CAPTCHA plugin and are licensed under the MIT License, the same as YOURLS itself.
