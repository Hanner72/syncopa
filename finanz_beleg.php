<?php
// finanz_beleg.php – Belegdatei einer Buchung anzeigen (nur mit Leserecht für Finanzen)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'lesen');

$b = Kassabuch::buchung((int)($_GET['id'] ?? 0));
$datei = $b ? Kassabuch::belegDatei($b) : null;
if (!$datei) {
    http_response_code(404);
    exit('Beleg nicht gefunden.');
}
header('Content-Type: ' . $datei['typ']);
header('X-Content-Type-Options: nosniff');
header("Content-Disposition: inline; filename=\"" . preg_replace('/[^\x20-\x7e]|"/', '_', $datei['name']) . "\"; filename*=UTF-8''" . rawurlencode($datei['name']));
header('Content-Length: ' . filesize($datei['pfad']));
readfile($datei['pfad']);
