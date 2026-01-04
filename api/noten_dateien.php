<?php
/**
 * API: Noten-Dateien Liste abrufen
 */

require_once '../config.php';
require_once '../includes.php';

Session::requireLogin();
Session::requirePermission('noten', 'lesen');

header('Content-Type: application/json');

// Noten-ID erforderlich
$notenId = $_GET['noten_id'] ?? null;
if (!$notenId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Noten-ID fehlt']);
    exit;
}

$notenObj = new Noten();

// Prüfen ob Noten existiert
$note = $notenObj->getById($notenId);
if (!$note) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Notenstück nicht gefunden']);
    exit;
}

try {
    $dateien = $notenObj->getDateien($notenId);
    
    echo json_encode([
        'success' => true,
        'noten_id' => $notenId,
        'titel' => $note['titel'],
        'dateien' => $dateien
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
