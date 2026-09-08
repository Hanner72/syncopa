<?php
// api/fest_station_tage_get.php
// AJAX: Tages-Konfigurationen einer Station laden
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']); exit;
}

$stationId = (int)($_GET['station_id'] ?? 0);
if (!$stationId) {
    echo json_encode(['success' => false, 'error' => 'Ungültige Station']); exit;
}

// Daten werden vom Client übergeben (bereits bekannt aus PHP-Output)
$alleDaten = [];
if (!empty($_GET['daten'])) {
    foreach (explode(',', $_GET['daten']) as $d) {
        $d = trim($d);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $alleDaten[] = $d;
        }
    }
}

$stationObj = new FestStation();
$konfigs    = $stationObj->getTageKonfigs($stationId, $alleDaten);

echo json_encode(['success' => true, 'konfigs' => $konfigs]);
