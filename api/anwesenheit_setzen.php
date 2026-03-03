<?php
// api/anwesenheit_setzen.php
require_once '../config.php';
require_once '../includes.php';

header('Content-Type: application/json');

Session::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

$ausrueckungId = $_POST['ausrueckung_id'] ?? null;
$status = $_POST['status'] ?? null;
$grund = $_POST['grund'] ?? null;

$erlaubteStatus = ['zugesagt', 'abgesagt', 'ungewiss'];

if (!$ausrueckungId || !$status || !in_array($status, $erlaubteStatus)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Parameter']);
    exit;
}

$currentUserId = Session::getUserId();
$db = Database::getInstance();
$benutzer = $db->fetchOne("SELECT mitglied_id FROM benutzer WHERE id = ?", [$currentUserId]);

if (!$benutzer || !$benutzer['mitglied_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Kein Mitgliedsprofil zugeordnet']);
    exit;
}

$mitgliedId = $benutzer['mitglied_id'];

$ausrueckungObj = new Ausrueckung();
$ausrueckungObj->setAnwesenheit($ausrueckungId, $mitgliedId, $status, $grund);

echo json_encode(['success' => true, 'status' => $status]);
