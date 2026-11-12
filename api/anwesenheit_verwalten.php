<?php
// api/anwesenheit_verwalten.php
// Status und Grund eines Mitglieds in der Anwesenheitsliste sofort speichern (für Schreibberechtigte)
require_once '../config.php';
require_once '../includes.php';

header('Content-Type: application/json; charset=utf-8');

Session::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt']);
    exit;
}

if (!Session::checkPermission('ausrueckungen', 'schreiben')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

$ausrueckungId = (int)($_POST['ausrueckung_id'] ?? 0);
$mitgliedId    = (int)($_POST['mitglied_id'] ?? 0);
$status        = $_POST['status'] ?? '';
$grund         = trim((string)($_POST['grund'] ?? ''));
$grund         = $grund === '' ? null : mb_substr($grund, 0, 1000);

$erlaubteStatus = ['keine_antwort', 'zugesagt', 'ungewiss', 'abgesagt'];
if (!$ausrueckungId || !$mitgliedId || !in_array($status, $erlaubteStatus, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Parameter']);
    exit;
}

$ausrueckungObj = new Ausrueckung();
if (!$ausrueckungObj->getById($ausrueckungId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Ausrückung nicht gefunden']);
    exit;
}
$db = Database::getInstance();
if (!$db->fetchOne("SELECT id FROM mitglieder WHERE id = ?", [$mitgliedId])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Mitglied nicht gefunden']);
    exit;
}

try {
    $ausrueckungObj->setAnwesenheit($ausrueckungId, $mitgliedId, $status, $grund);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Speichern fehlgeschlagen']);
    exit;
}

echo json_encode(['success' => true, 'status' => $status, 'gemeldet_am' => date('d.m.Y H:i')]);
