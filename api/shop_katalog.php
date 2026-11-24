<?php
// api/shop_katalog.php – liefert den Shop-Katalog als JSON an die Vereins-Installationen.
// Nur auf dem Katalog-Server (SHOP_IST_ZENTRALE). Zugriff mit dem gemeinsamen Schlüssel SHOP_API_KEY.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!SHOP_IST_ZENTRALE) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Diese Installation ist kein Katalog-Server.']);
    exit;
}
$key = (string)($_GET['key'] ?? $_SERVER['HTTP_X_SHOP_KEY'] ?? '');
if (SHOP_API_KEY !== '' && !hash_equals(SHOP_API_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ungültiger Shop-Schlüssel.']);
    exit;
}

$bildBasis = rtrim(SHOP_ZENTRALE_URL, '/') . '/uploads/shop';
try {
    echo json_encode(['success' => true, 'katalog' => Shop::katalog($bildBasis)], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Katalog konnte nicht geladen werden.']);
}
