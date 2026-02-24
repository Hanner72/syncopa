<?php
/**
 * API: Uniform-Zuweisungen eines Mitglieds abrufen
 */

require_once '../config.php';
require_once '../includes.php';

Session::start();

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

if (!Session::checkPermission('uniformen', 'lesen')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

$mitgliedId = $_GET['id'] ?? null;
if (!$mitgliedId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Mitglied-ID fehlt']);
    exit;
}

try {
    $uniformObj = new Uniform();
    $zuweisungen = $uniformObj->getZuweisungenByMitglied($mitgliedId);
    
    echo json_encode([
        'success' => true,
        'mitglied_id' => $mitgliedId,
        'zuweisungen' => $zuweisungen
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
