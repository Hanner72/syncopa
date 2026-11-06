<?php
require_once '../config.php';
require_once '../includes.php';

header('Content-Type: application/json');
session_write_close();

Session::requireLogin();

$db          = Database::getInstance();
$formationId = Session::getFormationId();
$benutzerId  = Session::getUserId();

// Musikant verlässt den Live-Modus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!empty($body['leave'])) {
        $db->execute("DELETE FROM probe_session_spieler WHERE benutzer_id = ?", [$benutzerId]);
        $db->execute("DELETE FROM probe_session_viewer  WHERE benutzer_id = ?", [$benutzerId]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// Aktive Session ermitteln
$sql    = "SELECT ps.id, ps.noten_id FROM probe_session ps WHERE ps.aktiv = 1";
$params = [];
if ($formationId) {
    $sql    .= " AND (ps.formation_id = ? OR ps.formation_id IS NULL)";
    $params[] = $formationId;
}
$sql .= " ORDER BY ps.gestartet_am DESC LIMIT 1";

$session = $db->fetchOne($sql, $params);

// Heartbeat: last_seen in probe_session_spieler aktualisieren (alle 5 Sekunden)
if ($session) {
    $db->execute(
        "UPDATE probe_session_spieler SET last_seen = NOW()
         WHERE session_id = ? AND benutzer_id = ?",
        [$session['id'], $benutzerId]
    );
}

// Aktuell angezeigtes Stück des Benutzers vermerken
$viewingNotenId = (int)($_GET['viewing'] ?? 0);
if ($viewingNotenId) {
    $db->execute(
        "INSERT INTO probe_session_viewer (benutzer_id, noten_id, updated_at) VALUES (?,?,NOW())
         ON DUPLICATE KEY UPDATE noten_id=VALUES(noten_id), updated_at=NOW()",
        [$benutzerId, $viewingNotenId]
    );
}

echo json_encode([
    'session_id' => $session ? (int)$session['id']      : null,
    'noten_id'   => $session ? (int)$session['noten_id'] : null,
]);
