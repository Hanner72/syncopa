<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Löschen nur über ein bestätigtes Formular (POST mit Prüfzeichen) – ein einfacher Link reicht nicht
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Session::setFlashMessage('danger', 'Bitte über die Schaltfläche „Löschen“ löschen.');
    header('Location: instrumente.php');
    exit;
}
Session::requirePermission('instrumente', 'loeschen');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: instrumente.php');
    exit;
}

$instrument = new Instrument();
try {
    $instrument->delete($id);
    Session::setFlashMessage('success', 'Instrument gelöscht');
} catch (Exception $e) {
    Session::setFlashMessage('danger', 'Fehler: ' . $e->getMessage());
}

header('Location: instrumente.php');
exit;
