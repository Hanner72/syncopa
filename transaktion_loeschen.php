<?php
// transaktion_loeschen.php – Buchung löschen (nur per POST, nicht in abgeschlossenen Jahren)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'loeschen');

$id = (int)($_POST['id'] ?? 0);
$b = $id && $_SERVER['REQUEST_METHOD'] === 'POST' ? Kassabuch::buchung($id) : null;
if ($b) {
    try {
        Kassabuch::loeschen($id);
        Session::setFlashMessage('success', 'Buchung ' . ($b['beleg_nummer'] ?? '') . ' gelöscht.');
    } catch (RuntimeException $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
}
header('Location: finanzen.php' . ($b ? '?jahr=' . substr($b['datum'], 0, 4) : ''));
exit;
