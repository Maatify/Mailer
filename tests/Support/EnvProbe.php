<?php

namespace Maatify\Mailer\Tests\Support;

use Maatify\Mailer\Adapter\MailerSender;

/** Exposes the protected configuration readers of MailerSender to the tests. */
final class EnvProbe extends MailerSender
{
    public static function string(string $key): string
    {
        return self::env($key);
    }

    public static function integer(string $key): int
    {
        return self::envInt($key);
    }
}
