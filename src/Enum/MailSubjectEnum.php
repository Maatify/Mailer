<?php

namespace Maatify\Mailer\Enum;

/**
 * The messages whose subject line the library chooses, with the default subject the library owns
 * for each. An application changes a subject through a SubjectProviderInterface.
 */
enum MailSubjectEnum: string
{
    case ConfirmCode = 'confirm_code';
    case ConfirmMail = 'confirm_mail';
    case ResetPassword = 'reset_password';
    case TemporaryPassword = 'temporary_password';

    public function defaultSubject(): string
    {
        return match ($this) {
            self::ConfirmCode => 'Confirm Code',
            self::ConfirmMail => 'Confirm Mail',
            self::ResetPassword => 'Reset Password',
            self::TemporaryPassword => 'Your Temporary Password',
        };
    }
}
