<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('noten', 'loeschen');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: noten.php');
    exit;
}

$noten = new Noten();

try {
    $noten->delete($id);
    Session::setFlashMessage('success', 'Noten erfolgreich gelöscht');
} catch (Exception $e) {
    Session::setFlashMessage('danger', 'Fehler beim Löschen: ' . $e->getMessage());
}

header('Location: noten.php');
exit;
