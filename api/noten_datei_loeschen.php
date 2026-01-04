<?php
/**
 * API: Noten-Dateien Löschen
 */

require_once '../config.php';
require_once '../includes.php';

Session::requireLogin();
Session::requirePermission('noten', 'loeschen');

header('Content-Type: application/json');

// Nur POST/DELETE erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

// Datei-ID erforderlich
$dateiId = $_POST['datei_id'] ?? $_GET['id'] ?? null;
if (!$dateiId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datei-ID fehlt']);
    exit;
}

$notenObj = new Noten();

try {
    // Prüfen ob Datei existiert
    $datei = $notenObj->getDateiById($dateiId);
    if (!$datei) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Datei nicht gefunden']);
        exit;
    }
    
    // Datei löschen
    $notenObj->deleteDatei($dateiId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Datei erfolgreich gelöscht'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
