<?php
/**
 * API: Noten-Dateien Upload
 * Unterstützt Drag & Drop Upload mehrerer PDFs
 */

// Fehlerausgabe unterdrücken, damit JSON nicht kaputt geht
error_reporting(0);
ini_set('display_errors', 0);

// JSON Header setzen
header('Content-Type: application/json; charset=utf-8');

try {
    require_once '../config.php';
    require_once '../includes.php';
    
    // Session starten
    Session::start();
    
    // Login prüfen
    if (!Session::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
        exit;
    }
    
    // Berechtigung prüfen
    if (!Session::checkPermission('noten', 'schreiben')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
    
    // Nur POST erlaubt
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
        exit;
    }
    
    // Noten-ID erforderlich
    $notenId = isset($_POST['noten_id']) ? (int)$_POST['noten_id'] : 0;
    if ($notenId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Noten-ID fehlt oder ungültig']);
        exit;
    }
    
    // Prüfen ob Noten existiert
    $notenObj = new Noten();
    $note = $notenObj->getById($notenId);
    if (!$note) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Notenstück nicht gefunden (ID: ' . $notenId . ')']);
        exit;
    }
    
    // Datei(en) prüfen
    if (!isset($_FILES['dateien'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Keine Dateien im Request']);
        exit;
    }
    
    if (!is_array($_FILES['dateien']['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültiges Dateiformat']);
        exit;
    }
    
    if (empty($_FILES['dateien']['name'][0])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Keine Dateien hochgeladen']);
        exit;
    }
    
    // Upload durchführen
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
    echo json_encode([
        'success' => false, 
        'error' => 'Server-Fehler: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'PHP-Fehler: ' . $e->getMessage()
    ]);
}
