<?php

namespace Maatify\Mailer\Tests;

use Maatify\Mailer\Tests\Support\CapturesLogs;
use PHPUnit\Framework\TestCase;

/**
 * The mailer must send through SMTP (not PHP mail()), report to its caller
 * whether the message was accepted, and have no preview switch. Each send runs
 * in its own process (tests/Support/smtp_runner.php) against a local SMTP
 * stand-in with implicit TLS and a self-signed certificate that the runner
 * trusts through openssl.cafile; no real mail is ever sent.
 */
final class SmtpTransportTest extends TestCase
{
    use CapturesLogs;

    private static int $pid = 0;
    private static int $port = 0;
    private static string $dir = '';
    private static string $pem = '';
    private static string $events = '';

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/mailer-smtp-' . bin2hex(random_bytes(4));
        mkdir(self::$dir);
        self::$pem = self::$dir . '/server.pem';
        self::$events = self::$dir . '/events.log';
        self::generateCertificate(self::$pem);

        self::$port = self::freePort();
        self::$pid = (int)trim((string)shell_exec(sprintf(
            '%s %s %d %s %s >/dev/null 2>&1 & echo $!',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/Support/smtp_server.php'),
            self::$port,
            escapeshellarg(self::$pem),
            escapeshellarg(self::$events)
        )));
        for ($i = 0; $i < 50; $i++) {
            $probe = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.1);
            if ($probe) {
                fclose($probe);
                return;
            }
            usleep(100000);
        }
        self::fail('The local SMTP stand-in did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pid > 0) {
            shell_exec('kill ' . self::$pid . ' 2>/dev/null');
        }
        array_map('unlink', glob(self::$dir . '/*') ?: []);
        @rmdir(self::$dir);
    }

    protected function setUp(): void
    {
        file_put_contents(self::$events, '');
        $this->startLogCapture();
    }

    public function testTheMessageIsDeliveredThroughSmtpAndReportedAsSent(): void
    {
        $output = $this->send(self::$port, 'person@example.test', 'CANARY-SUBJECT');
        $this->assertSame('sent', $output);
        $commands = array_column($this->events(), 'command');
        $this->assertContains('AUTH', $commands, 'SMTP authentication must have been used.');
        $this->assertContains('DATA', $commands);
        $message = $this->message();
        $this->assertStringContainsString('CANARY-OTP-482913', $message);
        $this->assertStringContainsString('Subject: CANARY-SUBJECT', $message);
    }

    public function testARefusedRecipientIsReportedAsNotSentAndLogsNoAddress(): void
    {
        $output = $this->send(self::$port, 'reject@example.test', 'CANARY-SUBJECT');
        $this->assertSame('not-sent', $output);
        $this->assertNotContains('DATA', array_column($this->events(), 'command'));
        $logged = $this->loggedText();
        $this->assertStringContainsString('mail_send_failed', $logged);
        $this->assertStringNotContainsString('reject@example.test', $logged);
        $this->assertStringNotContainsString('CANARY', $logged);
    }

    public function testAnUnreachableServerIsReportedAsNotSent(): void
    {
        $output = $this->send(self::freePort(), 'person@example.test', 'CANARY-SUBJECT');
        $this->assertSame('not-sent', $output);
        $logged = $this->loggedText();
        $this->assertStringContainsString('mail_send_failed', $logged);
        $this->assertStringNotContainsString('CANARY', $logged);
    }

    public function testTheRetiredPreviewSwitchHasNoEffect(): void
    {
        $output = $this->send(self::$port, 'person@example.test', 'CANARY-SUBJECT', true);
        $this->assertSame('sent', $output, 'With the switch set the message must still be sent and nothing may be printed.');
        $this->assertStringContainsString('CANARY-OTP-482913', $this->message());
    }

    public function testThereIsNoPreviewOrExitPathInTheSource(): void
    {
        foreach (glob(__DIR__ . '/../mailer/*.php') ?: [] as $file) {
            $source = (string)preg_replace('~//[^\n]*|/\*.*?\*/~s', '', (string)file_get_contents($file));
            $this->assertStringNotContainsString('$_GET', $source, basename($file));
            $this->assertDoesNotMatchRegularExpression('/\b(die|exit)\s*[;(]/', $source, basename($file));
            $this->assertMatchesRegularExpression('/\$mail->isSMTP\(\)/', (string)file_get_contents(__DIR__ . '/../mailer/MailerSender.php'));
        }
    }

    private function send(int $port, string $recipient, string $subject, bool $preview = false): string
    {
        return trim((string)shell_exec(sprintf(
            '%s -d display_errors=0 -d openssl.cafile=%s %s %d %s %s %s 2>/dev/null',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::$pem),
            escapeshellarg(__DIR__ . '/Support/smtp_runner.php'),
            $port,
            escapeshellarg($recipient),
            escapeshellarg($subject),
            $preview ? 'preview' : ''
        )));
    }

    /** @return list<array<mixed>> */
    private function events(): array
    {
        $events = [];
        foreach (explode("\n", (string)file_get_contents(self::$events)) as $line) {
            $decoded = $line === '' ? null : json_decode($line, true);
            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }

        return $events;
    }

    private function message(): string
    {
        foreach ($this->events() as $event) {
            if (($event['command'] ?? '') === 'DATA') {
                $message = $event['message'] ?? '';

                return is_string($message) ? $message : '';
            }
        }

        return '';
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            self::fail('Cannot reserve a local port.');
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        if (!is_string($name) || strrpos($name, ':') === false) {
            self::fail('Cannot read the reserved port.');
        }

        return (int)substr($name, (int)strrpos($name, ':') + 1);
    }

    private static function generateCertificate(string $pem): void
    {
        $config = self::$dir . '/openssl.cnf';
        file_put_contents($config, "[req]\ndistinguished_name=dn\nx509_extensions=v3\nprompt=no\n[dn]\nCN=127.0.0.1\n[v3]\nsubjectAltName=IP:127.0.0.1\nbasicConstraints=CA:TRUE\n");
        $options = ['config' => $config, 'x509_extensions' => 'v3', 'req_extensions' => 'v3', 'private_key_bits' => 2048];
        $key = openssl_pkey_new($options);
        if ($key === false) {
            self::fail('Could not generate the test key: ' . openssl_error_string());
        }
        $requestKey = $key; // openssl_csr_new() takes the key by reference
        $csr = openssl_csr_new(['commonName' => '127.0.0.1'], $requestKey, $options);
        if (!$csr instanceof \OpenSSLCertificateSigningRequest) {
            self::fail('Could not create the test certificate request: ' . openssl_error_string());
        }
        $cert = openssl_csr_sign($csr, null, $key, 1, $options);
        if ($cert === false) {
            self::fail('Could not sign the test certificate: ' . openssl_error_string());
        }
        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem, null, $options);
        file_put_contents($pem, (is_string($certPem) ? $certPem : '') . (is_string($keyPem) ? $keyPem : ''));
    }
}
