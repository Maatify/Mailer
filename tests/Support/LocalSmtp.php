<?php

namespace Maatify\Mailer\Tests\Support;

/**
 * A local SMTP stand-in (tests/Support/smtp_server.php) with implicit TLS and a self-signed
 * certificate that child PHP processes trust through openssl.cafile. No real mail is ever sent.
 */
trait LocalSmtp
{
    private static int $pid = 0;
    private static int $port = 0;
    private static string $dir = '';
    private static string $pem = '';
    private static string $events = '';

    private static function startSmtpServer(): void
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
            escapeshellarg(__DIR__ . '/smtp_server.php'),
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

    private static function stopSmtpServer(): void
    {
        if (self::$pid > 0) {
            shell_exec('kill ' . self::$pid . ' 2>/dev/null');
        }
        array_map('unlink', glob(self::$dir . '/*') ?: []);
        @rmdir(self::$dir);
    }

    private function resetSmtpEvents(): void
    {
        file_put_contents(self::$events, '');
    }

    /** Runs a tests/Support runner script in its own PHP process and returns its trimmed output. */
    private function runSupportScript(string $script, string ...$arguments): string
    {
        return trim((string)shell_exec(sprintf(
            '%s -d display_errors=0 -d openssl.cafile=%s %s %s 2>/dev/null',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::$pem),
            escapeshellarg(__DIR__ . '/' . $script),
            implode(' ', array_map('escapeshellarg', $arguments))
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

    /** @return list<string> every message the stand-in received, in order */
    private function messages(): array
    {
        $messages = [];
        foreach ($this->events() as $event) {
            if (($event['command'] ?? '') === 'DATA' && is_string($event['message'] ?? null)) {
                $messages[] = $event['message'];
            }
        }

        return $messages;
    }

    private function message(): string
    {
        return $this->messages()[0] ?? '';
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
