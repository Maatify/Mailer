# Usage Guide

How to render your own Twig e-mail templates and send them through SMTP with `maatify/mailer`. The stable contract and the full Public Runtime API are in the [Package Reference](../../MAILER_PACKAGE_REFERENCE.md); this guide shows how to use it.

## Fit

Use this library when your PHP application sends transactional e-mail (confirmation codes, confirmation and password links, temporary passwords, contact-form messages) from templates it owns, and it must know whether the SMTP server accepted each message.

It builds the message, sends it through SMTP over implicit TLS, and returns `true` only when the server accepted it. A refused recipient, an unreachable server or a template failure is reported as `false`, never as success.

## Requirements

- PHP and dependency constraints are declared in [`composer.json`](../../composer.json): PHP `>=8.4.27 <8.5.0 || >=8.5.12 <8.6.0`, `ext-openssl`, and the packages it lists.
- An SMTP server that accepts authenticated connections over implicit TLS (typically port 465).
- A directory of Twig templates (see [Templates](#templates)); the library ships none.
- The settings below in `$_ENV`.

### Release status and installation

This guide describes `6.0.0`, the release target being prepared. As of release qualification, `6.0.0` is not yet published; Composer can install it only once it is published and available to consumers, and the live repository records the actual state after publication. The `5.x` line uses the previous namespaces and did not enable PHPMailer's SMTP mode, so the code in this guide does not work with it. After `6.0.0` is published, install it with:

```bash
composer require 'maatify/mailer:6.0.0' --with-all-dependencies
```

Upgrading from `5.x`: the namespaces, the transport and the subject customization changed. See [CHANGELOG.md](../../CHANGELOG.md).

## Non-goals

- Shipping e-mail templates.
- Queues, retries, scheduling or delivery tracking.
- Attachments, CC and BCC, or any transport other than SMTP with implicit TLS.
- Loading `.env` files or storing your SMTP credentials. Keep them in your own secret storage and never commit them.

## Configuration

The library reads these settings from `$_ENV` (not `getenv()`). If your PHP does not fill `$_ENV` from the process environment, enable it with `variables_order` (for example `EGPCS`) or populate `$_ENV` in your bootstrap.

| Setting | Meaning |
|---|---|
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` | the SMTP server, its port (an integer) and its credentials |
| `SMTP_FROM_MAIL`, `SMTP_FROM_NAME` | the sender of every message; also the site address for the contact form |
| `SMTP_REPLY_MAIL` | the recipient used when you create a `Mailer` without an address |
| `EMAIL_SITE_URL`, `EMAIL_SITE_LOGO`, `EMAIL_SITE_NAME` | made available to every template as `site_url`, `site_logo`, `site_name` |
| `SITE_URL` | the base of the links built by the confirmation and password link methods |

## Templates

Pass the template directory to the constructor (or place the templates in `templates/email` in your project root, the default). Each message needs an `.html.twig` and a `.text.twig` template, plus `__header.html.twig` and `__footer.html.twig`:

| Template | Sent by |
|---|---|
| `confirm` | `ConfirmCode()` |
| `confirm_link` | the confirm link methods |
| `forget_password` | `ResetPassDashboardLink()` |
| `temp_pass` | `TempPassword()` |
| `message` | `Message()` and the acknowledgement of `AdminMessage()` |
| `message_admin` | the site message of `AdminMessage()` |

Every template receives `name`, `email`, `site_url`, `site_logo` and `site_name`; most receive `code` or `message`. The exact variables are in the Package Reference. A working set of templates is in [`examples/templates/email/`](../../examples/templates/email/).

## Primary calls

| Call | Input | Output |
|---|---|---|
| `new Mailer($email, $name, $language, $templateDirectory, $subjectProvider)` | the recipient and, optionally, language, template directory and subject provider | a mailer for that recipient |
| `ConfirmCode($code)` | a code | `bool` |
| `ConfirmUserLink($code)`, `ConfirmCustomerLink($code, '', $url)`, `ConfirmDashboardLink($code)` | a URL-safe token | `bool` |
| `ResetPassDashboardLink($code)` | a URL-safe token | `bool` |
| `TempPassword($password)` | a password | `bool` |
| `Message($message, $subject)` | text and a subject | `bool` |
| `AdminMessage($message, $subject)` | a visitor's text and a subject | `bool` |

All of them live in `Maatify\Mailer\Service\Mailer`. `true` means the SMTP server accepted the message.

## Capability map

| Capability | Walkthrough | Example |
|---|---|---|
| Send a transactional message with the default subject | [Send a confirmation code](#send-a-confirmation-code) | [`examples/send-confirmation-code.php`](../../examples/send-confirmation-code.php) |
| Choose the subject lines | [Customize subjects](#customize-subjects) | [`examples/custom-subjects.php`](../../examples/custom-subjects.php) |
| Handle a contact form | [Handle a contact form](#handle-a-contact-form) | [`examples/contact-form.php`](../../examples/contact-form.php) |
| Find out whether a message was accepted | [Check the result](#check-the-result) | [`examples/send-confirmation-code.php`](../../examples/send-confirmation-code.php) |

## Send a confirmation code

```text
Input → Public Call → Result → Boundary
```

- **Input:** the recipient's address and name, a code, the settings above and your templates.
- **Public call:**

  ```php
  use Maatify\Mailer\Service\Mailer;

  $mailer = new Mailer('user@example.com', 'User Name', '', __DIR__ . '/templates/email');
  $sent = $mailer->ConfirmCode('482913');
  ```

- **Result:** `true` when the SMTP server accepted the message, otherwise `false`.
- **Boundary:** the library sends one message and does not retry; the subject is the library default (`Confirm Code`). The code is inserted as given.

## Customize subjects

- **Input:** your subject lines, per message kind and language.
- **Public call:** implement `SubjectProviderInterface` and pass it as the fifth constructor argument:

  ```php
  use Maatify\Mailer\Contract\SubjectProviderInterface;
  use Maatify\Mailer\Enum\MailSubjectEnum;

  $subjects = new class implements SubjectProviderInterface {
      public function subject(MailSubjectEnum $message, string $language): ?string
      {
          return $message === MailSubjectEnum::ConfirmCode ? 'Your verification code' : null;
      }
  };

  $mailer = new Mailer('user@example.com', 'User Name', '', $templates, $subjects);
  ```

- **Result:** the provider's subject is used; `null` or an empty string keeps the library default.
- **Boundary:** the provider covers the four messages whose subject the library chooses (`MailSubjectEnum`). `Message()` and `AdminMessage()` take their subject from your call. An exception thrown by the provider reaches your code.

## Handle a contact form

- **Input:** the visitor's address, name and message.
- **Public call:**

  ```php
  $mailer = new Mailer($visitorEmail, $visitorName, '', $templates);
  $sent = $mailer->AdminMessage($text, 'Website contact form');
  ```

- **Result:** the message goes to the site (`SMTP_FROM_MAIL`) with the visitor as `Reply-To`; if it was accepted, the visitor receives an acknowledgement that replies to the site. `true` only when both were accepted.
- **Boundary:** create one `Mailer` per visitor. `AdminMessage()` restores the object's recipient afterwards, but a shared instance (`Mailer::obj()`) is meant for one recipient; use `reInitiateSender()` to change it.

## Check the result

Every send method returns a `bool`; use it to decide what to tell the user and whether to try another channel. The library never retries.

Some problems surface as exceptions instead of `false`. A missing setting that is read before sending (`SMTP_REPLY_MAIL` in the constructor, `SITE_URL` in the link methods, `SMTP_FROM_*` in `AdminMessage()`) throws a `RuntimeException` that names the setting, a missing template directory throws a Twig `LoaderError`, and an exception thrown by your subject provider reaches you. Template errors and problems with the SMTP settings while sending are caught, logged without recipient data and reported as `false`. The Package Reference has the full table.

## Run the examples

The examples read their settings from `$_ENV`, so the environment of the process must reach `$_ENV`:

```bash
SMTP_HOST=smtp.example.com SMTP_PORT=465 SMTP_USER=user SMTP_PASS=secret \
SMTP_FROM_MAIL=office@example.com SMTP_FROM_NAME="Site Office" \
EMAIL_SITE_URL=https://example.com/ EMAIL_SITE_LOGO=logo.png EMAIL_SITE_NAME=example \
php -d variables_order=EGPCS examples/send-confirmation-code.php user@example.com "User Name"
```

Each example prints `sent` (or `not sent`) and exits `0` (or `1`). They send real mail to the address you give when pointed at a real SMTP server.

## Boundaries

- Logs contain fixed markers and exception metadata only, never addresses, subjects or message content.
- The library does not change PHPMailer's TLS verification: the SMTP server's certificate must be valid for `SMTP_HOST`.
- Anything not described in the Package Reference or this guide is not part of the supported contract.
