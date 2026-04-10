<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'loeschen');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    header('Location: feste.php'); exit;
}

$id = (int)$_POST['id'];
$festObj = new Fest();
$fest    = $festObj->getById($id);

if (!$fest) {
    Session::setFlashMessage('danger', 'Fest nicht gefunden.');
    header('Location: feste.php'); exit;
}

try {
    $festObj->delete($id);
    Session::setFlashMessage('success', 'Fest «' . htmlspecialchars($fest['name']) . '» wurde gelöscht.');
} catch (\Throwable $e) {
    Session::setFlashMessage('danger', 'Fehler beim Löschen: ' . $e->getMessage());
}

header('Location: feste.php'); exit;
