<?php
// logout.php
require_once 'config.php';
require_once 'includes.php';

Session::start();

if (Session::isLoggedIn()) {
    $db = Database::getInstance();
    $db->execute(
        "INSERT INTO aktivitaetslog (benutzer_id, aktion, beschreibung, ip_adresse) VALUES (?, ?, ?, ?)",
        [Session::getUserId(), 'logout', 'Benutzer hat sich abgemeldet', $_SERVER['REMOTE_ADDR']]
    );
}

Session::destroy();
header('Location: login.php');
exit;
