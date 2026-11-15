<?php
declare(strict_types=1);

require_once __DIR__ . '/private/auth.php';
require_once __DIR__ . '/private/database.php';
require_once __DIR__ . '/private/cms.php';
require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function inquiry_response(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') inquiry_response(405, ['ok'=>false,'message'=>'Use the form to submit your inquiry.']);
start_secure_session();
if (!empty($_POST['_company'] ?? '')) inquiry_response(200, ['ok'=>true,'message'=>'Thank you. Your inquiry was submitted.']);
$now = time();
if (!empty($_SESSION['inquiry_last_sent']) && $now - (int)$_SESSION['inquiry_last_sent'] < 10) inquiry_response(429, ['ok'=>false,'message'=>'Please wait a few seconds before submitting again.']);
$email = trim((string)($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) inquiry_response(422, ['ok'=>false,'message'=>'Enter a valid email address.']);
$fields = [];
foreach ($_POST as $key => $value) {
    if (!is_string($value) || in_array($key, ['captcha','_company'], true)) continue;
    $value = trim($value);
    if (strlen($value) > 10000) inquiry_response(422, ['ok'=>false,'message'=>'A form field is too long.']);
    $fields[preg_replace('/[^a-zA-Z0-9 _-]/', '', (string)$key)] = $value;
}
if (count($fields) < 2) inquiry_response(422, ['ok'=>false,'message'=>'Please complete the form and try again.']);
$attachments = [];
if (isset($_FILES['resume']) && $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['resume'];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) inquiry_response(422, ['ok'=>false,'message'=>'Resume must be smaller than 2 MB.']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['application/pdf'=>'pdf','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx'];
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$mime]) || $allowed[$mime] !== $ext) inquiry_response(422, ['ok'=>false,'message'=>'Attach a valid PDF, DOC, or DOCX resume.']);
    $attachments[] = ['name'=>basename((string)$file['name']),'type'=>$mime,'data'=>file_get_contents($file['tmp_name'])];
}
try {
    $pdo = database();
    $settings = cms_site_settings($pdo);
    $recipient = trim((string)($settings['submission_email'] ?? 'etakarlapallipratap@gmail.com'));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Submission email is not configured.');
    $subjectLabel = trim((string)($_POST['inquiry_type'] ?? 'Website inquiry'));
    $subjectLabel = preg_replace('/[\r\n]+/', ' ', $subjectLabel) ?: 'Website inquiry';
    $subject = 'Website submission: ' . mb_substr($subjectLabel, 0, 120);
    $body = "New website submission\n\n";
    foreach ($fields as $label=>$value) $body .= $label . ': ' . $value . "\n\n";
    $smtp = smtp_config();
    if ($smtp['username'] === '' || $smtp['password'] === '') throw new RuntimeException('SMTP credentials are missing from server configuration.');
    if (!in_array($smtp['encryption'], ['tls','ssl'], true)) throw new RuntimeException('SMTP encryption must be tls or ssl.');
    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $smtp['host'];
    $mailer->SMTPAuth = true;
    $mailer->Username = $smtp['username'];
    $mailer->Password = $smtp['password'];
    $mailer->Port = $smtp['port'];
    $mailer->SMTPSecure = $smtp['encryption'] === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->CharSet = PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
    $from = $smtp['from'] !== '' ? $smtp['from'] : $smtp['username'];
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('SMTP_FROM must be a valid email address.');
    $mailer->setFrom($from, 'Extek Profiles Website');
    $mailer->addAddress($recipient);
    $mailer->addReplyTo($email);
    $mailer->Subject = $subject;
    $mailer->Body = $body;
    foreach ($attachments as $attachment) $mailer->addStringAttachment($attachment['data'], $attachment['name'], PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64, $attachment['type']);
    $mailer->send();
    $_SESSION['inquiry_last_sent'] = $now;
    inquiry_response(200, ['ok'=>true,'message'=>'Thank you. Your submission was emailed successfully.']);
} catch (Throwable $exception) {
    error_log('Website inquiry email failed: ' . $exception->getMessage());
    inquiry_response(503, ['ok'=>false,'message'=>'We could not send your submission right now. Please contact us by phone or email.']);
}
