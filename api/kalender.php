<?php
// api/kalender.php
require_once '../config.php';
require_once '../includes.php';

// Error Logging aktivieren für Debugging
ini_set('display_errors', 0); // Keine Errors im Output (stört JSON)
error_log("=== Kalender API aufgerufen ===");

Session::requireLogin();
Session::requirePermission('ausrueckungen', 'lesen');

header('Content-Type: application/json');

try {
    // Datum-Parameter von FullCalendar parsen
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t');
    
    // ISO 8601 Format zu MySQL Format konvertieren
    if (strpos($start, 'T') !== false) {
        $start = substr($start, 0, 10);
    }
    if (strpos($end, 'T') !== false) {
        $end = substr($end, 0, 10);
    }
    
    error_log("Kalender API: start=$start, end=$end");
    
    $allEvents = [];
    
    // 1. Ausrückungen laden
    $ausrueckung = new Ausrueckung();
    $ausrueckungEvents = $ausrueckung->getKalenderEvents($start, $end);
    error_log("Kalender API: " . count($ausrueckungEvents) . " Ausrückungen gefunden");
    
    // Markiere Ausrückungen mit Prefix
    foreach ($ausrueckungEvents as &$event) {
        $event['id'] = 'ausrueckung_' . $event['id'];
        $event['extendedProps']['isAusrueckung'] = true;
    }
    
    $allEvents = array_merge($allEvents, $ausrueckungEvents);
    
    // 2. Kalender-Termine laden (falls Tabelle existiert)
    if (class_exists('KalenderTermin')) {
        $kalenderTermin = new KalenderTermin();
        $terminEvents = $kalenderTermin->getKalenderEvents($start, $end);
        error_log("Kalender API: " . count($terminEvents) . " Kalender-Termine gefunden");
        
        $allEvents = array_merge($allEvents, $terminEvents);
    }
    
    error_log("Kalender API: " . count($allEvents) . " Events insgesamt");
    
    echo json_encode($allEvents);
    
} catch (Exception $e) {
    error_log("Kalender API Fehler: " . $e->getMessage());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

