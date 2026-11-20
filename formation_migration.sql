-- Formation Migration
-- Mehrere Formationen pro Verein (Blasorchester, Jugendkapelle, Showband etc.)

-- 1. Formationen-Tabelle
CREATE TABLE IF NOT EXISTS formationen (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    kuerzel     VARCHAR(10)  NULL,
    farbe       VARCHAR(7)   NOT NULL DEFAULT '#4471A3',
    beschreibung TEXT        NULL,
    aktiv       TINYINT(1)  NOT NULL DEFAULT 1,
    erstellt_am TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Mitglieder ↔ Formationen (n:m)
CREATE TABLE IF NOT EXISTS mitglied_formationen (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    mitglied_id  INT  NOT NULL,
    formation_id INT  NOT NULL,
    rolle        VARCHAR(50) NULL,
    seit_datum   DATE        NULL,
    UNIQUE KEY uk_mitglied_formation (mitglied_id, formation_id),
    CONSTRAINT fk_mf_mitglied  FOREIGN KEY (mitglied_id)  REFERENCES mitglieder(id)  ON DELETE CASCADE,
    CONSTRAINT fk_mf_formation FOREIGN KEY (formation_id) REFERENCES formationen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. formation_id zu ausrueckungen (NULL = alle Formationen)
ALTER TABLE ausrueckungen
    ADD COLUMN IF NOT EXISTS formation_id INT NULL AFTER id,
    ADD CONSTRAINT fk_ausrueckungen_formation FOREIGN KEY (formation_id) REFERENCES formationen(id) ON DELETE SET NULL;

-- 4. formation_id zu noten (NULL = alle Formationen)
ALTER TABLE noten
    ADD COLUMN IF NOT EXISTS formation_id INT NULL AFTER id,
    ADD CONSTRAINT fk_noten_formation FOREIGN KEY (formation_id) REFERENCES formationen(id) ON DELETE SET NULL;

-- 5. formation_id zu finanzen (NULL = Vereinskasse, kein Formations-Filter)
ALTER TABLE finanzen
    ADD COLUMN IF NOT EXISTS formation_id INT NULL AFTER id,
    ADD CONSTRAINT fk_finanzen_formation FOREIGN KEY (formation_id) REFERENCES formationen(id) ON DELETE SET NULL;

-- 6. Berechtigungen für Formationen-Modul
INSERT IGNORE INTO berechtigungen (rolle, modul, lesen, schreiben, loeschen) VALUES
    ('admin',               'formationen', 1, 1, 1),
    ('obmann',              'formationen', 1, 0, 0),
    ('kapellmeister',       'formationen', 1, 0, 0),
    ('kassier',             'formationen', 1, 0, 0),
    ('schriftfuehrer',      'formationen', 1, 0, 0),
    ('instrumentenwart',    'formationen', 1, 0, 0),
    ('trachtenwart',        'formationen', 1, 0, 0),
    ('jugendbeauftragter',  'formationen', 1, 0, 0),
    ('notenwart',           'formationen', 1, 0, 0),
    ('mitglied',            'formationen', 1, 0, 0);
