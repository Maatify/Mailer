<?php

// Sends one e-mail through MailerSender in its own process and prints `sent` or `not-sent`,
// the boolean the library returned to its caller.
// Usage: php smtp_runner.php <smtp-port> <recipient> <subject> [preview]
// "preview" sets $_GET['stg'], the retired preview switch, which must have no effect.

use Maatify\Mailer\Tests\Support\RunnerSender;

require __DIR__ . '/../../vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
if (!is_array($arguments) || count($arguments) < 4) {
    fwrite(STDERR, "Usage: php smtp_runner.php <smtp-port> <recipient> <subject> [preview]\n");
    exit(2);
}
[$script, $port, $recipient, $subject] = array_map(static fn (mixed $value): string => is_scalar($value) ? (string)$value : '', $arguments);
if (($arguments[4] ?? '') === 'preview') {
    $_GET['stg'] = '1';
}
$_ENV += [
    'SMTP_HOST' => '127.0.0.1', 'SMTP_PORT' => $port, 'SMTP_USER' => 'user', 'SMTP_PASS' => 'password',
    'SMTP_FROM_MAIL' => 'from@example.test', 'SMTP_FROM_NAME' => 'Sender',
];

echo (new RunnerSender($recipient, $subject))->send() ? 'sent' : 'not-sent';
