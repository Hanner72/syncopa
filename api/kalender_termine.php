<?php
/**
 * api/kalender_termine.php
 * CRUD für Kalender-Termine (NICHT Ausrückungen)
 */
require_once '../config.php';
require_once '../includes.php';

Session::requireLogin();

// Nur Admins und Vorstand können Termine erstellen/bearbeiten/löschen
$canEdit = Session::checkPermission('ausrueckungen', 'schreiben');

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$kalenderTermin = new KalenderTermin();

try {
    switch ($action) {
        case 'create':
            if (!$canEdit) {
                throw new Exception('Keine Berechtigung zum Erstellen von Terminen');
            }
            
            $data = [
                'titel' => $_POST['titel'] ?? '',
                'beschreibung' => $_POST['beschreibung'] ?? '',
                'typ' => $_POST['typ'] ?? 'Termin',
                'start_datum' => $_POST['start_datum'] ?? '',
                'ende_datum' => $_POST['ende_datum'] ?? null,
                'ganztaegig' => isset($_POST['ganztaegig']) ? 1 : 0,
                'ort' => $_POST['ort'] ?? '',
                'farbe' => $_POST['farbe'] ?? '#6c757d'
            ];
            
            // Validation
            if (empty($data['titel'])) {
                throw new Exception('Titel ist erforderlich');
            }
            if (empty($data['start_datum'])) {
                throw new Exception('Start-Datum ist erforderlich');
            }
            
            // Ende-Datum: Wenn leer, automatisch berechnen
            if (empty($data['ende_datum']) || $data['ende_datum'] === '') {
                if ($data['ganztaegig']) {
                    // Bei ganztägig: Ende = Start
                    $data['ende_datum'] = null; // NULL = ganzer Tag
                } else {
                    // Bei nicht-ganztägig: +2 Stunden
                    $start = new DateTime($data['start_datum']);
                    $start->modify('+2 hours');
                    $data['ende_datum'] = $start->format('Y-m-d H:i:s');
                }
            }
            
            $id = $kalenderTermin->create($data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Termin erstellt',
                'id' => $id
            ]);
            break;
            
        case 'update':
            if (!$canEdit) {
                throw new Exception('Keine Berechtigung zum Bearbeiten von Terminen');
            }
            
            $id = $_POST['id'] ?? null;
            if (!$id) {
                throw new Exception('Termin-ID fehlt');
            }
            
            $data = [
                'titel' => $_POST['titel'] ?? '',
                'beschreibung' => $_POST['beschreibung'] ?? '',
                'typ' => $_POST['typ'] ?? 'Termin',
                'start_datum' => $_POST['start_datum'] ?? '',
                'ende_datum' => $_POST['ende_datum'] ?? null,
                'ganztaegig' => isset($_POST['ganztaegig']) ? 1 : 0,
                'ort' => $_POST['ort'] ?? '',
                'farbe' => $_POST['farbe'] ?? '#6c757d'
            ];
            
            $kalenderTermin->update($id, $data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Termin aktualisiert'
            ]);
            break;
            
        case 'delete':
            if (!$canEdit) {
                throw new Exception('Keine Berechtigung zum Löschen von Terminen');
            }
            
            $id = $_POST['id'] ?? $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Termin-ID fehlt');
            }
            
            $kalenderTermin->delete($id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Termin gelöscht'
            ]);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Termin-ID fehlt');
            }
            
            $termin = $kalenderTermin->getById($id);
            if (!$termin) {
                throw new Exception('Termin nicht gefunden');
            }
            
            echo json_encode($termin);
            break;
            
        default:
            throw new Exception('Ungültige Aktion');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
