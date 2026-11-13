<?php
// api/anwesenheit_liste.php
// Aktueller Stand der Zu- und Absagen einer Ausrückung (für die Live-Aktualisierung der Detailseite)
require_once '../config.php';
require_once '../includes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

Session::requireLogin();

if (!Session::checkPermission('ausrueckungen', 'lesen')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

$ausrueckungId = (int)($_GET['ausrueckung_id'] ?? 0);
$ausrueckungObj = new Ausrueckung();
if (!$ausrueckungId || !$ausrueckungObj->getById($ausrueckungId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Ausrückung nicht gefunden']);
    exit;
}

$liste = [];
foreach ($ausrueckungObj->getAnwesenheit($ausrueckungId) as $a) {
    $liste[] = [
        'mitglied_id' => (int)$a['mitglied_id'],
        'status'      => $a['status'],
        'grund'       => $a['grund'] ?? '',
        'gemeldet_am' => $a['gemeldet_am'] ? date('d.m.Y H:i', strtotime($a['gemeldet_am'])) : '',
    ];
}

require_once __DIR__ . '/../includes/anwesenheit_register.php';
$karten = [
    'zugesagt' => anwesenheitRegisterKarte('zugesagt', anwesenheitNachRegister($ausrueckungId, 'zugesagt')),
    'abgesagt' => anwesenheitRegisterKarte('abgesagt', anwesenheitNachRegister($ausrueckungId, 'abgesagt')),
];

echo json_encode(['success' => true, 'liste' => $liste, 'karten' => $karten, 'zeit' => date('H:i:s')]);
