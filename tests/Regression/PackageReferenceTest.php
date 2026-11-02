<?php

namespace Maatify\Mailer\Tests\Regression;

use Maatify\Mailer\Contract\SubjectProviderInterface;
use Maatify\Mailer\Enum\MailSubjectEnum;
use Maatify\Mailer\Service\Mailer;
use Maatify\Mailer\Tests\Support\SourceFiles;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The Package Reference is the canonical contract, so it must list what the code exposes: every
 * public member of the entry point, every public type, every enum case and every setting the
 * sources read.
 */
final class PackageReferenceTest extends TestCase
{
    private string $reference = '';

    protected function setUp(): void
    {
        $this->reference = (string)file_get_contents(__DIR__ . '/../../MAILER_PACKAGE_REFERENCE.md');
        $this->assertNotSame('', $this->reference);
    }

    public function testEveryPublicMemberOfTheEntryPointIsDocumented(): void
    {
        $count = 0;
        foreach ((new ReflectionClass(Mailer::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== Mailer::class) {
                continue;
            }
            $count++;
            $this->assertMatchesRegularExpression('/`(?:static )?' . preg_quote($method->getName(), '/') . '\(/', $this->reference, 'Undocumented public member: ' . $method->getName());
        }
        $this->assertGreaterThanOrEqual(10, $count);
    }

    public function testEveryPublicTypeAndEnumCaseIsDocumented(): void
    {
        foreach (SourceFiles::all() as $file) {
            $relative = substr($file, strlen(dirname(__DIR__, 2) . '/src/'), -4);
            $this->assertStringContainsString('Maatify\\Mailer\\' . str_replace('/', '\\', $relative), $this->reference);
        }
        foreach (MailSubjectEnum::cases() as $case) {
            $this->assertStringContainsString('`' . $case->name . '`', $this->reference);
            $this->assertStringContainsString('`' . $case->value . '`', $this->reference);
            $this->assertStringContainsString($case->defaultSubject(), $this->reference);
        }
        $this->assertStringContainsString('`subject(MailSubjectEnum $message, string $language): ?string`', $this->reference);
        $this->assertTrue((new ReflectionClass(SubjectProviderInterface::class))->hasMethod('subject'));
    }

    public function testEverySettingTheSourcesReadIsDocumented(): void
    {
        $keys = [];
        foreach (SourceFiles::all() as $file) {
            preg_match_all('/\benv(?:Int)?\(\'([A-Z_]+)\'\)/', (string)file_get_contents($file), $matches);
            $keys = array_merge($keys, $matches[1]);
        }
        $keys = array_unique($keys);
        $this->assertGreaterThanOrEqual(10, count($keys), 'The scan found too few settings; it would be vacuous.');
        foreach ($keys as $key) {
            $this->assertStringContainsString('`' . $key . '`', $this->reference, 'Undocumented setting: ' . $key);
        }
    }

    public function testTheReferenceDeclaresExactlyOneSourceTopology(): void
    {
        $this->assertSame(1, preg_match_all('/^Source Topology: /m', $this->reference));
        $this->assertStringContainsString('Source Topology: Single Capability', $this->reference);
    }
}
