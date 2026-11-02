<?php

// Sends the four messages whose subject the library chooses, each in its own call, and prints
// the subjects it selected and the calls the provider received as JSON. SMTP points at a closed
// loopback port, so nothing is delivered.
// Usage: php subject_runner.php <none|legacy|provider|partial|language>

use Maatify\Mailer\Adapter\MailerSender;
use Maatify\Mailer\Service\Mailer;
use Maatify\Mailer\Tests\Support\RecordingSubjectProvider;

require __DIR__ . '/../../vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
$mode = is_array($arguments) && isset($arguments[1]) && is_string($arguments[1]) ? $arguments[1] : '';
if (!in_array($mode, ['none', 'legacy', 'provider', 'partial', 'language'], true)) {
    fwrite(STDERR, "Unknown mode\n");
    exit(2);
}

if ($mode === 'legacy') {
    // The retired host configuration class: it must never be consulted.
    eval('namespace App\Assist\Config; final class MailerConfig {
        public static function obj(string $language = ""): self { return new self(); }
        public function subjectConfirmCode(): string { return "LEGACY"; }
        public function subjectConfirmMail(): string { return "LEGACY"; }
        public function subjectResetPass(): string { return "LEGACY"; }
        public function subjectTempPass(): string { return "LEGACY"; }
    }');
}

$provider = in_array($mode, ['provider', 'partial', 'language'], true) ? new RecordingSubjectProvider($mode === 'partial') : null;

$templates = sys_get_temp_dir() . '/mailer-subjects-' . bin2hex(random_bytes(4));
$directories = [$templates, $templates . '/ar'];
foreach ($directories as $directory) {
    mkdir($directory);
    foreach (['__header', '__footer', 'confirm', 'confirm_link', 'forget_password', 'temp_pass'] as $name) {
        file_put_contents($directory . '/' . $name . '.html.twig', 'body ' . $name);
        file_put_contents($directory . '/' . $name . '.text.twig', 'body ' . $name);
    }
}
$_ENV = array_merge($_ENV, [
    'EMAIL_SITE_URL' => 'https://site.example/', 'EMAIL_SITE_LOGO' => 'logo.png', 'EMAIL_SITE_NAME' => 'site',
    'SITE_URL' => 'https://site.example',
    'SMTP_HOST' => '127.0.0.1', 'SMTP_PORT' => '1', 'SMTP_USER' => 'user', 'SMTP_PASS' => 'password',
    'SMTP_FROM_MAIL' => 'from@example.test', 'SMTP_FROM_NAME' => 'Sender', 'SMTP_REPLY_MAIL' => 'reply@example.test',
]);

$mailer = new Mailer('person@example.test', 'Person', $mode === 'language' ? 'ar' : '', $templates, $provider);
$read = static function () use ($mailer): string {
    $subject = (new ReflectionProperty(MailerSender::class, 'subject'))->getValue($mailer);

    return is_string($subject) ? $subject : '[not a string]';
};
$subjects = [];
$mailer->ConfirmCode('123456');
$subjects['confirm_code'] = $read();
$mailer->ConfirmDashboardLink('token');
$subjects['confirm_mail'] = $read();
$mailer->ResetPassDashboardLink('token');
$subjects['reset_password'] = $read();
$mailer->TempPassword('secret');
$subjects['temporary_password'] = $read();

echo json_encode(['subjects' => $subjects, 'calls' => $provider?->calls() ?? []], JSON_THROW_ON_ERROR);

foreach ($directories as $directory) {
    array_map('unlink', glob($directory . '/*') ?: []);
}
rmdir($templates . '/ar');
rmdir($templates);
