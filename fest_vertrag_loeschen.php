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
$obj    = new FestVertrag();

try {
    $obj->delete($id); // löscht auch Datei
    Session::setFlashMessage('success', 'Vertrag gelöscht.');
} catch (Exception $e) {
    Session::setFlashMessage('danger', 'Fehler: ' . $e->getMessage());
}

header('Location: fest_vertraege.php?fest_id=' . $festId); exit;
