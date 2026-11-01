<?php

namespace Maatify\Mailer\Tests\Support;

use Maatify\Logger\Logger;
use ReflectionClass;

/** Captures the log text written while a test runs, for every supported maatify/logger version. */
trait CapturesLogs
{
    /** @var array<string, int> */
    private array $sizesBefore = [];

    private function startLogCapture(): void
    {
        $this->sizesBefore = $this->logSizes();
    }

    /**
     * maatify/logger resolves its log directory relative to its own location
     * (a different depth per version), so every candidate directory is watched.
     *
     * @return array<string, int>
     */
    private function logSizes(): array
    {
        $loggerFile = (string)(new ReflectionClass(Logger::class))->getFileName();
        $roots = [dirname($loggerFile, 3) . '/logs', dirname($loggerFile, 4) . '/logs', dirname($loggerFile, 5) . '/logs', dirname(__DIR__, 2) . '/logs'];
        $sizes = [];
        foreach (array_unique($roots) as $root) {
            if (!is_dir($root)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) {
                    $sizes[$file->getPathname()] = $file->getSize();
                }
            }
        }
        return $sizes;
    }

    /** Log text appended by this test only (new files in full, grown files from the old size). */
    private function loggedText(): string
    {
        clearstatcache();
        $text = '';
        foreach ($this->logSizes() as $path => $size) {
            $before = $this->sizesBefore[$path] ?? 0;
            if ($size > $before) {
                $text .= (string)file_get_contents($path, false, null, $before);
            }
        }
        $this->assertNotSame('', $text, 'No log entry was written; the check would be vacuous.');
        return $text;
    }
}
