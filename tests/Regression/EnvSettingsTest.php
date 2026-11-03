<?php

namespace Maatify\Mailer\Tests\Regression;

use Maatify\Mailer\Tests\Support\EnvProbe;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The library's settings are read from $_ENV. A missing or malformed setting is reported with
 * the setting's name (never its value), instead of being passed on as an untyped value.
 */
final class EnvSettingsTest extends TestCase
{
    private const KEY = 'MAILER_TEST_SETTING';

    /** @var array<mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        $this->saved = $_ENV;
        unset($_ENV[self::KEY]);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->saved;
    }

    public function testAStringSettingIsReturnedAsIs(): void
    {
        $_ENV[self::KEY] = 'smtp.example.test';
        $this->assertSame('smtp.example.test', EnvProbe::string(self::KEY));

        $_ENV[self::KEY] = '';
        $this->assertSame('', EnvProbe::string(self::KEY), 'An empty string is a present setting.');
    }

    public function testAMissingOrNonStringSettingIsReportedByName(): void
    {
        foreach ([null, 587, ['a'], true] as $value) {
            if ($value !== null) {
                $_ENV[self::KEY] = $value;
            }
            try {
                EnvProbe::string(self::KEY);
                $this->fail('A setting that is not a string must be rejected: ' . get_debug_type($value));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString(self::KEY, $e->getMessage());
            }
        }
    }

    public function testAnIntegerSettingAcceptsIntegerStringsAndIntegers(): void
    {
        $_ENV[self::KEY] = '465';
        $this->assertSame(465, EnvProbe::integer(self::KEY));

        $_ENV[self::KEY] = 587;
        $this->assertSame(587, EnvProbe::integer(self::KEY));
    }

    public function testAMissingOrMalformedIntegerSettingIsReportedByNameWithoutItsValue(): void
    {
        foreach ([null, '', 'abc', '5.5', '12abc', 'CANARY-PORT'] as $value) {
            unset($_ENV[self::KEY]);
            if ($value !== null) {
                $_ENV[self::KEY] = $value;
            }
            try {
                EnvProbe::integer(self::KEY);
                $this->fail('A setting that is not an integer must be rejected: ' . var_export($value, true));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString(self::KEY, $e->getMessage());
                $this->assertStringNotContainsString('CANARY-PORT', $e->getMessage());
            }
        }
    }
}
