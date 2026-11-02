<?php

declare(strict_types=1);

use Maatify\Mailer\Mailer;

/**
 * Realistic consumer workflow through the production autoload and the public API: a Host sends a
 * plain message, forwards a visitor's message to the site and is told when the SMTP server
 * refuses a recipient. The SMTP server is the harness's local stand-in; no real mail is sent.
 *
 * Usage: php workflow.php <smtp-port>
 */

require __DIR__ . '/vendor/autoload.php';

$port = $argv[1] ?? null;
if (!is_string($port) || $port === '') {
    fwrite(STDERR, "Usage: php workflow.php <smtp-port>\n");
    exit(2);
}

// The harness settings always replace anything the host environment already defines.
$_ENV = array_merge($_ENV, [
    'SMTP_HOST' => '127.0.0.1', 'SMTP_PORT' => $port, 'SMTP_USER' => 'consumer', 'SMTP_PASS' => 'consumer-password',
    'SMTP_FROM_MAIL' => 'office@example.test', 'SMTP_FROM_NAME' => 'Site Office',
    'EMAIL_SITE_URL' => 'https://site.example/', 'EMAIL_SITE_LOGO' => 'logo.png', 'EMAIL_SITE_NAME' => 'site',
]);
$templates = __DIR__ . '/templates/email';

$plain = new Mailer('person@example.test', 'Person', '', $templates);
$visitor = new Mailer('visitor@example.test', 'Visitor Name', '', $templates);
$refused = new Mailer('reject@example.test', 'Refused', '', $templates);

$results = [
    'message' => $plain->Message('CONSUMER-BODY', 'CONSUMER-SUBJECT'),
    'admin_message' => $visitor->AdminMessage('CONSUMER-VISITOR-BODY', 'CONSUMER-VISITOR-SUBJECT'),
    'refused_recipient' => $refused->Message('CONSUMER-REFUSED-BODY', 'CONSUMER-REFUSED-SUBJECT'),
];

$packageFile = (string)(new ReflectionClass(Mailer::class))->getFileName();
$vendorRoot = (string)realpath(__DIR__ . '/vendor');
$psr4Map = (string)file_get_contents($vendorRoot . '/composer/autoload_psr4.php');

echo json_encode([
    'loaded_from_vendor' => str_starts_with((string)realpath($packageFile), $vendorRoot . '/maatify/mailer/'),
    'test_autoload_absent' => !str_contains($psr4Map, 'Maatify\\\\Mailer\\\\Tests\\\\')
        && !class_exists('Maatify\\Mailer\\Tests\\Support\\RunnerSender'),
    'results' => $results,
], JSON_THROW_ON_ERROR);
