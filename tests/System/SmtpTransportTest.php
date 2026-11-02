<?php

namespace Maatify\Mailer\Tests\System;

use Maatify\Mailer\Tests\Support\CapturesLogs;
use Maatify\Mailer\Tests\Support\LocalSmtp;
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
    use LocalSmtp;

    public static function setUpBeforeClass(): void
    {
        self::startSmtpServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopSmtpServer();
    }

    protected function setUp(): void
    {
        $this->resetSmtpEvents();
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
        foreach (glob(__DIR__ . '/../../mailer/*.php') ?: [] as $file) {
            $source = (string)preg_replace('~//[^\n]*|/\*.*?\*/~s', '', (string)file_get_contents($file));
            $this->assertStringNotContainsString('$_GET', $source, basename($file));
            $this->assertDoesNotMatchRegularExpression('/\b(die|exit)\s*[;(]/', $source, basename($file));
            $this->assertMatchesRegularExpression('/\$mail->isSMTP\(\)/', (string)file_get_contents(__DIR__ . '/../../mailer/MailerSender.php'));
        }
    }

    private function send(int $port, string $recipient, string $subject, bool $preview = false): string
    {
        return $this->runSupportScript('smtp_runner.php', (string)$port, $recipient, $subject, $preview ? 'preview' : '');
    }
}
