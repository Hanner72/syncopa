<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('ausrueckungen', 'loeschen');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: ausrueckungen.php');
    exit;
}

$ausrueckung = new Ausrueckung();

try {
    $ausrueckung->delete($id);
    Session::setFlashMessage('success', 'Ausrückung erfolgreich gelöscht');
} catch (Exception $e) {
    Session::setFlashMessage('danger', 'Fehler beim Löschen: ' . $e->getMessage());
}

header('Location: ausrueckungen.php');
exit;
