<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Löschen nur über ein bestätigtes Formular (POST mit Prüfzeichen) – ein einfacher Link reicht nicht
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Session::setFlashMessage('danger', 'Bitte über die Schaltfläche „Löschen“ löschen.');
    header('Location: mitglieder.php');
    exit;
}
Session::requirePermission('mitglieder', 'loeschen');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: mitglieder.php');
    exit;
}

$mitglied = new Mitglied();

try {
    $mitglied->delete($id);
    Session::setFlashMessage('success', 'Mitglied erfolgreich gelöscht');
} catch (Exception $e) {
    Session::setFlashMessage('danger', 'Fehler beim Löschen: ' . $e->getMessage());
}

header('Location: mitglieder.php');
exit;
