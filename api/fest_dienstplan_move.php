<?php
// api/fest_dienstplan_move.php
// AJAX: Schicht verschieben (Zeit, Station und/oder Mitarbeiter ändern)
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

$id            = (int)($_POST['id'] ?? 0);
$zeit_von      = trim($_POST['zeit_von'] ?? '');
$zeit_bis      = trim($_POST['zeit_bis'] ?? '');
$station_id    = (int)($_POST['station_id'] ?? 0);
$mitarbeiter_id = !empty($_POST['mitarbeiter_id']) ? (int)$_POST['mitarbeiter_id'] : null;

if (!$id || !$zeit_von || !$zeit_bis || !$station_id) {
    echo json_encode(['success' => false, 'error' => 'Pflichtfelder fehlen']); exit;
}
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $zeit_von) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $zeit_bis)) {
    echo json_encode(['success' => false, 'error' => 'Ungültiges Zeitformat']); exit;
}

$von = substr($zeit_von, 0, 5) . ':00';
$bis = substr($zeit_bis, 0, 5) . ':00';

$db = Database::getInstance();

if ($mitarbeiter_id) {
    $db->execute(
        "UPDATE fest_dienstplaene SET zeit_von=?, zeit_bis=?, station_id=?, mitarbeiter_id=? WHERE id=?",
        [$von, $bis, $station_id, $mitarbeiter_id, $id]
    );
} else {
    $db->execute(
        "UPDATE fest_dienstplaene SET zeit_von=?, zeit_bis=?, station_id=? WHERE id=?",
        [$von, $bis, $station_id, $id]
    );
}

echo json_encode(['success' => true, 'id' => $id, 'zeit_von' => substr($von,0,5), 'zeit_bis' => substr($bis,0,5)]);
