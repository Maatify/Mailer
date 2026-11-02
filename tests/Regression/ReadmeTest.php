<?php

namespace Maatify\Mailer\Tests\Regression;

use Maatify\Mailer\Tests\Support\SourceFiles;
use PHPUnit\Framework\TestCase;

/**
 * The README is the release artifact's front page, so its facts must agree with the files that own
 * them: the release target with the CHANGELOG, the PHP requirement with composer.json, the public
 * types with the sources, and the retired MailerConfig only as an upgrade note.
 */
final class ReadmeTest extends TestCase
{
    private string $readme = '';

    protected function setUp(): void
    {
        $this->readme = (string)file_get_contents(__DIR__ . '/../../README.md');
        $this->assertNotSame('', $this->readme);
    }

    public function testTheReleaseTargetAndInstallCommandMatchTheChangelog(): void
    {
        $changelog = (string)file_get_contents(__DIR__ . '/../../CHANGELOG.md');
        if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $match) !== 1) {
            $this->fail('The CHANGELOG has no release target section.');
        }
        $target = $match[1];

        $this->assertStringContainsString('Release%20Target-' . $target . '-blue', $this->readme);
        $this->assertStringContainsString('composer require maatify/mailer:' . $target, $this->readme);
        $this->assertStringContainsString('as of release qualification', strtolower($this->readme));
    }

    public function testThePhpRequirementMatchesComposerJson(): void
    {
        $composer = json_decode((string)file_get_contents(__DIR__ . '/../../composer.json'), true);
        $this->assertIsArray($composer);
        $require = $composer['require'] ?? null;
        $this->assertIsArray($require);
        $php = $require['php'] ?? null;
        $this->assertIsString($php);
        $this->assertStringContainsString('PHP `' . $php . '`', $this->readme);
    }

    public function testEveryPublicTypeIsListed(): void
    {
        foreach (SourceFiles::all() as $file) {
            $relative = substr($file, strlen(dirname(__DIR__, 2) . '/src/'), -4);
            $this->assertStringContainsString('`Maatify\\Mailer\\' . str_replace('/', '\\', $relative) . '`', $this->readme);
        }
    }

    public function testTheRetiredHostClassAppearsOnlyInTheUpgradeNotes(): void
    {
        $this->assertGreaterThan(0, substr_count($this->readme, 'MailerConfig'));
        $upgrade = (int)strpos($this->readme, "## Upgrade Notes\n");
        $this->assertGreaterThan(0, $upgrade);
        $this->assertSame(0, substr_count(substr($this->readme, 0, $upgrade), 'MailerConfig'));
    }

    public function testTheHeaderStyleAndFooterFollowThePresentationRules(): void
    {
        $this->assertStringNotContainsString('for-the-badge', $this->readme);
        $this->assertStringEndsWith("---\n\n<div align=\"center\">\n\n[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)\n\n</div>\n", $this->readme);
        $this->assertStringContainsString("Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>\nBackend Lead & Technical Architect<br>\n[https://www.maatify.dev](https://www.maatify.dev)", $this->readme);
    }
}
