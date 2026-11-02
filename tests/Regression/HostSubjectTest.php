<?php

namespace Maatify\Mailer\Tests\Regression;

use PHPUnit\Framework\TestCase;

/**
 * The optional host class App\Assist\Config\MailerConfig customizes message subjects only when it
 * provides obj() and the requested subject method; otherwise the library's default applies, without
 * calling into the host class. Each case runs in its own process (tests/Support/host_subject_runner.php)
 * because the host class is declared globally.
 */
final class HostSubjectTest extends TestCase
{
    public function testWithoutAHostClassTheDefaultSubjectIsUsed(): void
    {
        $this->assertSame('Confirm Code', $this->subject('none'));
    }

    public function testAHostClassWithNeitherObjNorTheSubjectMethodFallsBackToTheDefault(): void
    {
        $this->assertSame('Confirm Code', $this->subject('bare'));
    }

    public function testAHostClassWithoutTheSubjectMethodFallsBackToTheDefault(): void
    {
        $this->assertSame('Confirm Code', $this->subject('no-method'));
    }

    public function testAHostClassWithoutObjFallsBackToTheDefault(): void
    {
        $this->assertSame('Confirm Code', $this->subject('no-obj'));
    }

    public function testAHostSubjectIsUsedWhenTheHostClassProvidesIt(): void
    {
        $this->assertSame('HOST SUBJECT', $this->subject('host'));
    }

    public function testMethodNamesAreMatchedCaseInsensitively(): void
    {
        $this->assertSame('HOST SUBJECT', $this->subject('host-case'));
    }

    public function testAnEmptyHostSubjectFallsBackToTheDefault(): void
    {
        $this->assertSame('Confirm Code', $this->subject('empty'));
    }

    private function subject(string $mode): string
    {
        return trim((string)shell_exec(sprintf(
            '%s -d display_errors=0 %s %s 2>/dev/null',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/../Support/host_subject_runner.php'),
            escapeshellarg($mode)
        )));
    }
}
