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
 * @copyright ©2023 Maatify.dev
 * @note    This Project extends other libraries phpmailer/phpmailer, symfony/mailer
 *
 * This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 *
 */

namespace Maatify\Mailer;

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

    protected static self $instance;

    public static function obj(string $email, string $name = ''): self
    {
        if(empty(self::$instance))
        {
            self::$instance = new self($email, $name);
        }
        return self::$instance;
    }

    public function __construct(string $email, string $name = '')
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../../../templates/email');

        $this->twig = new Environment($loader);

        if(empty($name)){
            $name = $email;
        }

        $this->receiver_name = $name;

        $this->receiver_email = $email;
    }

    private array $data;
    private string $twig_name;

    public function ConfirmCode(string $code): bool
    {
        $this->data = ['code' => $code];

        $this->subject = 'Confirm Code';

        $this->twig_name = 'confirm';

        return $this->Sender();
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

    public function ConfirmCustomerLink(string $code): bool
    {
        return $this->ConfirmLink($_ENV['SITE_URL'] . '/confirm_mail.php?token=' . $code);
    }

    public function ConfirmDashboardLink(string $code): bool
    {
        return $this->ConfirmLink($_ENV['SITE_URL'] . '/dashboard/confirm_mail.php?token=' . $code);
    }

    private function ConfirmLink(string $url): bool
    {
        $this->twig_name = 'confirm_link';
        $this->data = ['code' => $url];

        $this->subject = 'Confirm Mail';

        return $this->Sender();
    }

    public function TempPassword(string $password): bool
    {
        $this->data = ['code' => $password];

        $this->subject = 'Your Temporary Password';
        $this->twig_name = 'temp_pass';

        return $this->Sender();
    }

    private function Sender(): bool
    {
        try {
            $this->data['name'] = $this->receiver_name;
            $this->data['email'] = $this->receiver_email;
            $this->data['time'] = date("Y-m-d H:i:s", time());
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

}