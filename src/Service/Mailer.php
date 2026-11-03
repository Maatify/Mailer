<?php

/**
 * @PHP Version >= 8.0
 * @Project   Mailer
 * @see https://www.maatify.dev Visit Maatify.dev
 * @link https://github.com/Maatify/Mailer View project on GitHub
 * @link  https://github.com/PHPMailer/PHPMailer/ (phpmailer/phpmailer)
 *
 * @author    Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @copyright ©2024 Maatify.dev
 * @note    This Project extends the library phpmailer/phpmailer
 *
 * This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 *
 */

namespace Maatify\Mailer\Service;

use Exception;
use Maatify\Logger\Logger;
use Maatify\Mailer\Adapter\MailerSender;
use Maatify\Mailer\Contract\SubjectProviderInterface;
use Maatify\Mailer\Enum\MailSubjectEnum;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

/**
*@author    Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 */
class Mailer extends MailerSender
{
    private Environment $twig;

    private static self $instance;

    private string $twig_location = __DIR__ . '/../../../../../templates/email';
    private string $language = '';

    public static function obj(string $email = '', string $name = '', string $language = '', string $twig_location = '', ?SubjectProviderInterface $subjects = null): self
    {
        if(empty(self::$instance))
        {
            self::$instance = new self($email, $name, $language, $twig_location, $subjects);
        }
        return self::$instance;
    }

    public function __construct(string $email = '', string $name = '', string $language = '', string $twig_location = '', private readonly ?SubjectProviderInterface $subjects = null)
    {
        if(empty($email)){
            $email = self::env('SMTP_REPLY_MAIL');
        }
        
        if(!empty($twig_location)){
            $this->twig_location = $twig_location;
        }

        $this->twigLoader($language);

        if(empty($name)){
            $name = $email;
        }

        $this->receiver_name = $name;

        $this->receiver_email = $email;
    }

    public function reInitiateSender(string $email = '', string $name = '', string $language = ''): self
    {
        $this->receiver_name = $name;

        $this->receiver_email = $email;

        $this->reply_name = '';

        $this->reply_email = '';

        $this->twigLoader($language);

        return $this;
    }

    /** @var array<string, string> */
    private array $data = [];
    private string $twig_name;

    private function subjectFor(MailSubjectEnum $message): string
    {
        $subject = $this->subjects?->subject($message, $this->language);

        return $subject !== null && $subject !== '' ? $subject : $message->defaultSubject();
    }

    public function ConfirmCode(string $code): bool
    {
        $this->data = ['code' => $code];

        $this->subject = $this->subjectFor(MailSubjectEnum::ConfirmCode);

        $this->twig_name = 'confirm';

        return $this->Sender();
    }

    public function AdminMessage(string $message, string $subject): bool
    {
        // Read the settings before touching any state, so a missing setting leaves the object as it was.
        $siteEmail = self::env('SMTP_FROM_MAIL');
        $siteName = self::env('SMTP_FROM_NAME');

        $visitorEmail = $this->receiver_email;
        $visitorName = $this->receiver_name;
        $replyEmail = $this->reply_email;
        $replyName = $this->reply_name;

        try {
            $this->data = ['message' => $message];
            $this->subject = $subject;
            $this->reply_name = $visitorName;
            $this->reply_email = $visitorEmail;
            $this->receiver_email = $siteEmail;
            $this->receiver_name = $siteName;
            $this->twig_name = 'message_admin';

            if (!$this->Sender()) {
                return false;
            }

            $this->data = ['message' => 'We have received your message, we will reply ASAP.' . PHP_EOL . PHP_EOL . $message];
            $this->subject = 'RE: ' . $subject;
            $this->receiver_email = $visitorEmail;
            $this->receiver_name = $visitorName;
            $this->reply_name = $siteName;
            $this->reply_email = $siteEmail;
            $this->twig_name = 'message';

            return $this->Sender();
        } finally {
            // The two messages must not leak their recipient or Reply-To into later sends.
            $this->receiver_email = $visitorEmail;
            $this->receiver_name = $visitorName;
            $this->reply_email = $replyEmail;
            $this->reply_name = $replyName;
        }
    }

    public function Message(string $message, string $subject): bool
    {
        $this->data = ['message' => $message];

        $this->subject = $subject;

        $this->twig_name = 'message';

        return $this->Sender();
    }

    public function ConfirmUserLink(string $code): bool
    {
        return $this->ConfirmLink(self::env('SITE_URL') . '/portal/confirm_mail.php?token=' . $code);
    }

    public function ConfirmCustomerLink(string $code, string $subject = '', string $url = ''): bool
    {
        if(empty($url)){
            $url = self::env('SITE_URL');
        }
        return $this->ConfirmLink($url . '/confirm_mail.php?token=' . $code);
    }

    public function ConfirmDashboardLink(string $code): bool
    {
        return $this->ConfirmLink(self::env('SITE_URL') . '/dashboard/confirm-mail/' . $code);
    }

    private function ConfirmLink(string $url): bool
    {
        $this->twig_name = 'confirm_link';
        $this->data = ['code' => $url];

        $this->subject = $this->subjectFor(MailSubjectEnum::ConfirmMail);

        return $this->Sender();
    }

    public function ResetPassDashboardLink(string $code): bool
    {
        $this->twig_name = 'forget_password';
        $this->data = ['code' => self::env('SITE_URL') . '/dashboard/forget-password/' . $code,
                       'image' => self::env('SITE_URL') . '/images/letter.png'];

        $this->subject = $this->subjectFor(MailSubjectEnum::ResetPassword);

        return $this->Sender();
    }

    public function TempPassword(string $password): bool
    {
        $this->data = ['code' => $password];

        $this->subject = $this->subjectFor(MailSubjectEnum::TemporaryPassword);

        $this->twig_name = 'temp_pass';

        return $this->Sender();
    }

    private function Sender(): bool
    {
        try {
            $this->data['name'] = $this->receiver_name;
            $this->data['email'] = $this->receiver_email;
            $this->data['site_url'] = self::env('EMAIL_SITE_URL');
            $this->data['site_logo'] = self::env('EMAIL_SITE_LOGO');
            $this->data['site_name'] = ucwords(strtolower(self::env('EMAIL_SITE_NAME')));

            $this->html = $this->twig->render('__header.html.twig', $this->data);

            $this->html .= $this->twig->render($this->twig_name . '.html.twig', $this->data);

            $this->html .= $this->twig->render('__footer.html.twig', $this->data);

            $this->text = $this->twig->render($this->twig_name . '.text.twig', $this->data);

            return $this->SendEmail();

        }catch (SyntaxError|RuntimeError|LoaderError|Exception $e){
            Logger::RecordLog(message: [
                'exception' => get_class($e),
                'code' => $e->getCode(),
                'line' => $e->getLine(),
            ], logFile: __CLASS__);
            return false;
        }
    }

    /**
     * @param   string  $language
     *
     * @return void
     */
    public function twigLoader(string $language): void
    {
        if (! empty($language) && $language !== $this->language && file_exists($this->twig_location . '/' . $language)) {
            $this->language = $language;

            $twig_location_lang = $this->twig_location . '/' . $this->language;
            $loader = new FilesystemLoader($twig_location_lang);

            $this->twig = new Environment($loader);

        }else{
            if(empty($this->twig)){
                $loader = new FilesystemLoader($this->twig_location);

                $this->twig = new Environment($loader);
            }
        }
    }

}