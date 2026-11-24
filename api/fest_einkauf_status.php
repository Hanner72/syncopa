<?php
// api/fest_einkauf_status.php
// AJAX: Status eines Einkaufs ändern (geplant, bestellt, erhalten, storniert)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']); exit;
}
if (!Session::checkPermission('fest', 'schreiben')) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']); exit;
}

$id     = (int)($_POST['id'] ?? 0);
$status = (string)($_POST['status'] ?? '');
if (!$id || !in_array($status, ['geplant', 'bestellt', 'erhalten', 'storniert'], true)) {
    echo json_encode(['success' => false, 'error' => 'Ungültige Angaben']); exit;
}

$db = Database::getInstance();
if (!$db->fetchOne("SELECT id FROM fest_einkauefe WHERE id = ?", [$id])) {
    echo json_encode(['success' => false, 'error' => 'Einkauf nicht gefunden']); exit;
}
$db->execute("UPDATE fest_einkauefe SET status = ? WHERE id = ?", [$status, $id]);
echo json_encode(['success' => true, 'status' => $status]);
