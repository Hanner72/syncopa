<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'loeschen');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    header('Location: feste.php'); exit;
}

$id     = (int)$_POST['id'];
$festId = (int)($_POST['fest_id'] ?? 0);
$obj    = new FestEinkauf();

try {
    $obj->delete($id);
    Session::setFlashMessage('success', 'Einkauf gelöscht.');
} catch (\Throwable $e) {
    Session::setFlashMessage('danger', 'Fehler: ' . $e->getMessage());
}

header('Location: fest_einkauefe.php?fest_id=' . $festId); exit;
