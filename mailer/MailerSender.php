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
use App\Assist\Mailer\StgMail;
use Maatify\Logger\Logger;
use PHPMailer\PHPMailer\PHPMailer;

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
    protected function SendEmail(): bool
    {
        if(!empty($_SERVER['HTTP_HOST']) && !str_contains($_SERVER['HTTP_HOST'], '84206.net') && !str_contains($_SERVER['HTTP_HOST'], '127.0.0.1')) {
//            iconv_set_encoding("internal_encoding", "UTF-8");
            ini_set('default_charset', 'UTF-8');
            $mail = new PHPMailer();
            try {
                //Server settings
                //            $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
                //            $mail->isSMTP();                                            //Send using SMTP
                $mail->Host =
                    $_ENV['SMTP_HOST'];                                    //Set the SMTP server to send through
                $mail->SMTPAuth =
                    true;                                                  //Enable SMTP authentication
                $mail->Username =
                    $_ENV['SMTP_USER'];                                    //SMTP username
                $mail->Password =
                    $_ENV['SMTP_PASS'];                                    //SMTP password
                $mail->SMTPSecure =
                    PHPMailer::ENCRYPTION_SMTPS;                           //Enable implicit TLS encryption
                $mail->Port =
                    $_ENV['SMTP_PORT'];                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

                //Recipients
                $mail->setFrom($_ENV['SMTP_FROM_MAIL'],
                    $_ENV['SMTP_FROM_NAME']);
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

                if(empty($_GET['stg'])) {
                    $mail->send();
                }else{
                    echo $this->html;
                    die();
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
        }else{
            return $this->CurlJsonPost();
        }
    }

    /**
     * Scheme, host and port only: no credentials, path, query string or fragment,
     * because any of them can carry a secret.
     */
    protected static function logSafeEndpoint(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '[unavailable]';
        }

        return ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * Extra cURL options for the HTTP transport, for example a CA bundle. They are
     * applied first, so the fixed security options below (HTTPS only, certificate
     * and host verification, no redirects) always win and cannot be switched off.
     *
     * @return array<int, mixed>
     */
    protected function transportOptions(): array
    {
        return [];
    }

    /**
     * Sends the message to the staging mailer endpoint and reports whether it was accepted:
     * true only when the HTTPS request completed with HTTP 200, the body is a JSON object, and the
     * server did not report a failure (a `value` other than 200 or a non-empty `error`).
     */
    private function CurlJsonPost(): bool
    {
        $url = StgMail::stg_mailer_url;
        if (empty($url)) {
            return false;
        }

        $params = [
            'name'    => $this->receiver_name,
            'email'   => $this->receiver_email,
            'subject' => $this->subject,
            'message' => $this->html,
        ];
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!is_string($scheme) || strtolower($scheme) !== 'https') {
            Logger::RecordLog(message: [
                'outcome' => 'insecure_endpoint_refused',
                'endpoint' => self::logSafeEndpoint($url),
            ], logFile: __CLASS__);

            return false;
        }
        $ch = curl_init();
        curl_setopt_array($ch, $this->transportOptions());
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_errno = curl_errno($ch);
        curl_close($ch);

        $outcome = 'response_received';
        $statusValue = null;
        $accepted = false;
        if ($curl_errno > 0) {
            $outcome = 'transport_error';
        } else {
            $response = is_string($result) ? json_decode($result, true) : null;
            if (is_array($response) && $response !== []) {
                // Only a plain HTTP-style status number is ever logged; the server's own values are not.
                $statusValue = isset($response['value']) && is_int($response['value']) && $response['value'] >= 100 && $response['value'] <= 599 ? $response['value'] : null;
                $reportsFailure = (array_key_exists('value', $response) && $response['value'] !== 200)
                    || (!empty($response['error']));
                $accepted = $httpCode == 200 && !$reportsFailure;
            } else {
                $outcome = ($httpCode != 200) ? 'http_error' : 'invalid_response';
            }
            if ($outcome === 'response_received' && $httpCode != 200) {
                $outcome = 'http_error';
            }
        }
        // Fixed, pre-defined diagnostics only: never request data, response bodies or server-supplied text.
        Logger::RecordLog(message: [
            'outcome' => $outcome,
            'endpoint' => self::logSafeEndpoint($url),
            'param_names' => array_keys($params),
            'http_code' => $httpCode,
            'curl_errno' => $curl_errno,
            'status' => $statusValue,
            'accepted' => $accepted,
        ], logFile: __CLASS__);

        return $accepted;
    }
}
