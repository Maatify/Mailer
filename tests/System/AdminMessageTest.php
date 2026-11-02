<?php

namespace Maatify\Mailer\Tests\System;

use Maatify\Mailer\Tests\Support\LocalSmtp;
use PHPUnit\Framework\TestCase;

/**
 * AdminMessage() forwards a visitor's message to the site's own address and sends the visitor an
 * acknowledgement. Each message must carry the right recipient and Reply-To, with the address in
 * the address position and the display name in the name position.
 */
final class AdminMessageTest extends TestCase
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

    public function testTheMessageReachesTheSiteAndTheVisitorIsAcknowledged(): void
    {
        $output = $this->runSupportScript('admin_runner.php', (string)self::$port, 'visitor@example.test', 'Visitor Name');
        $this->assertSame('sent', $output);

        $messages = $this->messages();
        $this->assertCount(2, $messages, 'The site message and the acknowledgement must both be sent.');
        [$toSite, $acknowledgement] = $messages;

        $this->assertMatchesRegularExpression('/^To: Site Office <office@example\.test>\r?$/m', $toSite);
        $this->assertMatchesRegularExpression('/^Reply-To: Visitor Name <visitor@example\.test>\r?$/m', $toSite);
        $this->assertStringContainsString('CANARY-BODY', $toSite);

        $this->assertMatchesRegularExpression('/^To: Visitor Name <visitor@example\.test>\r?$/m', $acknowledgement);
        $this->assertMatchesRegularExpression('/^Reply-To: Site Office <office@example\.test>\r?$/m', $acknowledgement);
        $this->assertStringContainsString('Subject: RE: CANARY-SUBJECT', $acknowledgement);
        $this->assertStringContainsString('We have received your message', $acknowledgement);
    }
}
