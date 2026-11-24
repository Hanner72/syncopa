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
@ini_set('memory_limit', '512M');   // Bestellungen können Logos/Vorlagen (base64) enthalten
@set_time_limit(120);
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
// Bestellungen nur von freigeschalteten Installationen annehmen (zentrale Steuerung je Verein)
$installationId = preg_replace('/[^a-f0-9]/i', '', (string)($daten['installation_id'] ?? ''));
if (!Shop::installationAktiv($installationId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Der Shop ist für diesen Verein nicht freigeschaltet.']);
    exit;
}

try {
    $ergebnis = Shop::bestellungAnlegen($daten);   // meldet die Bestellung auch per E-Mail (Shop-Einstellungen)
    echo json_encode(['success' => true, 'bestellnummer' => $ergebnis['bestellnummer']]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
