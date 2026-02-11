<?php
/**
 * Befördert einen Benutzer von "user" zu "mitglied"
 */
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Nur Admin und Obmann dürfen befördern
$currentRole = Session::getRole();
if ($currentRole !== 'admin' && $currentRole !== 'obmann') {
    Session::setFlashMessage('danger', 'Keine Berechtigung für diese Aktion.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = $_POST['id'] ?? null;
if (!$id) {
    Session::setFlashMessage('danger', 'Kein Benutzer angegeben.');
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

// Benutzer laden
$benutzer = $db->fetchOne("SELECT * FROM benutzer WHERE id = ?", [$id]);
if (!$benutzer) {
    Session::setFlashMessage('danger', 'Benutzer nicht gefunden.');
    header('Location: index.php');
    exit;
}

// Nur "user" können befördert werden
if ($benutzer['rolle'] !== 'user') {
    Session::setFlashMessage('warning', 'Dieser Benutzer ist bereits freigeschaltet.');
    header('Location: index.php');
    exit;
}

// Rolle auf "mitglied" setzen (rolle_id = 9)
$sql = "UPDATE benutzer SET rolle = 'mitglied', rolle_id = 9 WHERE id = ?";
$db->execute($sql, [$id]);

// Aktivitätslog
$db->execute(
    "INSERT INTO aktivitaetslog (benutzer_id, aktion, beschreibung, ip_adresse) VALUES (?, ?, ?, ?)",
    [Session::getUserId(), 'benutzer_befoerdern', 'Benutzer ' . $benutzer['benutzername'] . ' wurde zum Mitglied befördert', $_SERVER['REMOTE_ADDR']]
);

Session::setFlashMessage('success', 'Benutzer "' . htmlspecialchars($benutzer['benutzername']) . '" wurde erfolgreich zum Mitglied befördert.');
header('Location: index.php');
exit;
