<?php

namespace Maatify\Mailer\Tests\Support;

use Maatify\Mailer\Service\Mailer;

/** A Mailer whose Nth send throws an Error, to prove state is restored when sending throws. */
final class ThrowingMailer extends Mailer
{
    private int $calls = 0;

    public function __construct(private readonly int $throwOnCall, string $email, string $name, string $twigLocation)
    {
        parent::__construct($email, $name, '', $twigLocation);
    }

    protected function SendEmail(): bool
    {
        if (++$this->calls === $this->throwOnCall) {
            throw new \Error('simulated failure while sending');
        }

        return parent::SendEmail();
    }
}
