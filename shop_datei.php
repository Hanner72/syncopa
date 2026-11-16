<?php
// shop_datei.php – Vorlage (Logo/Schriftzug) einer Shop-Bestellung anzeigen bzw. herunterladen (Katalog-Server, Admin)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin() || !SHOP_IST_ZENTRALE) {
    http_response_code(403);
    exit('Kein Zugriff.');
}

$v = Shop::bestellVorlage((int)($_GET['b'] ?? 0), (string)($_GET['d'] ?? ''));
if (!$v) {
    http_response_code(404);
    exit('Datei nicht gefunden.');
}

$name = $v['original'];
$herunterladen = !empty($_GET['download']);
header('Content-Type: ' . $v['typ']);
header('Content-Length: ' . filesize($v['pfad']));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: private, max-age=3600');
header('Content-Disposition: ' . ($herunterladen ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', preg_replace('/[^\x20-\x7E]/', '_', $name)) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
readfile($v['pfad']);
