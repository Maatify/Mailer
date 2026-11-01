<?php

// Calls Mailer::ConfirmCode() in its own process with a chosen host MailerConfig and prints the
// subject the library selected. SMTP points at a closed port, so nothing is delivered.
// Usage: php host_subject_runner.php <none|bare|no-method|no-obj|host|host-case|empty>

use Maatify\Mailer\Mailer;
use Maatify\Mailer\MailerSender;

require __DIR__ . '/../../vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
$mode = is_array($arguments) && isset($arguments[1]) && is_string($arguments[1]) ? $arguments[1] : '';

$definitions = [
    'none' => null,
    'bare' => '',
    'no-method' => 'public static function obj(): self { return new self(); }',
    'no-obj' => 'public function subjectConfirmCode(): string { return "HOST SUBJECT"; }',
    'host' => 'public static function obj(): self { return new self(); } public function subjectConfirmCode(): string { return "HOST SUBJECT"; }',
    'host-case' => 'public static function obj(): self { return new self(); } public function SubjectConfirmCODE(): string { return "HOST SUBJECT"; }',
    'empty' => 'public static function obj(): self { return new self(); } public function subjectConfirmCode(): string { return ""; }',
];
if (!array_key_exists($mode, $definitions)) {
    fwrite(STDERR, "Unknown mode\n");
    exit(2);
}
if ($definitions[$mode] !== null) {
    eval('namespace App\Assist\Config; final class MailerConfig { ' . $definitions[$mode] . ' }');
}

$templates = sys_get_temp_dir() . '/mailer-host-subject-' . bin2hex(random_bytes(4));
mkdir($templates);
foreach (['__header.html.twig', 'confirm.html.twig', '__footer.html.twig', 'confirm.text.twig'] as $name) {
    file_put_contents($templates . '/' . $name, 'body ' . $name);
}
$_ENV += [
    'EMAIL_SITE_URL' => 'https://site.example/', 'EMAIL_SITE_LOGO' => 'logo.png', 'EMAIL_SITE_NAME' => 'site',
    'SMTP_HOST' => '127.0.0.1', 'SMTP_PORT' => '1', 'SMTP_USER' => 'user', 'SMTP_PASS' => 'password',
    'SMTP_FROM_MAIL' => 'from@example.test', 'SMTP_FROM_NAME' => 'Sender', 'SMTP_REPLY_MAIL' => 'reply@example.test',
];

$mailer = new Mailer('person@example.test', 'Person', '', $templates);
$mailer->ConfirmCode('123456');
$subject = (new ReflectionProperty(MailerSender::class, 'subject'))->getValue($mailer);
echo is_string($subject) ? $subject : '[not a string]';

array_map('unlink', glob($templates . '/*') ?: []);
rmdir($templates);
