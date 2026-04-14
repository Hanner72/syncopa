-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Erstellungszeit: 09. Mrz 2026 um 11:02
-- Server-Version: 10.4.34-MariaDB-cll-lve
-- PHP-Version: 7.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `mvpalfau_syncopa`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `aktivitaetslog`
--

DROP TABLE IF EXISTS `aktivitaetslog`;
CREATE TABLE IF NOT EXISTS `aktivitaetslog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `benutzer_id` int(11) DEFAULT NULL,
  `aktion` varchar(100) NOT NULL,
  `modul` varchar(50) DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `ip_adresse` varchar(45) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `benutzer_id` (`benutzer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabellenstruktur für Tabelle `anwesenheit`
--

DROP TABLE IF EXISTS `anwesenheit`;
CREATE TABLE IF NOT EXISTS `anwesenheit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ausrueckung_id` int(11) NOT NULL,
  `mitglied_id` int(11) NOT NULL,
  `status` enum('zugesagt','abgesagt','ungewiss','keine_antwort','anwesend','abwesend') DEFAULT 'keine_antwort',
  `grund` text DEFAULT NULL,
  `gemeldet_am` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ausrueckung_mitglied` (`ausrueckung_id`,`mitglied_id`),
  KEY `ausrueckung_id` (`ausrueckung_id`),
  KEY `mitglied_id` (`mitglied_id`)
) ENGINE=InnoDB AUTO_INCREMENT=608 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabellenstruktur für Tabelle `ausrueckungen`
--

DROP TABLE IF EXISTS `ausrueckungen`;
CREATE TABLE IF NOT EXISTS `ausrueckungen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titel` varchar(200) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `typ` enum('Probe','Konzert','Ausrückung','Fest','Wertung','Sonstiges') NOT NULL,
  `start_datum` datetime NOT NULL,
  `ende_datum` datetime DEFAULT NULL,
  `ganztaegig` tinyint(1) DEFAULT 0,
  `ort` varchar(200) DEFAULT NULL,
  `adresse` varchar(250) DEFAULT NULL,
  `treffpunkt` varchar(200) DEFAULT NULL,
  `treffpunkt_zeit` time DEFAULT NULL,
  `uniform` tinyint(1) DEFAULT 1,
  `notizen` text DEFAULT NULL,
  `google_calendar_id` varchar(255) DEFAULT NULL,
  `status` enum('geplant','bestaetigt','abgesagt') DEFAULT 'geplant',
  `erstellt_von` int(11) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `erstellt_von` (`erstellt_von`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabellenstruktur für Tabelle `ausrueckung_noten`
--

DROP TABLE IF EXISTS `ausrueckung_noten`;
CREATE TABLE IF NOT EXISTS `ausrueckung_noten` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ausrueckung_id` int(11) NOT NULL,
  `noten_id` int(11) NOT NULL,
  `reihenfolge` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ausrueckung_id` (`ausrueckung_id`),
  KEY `noten_id` (`noten_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `beitraege`
--

DROP TABLE IF EXISTS `beitraege`;
CREATE TABLE IF NOT EXISTS `beitraege` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mitglied_id` int(11) NOT NULL,
  `jahr` int(11) NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `bezahlt_am` date DEFAULT NULL,
  `bezahlt` tinyint(1) DEFAULT 0,
  `zahlungsart` enum('bar','überweisung','lastschrift') DEFAULT 'überweisung',
  `notizen` text DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `mitglied_id` (`mitglied_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `benutzer`
--

DROP TABLE IF EXISTS `benutzer`;
CREATE TABLE IF NOT EXISTS `benutzer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `benutzername` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `passwort_hash` varchar(255) DEFAULT NULL,
  `rolle` enum('admin','obmann','kassier','schriftfuehrer','mitglied','instrumentenwart','kapellmeister','trachtenwart','jugendbeauftragter','notenwart','user') DEFAULT 'mitglied',
  `rolle_id` int(11) DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT 1,
  `letzter_login` datetime DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mitglied_id` int(11) DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `benutzername` (`benutzername`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`),
  KEY `rolle_id` (`rolle_id`),
  KEY `mitglied_id` (`mitglied_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `benutzer`
--

INSERT INTO `benutzer` (`id`, `benutzername`, `email`, `passwort_hash`, `rolle`, `rolle_id`, `aktiv`, `letzter_login`, `erstellt_am`, `aktualisiert_am`, `mitglied_id`, `google_id`) VALUES
(1, 'admin', 'admin@musikverein.at', '$2y$10$9aKCAVWmIXUmW2edKAxJxesixiHc9LzpCgqw6a6dZUvH0sZfIRYe2', 'admin', 1, 1, '2026-03-08 16:51:00', '2026-02-10 09:25:15', '2026-03-08 16:51:00', 23, NULL),
(2, 'obmann', 'obmann@musikverein.at', '$2a$12$V3H/Yxfr3hyjpyE299xFLuVdUofhCSep7Knduu4.AN/LzXwfyHcRm', 'obmann', 2, 1, NULL, '2026-02-10 09:25:15', '2026-02-18 15:25:56', 42, NULL),
(3, 'kapellmeister', 'kapellmeister@musikverein.at', '$2y$10$pkMirixnRkZkQ0ZRZW.9p.9O9Irk1uWa5BJCV.dEgQhffpdB3gwNu', 'kapellmeister', 3, 1, '2026-02-10 11:39:42', '2026-02-10 09:25:15', '2026-02-18 15:25:41', 40, NULL),
(4, 'kassier', 'kassier@musikverein.at', '$2y$10$Ytv3UudMWt.0FnfGHe.pJOfwtdeVS/hPrZRQVkV3vx8h1soa6jbMO', 'kassier', 4, 1, '2026-02-10 11:38:19', '2026-02-10 09:25:15', '2026-02-10 11:38:19', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `berechtigungen`
--

DROP TABLE IF EXISTS `berechtigungen`;
CREATE TABLE IF NOT EXISTS `berechtigungen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rolle_id` int(11) DEFAULT NULL,
  `rolle` varchar(50) NOT NULL,
  `modul` varchar(50) NOT NULL,
  `lesen` tinyint(1) DEFAULT 0,
  `schreiben` tinyint(1) DEFAULT 0,
  `loeschen` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `rolle_id` (`rolle_id`)
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `berechtigungen`
--

INSERT INTO `berechtigungen` (`id`, `rolle_id`, `rolle`, `modul`, `lesen`, `schreiben`, `loeschen`) VALUES
(1, 1, 'admin', 'mitglieder', 1, 1, 1),
(2, 1, 'admin', 'instrumente', 1, 1, 1),
(3, 1, 'admin', 'noten', 1, 1, 1),
(4, 1, 'admin', 'ausrueckungen', 1, 1, 1),
(5, 1, 'admin', 'finanzen', 1, 1, 1),
(6, 1, 'admin', 'benutzer', 1, 1, 1),
(7, 1, 'admin', 'einstellungen', 1, 1, 1),
(8, 1, 'admin', 'uniformen', 1, 1, 1),
(9, 2, 'obmann', 'mitglieder', 1, 1, 1),
(10, 2, 'obmann', 'ausrueckungen', 1, 1, 1),
(11, 2, 'obmann', 'noten', 1, 0, 0),
(12, 2, 'obmann', 'instrumente', 1, 0, 0),
(13, 2, 'obmann', 'uniformen', 1, 0, 0),
(14, 2, 'obmann', 'finanzen', 1, 0, 0),
(15, 2, 'obmann', 'benutzer', 1, 0, 0),
(16, 3, 'kapellmeister', 'mitglieder', 1, 0, 0),
(17, 3, 'kapellmeister', 'ausrueckungen', 1, 1, 1),
(18, 3, 'kapellmeister', 'noten', 1, 1, 1),
(19, 3, 'kapellmeister', 'instrumente', 1, 0, 0),
(20, 3, 'kapellmeister', 'uniformen', 1, 0, 0),
(21, 3, 'kapellmeister', 'finanzen', 1, 0, 0),
(22, 3, 'kapellmeister', 'benutzer', 1, 0, 0),
(23, 4, 'kassier', 'mitglieder', 1, 0, 0),
(24, 4, 'kassier', 'ausrueckungen', 1, 0, 0),
(25, 4, 'kassier', 'noten', 1, 0, 0),
(26, 4, 'kassier', 'instrumente', 1, 0, 0),
(27, 4, 'kassier', 'uniformen', 1, 0, 0),
(28, 4, 'kassier', 'finanzen', 1, 1, 1),
(29, 4, 'kassier', 'benutzer', 1, 0, 0),
(30, 5, 'schriftfuehrer', 'mitglieder', 1, 1, 0),
(31, 5, 'schriftfuehrer', 'ausrueckungen', 1, 1, 0),
(32, 5, 'schriftfuehrer', 'noten', 1, 0, 0),
(33, 5, 'schriftfuehrer', 'instrumente', 1, 0, 0),
(34, 5, 'schriftfuehrer', 'uniformen', 1, 0, 0),
(35, 5, 'schriftfuehrer', 'finanzen', 1, 0, 0),
(36, 5, 'schriftfuehrer', 'benutzer', 1, 0, 0),
(37, 6, 'trachtenwart', 'mitglieder', 1, 1, 0),
(38, 6, 'trachtenwart', 'ausrueckungen', 1, 0, 0),
(39, 6, 'trachtenwart', 'noten', 1, 0, 0),
(40, 6, 'trachtenwart', 'instrumente', 1, 0, 0),
(41, 6, 'trachtenwart', 'uniformen', 1, 1, 1),
(42, 6, 'trachtenwart', 'finanzen', 1, 0, 0),
(43, 6, 'trachtenwart', 'benutzer', 1, 0, 0),
(44, 7, 'instrumentenwart', 'mitglieder', 1, 1, 0),
(45, 7, 'instrumentenwart', 'ausrueckungen', 1, 0, 0),
(46, 7, 'instrumentenwart', 'noten', 1, 0, 0),
(47, 7, 'instrumentenwart', 'instrumente', 1, 1, 1),
(48, 7, 'instrumentenwart', 'uniformen', 1, 0, 0),
(49, 7, 'instrumentenwart', 'finanzen', 1, 0, 0),
(50, 7, 'instrumentenwart', 'benutzer', 1, 0, 0),
(51, 8, 'jugendbeauftragter', 'mitglieder', 1, 1, 0),
(52, 8, 'jugendbeauftragter', 'ausrueckungen', 1, 1, 1),
(53, 8, 'jugendbeauftragter', 'noten', 1, 1, 1),
(54, 8, 'jugendbeauftragter', 'instrumente', 1, 0, 0),
(55, 8, 'jugendbeauftragter', 'uniformen', 1, 0, 0),
(56, 8, 'jugendbeauftragter', 'finanzen', 1, 0, 0),
(57, 8, 'jugendbeauftragter', 'benutzer', 1, 0, 0),
(58, 9, 'mitglied', 'mitglieder', 1, 0, 0),
(59, 9, 'mitglied', 'ausrueckungen', 1, 0, 0),
(60, 9, 'mitglied', 'noten', 1, 0, 0),
(61, 9, 'mitglied', 'instrumente', 1, 0, 0),
(62, 9, 'mitglied', 'uniformen', 1, 0, 0),
(70, NULL, 'notenwart', 'mitglieder', 1, 0, 0),
(71, NULL, 'notenwart', 'ausrueckungen', 1, 0, 0),
(72, NULL, 'notenwart', 'noten', 1, 1, 1),
(73, NULL, 'notenwart', 'instrumente', 1, 0, 0),
(74, NULL, 'notenwart', 'uniformen', 1, 0, 0),
(75, NULL, 'notenwart', 'finanzen', 1, 0, 0),
(76, NULL, 'notenwart', 'benutzer', 1, 0, 0),
(77, 10, 'notenwart', 'mitglieder', 1, 0, 0),
(78, 10, 'notenwart', 'ausrueckungen', 1, 0, 0),
(79, 10, 'notenwart', 'noten', 1, 1, 1),
(80, 10, 'notenwart', 'instrumente', 1, 0, 0),
(81, 10, 'notenwart', 'uniformen', 1, 0, 0),
(82, 10, 'notenwart', 'finanzen', 0, 0, 0),
(83, 10, 'notenwart', 'benutzer', 0, 0, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `dokumente`
--

DROP TABLE IF EXISTS `dokumente`;
CREATE TABLE IF NOT EXISTS `dokumente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titel` varchar(200) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `dateiname` varchar(255) NOT NULL,
  `dateipfad` varchar(500) NOT NULL,
  `dateityp` varchar(50) DEFAULT NULL,
  `dateigroesse` int(11) DEFAULT NULL,
  `kategorie` varchar(100) DEFAULT NULL,
  `hochgeladen_von` int(11) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hochgeladen_von` (`hochgeladen_von`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `einstellungen`
--

DROP TABLE IF EXISTS `einstellungen`;
CREATE TABLE IF NOT EXISTS `einstellungen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schluessel` varchar(100) NOT NULL,
  `wert` text DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `schluessel` (`schluessel`)
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `einstellungen`
--

INSERT INTO `einstellungen` (`id`, `schluessel`, `wert`, `beschreibung`, `aktualisiert_am`) VALUES
(1, 'verein_name', 'Musikverein Palfau', 'Name des Musikvereins', '2026-02-10 10:45:19'),
(2, 'verein_ort', 'Palfau', 'Ort des Vereins', '2026-02-10 10:45:19'),
(3, 'verein_plz', '8010', 'Postleitzahl', '2026-02-10 09:25:16'),
(4, 'verein_adresse', 'Musikerstraße 1', 'Adresse des Vereins', '2026-02-10 09:25:16'),
(5, 'verein_email', 'info@mv-musterstadt.at', 'E-Mail-Adresse', '2026-02-10 09:25:16'),
(6, 'verein_telefon', '+43 316 123456', 'Telefonnummer', '2026-02-10 09:25:16'),
(7, 'verein_website', 'www.mv-musterstadt.at', 'Website', '2026-02-10 09:25:16'),
(8, 'google_calendar_api_key', '', 'Google Calendar API Schlüssel', '2026-02-10 09:25:16'),
(9, 'google_calendar_id', '', 'Google Calendar ID', '2026-02-10 09:25:16'),
(10, 'mitgliedsbeitrag_jahr', '0', 'Jährlicher Mitgliedsbeitrag in Euro', '2026-02-10 10:45:19'),
(11, 'beitrag_aktiv', '0', 'Beitrag für aktive Mitglieder', '2026-02-10 10:45:19'),
(12, 'beitrag_passiv', '1', 'Beitrag für passive Mitglieder', '2026-02-10 10:45:19'),
(13, 'beitrag_ehrenmitglied', '0', 'Beitrag für Ehrenmitglieder (0 = frei)', '2026-02-10 09:25:16'),
(14, 'beitrag_faelligkeit_monat', '1', 'Monat der Beitragsfälligkeit (1-12)', '2026-02-10 09:25:16'),
(15, 'email_smtp_host', '', 'SMTP Server für E-Mail Versand', '2026-02-10 09:25:16'),
(16, 'email_smtp_port', '587', 'SMTP Port', '2026-02-10 09:25:16'),
(17, 'email_from', '', 'Absender E-Mail Adresse', '2026-02-10 09:25:16'),
(18, 'uniform_groessen_system', 'international', 'Größensystem: international (XS/S/M/L/XL) oder numerisch', '2026-02-10 09:25:16'),
(19, 'uniform_groessen_verfuegbar', 'XS,S,M,L,XL,XXL', 'Verfügbare Größen (komma-getrennt)', '2026-02-10 09:25:16'),
(20, 'uniform_pfand_betrag', '50.00', 'Standard-Pfandbetrag für Uniformteile', '2026-02-10 09:25:16'),
(21, 'probentag', 'Freitag', 'Regulärer Probentag', '2026-02-10 09:25:16'),
(22, 'probenzeit_beginn', '19:30', 'Beginn der Probe', '2026-02-10 09:25:16'),
(23, 'probenzeit_ende', '22:00', 'Ende der Probe', '2026-02-10 09:25:16'),
(24, 'probelokal_adresse', 'Musikerstraße 1, 8010 Graz', 'Adresse des Probelokals', '2026-02-10 09:25:16'),
(29, 'beitrag_passiv_betrag', '25', NULL, '2026-02-10 10:45:19'),
(39, 'beitrag_ausgetreten', '0', NULL, '2026-02-10 10:45:19'),
(40, 'nummernkreis_mitglieder_prefix', 'My', 'Nummernkreis Mitglieder – Präfix', '2026-02-26 16:22:36'),
(41, 'nummernkreis_mitglieder_stellen', '3', 'Nummernkreis Mitglieder – Stellen', '2026-02-26 16:20:57'),
(42, 'nummernkreis_noten_prefix', 'Ny', 'Nummernkreis Noten – Präfix', '2026-02-26 16:22:36'),
(43, 'nummernkreis_noten_stellen', '3', 'Nummernkreis Noten – Stellen', '2026-02-26 16:22:36'),
(44, 'nummernkreis_instrumente_prefix', 'Iy', 'Nummernkreis Instrumente – Präfix', '2026-02-26 16:01:44'),
(45, 'nummernkreis_instrumente_stellen', '3', 'Nummernkreis Instrumente – Stellen', '2026-02-26 16:01:44'),
(92, 'nummernkreis_uniformen_prefix', '', 'Nummernkreis Uniformen – Präfix', '2026-02-26 16:21:40'),
(93, 'nummernkreis_uniformen_stellen', '3', 'Nummernkreis Uniformen – Stellen', '2026-02-26 16:21:40');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `finanzen`
--

DROP TABLE IF EXISTS `finanzen`;
CREATE TABLE IF NOT EXISTS `finanzen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `typ` enum('einnahme','ausgabe') NOT NULL,
  `datum` date NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `kategorie` varchar(100) DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `beleg_nummer` varchar(50) DEFAULT NULL,
  `zahlungsart` varchar(50) DEFAULT NULL,
  `erstellt_von` int(11) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `erstellt_von` (`erstellt_von`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabellenstruktur für Tabelle `instrumente`
--

DROP TABLE IF EXISTS `instrumente`;
CREATE TABLE IF NOT EXISTS `instrumente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventar_nummer` varchar(50) NOT NULL,
  `instrument_typ_id` int(11) NOT NULL,
  `hersteller` varchar(100) DEFAULT NULL,
  `modell` varchar(100) DEFAULT NULL,
  `seriennummer` varchar(100) DEFAULT NULL,
  `baujahr` int(11) DEFAULT NULL,
  `anschaffungsdatum` date DEFAULT NULL,
  `anschaffungspreis` decimal(10,2) DEFAULT NULL,
  `zustand` enum('sehr gut','gut','befriedigend','schlecht','defekt') DEFAULT 'gut',
  `standort` varchar(100) DEFAULT NULL,
  `versicherungswert` decimal(10,2) DEFAULT NULL,
  `mitglied_id` int(11) DEFAULT NULL,
  `ausgeliehen_seit` date DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `instrument_typ_id` (`instrument_typ_id`),
  KEY `mitglied_id` (`mitglied_id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabellenstruktur für Tabelle `instrument_typen`
--

DROP TABLE IF EXISTS `instrument_typen`;
CREATE TABLE IF NOT EXISTS `instrument_typen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `register_id` int(11) DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `register_id` (`register_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `instrument_typen`
--

INSERT INTO `instrument_typen` (`id`, `name`, `register_id`, `beschreibung`) VALUES
(1, 'Flöte', 1, 'Querflöte'),
(2, 'Klarinette in Bb', 1, 'Standard-Klarinette'),
(3, 'Bassklarinette', 1, 'Tiefe Klarinette'),
(4, 'Altsaxophon', 2, 'Alt-Saxophon in Es'),
(5, 'Tenorsaxophon', 2, 'Tenor-Saxophon in Bb'),
(6, 'Baritonsaxophon', 2, 'Bariton-Saxophon in Es'),
(7, 'Flügelhorn', 3, 'Flügelhorn in Bb'),
(8, 'B-Trompete', 4, 'Trompete in Bb'),
(9, 'Tenorhorn', 5, 'Tenorhorn in Bb'),
(10, 'Bariton', 5, 'Bariton/Euphonium'),
(11, 'Posaune', 6, 'Zugposaune'),
(12, 'Zugposaune', 6, 'Bass-Posaune'),
(13, 'B-Tuba', 7, 'B-Tuba'),
(14, 'Es-Bass', 7, 'Es-Tuba'),
(15, 'Schlagzeug', 8, 'Drum-Set'),
(16, 'Kleine Trommel', 8, 'Snare Drum'),
(17, 'Große Trommel', 8, 'Bass Drum'),
(18, 'Becken', 8, 'Marschbecken'),
(19, 'Lyra', 8, 'Glockenspiel/Lyra'),
(20, 'F-Tuba', 7, 'F-Tuba'),
(21, 'Glockenspiel', 8, 'Glockenspiel'),
(22, 'Es-Horn', 10, NULL),
(23, 'F-Horn', 10, NULL),
(24, 'Es-Trompete', 4, NULL),
(25, 'Xylophon', 8, NULL),
(26, 'Piccolo', 1, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `instrument_wartungen`
--

DROP TABLE IF EXISTS `instrument_wartungen`;
CREATE TABLE IF NOT EXISTS `instrument_wartungen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instrument_id` int(11) NOT NULL,
  `datum` date NOT NULL,
  `art` enum('Wartung','Reparatur','Überholung','Reinigung') NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `kosten` decimal(10,2) DEFAULT NULL,
  `durchgefuehrt_von` varchar(100) DEFAULT NULL,
  `naechste_wartung` date DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `instrument_id` (`instrument_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kalender_termine`
--

DROP TABLE IF EXISTS `kalender_termine`;
CREATE TABLE IF NOT EXISTS `kalender_termine` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titel` varchar(200) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `typ` enum('Termin','Besprechung','Geburtstag','Feiertag','Reminder','Sonstiges') DEFAULT 'Termin',
  `start_datum` datetime NOT NULL,
  `ende_datum` datetime DEFAULT NULL,
  `ganztaegig` tinyint(1) DEFAULT 0,
  `ort` varchar(200) DEFAULT NULL,
  `farbe` varchar(7) DEFAULT '#6c757d',
  `erstellt_von` int(11) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `erstellt_von` (`erstellt_von`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabellenstruktur für Tabelle `mitglieder`
--

DROP TABLE IF EXISTS `mitglieder`;
CREATE TABLE IF NOT EXISTS `mitglieder` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `benutzer_id` int(11) DEFAULT NULL,
  `register_id` int(11) DEFAULT NULL,
  `eintritt_datum` date DEFAULT NULL,
  `austritt_datum` date DEFAULT NULL,
  `status` enum('aktiv','passiv','ausgetreten','ehrenmitglied') DEFAULT 'aktiv',
  `notizen` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `benutzer_id` (`benutzer_id`),
  KEY `register_id` (`register_id`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabellenstruktur für Tabelle `mitglied_instrumente`
--

DROP TABLE IF EXISTS `mitglied_instrumente`;
CREATE TABLE IF NOT EXISTS `mitglied_instrumente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mitglied_id` int(11) NOT NULL,
  `instrument_typ_id` int(11) NOT NULL,
  `hauptinstrument` tinyint(1) DEFAULT 0,
  `seit_datum` date DEFAULT NULL,
  `bis_datum` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mitglied_id` (`mitglied_id`),
  KEY `instrument_typ_id` (`instrument_typ_id`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabellenstruktur für Tabelle `noten`
--

DROP TABLE IF EXISTS `noten`;
CREATE TABLE IF NOT EXISTS `noten` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titel` varchar(200) NOT NULL,
  `untertitel` varchar(200) DEFAULT NULL,
  `komponist` varchar(150) DEFAULT NULL,
  `arrangeur` varchar(150) DEFAULT NULL,
  `verlag` varchar(100) DEFAULT NULL,
  `besetzung` varchar(100) DEFAULT NULL,
  `schwierigkeitsgrad` enum('1','2','3','4','5','6') DEFAULT '3',
  `dauer_minuten` int(11) DEFAULT NULL,
  `genre` varchar(50) DEFAULT NULL,
  `archiv_nummer` varchar(50) DEFAULT NULL,
  `anzahl_stimmen` int(11) DEFAULT NULL,
  `zustand` enum('sehr gut','gut','befriedigend','schlecht') DEFAULT 'gut',
  `bemerkungen` text DEFAULT NULL,
  `standort` varchar(100) DEFAULT NULL,
  `pdf_datei` varchar(255) DEFAULT NULL,
  `audio_datei` varchar(255) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=368 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabellenstruktur für Tabelle `noten_dateien`
--

DROP TABLE IF EXISTS `noten_dateien`;
CREATE TABLE IF NOT EXISTS `noten_dateien` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `noten_id` int(11) NOT NULL,
  `dateiname` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `dateityp` varchar(50) DEFAULT 'application/pdf',
  `dateigroesse` int(11) DEFAULT NULL,
  `beschreibung` varchar(255) DEFAULT NULL,
  `sortierung` int(11) DEFAULT 0,
  `hochgeladen_von` int(11) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `noten_id` (`noten_id`),
  KEY `hochgeladen_von` (`hochgeladen_von`)
) ENGINE=InnoDB AUTO_INCREMENT=196 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tabellenstruktur für Tabelle `register`
--

DROP TABLE IF EXISTS `register`;
CREATE TABLE IF NOT EXISTS `register` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `sortierung` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `register`
--

INSERT INTO `register` (`id`, `name`, `beschreibung`, `sortierung`) VALUES
(1, 'Flöten/Klarinetten', 'Holzbläser - hohe Register', 4),
(2, 'Saxophone', 'Holzbläser - Saxophone', 5),
(3, 'Flügelhörner', 'Blechbläser - hohe Lage', 6),
(4, 'Trompeten', 'Blechbläser - hohe Lage', 7),
(5, 'Tenorhörner', 'Blechbläser - mittlere Lage', 8),
(6, 'Posaune', 'Blechbläser - tiefe Lage', 6),
(7, 'Tuben', 'Blechbläser - Bass', 10),
(8, 'Schlagwerk', 'Perkussion', 11),
(9, 'Marketender/in', 'Marketender/in', 3),
(10, 'Horn', 'Horn', 9),
(11, 'Obmann', NULL, 1),
(12, 'Kapellmeister', NULL, 2),
(13, 'Stabführer', NULL, 2);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `rollen`
--

DROP TABLE IF EXISTS `rollen`;
CREATE TABLE IF NOT EXISTS `rollen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `ist_admin` tinyint(1) DEFAULT 0,
  `farbe` varchar(20) DEFAULT 'secondary',
  `sortierung` int(11) DEFAULT 100,
  `aktiv` tinyint(1) DEFAULT 1,
  `erstellt_am` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `rollen`
--

INSERT INTO `rollen` (`id`, `name`, `beschreibung`, `ist_admin`, `farbe`, `sortierung`, `aktiv`, `erstellt_am`) VALUES
(1, 'admin', 'Administrator mit vollen Rechten', 1, 'danger', 1, 1, '2026-02-10 09:25:15'),
(2, 'obmann', 'Obmann/Vorstand', 0, 'primary', 2, 1, '2026-02-10 09:25:15'),
(3, 'kapellmeister', 'Kapellmeister', 0, 'success', 3, 1, '2026-02-10 09:25:15'),
(4, 'kassier', 'Kassier', 0, 'info', 4, 1, '2026-02-10 09:25:15'),
(5, 'schriftfuehrer', 'Schriftführer', 0, 'warning', 5, 1, '2026-02-10 09:25:15'),
(6, 'trachtenwart', 'Trachtenwart', 0, 'purple', 6, 1, '2026-02-10 09:25:15'),
(7, 'instrumentenwart', 'Instrumentenwart', 0, 'dark', 7, 1, '2026-02-10 09:25:15'),
(8, 'jugendbeauftragter', 'Jugendbeauftragter', 0, 'info', 8, 1, '2026-02-10 09:25:15'),
(9, 'mitglied', 'Normales Mitglied', 0, 'secondary', 999, 1, '2026-02-10 09:25:15'),
(10, 'notenwart', 'Notenwart - Verwaltung des Notenarchivs', 0, 'danger', 9, 1, '2026-02-10 10:30:25'),
(11, 'user', 'Neuer Benutzer (noch nicht freigeschaltet)', 0, 'secondary', 1000, 1, '2026-02-10 12:54:59');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `uniform_kategorien`
--

DROP TABLE IF EXISTS `uniform_kategorien`;
CREATE TABLE IF NOT EXISTS `uniform_kategorien` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `sortierung` int(11) DEFAULT 100,
  `aktiv` tinyint(1) DEFAULT 1,
  `erstellt_am` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `uniform_kategorien`
--

INSERT INTO `uniform_kategorien` (`id`, `name`, `beschreibung`, `sortierung`, `aktiv`, `erstellt_am`) VALUES
(1, 'Tracht', 'Traditionelle Vereinstracht', 1, 1, '2026-02-10 09:25:15'),
(2, 'Moderne Tracht', 'Tracht für besondere Anlässe', 2, 1, '2026-02-10 09:25:15'),
(3, 'Sommertracht', 'Leichte Tracht für Sommeranlässe', 3, 1, '2026-02-10 09:25:15'),
(4, 'Regenbekleidung', 'Wetterschutz bei Ausrückungen', 4, 1, '2026-02-10 09:25:15'),
(5, 'Accessoires', 'Hüte, Gürtel, Abzeichen etc.', 5, 1, '2026-02-10 09:25:15');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `uniform_kleidungsstuecke`
--

DROP TABLE IF EXISTS `uniform_kleidungsstuecke`;
CREATE TABLE IF NOT EXISTS `uniform_kleidungsstuecke` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kategorie_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `groessen_verfuegbar` varchar(255) DEFAULT NULL,
  `sortierung` int(11) DEFAULT 100,
  `aktiv` tinyint(1) DEFAULT 1,
  `erstellt_am` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `kategorie_id` (`kategorie_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `uniform_kleidungsstuecke`
--

INSERT INTO `uniform_kleidungsstuecke` (`id`, `kategorie_id`, `name`, `beschreibung`, `groessen_verfuegbar`, `sortierung`, `aktiv`, `erstellt_am`) VALUES
(1, 1, 'Jacke', 'Trachtenjacke für Festanlässe', 'XS, S, M, L, XL, XXL', 1, 1, '2026-02-10 09:41:06'),
(2, 1, 'Hose/Rock', 'Trachtenhose oder Trachtenrock', '34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54', 2, 1, '2026-02-10 09:41:06'),
(3, 1, 'Hemd/Bluse', 'Weißes Trachtenhemd oder Bluse', 'XS, S, M, L, XL, XXL', 3, 1, '2026-02-10 09:41:06'),
(4, 1, 'Weste', 'Trachtenweste', 'S, M, L, XL', 4, 1, '2026-02-10 09:41:06'),
(5, 2, 'Hemd schwarz', 'Leichtes Hemd für Sommerauftritte', 'S, M, L, XL, XXL, 3XL, 4XL, 5XL', 1, 1, '2026-02-10 09:41:06'),
(6, 2, 'Kleid', 'Kleid für die Damen', '32, 33 ,34, 35 ,36 ,37 ,38 ,39 ,40', 2, 1, '2026-02-10 09:41:06'),
(7, 3, 'Polo-Shirt', 'Apfelgrün', 'DXXS, DXS, DS, DM, DL, DXL, DXXL, D3XL, D4Xl, HXS, HS, HM, HL, HXL, HXXL, H3XL, H4Xl, H5XL', 1, 1, '2026-02-10 09:41:06'),
(8, 3, 'Marschhose', 'Hose für Marschauftritte', '34-54', 2, 0, '2026-02-10 09:41:06'),
(9, 4, 'Regenmantel', 'Trachten Umhang', '34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54, 56, 58, 60', 1, 1, '2026-02-10 09:41:06'),
(10, 4, 'Regenhut', 'Wasserdichter Hut', 'Einheitsgröße', 2, 0, '2026-02-10 09:41:06'),
(11, 1, 'Hut', 'Trachtenhut mit Gamsbart', '40, 42, 44, 46, 48, 50, 52, 54, 56, 58, 60', 1, 1, '2026-02-10 09:41:06'),
(12, 5, 'Gürtel', 'Trachtengürtel', 'S, M, L, XL', 2, 0, '2026-02-10 09:41:06'),
(13, 5, 'Vereinsabzeichen', 'Offizielles Vereinsabzeichen', 'Einheitsgröße', 3, 0, '2026-02-10 09:41:06'),
(14, 1, 'Krawatte/Mascherl', 'Krawatte oder Mascherl', 'OS', 4, 1, '2026-02-10 09:41:06'),
(15, 2, 'Krawatte', 'Türkis', 'OS', 3, 1, '2026-02-18 14:32:17'),
(16, 2, 'Tücherl', 'Türkis', 'OS', 2, 1, '2026-02-18 14:32:48');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `uniform_zuweisungen`
--

DROP TABLE IF EXISTS `uniform_zuweisungen`;
CREATE TABLE IF NOT EXISTS `uniform_zuweisungen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mitglied_id` int(11) NOT NULL,
  `kleidungsstueck_id` int(11) NOT NULL,
  `groesse` varchar(20) DEFAULT NULL,
  `zustand` enum('sehr gut','gut','befriedigend','schlecht') DEFAULT 'gut',
  `ausgabe_datum` date DEFAULT NULL,
  `bemerkungen` text DEFAULT NULL,
  `nicht_benoetigt` tinyint(1) NOT NULL DEFAULT 0,
  `erstellt_am` timestamp NULL DEFAULT current_timestamp(),
  `aktualisiert_am` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `mitglied_kleidung` (`mitglied_id`,`kleidungsstueck_id`),
  KEY `kleidungsstueck_id` (`kleidungsstueck_id`)
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FESTVERWALTUNG
-- ============================================================================

CREATE TABLE IF NOT EXISTS `feste` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(100) NOT NULL,
  `jahr`         YEAR NOT NULL,
  `datum_von`    DATE NOT NULL,
  `datum_bis`    DATE NULL,
  `ort`          VARCHAR(100) NULL,
  `adresse`      VARCHAR(255) NULL,
  `beschreibung` TEXT NULL,
  `status`       ENUM('geplant','aktiv','abgeschlossen','abgesagt') NOT NULL DEFAULT 'geplant',
  `erstellt_von` INT NULL,
  `erstellt_am`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `erstellt_von` (`erstellt_von`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fest_stationen` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `fest_id`           INT NOT NULL,
  `name`              VARCHAR(100) NOT NULL,
  `beschreibung`      TEXT NULL,
  `oeffnung_von`      TIME NULL,
  `oeffnung_bis`      TIME NULL,
  `benoetigte_helfer` INT NOT NULL DEFAULT 1,
  `farbe`             VARCHAR(20) NULL DEFAULT 'primary',
  `sortierung`        INT NOT NULL DEFAULT 0,
  `erstellt_am`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`fest_id`) REFERENCES `feste`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fest_station_tage` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `station_id`        INT NOT NULL,
  `datum`             DATE NOT NULL,
  `aktiv`             TINYINT(1) NOT NULL DEFAULT 1,
  `oeffnung_von`      TIME NULL,
  `oeffnung_bis`      TIME NULL,
  `benoetigte_helfer` INT NOT NULL DEFAULT 1,
  UNIQUE KEY `station_datum` (`station_id`, `datum`),
  FOREIGN KEY (`station_id`) REFERENCES `fest_stationen`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fest_mitarbeiter` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `fest_id`     INT NOT NULL,
  `mitglied_id` INT NULL,
  `vorname`     VARCHAR(50) NULL,
  `nachname`    VARCHAR(50) NULL,
  `telefon`     VARCHAR(30) NULL,
  `email`       VARCHAR(100) NULL,
  `funktion`    VARCHAR(100) NULL,
  `ist_extern`  TINYINT(1) NOT NULL DEFAULT 0,
  `notizen`     TEXT NULL,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`fest_id`)     REFERENCES `feste`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fest_dienstplaene` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `fest_id`        INT NOT NULL,
  `station_id`     INT NOT NULL,
  `mitarbeiter_id` INT NOT NULL,
  `datum`          DATE NOT NULL,
  `zeit_von`       TIME NULL,
  `zeit_bis`       TIME NULL,
  `notizen`        TEXT NULL,
  `erstellt_am`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`fest_id`)        REFERENCES `feste`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`station_id`)     REFERENCES `fest_stationen`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`mitarbeiter_id`) REFERENCES `fest_mitarbeiter`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fest_einkauf_kategorien` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(80) NOT NULL,
  `sortierung`  INT NOT NULL DEFAULT 0,
  `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fest_einkauefe` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `fest_id`      INT NOT NULL,
  `kategorie_id` INT NULL,
  `station_id`   INT NULL,
  `bezeichnung`  VARCHAR(255) NOT NULL,
  `menge`        INT NOT NULL DEFAULT 1,
  `einheit`      VARCHAR(20) NULL,
  `preis`        DECIMAL(10,2) NULL,
  `lieferant`    VARCHAR(100) NULL,
  `status`       ENUM('geplant','bestellt','erhalten','storniert') NOT NULL DEFAULT 'geplant',
  `ist_vorlage`  TINYINT(1) NOT NULL DEFAULT 0,
  `notizen`      TEXT NULL,
  `erstellt_von` INT NULL,
  `erstellt_am`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`fest_id`)      REFERENCES `feste`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`kategorie_id`) REFERENCES `fest_einkauf_kategorien`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`station_id`)   REFERENCES `fest_stationen`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fest_vertraege` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `fest_id`         INT NOT NULL,
  `band_name`       VARCHAR(150) NOT NULL,
  `vertrags_datum`  DATE NULL,
  `auftritt_datum`  DATE NULL,
  `auftritt_zeit`   TIME NULL,
  `honorar`         DECIMAL(10,2) NULL,
  `zahlungsstatus`  ENUM('offen','angezahlt','bezahlt','storniert') NOT NULL DEFAULT 'offen',
  `zahlungsdatum`   DATE NULL,
  `dokument_pfad`   VARCHAR(255) NULL,
  `dokument_name`   VARCHAR(255) NULL,
  `notizen`         TEXT NULL,
  `erstellt_von`    INT NULL,
  `erstellt_am`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`fest_id`) REFERENCES `feste`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fest_todos` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `fest_id`       INT NOT NULL,
  `titel`         VARCHAR(255) NOT NULL,
  `beschreibung`  TEXT NULL,
  `prioritaet`    ENUM('niedrig','normal','hoch','kritisch') NOT NULL DEFAULT 'normal',
  `status`        ENUM('offen','in_bearbeitung','erledigt') NOT NULL DEFAULT 'offen',
  `faellig_am`    DATE NULL,
  `zustaendig_id` INT NULL,
  `erstellt_von`  INT NULL,
  `erstellt_am`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `erledigt_am`   DATETIME NULL,
  FOREIGN KEY (`fest_id`)       REFERENCES `feste`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`zustaendig_id`) REFERENCES `benutzer`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fest_abrechnung_posten` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `fest_id`      INT NOT NULL,
  `typ`          ENUM('einnahme','ausgabe') NOT NULL,
  `kategorie`    VARCHAR(80) NOT NULL DEFAULT 'sonstiges',
  `bezeichnung`  VARCHAR(255) NOT NULL,
  `betrag`       DECIMAL(10,2) NOT NULL DEFAULT 0,
  `station_id`   INT NULL,
  `notizen`      TEXT NULL,
  `erstellt_von` INT NULL,
  `erstellt_am`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`fest_id`)    REFERENCES `feste`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`station_id`) REFERENCES `fest_stationen`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `benutzer_rollen` (
  `benutzer_id` INT NOT NULL,
  `rolle_id`    INT NOT NULL,
  PRIMARY KEY (`benutzer_id`, `rolle_id`),
  FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`rolle_id`)    REFERENCES `rollen`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `aktivitaetslog`
--
ALTER TABLE `aktivitaetslog`
  ADD CONSTRAINT `aktivitaetslog_ibfk_1` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `anwesenheit`
--
ALTER TABLE `anwesenheit`
  ADD CONSTRAINT `anwesenheit_ibfk_1` FOREIGN KEY (`ausrueckung_id`) REFERENCES `ausrueckungen` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `anwesenheit_ibfk_2` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `ausrueckungen`
--
ALTER TABLE `ausrueckungen`
  ADD CONSTRAINT `ausrueckungen_ibfk_1` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `ausrueckung_noten`
--
ALTER TABLE `ausrueckung_noten`
  ADD CONSTRAINT `ausrueckung_noten_ibfk_1` FOREIGN KEY (`ausrueckung_id`) REFERENCES `ausrueckungen` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ausrueckung_noten_ibfk_2` FOREIGN KEY (`noten_id`) REFERENCES `noten` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `beitraege`
--
ALTER TABLE `beitraege`
  ADD CONSTRAINT `beitraege_ibfk_1` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `benutzer`
--
ALTER TABLE `benutzer`
  ADD CONSTRAINT `benutzer_ibfk_1` FOREIGN KEY (`rolle_id`) REFERENCES `rollen` (`id`),
  ADD CONSTRAINT `benutzer_ibfk_2` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `berechtigungen`
--
ALTER TABLE `berechtigungen`
  ADD CONSTRAINT `berechtigungen_ibfk_1` FOREIGN KEY (`rolle_id`) REFERENCES `rollen` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `dokumente`
--
ALTER TABLE `dokumente`
  ADD CONSTRAINT `dokumente_ibfk_1` FOREIGN KEY (`hochgeladen_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `finanzen`
--
ALTER TABLE `finanzen`
  ADD CONSTRAINT `finanzen_ibfk_1` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `instrumente`
--
ALTER TABLE `instrumente`
  ADD CONSTRAINT `instrumente_ibfk_1` FOREIGN KEY (`instrument_typ_id`) REFERENCES `instrument_typen` (`id`),
  ADD CONSTRAINT `instrumente_ibfk_2` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `instrument_typen`
--
ALTER TABLE `instrument_typen`
  ADD CONSTRAINT `instrument_typen_ibfk_1` FOREIGN KEY (`register_id`) REFERENCES `register` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `instrument_wartungen`
--
ALTER TABLE `instrument_wartungen`
  ADD CONSTRAINT `instrument_wartungen_ibfk_1` FOREIGN KEY (`instrument_id`) REFERENCES `instrumente` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `kalender_termine`
--
ALTER TABLE `kalender_termine`
  ADD CONSTRAINT `kalender_termine_ibfk_1` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `mitglieder`
--
ALTER TABLE `mitglieder`
  ADD CONSTRAINT `mitglieder_ibfk_1` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `mitglieder_ibfk_2` FOREIGN KEY (`register_id`) REFERENCES `register` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `mitglied_instrumente`
--
ALTER TABLE `mitglied_instrumente`
  ADD CONSTRAINT `mitglied_instrumente_ibfk_1` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mitglied_instrumente_ibfk_2` FOREIGN KEY (`instrument_typ_id`) REFERENCES `instrument_typen` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `noten_dateien`
--
ALTER TABLE `noten_dateien`
  ADD CONSTRAINT `noten_dateien_ibfk_1` FOREIGN KEY (`noten_id`) REFERENCES `noten` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `noten_dateien_ibfk_2` FOREIGN KEY (`hochgeladen_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `uniform_kleidungsstuecke`
--
ALTER TABLE `uniform_kleidungsstuecke`
  ADD CONSTRAINT `kleidungsstuecke_ibfk_1` FOREIGN KEY (`kategorie_id`) REFERENCES `uniform_kategorien` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `uniform_zuweisungen`
--
ALTER TABLE `uniform_zuweisungen`
  ADD CONSTRAINT `zuweisungen_ibfk_1` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `zuweisungen_ibfk_2` FOREIGN KEY (`kleidungsstueck_id`) REFERENCES `uniform_kleidungsstuecke` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
