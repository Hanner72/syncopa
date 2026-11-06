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

$isView = isset($_GET['view']) && $_GET['view'] === '1';
$disposition = $isView ? 'inline' : 'attachment';

header('Content-Type: ' . $fileType);
header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
if (!$isView) {
    header('Content-Description: File Transfer');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
}

// Output Buffer leeren
ob_clean();
flush();

// Datei ausgeben
readfile($filePath);
exit;
