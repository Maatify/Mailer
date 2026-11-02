<?php

namespace Maatify\Mailer\Tests\System;

use Maatify\Mailer\Tests\Support\LocalSmtp;
use PHPUnit\Framework\TestCase;

/**
 * A Mailer keeps its recipient and Reply-To between calls, and AdminMessage() changes them. These
 * tests pin that documented behavior (see "State and reuse" in the Package Reference) through the
 * messages that actually reach the SMTP stand-in.
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

    public function testAfterASuccessfulAdminMessageTheNextSendKeepsTheVisitorAsRecipientAndTheSiteAsReplyTo(): void
    {
        $results = $this->scenario('after-success');
        $this->assertSame(['admin' => true, 'follow_up' => true], $results);

        $messages = $this->messages();
        $this->assertCount(3, $messages);
        $this->assertMatchesRegularExpression('/^To: Visitor Name <visitor@example\.test>\r?$/m', $messages[2]);
        $this->assertMatchesRegularExpression('/^Reply-To: Site Office <office@example\.test>\r?$/m', $messages[2]);
        $this->assertStringContainsString('REUSE-FOLLOW-UP', $messages[2]);
    }

    public function testAfterAFailedAdminMessageTheNextSendGoesToTheSiteWithTheVisitorAsReplyTo(): void
    {
        $results = $this->scenario('after-failure');
        $this->assertSame(['admin' => false, 'follow_up' => true], $results);

        $messages = $this->messages();
        $this->assertCount(1, $messages, 'The failed AdminMessage() sent nothing; only the follow-up reached the server.');
        $this->assertMatchesRegularExpression('/^To: Site Office <office@example\.test>\r?$/m', $messages[0]);
        $this->assertMatchesRegularExpression('/^Reply-To: Visitor Name <visitor@example\.test>\r?$/m', $messages[0]);
    }

    public function testReInitiateSenderReplacesTheRecipientButNotTheReplyTo(): void
    {
        $results = $this->scenario('reinitiate');
        $this->assertSame(['admin' => true, 'follow_up' => true], $results);

        $messages = $this->messages();
        $this->assertCount(3, $messages);
        $this->assertMatchesRegularExpression('/^To: Other <other@example\.test>\r?$/m', $messages[2]);
        $this->assertMatchesRegularExpression('/^Reply-To: Site Office <office@example\.test>\r?$/m', $messages[2]);
    }

    /** @return array<string, bool> */
    private function scenario(string $name): array
    {
        $output = $this->runSupportScript('reuse_runner.php', (string)self::$port, $name);
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded, 'The runner printed no result: ' . $output);
        $results = [];
        foreach ($decoded as $key => $value) {
            $this->assertIsString($key);
            $this->assertIsBool($value);
            $results[$key] = $value;
        }

        return $results;
    }
}
