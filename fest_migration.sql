-- ============================================================
-- SYNCOPA - Festverwaltung Migration
-- Version 1.0 | 2026
-- Ausführen via: mysql -u user -p dbname < fest_migration.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. feste (Hauptdatensatz pro Fest)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feste` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(200)  NOT NULL,
  `jahr`            INT(4)        NOT NULL,
  `datum_von`       DATE          NOT NULL,
  `datum_bis`       DATE          DEFAULT NULL,
  `ort`             VARCHAR(200)  DEFAULT NULL,
  `adresse`         VARCHAR(250)  DEFAULT NULL,
  `beschreibung`    TEXT          DEFAULT NULL,
  `status`          ENUM('geplant','aktiv','abgeschlossen','abgesagt') NOT NULL DEFAULT 'geplant',
  `erstellt_von`    INT(11)       DEFAULT NULL,
  `erstellt_am`     DATETIME      NOT NULL DEFAULT current_timestamp(),
  `aktualisiert_am` DATETIME      NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_feste_jahr` (`jahr`),
  KEY `idx_feste_status` (`status`),
  KEY `idx_feste_erstellt_von` (`erstellt_von`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. fest_stationen (Stände/Bereiche eines Festes)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fest_stationen` (
  `id`                INT(11)       NOT NULL AUTO_INCREMENT,
  `fest_id`           INT(11)       NOT NULL,
  `name`              VARCHAR(150)  NOT NULL,
  `beschreibung`      TEXT          DEFAULT NULL,
  `benoetigte_helfer` INT(11)       NOT NULL DEFAULT 1,
  `oeffnung_von`      TIME          DEFAULT NULL,
  `oeffnung_bis`      TIME          DEFAULT NULL,
  `sortierung`        INT(11)       NOT NULL DEFAULT 100,
  `erstellt_am`       DATETIME      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fest_stationen_fest` (`fest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. fest_mitarbeiter (Helfer/Mitarbeiter pro Fest)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fest_mitarbeiter` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `fest_id`       INT(11)       NOT NULL,
  `mitglied_id`   INT(11)       DEFAULT NULL,
  `vorname`       VARCHAR(100)  DEFAULT NULL,
  `nachname`      VARCHAR(100)  DEFAULT NULL,
  `telefon`       VARCHAR(30)   DEFAULT NULL,
  `email`         VARCHAR(100)  DEFAULT NULL,
  `funktion`      VARCHAR(100)  DEFAULT NULL,
  `ist_extern`    TINYINT(1)    NOT NULL DEFAULT 0,
  `notizen`       TEXT          DEFAULT NULL,
  `erstellt_am`   DATETIME      NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fest_mitarbeiter_fest` (`fest_id`),
  KEY `idx_fest_mitarbeiter_mitglied` (`mitglied_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. fest_dienstplaene (Schichtplan: Wer wann an welcher Station)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fest_dienstplaene` (
  `id`             INT(11)   NOT NULL AUTO_INCREMENT,
  `fest_id`        INT(11)   NOT NULL,
  `station_id`     INT(11)   NOT NULL,
  `mitarbeiter_id` INT(11)   NOT NULL,
  `datum`          DATE      NOT NULL,
  `zeit_von`       TIME      NOT NULL,
  `zeit_bis`       TIME      NOT NULL,
  `notizen`        TEXT      DEFAULT NULL,
  `erstellt_am`    DATETIME  NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fest_dienstplaene_fest` (`fest_id`),
  KEY `idx_fest_dienstplaene_station` (`station_id`),
  KEY `idx_fest_dienstplaene_mitarbeiter` (`mitarbeiter_id`),
  KEY `idx_fest_dienstplaene_datum` (`datum`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. fest_einkauf_kategorien (Kategorie-Lookup für Einkäufe)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fest_einkauf_kategorien` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)  NOT NULL,
  `sortierung`  INT(11)       NOT NULL DEFAULT 100,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `fest_einkauf_kategorien` (`name`, `sortierung`) VALUES
('Lebensmittel', 10),
('Getränke', 20),
('Material', 30),
('Technik', 40),
('Dekoration', 50),
('Personal/Verpflegung', 60),
('Sonstiges', 100);

-- ------------------------------------------------------------
-- 6. fest_einkauefe (Einkaufsliste pro Fest)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fest_einkauefe` (
  `id`              INT(11)         NOT NULL AUTO_INCREMENT,
  `fest_id`         INT(11)         NOT NULL,
  `kategorie_id`    INT(11)         DEFAULT NULL,
  `bezeichnung`     VARCHAR(200)    NOT NULL,
  `menge`           DECIMAL(10,3)   DEFAULT NULL,
  `einheit`         VARCHAR(30)     DEFAULT NULL,
  `preis_gesamt`    DECIMAL(10,2)   DEFAULT NULL,
  `lieferant`       VARCHAR(150)    DEFAULT NULL,
  `status`          ENUM('geplant','bestellt','erhalten','storniert') NOT NULL DEFAULT 'geplant',
  `ist_vorlage`     TINYINT(1)      NOT NULL DEFAULT 0,
  `notizen`         TEXT            DEFAULT NULL,
  `erstellt_von`    INT(11)         DEFAULT NULL,
  `erstellt_am`     DATETIME        NOT NULL DEFAULT current_timestamp(),
  `aktualisiert_am` DATETIME        NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fest_einkauefe_fest` (`fest_id`),
  KEY `idx_fest_einkauefe_kategorie` (`kategorie_id`),
  KEY `idx_fest_einkauefe_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. fest_vertraege (Verträge mit Bands/Gruppen)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fest_vertraege` (
  `id`               INT(11)       NOT NULL AUTO_INCREMENT,
  `fest_id`          INT(11)       NOT NULL,
  `band_name`        VARCHAR(200)  NOT NULL,
  `vertrags_datum`   DATE          DEFAULT NULL,
  `auftritt_datum`   DATE          DEFAULT NULL,
  `auftritt_zeit`    TIME          DEFAULT NULL,
  `honorar`          DECIMAL(10,2) DEFAULT NULL,
  `zahlungsstatus`   ENUM('offen','teilweise','bezahlt','storniert') NOT NULL DEFAULT 'offen',
  `zahlungsdatum`    DATE          DEFAULT NULL,
  `dokument_pfad`    VARCHAR(500)  DEFAULT NULL,
  `dokument_name`    VARCHAR(255)  DEFAULT NULL,
  `notizen`          TEXT          DEFAULT NULL,
  `erstellt_von`     INT(11)       DEFAULT NULL,
  `erstellt_am`      DATETIME      NOT NULL DEFAULT current_timestamp(),
  `aktualisiert_am`  DATETIME      NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fest_vertraege_fest` (`fest_id`),
  KEY `idx_fest_vertraege_zahlungsstatus` (`zahlungsstatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. fest_todos (Aufgaben/Todos pro Fest)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fest_todos` (
  `id`              INT(11)       NOT NULL AUTO_INCREMENT,
  `fest_id`         INT(11)       NOT NULL,
  `titel`           VARCHAR(200)  NOT NULL,
  `beschreibung`    TEXT          DEFAULT NULL,
  `faellig_am`      DATE          DEFAULT NULL,
  `zustaendig_id`   INT(11)       DEFAULT NULL,
  `status`          ENUM('offen','in_arbeit','erledigt','abgebrochen') NOT NULL DEFAULT 'offen',
  `prioritaet`      ENUM('niedrig','normal','hoch','kritisch') NOT NULL DEFAULT 'normal',
  `erstellt_von`    INT(11)       DEFAULT NULL,
  `erstellt_am`     DATETIME      NOT NULL DEFAULT current_timestamp(),
  `aktualisiert_am` DATETIME      NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fest_todos_fest` (`fest_id`),
  KEY `idx_fest_todos_zustaendig` (`zustaendig_id`),
  KEY `idx_fest_todos_status` (`status`),
  KEY `idx_fest_todos_prioritaet` (`prioritaet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Foreign Keys
-- ------------------------------------------------------------
ALTER TABLE `feste`
  ADD CONSTRAINT `fk_feste_erstellt_von`
    FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL;

ALTER TABLE `fest_stationen`
  ADD CONSTRAINT `fk_fest_stationen_fest`
    FOREIGN KEY (`fest_id`) REFERENCES `feste` (`id`) ON DELETE CASCADE;

ALTER TABLE `fest_mitarbeiter`
  ADD CONSTRAINT `fk_fest_mitarbeiter_fest`
    FOREIGN KEY (`fest_id`) REFERENCES `feste` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fest_mitarbeiter_mitglied`
    FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE SET NULL;

ALTER TABLE `fest_dienstplaene`
  ADD CONSTRAINT `fk_fest_dienstplaene_fest`
    FOREIGN KEY (`fest_id`) REFERENCES `feste` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fest_dienstplaene_station`
    FOREIGN KEY (`station_id`) REFERENCES `fest_stationen` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fest_dienstplaene_mitarbeiter`
    FOREIGN KEY (`mitarbeiter_id`) REFERENCES `fest_mitarbeiter` (`id`) ON DELETE CASCADE;

ALTER TABLE `fest_einkauefe`
  ADD CONSTRAINT `fk_fest_einkauefe_fest`
    FOREIGN KEY (`fest_id`) REFERENCES `feste` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fest_einkauefe_kategorie`
    FOREIGN KEY (`kategorie_id`) REFERENCES `fest_einkauf_kategorien` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fest_einkauefe_erstellt`
    FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL;

ALTER TABLE `fest_vertraege`
  ADD CONSTRAINT `fk_fest_vertraege_fest`
    FOREIGN KEY (`fest_id`) REFERENCES `feste` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fest_vertraege_erstellt`
    FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL;

ALTER TABLE `fest_todos`
  ADD CONSTRAINT `fk_fest_todos_fest`
    FOREIGN KEY (`fest_id`) REFERENCES `feste` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fest_todos_zustaendig`
    FOREIGN KEY (`zustaendig_id`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fest_todos_erstellt`
    FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL;

-- ------------------------------------------------------------
-- Berechtigungen für Modul 'fest'
-- Hinweis: Bestehende Zeilen werden ignoriert (INSERT IGNORE)
-- ------------------------------------------------------------
INSERT IGNORE INTO `berechtigungen` (`rolle`, `modul`, `lesen`, `schreiben`, `loeschen`) VALUES
('admin',              'fest', 1, 1, 1),
('obmann',             'fest', 1, 1, 0),
('kapellmeister',      'fest', 1, 0, 0),
('kassier',            'fest', 1, 1, 0),
('schriftfuehrer',     'fest', 1, 1, 0),
('trachtenwart',       'fest', 1, 0, 0),
('instrumentenwart',   'fest', 1, 0, 0),
('jugendbeauftragter', 'fest', 1, 1, 0),
('notenwart',          'fest', 1, 0, 0),
('mitglied',           'fest', 1, 0, 0),
('user',               'fest', 0, 0, 0);

SET FOREIGN_KEY_CHECKS = 1;
