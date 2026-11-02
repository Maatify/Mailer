<?php

namespace Maatify\Mailer\Contract;

use Maatify\Mailer\Enum\MailSubject;

/**
 * Lets an application choose the subject line of the messages whose subject the library selects.
 * Pass an implementation to the Mailer constructor; without one the library's default subjects
 * apply, and sending mail never requires a provider.
 */
interface SubjectProviderInterface
{
    /**
     * @param string $language The language short code the Mailer is using, or an empty string
     *                         when it uses the default template directory.
     * @return string|null The subject, or null (or an empty string) to use the library's default.
     */
    public function subject(MailSubject $message, string $language): ?string;
}
