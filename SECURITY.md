# Security Policy

[![Maatify Mailer](https://img.shields.io/badge/Maatify-Mailer-blue?style=for-the-badge)](https://github.com/Maatify/Mailer)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-9C27B0?style=for-the-badge)](https://github.com/Maatify)

## Supported Versions

The actively supported release line is `6.x`.

Security fixes are provided in the latest stable release within the supported `6.x` line. Users should upgrade to the latest available `6.x` version before reporting a vulnerability.

| Version     | Supported |
|-------------|-----------|
| `6.x`       | Yes       |
| Older lines | No        |

## Reporting a Vulnerability

Please do not report security vulnerabilities through public GitHub issues, pull requests, or discussions.

Report them privately by email to [support@maatify.dev](mailto:support@maatify.dev) with the subject `Security: maatify/mailer`. Include:

- the affected version;
- a description of the issue and its impact;
- steps or a minimal example that reproduces it;
- any suggested mitigation, if you have one.

We will acknowledge the report, assess it, and coordinate a fix and disclosure with you. Please keep the details private until a fix is available.

## Scope

In scope: the code in this repository, including how it renders e-mail templates, builds the message and sends it through SMTP, what it writes to logs, and how it reports a failed send (a message that was not accepted must never be reported as sent).

Out of scope:

- vulnerabilities in PHPMailer, Twig, `maatify/logger` or other third-party dependencies, which should be reported to their maintainers;
- the SMTP server you send through;
- the secrets and configuration of the application that uses this library. Never commit your SMTP credentials; keep them in your own secret storage.
