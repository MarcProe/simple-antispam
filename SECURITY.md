# Security Policy

## Supported versions

This is a small plugin with a single active release line. Security fixes are
applied to the latest version only; please make sure you are running the most
recent release before reporting an issue.

| Version | Supported          |
| ------- | ------------------ |
| 1.1.x   | :white_check_mark: |
| < 1.1   | :x:                |

## Reporting a vulnerability

Please **do not** report security vulnerabilities through public GitHub issues.

Instead, report them privately using GitHub's
[private vulnerability reporting](https://github.com/MarcProe/simple-antispam/security/advisories/new)
("Report a vulnerability" under the repository's **Security** tab). If that is
unavailable, open a minimal issue asking for a private contact channel — without
disclosing any details of the vulnerability.

When reporting, please include:

- A description of the vulnerability and its impact.
- Steps to reproduce (a proof of concept if possible).
- The plugin version and the YOURLS / PHP versions you are running.

You can expect an initial acknowledgement within a few days. Once a fix is
ready, it will be released and the reporter credited (unless anonymity is
requested).

## Scope

This plugin adds a math-question CAPTCHA to the YOURLS admin form. Relevant
concerns include the server-side answer verification, session handling, and the
sanitization of any output rendered into the admin page. Issues in YOURLS core
itself should be reported to the
[YOURLS project](https://github.com/YOURLS/YOURLS).
