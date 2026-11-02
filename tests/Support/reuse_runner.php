<?php

// Reuses one Mailer object across calls against the local SMTP stand-in and prints the results
// of each call as JSON. Templates come from tests/Fixtures/templates; the `after-failure`
// scenario removes the message_admin templates so the first message of AdminMessage() fails.
// Usage: php reuse_runner.php <smtp-port> <after-success|after-failure|reinitiate>

use Maatify\Mailer\Service\Mailer;

require __DIR__ . '/../../vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
if (!is_array($arguments) || count($arguments) !== 3) {
    fwrite(STDERR, "Usage: php reuse_runner.php <smtp-port> <after-success|after-failure|reinitiate>\n");
    exit(2);
}
[$script, $port, $scenario] = array_map(static fn (mixed $value): string => is_scalar($value) ? (string)$value : '', $arguments);
if (!in_array($scenario, ['after-success', 'after-failure', 'reinitiate'], true)) {
    fwrite(STDERR, "Unknown scenario\n");
    exit(2);
}
$_ENV = array_merge($_ENV, [
    'SMTP_HOST' => '127.0.0.1', 'SMTP_PORT' => $port, 'SMTP_USER' => 'user', 'SMTP_PASS' => 'password',
    'SMTP_FROM_MAIL' => 'office@example.test', 'SMTP_FROM_NAME' => 'Site Office',
    'EMAIL_SITE_URL' => 'https://site.example/', 'EMAIL_SITE_LOGO' => 'logo.png', 'EMAIL_SITE_NAME' => 'site',
]);

$templates = __DIR__ . '/../Fixtures/templates';
$copy = '';
if ($scenario === 'after-failure') {
    $copy = sys_get_temp_dir() . '/mailer-reuse-' . bin2hex(random_bytes(4));
    mkdir($copy);
    foreach (glob($templates . '/*') ?: [] as $file) {
        if (!str_starts_with(basename($file), 'message_admin')) {
            copy($file, $copy . '/' . basename($file));
        }
    }
    $templates = $copy;
}

$mailer = new Mailer('visitor@example.test', 'Visitor Name', '', $templates);
$results = ['admin' => $mailer->AdminMessage('REUSE-BODY', 'REUSE-SUBJECT')];
if ($scenario === 'reinitiate') {
    $mailer->reInitiateSender('other@example.test', 'Other');
}
$results['follow_up'] = $mailer->Message('REUSE-FOLLOW-UP', 'REUSE-FOLLOW-SUBJECT');
echo json_encode($results, JSON_THROW_ON_ERROR);

if ($copy !== '') {
    array_map('unlink', glob($copy . '/*') ?: []);
    rmdir($copy);
}
