<?php

namespace Maatify\Mailer\Tests\Support;

use Maatify\Mailer\Contract\SubjectProviderInterface;
use Maatify\Mailer\Enum\MailSubjectEnum;

/** A subject provider that records every request; `partial` mode answers only for some messages. */
final class RecordingSubjectProvider implements SubjectProviderInterface
{
    /** @var list<array{string, string}> */
    private array $calls = [];

    public function __construct(private readonly bool $partial)
    {
    }

    public function subject(MailSubjectEnum $message, string $language): ?string
    {
        $this->calls[] = [$message->value, $language];
        if ($this->partial) {
            return match ($message) {
                MailSubjectEnum::ConfirmCode => 'CUSTOM CODE',
                MailSubjectEnum::ResetPassword => '',
                default => null,
            };
        }

        return 'P:' . $message->value . ':' . $language;
    }

    /** @return list<array{string, string}> */
    public function calls(): array
    {
        return $this->calls;
    }
}
