<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Admin oder Lösch-Berechtigung erforderlich
if (Session::getRole() !== 'admin' && !Session::checkPermission('ausrueckungen', 'loeschen')) {
    Session::setFlashMessage('danger', 'Keine Berechtigung zum Löschen');
    header('Location: ausrueckungen.php');
    exit;
}

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

$redirect = $_GET['redirect'] ?? 'ausrueckungen.php';
header('Location: ' . $redirect);
exit;
