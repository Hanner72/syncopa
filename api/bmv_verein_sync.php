<?php
// api/bmv_verein_sync.php
// AJAX: Vereinsdaten aus dem BMV-Datenservice in die Einstellungen übernehmen
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']); exit;
}
if (!Session::isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Nur Administratoren dürfen synchronisieren']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']); exit;
}

$api = new BmvApi();
if (!$api->istKonfiguriert()) {
    echo json_encode(['success' => false, 'error' => 'Die BMV-Zugangsdaten sind nicht vollständig.']); exit;
}

try {
    $verein = $api->findeEigenenVerein($api->get('/api/KAPELLE'));
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]); exit;
}

if ($verein === null) {
    echo json_encode(['success' => false, 'error' => 'Der Verein ' . $api->getVereinskennung() . ' wurde im BMV-Datenservice nicht gefunden.']); exit;
}

$db     = Database::getInstance();
$werte  = BmvApi::vereinsdatenZuEinstellungen($verein);
$jetzt  = date('Y-m-d H:i:s');
$werte['bmv_letzte_sync_verein'] = $jetzt;
$werte['bmv_verein_nr']          = (string)($verein['nr'] ?? '');

foreach ($werte as $schluessel => $wert) {
    $db->execute(
        "INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?) ON DUPLICATE KEY UPDATE wert = ?",
        [$schluessel, $wert, $wert]
    );
}

echo json_encode(['success' => true, 'werte' => $werte, 'zeitpunkt' => $jetzt]);
