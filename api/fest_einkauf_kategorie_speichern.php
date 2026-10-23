<?php
// api/fest_einkauf_kategorie_speichern.php
// AJAX: Neue Einkauf-Kategorie anlegen
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']); exit;
}
if (!Session::checkPermission('fest', 'schreiben')) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']); exit;
}

$name = trim($_POST['name'] ?? '');
if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Bitte einen Namen eingeben.']); exit;
}

$eObj = new FestEinkauf();
$id   = $eObj->createKategorie($name);
echo json_encode(['success' => true, 'id' => $id, 'name' => $name]);
