# Security Policy

[![Maatify Mailer](https://img.shields.io/badge/Maatify-Mailer-blue?style=for-the-badge)](https://github.com/Maatify/Mailer)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-9C27B0?style=for-the-badge)](https://github.com/Maatify)

## Supported Versions

This policy covers the `6.x` line, prepared as release `6.0.0`. `6.x` becomes the actively supported release line once `6.0.0` is published and available to consumers; security fixes are then provided in the latest stable release within `6.x`. Users should upgrade to the latest available `6.x` version before reporting a vulnerability.

The `5.x` line becomes a legacy line from the moment `6.0.0` is published and available to consumers: it receives no commitment to periodic security maintenance and no guaranteed fix timelines. A security backport to `5.x` may be considered only for a proven high or critical severity vulnerability, on a separate branch, with tests and an independent review, and with the maintainers' explicit approval of that specific case.

As of release qualification, the latest published release is `5.0.1` and `6.0.0` is not yet published; until it is, this file does not state that `5.x` is unsupported.

| Version | Supported                                                                      |
|---------|--------------------------------------------------------------------------------|
| `6.x`   | Actively supported from the publication of `6.0.0`                             |
| `5.x`   | Legacy from the publication of `6.0.0`: no commitment; exceptional backport only |
| Older   | No                                                                             |

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
