<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Löschen nur über ein bestätigtes Formular (POST mit Prüfzeichen) – ein einfacher Link reicht nicht
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Session::setFlashMessage('danger', 'Bitte über die Schaltfläche „Löschen“ löschen.');
    header('Location: ausrueckungen.php');
    exit;
}

// Admin oder Lösch-Berechtigung erforderlich
if (Session::getRole() !== 'admin' && !Session::checkPermission('ausrueckungen', 'loeschen')) {
    Session::setFlashMessage('danger', 'Keine Berechtigung zum Löschen');
    header('Location: ausrueckungen.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
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

// Zurück nur auf eine eigene Seite (z. B. kalender.php), nie auf eine fremde Adresse
$redirect = (string)($_GET['redirect'] ?? '');
if (!preg_match('/^[a-z0-9_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $redirect)) {
    $redirect = 'ausrueckungen.php';
}
header('Location: ' . $redirect); // nosemgrep: syncopa-weiterleitung-mit-benutzereingabe – nur eigene Seiten, siehe Prüfung oben
exit;
