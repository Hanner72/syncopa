-- ============================================================================
-- SYNCOPA - Musikvereinsverwaltung
-- Datenbank-Schema und Beispieldaten
-- Version 2.0.0
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================================
-- TABELLEN LÖSCHEN (falls vorhanden)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `aktivitaetslog`;
DROP TABLE IF EXISTS `anwesenheit`;
DROP TABLE IF EXISTS `ausrueckung_noten`;
DROP TABLE IF EXISTS `ausrueckungen`;
DROP TABLE IF EXISTS `beitraege`;
DROP TABLE IF EXISTS `benutzer`;
DROP TABLE IF EXISTS `berechtigungen`;
DROP TABLE IF EXISTS `dokumente`;
DROP TABLE IF EXISTS `einstellungen`;
DROP TABLE IF EXISTS `finanzen`;
DROP TABLE IF EXISTS `instrumente`;
DROP TABLE IF EXISTS `instrument_typen`;
DROP TABLE IF EXISTS `instrument_wartungen`;
DROP TABLE IF EXISTS `kalender_termine`;
DROP TABLE IF EXISTS `mitglieder`;
DROP TABLE IF EXISTS `mitglied_instrumente`;
DROP TABLE IF EXISTS `noten`;
DROP TABLE IF EXISTS `register`;
DROP TABLE IF EXISTS `rollen`;
DROP TABLE IF EXISTS `uniformen`;
DROP TABLE IF EXISTS `uniform_ausgaben`;
DROP TABLE IF EXISTS `uniform_kategorien`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- TABELLE: register
-- ============================================================================

CREATE TABLE `register` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `beschreibung` text,
  `sortierung` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `register` (`id`, `name`, `beschreibung`, `sortierung`) VALUES
(1, 'Flöten/Klarinetten', 'Holzbläser - hohe Register', 1),
(2, 'Saxophone', 'Holzbläser - Saxophone', 2),
(3, 'Flügelhörner', 'Blechbläser - hohe Lage', 3),
(4, 'Trompeten', 'Blechbläser - hohe Lage', 4),
(5, 'Tenorhörner', 'Blechbläser - mittlere Lage', 5),
(6, 'Posaunen', 'Blechbläser - tiefe Lage', 6),
(7, 'Tuben', 'Blechbläser - Bass', 7),
(8, 'Schlagwerk', 'Perkussion', 8);

-- ============================================================================
-- TABELLE: instrument_typen
-- ============================================================================

CREATE TABLE `instrument_typen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `register_id` int DEFAULT NULL,
  `beschreibung` text,
  PRIMARY KEY (`id`),
  KEY `register_id` (`register_id`),
  CONSTRAINT `instrument_typen_ibfk_1` FOREIGN KEY (`register_id`) REFERENCES `register` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `instrument_typen` (`id`, `name`, `register_id`, `beschreibung`) VALUES
(1, 'Flöte', 1, 'Querflöte'),
(2, 'Klarinette in Bb', 1, 'Standard-Klarinette'),
(3, 'Bassklarinette', 1, 'Tiefe Klarinette'),
(4, 'Altsaxophon', 2, 'Alt-Saxophon in Es'),
(5, 'Tenorsaxophon', 2, 'Tenor-Saxophon in Bb'),
(6, 'Baritonsaxophon', 2, 'Bariton-Saxophon in Es'),
(7, 'Flügelhorn', 3, 'Flügelhorn in Bb'),
(8, 'Trompete', 4, 'Trompete in Bb'),
(9, 'Tenorhorn', 5, 'Tenorhorn in Bb'),
(10, 'Bariton', 5, 'Bariton/Euphonium'),
(11, 'Posaune', 6, 'Zugposaune'),
(12, 'Bassposaune', 6, 'Bass-Posaune'),
(13, 'Tuba', 7, 'B-Tuba'),
(14, 'Es-Bass', 7, 'Es-Tuba'),
(15, 'Schlagzeug', 8, 'Drum-Set'),
(16, 'Kleine Trommel', 8, 'Snare Drum'),
(17, 'Große Trommel', 8, 'Bass Drum'),
(18, 'Becken', 8, 'Marschbecken'),
(19, 'Lyra', 8, 'Glockenspiel/Lyra');

-- ============================================================================
-- TABELLE: rollen
-- ============================================================================

CREATE TABLE `rollen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `beschreibung` text,
  `ist_admin` tinyint(1) DEFAULT '0',
  `farbe` varchar(20) DEFAULT 'secondary',
  `sortierung` int DEFAULT '100',
  `aktiv` tinyint(1) DEFAULT '1',
  `erstellt_am` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rollen` (`id`, `name`, `beschreibung`, `ist_admin`, `farbe`, `sortierung`, `aktiv`) VALUES
(1, 'admin', 'Administrator mit vollen Rechten', 1, 'danger', 1, 1),
(2, 'obmann', 'Obmann/Vorstand', 0, 'primary', 2, 1),
(3, 'kapellmeister', 'Kapellmeister', 0, 'success', 3, 1),
(4, 'kassier', 'Kassier', 0, 'info', 4, 1),
(5, 'schriftfuehrer', 'Schriftführer', 0, 'warning', 5, 1),
(6, 'trachtenwart', 'Trachtenwart', 0, 'purple', 6, 1),
(7, 'instrumentenwart', 'Instrumentenwart', 0, 'dark', 7, 1),
(8, 'jugendbeauftragter', 'Jugendbeauftragter', 0, 'info', 8, 1),
(9, 'mitglied', 'Normales Mitglied', 0, 'secondary', 999, 1);

-- ============================================================================
-- TABELLE: benutzer
-- ============================================================================

CREATE TABLE `benutzer` (
  `id` int NOT NULL AUTO_INCREMENT,
  `benutzername` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `passwort_hash` varchar(255) NOT NULL,
  `rolle` enum('admin','obmann','kassier','schriftfuehrer','mitglied','instrumentenwart','kapellmeister','trachtenwart','jugendbeauftragter') DEFAULT 'mitglied',
  `rolle_id` int DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT '1',
  `letzter_login` datetime DEFAULT NULL,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  `aktualisiert_am` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `mitglied_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `benutzername` (`benutzername`),
  UNIQUE KEY `email` (`email`),
  KEY `rolle_id` (`rolle_id`),
  KEY `mitglied_id` (`mitglied_id`),
  CONSTRAINT `benutzer_ibfk_1` FOREIGN KEY (`rolle_id`) REFERENCES `rollen` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standard-Admin: Benutzername: admin, Passwort: admin123
-- Hash generiert mit: password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO `benutzer` (`id`, `benutzername`, `email`, `passwort_hash`, `rolle`, `rolle_id`, `aktiv`) VALUES
(1, 'admin', 'admin@musikverein.at', '$2a$12$V3H/Yxfr3hyjpyE299xFLuVdUofhCSep7Knduu4.AN/LzXwfyHcRm', 'admin', 1, 1),
(2, 'obmann', 'obmann@musikverein.at', '$2a$12$V3H/Yxfr3hyjpyE299xFLuVdUofhCSep7Knduu4.AN/LzXwfyHcRm', 'obmann', 2, 1),
(3, 'kapellmeister', 'kapellmeister@musikverein.at', '$2a$12$V3H/Yxfr3hyjpyE299xFLuVdUofhCSep7Knduu4.AN/LzXwfyHcRm', 'kapellmeister', 3, 1),
(4, 'kassier', 'kassier@musikverein.at', '$2a$12$V3H/Yxfr3hyjpyE299xFLuVdUofhCSep7Knduu4.AN/LzXwfyHcRm', 'kassier', 4, 1);

-- ============================================================================
-- TABELLE: mitglieder
-- ============================================================================

CREATE TABLE `mitglieder` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mitgliedsnummer` varchar(20) DEFAULT NULL,
  `vorname` varchar(100) NOT NULL,
  `nachname` varchar(100) NOT NULL,
  `geburtsdatum` date DEFAULT NULL,
  `geschlecht` enum('m','w','d') DEFAULT 'd',
  `strasse` varchar(150) DEFAULT NULL,
  `plz` varchar(10) DEFAULT NULL,
  `ort` varchar(100) DEFAULT NULL,
  `land` varchar(50) DEFAULT 'Österreich',
  `telefon` varchar(30) DEFAULT NULL,
  `mobil` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `benutzer_id` int DEFAULT NULL,
  `register_id` int DEFAULT NULL,
  `eintritt_datum` date DEFAULT NULL,
  `austritt_datum` date DEFAULT NULL,
  `status` enum('aktiv','passiv','ausgetreten','ehrenmitglied') DEFAULT 'aktiv',
  `notizen` text,
  `foto` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  `aktualisiert_am` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `benutzer_id` (`benutzer_id`),
  KEY `register_id` (`register_id`),
  CONSTRAINT `mitglieder_ibfk_1` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mitglieder_ibfk_2` FOREIGN KEY (`register_id`) REFERENCES `register` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `mitglieder` (`id`, `mitgliedsnummer`, `vorname`, `nachname`, `geburtsdatum`, `geschlecht`, `strasse`, `plz`, `ort`, `telefon`, `mobil`, `email`, `register_id`, `eintritt_datum`, `status`) VALUES
(1, 'MV-001', 'Johann', 'Huber', '1985-03-15', 'm', 'Hauptstraße 12', '8010', 'Graz', '+43 316 123456', '+43 664 1234567', 'johann.huber@email.at', 3, '2010-09-01', 'aktiv'),
(2, 'MV-002', 'Maria', 'Gruber', '1990-07-22', 'w', 'Kirchgasse 5', '8020', 'Graz', '+43 316 234567', '+43 664 2345678', 'maria.gruber@email.at', 1, '2015-01-15', 'aktiv'),
(3, 'MV-003', 'Stefan', 'Maier', '1978-11-30', 'm', 'Feldweg 8', '8045', 'Graz-Andritz', '+43 316 345678', '+43 664 3456789', 'stefan.maier@email.at', 4, '2008-03-01', 'aktiv'),
(4, 'MV-004', 'Anna', 'Berger', '1995-05-10', 'w', 'Lindenstraße 23', '8010', 'Graz', NULL, '+43 664 4567890', 'anna.berger@email.at', 2, '2018-09-01', 'aktiv'),
(5, 'MV-005', 'Thomas', 'Schwarz', '1982-09-05', 'm', 'Bachgasse 17', '8020', 'Graz', '+43 316 567890', '+43 664 5678901', 'thomas.schwarz@email.at', 5, '2012-06-01', 'aktiv'),
(6, 'MV-006', 'Elisabeth', 'Wagner', '1988-12-20', 'w', 'Mozartstraße 4', '8010', 'Graz', NULL, '+43 664 6789012', 'elisabeth.wagner@email.at', 6, '2014-02-15', 'aktiv'),
(7, 'MV-007', 'Michael', 'Bauer', '1975-06-18', 'm', 'Schlossallee 9', '8045', 'Graz-Andritz', '+43 316 678901', '+43 664 7890123', 'michael.bauer@email.at', 7, '2005-01-01', 'aktiv'),
(8, 'MV-008', 'Sabine', 'Fischer', '1992-02-28', 'w', 'Gartenweg 11', '8020', 'Graz', NULL, '+43 664 8901234', 'sabine.fischer@email.at', 8, '2017-09-01', 'aktiv'),
(9, 'MV-009', 'Franz', 'Hofer', '1965-04-12', 'm', 'Waldstraße 33', '8010', 'Graz', '+43 316 789012', '+43 664 9012345', 'franz.hofer@email.at', 3, '1990-01-01', 'ehrenmitglied'),
(10, 'MV-010', 'Katharina', 'Müller', '1998-08-08', 'w', 'Schulstraße 7', '8045', 'Graz-Andritz', NULL, '+43 664 0123456', 'katharina.mueller@email.at', 1, '2020-09-01', 'aktiv'),
(11, 'MV-011', 'Robert', 'Steiner', '1970-01-25', 'm', 'Bergstraße 15', '8020', 'Graz', '+43 316 890123', NULL, 'robert.steiner@email.at', 5, '1995-03-01', 'passiv'),
(12, 'MV-012', 'Lisa', 'Winkler', '2005-11-14', 'w', 'Sonnenweg 2', '8010', 'Graz', NULL, '+43 664 1122334', 'lisa.winkler@email.at', 2, '2022-09-01', 'aktiv');

-- ============================================================================
-- TABELLE: mitglied_instrumente
-- ============================================================================

CREATE TABLE `mitglied_instrumente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mitglied_id` int NOT NULL,
  `instrument_typ_id` int NOT NULL,
  `hauptinstrument` tinyint(1) DEFAULT '0',
  `seit_datum` date DEFAULT NULL,
  `bis_datum` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mitglied_id` (`mitglied_id`),
  KEY `instrument_typ_id` (`instrument_typ_id`),
  CONSTRAINT `mitglied_instrumente_ibfk_1` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mitglied_instrumente_ibfk_2` FOREIGN KEY (`instrument_typ_id`) REFERENCES `instrument_typen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `mitglied_instrumente` (`mitglied_id`, `instrument_typ_id`, `hauptinstrument`, `seit_datum`) VALUES
(1, 7, 1, '2010-09-01'),
(1, 8, 0, '2015-01-01'),
(2, 1, 1, '2015-01-15'),
(2, 2, 0, '2018-01-01'),
(3, 8, 1, '2008-03-01'),
(4, 4, 1, '2018-09-01'),
(5, 9, 1, '2012-06-01'),
(5, 10, 0, '2016-01-01'),
(6, 11, 1, '2014-02-15'),
(7, 13, 1, '2005-01-01'),
(8, 15, 1, '2017-09-01'),
(8, 16, 0, '2019-01-01'),
(9, 7, 1, '1990-01-01'),
(10, 2, 1, '2020-09-01'),
(11, 10, 1, '1995-03-01'),
(12, 5, 1, '2022-09-01');

-- ============================================================================
-- TABELLE: instrumente
-- ============================================================================

CREATE TABLE `instrumente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `inventar_nummer` varchar(50) NOT NULL,
  `instrument_typ_id` int NOT NULL,
  `hersteller` varchar(100) DEFAULT NULL,
  `modell` varchar(100) DEFAULT NULL,
  `seriennummer` varchar(100) DEFAULT NULL,
  `baujahr` int DEFAULT NULL,
  `anschaffungsdatum` date DEFAULT NULL,
  `anschaffungspreis` decimal(10,2) DEFAULT NULL,
  `zustand` enum('sehr gut','gut','befriedigend','schlecht','defekt') DEFAULT 'gut',
  `standort` varchar(100) DEFAULT NULL,
  `versicherungswert` decimal(10,2) DEFAULT NULL,
  `mitglied_id` int DEFAULT NULL,
  `ausgeliehen_seit` date DEFAULT NULL,
  `notizen` text,
  `foto` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  `aktualisiert_am` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `instrument_typ_id` (`instrument_typ_id`),
  KEY `mitglied_id` (`mitglied_id`),
  CONSTRAINT `instrumente_ibfk_1` FOREIGN KEY (`instrument_typ_id`) REFERENCES `instrument_typen` (`id`),
  CONSTRAINT `instrumente_ibfk_2` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `instrumente` (`inventar_nummer`, `instrument_typ_id`, `hersteller`, `modell`, `seriennummer`, `baujahr`, `anschaffungsdatum`, `anschaffungspreis`, `zustand`, `standort`, `versicherungswert`, `mitglied_id`, `ausgeliehen_seit`) VALUES
('INV-001', 7, 'Miraphone', 'Loimayr', 'M123456', 2018, '2018-06-15', 4500.00, 'sehr gut', 'Probelokal', 4000.00, 1, '2018-09-01'),
('INV-002', 7, 'Miraphone', 'Premium', 'M234567', 2015, '2015-03-20', 3800.00, 'gut', 'Probelokal', 3200.00, NULL, NULL),
('INV-003', 8, 'Yamaha', 'YTR-4335G', 'Y345678', 2020, '2020-01-10', 1200.00, 'sehr gut', 'Probelokal', 1100.00, 3, '2020-02-01'),
('INV-004', 8, 'Bach', 'Stradivarius', 'B456789', 2012, '2012-09-01', 2500.00, 'gut', 'Probelokal', 2000.00, NULL, NULL),
('INV-005', 1, 'Yamaha', 'YFL-212', 'Y567890', 2019, '2019-08-15', 800.00, 'sehr gut', 'Probelokal', 700.00, 2, '2019-09-01'),
('INV-006', 2, 'Buffet Crampon', 'E11', 'BC678901', 2017, '2017-06-01', 1500.00, 'gut', 'Probelokal', 1300.00, 10, '2020-09-01'),
('INV-007', 13, 'Miraphone', 'Hagen 495', 'M789012', 2010, '2010-04-20', 8500.00, 'gut', 'Probelokal', 7000.00, 7, '2010-05-01'),
('INV-008', 15, 'Sonor', 'Force 3007', 'S890123', 2016, '2016-11-30', 2200.00, 'gut', 'Probelokal', 1800.00, 8, '2017-09-01'),
('INV-009', 4, 'Selmer', 'AS42', 'SE901234', 2021, '2021-02-15', 3500.00, 'sehr gut', 'Probelokal', 3200.00, 4, '2021-03-01'),
('INV-010', 11, 'Conn', '88H', 'C012345', 2014, '2014-07-10', 2800.00, 'befriedigend', 'Probelokal', 2000.00, 6, '2014-09-01');

-- ============================================================================
-- TABELLE: instrument_wartungen
-- ============================================================================

CREATE TABLE `instrument_wartungen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `instrument_id` int NOT NULL,
  `datum` date NOT NULL,
  `art` enum('Wartung','Reparatur','Überholung','Reinigung') NOT NULL,
  `beschreibung` text,
  `kosten` decimal(10,2) DEFAULT NULL,
  `durchgefuehrt_von` varchar(100) DEFAULT NULL,
  `naechste_wartung` date DEFAULT NULL,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `instrument_id` (`instrument_id`),
  CONSTRAINT `instrument_wartungen_ibfk_1` FOREIGN KEY (`instrument_id`) REFERENCES `instrumente` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `instrument_wartungen` (`instrument_id`, `datum`, `art`, `beschreibung`, `kosten`, `durchgefuehrt_von`, `naechste_wartung`) VALUES
(1, '2024-06-15', 'Wartung', 'Jährliche Wartung, Ventilöl gewechselt', 85.00, 'Musikhaus Graz', '2025-06-15'),
(3, '2024-09-20', 'Reinigung', 'Generalreinigung nach Festsaison', 45.00, 'Musikhaus Graz', NULL),
(7, '2024-03-10', 'Überholung', 'Komplette Überholung mit Neulackierung', 650.00, 'Instrumentenbau Müller', '2027-03-10'),
(8, '2024-11-05', 'Reparatur', 'Fell der Snare Drum ersetzt', 120.00, 'Drumshop Wien', NULL);

-- ============================================================================
-- TABELLE: noten
-- ============================================================================

CREATE TABLE `noten` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titel` varchar(200) NOT NULL,
  `untertitel` varchar(200) DEFAULT NULL,
  `komponist` varchar(150) DEFAULT NULL,
  `arrangeur` varchar(150) DEFAULT NULL,
  `verlag` varchar(100) DEFAULT NULL,
  `besetzung` varchar(100) DEFAULT NULL,
  `schwierigkeitsgrad` enum('1','2','3','4','5','6') DEFAULT '3',
  `dauer_minuten` int DEFAULT NULL,
  `genre` varchar(50) DEFAULT NULL,
  `archiv_nummer` varchar(50) DEFAULT NULL,
  `anzahl_stimmen` int DEFAULT NULL,
  `zustand` enum('sehr gut','gut','befriedigend','schlecht') DEFAULT 'gut',
  `bemerkungen` text,
  `standort` varchar(100) DEFAULT NULL,
  `pdf_datei` varchar(255) DEFAULT NULL,
  `audio_datei` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  `aktualisiert_am` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `noten` (`titel`, `untertitel`, `komponist`, `arrangeur`, `verlag`, `besetzung`, `schwierigkeitsgrad`, `dauer_minuten`, `genre`, `archiv_nummer`, `anzahl_stimmen`, `zustand`, `standort`) VALUES
('Böhmische Liebe', 'Polka', 'Ernst Mosch', 'Franz Watz', 'Rundel', 'Blasorchester', '3', 3, 'Polka', 'A-001', 28, 'gut', 'Schrank A'),
('Egerländer Musikantenmarsch', NULL, 'Ernst Mosch', NULL, 'Rundel', 'Blasorchester', '3', 4, 'Marsch', 'A-002', 25, 'sehr gut', 'Schrank A'),
('Mein Heimatland', 'Konzertwalzer', 'Julius Fučík', 'Siegfried Rundel', 'Rundel', 'Blasorchester', '4', 8, 'Walzer', 'A-003', 30, 'gut', 'Schrank A'),
('Glückauf!', 'Marsch', 'Carl Latann', NULL, 'Edition Helbling', 'Blasorchester', '2', 4, 'Marsch', 'A-004', 22, 'gut', 'Schrank A'),
('Vogelwiese', 'Polka', 'Guido Henn', NULL, 'Musictown', 'Blasorchester', '3', 3, 'Polka', 'B-001', 25, 'sehr gut', 'Schrank B'),
('Highland Cathedral', NULL, 'Michael Korb', 'Hans-Joachim Rhinow', 'Obrasso', 'Blasorchester', '4', 5, 'Konzertstück', 'B-002', 28, 'gut', 'Schrank B'),
('Also sprach Zarathustra', 'Fanfare', 'Richard Strauss', 'Paul Lavender', 'Hal Leonard', 'Blasorchester', '5', 2, 'Klassik', 'B-003', 35, 'befriedigend', 'Schrank B'),
('Tiroler Holzhackerbuab\'n', 'Polka', 'Josef Franz Wagner', NULL, 'Kliment', 'Blasorchester', '3', 3, 'Polka', 'B-004', 24, 'gut', 'Schrank B'),
('Böhmischer Traum', 'Polka', 'Norbert Gälle', NULL, 'Musikverlag Geiger', 'Blasorchester', '4', 4, 'Polka', 'C-001', 28, 'sehr gut', 'Schrank C'),
('Gabriel\'s Oboe', 'aus "The Mission"', 'Ennio Morricone', 'Johan de Meij', 'Amstel Music', 'Blasorchester', '4', 5, 'Filmmusik', 'C-002', 30, 'gut', 'Schrank C'),
('Music', NULL, 'John Miles', 'Thijs Oud', 'De Haske', 'Blasorchester', '5', 7, 'Pop', 'C-003', 32, 'gut', 'Schrank C'),
('Florentiner Marsch', NULL, 'Julius Fučík', 'Fritz Neuböck', 'Rundel', 'Blasorchester', '4', 5, 'Marsch', 'C-004', 28, 'sehr gut', 'Schrank C');

-- ============================================================================
-- TABELLE: noten_dateien (für mehrere PDFs pro Notenstück)
-- ============================================================================

CREATE TABLE `noten_dateien` (
  `id` int NOT NULL AUTO_INCREMENT,
  `noten_id` int NOT NULL,
  `dateiname` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `dateityp` varchar(50) DEFAULT 'application/pdf',
  `dateigroesse` int DEFAULT NULL,
  `beschreibung` varchar(255) DEFAULT NULL,
  `sortierung` int DEFAULT '0',
  `hochgeladen_von` int DEFAULT NULL,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `noten_id` (`noten_id`),
  KEY `hochgeladen_von` (`hochgeladen_von`),
  CONSTRAINT `noten_dateien_ibfk_1` FOREIGN KEY (`noten_id`) REFERENCES `noten` (`id`) ON DELETE CASCADE,
  CONSTRAINT `noten_dateien_ibfk_2` FOREIGN KEY (`hochgeladen_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELLE: ausrueckungen
-- ============================================================================

CREATE TABLE `ausrueckungen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titel` varchar(200) NOT NULL,
  `beschreibung` text,
  `typ` enum('Probe','Konzert','Ausrückung','Fest','Wertung','Sonstiges') NOT NULL,
  `start_datum` datetime NOT NULL,
  `ende_datum` datetime DEFAULT NULL,
  `ganztaegig` tinyint(1) DEFAULT '0',
  `ort` varchar(200) DEFAULT NULL,
  `adresse` varchar(250) DEFAULT NULL,
  `treffpunkt` varchar(200) DEFAULT NULL,
  `treffpunkt_zeit` time DEFAULT NULL,
  `uniform` tinyint(1) DEFAULT '1',
  `notizen` text,
  `google_calendar_id` varchar(255) DEFAULT NULL,
  `status` enum('geplant','bestaetigt','abgesagt') DEFAULT 'geplant',
  `erstellt_von` int DEFAULT NULL,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  `aktualisiert_am` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `erstellt_von` (`erstellt_von`),
  CONSTRAINT `ausrueckungen_ibfk_1` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ausrueckungen` (`titel`, `beschreibung`, `typ`, `start_datum`, `ende_datum`, `ganztaegig`, `ort`, `adresse`, `treffpunkt`, `treffpunkt_zeit`, `uniform`, `status`, `erstellt_von`) VALUES
('Wöchentliche Probe', 'Reguläre Freitagsprobe', 'Probe', '2026-01-10 19:30:00', '2026-01-10 22:00:00', 0, 'Probelokal', 'Musikerstraße 1, 8010 Graz', 'Probelokal', '19:15:00', 0, 'bestaetigt', 1),
('Neujahrskonzert 2026', 'Traditionelles Neujahrskonzert mit Werken von Johann Strauss', 'Konzert', '2026-01-11 17:00:00', '2026-01-11 20:00:00', 0, 'Stadthalle Graz', 'Messestraße 1, 8010 Graz', 'Hintereingang Stadthalle', '15:30:00', 1, 'bestaetigt', 1),
('Faschingsumzug', 'Teilnahme am Grazer Faschingsumzug', 'Ausrückung', '2026-02-15 13:00:00', '2026-02-15 17:00:00', 0, 'Innenstadt Graz', 'Hauptplatz', 'Parkplatz Opernhaus', '12:00:00', 1, 'bestaetigt', 1),
('Frühjahrskonzert', 'Jahreskonzert mit neuem Programm', 'Konzert', '2026-04-18 19:00:00', '2026-04-18 22:00:00', 0, 'Kulturhaus', 'Kulturstraße 5, 8020 Graz', 'Eingang Kulturhaus', '17:00:00', 1, 'geplant', 1),
('Maibaum aufstellen', 'Traditionelles Maibaumaufstellen', 'Ausrückung', '2026-04-30 18:00:00', '2026-04-30 22:00:00', 0, 'Dorfplatz', 'Hauptstraße, 8045 Graz-Andritz', 'Feuerwehrhaus', '17:30:00', 1, 'geplant', 1),
('Fronleichnam', 'Prozession und Festmesse', 'Ausrückung', '2026-06-04 08:00:00', '2026-06-04 12:00:00', 0, 'Pfarrkirche', 'Kirchplatz 1', 'Kirchplatz', '07:30:00', 1, 'geplant', 1),
('Sommerfest', 'Vereinssommerfest mit Musik und Kulinarik', 'Fest', '2026-07-18 14:00:00', '2026-07-18 23:00:00', 0, 'Festgelände', 'Am Sportplatz 2', NULL, NULL, 1, 'geplant', 1),
('Platzkonzert', 'Sommerliches Platzkonzert', 'Konzert', '2026-08-08 18:00:00', '2026-08-08 20:00:00', 0, 'Hauptplatz Graz', 'Hauptplatz', 'Rathaus', '17:00:00', 1, 'geplant', 1),
('Erntedankfest', 'Musikalische Umrahmung des Erntedankfestes', 'Ausrückung', '2026-09-27 09:00:00', '2026-09-27 14:00:00', 0, 'Pfarrkirche', 'Kirchplatz 1', 'Kirchplatz', '08:30:00', 1, 'geplant', 1),
('Martinsfest', 'Laternenumzug mit Blasmusik', 'Ausrückung', '2026-11-11 17:00:00', '2026-11-11 20:00:00', 0, 'Kindergarten', 'Kindergartenweg 3', 'Kindergarten', '16:30:00', 1, 'geplant', 1),
('Adventkonzert', 'Besinnliches Konzert im Advent', 'Konzert', '2026-12-13 17:00:00', '2026-12-13 19:30:00', 0, 'Pfarrkirche', 'Kirchplatz 1', 'Sakristei', '15:30:00', 1, 'geplant', 1);

-- ============================================================================
-- TABELLE: ausrueckung_noten
-- ============================================================================

CREATE TABLE `ausrueckung_noten` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ausrueckung_id` int NOT NULL,
  `noten_id` int NOT NULL,
  `reihenfolge` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ausrueckung_id` (`ausrueckung_id`),
  KEY `noten_id` (`noten_id`),
  CONSTRAINT `ausrueckung_noten_ibfk_1` FOREIGN KEY (`ausrueckung_id`) REFERENCES `ausrueckungen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ausrueckung_noten_ibfk_2` FOREIGN KEY (`noten_id`) REFERENCES `noten` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ausrueckung_noten` (`ausrueckung_id`, `noten_id`, `reihenfolge`) VALUES
(2, 3, 1), (2, 12, 2), (2, 1, 3), (2, 5, 4),
(4, 6, 1), (4, 10, 2), (4, 11, 3), (4, 9, 4);

-- ============================================================================
-- TABELLE: anwesenheit
-- ============================================================================

CREATE TABLE `anwesenheit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ausrueckung_id` int NOT NULL,
  `mitglied_id` int NOT NULL,
  `status` enum('zugesagt','abgesagt','keine_antwort','anwesend','abwesend') DEFAULT 'keine_antwort',
  `grund` text,
  `gemeldet_am` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ausrueckung_id` (`ausrueckung_id`),
  KEY `mitglied_id` (`mitglied_id`),
  CONSTRAINT `anwesenheit_ibfk_1` FOREIGN KEY (`ausrueckung_id`) REFERENCES `ausrueckungen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `anwesenheit_ibfk_2` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `anwesenheit` (`ausrueckung_id`, `mitglied_id`, `status`, `grund`, `gemeldet_am`) VALUES
(2, 1, 'zugesagt', NULL, NOW()),
(2, 2, 'zugesagt', NULL, NOW()),
(2, 3, 'zugesagt', NULL, NOW()),
(2, 4, 'zugesagt', NULL, NOW()),
(2, 5, 'abgesagt', 'Familiäre Verpflichtung', NOW()),
(2, 6, 'zugesagt', NULL, NOW()),
(2, 7, 'zugesagt', NULL, NOW()),
(2, 8, 'zugesagt', NULL, NOW()),
(2, 10, 'zugesagt', NULL, NOW()),
(2, 12, 'keine_antwort', NULL, NULL);

-- ============================================================================
-- TABELLE: kalender_termine
-- ============================================================================

CREATE TABLE `kalender_termine` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titel` varchar(200) NOT NULL,
  `beschreibung` text,
  `typ` enum('Termin','Besprechung','Geburtstag','Feiertag','Reminder','Sonstiges') DEFAULT 'Termin',
  `start_datum` datetime NOT NULL,
  `ende_datum` datetime DEFAULT NULL,
  `ganztaegig` tinyint(1) DEFAULT '0',
  `ort` varchar(200) DEFAULT NULL,
  `farbe` varchar(7) DEFAULT '#6c757d',
  `erstellt_von` int DEFAULT NULL,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  `aktualisiert_am` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `erstellt_von` (`erstellt_von`),
  CONSTRAINT `kalender_termine_ibfk_1` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `kalender_termine` (`titel`, `beschreibung`, `typ`, `start_datum`, `ende_datum`, `ganztaegig`, `ort`, `farbe`) VALUES
('Vorstandssitzung', 'Monatliche Vorstandssitzung', 'Besprechung', '2026-01-15 19:00:00', '2026-01-15 21:00:00', 0, 'Probelokal', '#0d6efd'),
('Vorstandssitzung', 'Monatliche Vorstandssitzung', 'Besprechung', '2026-02-12 19:00:00', '2026-02-12 21:00:00', 0, 'Probelokal', '#0d6efd'),
('Vorstandssitzung', 'Monatliche Vorstandssitzung', 'Besprechung', '2026-03-11 19:00:00', '2026-03-11 21:00:00', 0, 'Probelokal', '#0d6efd'),
('Generalversammlung', 'Jahreshauptversammlung mit Neuwahlen', 'Besprechung', '2026-03-20 19:00:00', '2026-03-20 22:00:00', 0, 'Gasthaus Zur Post', '#dc3545'),
('Instrumentenwartung', 'Jährliche Wartung aller Vereinsinstrumente', 'Termin', '2026-03-05 14:00:00', '2026-03-05 17:00:00', 0, 'Probelokal', '#6f42c1'),
('Geburtstag Johann Huber', '', 'Geburtstag', '2026-03-15 00:00:00', NULL, 1, NULL, '#ffc107'),
('Probenwochenende', 'Intensivproben für Frühjahrskonzert', 'Termin', '2026-04-04 09:00:00', '2026-04-05 17:00:00', 0, 'Jugendheim Bergland', '#198754');

-- ============================================================================
-- TABELLE: finanzen
-- ============================================================================

CREATE TABLE `finanzen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `typ` enum('einnahme','ausgabe') NOT NULL,
  `datum` date NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `kategorie` varchar(100) DEFAULT NULL,
  `beschreibung` text,
  `beleg_nummer` varchar(50) DEFAULT NULL,
  `zahlungsart` varchar(50) DEFAULT NULL,
  `erstellt_von` int DEFAULT NULL,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `erstellt_von` (`erstellt_von`),
  CONSTRAINT `finanzen_ibfk_1` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `finanzen` (`typ`, `datum`, `betrag`, `kategorie`, `beschreibung`, `beleg_nummer`, `zahlungsart`, `erstellt_von`) VALUES
('einnahme', '2025-01-15', 1440.00, 'Mitgliedsbeiträge', 'Mitgliedsbeiträge 2025 (12 x 120 €)', 'E-2025-001', 'überweisung', 4),
('einnahme', '2025-03-22', 850.00, 'Spende', 'Spende Firma Müller GmbH', 'E-2025-002', 'überweisung', 4),
('einnahme', '2025-04-18', 2340.00, 'Konzerteinnahmen', 'Kartenverkauf Frühjahrskonzert', 'E-2025-003', 'bar', 4),
('einnahme', '2025-07-18', 4250.00, 'Festeinnahmen', 'Erlös Sommerfest 2025', 'E-2025-004', 'bar', 4),
('einnahme', '2025-09-10', 500.00, 'Subvention', 'Kulturförderung Gemeinde', 'E-2025-005', 'überweisung', 4),
('ausgabe', '2025-02-10', 320.00, 'Noten', 'Neue Noten für Frühjahrskonzert', 'A-2025-001', 'überweisung', 4),
('ausgabe', '2025-03-15', 580.00, 'Instrumentenwartung', 'Wartung Blechblasinstrumente', 'A-2025-002', 'überweisung', 4),
('ausgabe', '2025-04-01', 150.00, 'Raummiete', 'Saalmiete Frühjahrskonzert', 'A-2025-003', 'überweisung', 4),
('ausgabe', '2025-06-20', 890.00, 'Uniformen', 'Neue Uniformteile (Hemden)', 'A-2025-004', 'überweisung', 4),
('ausgabe', '2025-07-05', 1200.00, 'Festorganisation', 'Zeltmiete, Bühne Sommerfest', 'A-2025-005', 'überweisung', 4),
('ausgabe', '2025-11-15', 95.00, 'Versicherung', 'Jahresprämie Instrumentenversicherung', 'A-2025-006', 'überweisung', 4);

-- ============================================================================
-- TABELLE: beitraege
-- ============================================================================

CREATE TABLE `beitraege` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mitglied_id` int NOT NULL,
  `jahr` int NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `bezahlt_am` date DEFAULT NULL,
  `bezahlt` tinyint(1) DEFAULT '0',
  `zahlungsart` enum('bar','überweisung','lastschrift') DEFAULT 'überweisung',
  `notizen` text,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mitglied_id` (`mitglied_id`),
  CONSTRAINT `beitraege_ibfk_1` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `beitraege` (`mitglied_id`, `jahr`, `betrag`, `bezahlt_am`, `bezahlt`, `zahlungsart`) VALUES
(1, 2025, 120.00, '2025-01-15', 1, 'überweisung'),
(2, 2025, 120.00, '2025-01-20', 1, 'überweisung'),
(3, 2025, 120.00, '2025-02-01', 1, 'bar'),
(4, 2025, 120.00, '2025-01-18', 1, 'überweisung'),
(5, 2025, 120.00, '2025-01-25', 1, 'lastschrift'),
(6, 2025, 120.00, '2025-02-10', 1, 'überweisung'),
(7, 2025, 120.00, '2025-01-30', 1, 'bar'),
(8, 2025, 120.00, '2025-02-05', 1, 'überweisung'),
(10, 2025, 120.00, '2025-01-22', 1, 'überweisung'),
(11, 2025, 60.00, '2025-03-01', 1, 'überweisung'),
(12, 2025, 120.00, NULL, 0, 'überweisung'),
(1, 2026, 120.00, NULL, 0, 'überweisung'),
(2, 2026, 120.00, NULL, 0, 'überweisung');

-- ============================================================================
-- TABELLE: uniformen
-- ============================================================================

CREATE TABLE `uniformen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mitglied_id` int NOT NULL,
  `typ` enum('Parade','Ausgehuniform','Probe','Sommer','Winter') NOT NULL,
  `jacke_groesse` varchar(10) DEFAULT NULL,
  `hose_groesse` varchar(10) DEFAULT NULL,
  `hemd_groesse` varchar(10) DEFAULT NULL,
  `schuhgroesse` varchar(10) DEFAULT NULL,
  `ausgabe_datum` date DEFAULT NULL,
  `rueckgabe_datum` date DEFAULT NULL,
  `zustand` enum('sehr gut','gut','befriedigend','schlecht') DEFAULT 'gut',
  `anzahl_vorhanden` int DEFAULT '1',
  `standort` varchar(100) DEFAULT NULL,
  `notizen` text,
  `groesse_numerisch` varchar(10) DEFAULT NULL,
  `groesse_text` varchar(50) DEFAULT NULL,
  `anschaffungsdatum` date DEFAULT NULL,
  `anschaffungspreis` decimal(10,2) DEFAULT NULL,
  `zustand_beschreibung` text,
  `bild_pfad` varchar(255) DEFAULT NULL,
  `kategorie` varchar(50) DEFAULT 'Uniform',
  `aktiv` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `mitglied_id` (`mitglied_id`),
  CONSTRAINT `uniformen_ibfk_1` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `uniformen` (`mitglied_id`, `typ`, `jacke_groesse`, `hose_groesse`, `hemd_groesse`, `ausgabe_datum`, `zustand`) VALUES
(1, 'Parade', 'L', '50', 'L', '2018-09-01', 'gut'),
(2, 'Parade', 'S', '36', 'S', '2019-09-01', 'sehr gut'),
(3, 'Parade', 'XL', '52', 'XL', '2015-09-01', 'befriedigend'),
(4, 'Parade', 'M', '38', 'M', '2021-09-01', 'sehr gut'),
(5, 'Parade', 'L', '50', 'L', '2017-09-01', 'gut'),
(6, 'Parade', 'M', '40', 'M', '2018-09-01', 'gut'),
(7, 'Parade', 'XXL', '56', 'XXL', '2010-09-01', 'befriedigend'),
(8, 'Parade', 'S', '36', 'S', '2020-09-01', 'sehr gut');

-- ============================================================================
-- TABELLE: uniform_kategorien
-- ============================================================================

CREATE TABLE `uniform_kategorien` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `beschreibung` text,
  `sortierung` int DEFAULT '100',
  `aktiv` tinyint(1) DEFAULT '1',
  `erstellt_am` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `uniform_kategorien` (`name`, `beschreibung`, `sortierung`) VALUES
('Tracht', 'Traditionelle Vereinstracht', 1),
('Festtracht', 'Tracht für besondere Anlässe', 2),
('Sommertracht', 'Leichte Tracht für Sommeranlässe', 3),
('Regenbekleidung', 'Wetterschutz bei Ausrückungen', 4),
('Accessoires', 'Hüte, Gürtel, Abzeichen etc.', 5);

-- ============================================================================
-- TABELLE: uniform_ausgaben
-- ============================================================================

CREATE TABLE `uniform_ausgaben` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uniform_id` int NOT NULL,
  `mitglied_id` int NOT NULL,
  `ausgabe_datum` date NOT NULL,
  `rueckgabe_datum` date DEFAULT NULL,
  `zustand_bei_ausgabe` varchar(50) DEFAULT NULL,
  `zustand_bei_rueckgabe` varchar(50) DEFAULT NULL,
  `bemerkungen` text,
  `ausgegeben_von` int DEFAULT NULL,
  `erstellt_am` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uniform_id` (`uniform_id`),
  KEY `mitglied_id` (`mitglied_id`),
  KEY `ausgegeben_von` (`ausgegeben_von`),
  CONSTRAINT `uniform_ausgaben_ibfk_1` FOREIGN KEY (`uniform_id`) REFERENCES `uniformen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `uniform_ausgaben_ibfk_2` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE CASCADE,
  CONSTRAINT `uniform_ausgaben_ibfk_3` FOREIGN KEY (`ausgegeben_von`) REFERENCES `benutzer` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELLE: berechtigungen
-- ============================================================================

CREATE TABLE `berechtigungen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rolle_id` int DEFAULT NULL,
  `rolle` varchar(50) NOT NULL,
  `modul` varchar(50) NOT NULL,
  `lesen` tinyint(1) DEFAULT '0',
  `schreiben` tinyint(1) DEFAULT '0',
  `loeschen` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `rolle_id` (`rolle_id`),
  CONSTRAINT `berechtigungen_ibfk_1` FOREIGN KEY (`rolle_id`) REFERENCES `rollen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin: Vollzugriff
INSERT INTO `berechtigungen` (`rolle_id`, `rolle`, `modul`, `lesen`, `schreiben`, `loeschen`) VALUES
(1, 'admin', 'mitglieder', 1, 1, 1),
(1, 'admin', 'instrumente', 1, 1, 1),
(1, 'admin', 'noten', 1, 1, 1),
(1, 'admin', 'ausrueckungen', 1, 1, 1),
(1, 'admin', 'finanzen', 1, 1, 1),
(1, 'admin', 'benutzer', 1, 1, 1),
(1, 'admin', 'einstellungen', 1, 1, 1),
(1, 'admin', 'uniformen', 1, 1, 1);

-- Obmann
INSERT INTO `berechtigungen` (`rolle_id`, `rolle`, `modul`, `lesen`, `schreiben`, `loeschen`) VALUES
(2, 'obmann', 'mitglieder', 1, 1, 1),
(2, 'obmann', 'ausrueckungen', 1, 1, 1),
(2, 'obmann', 'noten', 1, 0, 0),
(2, 'obmann', 'instrumente', 1, 0, 0),
(2, 'obmann', 'uniformen', 1, 0, 0),
(2, 'obmann', 'finanzen', 1, 0, 0),
(2, 'obmann', 'benutzer', 1, 0, 0);

-- Kapellmeister
INSERT INTO `berechtigungen` (`rolle_id`, `rolle`, `modul`, `lesen`, `schreiben`, `loeschen`) VALUES
(3, 'kapellmeister', 'mitglieder', 1, 0, 0),
(3, 'kapellmeister', 'ausrueckungen', 1, 1, 1),
(3, 'kapellmeister', 'noten', 1, 1, 1),
(3, 'kapellmeister', 'instrumente', 1, 0, 0),
(3, 'kapellmeister', 'uniformen', 1, 0, 0),
(3, 'kapellmeister', 'finanzen', 1, 0, 0),
(3, 'kapellmeister', 'benutzer', 1, 0, 0);

-- Kassier
INSERT INTO `berechtigungen` (`rolle_id`, `rolle`, `modul`, `lesen`, `schreiben`, `loeschen`) VALUES
(4, 'kassier', 'mitglieder', 1, 0, 0),
(4, 'kassier', 'ausrueckungen', 1, 0, 0),
(4, 'kassier', 'noten', 1, 0, 0),
(4, 'kassier', 'instrumente', 1, 0, 0),
(4, 'kassier', 'uniformen', 1, 0, 0),
(4, 'kassier', 'finanzen', 1, 1, 1),
(4, 'kassier', 'benutzer', 1, 0, 0);

-- Schriftführer
INSERT INTO `berechtigungen` (`rolle_id`, `rolle`, `modul`, `lesen`, `schreiben`, `loeschen`) VALUES
(5, 'schriftfuehrer', 'mitglieder', 1, 1, 0),
(5, 'schriftfuehrer', 'ausrueckungen', 1, 1, 0),
(5, 'schriftfuehrer', 'noten', 1, 0, 0),
(5, 'schriftfuehrer', 'instrumente', 1, 0, 0),
(5, 'schriftfuehrer', 'uniformen', 1, 0, 0),
(5, 'schriftfuehrer', 'finanzen', 1, 0, 0),
(5, 'schriftfuehrer', 'benutzer', 1, 0, 0);

-- Trachtenwart
INSERT INTO `berechtigungen` (`rolle_id`, `rolle`, `modul`, `lesen`, `schreiben`, `loeschen`) VALUES
(6, 'trachtenwart', 'mitglieder', 1, 1, 0),
(6, 'trachtenwart', 'ausrueckungen', 1, 0, 0),
(6, 'trachtenwart', 'noten', 1, 0, 0),
(6, 'trachtenwart', 'instrumente', 1, 0, 0),
(6, 'trachtenwart', 'uniformen', 1, 1, 1),
(6, 'trachtenwart', 'finanzen', 1, 0, 0),
(6, 'trachtenwart', 'benutzer', 1, 0, 0);

-- Instrumentenwart
INSERT INTO `berechtigungen` (`rolle_id`, `rolle`, `modul`, `lesen`, `schreiben`, `loeschen`) VALUES
(7, 'instrumentenwart', 'mitglieder', 1, 1, 0),
(7, 'instrumentenwart', 'ausrueckungen', 1, 0, 0),
(7, 'instrumentenwart', 'noten', 1, 0, 0),
(7, 'instrumentenwart', 'instrumente', 1, 1, 1),
(7, 'instrumentenwart', 'uniformen', 1, 0, 0),
(7, 'instrumentenwart', 'finanzen', 1, 0, 0),
(7, 'instrumentenwart', 'benutzer', 1, 0, 0);

-- Jugendbeauftragter
INSERT INTO `berechtigungen` (`rolle_id`, `rolle`, `modul`, `lesen`, `schreiben`, `loeschen`) VALUES
(8, 'jugendbeauftragter', 'mitglieder', 1, 1, 0),
(8, 'jugendbeauftragter', 'ausrueckungen', 1, 1, 1),
(8, 'jugendbeauftragter', 'noten', 1, 1, 1),
(8, 'jugendbeauftragter', 'instrumente', 1, 0, 0),
(8, 'jugendbeauftragter', 'uniformen', 1, 0, 0),
(8, 'jugendbeauftragter', 'finanzen', 1, 0, 0),
(8, 'jugendbeauftragter', 'benutzer', 1, 0, 0);

-- Mitglied
INSERT INTO `berechtigungen` (`rolle_id`, `rolle`, `modul`, `lesen`, `schreiben`, `loeschen`) VALUES
(9, 'mitglied', 'mitglieder', 1, 0, 0),
(9, 'mitglied', 'ausrueckungen', 1, 0, 0),
(9, 'mitglied', 'noten', 1, 0, 0),
(9, 'mitglied', 'instrumente', 1, 0, 0),
(9, 'mitglied', 'uniformen', 1, 0, 0);

-- ============================================================================
-- TABELLE: einstellungen
-- ============================================================================

CREATE TABLE `einstellungen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `schluessel` varchar(100) NOT NULL,
  `wert` text,
  `beschreibung` text,
  `aktualisiert_am` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schluessel` (`schluessel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `einstellungen` (`schluessel`, `wert`, `beschreibung`) VALUES
('verein_name', 'Musikverein Musterstadt', 'Name des Musikvereins'),
('verein_ort', 'Musterstadt', 'Ort des Vereins'),
('verein_plz', '8010', 'Postleitzahl'),
('verein_adresse', 'Musikerstraße 1', 'Adresse des Vereins'),
('verein_email', 'info@mv-musterstadt.at', 'E-Mail-Adresse'),
('verein_telefon', '+43 316 123456', 'Telefonnummer'),
('verein_website', 'www.mv-musterstadt.at', 'Website'),
('google_calendar_api_key', '', 'Google Calendar API Schlüssel'),
('google_calendar_id', '', 'Google Calendar ID'),
('mitgliedsbeitrag_jahr', '120.00', 'Jährlicher Mitgliedsbeitrag in Euro'),
('beitrag_aktiv', '120.00', 'Beitrag für aktive Mitglieder'),
('beitrag_passiv', '60.00', 'Beitrag für passive Mitglieder'),
('beitrag_ehrenmitglied', '0', 'Beitrag für Ehrenmitglieder (0 = frei)'),
('beitrag_faelligkeit_monat', '1', 'Monat der Beitragsfälligkeit (1-12)'),
('email_smtp_host', '', 'SMTP Server für E-Mail Versand'),
('email_smtp_port', '587', 'SMTP Port'),
('email_from', '', 'Absender E-Mail Adresse'),
('uniform_groessen_system', 'international', 'Größensystem: international (XS/S/M/L/XL) oder numerisch'),
('uniform_groessen_verfuegbar', 'XS,S,M,L,XL,XXL', 'Verfügbare Größen (komma-getrennt)'),
('uniform_pfand_betrag', '50.00', 'Standard-Pfandbetrag für Uniformteile'),
('probentag', 'Freitag', 'Regulärer Probentag'),
('probenzeit_beginn', '19:30', 'Beginn der Probe'),
('probenzeit_ende', '22:00', 'Ende der Probe'),
('probelokal_adresse', 'Musikerstraße 1, 8010 Graz', 'Adresse des Probelokals');

-- ============================================================================
-- TABELLE: dokumente
-- ============================================================================

CREATE TABLE `dokumente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titel` varchar(200) NOT NULL,
  `beschreibung` text,
  `dateiname` varchar(255) NOT NULL,
  `dateipfad` varchar(500) NOT NULL,
  `dateityp` varchar(50) DEFAULT NULL,
  `dateigroesse` int DEFAULT NULL,
  `kategorie` varchar(100) DEFAULT NULL,
  `hochgeladen_von` int DEFAULT NULL,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hochgeladen_von` (`hochgeladen_von`),
  CONSTRAINT `dokumente_ibfk_1` FOREIGN KEY (`hochgeladen_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELLE: aktivitaetslog
-- ============================================================================

CREATE TABLE `aktivitaetslog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `benutzer_id` int DEFAULT NULL,
  `aktion` varchar(100) NOT NULL,
  `modul` varchar(50) DEFAULT NULL,
  `beschreibung` text,
  `ip_adresse` varchar(45) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `benutzer_id` (`benutzer_id`),
  CONSTRAINT `aktivitaetslog_ibfk_1` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FOREIGN KEY für benutzer.mitglied_id
-- ============================================================================

ALTER TABLE `benutzer`
  ADD CONSTRAINT `benutzer_ibfk_2` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE SET NULL;

COMMIT;

-- ============================================================================
-- INSTALLATION ABGESCHLOSSEN
-- ============================================================================
-- Standard-Login:
-- Benutzername: admin
-- Passwort: admin123
-- 
-- WICHTIG: Passwort nach erstem Login ändern!
-- ============================================================================
