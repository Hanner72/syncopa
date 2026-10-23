<?php
// api/fest_station_tag.php
// AJAX: Tages-Konfiguration einer Station speichern
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']); exit;
}
if (!Session::checkPermission('fest', 'schreiben')) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']); exit;
}

$stationId         = (int)($_POST['station_id'] ?? 0);
$datum             = trim($_POST['datum'] ?? '');
$aktiv             = (int)($_POST['aktiv'] ?? 1);
$oeffnung_von      = trim($_POST['oeffnung_von'] ?? '');
$oeffnung_bis      = trim($_POST['oeffnung_bis'] ?? '');
$benoetigte_helfer = max(0, (int)($_POST['benoetigte_helfer'] ?? 1));

if (!$stationId || !$datum || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
    echo json_encode(['success' => false, 'error' => 'Ungültige Parameter']); exit;
}

$stationObj = new FestStation();
$stationObj->saveTageKonfig($stationId, $datum, [
    'aktiv'             => $aktiv,
    'oeffnung_von'      => $oeffnung_von ?: null,
    'oeffnung_bis'      => $oeffnung_bis ?: null,
    'benoetigte_helfer' => $benoetigte_helfer,
]);

// Gespeicherte Zeile zurücklesen zur Bestätigung
$saved = $stationObj->getTageKonfigRow($stationId, $datum);
echo json_encode(['success' => true, 'saved' => $saved]);
