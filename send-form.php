<?php
/**
 * BesaWeb — kontaktní formulář (zpracování a odeslání e-mailem)
 *
 * Umístěte tento soubor na stejný hosting jako index.html, do stejné
 * složky (např. /besaweb.al/send-form.php). Formulář na webu na něj
 * odkazuje jako na relativní cestu "send-form.php".
 */

// ---- Nastavení ----------------------------------------------------
$recipient   = 'info@besaweb.al';   // kam se poptávky posílají
// Odesílací adresa MUSÍ být existující schránka na tomto hostingu - jinak
// mnoho serverů (vč. GigaServer) poštu tiše zahodí i když mail() vrátí "úspěch".
$fromAddress = 'info@besaweb.al';
// ---------------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message): void {
    http_response_code($success ? 200 : 400);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

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

$subject = "Nová poptávka z besaweb.al — $business";

$body  = "Nová poptávka o bezplatný návrh z besaweb.al\n\n";
$body .= "Jméno: $name\n";
$body .= "Firma / podnik: $business\n";
$body .= "Typ podnikání: $businessType\n";
$body .= "Telefon / WhatsApp: $phone\n";
$body .= "E-mail: $email\n";
$body .= 'Aktuální web/sociální sítě: ' . ($social !== '' ? $social : '—') . "\n\n";
$body .= "Zpráva:\n" . ($message !== '' ? $message : '—') . "\n";

$headers   = [];
$headers[] = "From: BesaWeb Website <$fromAddress>";
$headers[] = "Reply-To: $name <$email>"; // odpověď půjde přímo tazateli
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'X-Mailer: PHP/' . phpversion();

// Envelope-sender (5. parametr) nastavíme na stejnou existující schránku -
// některé servery bez tohoto parametru odesílatele odvodí jinak a poštu odmítnou.
$envelopeSender = '-f' . $fromAddress;

$sent = mail($recipient, $subject, $body, implode("\r\n", $headers), $envelopeSender);

if ($sent) {
    respond(true, 'Message sent successfully.');
}

respond(false, 'Failed to send message. Please try again later.');
