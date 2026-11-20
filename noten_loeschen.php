<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Löschen nur über ein bestätigtes Formular (POST mit Prüfzeichen) – ein einfacher Link reicht nicht
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Session::setFlashMessage('danger', 'Bitte über die Schaltfläche „Löschen“ löschen.');
    header('Location: noten.php');
    exit;
}
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
