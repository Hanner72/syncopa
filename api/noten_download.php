<?php
/**
 * API: Noten-Dateien Download
 * Sicherer Download mit Berechtigungsprüfung
 */

require_once '../config.php';
require_once '../includes.php';

Session::requireLogin();
Session::requirePermission('noten', 'lesen');

// Datei-ID erforderlich
$dateiId = $_GET['id'] ?? null;
if (!$dateiId) {
    http_response_code(400);
    die('Datei-ID fehlt');
}

$notenObj = new Noten();
$dateiInfo = $notenObj->getDateiPfad($dateiId);

if (!$dateiInfo) {
    http_response_code(404);
    die('Datei nicht gefunden');
}

// Datei ausliefern
$filePath = $dateiInfo['path'];
$fileName = $dateiInfo['name'];
$fileType = $dateiInfo['type'];
$fileSize = $dateiInfo['size'];

// Headers für Download
header('Content-Description: File Transfer');
header('Content-Type: ' . $fileType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $fileSize);

// Output Buffer leeren
ob_clean();
flush();

// Datei ausgeben
readfile($filePath);
exit;
