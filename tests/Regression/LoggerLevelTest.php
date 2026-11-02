<?php

namespace Maatify\Mailer\Tests\Regression;

use Maatify\Logger\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Static guard for Logger::RecordLog() calls in the library sources, plus a
 * runtime check of the call shape against the installed maatify/logger.
 *
 * What it guarantees: no call passes a positional second argument (which lands
 * in the PSR-3 level slot on maatify/logger >= 5.1), and a level given as a
 * string literal is a PSR-3 level. What it does not guarantee: a level coming
 * from a variable or other expression is not evaluated, and the scan is a
 * regular-expression check, not a full PHP parser. The call shape used by the
 * library is exercised against every supported logger release by
 * scripts/verify-logger-matrix.sh.
 */
final class LoggerLevelTest extends TestCase
{
    private const PSR3_LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    public function testNoRecordLogCallPassesAPositionalSecondArgumentOrALiteralNonPsr3Level(): void
    {
        $calls = 0;
        foreach (glob(__DIR__ . '/../../mailer/*.php') ?: [] as $file) {
            $found = 0;
            $this->assertSame([], self::violations((string)file_get_contents($file), $found), basename($file));
            $calls += $found;
        }
        $this->assertGreaterThan(0, $calls, 'No Logger::RecordLog() call was found; the check would be vacuous.');
    }

    public function testDetectsPositionalSecondArgument(): void
    {
        $found = 0;
        $this->assertNotSame([], self::violations('<?php Logger::RecordLog($e, "Maatify\\Mailer\\Mailer");', $found));
        $this->assertSame(1, $found);
    }

    public function testDetectsNonPsr3NamedLevel(): void
    {
        $found = 0;
        $this->assertNotSame([], self::violations('<?php Logger::RecordLog(message: $e, level: "mailer_calls");', $found));
        $this->assertSame([], self::violations('<?php Logger::RecordLog(message: $e, level: "error", logFile: "x"); Logger::RecordLog($e);', $found));
    }

    public function testTheLibrariesCallShapeWorksWithTheInstalledLogger(): void
    {
        Logger::RecordLog(message: new \Exception('logger compatibility check'), logFile: 'Maatify\\Mailer\\Mailer');
        Logger::RecordLog(message: [['value' => 400], 'https://example.test', ['subject' => 'x']], logFile: 'Maatify\\Mailer\\MailerSender');
        $this->addToAssertionCount(1);
    }

    /** @return list<string> */
    private static function violations(string $code, int &$found): array
    {
        $code = preg_replace('~//[^\n]*|/\*.*?\*/~s', '', $code) ?? $code;
        preg_match_all('/Logger::RecordLog\((.*?)\);/s', $code, $matches);
        $found = count($matches[1]);
        $violations = [];
        foreach ($matches[1] as $arguments) {
            $arguments = trim($arguments);
            $named = (bool)preg_match('/^message\s*:/', $arguments);
            $bareMessageOnly = !str_contains($arguments, ',') || self::topLevelCommaCount($arguments) === 0;
            if (!$named && !$bareMessageOnly) {
                $violations[] = 'positional extra argument: ' . $arguments;
                continue;
            }
            if (preg_match('/\blevel\s*:\s*([\'"])([^\'"]*)\1/', $arguments, $level) && !in_array(strtolower($level[2]), self::PSR3_LEVELS, true)) {
                $violations[] = 'non-PSR-3 level: ' . $level[2];
            }
        }
        return $violations;
    }

    private static function topLevelCommaCount(string $arguments): int
    {
        $depth = 0;
        $count = 0;
        $quote = '';
        foreach (str_split($arguments) as $char) {
            if ($quote !== '') {
                $quote = $char === $quote ? '' : $quote;
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $count++;
            }
        }
        return $count;
    }
}
