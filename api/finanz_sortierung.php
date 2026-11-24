<?php
// api/finanz_sortierung.php – speichert die per Drag & Drop geänderte Reihenfolge der Konten bzw. Kategorien des Kassabuchs
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');
Session::requireLogin();

if (!Session::checkPermission('finanzen', 'schreiben')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
// Fester SQL-Text je Art – der Tabellenname kommt nie aus der Anfrage
$sql = match ((string)($input['art'] ?? $_POST['art'] ?? '')) {
    'konten'     => "UPDATE finanz_konten SET sortierung = ? WHERE id = ?",
    'kategorien' => "UPDATE finanz_kategorien SET sortierung = ? WHERE id = ?",
    default      => null,
};
$ids = array_values(array_filter(array_map('intval', (array)($input['ids'] ?? $_POST['ids'] ?? []))));
if (!$sql || !$ids) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
    exit;
}

$db = Database::getInstance();
$db->beginTransaction();
try {
    foreach ($ids as $pos => $id) {
        $db->execute($sql, [($pos + 1) * 10, $id]);
    }
    $db->commit();
} catch (\Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Speichern fehlgeschlagen']);
    exit;
}

echo json_encode(['success' => true]);
