<?php

declare(strict_types=1);

use Maatify\Mailer\Contract\SubjectProviderInterface;
use Maatify\Mailer\Enum\MailSubjectEnum;
use Maatify\Mailer\Service\Mailer;

/**
 * Chooses the subject lines of the messages the library selects, through a subject provider.
 * Messages the provider does not answer for (it returns null) keep the library default.
 *
 * Settings: the same as send-confirmation-code.php.
 *
 * Usage: php custom-subjects.php <recipient-email>
 * Prints one `sent` or `not sent` line per message and exits 0 only when both were accepted.
 */

require __DIR__ . '/../vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
$recipient = is_array($arguments) && isset($arguments[1]) && is_string($arguments[1]) ? $arguments[1] : '';
if ($recipient === '') {
    fwrite(STDERR, "Usage: php custom-subjects.php <recipient-email>\n");
    exit(2);
}

$subjects = new class implements SubjectProviderInterface {
    public function subject(MailSubjectEnum $message, string $language): ?string
    {
        return match ($message) {
            MailSubjectEnum::ConfirmCode => 'Your verification code',
            MailSubjectEnum::TemporaryPassword => 'Your new password',
            default => null, // null (or an empty string) keeps the library default
        };
    }
};

$mailer = new Mailer($recipient, 'Customer', '', __DIR__ . '/templates/email', $subjects);
$results = [
    'confirm code' => $mailer->ConfirmCode('482913'),
    'temporary password' => $mailer->TempPassword('s3cret-Temp'),
];

$allSent = true;
foreach ($results as $label => $sent) {
    echo $label . ': ' . ($sent ? 'sent' : 'not sent') . "\n";
    $allSent = $allSent && $sent;
}
exit($allSent ? 0 : 1);
