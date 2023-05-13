<?php

namespace Maatify\Mailer;

use Exception;
use Maatify\Logger\Logger;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

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
        $this->data = ['name' => $this->receiver_name,
                       'code' => $code,
                       'email' => $this->receiver_email,
                       'time' => date("Y-m-d H:i:s", time())];

        $this->subject = 'Confirm Code';

        $this->twig_name = 'confirm';

        return $this->Sender();
    }

    public function Message(string $message, string $subject): bool
    {
        $this->data = ['name' => $this->receiver_name,
                       'message' => $message,
                       'email' => $this->receiver_email,
                       'time' => date("Y-m-d H:i:s", time())];

        $this->subject = $subject;

        $this->twig_name = 'message';

        return $this->Sender();
    }

    public function ConfirmCustomerLink(string $code): bool
    {

        $this->data = ['code' => $_ENV['SITE_URL'] . '/confirm_mail.php?token=' . $code,
                       'email' => $this->receiver_email,
                       'time' => date("Y-m-d H:i:s", time())];

        $this->subject = 'Confirm Mail';

        $this->twig_name = 'confirm_customer_link';

        return $this->Sender();
    }

    public function TempPassword(string $password): bool
    {
        $this->data = ['name' => $this->receiver_name,
                       'code' => $password,
                       'email' => $this->receiver_email,
                       'time' => date("Y-m-d H:i:s", time())];

        $this->subject = 'Your Temporary Password';
        $this->twig_name = 'temp_pass';

        return $this->Sender();
    }

    private function Sender(): bool
    {
        try {
            $this->html = $this->twig->render('__header.html.twig',
                $this->data
            );
            $this->html .= $this->twig->render($this->twig_name . '.html.twig',
                $this->data
            );
            $this->html .= $this->twig->render('__footer.html.twig',
                $this->data
            );

            $this->text = $this->twig->render($this->twig_name . '.text.twig',
                $this->data
            );

            return $this->SendEmail();

        }catch (SyntaxError|RuntimeError|LoaderError|Exception $e){
            Logger::RecordLog($e, __CLASS__);
            return false;
        }
    }

}