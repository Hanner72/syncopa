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

    // Migration: Telemetry-Einstellungen für bestehende Installationen
    $existing = $db->fetchOne("SELECT COUNT(*) as cnt FROM einstellungen WHERE schluessel = 'installation_id'");
    if ((int)($existing['cnt'] ?? 0) === 0) {
        $db->execute(
            "INSERT IGNORE INTO einstellungen (schluessel, wert, beschreibung, aktualisiert_am) VALUES (?, ?, ?, NOW())",
            ['installation_id', bin2hex(random_bytes(16)), 'Eindeutige Installations-ID']
        );
    }
    $db->execute(
        "INSERT IGNORE INTO einstellungen (schluessel, wert, beschreibung, aktualisiert_am) VALUES (?, ?, ?, NOW())",
        ['telemetry_enabled', '1', 'Anonyme Nutzungsstatistik senden']
    );
})();


// Formationen
require_once __DIR__ . '/classes/Formation.php';

// Migration-Guard: Formation-Tabellen anlegen falls noch nicht vorhanden
(function() {
    $db = Database::getInstance();
    $db->execute("CREATE TABLE IF NOT EXISTS formationen (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        kuerzel     VARCHAR(10)  NULL,
        farbe       VARCHAR(7)   NOT NULL DEFAULT '#4471A3',
        beschreibung TEXT        NULL,
        aktiv       TINYINT(1)  NOT NULL DEFAULT 1,
        erstellt_am TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS mitglied_formationen (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        mitglied_id  INT  NOT NULL,
        formation_id INT  NOT NULL,
        rolle        VARCHAR(50) NULL,
        seit_datum   DATE        NULL,
        UNIQUE KEY uk_mitglied_formation (mitglied_id, formation_id),
        CONSTRAINT fk_mf_mitglied  FOREIGN KEY (mitglied_id)  REFERENCES mitglieder(id)  ON DELETE CASCADE,
        CONSTRAINT fk_mf_formation FOREIGN KEY (formation_id) REFERENCES formationen(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Spalten sicher hinzufügen. Hinweis: "ADD COLUMN IF NOT EXISTS" ist auf älteren
    // MySQL-Versionen ein Syntaxfehler (nicht nur "Spalte existiert bereits") und wird
    // vom try/catch stillschweigend verschluckt – die Spalte wird dann NIE angelegt.
    // Deshalb hier stattdessen über information_schema prüfen (funktioniert auf allen Versionen).
    $columnExists = function(string $table, string $column) use ($db): bool {
        $row = $db->fetchOne(
            "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );
        return (int)($row['cnt'] ?? 0) > 0;
    };

    foreach (['ausrueckungen', 'noten', 'finanzen'] as $tbl) {
        if (!$columnExists($tbl, 'formation_id')) {
            $db->execute("ALTER TABLE `{$tbl}` ADD COLUMN formation_id INT NULL");
        }
    }
    // formation_id für formationsspezifische Instrumentenzuordnung
    if (!$columnExists('mitglied_instrumente', 'formation_id')) {
        $db->execute("ALTER TABLE `mitglied_instrumente` ADD COLUMN formation_id INT NULL");
    }
    // register_id für formationsspezifisches Register pro Mitglied
    if (!$columnExists('mitglied_formationen', 'register_id')) {
        $db->execute("ALTER TABLE `mitglied_formationen` ADD COLUMN register_id INT NULL");
    }

    // Berechtigungen-Tabelle: Duplikate bereinigen + UNIQUE KEY anlegen (einmalig)
    try {
        $db->execute("DELETE FROM berechtigungen WHERE id NOT IN (
            SELECT id FROM (SELECT MAX(id) as id FROM berechtigungen GROUP BY rolle, modul) tmp
        )");
        $db->execute("ALTER TABLE berechtigungen ADD UNIQUE KEY uk_rolle_modul (rolle, modul)");
    } catch (\Throwable $e) { /* UNIQUE KEY bereits vorhanden */ }

    // Berechtigungen für Formationen-Modul (nur beim Erstinstall)
    $formPerm = $db->fetchOne("SELECT COUNT(*) as cnt FROM berechtigungen WHERE modul = 'formationen'");
    if ((int)($formPerm['cnt'] ?? 0) === 0) {
        $db->execute("INSERT INTO berechtigungen (rolle, modul, lesen, schreiben, loeschen) VALUES
            ('admin','formationen',1,1,1),('obmann','formationen',1,0,0),
            ('kapellmeister','formationen',1,0,0),('kassier','formationen',1,0,0),
            ('schriftfuehrer','formationen',1,0,0),('instrumentenwart','formationen',1,0,0),
            ('trachtenwart','formationen',1,0,0),('jugendbeauftragter','formationen',1,0,0),
            ('notenwart','formationen',1,0,0),('mitglied','formationen',1,0,0)");
    }

    // Rolle "extern" für externe Gastmusiker
    $db->execute("INSERT IGNORE INTO rollen (id, name, beschreibung, ist_admin, farbe, sortierung, aktiv)
        VALUES (12, 'extern', 'Externer Gastmusiker', 0, 'secondary', 998, 1)");
    $externPerm = $db->fetchOne("SELECT COUNT(*) as cnt FROM berechtigungen WHERE rolle = 'extern'");
    if ((int)($externPerm['cnt'] ?? 0) === 0) {
        $db->execute("INSERT INTO berechtigungen (rolle, modul, lesen, schreiben, loeschen) VALUES
            ('extern','ausrueckungen',1,0,0),
            ('extern','noten',1,0,0),
            ('extern','formationen',1,0,0)");
    }
})();

// Formations-Kontext für eingeloggte Nicht-Admins sicherstellen (Fallback auf jeder Seite)
if (Session::isLoggedIn() && !Session::isAdmin() && Session::getFormationId() === null) {
    $formIds = Session::getFormationIds();
    if (count($formIds) === 1) {
        Session::setFormationId((int)$formIds[0]);
    }
}

// Für "extern"-Benutzer: Zugriff sperren wenn keine Formation gesetzt werden konnte
if (Session::isLoggedIn() && !Session::isAdmin() && Session::getFormationId() === null) {
    $dbCheck  = Database::getInstance();
    $uid      = Session::getUserId();
    // Rollen direkt aus DB lesen (unabhängig vom Session-Cache)
    $isExtern = $dbCheck->fetchOne(
        "SELECT COUNT(*) as cnt
         FROM benutzer_rollen br
         JOIN rollen r ON br.rolle_id = r.id
         WHERE br.benutzer_id = ? AND r.name = 'extern' AND r.aktiv = 1",
        [$uid]
    );
    if (!empty($isExtern['cnt']) && (int)$isExtern['cnt'] > 0) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        if (!in_array($currentPage, ['login.php', 'logout.php'])) {
            Session::setFlashMessage('danger', 'Kein Zugriff: Ihrem Gastkonto ist keine Formation zugewiesen. Bitte wenden Sie sich an den Administrator.');
            header('Location: login.php');
            exit;
        }
    }
}

// Migration: Instrument-Pattern für Noten-Aufteilung
(function() {
    $db = Database::getInstance();
    $db->execute("CREATE TABLE IF NOT EXISTS noten_instrumente_pattern (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(100)  NOT NULL,
        pattern      VARCHAR(500)  NOT NULL,
        sortierung   INT           NOT NULL DEFAULT 100,
        aktiv        TINYINT(1)    NOT NULL DEFAULT 1,
        beschreibung TEXT          NULL,
        erstellt_am  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $cnt = $db->fetchOne("SELECT COUNT(*) as cnt FROM noten_instrumente_pattern");
    if ((int)($cnt['cnt'] ?? 0) === 0) {
        $defaults = [
            [10,  'Piccolo / Piccoloflöte',   'Piccolo(?:fl[öu]te)?',       'Matcht "Piccolo" und "Piccoloflöte"'],
            [20,  'Querflöte',                 'Querfl[öu]te[n]?',           ''],
            [22,  'Bassflöte',                 'Bassfl[öu]te',               'Vor allgemeiner Flöte einordnen'],
            [25,  'Flöte',                     'Fl[öu]te[n]?',              ''],
            [30,  'Bassklarinette',            'Bassklarinette',             'Vor Klarinette einordnen'],
            [35,  'Klarinette',                'Klarinette[n]?',            ''],
            [40,  'Englischhorn',              'Englisch(?:es)?\s*Horn',    'Matcht "Englischhorn" und "Englisches Horn"'],
            [45,  'Oboe',                      'Oboe[n]?',                  ''],
            [48,  'Kontrafagott',              'Kontrafagott',               'Vor Fagott einordnen'],
            [50,  'Fagott',                    'Fagott[e]?',                ''],
            [55,  'Sopransaxophon',            'Sopransaxophon',            'Vor allgemeinem Saxophon'],
            [56,  'Altsaxophon',               'Altsaxophon',               'Vor allgemeinem Saxophon'],
            [57,  'Tenorsaxophon',             'Tenorsaxophon',             'Vor allgemeinem Saxophon'],
            [58,  'Baritonsaxophon',           'Baritonsaxophon',           'Vor allgemeinem Saxophon'],
            [59,  'Basssaxophon',              'Basssaxophon',              'Vor allgemeinem Saxophon'],
            [60,  'Saxophon',                  'Saxophon[e]?',              ''],
            [70,  'Flügelhorn',                'Fl[üu]gelhorn(?:er)?',     ''],
            [72,  'Cornet',                    'Cornet(?:te)?',             ''],
            [75,  'Trompete',                  'Trompete[n]?',              ''],
            [80,  'Tenorhorn',                 'Tenorhorn(?:er)?',           'Vor Horn einordnen!'],
            [82,  'Waldhorn',                  'Waldhorn(?:er)?',            'Vor Horn einordnen!'],
            [85,  'Horn',                      'Horn(?:er)?',               'Nach Tenorhorn und Waldhorn einordnen'],
            [88,  'Bariton / Baryton',         'Bar[iy]ton[e]?',            'Matcht "Bariton" und österr. "Baryton"'],
            [90,  'Euphonium',                 'Euphonium',                 ''],
            [93,  'Kontrabassposaune',         'Kontrabassposaune',          'Vor Bassposaune einordnen'],
            [95,  'Bassposaune',               'Bassposaune',                'Vor Posaune einordnen'],
            [100, 'Posaune',                   'Posaune[n]?',               ''],
            [103, 'Kontrabasstuba',            'Kontrabasstuba',             'Vor Basstuba und Tuba einordnen'],
            [105, 'Basstuba',                  'Basstuba',                   'Vor Tuba einordnen'],
            [108, 'Tuba',                      'Tuba[s]?',                  ''],
            [110, 'Bass (Schlüssel-Präfix)',   '(?:Es|[A-H])-Bass',         'Matcht "Es-Bass", "B-Bass", "C-Bass" etc.'],
            [112, 'Bass (Schlüssel-Suffix)',   'Bass-(?:Es|[A-H])',          'Matcht "Bass-C", "Bass-B", "Bass-Es" etc.'],
            [115, 'Bass',                      'Bass',                       'Allgemeines Bass-Pattern – nach spezifischeren einordnen'],
            [120, 'Schlagzeug',                'Schlagzeug',                ''],
            [122, 'Drumset',                   'Drumset',                   ''],
            [124, 'Kleine Trommel',            'Kleine\s*Trommel',          ''],
            [126, 'Große Trommel',             'Grosse?\s*Trommel',         'Matcht "Große" und "Grosse Trommel"'],
            [128, 'Pauke',                     'Pauken?',                   ''],
            [130, 'Becken',                    'Becken',                    ''],
            [132, 'Glockenspiel',              'Glockenspiel',              ''],
            [134, 'Xylophon',                  'Xylophon',                  ''],
            [136, 'Vibraphon',                 'Vibraphon',                 ''],
            [138, 'Marimba',                   'Marimba',                   ''],
            [140, 'Percussion',                'Percussion',                ''],
            [150, 'Partitur',                  'Partitur',                  ''],
            [152, 'Direktion',                 'Direktion',                 ''],
            [154, 'Score',                     'Score',                     ''],
            [160, 'Kontrabass',                'Kontrabass',                ''],
            [165, 'Akkordeon',                 'Akkordeon',                 ''],
            [170, 'Klavier',                   'Klavier',                   ''],
            [175, 'Orgel',                     'Orgel',                     ''],
            [180, 'Gitarre',                   'Gitarre',                   ''],
        ];
        foreach ($defaults as [$sort, $name, $pattern, $beschr]) {
            $db->execute(
                "INSERT INTO noten_instrumente_pattern (sortierung, name, pattern, beschreibung) VALUES (?,?,?,?)",
                [$sort, $name, $pattern, $beschr ?: null]
            );
        }
    }
})();

// Probe-Modul (GigSheet Integration)
(function() {
    $db = Database::getInstance();

    $db->execute("CREATE TABLE IF NOT EXISTS noten_stimmen (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        noten_id    INT NOT NULL,
        datei_id    INT NULL,
        name        VARCHAR(100) NOT NULL,
        seite_von   INT NOT NULL DEFAULT 1,
        seite_bis   INT NOT NULL DEFAULT 1,
        register_id INT NULL,
        reihenfolge INT NOT NULL DEFAULT 0,
        UNIQUE KEY uk_noten_stimme (noten_id, name),
        FOREIGN KEY (noten_id) REFERENCES noten(id) ON DELETE CASCADE,
        FOREIGN KEY (datei_id) REFERENCES noten_dateien(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS probe_session (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        noten_id         INT NOT NULL,
        formation_id     INT NULL,
        kapellmeister_id INT NOT NULL,
        aktiv            TINYINT(1) NOT NULL DEFAULT 1,
        gestartet_am     TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (noten_id)         REFERENCES noten(id),
        FOREIGN KEY (kapellmeister_id) REFERENCES benutzer(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS probe_session_spieler (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        session_id   INT NOT NULL,
        benutzer_id  INT NOT NULL,
        stimme_id    INT NULL,
        joined_am    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id)  REFERENCES probe_session(id) ON DELETE CASCADE,
        FOREIGN KEY (benutzer_id) REFERENCES benutzer(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS probe_session_viewer (
        benutzer_id  INT NOT NULL,
        noten_id     INT NOT NULL,
        updated_at   DATETIME NOT NULL,
        PRIMARY KEY (benutzer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS noten_annotationen (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        benutzer_id  INT NOT NULL,
        stimme_id    INT NOT NULL,
        seite_number INT NOT NULL,
        datei_path   VARCHAR(255) NULL,
        erstellt_am  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        geaendert_am TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_annotation (benutzer_id, stimme_id, seite_number),
        FOREIGN KEY (benutzer_id) REFERENCES benutzer(id),
        FOREIGN KEY (stimme_id)   REFERENCES noten_stimmen(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS noten_favoriten (
        benutzer_id INT NOT NULL,
        stimme_id   INT NOT NULL,
        PRIMARY KEY (benutzer_id, stimme_id),
        FOREIGN KEY (benutzer_id) REFERENCES benutzer(id)      ON DELETE CASCADE,
        FOREIGN KEY (stimme_id)   REFERENCES noten_stimmen(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Probe-Berechtigungen (nur beim Erstinstall)
    $probePerm = $db->fetchOne("SELECT COUNT(*) as cnt FROM berechtigungen WHERE modul = 'probe'");
    if ((int)($probePerm['cnt'] ?? 0) === 0) {
        $db->execute("INSERT INTO berechtigungen (rolle, modul, lesen, schreiben, loeschen) VALUES
            ('admin',              'probe', 1, 1, 1),
            ('kapellmeister',      'probe', 1, 1, 1),
            ('jugendbeauftragter', 'probe', 1, 1, 0),
            ('notenwart',          'probe', 1, 1, 0),
            ('obmann',             'probe', 1, 0, 0),
            ('schriftfuehrer',     'probe', 1, 0, 0),
            ('kassier',            'probe', 1, 0, 0),
            ('instrumentenwart',   'probe', 1, 0, 0),
            ('trachtenwart',       'probe', 1, 0, 0),
            ('mitglied',           'probe', 1, 1, 0),
            ('extern',             'probe', 1, 0, 0)");
    }
})();

// Migration: last_seen für Anwesenheitserkennung in Probe-Session
(function() {
    $db = Database::getInstance();
    $col = $db->fetchOne(
        "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'probe_session_spieler' AND COLUMN_NAME = 'last_seen'"
    );
    if ((int)($col['cnt'] ?? 0) === 0) {
        $db->execute("ALTER TABLE probe_session_spieler ADD COLUMN last_seen DATETIME NULL");
    }
})();

// Notenbücher
(function() {
    $db = Database::getInstance();

    $db->execute("CREATE TABLE IF NOT EXISTS notenbucher (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(150) NOT NULL,
        beschreibung TEXT NULL,
        benutzer_id  INT NOT NULL,
        typ          ENUM('privat','geteilt') NOT NULL DEFAULT 'privat',
        formation_id INT NULL,
        erstellt_am  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (benutzer_id) REFERENCES benutzer(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS notenbuch_noten (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        notenbuch_id INT NOT NULL,
        noten_id     INT NOT NULL,
        reihenfolge  INT NOT NULL DEFAULT 0,
        UNIQUE KEY uk_notenbuch_note (notenbuch_id, noten_id),
        FOREIGN KEY (notenbuch_id) REFERENCES notenbucher(id) ON DELETE CASCADE,
        FOREIGN KEY (noten_id)     REFERENCES noten(id)       ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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

// Migration: Festverwaltung-Tabellen (bislang nur in database.sql, nie für Bestandsinstallationen
// nachgezogen worden – siehe fest_todos.php-Absturz "Table 'fest_todos' doesn't exist")
(function() {
    $db = Database::getInstance();

    $db->execute("CREATE TABLE IF NOT EXISTS feste (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(100) NOT NULL,
        jahr         YEAR NOT NULL,
        datum_von    DATE NOT NULL,
        datum_bis    DATE NULL,
        ort          VARCHAR(100) NULL,
        adresse      VARCHAR(255) NULL,
        beschreibung TEXT NULL,
        status       ENUM('geplant','aktiv','abgeschlossen','abgesagt') NOT NULL DEFAULT 'geplant',
        erstellt_von INT NULL,
        erstellt_am  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY erstellt_von (erstellt_von)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS fest_einkauf_kategorien (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(80) NOT NULL,
        sortierung  INT NOT NULL DEFAULT 0,
        erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS fest_stationen (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        fest_id           INT NOT NULL,
        name              VARCHAR(100) NOT NULL,
        beschreibung      TEXT NULL,
        oeffnung_von      TIME NULL,
        oeffnung_bis      TIME NULL,
        benoetigte_helfer INT NOT NULL DEFAULT 1,
        farbe             VARCHAR(20) NULL DEFAULT 'primary',
        sortierung        INT NOT NULL DEFAULT 0,
        erstellt_am       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fest_id) REFERENCES feste(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS fest_mitarbeiter (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        fest_id      INT NOT NULL,
        mitglied_id  INT NULL,
        vorname      VARCHAR(50) NULL,
        nachname     VARCHAR(50) NULL,
        telefon      VARCHAR(30) NULL,
        email        VARCHAR(100) NULL,
        funktion     VARCHAR(100) NULL,
        ist_extern   TINYINT(1) NOT NULL DEFAULT 0,
        notizen      TEXT NULL,
        erstellt_am  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fest_id)     REFERENCES feste(id) ON DELETE CASCADE,
        FOREIGN KEY (mitglied_id) REFERENCES mitglieder(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS fest_station_tage (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        station_id        INT NOT NULL,
        datum             DATE NOT NULL,
        aktiv             TINYINT(1) NOT NULL DEFAULT 1,
        oeffnung_von      TIME NULL,
        oeffnung_bis      TIME NULL,
        benoetigte_helfer INT NOT NULL DEFAULT 1,
        UNIQUE KEY station_datum (station_id, datum),
        FOREIGN KEY (station_id) REFERENCES fest_stationen(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS fest_dienstplaene (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        fest_id        INT NOT NULL,
        station_id     INT NOT NULL,
        mitarbeiter_id INT NOT NULL,
        datum          DATE NOT NULL,
        zeit_von       TIME NULL,
        zeit_bis       TIME NULL,
        notizen        TEXT NULL,
        erstellt_am    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fest_id)        REFERENCES feste(id) ON DELETE CASCADE,
        FOREIGN KEY (station_id)     REFERENCES fest_stationen(id) ON DELETE CASCADE,
        FOREIGN KEY (mitarbeiter_id) REFERENCES fest_mitarbeiter(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // preis_gesamt (NICHT preis) - muss zum von classes/Fest.php, FestEinkauf.php,
    // FestKopieren.php und FestAbrechnung.php erwarteten Spaltennamen passen.
    $db->execute("CREATE TABLE IF NOT EXISTS fest_einkauefe (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        fest_id       INT NOT NULL,
        kategorie_id  INT NULL,
        station_id    INT NULL,
        bezeichnung   VARCHAR(255) NOT NULL,
        menge         INT NOT NULL DEFAULT 1,
        einheit       VARCHAR(20) NULL,
        preis_gesamt  DECIMAL(10,2) NULL,
        lieferant     VARCHAR(100) NULL,
        status        ENUM('geplant','bestellt','erhalten','storniert') NOT NULL DEFAULT 'geplant',
        ist_vorlage   TINYINT(1) NOT NULL DEFAULT 0,
        notizen       TEXT NULL,
        erstellt_von  INT NULL,
        erstellt_am   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fest_id)      REFERENCES feste(id) ON DELETE CASCADE,
        FOREIGN KEY (kategorie_id) REFERENCES fest_einkauf_kategorien(id) ON DELETE SET NULL,
        FOREIGN KEY (station_id)   REFERENCES fest_stationen(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // zahlungsstatus nutzt 'teilweise' (NICHT 'angezahlt') - siehe classes/FestVertrag.php
    // und fest_vertrag_bearbeiten.php.
    $db->execute("CREATE TABLE IF NOT EXISTS fest_vertraege (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        fest_id        INT NOT NULL,
        band_name      VARCHAR(150) NOT NULL,
        vertrags_datum DATE NULL,
        auftritt_datum DATE NULL,
        auftritt_zeit  TIME NULL,
        honorar        DECIMAL(10,2) NULL,
        zahlungsstatus ENUM('offen','teilweise','bezahlt','storniert') NOT NULL DEFAULT 'offen',
        zahlungsdatum  DATE NULL,
        dokument_pfad  VARCHAR(255) NULL,
        dokument_name  VARCHAR(255) NULL,
        notizen        TEXT NULL,
        erstellt_von   INT NULL,
        erstellt_am    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fest_id) REFERENCES feste(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // status nutzt 'in_arbeit'/'abgebrochen' (NICHT 'in_bearbeitung') - siehe
    // classes/FestTodo.php::updateStatus() und die fest_todos*.php Toggle-Logik.
    $db->execute("CREATE TABLE IF NOT EXISTS fest_todos (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        fest_id       INT NOT NULL,
        titel         VARCHAR(255) NOT NULL,
        beschreibung  TEXT NULL,
        prioritaet    ENUM('niedrig','normal','hoch','kritisch') NOT NULL DEFAULT 'normal',
        status        ENUM('offen','in_arbeit','erledigt','abgebrochen') NOT NULL DEFAULT 'offen',
        faellig_am    DATE NULL,
        zustaendig_id INT NULL,
        erstellt_von  INT NULL,
        erstellt_am   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        erledigt_am   DATETIME NULL,
        FOREIGN KEY (fest_id)       REFERENCES feste(id) ON DELETE CASCADE,
        FOREIGN KEY (zustaendig_id) REFERENCES benutzer(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->execute("CREATE TABLE IF NOT EXISTS fest_abrechnung_posten (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        fest_id      INT NOT NULL,
        typ          ENUM('einnahme','ausgabe') NOT NULL,
        kategorie    VARCHAR(80) NOT NULL DEFAULT 'sonstiges',
        bezeichnung  VARCHAR(255) NOT NULL,
        betrag       DECIMAL(10,2) NOT NULL DEFAULT 0,
        station_id   INT NULL,
        notizen      TEXT NULL,
        erstellt_von INT NULL,
        erstellt_am  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fest_id)    REFERENCES feste(id) ON DELETE CASCADE,
        FOREIGN KEY (station_id) REFERENCES fest_stationen(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // aktualisiert_am fehlte in der ursprünglichen CREATE-TABLE-Definition, wird aber von
    // fest_detail.php angezeigt und soll sich bei jedem UPDATE automatisch aktualisieren.
    try {
        $db->execute("ALTER TABLE feste ADD COLUMN aktualisiert_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER erstellt_am");
    } catch (\Throwable $e) { /* Spalte existiert bereits */ }

    // Berechtigungen für Festverwaltung-Modul (nur beim Erstinstall)
    $festPerm = $db->fetchOne("SELECT COUNT(*) as cnt FROM berechtigungen WHERE modul = 'fest'");
    if ((int)($festPerm['cnt'] ?? 0) === 0) {
        $db->execute("INSERT INTO berechtigungen (rolle, modul, lesen, schreiben, loeschen) VALUES
            ('admin','fest',1,1,1),('obmann','fest',1,1,1),
            ('kassier','fest',1,1,0),('schriftfuehrer','fest',1,1,0),
            ('jugendbeauftragter','fest',1,1,0),('kapellmeister','fest',1,0,0),
            ('notenwart','fest',1,0,0),('instrumentenwart','fest',1,0,0),
            ('trachtenwart','fest',1,0,0),('mitglied','fest',1,0,0)");
    }
})();
