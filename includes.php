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
