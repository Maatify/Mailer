<?php

namespace Maatify\Mailer\Tests\Regression;

use Maatify\Mailer\Tests\Support\SourceFiles;
use Maatify\Mailer\Service\Mailer;
use Maatify\Mailer\Tests\Support\CapturesLogs;
use PHPUnit\Framework\TestCase;

/**
 * Recipient data, message content and exception text must never reach the log,
 * whatever maatify/logger version is installed, and the library no longer has
 * the HTTP relay transport.
 */
final class SensitiveLoggingTest extends TestCase
{
    use CapturesLogs;

    private const CANARIES = ['CANARY-USER', 'CANARY-NAME', 'CANARY-OTP', 'CANARY-UNKNOWN-TAG', 'canary.example'];

    private string $templates = '';

    protected function setUp(): void
    {
        $this->startLogCapture();
        $this->templates = sys_get_temp_dir() . '/mailer-templates-' . bin2hex(random_bytes(4));
        mkdir($this->templates);
        // A template that fails to compile: Twig puts the offending text in its exception message.
        file_put_contents($this->templates . '/__header.html.twig', '{% CANARY-UNKNOWN-TAG %}');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->templates . '/*') ?: []);
        @rmdir($this->templates);
    }

    public function testATemplateFailureLogsOnlyTheExceptionClassCodeAndLine(): void
    {
        $_ENV = array_merge($_ENV, ['EMAIL_SITE_URL' => 'https://site.example/', 'EMAIL_SITE_LOGO' => 'logo.png', 'EMAIL_SITE_NAME' => 'site', 'SITE_URL' => 'https://site.example']);
        $mailer = new Mailer('CANARY-USER@canary.example', 'CANARY-NAME', '', $this->templates);

        $this->assertFalse($mailer->ConfirmCode('CANARY-OTP-482913'));

        $logged = $this->loggedText();
        $this->assertStringContainsString('Twig', $logged);
        foreach (self::CANARIES as $canary) {
            $this->assertStringNotContainsString($canary, $logged);
        }
    }

    public function testNoLogCallPassesExceptionTextOrMessageData(): void
    {
        $calls = 0;
        foreach (SourceFiles::all() as $file) {
            $code = (string)preg_replace('~//[^\n]*|/\*.*?\*/~s', '', (string)file_get_contents($file));
            preg_match_all('/Logger::RecordLog\((.*?)\);/s', $code, $matches);
            foreach ($matches[1] as $arguments) {
                $calls++;
                foreach (['getMessage', 'ErrorInfo', 'getTraceAsString', '->html', '->text', 'receiver_', 'reply_', '->subject', '$_POST', '$_GET', '$_ENV'] as $forbidden) {
                    $this->assertStringNotContainsString($forbidden, $arguments, basename($file));
                }
                $this->assertDoesNotMatchRegularExpression('/message:\s*\$e\b/', $arguments, basename($file) . ': a raw throwable is logged with its message and trace');
            }
        }
        $this->assertGreaterThan(0, $calls, 'No Logger::RecordLog() call was found; the check would be vacuous.');
    }

    public function testTheHttpRelayTransportIsGone(): void
    {
        foreach (SourceFiles::all() as $file) {
            $source = (string)file_get_contents($file);
            $this->assertStringNotContainsString('StgMail', $source, basename($file));
            $this->assertStringNotContainsString('curl_', $source, basename($file));
            $this->assertStringNotContainsString('CURLOPT_', $source, basename($file));
        }
    }
}
