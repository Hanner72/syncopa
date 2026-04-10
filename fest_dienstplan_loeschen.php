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
$datum  = $_POST['datum'] ?? '';
$obj    = new FestDienstplan();

try {
    $obj->delete($id);
    Session::setFlashMessage('success', 'Schicht gelöscht.');
} catch (\Throwable $e) {
    Session::setFlashMessage('danger', 'Fehler: ' . $e->getMessage());
}

$redirect = 'fest_dienstplan.php?fest_id=' . $festId;
if ($datum) $redirect .= '&datum=' . urlencode($datum);
header('Location: ' . $redirect); exit;
