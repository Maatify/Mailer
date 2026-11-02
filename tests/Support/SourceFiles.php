<?php

namespace Maatify\Mailer\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** The PHP files of the library under src/, for the static source guards. */
final class SourceFiles
{
    /** @return list<string> */
    public static function all(): array
    {
        $root = dirname(__DIR__, 2) . '/src';
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        if ($files === []) {
            throw new RuntimeException('No source file was found under src/; a static guard would be vacuous.');
        }
        sort($files);

        return $files;
    }
}
