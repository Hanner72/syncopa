<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'loeschen');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    header('Location: fest_todos.php'); exit;
}

$id     = (int)$_POST['id'];
$festId = (int)($_POST['fest_id'] ?? 0);
$obj    = new FestTodo();

try {
    $obj->delete($id);
    Session::setFlashMessage('success', 'Todo gelöscht.');
} catch (Exception $e) {
    Session::setFlashMessage('danger', 'Fehler: ' . $e->getMessage());
}

header('Location: fest_todos.php' . ($festId ? '?fest_id=' . $festId : '')); exit;
