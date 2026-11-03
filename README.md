<div align="center">

# Mailer

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://www.maatify.dev/company-profile/maatify_logo_white.svg">
  <source media="(prefers-color-scheme: light)" srcset="https://www.maatify.dev/company-profile/maatify_logo_dark.svg">
  <img alt="Maatify.dev" src="https://www.maatify.dev/company-profile/maatify_logo_dark.svg">
</picture>

[![Release Target](https://img.shields.io/badge/Release%20Target-6.0.0-blue)](CHANGELOG.md)
[![Published at qualification](https://img.shields.io/badge/Published%20at%20qualification-5.0.1-lightgrey)](https://packagist.org/packages/maatify/mailer)
[![PHP](https://img.shields.io/badge/PHP-8.4.27%2B%20%7C%208.5.12%2B%20(%3C8.6)-8892BF)](composer.json)
[![License](https://img.shields.io/badge/License-Proprietary-green)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%20Max-brightgreen)](#quality-status)

[![Packagist](https://img.shields.io/badge/Packagist-maatify%2Fmailer-blue)](https://packagist.org/packages/maatify/mailer)
[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/mailer)](https://packagist.org/packages/maatify/mailer/stats)
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/mailer)](https://packagist.org/packages/maatify/mailer/stats)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)

[![Usage Guide](https://img.shields.io/badge/Docs-Usage%20Guide-blue)](docs/guides/USAGE_GUIDE.md)
[![Examples](https://img.shields.io/badge/Docs-Examples-blue)](examples/)
[![Package Reference](https://img.shields.io/badge/Docs-Package%20Reference-blue)](MAILER_PACKAGE_REFERENCE.md)
[![Changelog](https://img.shields.io/badge/Docs-Changelog-blue)](CHANGELOG.md)
[![Security Policy](https://img.shields.io/badge/Docs-Security-blue)](SECURITY.md)
[![Contributing](https://img.shields.io/badge/Docs-Contributing-blue)](CONTRIBUTING.md)

PHP library that renders Twig e-mail templates supplied by your application and sends them through SMTP with PHPMailer, reporting whether the SMTP server accepted each message.

</div>

---

## Package status

This tree is the artifact for release **`6.0.0`**, which, as of this release qualification, had **not yet been published**. The version published on Packagist at the time of qualification is `5.0.1`, and it uses the previous namespaces and transport; see [Upgrade notes](#upgrade-notes). `6.0.0` is a breaking release: the namespaces, the transport, the subject customization and the PHP and dependency requirements changed.

## Key Features

- Renders the Twig templates you provide (the library ships none) and sends the result as an HTML message with a plain-text alternative.
- Sends through SMTP with PHPMailer over implicit TLS and returns `true` only when the SMTP server accepted the message.
- Ready-made messages: confirmation code, confirmation and password-reset links, temporary password, plain message, and a contact form that forwards a visitor's message to the site and acknowledges it.
- Subject lines you can customize through an explicit contract; the library owns sensible defaults.
- Logs failures through `maatify/logger` as fixed markers and exception metadata, never recipient addresses, subjects or message content.

## Requirements

- PHP `>=8.4.27 <8.5.0 || >=8.5.12 <8.6.0` with `ext-openssl`.
- `phpmailer/phpmailer ^6.10`, `twig/twig ^3.27` and `maatify/logger ^5.0 || ^7.0`.
- An SMTP server that accepts authenticated connections over implicit TLS, and a directory of Twig templates.

The authoritative constraints are in [`composer.json`](composer.json).

## Installation

```bash
composer require maatify/mailer:6.0.0
```

This command resolves through Packagist only once `6.0.0` is published (as of release qualification it was not yet published), so it cannot install this tree before that. With the constraint `^5.0` an existing project stays on `5.x`, so to move an existing project to `6.x` use `composer require 'maatify/mailer:^6.0' --with-all-dependencies` once it is published.

## Quick Usage

```php
<?php

use Maatify\Mailer\Service\Mailer;

require 'vendor/autoload.php';

// The settings (SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_MAIL, SMTP_FROM_NAME,
// EMAIL_SITE_URL, EMAIL_SITE_LOGO, EMAIL_SITE_NAME) are read from $_ENV.
$mailer = new Mailer('user@example.com', 'User Name', '', __DIR__ . '/templates/email');

if ($mailer->ConfirmCode('482913')) {
    // the SMTP server accepted the message
} else {
    // it was not accepted, or could not be sent
}
```

The Usage Guide walks through each call as `Input → Public Call → Result → Boundary`.

## Public Runtime API

| Type | Role |
|---|---|
| `Maatify\Mailer\Service\Mailer` | Entry point: the constructor, `obj()`, `reInitiateSender()`, `twigLoader()` and the send methods `ConfirmCode()`, `ConfirmUserLink()`, `ConfirmCustomerLink()`, `ConfirmDashboardLink()`, `ResetPassDashboardLink()`, `TempPassword()`, `Message()` and `AdminMessage()`. |
| `Maatify\Mailer\Contract\SubjectProviderInterface` | Lets your application choose subject lines. |
| `Maatify\Mailer\Enum\MailSubjectEnum` | The messages whose subject the library chooses, with their default subjects. |
| `Maatify\Mailer\Adapter\MailerSender` | Abstract SMTP sender that `Mailer` extends; its protected members are the documented extension point. |

The complete inventory, signatures and guarantees are in the [Package Reference](MAILER_PACKAGE_REFERENCE.md).

## Critical Runtime Behavior

- A send method returns `true` only when the SMTP server accepted the message; it never retries.
- `AdminMessage()` sends two messages (to the site, then an acknowledgement to the visitor) and restores the object's recipient afterwards.
- Settings are read from `$_ENV`, not from `getenv()` or `.env` files.

## Exception and Error Propagation

Where a failure surfaces depends on where it occurs. A missing setting that is read before sending (for example `SMTP_REPLY_MAIL` in the constructor, `SITE_URL` in the link methods, `SMTP_FROM_*` in `AdminMessage()`), a missing template directory and an exception thrown by your subject provider reach your code. Template errors and problems with the SMTP settings or connection while sending are caught, logged without recipient data and returned as `false`. The Package Reference has the full table.

## Security and Trust Boundaries

- The SMTP credentials belong to your application: keep them in your own secret storage and never commit them. The library does not store or rotate them.
- Log entries contain fixed markers and exception metadata only, never addresses, subjects, message content or exception messages.
- The library does not change PHPMailer's TLS verification: the server's certificate must be valid for `SMTP_HOST`.
- Report a vulnerability privately, as described in [SECURITY.md](SECURITY.md); do not use public issues.

## Examples

Runnable examples are in [`examples/`](examples/): a confirmation code, custom subject lines, and a contact form, with a working template set. CI executes them against a local SMTP stand-in.

## Documentation

- [Usage Guide](docs/guides/USAGE_GUIDE.md): integration workflows and boundaries.
- [Package Reference](MAILER_PACKAGE_REFERENCE.md): the stable public, runtime and behavioral contract.
- [Changelog](CHANGELOG.md): release history and the upgrade path from `5.x`.
- [Security Policy](SECURITY.md) and [Contributing Guide](CONTRIBUTING.md).

## Quality Status

Verified in CI on PHP 8.4 and 8.5, and on the lowest supported dependencies:

- PHPStan at level `max`.
- Regression and system test suites; every send runs against a local SMTP stand-in with implicit TLS, so no real mail is sent.
- The logging calls run against `maatify/logger` 5.0.1, 5.1.0, 5.1.2 and 7.0.0, which represent the supported `^5.0 || ^7.0` range.
- A consumer harness that installs the package from the committed tree into a clean Composer project and sends mail through its public API.
- Smoke execution of the examples, and an inspection of the distribution archive and its document links.
- `composer audit` with abandoned packages treated as failures.

These checks prove the implemented contract against a deterministic local SMTP server. They do not prove the behavior of a particular live mail provider.

## Development and Testing

```bash
composer update
composer analyse
composer test
scripts/ci/verify.sh latest
```

`scripts/ci/verify.sh` runs the same checks as CI. See [CONTRIBUTING.md](CONTRIBUTING.md).

## Upgrade Notes

`6.0.0` moves the classes to the canonical namespaces, sends through SMTP only, and replaces the application class `App\Assist\Config\MailerConfig` with an injected subject provider:

```php
// 5.x
use Maatify\Mailer\Mailer;

// 6.0.0
use Maatify\Mailer\Service\Mailer;
```

The complete upgrade steps are in the [Changelog](CHANGELOG.md).

## License

Proprietary. See [LICENSE](LICENSE).

## Author

Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>
Backend Lead & Technical Architect<br>
[https://www.maatify.dev](https://www.maatify.dev)

---

<div align="center">

[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)

</div>
