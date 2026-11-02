<?php

namespace Maatify\Mailer\Tests\System;

use Maatify\Mailer\Tests\Support\LocalSmtp;
use PHPUnit\Framework\TestCase;

/**
 * A Mailer must not leak the recipient or Reply-To of AdminMessage() into later sends, whichever
 * way AdminMessage() ends, and reInitiateSender() must not keep a stale Reply-To. Checked through
 * the messages that actually reach the SMTP stand-in (tests/Support/reuse_runner.php).
 */
final class MailerReuseTest extends TestCase
{
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
    }

    public function testAfterASuccessfulAdminMessageTheNextSendGoesToTheVisitorWithoutAReplyTo(): void
    {
        $this->assertSame(['admin' => true, 'follow_up' => true], $this->scenario('after-success'));

        $messages = $this->messages();
        $this->assertCount(3, $messages);
        $this->assertFollowUpIsPlain($messages[2], 'Visitor Name <visitor@example.test>');
    }

    public function testAfterAFailedFirstMessageTheNextSendStillGoesToTheVisitor(): void
    {
        $this->assertSame(['admin' => false, 'follow_up' => true], $this->scenario('after-failure'));

        $messages = $this->messages();
        $this->assertCount(1, $messages, 'The failed AdminMessage() sent nothing; only the follow-up reached the server.');
        $this->assertFollowUpIsPlain($messages[0], 'Visitor Name <visitor@example.test>');
    }

    public function testAfterAFailedAcknowledgementTheNextSendStillGoesToTheVisitor(): void
    {
        $this->assertSame(['admin' => false, 'follow_up' => true], $this->scenario('after-ack-failure'));

        $messages = $this->messages();
        $this->assertCount(2, $messages, 'The site message and the follow-up reached the server; the acknowledgement did not.');
        $this->assertMatchesRegularExpression('/^To: Site Office <office@example\.test>\r?$/m', $messages[0]);
        $this->assertFollowUpIsPlain($messages[1], 'Visitor Name <visitor@example.test>');
    }

    public function testAMissingSettingIsReportedBeforeAnyStateChanges(): void
    {
        $this->assertSame(['admin' => 'exception', 'follow_up' => true], $this->scenario('after-exception'));

        $messages = $this->messages();
        $this->assertCount(1, $messages, 'The failed AdminMessage() sent nothing; only the follow-up reached the server.');
        $this->assertFollowUpIsPlain($messages[0], 'Visitor Name <visitor@example.test>');
    }

    public function testWhenTheFirstSendThrowsAfterTheStateChangedTheRecipientAndReplyToAreRestored(): void
    {
        $this->assertSame(['admin' => 'error', 'follow_up' => true], $this->scenario('throw-first'));

        $messages = $this->messages();
        $this->assertCount(1, $messages, 'The throwing send reached no server; only the follow-up did.');
        $this->assertFollowUpIsPlain($messages[0], 'Visitor Name <visitor@example.test>');
    }

    public function testWhenTheAcknowledgementSendThrowsTheRecipientAndReplyToAreRestored(): void
    {
        $this->assertSame(['admin' => 'error', 'follow_up' => true], $this->scenario('throw-ack'));

        $messages = $this->messages();
        $this->assertCount(2, $messages, 'The site message and the follow-up reached the server; the throwing acknowledgement did not.');
        $this->assertMatchesRegularExpression('/^To: Site Office <office@example\.test>\r?$/m', $messages[0]);
        $this->assertFollowUpIsPlain($messages[1], 'Visitor Name <visitor@example.test>');
    }

    public function testReInitiateSenderReplacesTheRecipientAndClearsTheReplyTo(): void
    {
        $this->assertSame(['admin' => true, 'follow_up' => true], $this->scenario('reinitiate'));

        $messages = $this->messages();
        $this->assertCount(3, $messages);
        $this->assertFollowUpIsPlain($messages[2], 'Other <other@example.test>');
    }

    private function assertFollowUpIsPlain(string $message, string $recipient): void
    {
        $this->assertStringContainsString('REUSE-FOLLOW-UP', $message);
        $this->assertMatchesRegularExpression('/^To: ' . preg_quote($recipient, '/') . '\r?$/m', $message);
        $this->assertDoesNotMatchRegularExpression('/^Reply-To:/mi', $message, 'A later send must not carry the Reply-To of AdminMessage().');
    }

    /** @return array<string, bool|string> */
    private function scenario(string $name): array
    {
        $output = $this->runSupportScript('reuse_runner.php', (string)self::$port, $name);
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded, 'The runner printed no result: ' . $output);
        $results = [];
        foreach ($decoded as $key => $value) {
            $this->assertIsString($key);
            $this->assertTrue(is_bool($value) || is_string($value));
            $results[$key] = $value;
        }

        return $results;
    }
}
