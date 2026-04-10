<?php
// api/fest_dienstplan_eintrag.php
// AJAX-Endpoint: Schicht-Eintrag erstellen oder löschen (für Grid-Schnelleingabe)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']); exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$dpObj  = new FestDienstplan();

// DELETE: Schicht entfernen
if ($method === 'DELETE' || ($method === 'POST' && ($_POST['_method'] ?? '') === 'DELETE')) {
    if (!Session::checkPermission('fest', 'loeschen')) {
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']); exit;
    }
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'ID fehlt']); exit; }
    $ok = $dpObj->delete($id);
    echo json_encode(['success' => $ok]);
    exit;
}

// POST: Schicht erstellen
if ($method === 'POST') {
    if (!Session::checkPermission('fest', 'schreiben')) {
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']); exit;
    }

    $data = [
        'fest_id'        => (int)($_POST['fest_id'] ?? 0),
        'station_id'     => (int)($_POST['station_id'] ?? 0),
        'mitarbeiter_id' => (int)($_POST['mitarbeiter_id'] ?? 0),
        'datum'          => trim($_POST['datum'] ?? ''),
        'zeit_von'       => trim($_POST['zeit_von'] ?? ''),
        'zeit_bis'       => trim($_POST['zeit_bis'] ?? ''),
        'notizen'        => trim($_POST['notizen'] ?? ''),
    ];

    if (!$data['fest_id'] || !$data['station_id'] || !$data['mitarbeiter_id'] || !$data['datum'] || !$data['zeit_von'] || !$data['zeit_bis']) {
        echo json_encode(['success' => false, 'error' => 'Fehlende Pflichtfelder']); exit;
    }

    try {
        $newId = $dpObj->create($data);
        $entry = $dpObj->getById($newId);
        echo json_encode(['success' => true, 'id' => $newId, 'eintrag' => $entry]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
