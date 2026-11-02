<?php

namespace Maatify\Mailer\Tests\Regression;

use Maatify\Mailer\Service\Mailer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Where a missing or malformed setting surfaces (documented in the CHANGELOG): settings read
 * before a message is rendered and sent throw to the caller; settings read while rendering or
 * sending are caught and the send method returns false.
 */
final class ConfigFailureTest extends TestCase
{
    /** @var array<mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->saved = $_ENV;
        $_ENV = array_merge($_ENV, [
            'SMTP_HOST' => '127.0.0.1', 'SMTP_PORT' => '1', 'SMTP_USER' => 'user', 'SMTP_PASS' => 'password',
            'SMTP_FROM_MAIL' => 'office@example.test', 'SMTP_FROM_NAME' => 'Site Office',
            'EMAIL_SITE_URL' => 'https://site.example/', 'EMAIL_SITE_LOGO' => 'logo.png', 'EMAIL_SITE_NAME' => 'site',
            'SITE_URL' => 'https://site.example',
        ]);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->saved;
    }

    private function mailer(): Mailer
    {
        return new Mailer('person@example.test', 'Person', '', __DIR__ . '/../Fixtures/templates');
    }

    public function testASettingReadBeforeSendingThrowsToTheCaller(): void
    {
        unset($_ENV['SMTP_REPLY_MAIL']);
        try {
            new Mailer('', '', '', __DIR__ . '/../Fixtures/templates');
            $this->fail('The constructor must throw when SMTP_REPLY_MAIL is missing.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SMTP_REPLY_MAIL', $e->getMessage());
        }

        $mailer = $this->mailer();
        unset($_ENV['SITE_URL']);
        try {
            $mailer->ConfirmUserLink('code');
            $this->fail('A link method must throw when SITE_URL is missing.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SITE_URL', $e->getMessage());
        }

        $mailer = $this->mailer();
        unset($_ENV['SMTP_FROM_MAIL']);
        try {
            $mailer->AdminMessage('body', 'subject');
            $this->fail('AdminMessage() must throw when SMTP_FROM_MAIL is missing.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SMTP_FROM_MAIL', $e->getMessage());
        }
    }

    public function testASettingReadWhileRenderingOrSendingMakesTheSendReturnFalse(): void
    {
        unset($_ENV['EMAIL_SITE_URL']);
        $this->assertFalse($this->mailer()->Message('body', 'subject'), 'A missing rendering setting must be reported as not sent.');

        $_ENV['EMAIL_SITE_URL'] = 'https://site.example/';
        $_ENV['SMTP_PORT'] = 'not-a-port';
        $this->assertFalse($this->mailer()->Message('body', 'subject'), 'A malformed SMTP_PORT must be reported as not sent.');

        $_ENV['SMTP_PORT'] = '1';
        unset($_ENV['SMTP_HOST']);
        $this->assertFalse($this->mailer()->Message('body', 'subject'), 'A missing SMTP_HOST must be reported as not sent.');
    }
}
