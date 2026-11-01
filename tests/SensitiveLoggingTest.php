<?php

namespace Maatify\Mailer\Tests;

use Maatify\Logger\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Recipient data, message content and anything the mail server sends back must
 * never reach the log, whatever maatify/logger version is installed, and the
 * HTTP transport must be HTTPS-only with real certificate verification. Each
 * send runs in its own process (tests/Support/send_runner.php) against a local
 * HTTPS stand-in; the real mailer endpoint is never contacted.
 */
final class SensitiveLoggingTest extends TestCase
{
    private static int $pid = 0;
    private static int $port = 0;
    private static string $dir = '';
    private static string $pem = '';
    private static string $requestLog = '';
    /** @var array<string, int> */
    private array $sizesBefore = [];

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/mailer-test-' . bin2hex(random_bytes(4));
        mkdir(self::$dir);
        self::$pem = self::$dir . '/server.pem';
        self::$requestLog = self::$dir . '/requests.log';
        self::generateCertificate(self::$pem);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$port = (int)substr($name, strrpos($name, ':') + 1);
        self::$pid = (int)trim((string)shell_exec(sprintf(
            '%s %s %d %s %s >/dev/null 2>&1 & echo $!',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/Support/tls_server.php'),
            self::$port,
            escapeshellarg(self::$pem),
            escapeshellarg(self::$requestLog)
        )));
        for ($i = 0; $i < 50; $i++) {
            $probe = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.1);
            if ($probe) {
                fclose($probe);
                return;
            }
            usleep(100000);
        }
        self::fail('The local HTTPS stand-in did not start.');
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
        file_put_contents(self::$requestLog, '');
        $this->sizesBefore = $this->logSizes();
    }

    public function testAnAcceptedRequestLogsOnlyFixedDiagnostics(): void
    {
        $this->send($this->https(), true, 'CANARY-SUBJECT MODE:ok');
        $logged = $this->loggedText();
        $this->assertCount(1, $this->requests());
        $this->assertStringContainsString('response_received', $logged);
        $this->assertStringContainsString('param_names', $logged);
        $this->assertStringNotContainsString('/mailer', $logged);
        $this->assertNoCanary($logged);
    }

    public function testSecretsInTheServerValueAndErrorAreNotLogged(): void
    {
        $this->send($this->https() . '?key=CANARY-URL-KEY', true, 'CANARY-SUBJECT MODE:secrets');
        $logged = $this->loggedText();
        $this->assertCount(1, $this->requests());
        $this->assertStringContainsString('response_received', $logged);
        $this->assertNoCanary($logged);
    }

    public function testANonJsonResponseLogsNoRawBody(): void
    {
        $this->send($this->https(), true, 'CANARY-SUBJECT MODE:raw');
        $logged = $this->loggedText();
        $this->assertStringContainsString('invalid_response', $logged);
        $this->assertNoCanary($logged);
    }

    public function testAnEmptyResponseLogsNoRequestValues(): void
    {
        $this->send($this->https(), true, 'CANARY-SUBJECT MODE:empty');
        $logged = $this->loggedText();
        $this->assertStringContainsString('invalid_response', $logged);
        $this->assertNoCanary($logged);
    }

    public function testAnUntrustedCertificateIsRejectedBeforeAnyRequestIsSent(): void
    {
        $this->send($this->https(), false, 'CANARY-SUBJECT MODE:ok');
        $logged = $this->loggedText();
        $this->assertSame([], $this->requests(), 'No request may reach a server whose certificate is not trusted.');
        $this->assertStringContainsString('transport_error', $logged);
        $this->assertNoCanary($logged);
    }

    public function testTransportOptionsCannotSwitchVerificationOff(): void
    {
        $this->send($this->https(), false, 'CANARY-SUBJECT MODE:ok', true);
        $logged = $this->loggedText();
        $this->assertSame([], $this->requests(), 'A subclass must not be able to disable certificate verification.');
        $this->assertStringContainsString('transport_error', $logged);
        $this->assertNoCanary($logged);
    }

    public function testAPlainHttpEndpointIsRefusedAndNothingIsSent(): void
    {
        $this->send('http://127.0.0.1:' . self::$port . '/mailer?key=CANARY-URL-KEY', true, 'CANARY-SUBJECT MODE:ok');
        $logged = $this->loggedText();
        $this->assertSame([], $this->requests());
        $this->assertStringContainsString('insecure_endpoint_refused', $logged);
        $this->assertNoCanary($logged);
    }

    public function testARedirectIsNotFollowed(): void
    {
        $this->send($this->https(), true, 'CANARY-SUBJECT MODE:redirect');
        $logged = $this->loggedText();
        $this->assertCount(1, $this->requests());
        $this->assertStringContainsString('http_error', $logged);
        $this->assertNoCanary($logged);
    }

    private function https(): string
    {
        return 'https://127.0.0.1:' . self::$port . '/mailer';
    }

    private function send(string $url, bool $trusted, string $subject, bool $weaken = false): void
    {
        shell_exec(sprintf(
            '%s -d display_errors=0 %s %s %s %s %s >/dev/null 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/Support/send_runner.php'),
            escapeshellarg($url),
            escapeshellarg($trusted ? self::$pem : '-'),
            escapeshellarg($subject),
            $weaken ? 'weaken' : ''
        ));
    }

    /** @return list<array{line: string, body: string}> */
    private function requests(): array
    {
        $lines = array_filter(explode("\n", (string)file_get_contents(self::$requestLog)));

        return array_values(array_map(static fn (string $line): array => json_decode($line, true), $lines));
    }

    private function assertNoCanary(string $logged): void
    {
        foreach (['CANARY-USER', 'CANARY-NAME', 'CANARY-SUBJECT', 'CANARY-OTP', 'CANARY-TOKEN', 'CANARY-URL-KEY', 'CANARY-RESPONSE-FIELD', 'canary.example'] as $canary) {
            $this->assertStringNotContainsString($canary, $logged);
        }
    }

    private static function generateCertificate(string $pem): void
    {
        $config = self::$dir . '/openssl.cnf';
        file_put_contents($config, "[req]\ndistinguished_name=dn\nx509_extensions=v3\nprompt=no\n[dn]\nCN=127.0.0.1\n[v3]\nsubjectAltName=IP:127.0.0.1\nbasicConstraints=CA:TRUE\n");
        $options = ['config' => $config, 'x509_extensions' => 'v3', 'req_extensions' => 'v3', 'private_key_bits' => 2048];
        $key = openssl_pkey_new($options);
        $csr = $key ? openssl_csr_new(['commonName' => '127.0.0.1'], $key, $options) : false;
        $cert = $csr ? openssl_csr_sign($csr, null, $key, 1, $options) : false;
        if ($key === false || $csr === false || $cert === false) {
            self::fail('Could not generate the test certificate: ' . openssl_error_string());
        }
        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem, null, $options);
        file_put_contents($pem, $certPem . $keyPem);
    }

    /**
     * maatify/logger resolves its log directory relative to its own location
     * (a different depth per version), so every candidate directory is watched.
     *
     * @return array<string, int>
     */
    private function logSizes(): array
    {
        $loggerFile = (string)(new ReflectionClass(Logger::class))->getFileName();
        $roots = [dirname($loggerFile, 3) . '/logs', dirname($loggerFile, 4) . '/logs', dirname($loggerFile, 5) . '/logs', dirname(__DIR__) . '/logs'];
        $sizes = [];
        foreach (array_unique($roots) as $root) {
            if (!is_dir($root)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) {
                    $sizes[$file->getPathname()] = $file->getSize();
                }
            }
        }
        return $sizes;
    }

    /** Log text appended by this test only (new files in full, grown files from the old size). */
    private function loggedText(): string
    {
        clearstatcache();
        $text = '';
        foreach ($this->logSizes() as $path => $size) {
            $before = $this->sizesBefore[$path] ?? 0;
            if ($size > $before) {
                $text .= (string)file_get_contents($path, false, null, $before);
            }
        }
        $this->assertNotSame('', $text, 'No log entry was written; the check would be vacuous.');
        return $text;
    }
}
