<?php
// api/shop_status.php – eine Vereins-Installation meldet sich beim Katalog-Server und erfährt,
// ob der Shop für sie freigeschaltet ist und unter welcher Adresse der Katalog liegt.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!SHOP_IST_ZENTRALE) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Diese Installation ist kein Katalog-Server.']);
    exit;
}
$key = (string)($_REQUEST['key'] ?? $_SERVER['HTTP_X_SHOP_KEY'] ?? '');
if (SHOP_API_KEY !== '' && !hash_equals(SHOP_API_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ungültiger Shop-Schlüssel.']);
    exit;
}

$installationId = preg_replace('/[^a-f0-9]/i', '', (string)($_REQUEST['id'] ?? ''));
if ($installationId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Keine Installations-ID.']);
    exit;
}

$datensatz = Shop::installationMelden($installationId, [
    'name'    => $_REQUEST['name'] ?? '',
    'url'     => $_REQUEST['url'] ?? '',
    'version' => $_REQUEST['version'] ?? '',
]);

echo json_encode([
    'success'     => true,
    'shop_aktiv'  => (int)($datensatz['shop_aktiv'] ?? 1) === 1,
    'katalog_url' => rtrim(SHOP_ZENTRALE_URL !== '' ? SHOP_ZENTRALE_URL : SHOP_SERVER_URL, '/'),
]);
