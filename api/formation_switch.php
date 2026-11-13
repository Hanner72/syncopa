<?php
// api/formation_switch.php – Aktive Formation in der Session setzen
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';
Session::start();
Session::requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$id = isset($_POST['formation_id']) ? (int)$_POST['formation_id'] : null;

// 0 = "alle Formationen" (nur Admin)
if ($id === 0) {
    if (!Session::isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
    Session::setFormationId(null);
    echo json_encode(['success' => true, 'formation_id' => null]);
    exit;
}

if ($id) {
    // Prüfen ob Formation existiert
    $formation = new Formation();
    $f = $formation->getById($id);
    if (!$f) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Formation nicht gefunden']);
        exit;
    }

    // Nicht-Admin darf nur eigene Formationen wählen
    if (!Session::isAdmin()) {
        $erlaubte = Session::getFormationIds();
        if (!in_array($id, $erlaubte)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Keine Berechtigung für diese Formation']);
            exit;
        }
    }

    Session::setFormationId($id);
    echo json_encode(['success' => true, 'formation_id' => $id, 'name' => $f['name']]);
} else {
    Session::setFormationId(null);
    echo json_encode(['success' => true, 'formation_id' => null]);
}
