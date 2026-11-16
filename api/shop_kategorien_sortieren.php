<?php
// api/shop_kategorien_sortieren.php – Reihenfolge und Ober-/Unterkategorie aller Shop-Kategorien speichern (Drag & Drop, Katalog-Server, Admin)
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

$daten = json_decode((string)file_get_contents('php://input'), true);
try {
    if (!is_array($daten) || !isset($daten['struktur']) || !is_array($daten['struktur'])) throw new RuntimeException('Keine Kategorienliste übermittelt.');
    Shop::kategorienAnordnen($daten['struktur']);
    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
