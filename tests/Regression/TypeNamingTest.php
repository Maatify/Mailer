<?php

namespace Maatify\Mailer\Tests\Regression;

use Maatify\Mailer\Tests\Support\SourceFiles;
use PHPUnit\Framework\TestCase;

/**
 * Type naming of the library's public API: every interface ends with `Interface`, every enum with
 * `Enum`, and every file is named after the single type it declares.
 */
final class TypeNamingTest extends TestCase
{
    public function testInterfacesAndEnumsCarryTheirSuffixAndFilesMatchTheirType(): void
    {
        $declared = 0;
        foreach (SourceFiles::all() as $file) {
            $code = (string)file_get_contents($file);
            $this->assertSame(1, preg_match_all('/^(?:abstract |final )?(class|interface|enum|trait)\s+(\w+)/m', $code, $matches, PREG_SET_ORDER), basename($file) . ' must declare exactly one type.');
            [, $kind, $name] = $matches[0];
            $declared++;
            $this->assertSame(basename($file, '.php'), $name, 'The file name must match the declared type.');
            if ($kind === 'interface') {
                $this->assertStringEndsWith('Interface', $name);
            }
            if ($kind === 'enum') {
                $this->assertStringEndsWith('Enum', $name);
            }
        }
        $this->assertGreaterThanOrEqual(4, $declared, 'The scan found too few types; it would be vacuous.');
    }
}
