<?php
// api/fest_einkauf_vorlage.php
// AJAX: Vorlage-Status eines Einkaufs umschalten
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

$id  = (int)($_POST['id'] ?? 0);
$val = (int)($_POST['ist_vorlage'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID fehlt']); exit;
}

$db = Database::getInstance();
$db->execute("UPDATE fest_einkauefe SET ist_vorlage = ? WHERE id = ?", [$val ? 1 : 0, $id]);
echo json_encode(['success' => true, 'ist_vorlage' => $val ? 1 : 0]);
