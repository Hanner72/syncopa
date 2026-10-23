<?php
require_once '../config.php';
require_once '../includes.php';

header('Content-Type: application/json');
session_write_close();

Session::requireLogin();

$benutzerId = (int)($_GET['benutzer_id'] ?? 0);
$stimmeId   = (int)($_GET['stimme_id']   ?? 0);
$page       = (int)($_GET['page_number'] ?? 1);

if (!$benutzerId || !$stimmeId) {
    echo json_encode(['success' => true, 'zeichnung' => null]);
    exit;
}

try {
    $db  = Database::getInstance();
    $ann = $db->fetchOne(
        "SELECT datei_path FROM noten_annotationen
         WHERE benutzer_id = ? AND stimme_id = ? AND seite_number = ?",
        [$benutzerId, $stimmeId, $page]
    );

    if ($ann && file_exists(BASE_PATH . DIRECTORY_SEPARATOR . $ann['datei_path'])) {
        $base64 = base64_encode(file_get_contents(BASE_PATH . DIRECTORY_SEPARATOR . $ann['datei_path']));
        echo json_encode(['success' => true, 'zeichnung' => 'data:image/png;base64,' . $base64]);
    } else {
        echo json_encode(['success' => true, 'zeichnung' => null]);
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
