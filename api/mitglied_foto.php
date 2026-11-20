<?php
// api/mitglied_foto.php
// Liefert das Foto eines Mitglieds: Originalbild, sonst die BMV-Version (128×128 PNG)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

if (!Session::isLoggedIn() || !Session::checkPermission('mitglieder', 'lesen')) {
    http_response_code(403);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$mitglied = $id ? Database::getInstance()->fetchOne("SELECT foto, bmv_foto FROM mitglieder WHERE id = ?", [$id]) : null;
if (!$mitglied) {
    http_response_code(404);
    exit;
}

$datei = !empty($mitglied['foto']) ? FOTOS_DIR . DIRECTORY_SEPARATOR . basename($mitglied['foto']) : null;
if ($datei && is_file($datei)) {
    $info = getimagesize($datei);
    header('Content-Type: ' . ($info['mime'] ?? 'application/octet-stream'));
    header('Cache-Control: private, max-age=3600');
    readfile($datei);
    exit;
}

if (!empty($mitglied['bmv_foto'])) {
    $bild = base64_decode($mitglied['bmv_foto'], true);
    if ($bild !== false) {
        header('Content-Type: image/png');
        header('Cache-Control: private, max-age=3600');
        echo $bild;
        exit;
    }
}

http_response_code(404);
