# Mailer Package Reference

Canonical reference for the stable public, runtime and behavioral contract of `maatify/mailer`.

Composer identity, metadata, requirements, dependencies, autoloading and scripts are owned by [`composer.json`](composer.json) and are not restated here. Changes between releases, including the upgrade path from `5.x`, are recorded in [CHANGELOG.md](CHANGELOG.md).

Usage walkthroughs are in the [Usage Guide](docs/guides/USAGE_GUIDE.md); runnable code is in [`examples/`](examples/).

## Purpose and boundaries

The library renders Twig e-mail templates that the application provides and sends the result through SMTP with PHPMailer. It builds the message, sends it, reports to the caller whether the SMTP server accepted it, and logs a failure without recipient data, message content or exception text.

Non-goals:

- shipping e-mail templates (the application owns its templates and their content);
- queues, retries, scheduling or delivery tracking;
- attachments, CC and BCC, or any transport other than SMTP with implicit TLS;
- reading configuration files or loading `.env` files (the application puts its settings into `$_ENV`);
- storing, rotating or protecting the SMTP credentials (the application owns them).

## Source topology

```text
Source Topology: Single Capability
```

Capability: sending Twig-rendered e-mail through SMTP.

The sources live under `src/{Responsibility}/` and use the canonical namespace `Maatify\Mailer\` (`Maatify\{PackageName}\` plus the responsibility):

| Type | File | Responsibility |
|---|---|---|
| `Maatify\Mailer\Service\Mailer` | `src/Service/Mailer.php` | rendering and send orchestration (the public entry point) |
| `Maatify\Mailer\Adapter\MailerSender` | `src/Adapter/MailerSender.php` | SMTP delivery through PHPMailer and configuration reading |
| `Maatify\Mailer\Contract\SubjectProviderInterface` | `src/Contract/SubjectProviderInterface.php` | application-supplied subject lines |
| `Maatify\Mailer\Enum\MailSubjectEnum` | `src/Enum/MailSubjectEnum.php` | the messages whose subject the library chooses, with their defaults |

`composer.json` maps `Maatify\Mailer\` to `src/`. No structural exception applies: the `5.x` types `Maatify\Mailer\Mailer` and `Maatify\Mailer\MailerSender` moved to the canonical namespaces in `6.0.0`, a breaking release, and the migration is described in [CHANGELOG.md](CHANGELOG.md).

## Consumer workflow

```text
Application settings ($_ENV) + templates → Mailer → rendered message → SMTP server → boolean result
```

1. The application provides the settings below in `$_ENV` and a template directory.
2. The application creates a `Mailer` for a recipient: `new Mailer($email, $name, $language, $templateDirectory, $subjectProvider)`.
3. The application calls one of the send methods.
4. The library renders the templates, sends one message (two for `AdminMessage()`) and returns `true` only when the SMTP server accepted the message.

## Public Runtime API

### `Maatify\Mailer\Service\Mailer` (extends `MailerSender`)

| Member | Contract |
|---|---|
| `__construct(string $email = '', string $name = '', string $language = '', string $twig_location = '', ?SubjectProviderInterface $subjects = null)` | Creates a mailer for one recipient. An empty `$email` uses `SMTP_REPLY_MAIL`; an empty `$name` uses the address. A non-empty `$twig_location` replaces the default template directory. `$language` selects a language subdirectory, see [Templates](#templates). `$subjects` customizes subject lines, see [Subject lines](#subject-lines). Throws `RuntimeException` when `SMTP_REPLY_MAIL` is needed and missing, and `Twig\Error\LoaderError` when the template directory does not exist. |
| `static obj(string $email = '', string $name = '', string $language = '', string $twig_location = '', ?SubjectProviderInterface $subjects = null): self` | Returns the shared instance. The arguments of the first call, the provider included, create it; later calls return the same instance and ignore their arguments. Use `reInitiateSender()` to change the recipient. |
| `reInitiateSender(string $email = '', string $name = '', string $language = ''): self` | Replaces the recipient and re-evaluates the language directory; returns the same instance. It clears any `Reply-To` and does not change the provider, see [State and reuse](#state-and-reuse). Unlike the constructor, it takes the address and name as given: an empty `$email` is not replaced by `SMTP_REPLY_MAIL`. |
| `twigLoader(string $language): void` | Selects the template directory for a language, see [Templates](#templates). Called by the constructor and by `reInitiateSender()`. |
| `ConfirmCode(string $code): bool` | Sends the `confirm` message with the code. |
| `ConfirmUserLink(string $code): bool` | Sends the `confirm_link` message with the URL `SITE_URL/portal/confirm_mail.php?token=<code>`. |
| `ConfirmCustomerLink(string $code, string $subject = '', string $url = ''): bool` | Sends the `confirm_link` message with the URL `<url>/confirm_mail.php?token=<code>`; an empty `$url` uses `SITE_URL`. The `$subject` parameter is accepted for compatibility and ignored: the subject is the confirm-mail subject. |
| `ConfirmDashboardLink(string $code): bool` | Sends the `confirm_link` message with the URL `SITE_URL/dashboard/confirm-mail/<code>`. |
| `ResetPassDashboardLink(string $code): bool` | Sends the `forget_password` message with the URL `SITE_URL/dashboard/forget-password/<code>`. |
| `TempPassword(string $password): bool` | Sends the `temp_pass` message with the password. |
| `Message(string $message, string $subject): bool` | Sends the `message` template with the given text and subject to the recipient. |
| `AdminMessage(string $message, string $subject): bool` | Forwards a visitor's message to the site and acknowledges it, see [AdminMessage](#adminmessage). |

Codes, tokens and passwords are inserted into URLs and templates as given; the caller passes URL-safe values.

### `Maatify\Mailer\Contract\SubjectProviderInterface`

| Member | Contract |
|---|---|
| `subject(MailSubjectEnum $message, string $language): ?string` | Returns the subject for a message, or `null` or an empty string to use the library default. `$language` is the language the mailer is using (empty for the default template directory). |

### `Maatify\Mailer\Enum\MailSubjectEnum` (string-backed)

| Case | Value | Default subject | Used by |
|---|---|---|---|
| `ConfirmCode` | `confirm_code` | `Confirm Code` | `ConfirmCode()` |
| `ConfirmMail` | `confirm_mail` | `Confirm Mail` | `ConfirmUserLink()`, `ConfirmCustomerLink()`, `ConfirmDashboardLink()` |
| `ResetPassword` | `reset_password` | `Reset Password` | `ResetPassDashboardLink()` |
| `TemporaryPassword` | `temporary_password` | `Your Temporary Password` | `TempPassword()` |

`defaultSubject(): string` returns the default subject of a case. `Message()` and `AdminMessage()` take their subject from the caller.

## Subject lines

The library chooses the subject of four messages (the `MailSubjectEnum` cases above). For each, it asks the provider passed to the constructor; a `null` or empty answer, or no provider at all, gives the library default. `Message()` and `AdminMessage()` use the subject the caller passes. The provider is consulted on every send, before rendering, so an exception it throws reaches the caller. With `Mailer::obj()` the provider of the first call stays for the life of the shared instance.

## State and reuse

A `Mailer` object holds its recipient (address and name) between calls; each send method sets only the template, data and subject of its own message. `AdminMessage()` temporarily addresses the site and then the visitor, and restores the object to its previous recipient and `Reply-To` whichever way it ends: when it succeeds, when the site message or the acknowledgement fails, and when it throws. A later send from the same object therefore goes to the original recipient and carries no `Reply-To`.

`reInitiateSender()` replaces the recipient and clears any `Reply-To`. `Mailer::obj()` returns one shared instance; use `reInitiateSender()` to address a different recipient, or create a new `Mailer` for each recipient. This behavior is covered by `tests/System/MailerReuseTest.php`.

## Configuration

The library reads its settings from `$_ENV`, not from `getenv()`. Populating `$_ENV` (for example through the `variables_order` setting or the application's own loader) is the application's responsibility. A setting must be a string; `SMTP_PORT` must be an integer (or an integer string).

| Setting | Used for | Read when |
|---|---|---|
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` | the SMTP connection and its authentication | sending |
| `SMTP_FROM_MAIL`, `SMTP_FROM_NAME` | the sender of every message; the site recipient and reply identity of `AdminMessage()` | sending; `AdminMessage()` before rendering |
| `SMTP_REPLY_MAIL` | the recipient when the constructor receives an empty address | construction |
| `EMAIL_SITE_URL`, `EMAIL_SITE_LOGO`, `EMAIL_SITE_NAME` | the template variables `site_url`, `site_logo`, `site_name` | rendering |
| `SITE_URL` | the base of the URLs built by the link methods | before rendering |

## Templates

The library ships no templates. The default template directory is `templates/email` in the project root, resolved from the installed package; pass a different directory to the constructor.

When a language is given and a subdirectory of that name exists in the template directory, templates are loaded from that subdirectory and the language is passed to the subject provider. A language without a matching directory leaves the previous selection unchanged (the template directory itself on first use). Twig is used with its default environment options.

Required templates, each with an `.html.twig` and a `.text.twig` variant (the text variant is the alternative body):

| Template | Sent by | Variables besides the common ones |
|---|---|---|
| `__header`, `__footer` (HTML only) | every message | none |
| `confirm` | `ConfirmCode()` | `code` |
| `confirm_link` | the confirm link methods | `code` (the URL) |
| `forget_password` | `ResetPassDashboardLink()` | `code` (the URL), `image` (`SITE_URL/images/letter.png`) |
| `temp_pass` | `TempPassword()` | `code` (the password) |
| `message` | `Message()`, the acknowledgement of `AdminMessage()` | `message` |
| `message_admin` | the site message of `AdminMessage()` | `message` |

Common variables for every template: `name` and `email` (the recipient), `site_url`, `site_logo`, and `site_name` (the configured name with each word capitalized).

For the `message_admin` template the recipient is the site, so `name` and `email` are the site's; the visitor's address is the message's `Reply-To`, and the template does not receive it.

## Message and transport contract

- Transport: PHPMailer in SMTP mode, SMTP authentication enabled, implicit TLS (`SMTPS`) on `SMTP_PORT`. The library does not change PHPMailer's TLS verification options.
- Sender: `SMTP_FROM_MAIL` and `SMTP_FROM_NAME`. Recipient: the mailer's address, with the name or the address as display name. `Reply-To` is set only on the messages of `AdminMessage()`, see [State and reuse](#state-and-reuse).
- Content: UTF-8, an HTML body (header, message and footer templates) and a plain-text alternative body. No attachments, CC or BCC.
- One message per send method call, except `AdminMessage()`.

### `AdminMessage()`

1. The visitor's mailer address and name become the reply identity, and the site (`SMTP_FROM_MAIL`, `SMTP_FROM_NAME`) becomes the recipient. The `message_admin` template is sent with the caller's subject and the visitor as `Reply-To`.
2. Only if that message was accepted, an acknowledgement is sent to the visitor with the `message` template, the subject `RE: <subject>`, the text `We have received your message, we will reply ASAP.` followed by the original message, and the site as `Reply-To`.
3. The method returns `false` when the first message was not accepted (no acknowledgement is attempted), otherwise the result of the acknowledgement.

## Result and failure guarantees

A send method returns `true` only when the SMTP server accepted the message. It returns `false` when the server refuses a recipient or the message, when it cannot be reached or authenticated, and when rendering or the connection settings fail while sending.

Where a failure surfaces depends on where the problem occurs:

| Situation | Outcome |
|---|---|
| `SMTP_REPLY_MAIL` missing when the constructor needs it | `RuntimeException` naming the setting, from the constructor |
| Template directory missing | `Twig\Error\LoaderError` from the constructor |
| `SITE_URL` missing in a link method that needs it | `RuntimeException` naming the setting, to the caller |
| `SMTP_FROM_MAIL` or `SMTP_FROM_NAME` missing in `AdminMessage()` | `RuntimeException` naming the setting, to the caller; the object is left unchanged |
| An exception thrown by a `SubjectProviderInterface` | propagates to the caller (the provider runs before rendering) |
| Template error, or a missing `EMAIL_SITE_*` setting, while rendering | caught and logged; the method returns `false` |
| Missing or malformed `SMTP_*` setting, SMTP refusal or connection failure | caught and logged; the method returns `false` |

## Logging

Failures are written through `maatify/logger` as fixed markers and exception metadata only:

- a message the SMTP server did not accept: `error = mail_send_failed`, `transport = smtp`;
- an exception while sending: `error = mail_send_failed`, the exception class and code;
- an exception while rendering: the exception class, code and line, under a log file named after the `Mailer` class (`Maatify\Mailer\Service\Mailer`).

The library never passes recipient addresses or names, subjects, message content, template variables, credentials or exception messages to the logger. This covers what the library passes to the logger; it does not control other operational data that the logger itself may record. CI verifies the call shape against `maatify/logger` 5.0.1, 5.1.0, 5.1.2 and 7.0.0, which represent the supported range.

## Extension point

`MailerSender` is abstract. A subclass sets the protected fields `receiver_name`, `receiver_email`, `reply_name`, `reply_email`, `subject`, `html` and `text`, then calls the protected `SendEmail(): bool`, which sends one message with the contract above and returns whether it was accepted. The protected static `env(string $key): string` and `envInt(string $key): int` read the settings and throw `RuntimeException` for a missing or malformed value. The library's own tests use this to drive the transport directly; applications normally use `Mailer`.

## Compatibility notes

- The `Maatify\Mailer` namespace, the type names and the signatures above are the public contract of the `6.0.0` line.
- The exact PHP and dependency requirements are those declared in `composer.json`; the changes introduced by `6.0.0` and the upgrade path from `5.x` are in [CHANGELOG.md](CHANGELOG.md).
