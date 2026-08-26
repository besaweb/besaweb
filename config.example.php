<?php
/**
 * Konfigurace odesílání e-mailů pro kontaktní formulář BesaWeb.
 *
 * DŮLEŽITÉ:
 * 1. Zkopírujte tento soubor na serveru jako "config.php" (stejná složka jako send-form.php).
 * 2. Doplňte skutečné heslo ke schránce info@besaweb.al.
 * 3. Soubor "config.php" NIKDY necommitujte do gitu (je v .gitignore) ani ho neposílejte
 *    nikam přes chat/e-mail - zůstává jen fyzicky na serveru.
 */

return [
    // --- SMTP přístup ke schránce, ZE KTERÉ se bude odesílat ---
    'smtp_host'     => 'mail.gigaserver.cz',
    'smtp_port'     => 465,              // 465 = SSL/TLS, 587 = STARTTLS
    'smtp_secure'   => 'ssl',            // 'ssl' pro port 465, 'tls' pro port 587
    'smtp_username' => 'info@besaweb.al', // přihlašovací jméno schránky (celá e-mailová adresa)
    'smtp_password' => 'ZDE_DOPLNIT_HESLO_KE_SCHRANCE',

    // --- Odesílatel (zobrazí se jako "od koho" e-mail přišel) ---
    'from_email' => 'info@besaweb.al',
    'from_name'  => 'BesaWeb Website',

    // --- Příjemce (kam poptávky chodí) ---
    'to_email' => 'info@besaweb.al',
    'to_name'  => 'BesaWeb',
];
