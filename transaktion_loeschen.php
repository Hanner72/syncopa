<?php
// transaktion_loeschen.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'loeschen');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: finanzen.php');
    exit;
}

$db = Database::getInstance();

try {
    // Transaktion löschen
    $db->execute("DELETE FROM finanzen WHERE id = ?", [$id]);
    Session::setFlashMessage('success', 'Transaktion erfolgreich gelöscht');
} catch (Exception $e) {
    Session::setFlashMessage('danger', 'Fehler beim Löschen: ' . $e->getMessage());
}

header('Location: finanzen.php');
exit;
