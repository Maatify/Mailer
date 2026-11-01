<?php

namespace Maatify\Mailer\Tests;

use Maatify\Logger\Logger;
use Maatify\Mailer\Tests\Support\TestSender;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Recipient data, message content and raw request/response bodies must never
 * reach the log, whatever maatify/logger version is installed, and the
 * transport must verify TLS. The real mailer endpoint is never contacted.
 */
final class SensitiveLoggingTest extends TestCase
{
    private const EMAIL = 'CANARY-USER@canary.example';
    private const NAME = 'CANARY-NAME';
    private const BODY = 'CANARY-OTP-482913 reset https://canary.example/reset?token=CANARY-TOKEN';

    private static int $pid = 0;
    /** @var array<string, int> */
    private array $sizesBefore = [];

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int)substr($name, strrpos($name, ':') + 1);
        self::$pid = (int)trim((string)shell_exec(sprintf(
            '%s -S 127.0.0.1:%d %s >/dev/null 2>&1 & echo $!',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg(__DIR__ . '/router.php')
        )));
        for ($i = 0; $i < 50; $i++) {
            $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($probe) {
                fclose($probe);
                break;
            }
            usleep(100000);
        }
        // The library reads the endpoint from the host application's StgMail constant.
        if (!class_exists('App\Assist\Mailer\StgMail', false)) {
            eval(sprintf("namespace App\\Assist\\Mailer; final class StgMail { public const stg_mailer_url = 'http://127.0.0.1:%d/mailer?key=CANARY-URL-KEY'; }", $port));
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pid > 0) {
            shell_exec('kill ' . self::$pid . ' 2>/dev/null');
        }
    }

    protected function setUp(): void
    {
        $this->sizesBefore = $this->logSizes();
    }

    public function testAnAcceptedRequestLogsNoMessageContent(): void
    {
        (new TestSender(self::EMAIL, self::NAME, 'CANARY-SUBJECT MODE:json', self::BODY))->send();
        $logged = $this->loggedText();
        $this->assertStringContainsString('param_names', $logged);
        $this->assertStringContainsString('127.0.0.1', $logged);
        $this->assertNoCanary($logged);
    }

    public function testANonJsonResponseLogsNoRawBody(): void
    {
        (new TestSender(self::EMAIL, self::NAME, 'CANARY-SUBJECT MODE:raw', self::BODY))->send();
        $logged = $this->loggedText();
        $this->assertStringContainsString('param_names', $logged);
        $this->assertNoCanary($logged);
    }

    public function testAnEmptyResponseLogsNoRequestValues(): void
    {
        (new TestSender(self::EMAIL, self::NAME, 'CANARY-SUBJECT MODE:empty', self::BODY))->send();
        $logged = $this->loggedText();
        $this->assertStringContainsString('There is no response from server', $logged);
        $this->assertNoCanary($logged);
    }

    public function testLogSafeTextMasksEveryAddress(): void
    {
        $this->assertSame('Invalid address: [masked] (to) [masked]', TestSender::safeText('Invalid address: a@@b.c (to) x@y.z'));
        $this->assertSame('no address here', TestSender::safeText('no address here'));
    }

    public function testLogSafeUrlKeepsOnlySchemeHostPortAndPath(): void
    {
        $this->assertSame('https://mail.example.test:8443/send', TestSender::safeUrl('https://user:pass@mail.example.test:8443/send?key=SECRET#frag'));
        $this->assertSame('[unavailable]', TestSender::safeUrl('not a url'));
    }

    public function testTheTransportVerifiesTls(): void
    {
        $source = (string)file_get_contents(__DIR__ . '/../mailer/MailerSender.php');
        $this->assertMatchesRegularExpression('/CURLOPT_SSL_VERIFYPEER,\s*true/', $source);
        $this->assertMatchesRegularExpression('/CURLOPT_SSL_VERIFYHOST,\s*2/', $source);
        $this->assertDoesNotMatchRegularExpression('/CURLOPT_SSL_VERIFYPEER,\s*false/', $source);
        $this->assertDoesNotMatchRegularExpression('/CURLOPT_SSL_VERIFYHOST,\s*0/', $source);
    }

    private function assertNoCanary(string $logged): void
    {
        foreach (['CANARY-USER', 'CANARY-NAME', 'CANARY-SUBJECT', 'CANARY-OTP', 'CANARY-TOKEN', 'CANARY-URL-KEY', 'CANARY-RESPONSE-FIELD', 'canary.example'] as $canary) {
            $this->assertStringNotContainsString($canary, $logged);
        }
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
