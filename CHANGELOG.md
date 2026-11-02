# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [6.0.0]

### Breaking changes

- The package now declares `php` as `>=8.4.27 <8.5.0 || >=8.5.12 <8.6.0`. Earlier releases declared no PHP constraint. Applications on an older PHP version, or on a PHP patch version below the security floors of 8.4 and 8.5, can no longer install this version.
- Every message is sent through SMTP with PHPMailer (implicit TLS). The `SMTP_*` settings were already read in 5.x, but PHPMailer's SMTP mode was not enabled, so they did not select the transport. The host-name based staging relay (`StgMail`, an HTTP request to a separate service) and the `?stg` preview switch were removed; there is no longer a transport other than SMTP.
- The send methods of `Maatify\Mailer\Service\Mailer` now return `false` when the SMTP server does not accept the message, and `true` only when it did. In 5.x they returned `true` whenever no exception was thrown.
- A missing or non-string configuration setting is now reported with a `RuntimeException` naming the setting, and `SMTP_PORT` must be an integer. Previously the missing value was passed on as `null`. Where the failure surfaces depends on where the setting is read. Settings read before a message is rendered and sent (`SMTP_REPLY_MAIL` in the constructor, `SITE_URL` in the link methods, `SMTP_FROM_MAIL` and `SMTP_FROM_NAME` in `AdminMessage()`) throw to the caller. Settings read while the message is rendered or sent (`EMAIL_SITE_URL`, `EMAIL_SITE_LOGO`, `EMAIL_SITE_NAME` and the `SMTP_*` settings used for the connection) are caught, logged by exception class, code and line, and the send method returns `false`.
- Dependencies changed: `maatify/logger` is now `^5.0 || ^7.0` (previously `^5`), `phpmailer/phpmailer` is `^6.10`, and `twig/twig` `^3.27` is now a direct requirement, replacing `symfony/twig-bundle`. `ext-curl`, `vlucas/phpdotenv` and `symfony/mailer` are no longer required; applications that relied on them being installed through this package must require them directly. `ext-openssl` is now required.
- The public classes moved to the canonical namespace layout: `Maatify\Mailer\Mailer` is now `Maatify\Mailer\Service\Mailer`, and `Maatify\Mailer\MailerSender` is now `Maatify\Mailer\Adapter\MailerSender`. Class names, method signatures and behavior are unchanged; only `use` statements and `extends` clauses need to change.
- The library no longer looks for the application class `App\Assist\Config\MailerConfig`, and no longer calls `MailerConfig::obj($language)` when a language directory is selected. The library owns the default subjects (`Confirm Code`, `Confirm Mail`, `Reset Password`, `Your Temporary Password`); an application that customized them implements `Maatify\Mailer\Contract\SubjectProviderInterface` and passes it as the fifth argument of the `Mailer` constructor (or of `Mailer::obj()`). Sending mail never requires a provider.

### Upgrading from 5.x

1. Run the application on PHP 8.4.27 or newer in 8.4, or 8.5.12 or newer in 8.5, as declared by `require.php`.
2. Change the version constraint of the library in your project to `^6.0` and update it with its dependencies:

   ```bash
   composer require 'maatify/mailer:^6.0' --with-all-dependencies
   ```

   A constraint such as `^5.0` keeps Composer on 5.x, so `composer update` alone does not upgrade. Require directly any package you used through this one and that is no longer a dependency (`vlucas/phpdotenv`, `symfony/mailer`, `ext-curl`).
3. Update the class references in your code:

   ```php
   // before (5.x)
   use Maatify\Mailer\Mailer;
   // after (6.0.0)
   use Maatify\Mailer\Service\Mailer;
   ```

   If you extend the sender base class, import `Maatify\Mailer\Adapter\MailerSender` instead of `Maatify\Mailer\MailerSender`.
4. If your application defines `App\Assist\Config\MailerConfig` to customize subjects, replace it with a `SubjectProviderInterface` implementation:

   ```php
   use Maatify\Mailer\Contract\SubjectProviderInterface;
   use Maatify\Mailer\Enum\MailSubjectEnum;

   final class AppSubjects implements SubjectProviderInterface
   {
       public function subject(MailSubjectEnum $message, string $language): ?string
       {
           return match ($message) {
               MailSubjectEnum::ConfirmCode => $language === 'ar' ? 'رمز التحقق' : 'Confirm Code',
               default => null, // null or '' keeps the library default
           };
       }
   }

   $mailer = new Mailer('user@example.com', 'User', 'en', '', new AppSubjects());
   ```
5. Make sure `SMTP_HOST`, `SMTP_PORT` (an integer), `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM_MAIL` and `SMTP_FROM_NAME` describe a reachable SMTP server with implicit TLS: mail is no longer sent through PHP's `mail()`.

### Added

- `MAILER_PACKAGE_REFERENCE.md`, the canonical reference for the public API, configuration, templates, result and failure guarantees, logging and extension point; a regression test keeps it in step with the code.
- `Maatify\Mailer\Contract\SubjectProviderInterface` and the `Maatify\Mailer\Enum\MailSubjectEnum` enum (with the library's default subject for each message): the explicit way for an application to choose subject lines.
- Support metadata in `composer.json` (`support.issues`, `support.source`, `support.security`), `LICENSE`, `SECURITY.md`, `CONTRIBUTING.md` and `CODE_OF_CONDUCT.md`.
- PHPStan at level `max` with no baseline and no suppressions, regression and system test suites, a logger compatibility check against `maatify/logger` 5.0.1, 5.1.x and 7.0, and a Consumer Verification Harness that installs the package from the committed tree into a clean Composer consumer and sends mail through a local SMTP stand-in.
- A CI workflow with a stable `CI gate` check, a pinned workflow lint, and a repository-owned verification gate (`scripts/ci/verify.sh`) that runs for the latest and the lowest supported dependencies.

### Changed

- `composer.json` metadata (description, keywords, homepage, authors, configuration and dependency policy) now follows the Maatify Composer package standard, and the `version` field was removed: versions come from Git tags. The listed authors are now the Maatify entity.

- The PHP sources moved from `mailer/` to `src/Service/` and `src/Adapter/`, and `composer.json` maps `Maatify\Mailer\` to `src/`. The default template directory is unchanged: `templates/email` in the project root, resolved from the installed package. The log file name that `Mailer` passes to `maatify/logger` is its class name, so it is now `Maatify\Mailer\Service\Mailer`.

### Fixed

- Log calls passed the log-file name where `maatify/logger` 5.1 and newer expect the log level, which `maatify/logger` 7 rejects with `InvalidArgumentException`. They now use named arguments, so every supported `maatify/logger` release accepts them and `maatify/logger` 7 can be installed.
- The acknowledgement that `AdminMessage()` sends to the visitor had its `Reply-To` name and address swapped.
- Failures are now logged with fixed markers and the exception class, code and line only.

## [5.0.1] - 2025-08-10

Published release. The changes in this release are not itemized in this changelog; see the Git history and the [GitHub release][5.0.1].

[5.0.1]: https://github.com/Maatify/Mailer/releases/tag/5.0.1
