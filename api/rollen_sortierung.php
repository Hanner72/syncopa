<?php
// api/rollen_sortierung.php – speichert neue Reihenfolge der Rollen
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json');
Session::requireLogin();

if (Session::getRole() !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order = $input['order'] ?? [];  // [ {id: 3, pos: 0}, {id:1, pos:1}, ... ]

if (empty($order) || !is_array($order)) {
    echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
    exit;
}

$db = Database::getInstance();
foreach ($order as $item) {
    $db->execute("UPDATE rollen SET sortierung = ? WHERE id = ?", [
        (int)$item['pos'],
        (int)$item['id'],
    ]);
}

echo json_encode(['success' => true]);
