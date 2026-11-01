<?php

declare(strict_types=1);

use Composer\Semver\Semver;

/**
 * Constraint-boundary verification of composer.json require.php using Composer's
 * own semantics (CI_WORKFLOW_STANDARD §7.1). For every supported minor the floor
 * must be accepted and the patch below it rejected; the first patch of the next
 * minor without a floor must be rejected. Nothing below a floor is executed.
 */

require __DIR__ . '/../../vendor/autoload.php';

const SUPPORTED_FLOORS = ['8.4' => '8.4.27', '8.5' => '8.5.12'];
const FIRST_MINOR_WITHOUT_FLOOR = '8.6.0';

$composer = json_decode((string)file_get_contents(__DIR__ . '/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
$require = is_array($composer) ? ($composer['require'] ?? null) : null;
$constraint = is_array($require) ? ($require['php'] ?? null) : null;
if (!is_string($constraint)) {
    fwrite(STDERR, "composer.json has no require.php.\n");
    exit(1);
}

$failures = [];
foreach (SUPPORTED_FLOORS as $minor => $floor) {
    if (!Semver::satisfies($floor, $constraint)) {
        $failures[] = "floor $floor must be accepted by '$constraint'";
    }
    [$major, $min, $patch] = array_map('intval', explode('.', $floor));
    $below = "$major.$min." . ($patch - 1);
    if (Semver::satisfies($below, $constraint)) {
        $failures[] = "$below (below the $minor floor) must be rejected by '$constraint'";
    }
}
if (Semver::satisfies(FIRST_MINOR_WITHOUT_FLOOR, $constraint)) {
    $failures[] = FIRST_MINOR_WITHOUT_FLOOR . " (minor without a floor) must be rejected by '$constraint'";
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "OK: require.php '$constraint' satisfies the patch-floor boundaries.\n");
