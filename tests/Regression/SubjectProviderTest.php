<?php

namespace Maatify\Mailer\Tests\Regression;

use Maatify\Mailer\Enum\MailSubjectEnum;
use PHPUnit\Framework\TestCase;

/**
 * The library owns the default subjects; an application customizes them only through an injected
 * SubjectProviderInterface. The retired host class App\Assist\Config\MailerConfig is never
 * consulted. Each case runs in its own process (tests/Support/subject_runner.php) because the
 * retired host class is declared globally.
 */
final class SubjectProviderTest extends TestCase
{
    private const DEFAULTS = [
        'confirm_code' => 'Confirm Code',
        'confirm_mail' => 'Confirm Mail',
        'reset_password' => 'Reset Password',
        'temporary_password' => 'Your Temporary Password',
    ];

    public function testWithoutAProviderTheLibraryDefaultsAreUsed(): void
    {
        $result = $this->execute('none');
        $this->assertSame(self::DEFAULTS, $result['subjects']);
        $this->assertSame([], $result['calls']);
    }

    public function testTheDefaultsAreTheOnesTheEnumOwns(): void
    {
        $owned = [];
        foreach (MailSubjectEnum::cases() as $case) {
            $owned[$case->value] = $case->defaultSubject();
        }
        $this->assertSame(self::DEFAULTS, $owned);
    }

    public function testTheRetiredHostConfigurationClassIsNeverConsulted(): void
    {
        $result = $this->execute('legacy');
        $this->assertSame(self::DEFAULTS, $result['subjects']);
    }

    public function testAProviderChoosesEverySubjectAndSeesTheMessageKind(): void
    {
        $result = $this->execute('provider');
        $this->assertSame([
            'confirm_code' => 'P:confirm_code:',
            'confirm_mail' => 'P:confirm_mail:',
            'reset_password' => 'P:reset_password:',
            'temporary_password' => 'P:temporary_password:',
        ], $result['subjects']);
        $this->assertSame([['confirm_code', ''], ['confirm_mail', ''], ['reset_password', ''], ['temporary_password', '']], $result['calls']);
    }

    public function testANullOrEmptySubjectFallsBackToTheLibraryDefault(): void
    {
        $result = $this->execute('partial');
        $this->assertSame([
            'confirm_code' => 'CUSTOM CODE',
            'confirm_mail' => 'Confirm Mail',
            'reset_password' => 'Reset Password',
            'temporary_password' => 'Your Temporary Password',
        ], $result['subjects']);
    }

    public function testTheProviderReceivesTheLanguageTheMailerUses(): void
    {
        $result = $this->execute('language');
        $this->assertSame('P:confirm_code:ar', $result['subjects']['confirm_code']);
        $this->assertSame(['confirm_code', 'ar'], $result['calls'][0]);
    }

    /** @return array{subjects: array<string, string>, calls: list<array{string, string}>} */
    private function execute(string $mode): array
    {
        $output = trim((string)shell_exec(sprintf(
            '%s -d display_errors=0 %s %s 2>/dev/null',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/../Support/subject_runner.php'),
            escapeshellarg($mode)
        )));
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded, 'The runner printed no result: ' . $output);
        $this->assertIsArray($decoded['subjects'] ?? null);
        $this->assertIsArray($decoded['calls'] ?? null);
        /** @var array{subjects: array<string, string>, calls: list<array{string, string}>} $decoded */
        return $decoded;
    }
}
