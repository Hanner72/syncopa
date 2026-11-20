<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Löschen nur über ein bestätigtes Formular (POST mit Prüfzeichen) – ein einfacher Link reicht nicht
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Session::setFlashMessage('danger', 'Bitte über die Schaltfläche „Löschen“ löschen.');
    header('Location: benutzer.php');
    exit;
}
if (Session::getRole() !== 'admin') {
    Session::setFlashMessage('danger', 'Keine Berechtigung');
    header('Location: index.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id || $id == Session::getUserId()) {
    Session::setFlashMessage('danger', 'Ungültige Aktion');
    header('Location: benutzer.php');
    exit;
}

$db = Database::getInstance();
try {
    $db->execute("DELETE FROM benutzer WHERE id = ?", [$id]);
    Session::setFlashMessage('success', 'Benutzer gelöscht');
} catch (Exception $e) {
    Session::setFlashMessage('danger', 'Fehler: ' . $e->getMessage());
}

header('Location: benutzer.php');
exit;
