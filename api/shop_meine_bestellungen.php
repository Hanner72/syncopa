<?php
// api/shop_meine_bestellungen.php – eine Vereins-Installation fragt ihre eigenen Bestellungen samt Status ab (Server-zu-Server).
// Nur auf dem Katalog-Server. Die Installation weist sich mit ihrer geheimen Installations-ID aus; sie sieht nur eigene Bestellungen.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!SHOP_IST_ZENTRALE) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Diese Installation ist kein Katalog-Server.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Nur POST.']);
    exit;
}
$daten = json_decode((string)file_get_contents('php://input'), true);
$key = (string)($daten['key'] ?? $_SERVER['HTTP_X_SHOP_KEY'] ?? '');
if (SHOP_API_KEY !== '' && !hash_equals(SHOP_API_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ungültiger Shop-Schlüssel.']);
    exit;
}
$installationId = (string)($daten['installation_id'] ?? '');
if (!preg_match('/^[a-f0-9]{16,64}$/i', $installationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Keine gültige Installations-ID.']);
    exit;
}
echo json_encode(['success' => true, 'bestellungen' => Shop::bestellungenFuerInstallation($installationId)], JSON_UNESCAPED_UNICODE);
