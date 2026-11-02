# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [6.0.0]

### Breaking changes

- The package now declares `php` as `>=8.4.27 <8.5.0 || >=8.5.12 <8.6.0`. Earlier releases declared no PHP constraint. Applications on an older PHP version, or on a PHP patch version below the security floors of 8.4 and 8.5, can no longer install this version.
- Every message is sent through SMTP with PHPMailer (implicit TLS). The `SMTP_*` settings were already read in 5.x, but PHPMailer's SMTP mode was not enabled, so they did not select the transport. The host-name based staging relay (`StgMail`, an HTTP request to a separate service) and the `?stg` preview switch were removed; there is no longer a transport other than SMTP.
- The send methods of `Maatify\Mailer\Mailer` now return `false` when the SMTP server does not accept the message, and `true` only when it did. In 5.x they returned `true` whenever no exception was thrown.
- A missing or non-string configuration setting now throws a `RuntimeException` naming the setting, and `SMTP_PORT` must be an integer. Previously the missing value was passed on as `null`.
- Dependencies changed: `maatify/logger` is now `^5.0 || ^7.0` (previously `^5`), `phpmailer/phpmailer` is `^6.10`, and `twig/twig` `^3.27` is now a direct requirement, replacing `symfony/twig-bundle`. `ext-curl`, `vlucas/phpdotenv` and `symfony/mailer` are no longer required; applications that relied on them being installed through this package must require them directly. `ext-openssl` is now required.

### Added

- Support metadata in `composer.json` (`support.issues`, `support.source`, `support.security`), `LICENSE`, `SECURITY.md`, `CONTRIBUTING.md` and `CODE_OF_CONDUCT.md`.
- PHPStan at level `max` with no baseline and no suppressions, regression and system test suites, a logger compatibility check against `maatify/logger` 5.0.1, 5.1.x and 7.0, and a Consumer Verification Harness that installs the package from the committed tree into a clean Composer consumer and sends mail through a local SMTP stand-in.
- A CI workflow with a stable `CI gate` check, a pinned workflow lint, and a repository-owned verification gate (`scripts/ci/verify.sh`) that runs for the latest and the lowest supported dependencies.

### Changed

- `composer.json` metadata (description, keywords, homepage, authors, configuration and dependency policy) now follows the Maatify Composer package standard, and the `version` field was removed: versions come from Git tags. The listed authors are now the Maatify entity.

### Fixed

- Log calls passed the log-file name where `maatify/logger` 5.1 and newer expect the log level, which `maatify/logger` 7 rejects with `InvalidArgumentException`. They now use named arguments, so every supported `maatify/logger` release accepts them and `maatify/logger` 7 can be installed.
- The acknowledgement that `AdminMessage()` sends to the visitor had its `Reply-To` name and address swapped.
- Failures are now logged with fixed markers and the exception class, code and line only.

## [5.0.1] - 2025-08-10

Published release. The changes in this release are not itemized in this changelog; see the Git history and the [GitHub release](https://github.com/Maatify/Mailer/releases/tag/5.0.1).
