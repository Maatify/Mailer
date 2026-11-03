<?php

declare(strict_types=1);

use Maatify\Mailer\Service\Mailer;

/**
 * Handles a contact form: forwards the visitor's message to the site (SMTP_FROM_MAIL) with the
 * visitor as Reply-To, then sends the visitor an acknowledgement that replies to the site.
 *
 * Settings: the same as send-confirmation-code.php.
 *
 * Usage: php contact-form.php <visitor-email> <visitor-name> <message>
 * Prints `sent` and exits 0 when both messages were accepted, otherwise `not sent` and 1.
 */

require __DIR__ . '/../vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
$email = is_array($arguments) && isset($arguments[1]) && is_string($arguments[1]) ? $arguments[1] : '';
$name = is_array($arguments) && isset($arguments[2]) && is_string($arguments[2]) ? $arguments[2] : '';
$text = is_array($arguments) && isset($arguments[3]) && is_string($arguments[3]) ? $arguments[3] : '';
if ($email === '' || $text === '') {
    fwrite(STDERR, "Usage: php contact-form.php <visitor-email> <visitor-name> <message>\n");
    exit(2);
}

// One Mailer per visitor: it is addressed to the visitor, and AdminMessage() handles the site side.
$mailer = new Mailer($email, $name, '', __DIR__ . '/templates/email');
$sent = $mailer->AdminMessage($text, 'Website contact form');

echo $sent ? "sent\n" : "not sent\n";
exit($sent ? 0 : 1);
