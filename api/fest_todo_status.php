<?php
// api/fest_todo_status.php
// AJAX-Endpoint: Todo-Status umschalten
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}
if (!Session::checkPermission('fest', 'schreiben')) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Ungültige Methode']);
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if (!$id || !$status) {
    echo json_encode(['success' => false, 'error' => 'Fehlende Parameter']);
    exit;
}

$todoObj = new FestTodo();
$todo    = $todoObj->getById($id);

if (!$todo) {
    echo json_encode(['success' => false, 'error' => 'Todo nicht gefunden']);
    exit;
}

$ok = $todoObj->updateStatus($id, $status);
echo json_encode(['success' => $ok, 'status' => $status]);
