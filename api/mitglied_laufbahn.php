<?php
// api/mitglied_laufbahn.php
// AJAX: eigene Einträge eines Mitglieds (Funktion, Ehrung, Instrument, Prüfung, Mitgliedsart) anlegen, ändern, löschen.
// Einträge aus dem BMV sind gesperrt (bei Instrumenten nur Hauptinstrument/Formation änderbar), siehe BmvZusatzdaten.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']); exit;
}
if (!Session::checkPermission('mitglieder', 'schreiben')) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung zum Bearbeiten von Mitgliedern']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']); exit;
}

$aktion     = $_POST['aktion'] ?? '';
$art        = $_POST['art'] ?? '';
$mitgliedId = (int)($_POST['mitglied_id'] ?? 0);
$id         = !empty($_POST['id']) ? (int)$_POST['id'] : null;

if ($mitgliedId <= 0 || !Database::getInstance()->fetchOne("SELECT id FROM mitglieder WHERE id = ?", [$mitgliedId])) {
    echo json_encode(['success' => false, 'error' => 'Mitglied nicht gefunden']); exit;
}

$name = BmvZusatzdaten::ARTEN[$art]['name'] ?? 'Eintrag';
try {
    if ($aktion === 'speichern') {
        BmvZusatzdaten::eintragSpeichern($art, $mitgliedId, $_POST, $id);
        Session::setFlashMessage('success', $name . ($id ? ' geändert.' : ' hinzugefügt.'));
    } elseif ($aktion === 'loeschen' && $id) {
        BmvZusatzdaten::eintragLoeschen($art, $mitgliedId, $id);
        Session::setFlashMessage('success', $name . ' entfernt.');
    } else {
        throw new RuntimeException('Unbekannte Aktion.');
    }
    echo json_encode(['success' => true]);
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
