<?php
require_once '../config.php';
require_once '../includes.php';

header('Content-Type: application/json');
session_write_close();

Session::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true);
$benutzerId = (int)($data['musiker_id']  ?? 0);
$stimmeId   = (int)($data['stimme_id']   ?? 0);
$page       = (int)($data['page_number'] ?? 1);
$zeichnung  = $data['zeichnung'] ?? '';

if (!$benutzerId || !$stimmeId || !$zeichnung) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Fehlende Parameter']);
    exit;
}

try {
    $db = Database::getInstance();

    $filename  = "s{$stimmeId}_b{$benutzerId}_p{$page}.png";
    $filepath  = PROBE_ANNOTATIONS_DIR . DIRECTORY_SEPARATOR . $filename;
    $imageData = base64_decode(explode(',', $zeichnung)[1]);

    if (file_put_contents($filepath, $imageData) === false) {
        throw new \Exception('Datei konnte nicht gespeichert werden');
    }

    $relativePath = 'uploads/probe_annotationen/' . $filename;

    $db->query(
        "INSERT INTO noten_annotationen (benutzer_id, stimme_id, seite_number, datei_path, erstellt_am, geaendert_am)
         VALUES (?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE datei_path = VALUES(datei_path), geaendert_am = NOW()",
        [$benutzerId, $stimmeId, $page, $relativePath]
    );

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
