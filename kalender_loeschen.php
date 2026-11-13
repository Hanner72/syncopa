<?php
// kalender_loeschen.php - Löscht Kalender-Termine (nicht Ausrückungen!)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Löschen nur über ein bestätigtes Formular (POST mit Prüfzeichen) – ein einfacher Link reicht nicht
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Session::setFlashMessage('danger', 'Bitte über die Schaltfläche „Löschen“ löschen.');
    header('Location: kalender.php');
    exit;
}

// Admin oder Lösch-Berechtigung erforderlich
if (Session::getRole() !== 'admin' && !Session::checkPermission('ausrueckungen', 'loeschen')) {
    Session::setFlashMessage('danger', 'Keine Berechtigung zum Löschen');
    header('Location: kalender.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    Session::setFlashMessage('danger', 'Keine ID angegeben');
    header('Location: kalender.php');
    exit;
}

$db = Database::getInstance();

// Termin laden
$termin = $db->fetchOne("SELECT * FROM kalender_termine WHERE id = ?", [$id]);
if (!$termin) {
    Session::setFlashMessage('danger', 'Termin nicht gefunden');
    header('Location: kalender.php');
    exit;
}

try {
    $db->execute("DELETE FROM kalender_termine WHERE id = ?", [$id]);
    Session::setFlashMessage('success', 'Termin "' . $termin['titel'] . '" wurde gelöscht');
} catch (Exception $e) {
    Session::setFlashMessage('danger', 'Fehler beim Löschen: ' . $e->getMessage());
}

header('Location: kalender.php');
exit;
