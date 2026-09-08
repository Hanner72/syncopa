<?php
require_once '../config.php';
require_once '../includes.php';

header('Content-Type: application/json');

Session::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input      = json_decode(file_get_contents('php://input'), true);
$notenbuchId = (int)($input['notenbuch_id'] ?? 0);
$order       = $input['order'] ?? [];

if (!$notenbuchId || !is_array($order)) {
    echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
    exit;
}

$db         = Database::getInstance();
$benutzerId = Session::getUserId();

// Zugriff prüfen
$buch = $db->fetchOne("SELECT * FROM notenbucher WHERE id = ?", [$notenbuchId]);
if (!$buch) {
    echo json_encode(['success' => false, 'error' => 'Nicht gefunden']);
    exit;
}
$darfBearbeiten = $buch['benutzer_id'] == $benutzerId || Session::isAdmin()
    || ($buch['typ'] === 'geteilt' && Session::checkPermission('noten', 'schreiben'));
if (!$darfBearbeiten) {
    echo json_encode(['success' => false, 'error' => 'Kein Zugriff']);
    exit;
}

try {
    foreach ($order as $item) {
        $notenId = (int)($item['id'] ?? 0);
        $pos     = (int)($item['pos'] ?? 0);
        if ($notenId) {
            $db->execute(
                "UPDATE notenbuch_noten SET reihenfolge = ? WHERE notenbuch_id = ? AND noten_id = ?",
                [$pos, $notenbuchId, $notenId]
            );
        }
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
