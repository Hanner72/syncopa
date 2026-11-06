<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';
Session::requireLogin();
Session::requirePermission('formationen', 'loeschen');

header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['success' => false, 'error' => 'Ungültige ID']); exit; }

$formationObj = new Formation();
$formationObj->delete($id);

// Aktive Formation in Sessions zurücksetzen falls es diese war
if (Session::getFormationId() === $id) {
    Session::setFormationId(null);
}

echo json_encode(['success' => true]);
