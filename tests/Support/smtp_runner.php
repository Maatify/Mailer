<?php

// Sends one e-mail through MailerSender in its own process and prints `sent` or `not-sent`,
// the boolean the library returned to its caller.
// Usage: php smtp_runner.php <smtp-port> <recipient> <subject> [preview]
// "preview" sets $_GET['stg'], the retired preview switch, which must have no effect.

use Maatify\Mailer\MailerSender;

require __DIR__ . '/../../vendor/autoload.php';

[$script, $port, $recipient, $subject] = $argv;
if (($argv[4] ?? '') === 'preview') {
    $_GET['stg'] = '1';
}
$_ENV += [
    'SMTP_HOST' => '127.0.0.1', 'SMTP_PORT' => $port, 'SMTP_USER' => 'user', 'SMTP_PASS' => 'password',
    'SMTP_FROM_MAIL' => 'from@example.test', 'SMTP_FROM_NAME' => 'Sender',
];

final class RunnerSender extends MailerSender
{
    public function __construct(string $recipient, string $subject)
    {
        $this->receiver_email = $recipient;
        $this->receiver_name = 'Recipient';
        $this->subject = $subject;
        $this->html = '<p>CANARY-OTP-482913</p>';
        $this->text = 'CANARY-OTP-482913';
        $this->reply_email = '';
        $this->reply_name = '';
    }

    public function send(): bool
    {
        return $this->SendEmail();
    }
}

echo (new RunnerSender($recipient, $subject))->send() ? 'sent' : 'not-sent';
