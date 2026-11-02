<?php

declare(strict_types=1);

use Maatify\Mailer\Service\Mailer;

/**
 * Sends a confirmation code to one recipient with the library's default subject.
 *
 * Settings (read from $_ENV): SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_MAIL,
 * SMTP_FROM_NAME, EMAIL_SITE_URL, EMAIL_SITE_LOGO, EMAIL_SITE_NAME. If your PHP does not fill $_ENV
 * from the process environment, run with `php -d variables_order=EGPCS` or set $_ENV yourself.
 *
 * Usage: php send-confirmation-code.php <recipient-email> [recipient-name]
 * Prints `sent` and exits 0 when the SMTP server accepted the message, otherwise `not sent` and 1.
 */

require __DIR__ . '/../vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
$recipient = is_array($arguments) && isset($arguments[1]) && is_string($arguments[1]) ? $arguments[1] : '';
$name = is_array($arguments) && isset($arguments[2]) && is_string($arguments[2]) ? $arguments[2] : '';
if ($recipient === '') {
    fwrite(STDERR, "Usage: php send-confirmation-code.php <recipient-email> [recipient-name]\n");
    exit(2);
}

$mailer = new Mailer($recipient, $name, '', __DIR__ . '/templates/email');
$sent = $mailer->ConfirmCode('482913');

echo $sent ? "sent\n" : "not sent\n";
exit($sent ? 0 : 1);
