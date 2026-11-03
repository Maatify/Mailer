<?php

// Calls Mailer::AdminMessage() in its own process against the local SMTP stand-in and prints
// `sent` or `not-sent`. Templates come from tests/Fixtures/templates.
// Usage: php admin_runner.php <smtp-port> <visitor-email> <visitor-name>

use Maatify\Mailer\Service\Mailer;

require __DIR__ . '/../../vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
if (!is_array($arguments) || count($arguments) !== 4) {
    fwrite(STDERR, "Usage: php admin_runner.php <smtp-port> <visitor-email> <visitor-name>\n");
    exit(2);
}
[$script, $port, $email, $name] = array_map(static fn (mixed $value): string => is_scalar($value) ? (string)$value : '', $arguments);
$_ENV = array_merge($_ENV, [
    'SMTP_HOST' => '127.0.0.1', 'SMTP_PORT' => $port, 'SMTP_USER' => 'user', 'SMTP_PASS' => 'password',
    'SMTP_FROM_MAIL' => 'office@example.test', 'SMTP_FROM_NAME' => 'Site Office',
    'EMAIL_SITE_URL' => 'https://site.example/', 'EMAIL_SITE_LOGO' => 'logo.png', 'EMAIL_SITE_NAME' => 'site',
]);

$mailer = new Mailer($email, $name, '', __DIR__ . '/../Fixtures/templates');
echo $mailer->AdminMessage('CANARY-BODY', 'CANARY-SUBJECT') ? 'sent' : 'not-sent';
