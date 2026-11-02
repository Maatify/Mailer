<?php

declare(strict_types=1);

/**
 * Verifies that every relative link in the Markdown documents (and llms.txt)
 * of an extracted archive resolves to a file or directory inside that archive,
 * so no shipped document links to a file that the distribution excludes.
 *
 * Usage: php check-markdown-links.php <extracted-archive-root>
 */

$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "Usage: php check-markdown-links.php <extracted-archive-root>\n");
    exit(2);
}
$root = rtrim((string)realpath($root), '/');

$documents = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->isFile() && (str_ends_with($file->getFilename(), '.md') || $file->getFilename() === 'llms.txt')) {
        $documents[] = $file->getPathname();
    }
}
sort($documents);

$broken = [];
foreach ($documents as $document) {
    $text = (string)file_get_contents($document);
    preg_match_all('/\]\(([^)\s]+)\)/', $text, $matches);
    foreach ($matches[1] as $target) {
        if (preg_match('#^([a-z][a-z0-9+.-]*:|\#|//)#i', $target) === 1) {
            continue;
        }
        $path = explode('#', $target, 2)[0];
        if ($path === '') {
            continue;
        }
        $resolved = realpath(dirname($document) . '/' . $path);
        if ($resolved === false || !str_starts_with($resolved, $root)) {
            $broken[] = substr($document, strlen($root) + 1) . ' -> ' . $target;
        }
    }
}

if ($broken !== []) {
    fwrite(STDERR, "Links to files that are not in the archive:\n  " . implode("\n  ", $broken) . "\n");
    exit(1);
}
echo 'Archive link check passed (' . count($documents) . " documents).\n";
