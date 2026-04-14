<?php
// api/fest_vertrag_download.php
// Vertragsdokument sicher zum Download bereitstellen
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'lesen');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    http_response_code(400); echo 'Ungültige Anfrage'; exit;
}

$vObj    = new FestVertrag();
$vertrag = $vObj->getById($id);

if (!$vertrag) {
    http_response_code(404); echo 'Vertrag nicht gefunden'; exit;
}

$pfad = $vertrag['dokument_pfad'] ?? '';
if (!$pfad || !file_exists($pfad)) {
    http_response_code(404); echo 'Dokument nicht gefunden'; exit;
}

// Sicherstellen, dass die Datei im erlaubten Upload-Verzeichnis liegt
$realPfad    = realpath($pfad);
$realUpload  = realpath(FEST_VERTRAEGE_DIR);
if (!$realPfad || !$realUpload || strpos($realPfad, $realUpload) !== 0) {
    http_response_code(403); echo 'Zugriff verweigert'; exit;
}

$dateiname = $vertrag['dokument_name'] ?: basename($pfad);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . addslashes($dateiname) . '"');
header('Content-Length: ' . filesize($pfad));
header('Cache-Control: private, no-cache');
readfile($pfad);
exit;
