<?php
// api/mitgliedsarten_sortieren.php – Reihenfolge der Mitgliedsarten speichern (Drag & Drop in stammdaten.php)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');
Session::requireLogin();
if (Session::getRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Nur Administratoren dürfen die Stammdaten ändern.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Nur POST erlaubt.']);
    exit;
}

$daten = json_decode((string)file_get_contents('php://input'), true);
try {
    if (!is_array($daten) || !is_array($daten['codes'] ?? null)) throw new RuntimeException('Keine Reihenfolge übermittelt.');
    echo json_encode(['success' => true, 'sortierung' => Mitgliedsart::anordnen($daten['codes'])]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
