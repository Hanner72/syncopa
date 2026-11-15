<?php
// api/shop_bestellung.php – nimmt eine Bestellung einer Vereins-Installation entgegen (Server-zu-Server).
// Nur auf dem Katalog-Server. Zugriff mit dem gemeinsamen Schlüssel SHOP_API_KEY.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!SHOP_IST_ZENTRALE) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Diese Installation nimmt keine Bestellungen entgegen.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Nur POST.']);
    exit;
}
$roh = file_get_contents('php://input');
$daten = json_decode((string)$roh, true);
$key = (string)($daten['key'] ?? $_SERVER['HTTP_X_SHOP_KEY'] ?? '');
if (SHOP_API_KEY !== '' && !hash_equals(SHOP_API_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ungültiger Shop-Schlüssel.']);
    exit;
}
if (!is_array($daten)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Daten.']);
    exit;
}

try {
    $ergebnis = Shop::bestellungAnlegen($daten);
    // Betreiber benachrichtigen (nur wenn E-Mail-Versand eingerichtet ist; Fehler soll die Bestellung nicht verhindern)
    $email = Shop::einstellung('bestell_email');
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && defined('EMAIL_ENABLED') && EMAIL_ENABLED) {
        $betreff = 'Neue Shop-Bestellung ' . $ergebnis['bestellnummer'];
        $text = 'Verein: ' . (string)($daten['verein_name'] ?? '-') . "\n"
              . 'Summe: ' . number_format($ergebnis['summe'], 2, ',', '.') . " EUR\n"
              . 'Details in Syncopa unter Shop → Bestellungen.';
        @mail($email, $betreff, $text, 'From: ' . (defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@localhost'));
    }
    echo json_encode(['success' => true, 'bestellnummer' => $ergebnis['bestellnummer']]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
