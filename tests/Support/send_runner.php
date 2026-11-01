<?php

// Sends one e-mail through the library's HTTP transport in its own process.
// Usage: php send_runner.php <endpoint-url> <ca-file|-> <subject> [weaken]
// "weaken" makes the subclass try to switch certificate verification off through
// transportOptions(), which the library must ignore.

use Maatify\Mailer\MailerSender;

require __DIR__ . '/../../vendor/autoload.php';

[$script, $url, $caFile, $subject] = $argv;
$weaken = ($argv[4] ?? '') === 'weaken';

// The library reads the endpoint from the host application's StgMail constant.
eval(sprintf("namespace App\\Assist\\Mailer; final class StgMail { public const stg_mailer_url = %s; }", var_export($url, true)));

final class RunnerSender extends MailerSender
{
    public function __construct(private readonly string $caFile, private readonly bool $weaken, string $subject)
    {
        $this->receiver_email = 'CANARY-USER@canary.example';
        $this->receiver_name = 'CANARY-NAME';
        $this->subject = $subject;
        $this->html = 'CANARY-OTP-482913 reset https://canary.example/reset?token=CANARY-TOKEN';
        $this->text = '';
        $this->reply_email = '';
        $this->reply_name = '';
    }

    protected function transportOptions(): array
    {
        $options = $this->caFile !== '-' ? [CURLOPT_CAINFO => $this->caFile] : [];
        if ($this->weaken) {
            $options += [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_FOLLOWLOCATION => true];
        }

        return $options;
    }

    public function send(): bool
    {
        return $this->SendEmail();
    }
}

(new RunnerSender($caFile, $weaken, $subject))->send();
