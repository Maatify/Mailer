<?php

namespace Maatify\Mailer\Tests\Support;

use Maatify\Mailer\MailerSender;

/** Exposes the protected send path of MailerSender for tests. */
final class TestSender extends MailerSender
{
    public function __construct(string $email, string $name, string $subject, string $html)
    {
        $this->receiver_email = $email;
        $this->receiver_name = $name;
        $this->subject = $subject;
        $this->html = $html;
        $this->text = '';
        $this->reply_email = '';
        $this->reply_name = '';
    }

    public function send(): bool
    {
        return $this->SendEmail();
    }

    public static function safeText(string $text): string
    {
        return self::logSafeText($text);
    }

    public static function safeUrl(string $url): string
    {
        return self::logSafeUrl($url);
    }
}
