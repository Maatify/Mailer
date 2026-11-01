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
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

/**
 *@author    Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 */
abstract class MailerSender
{
    protected string $text;
    protected string $html;

    protected string $receiver_name;
    protected string $receiver_email;
    protected string $reply_email;
    protected string $reply_name;
    protected string $subject;
    /**
     * A configuration setting read from the environment. The library's settings are a documented
     * contract of the host application; a missing or non-string setting is reported instead of
     * being passed on as an untyped value.
     */
    protected static function env(string $key): string
    {
        $value = $_ENV[$key] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('Missing environment setting ' . $key);
        }

        return $value;
    }

    protected static function envInt(string $key): int
    {
        $value = filter_var($_ENV[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false) {
            throw new RuntimeException('Environment setting ' . $key . ' is not an integer');
        }

        return $value;
    }

    protected function SendEmail(): bool
    {
//            iconv_set_encoding("internal_encoding", "UTF-8");
        ini_set('default_charset', 'UTF-8');
        $mail = new PHPMailer();
        try {
            //Server settings
            //            $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
            $mail->isSMTP();                                           //Send using SMTP (PHPMailer defaults to PHP mail() otherwise)
            $mail->Host =
                self::env('SMTP_HOST');                                    //Set the SMTP server to send through
            $mail->SMTPAuth =
                true;                                                  //Enable SMTP authentication
            $mail->Username =
                self::env('SMTP_USER');                                    //SMTP username
            $mail->Password =
                self::env('SMTP_PASS');                                    //SMTP password
            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_SMTPS;                           //Enable implicit TLS encryption
            $mail->Port =
                self::envInt('SMTP_PORT');                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

            //Recipients
            $mail->setFrom(self::env('SMTP_FROM_MAIL'),
                self::env('SMTP_FROM_NAME'));
            //            $mail->addAddress($email);     //Add a recipient
            $mail->addAddress($this->receiver_email,
                (! empty($this->receiver_name) ? $this->receiver_name
                    : $this->receiver_email));                                        //Add a recipient
            //            $mail->addAddress('ellen@example.com');               //Name is optional
            if(!empty($this->reply_email)){
                $mail->addReplyTo($this->reply_email,
                    (! empty($this->reply_name) ? $this->reply_name
                        : $this->reply_email));
            }
            //            $mail->addReplyTo('info@example.com', 'Information');
            //            $mail->addCC('cc@example.com');
            //            $mail->addBCC('bcc@example.com');

            //Attachments
            //            $mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
            //            $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

            $mail->CharSet = 'UTF-8';
//                $mail->addCustomHeader('Content-Type', 'text/html;charset=UTF-8');
//                $mail->Encoding = 'base64';

            //Content
            $mail->isHTML(true);                                       //Set email format to HTML

//                $mail->CharSet = 'Windows-1256';



            $mail->Subject = $this->subject;
            //            $mail->Body    = $body;
            $mail->Body = $this->html;
//                            if(!empty($this->text)) {
            $mail->AltBody = $this->text;
//                            }

            if (!$mail->send()) {
                // PHPMailer is not in exception mode, so a failed send is reported as false; log only a fixed marker.
                Logger::RecordLog(message: ['error' => 'mail_send_failed', 'transport' => 'smtp']);

                return false;
            }

            return true;
        } catch (Exception $e) {
            Logger::RecordLog(message: [
                'error' => 'mail_send_failed',
                'exception' => get_class($e),
                'code' => $e->getCode(),
            ]);

            return false;
        }
    }
}
