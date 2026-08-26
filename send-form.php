<?php
/**
 * send-form.php
 * Přijme data z kontaktního formuláře na webu BesaWeb a odešle je e-mailem
 * přes SMTP schránku nastavenou v config.php.
 *
 * Očekává POST požadavek (multipart/form-data nebo application/x-www-form-urlencoded)
 * s poli: name, business, businessType, phone, email, social, message, website (honeypot).
 *
 * Vrací JSON: {"success": true, "message": "..."} nebo {"success": false, "message": "..."}
 */

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message): void {
    http_response_code($success ? 200 : 400);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (!file_exists(__DIR__ . '/config.php')) {
    respond(false, 'Server is not configured yet (missing config.php).');
}
$config = require __DIR__ . '/config.php';

// Honeypot proti spam robotům — skryté pole "website" v HTML formuláři.
// Skuteční návštěvníci ho nikdy nevyplní, roboti often ano.
if (!empty($_POST['website'])) {
    // Robotovi tváříme úspěch, ať se nenaučí pole přeskakovat, ale zprávu neposíláme.
    respond(true, 'OK');
}

/** Ořízne, odstraní HTML tagy a zabrání vložení dalších hlaviček (header injection). */
function cleanLine(string $value): string {
    $value = trim(strip_tags($value));
    return str_replace(["\r", "\n"], ' ', $value);
}

$name         = cleanLine($_POST['name'] ?? '');
$business     = cleanLine($_POST['business'] ?? '');
$businessType = cleanLine($_POST['businessType'] ?? '');
$phone        = cleanLine($_POST['phone'] ?? '');
$email        = cleanLine($_POST['email'] ?? '');
$social       = cleanLine($_POST['social'] ?? '');
$message      = trim(strip_tags($_POST['message'] ?? '')); // víceřádkové pole, zalomení tu vadit nemůže

// Povinná pole
if ($name === '' || $business === '' || $businessType === '' || $phone === '' || $email === '') {
    respond(false, 'Please fill in all required fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Invalid email address.');
}

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp_username'];
    $mail->Password   = $config['smtp_password'];
    $mail->SMTPSecure = $config['smtp_secure'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $config['smtp_port'];
    $mail->CharSet    = 'UTF-8';

    // Sender / recipient
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['to_email'], $config['to_name']);
    // Odpověď z e-mailového klienta půjde přímo tazateli, který formulář odeslal
    $mail->addReplyTo($email, $name);

    // Content
    $mail->isHTML(true);
    $mail->Subject = "Nová poptávka z besaweb.al — $business";

    $bodyRows = [
        'Jméno'                       => $name,
        'Firma / podnik'              => $business,
        'Typ podnikání'               => $businessType,
        'Telefon / WhatsApp'          => $phone,
        'E-mail'                      => $email,
        'Aktuální web/sociální sítě'  => $social !== '' ? $social : '—',
        'Zpráva'                      => $message !== '' ? nl2br(htmlspecialchars($message)) : '—',
    ];

    $html = '<h2 style="font-family:sans-serif;color:#059669;">Nová poptávka o bezplatný návrh z besaweb.al</h2>';
    $html .= '<table style="font-family:sans-serif;font-size:14px;border-collapse:collapse;">';
    foreach ($bodyRows as $label => $value) {
        $html .= '<tr>'
            . '<td style="padding:6px 12px 6px 0;font-weight:bold;vertical-align:top;">' . htmlspecialchars($label) . '</td>'
            . '<td style="padding:6px 0;">' . ($label === 'Zpráva' ? $value : htmlspecialchars($value)) . '</td>'
            . '</tr>';
    }
    $html .= '</table>';
    $html .= '<p style="font-family:sans-serif;font-size:12px;color:#888;margin-top:16px;">Odesláno automaticky z formuláře na besaweb.al ' . date('d.m.Y H:i') . '</p>';

    $mail->Body    = $html;
    $mail->AltBody = implode("\n", array_map(
        fn($label, $value) => "$label: " . strip_tags($value),
        array_keys($bodyRows),
        $bodyRows
    ));

    $mail->send();
    respond(true, 'Message sent successfully.');
} catch (PHPMailerException $e) {
    error_log('BesaWeb contact form mail error: ' . $mail->ErrorInfo);
    respond(false, 'Failed to send message. Please try again later.');
}
