<?php
// includes.php - Lädt alle benötigten Klassen

// Basis-Klassen
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Session.php';

// Feature-Klassen
require_once __DIR__ . '/classes/Mitglied.php';
require_once __DIR__ . '/classes/Ausrueckung.php';
require_once __DIR__ . '/classes/Noten.php';
require_once __DIR__ . '/classes/Instrument.php';
require_once __DIR__ . '/classes/Uniform.php';
require_once __DIR__ . '/classes/Nummernkreis.php';

// Migration: Mehrfachrollen-Pivot-Tabelle
(function() {
    $db = Database::getInstance();
    $db->execute("CREATE TABLE IF NOT EXISTS benutzer_rollen (
        benutzer_id INT NOT NULL,
        rolle_id    INT NOT NULL,
        PRIMARY KEY (benutzer_id, rolle_id),
        FOREIGN KEY (benutzer_id) REFERENCES benutzer(id) ON DELETE CASCADE,
        FOREIGN KEY (rolle_id)    REFERENCES rollen(id)   ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Bestehende Benutzer migrieren (einmalig): rolle_id → benutzer_rollen
    $db->execute("INSERT IGNORE INTO benutzer_rollen (benutzer_id, rolle_id)
        SELECT id, rolle_id FROM benutzer WHERE rolle_id IS NOT NULL");
})();

// Telemetry-Ping (einmal pro Tag, nur wenn eingeloggt und aktiviert)
(function() {
    if (session_status() === PHP_SESSION_NONE) return;
    $lastPing = $_SESSION['_telemetry_ping_ts'] ?? 0;
    if (time() - $lastPing < 86400) return;
    $_SESSION['_telemetry_ping_ts'] = time();

    try {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT schluessel, wert FROM einstellungen WHERE schluessel IN ('telemetry_enabled','installation_id','verein_name')"
        );
        $cfg = array_column($rows, 'wert', 'schluessel');
        if (empty($cfg['telemetry_enabled']) || $cfg['telemetry_enabled'] !== '1') return;
        if (empty($cfg['installation_id'])) return;

        $payload = json_encode([
            'id'      => $cfg['installation_id'],
            'version' => defined('APP_VERSION') ? APP_VERSION : '?',
            'verein'  => $cfg['verein_name'] ?? '',
        ]);
        $url = 'https://syncopa.dannerbam.eu/telemetry/ping.php';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            @curl_exec($ch);
            curl_close($ch);
        } elseif (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 3,
            ]]);
            @file_get_contents($url, false, $ctx);
        }
    } catch (\Throwable $e) { /* still – Telemetry-Fehler nie anzeigen */ }
})();

// Festverwaltung
require_once __DIR__ . '/classes/Fest.php';
require_once __DIR__ . '/classes/FestStation.php';
require_once __DIR__ . '/classes/FestMitarbeiter.php';
require_once __DIR__ . '/classes/FestDienstplan.php';
require_once __DIR__ . '/classes/FestEinkauf.php';
require_once __DIR__ . '/classes/FestVertrag.php';
require_once __DIR__ . '/classes/FestTodo.php';
require_once __DIR__ . '/classes/FestKopieren.php';
require_once __DIR__ . '/classes/FestAbrechnung.php';
