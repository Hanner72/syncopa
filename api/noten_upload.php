<?php
/**
 * API: Noten-Dateien Upload
 * Unterstützt Drag & Drop Upload mehrerer PDFs
 */

require_once '../config.php';
require_once '../includes.php';

Session::requireLogin();
Session::requirePermission('noten', 'schreiben');

header('Content-Type: application/json');

// Nur POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

// Noten-ID erforderlich
$notenId = $_POST['noten_id'] ?? null;
if (!$notenId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Noten-ID fehlt']);
    exit;
}

// Prüfen ob Noten existiert
$notenObj = new Noten();
$note = $notenObj->getById($notenId);
if (!$note) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Notenstück nicht gefunden']);
    exit;
}

// Datei(en) prüfen
if (!isset($_FILES['dateien']) || empty($_FILES['dateien']['name'][0])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Keine Dateien hochgeladen']);
    exit;
}

try {
    $benutzerId = Session::getUserId();
    $result = $notenObj->uploadMultipleDateien($notenId, $_FILES['dateien'], $benutzerId);
    
    $response = [
        'success' => true,
        'uploaded' => $result['uploaded'],
        'errors' => $result['errors'],
        'message' => count($result['uploaded']) . ' Datei(en) erfolgreich hochgeladen'
    ];
    
    if (!empty($result['errors'])) {
        $response['message'] .= ', ' . count($result['errors']) . ' Fehler';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
