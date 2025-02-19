<?php

/**
 * @PHP Version >= 8.0
 * @Project   Mailer
 * @see https://www.maatify.dev Visit Maatify.dev
 * @link https://github.com/Maatify/Mailer View project on GitHub
 * @link  https://github.com/PHPMailer/PHPMailer/ (phpmailer/phpmailer),
 * @link https://github.com/symfony/mailer/ (symfony/mailer),
 *
 * @author    Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @copyright ©2024 Maatify.dev
 * @note    This Project extends other libraries phpmailer/phpmailer, symfony/mailer
 *
 * This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 *
 */

namespace Maatify\Mailer;

use App\Assist\Config\MailerConfig;
use Exception;
use Maatify\Logger\Logger;
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

    private string $twig_location = __DIR__ . '/../../../../templates/email';
    private string $language = '';

    public static function obj(string $email = '', string $name = '', string $language = '', string $twig_location = ''): self
    {
        if(empty(self::$instance))
        {
            self::$instance = new self($email, $name, $language, $twig_location);
        }
        return self::$instance;
    }

    public function __construct(string $email = '', string $name = '', string $language = '', string $twig_location = '')
    {
        if(empty($email)){
            $email = $_ENV['SMTP_REPLY_MAIL'];
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

        $this->twigLoader($language);

        return $this;
    }

    private array $data;
    private string $twig_name;

    private function CheckMailerConfigMethod(string $method): bool
    {
        return class_exists('App\Assist\Config\MailerConfig')
               &&
               method_exists('App\Assist\Config\MailerConfig', $method);
    }

    public function ConfirmCode(string $code): bool
    {
        $this->data = ['code' => $code];

        if($this->CheckMailerConfigMethod('subjectConfirmCode')){
            $this->subject = MailerConfig::obj()->subjectConfirmCode();
        }

        if(empty($this->subject)){
            $this->subject = 'Confirm Code';
        }

        $this->twig_name = 'confirm';

        return $this->Sender();
    }

    public function AdminMessage(string $message, string $subject): bool
    {
        $this->data = ['message' => $message];

        $this->subject = $subject;

        $this->reply_name = $this->receiver_name;
        $this->reply_email = $this->receiver_email;
        $this->receiver_email = $_ENV['SMTP_FROM_MAIL'];
        $this->receiver_name = $_ENV['SMTP_FROM_NAME'];

        $this->twig_name = 'message_admin';

        if($this->Sender()){
            $this->data = ['message' => 'We have received your message, we will reply ASAP.' . PHP_EOL . PHP_EOL . $message];
            $this->subject = 'RE: ' . $subject;
            $this->receiver_email = $this->reply_email;
            $this->receiver_name = $this->reply_name;
            $this->reply_name = $_ENV['SMTP_FROM_MAIL'];
            $this->reply_email = $_ENV['SMTP_FROM_NAME'];
            $this->twig_name = 'message';
            return $this->Sender();
        }else{
            return false;
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
        return $this->ConfirmLink($_ENV['SITE_URL'] . '/portal/confirm_mail.php?token=' . $code);
    }

    public function ConfirmCustomerLink(string $code, string $subject = '', string $url = ''): bool
    {
        if(empty($url)){
            $url = $_ENV['SITE_URL'];
        }
        return $this->ConfirmLink($url . '/confirm_mail.php?token=' . $code);
    }

    public function ConfirmDashboardLink(string $code): bool
    {
        return $this->ConfirmLink($_ENV['SITE_URL'] . '/dashboard/confirm-mail/' . $code);
    }

    private function ConfirmLink(string $url): bool
    {
        $this->twig_name = 'confirm_link';
        $this->data = ['code' => $url];

        if($this->CheckMailerConfigMethod('subjectConfirmMail')){
            $this->subject = MailerConfig::obj()->subjectConfirmMail();
        }

        if(empty($this->subject)){
            $this->subject = 'Confirm Mail';
        }

        return $this->Sender();
    }

    public function ResetPassDashboardLink(string $code): bool
    {
        $this->twig_name = 'forget_password';
        $this->data = ['code' => $_ENV['SITE_URL'] . '/dashboard/forget-password/' . $code,
                       'image' => $_ENV['SITE_URL'] . '/images/letter.png'];

        if($this->CheckMailerConfigMethod('subjectResetPass')){
            $this->subject = MailerConfig::obj()->subjectResetPass();
        }

        if(empty($this->subject)){
            $this->subject = 'Reset Password';
        }

        return $this->Sender();
    }

    public function TempPassword(string $password): bool
    {
        $this->data = ['code' => $password];

        if($this->CheckMailerConfigMethod('subjectTempPass')){
            $this->subject = MailerConfig::obj()->subjectTempPass();
        }

        if(empty($this->subject)){
            $this->subject = 'Your Temporary Password';
        }

        $this->twig_name = 'temp_pass';

        return $this->Sender();
    }

    private function Sender(): bool
    {
        try {
            $this->data['name'] = $this->receiver_name;
            $this->data['email'] = $this->receiver_email;
            $this->data['site_url'] = $_ENV['EMAIL_SITE_URL'];
            $this->data['site_logo'] = $_ENV['EMAIL_SITE_LOGO'];
            $this->data['site_name'] = ucwords(strtolower($_ENV['EMAIL_SITE_NAME']));

            $this->html = $this->twig->render('__header.html.twig', $this->data);

            $this->html .= $this->twig->render($this->twig_name . '.html.twig', $this->data);

            $this->html .= $this->twig->render('__footer.html.twig', $this->data);

            $this->text = $this->twig->render($this->twig_name . '.text.twig', $this->data);

            return $this->SendEmail();

        }catch (SyntaxError|RuntimeError|LoaderError|Exception $e){
            Logger::RecordLog($e, __CLASS__);
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
            if (class_exists('App\Assist\Config\MailerConfig')) {
                MailerConfig::obj($this->language);
            }
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