<?php

declare(strict_types=1);

/**
 * Verifies the actually installed PHP patch against the security patch floor of
 * its minor (COMPOSER_PACKAGE_STANDARD §15.1.1 / CI_WORKFLOW_STANDARD §7.1).
 * Fails closed when the version is below the floor or its minor has no floor.
 */

const PHP_PATCH_FLOORS = ['8.4' => '8.4.27', '8.5' => '8.5.12'];

// phpversion() is read at runtime; PHPStan folds PHP_VERSION constants to its configured target.
$version = phpversion();
$minor = implode('.', array_slice(explode('.', $version), 0, 2));
fwrite(STDOUT, 'Installed PHP: ' . $version . ' (PHP_VERSION_ID ' . PHP_VERSION_ID . ")\n");

if (!isset(PHP_PATCH_FLOORS[$minor])) {
    fwrite(STDERR, "No security patch floor is defined for PHP $minor.\n");
    exit(1);
}

if (version_compare($version, PHP_PATCH_FLOORS[$minor], '<')) {
    fwrite(STDERR, 'PHP ' . $version . ' is below the ' . $minor . ' floor ' . PHP_PATCH_FLOORS[$minor] . ".\n");
    exit(1);
}

fwrite(STDOUT, "OK: PHP $minor floor " . PHP_PATCH_FLOORS[$minor] . " satisfied.\n");
