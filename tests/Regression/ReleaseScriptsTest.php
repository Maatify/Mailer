<?php

namespace Maatify\Mailer\Tests\Regression;

use PHPUnit\Framework\TestCase;

/**
 * The release verification scripts must be strict where qualification depends on them: a
 * qualification run refuses every Composer platform override, and the published-package comparison
 * detects a swapped manifest, a missing file, an extra file and a changed file.
 */
final class ReleaseScriptsTest extends TestCase
{
    private string $root = '';
    private string $work = '';

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->work = sys_get_temp_dir() . '/mailer-release-' . bin2hex(random_bytes(4));
        mkdir($this->work);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->work));
    }

    public function testAQualificationRunRefusesEveryComposerPlatformOverride(): void
    {
        foreach (['HARNESS_COMPOSER_ARGS=--ignore-platform-req=php', 'COMPOSER_IGNORE_PLATFORM_REQS=php', 'COMPOSER_IGNORE_PLATFORM_REQ=php', 'COMPOSER_IGNORE_PLATFORM_REQS='] as $assignment) {
            [$status, $output] = $this->shell('env ' . $assignment . ' ' . escapeshellarg($this->root . '/scripts/release/verify-artifact.sh') . ' HEAD');
            $this->assertSame(2, $status, $assignment . ' must be refused: ' . $output);
            $this->assertStringContainsString('Refusing to qualify', $output);
        }
    }

    public function testThePublishedCheckRequiresAFullLowercaseSha(): void
    {
        [$status, $output] = $this->shell(escapeshellarg($this->root . '/scripts/release/verify-published.sh') . ' 6.0.0 abc123');
        $this->assertSame(2, $status, $output);
        $this->assertStringContainsString('40-character', $output);
    }

    public function testAnInstalledPackageIdenticalToTheQualifiedArchivePasses(): void
    {
        $installed = $this->installedCopy();
        [$status, $output] = $this->compare($installed);
        $this->assertSame(0, $status, $output);
    }

    public function testASwappedManifestIsDetected(): void
    {
        $installed = $this->installedCopy();
        file_put_contents($installed . '/composer.json', '{"name":"maatify/mailer","description":"swapped"}');
        [$status, $output] = $this->compare($installed);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('composer.json differs', $output);
    }

    public function testAMissingRequiredTemplateIsDetected(): void
    {
        $installed = $this->installedCopy();
        $this->assertFileExists($installed . '/examples/templates/email/__footer.html.twig');
        unlink($installed . '/examples/templates/email/__footer.html.twig');
        [$status, $output] = $this->compare($installed);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('__footer.html.twig', $output);
    }

    public function testAnExtraDevelopmentFileIsDetected(): void
    {
        $installed = $this->installedCopy();
        mkdir($installed . '/tests');
        file_put_contents($installed . '/tests/Leak.php', '<?php');
        [$status, $output] = $this->compare($installed);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('tests', $output);
    }

    public function testAChangedFileIsDetected(): void
    {
        $installed = $this->installedCopy();
        file_put_contents($installed . '/src/Service/Mailer.php', "\n// tampered\n", FILE_APPEND);
        [$status, $output] = $this->compare($installed);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('Mailer.php', $output);
    }

    private function installedCopy(): string
    {
        [$status] = $this->shell('git -C ' . escapeshellarg($this->root) . ' rev-parse --verify HEAD');
        if ($status !== 0) {
            $this->markTestSkipped('The package comparison needs the git history of the repository.');
        }
        $installed = $this->work . '/installed';
        mkdir($installed);
        [$status, $output] = $this->shell('git -C ' . escapeshellarg($this->root) . ' archive HEAD | tar -x -C ' . escapeshellarg($installed));
        $this->assertSame(0, $status, $output);

        return $installed;
    }

    /** @return array{int, string} */
    private function compare(string $installed): array
    {
        return $this->shell(escapeshellarg($this->root . '/scripts/release/compare-package.sh') . ' ' . escapeshellarg($installed) . ' HEAD');
    }

    /** @return array{int, string} */
    private function shell(string $command): array
    {
        $output = [];
        $status = 0;
        exec($command . ' 2>&1', $output, $status);

        return [$status, implode("\n", $output)];
    }
}
