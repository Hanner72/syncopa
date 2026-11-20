<?php
// api/fest_stationen_sortieren.php – Reihenfolge der Stationen eines Festes speichern (Drag & Drop in fest_stationen.php)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');
Session::requireLogin();
if (!Session::checkPermission('fest', 'schreiben')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Nur POST erlaubt.']);
    exit;
}

$daten = json_decode((string)file_get_contents('php://input'), true);
try {
    if (!is_array($daten) || !is_array($daten['ids'] ?? null)) throw new RuntimeException('Keine Reihenfolge übermittelt.');
    $sortierung = (new FestStation())->anordnen((int)($daten['fest_id'] ?? 0), array_map('intval', $daten['ids']));
    echo json_encode(['success' => true, 'sortierung' => $sortierung]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
