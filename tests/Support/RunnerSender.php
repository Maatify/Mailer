<?php

namespace Maatify\Mailer\Tests\Support;

use Maatify\Mailer\Adapter\MailerSender;

/** Exposes the protected send path of MailerSender to tests/Support/smtp_runner.php. */
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
