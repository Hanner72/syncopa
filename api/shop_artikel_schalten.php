<?php
// api/shop_artikel_schalten.php – Sichtbar/Ausverkauft eines Artikels direkt aus der Artikelliste umschalten (Katalog-Server, Admin)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');
Session::requireLogin();
if (!Session::isAdmin() || !SHOP_IST_ZENTRALE) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Kein Zugriff auf die Shop-Verwaltung.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Nur POST erlaubt.']);
    exit;
}
$d = json_decode((string)file_get_contents('php://input'), true);
try {
    if (!is_array($d)) throw new RuntimeException('Ungültige Anfrage.');
    $id = (int)($d['id'] ?? 0);
    if (($d['feld'] ?? '') === 'aktionspreis') {
        $p = Shop::aktionspreisSetzen($id, (string)($d['wert'] ?? ''));
        echo json_encode(['success' => true, 'datum' => date('d.m.Y'), 'aktionspreis' => $p]);
    } else {
        Shop::artikelSchalten($id, (string)($d['feld'] ?? ''), !empty($d['wert']));
        echo json_encode(['success' => true, 'datum' => date('d.m.Y')]);
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
