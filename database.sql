-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Erstellungszeit: 10. Feb 2026 um 13:10
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

CREATE TABLE `aktivitaetslog` (
  `id` int(11) NOT NULL,
  `benutzer_id` int(11) DEFAULT NULL,
  `aktion` varchar(100) NOT NULL,
  `modul` varchar(50) DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `ip_adresse` varchar(45) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `aktivitaetslog`
--

INSERT INTO `aktivitaetslog` (`id`, `benutzer_id`, `aktion`, `modul`, `beschreibung`, `ip_adresse`, `erstellt_am`) VALUES
(1, 1, 'login', NULL, 'Benutzer hat sich angemeldet', '46.124.148.41', '2026-02-10 10:25:42'),
(2, 1, 'logout', NULL, 'Benutzer hat sich abgemeldet', '46.124.148.41', '2026-02-10 11:37:30'),
(3, 1, 'login', NULL, 'Benutzer hat sich angemeldet', '46.124.148.41', '2026-02-10 11:38:01'),
(4, 1, 'logout', NULL, 'Benutzer hat sich abgemeldet', '46.124.148.41', '2026-02-10 11:38:15'),
(5, 4, 'login', NULL, 'Benutzer hat sich angemeldet', '46.124.148.41', '2026-02-10 11:38:19'),
(6, 4, 'logout', NULL, 'Benutzer hat sich abgemeldet', '46.124.148.41', '2026-02-10 11:39:16'),
(7, 1, 'login', NULL, 'Benutzer hat sich angemeldet', '46.124.148.41', '2026-02-10 11:39:20'),
(8, 1, 'logout', NULL, 'Benutzer hat sich abgemeldet', '46.124.148.41', '2026-02-10 11:39:40'),
(9, 3, 'login', NULL, 'Benutzer hat sich angemeldet', '46.124.148.41', '2026-02-10 11:39:42'),
(10, 3, 'logout', NULL, 'Benutzer hat sich abgemeldet', '46.124.148.41', '2026-02-10 11:40:37');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `anwesenheit`
--

CREATE TABLE `anwesenheit` (
  `id` int(11) NOT NULL,
  `ausrueckung_id` int(11) NOT NULL,
  `mitglied_id` int(11) NOT NULL,
  `status` enum('zugesagt','abgesagt','keine_antwort','anwesend','abwesend') DEFAULT 'keine_antwort',
  `grund` text DEFAULT NULL,
  `gemeldet_am` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `anwesenheit`
--

INSERT INTO `anwesenheit` (`id`, `ausrueckung_id`, `mitglied_id`, `status`, `grund`, `gemeldet_am`) VALUES
(1, 2, 1, 'zugesagt', NULL, '2026-02-10 09:25:15'),
(2, 2, 2, 'zugesagt', NULL, '2026-02-10 09:25:15'),
(3, 2, 3, 'zugesagt', NULL, '2026-02-10 09:25:15'),
(4, 2, 4, 'zugesagt', NULL, '2026-02-10 09:25:15'),
(5, 2, 5, 'abgesagt', 'Familiäre Verpflichtung', '2026-02-10 09:25:15'),
(6, 2, 6, 'zugesagt', NULL, '2026-02-10 09:25:15'),
(7, 2, 7, 'zugesagt', NULL, '2026-02-10 09:25:15'),
(8, 2, 8, 'zugesagt', NULL, '2026-02-10 09:25:15'),
(9, 2, 10, 'zugesagt', NULL, '2026-02-10 09:25:15'),
(10, 2, 12, 'keine_antwort', NULL, NULL),
(11, 3, 13, 'zugesagt', '', '2026-02-10 10:31:11');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ausrueckungen`
--

CREATE TABLE `ausrueckungen` (
  `id` int(11) NOT NULL,
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
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `ausrueckungen`
--

INSERT INTO `ausrueckungen` (`id`, `titel`, `beschreibung`, `typ`, `start_datum`, `ende_datum`, `ganztaegig`, `ort`, `adresse`, `treffpunkt`, `treffpunkt_zeit`, `uniform`, `notizen`, `google_calendar_id`, `status`, `erstellt_von`, `erstellt_am`, `aktualisiert_am`) VALUES
(1, 'Wöchentliche Probe', 'Reguläre Freitagsprobe', 'Probe', '2026-01-10 19:30:00', '2026-01-10 22:00:00', 0, 'Probelokal', 'Musikerstraße 1, 8010 Graz', 'Probelokal', '19:15:00', 0, NULL, NULL, 'bestaetigt', 1, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(2, 'Neujahrskonzert 2026', 'Traditionelles Neujahrskonzert mit Werken von Johann Strauss', 'Konzert', '2026-01-11 17:00:00', '2026-01-11 20:00:00', 0, 'Stadthalle Graz', 'Messestraße 1, 8010 Graz', 'Hintereingang Stadthalle', '15:30:00', 1, NULL, NULL, 'bestaetigt', 1, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(3, 'Faschingsumzug', 'Teilnahme am Palfauer Faschingsumzug,\r\nwir gehen als Fliegen', 'Ausrückung', '2026-02-15 14:00:00', '2026-02-15 17:00:00', 0, 'Rüsthaus Palfau', 'Palfau 2', 'Rüsthaus Palfau', '13:45:00', 0, '', NULL, 'bestaetigt', 1, '2026-02-10 09:25:15', '2026-02-10 10:31:05'),
(4, 'Frühjahrskonzert', 'Jahreskonzert mit neuem Programm', 'Konzert', '2026-04-18 19:00:00', '2026-04-18 22:00:00', 0, 'Kulturhaus', 'Kulturstraße 5, 8020 Graz', 'Eingang Kulturhaus', '17:00:00', 1, NULL, NULL, 'geplant', 1, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(5, 'Maibaum aufstellen', 'Traditionelles Maibaumaufstellen', 'Ausrückung', '2026-04-30 18:00:00', '2026-04-30 22:00:00', 0, 'Dorfplatz', 'Hauptstraße, 8045 Graz-Andritz', 'Feuerwehrhaus', '17:30:00', 1, NULL, NULL, 'geplant', 1, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(6, 'Fronleichnam', 'Prozession und Festmesse', 'Ausrückung', '2026-06-04 08:00:00', '2026-06-04 12:00:00', 0, 'Pfarrkirche', 'Kirchplatz 1', 'Kirchplatz', '07:30:00', 1, NULL, NULL, 'geplant', 1, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(7, 'Sommerfest', 'Vereinssommerfest mit Musik und Kulinarik', 'Fest', '2026-07-18 14:00:00', '2026-07-18 23:00:00', 0, 'Festgelände', 'Am Sportplatz 2', NULL, NULL, 1, NULL, NULL, 'geplant', 1, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(8, 'Platzkonzert', 'Sommerliches Platzkonzert', 'Konzert', '2026-08-08 18:00:00', '2026-08-08 20:00:00', 0, 'Hauptplatz Graz', 'Hauptplatz', 'Rathaus', '17:00:00', 1, NULL, NULL, 'geplant', 1, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(9, 'Erntedankfest', 'Musikalische Umrahmung des Erntedankfestes', 'Ausrückung', '2026-09-27 09:00:00', '2026-09-27 14:00:00', 0, 'Pfarrkirche', 'Kirchplatz 1', 'Kirchplatz', '08:30:00', 1, NULL, NULL, 'geplant', 1, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(10, 'Martinsfest', 'Laternenumzug mit Blasmusik', 'Ausrückung', '2026-11-11 17:00:00', '2026-11-11 20:00:00', 0, 'Kindergarten', 'Kindergartenweg 3', 'Kindergarten', '16:30:00', 1, NULL, NULL, 'geplant', 1, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(11, 'Adventkonzert', 'Besinnliches Konzert im Advent', 'Konzert', '2026-12-13 17:00:00', '2026-12-13 19:30:00', 0, 'Pfarrkirche', 'Kirchplatz 1', 'Sakristei', '15:30:00', 1, NULL, NULL, 'geplant', 1, '2026-02-10 09:25:15', '2026-02-10 09:25:15');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ausrueckung_noten`
--

CREATE TABLE `ausrueckung_noten` (
  `id` int(11) NOT NULL,
  `ausrueckung_id` int(11) NOT NULL,
  `noten_id` int(11) NOT NULL,
  `reihenfolge` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `ausrueckung_noten`
--

INSERT INTO `ausrueckung_noten` (`id`, `ausrueckung_id`, `noten_id`, `reihenfolge`) VALUES
(1, 2, 3, 1),
(2, 2, 12, 2),
(3, 2, 1, 3),
(4, 2, 5, 4),
(5, 4, 6, 1),
(6, 4, 10, 2),
(7, 4, 11, 3),
(8, 4, 9, 4);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `beitraege`
--

CREATE TABLE `beitraege` (
  `id` int(11) NOT NULL,
  `mitglied_id` int(11) NOT NULL,
  `jahr` int(11) NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `bezahlt_am` date DEFAULT NULL,
  `bezahlt` tinyint(1) DEFAULT 0,
  `zahlungsart` enum('bar','überweisung','lastschrift') DEFAULT 'überweisung',
  `notizen` text DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `beitraege`
--

INSERT INTO `beitraege` (`id`, `mitglied_id`, `jahr`, `betrag`, `bezahlt_am`, `bezahlt`, `zahlungsart`, `notizen`, `erstellt_am`) VALUES
(1, 1, 2025, 120.00, '2025-01-15', 1, 'überweisung', NULL, '2026-02-10 09:25:15'),
(2, 2, 2025, 120.00, '2025-01-20', 1, 'überweisung', NULL, '2026-02-10 09:25:15'),
(3, 3, 2025, 120.00, '2025-02-01', 1, 'bar', NULL, '2026-02-10 09:25:15'),
(4, 4, 2025, 120.00, '2025-01-18', 1, 'überweisung', NULL, '2026-02-10 09:25:15'),
(5, 5, 2025, 120.00, '2025-01-25', 1, 'lastschrift', NULL, '2026-02-10 09:25:15'),
(6, 6, 2025, 120.00, '2025-02-10', 1, 'überweisung', NULL, '2026-02-10 09:25:15'),
(7, 7, 2025, 120.00, '2025-01-30', 1, 'bar', NULL, '2026-02-10 09:25:15'),
(8, 8, 2025, 120.00, '2025-02-05', 1, 'überweisung', NULL, '2026-02-10 09:25:15'),
(9, 10, 2025, 120.00, '2025-01-22', 1, 'überweisung', NULL, '2026-02-10 09:25:15'),
(10, 11, 2025, 60.00, '2025-03-01', 1, 'überweisung', NULL, '2026-02-10 09:25:15'),
(11, 12, 2025, 120.00, NULL, 0, 'überweisung', NULL, '2026-02-10 09:25:15'),
(14, 11, 2026, 25.00, '2026-02-10', 1, 'überweisung', NULL, '2026-02-10 10:46:02');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `benutzer`
--

CREATE TABLE `benutzer` (
  `id` int(11) NOT NULL,
  `benutzername` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `passwort_hash` varchar(255) DEFAULT NULL,
  `rolle` enum('admin','obmann','kassier','schriftfuehrer','mitglied','instrumentenwart','kapellmeister','trachtenwart','jugendbeauftragter','notenwart') DEFAULT 'mitglied',
  `rolle_id` int(11) DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT 1,
  `letzter_login` datetime DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mitglied_id` int(11) DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `benutzer`
--

INSERT INTO `benutzer` (`id`, `benutzername`, `email`, `passwort_hash`, `rolle`, `rolle_id`, `aktiv`, `letzter_login`, `erstellt_am`, `aktualisiert_am`, `mitglied_id`, `google_id`) VALUES
(1, 'admin', 'admin@musikverein.at', '$2a$12$V3H/Yxfr3hyjpyE299xFLuVdUofhCSep7Knduu4.AN/LzXwfyHcRm', 'admin', 1, 1, '2026-02-10 11:39:20', '2026-02-10 09:25:15', '2026-02-10 11:39:20', 13, NULL),
(2, 'obmann', 'obmann@musikverein.at', '$2a$12$V3H/Yxfr3hyjpyE299xFLuVdUofhCSep7Knduu4.AN/LzXwfyHcRm', 'obmann', 2, 1, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15', NULL, NULL),
(3, 'kapellmeister', 'kapellmeister@musikverein.at', '$2y$10$pkMirixnRkZkQ0ZRZW.9p.9O9Irk1uWa5BJCV.dEgQhffpdB3gwNu', 'kapellmeister', 3, 1, '2026-02-10 11:39:42', '2026-02-10 09:25:15', '2026-02-10 11:39:42', NULL, NULL),
(4, 'kassier', 'kassier@musikverein.at', '$2y$10$Ytv3UudMWt.0FnfGHe.pJOfwtdeVS/hPrZRQVkV3vx8h1soa6jbMO', 'kassier', 4, 1, '2026-02-10 11:38:19', '2026-02-10 09:25:15', '2026-02-10 11:38:19', NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `berechtigungen`
--

CREATE TABLE `berechtigungen` (
  `id` int(11) NOT NULL,
  `rolle_id` int(11) DEFAULT NULL,
  `rolle` varchar(50) NOT NULL,
  `modul` varchar(50) NOT NULL,
  `lesen` tinyint(1) DEFAULT 0,
  `schreiben` tinyint(1) DEFAULT 0,
  `loeschen` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `dokumente` (
  `id` int(11) NOT NULL,
  `titel` varchar(200) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `dateiname` varchar(255) NOT NULL,
  `dateipfad` varchar(500) NOT NULL,
  `dateityp` varchar(50) DEFAULT NULL,
  `dateigroesse` int(11) DEFAULT NULL,
  `kategorie` varchar(100) DEFAULT NULL,
  `hochgeladen_von` int(11) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `einstellungen`
--

CREATE TABLE `einstellungen` (
  `id` int(11) NOT NULL,
  `schluessel` varchar(100) NOT NULL,
  `wert` text DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(39, 'beitrag_ausgetreten', '0', NULL, '2026-02-10 10:45:19');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `finanzen`
--

CREATE TABLE `finanzen` (
  `id` int(11) NOT NULL,
  `typ` enum('einnahme','ausgabe') NOT NULL,
  `datum` date NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `kategorie` varchar(100) DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `beleg_nummer` varchar(50) DEFAULT NULL,
  `zahlungsart` varchar(50) DEFAULT NULL,
  `erstellt_von` int(11) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `finanzen`
--

INSERT INTO `finanzen` (`id`, `typ`, `datum`, `betrag`, `kategorie`, `beschreibung`, `beleg_nummer`, `zahlungsart`, `erstellt_von`, `erstellt_am`) VALUES
(1, 'einnahme', '2025-01-15', 1440.00, 'Mitgliedsbeiträge', 'Mitgliedsbeiträge 2025 (12 x 120 €)', 'E-2025-001', 'überweisung', 4, '2026-02-10 09:25:15'),
(2, 'einnahme', '2025-03-22', 850.00, 'Spende', 'Spende Firma Müller GmbH', 'E-2025-002', 'überweisung', 4, '2026-02-10 09:25:15'),
(3, 'einnahme', '2025-04-18', 2340.00, 'Konzerteinnahmen', 'Kartenverkauf Frühjahrskonzert', 'E-2025-003', 'bar', 4, '2026-02-10 09:25:15'),
(4, 'einnahme', '2025-07-18', 4250.00, 'Festeinnahmen', 'Erlös Sommerfest 2025', 'E-2025-004', 'bar', 4, '2026-02-10 09:25:15'),
(5, 'einnahme', '2025-09-10', 500.00, 'Subvention', 'Kulturförderung Gemeinde', 'E-2025-005', 'überweisung', 4, '2026-02-10 09:25:15'),
(6, 'ausgabe', '2025-02-10', 320.00, 'Noten', 'Neue Noten für Frühjahrskonzert', 'A-2025-001', 'überweisung', 4, '2026-02-10 09:25:15'),
(7, 'ausgabe', '2025-03-15', 580.00, 'Instrumentenwartung', 'Wartung Blechblasinstrumente', 'A-2025-002', 'überweisung', 4, '2026-02-10 09:25:15'),
(8, 'ausgabe', '2025-04-01', 150.00, 'Raummiete', 'Saalmiete Frühjahrskonzert', 'A-2025-003', 'überweisung', 4, '2026-02-10 09:25:15'),
(9, 'ausgabe', '2025-06-20', 890.00, 'Uniformen', 'Neue Uniformteile (Hemden)', 'A-2025-004', 'überweisung', 4, '2026-02-10 09:25:15'),
(10, 'ausgabe', '2025-07-05', 1200.00, 'Festorganisation', 'Zeltmiete, Bühne Sommerfest', 'A-2025-005', 'überweisung', 4, '2026-02-10 09:25:15'),
(11, 'ausgabe', '2025-11-15', 95.00, 'Versicherung', 'Jahresprämie Instrumentenversicherung', 'A-2025-006', 'überweisung', 4, '2026-02-10 09:25:15'),
(12, 'einnahme', '2026-02-10', 123.00, 'Test', '', '123', 'bar', 1, '2026-02-10 10:47:16');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `instrumente`
--

CREATE TABLE `instrumente` (
  `id` int(11) NOT NULL,
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
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `instrumente`
--

INSERT INTO `instrumente` (`id`, `inventar_nummer`, `instrument_typ_id`, `hersteller`, `modell`, `seriennummer`, `baujahr`, `anschaffungsdatum`, `anschaffungspreis`, `zustand`, `standort`, `versicherungswert`, `mitglied_id`, `ausgeliehen_seit`, `notizen`, `foto`, `erstellt_am`, `aktualisiert_am`) VALUES
(1, 'INV-001', 7, 'Miraphone', 'Loimayr', 'M123456', 2018, '2018-06-15', 4500.00, 'sehr gut', 'Probelokal', 4000.00, 13, '2026-02-10', '', NULL, '2026-02-10 09:25:15', '2026-02-10 10:32:27'),
(2, 'INV-002', 7, 'Miraphone', 'Premium', 'M234567', 2015, '2015-03-20', 3800.00, 'gut', 'Probelokal', 3200.00, NULL, NULL, NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(3, 'INV-003', 8, 'Yamaha', 'YTR-4335G', 'Y345678', 2020, '2020-01-10', 1200.00, 'sehr gut', 'Probelokal', 1100.00, 3, '2020-02-01', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(4, 'INV-004', 8, 'Bach', 'Stradivarius', 'B456789', 2012, '2012-09-01', 2500.00, 'gut', 'Probelokal', 2000.00, NULL, NULL, NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(5, 'INV-005', 1, 'Yamaha', 'YFL-212', 'Y567890', 2019, '2019-08-15', 800.00, 'sehr gut', 'Probelokal', 700.00, 2, '2019-09-01', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(6, 'INV-006', 2, 'Buffet Crampon', 'E11', 'BC678901', 2017, '2017-06-01', 1500.00, 'gut', 'Probelokal', 1300.00, 10, '2020-09-01', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(7, 'INV-007', 13, 'Miraphone', 'Hagen 495', 'M789012', 2010, '2010-04-20', 8500.00, 'gut', 'Probelokal', 7000.00, 7, '2010-05-01', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(8, 'INV-008', 15, 'Sonor', 'Force 3007', 'S890123', 2016, '2016-11-30', 2200.00, 'gut', 'Probelokal', 1800.00, 8, '2017-09-01', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(9, 'INV-009', 4, 'Selmer', 'AS42', 'SE901234', 2021, '2021-02-15', 3500.00, 'sehr gut', 'Probelokal', 3200.00, 4, '2021-03-01', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(10, 'INV-010', 11, 'Conn', '88H', 'C012345', 2014, '2014-07-10', 2800.00, 'befriedigend', 'Probelokal', 2000.00, 6, '2014-09-01', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `instrument_typen`
--

CREATE TABLE `instrument_typen` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `register_id` int(11) DEFAULT NULL,
  `beschreibung` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `instrument_wartungen`
--

CREATE TABLE `instrument_wartungen` (
  `id` int(11) NOT NULL,
  `instrument_id` int(11) NOT NULL,
  `datum` date NOT NULL,
  `art` enum('Wartung','Reparatur','Überholung','Reinigung') NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `kosten` decimal(10,2) DEFAULT NULL,
  `durchgefuehrt_von` varchar(100) DEFAULT NULL,
  `naechste_wartung` date DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `instrument_wartungen`
--

INSERT INTO `instrument_wartungen` (`id`, `instrument_id`, `datum`, `art`, `beschreibung`, `kosten`, `durchgefuehrt_von`, `naechste_wartung`, `erstellt_am`) VALUES
(1, 1, '2024-06-15', 'Wartung', 'Jährliche Wartung, Ventilöl gewechselt', 85.00, 'Musikhaus Graz', '2025-06-15', '2026-02-10 09:25:15'),
(2, 3, '2024-09-20', 'Reinigung', 'Generalreinigung nach Festsaison', 45.00, 'Musikhaus Graz', NULL, '2026-02-10 09:25:15'),
(3, 7, '2024-03-10', 'Überholung', 'Komplette Überholung mit Neulackierung', 650.00, 'Instrumentenbau Müller', '2027-03-10', '2026-02-10 09:25:15'),
(4, 8, '2024-11-05', 'Reparatur', 'Fell der Snare Drum ersetzt', 120.00, 'Drumshop Wien', NULL, '2026-02-10 09:25:15'),
(5, 1, '2026-02-10', 'Wartung', 'Alles schmieren', 25.00, 'Haagstone', '0000-00-00', '2026-02-10 10:33:10');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kalender_termine`
--

CREATE TABLE `kalender_termine` (
  `id` int(11) NOT NULL,
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
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `kalender_termine`
--

INSERT INTO `kalender_termine` (`id`, `titel`, `beschreibung`, `typ`, `start_datum`, `ende_datum`, `ganztaegig`, `ort`, `farbe`, `erstellt_von`, `erstellt_am`, `aktualisiert_am`) VALUES
(1, 'Vorstandssitzung', 'Monatliche Vorstandssitzung', 'Besprechung', '2026-01-15 19:00:00', '2026-01-15 21:00:00', 0, 'Probelokal', '#0d6efd', NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(2, 'Vorstandssitzung', 'Monatliche Vorstandssitzung', 'Besprechung', '2026-02-12 19:00:00', '2026-02-12 21:00:00', 0, 'Probelokal', '#0d6efd', NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(3, 'Vorstandssitzung', 'Monatliche Vorstandssitzung', 'Besprechung', '2026-03-11 19:00:00', '2026-03-11 21:00:00', 0, 'Probelokal', '#0d6efd', NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(4, 'Generalversammlung', 'Jahreshauptversammlung mit Neuwahlen', 'Besprechung', '2026-03-20 19:00:00', '2026-03-20 22:00:00', 0, 'Gasthaus Zur Post', '#dc3545', NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(5, 'Instrumentenwartung', 'Jährliche Wartung aller Vereinsinstrumente', 'Termin', '2026-03-05 14:00:00', '2026-03-05 17:00:00', 0, 'Probelokal', '#6f42c1', NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(6, 'Geburtstag Johann Huber', '', 'Geburtstag', '2026-03-15 00:00:00', NULL, 1, NULL, '#ffc107', NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(7, 'Probenwochenende', 'Intensivproben für Frühjahrskonzert', 'Termin', '2026-04-04 09:00:00', '2026-04-05 17:00:00', 0, 'Jugendheim Bergland', '#198754', NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitglieder`
--

CREATE TABLE `mitglieder` (
  `id` int(11) NOT NULL,
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
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `mitglieder`
--

INSERT INTO `mitglieder` (`id`, `mitgliedsnummer`, `vorname`, `nachname`, `geburtsdatum`, `geschlecht`, `strasse`, `plz`, `ort`, `land`, `telefon`, `mobil`, `email`, `benutzer_id`, `register_id`, `eintritt_datum`, `austritt_datum`, `status`, `notizen`, `foto`, `erstellt_am`, `aktualisiert_am`) VALUES
(1, 'MV-001', 'Johann', 'Huber', '1985-03-15', 'm', 'Hauptstraße 12', '8010', 'Graz', 'Österreich', '+43 316 123456', '+43 664 1234567', 'johann.huber@email.at', NULL, 3, '2010-09-01', NULL, 'aktiv', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(2, 'MV-002', 'Maria', 'Gruber', '1990-07-22', 'w', 'Kirchgasse 5', '8020', 'Graz', 'Österreich', '+43 316 234567', '+43 664 2345678', 'maria.gruber@email.at', NULL, 1, '2015-01-15', NULL, 'aktiv', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(3, 'MV-003', 'Stefan', 'Maier', '1978-11-30', 'm', 'Feldweg 8', '8045', 'Graz-Andritz', 'Österreich', '+43 316 345678', '+43 664 3456789', 'stefan.maier@email.at', NULL, 4, '2008-03-01', NULL, 'aktiv', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(4, 'MV-004', 'Anna', 'Berger', '1995-05-10', 'w', 'Lindenstraße 23', '8010', 'Graz', 'Österreich', NULL, '+43 664 4567890', 'anna.berger@email.at', NULL, 2, '2018-09-01', NULL, 'aktiv', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(5, 'MV-005', 'Thomas', 'Schwarz', '1982-09-05', 'm', 'Bachgasse 17', '8020', 'Graz', 'Österreich', '+43 316 567890', '+43 664 5678901', 'thomas.schwarz@email.at', NULL, 5, '2012-06-01', NULL, 'aktiv', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(6, 'MV-006', 'Elisabeth', 'Wagner', '1988-12-20', 'w', 'Mozartstraße 4', '8010', 'Graz', 'Österreich', NULL, '+43 664 6789012', 'elisabeth.wagner@email.at', NULL, 6, '2014-02-15', NULL, 'aktiv', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(7, 'MV-007', 'Michael', 'Bauer', '1975-06-18', 'm', 'Schlossallee 9', '8045', 'Graz-Andritz', 'Österreich', '+43 316 678901', '+43 664 7890123', 'michael.bauer@email.at', NULL, 7, '2005-01-01', NULL, 'aktiv', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(8, 'MV-008', 'Sabine', 'Fischer', '1992-02-28', 'w', 'Gartenweg 11', '8020', 'Graz', 'Österreich', NULL, '+43 664 8901234', 'sabine.fischer@email.at', NULL, 8, '2017-09-01', NULL, 'aktiv', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(9, 'MV-009', 'Franz', 'Hofer', '1965-04-12', 'm', 'Waldstraße 33', '8010', 'Graz', 'Österreich', '+43 316 789012', '+43 664 9012345', 'franz.hofer@email.at', NULL, 3, '1990-01-01', NULL, 'ehrenmitglied', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(10, 'MV-010', 'Katharina', 'Müller', '1998-08-08', 'w', 'Schulstraße 7', '8045', 'Graz-Andritz', 'Österreich', NULL, '+43 664 0123456', 'katharina.mueller@email.at', NULL, 1, '2020-09-01', NULL, 'aktiv', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(11, 'MV-011', 'Robert', 'Steiner', '1970-01-25', 'm', 'Bergstraße 15', '8020', 'Graz', 'Österreich', '+43 316 890123', NULL, 'robert.steiner@email.at', NULL, 5, '1995-03-01', NULL, 'passiv', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(12, 'MV-012', 'Lisa', 'Winkler', '2005-11-14', 'w', 'Sonnenweg 2', '8010', 'Graz', 'Österreich', NULL, '+43 664 1122334', 'lisa.winkler@email.at', NULL, 2, '2022-09-01', NULL, 'aktiv', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(13, 'MV0001', 'Johann', 'Danner', '1972-01-22', 'm', 'Palfau 219', '8923', 'Landl', 'Österreich', '', '+43 664 8101975', 'johann.danner@gmail.com', NULL, 3, '2011-05-01', NULL, 'aktiv', '', NULL, '2026-02-10 10:28:10', '2026-02-10 10:28:10');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitglied_instrumente`
--

CREATE TABLE `mitglied_instrumente` (
  `id` int(11) NOT NULL,
  `mitglied_id` int(11) NOT NULL,
  `instrument_typ_id` int(11) NOT NULL,
  `hauptinstrument` tinyint(1) DEFAULT 0,
  `seit_datum` date DEFAULT NULL,
  `bis_datum` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `mitglied_instrumente`
--

INSERT INTO `mitglied_instrumente` (`id`, `mitglied_id`, `instrument_typ_id`, `hauptinstrument`, `seit_datum`, `bis_datum`) VALUES
(1, 1, 7, 1, '2010-09-01', NULL),
(2, 1, 8, 0, '2015-01-01', NULL),
(3, 2, 1, 1, '2015-01-15', NULL),
(4, 2, 2, 0, '2018-01-01', NULL),
(5, 3, 8, 1, '2008-03-01', NULL),
(6, 4, 4, 1, '2018-09-01', NULL),
(7, 5, 9, 1, '2012-06-01', NULL),
(8, 5, 10, 0, '2016-01-01', NULL),
(9, 6, 11, 1, '2014-02-15', NULL),
(10, 7, 13, 1, '2005-01-01', NULL),
(11, 8, 15, 1, '2017-09-01', NULL),
(12, 8, 16, 0, '2019-01-01', NULL),
(13, 9, 7, 1, '1990-01-01', NULL),
(14, 10, 2, 1, '2020-09-01', NULL),
(15, 11, 10, 1, '1995-03-01', NULL),
(16, 12, 5, 1, '2022-09-01', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `noten`
--

CREATE TABLE `noten` (
  `id` int(11) NOT NULL,
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
  `aktualisiert_am` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `noten`
--

INSERT INTO `noten` (`id`, `titel`, `untertitel`, `komponist`, `arrangeur`, `verlag`, `besetzung`, `schwierigkeitsgrad`, `dauer_minuten`, `genre`, `archiv_nummer`, `anzahl_stimmen`, `zustand`, `bemerkungen`, `standort`, `pdf_datei`, `audio_datei`, `erstellt_am`, `aktualisiert_am`) VALUES
(1, 'Böhmische Liebe', 'Polka', 'Ernst Mosch', 'Franz Watz', 'Rundel', 'Blasorchester', '3', 3, 'Polka', 'A-001', 28, 'gut', NULL, 'Schrank A', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(2, 'Egerländer Musikantenmarsch', NULL, 'Ernst Mosch', NULL, 'Rundel', 'Blasorchester', '3', 4, 'Marsch', 'A-002', 25, 'sehr gut', NULL, 'Schrank A', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(3, 'Mein Heimatland', 'Konzertwalzer', 'Julius Fučík', 'Siegfried Rundel', 'Rundel', 'Blasorchester', '4', 8, 'Walzer', 'A-003', 30, 'gut', NULL, 'Schrank A', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(4, 'Glückauf!', 'Marsch', 'Carl Latann', NULL, 'Edition Helbling', 'Blasorchester', '2', 4, 'Marsch', 'A-004', 22, 'gut', NULL, 'Schrank A', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(5, 'Vogelwiese', 'Polka', 'Guido Henn', NULL, 'Musictown', 'Blasorchester', '3', 3, 'Polka', 'B-001', 25, 'sehr gut', NULL, 'Schrank B', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(6, 'Highland Cathedral', NULL, 'Michael Korb', 'Hans-Joachim Rhinow', 'Obrasso', 'Blasorchester', '4', 5, 'Konzertstück', 'B-002', 28, 'gut', NULL, 'Schrank B', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(7, 'Also sprach Zarathustra', 'Fanfare', 'Richard Strauss', 'Paul Lavender', 'Hal Leonard', 'Blasorchester', '5', 2, 'Klassik', 'B-003', 35, 'befriedigend', NULL, 'Schrank B', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(8, 'Tiroler Holzhackerbuab\'n', 'Polka', 'Josef Franz Wagner', NULL, 'Kliment', 'Blasorchester', '3', 3, 'Polka', 'B-004', 24, 'gut', NULL, 'Schrank B', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(9, 'Böhmischer Traum', 'Polka', 'Norbert Gälle', NULL, 'Musikverlag Geiger', 'Blasorchester', '4', 4, 'Polka', 'C-001', 28, 'sehr gut', NULL, 'Schrank C', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(10, 'Gabriel\'s Oboe', 'aus \"The Mission\"', 'Ennio Morricone', 'Johan de Meij', 'Amstel Music', 'Blasorchester', '4', 5, 'Filmmusik', 'C-002', 30, 'gut', NULL, 'Schrank C', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(11, 'Music', NULL, 'John Miles', 'Thijs Oud', 'De Haske', 'Blasorchester', '5', 7, 'Pop', 'C-003', 32, 'gut', NULL, 'Schrank C', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(12, 'Florentiner Marsch', NULL, 'Julius Fučík', 'Fritz Neuböck', 'Rundel', 'Blasorchester', '4', 5, 'Marsch', 'C-004', 28, 'sehr gut', NULL, 'Schrank C', NULL, NULL, '2026-02-10 09:25:15', '2026-02-10 09:25:15'),
(13, 'gfdsgfdsg', 'fdgfdsdf', NULL, NULL, NULL, NULL, '3', NULL, NULL, 'N00001', NULL, 'gut', NULL, NULL, NULL, NULL, '2026-02-10 11:26:22', '2026-02-10 11:26:22'),
(14, 'gaaaaaaaaaa', 'fdsfdsf', NULL, NULL, NULL, NULL, '3', NULL, NULL, 'N00002', NULL, 'gut', NULL, 'FA', NULL, NULL, '2026-02-10 11:26:53', '2026-02-10 11:27:34'),
(15, 'aaaaaaaaa', 'dgfdgf', NULL, NULL, NULL, NULL, '3', NULL, NULL, 'N00003', NULL, 'gut', NULL, 'X', NULL, NULL, '2026-02-10 11:27:53', '2026-02-10 11:27:53');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `noten_dateien`
--

CREATE TABLE `noten_dateien` (
  `id` int(11) NOT NULL,
  `noten_id` int(11) NOT NULL,
  `dateiname` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `dateityp` varchar(50) DEFAULT 'application/pdf',
  `dateigroesse` int(11) DEFAULT NULL,
  `beschreibung` varchar(255) DEFAULT NULL,
  `sortierung` int(11) DEFAULT 0,
  `hochgeladen_von` int(11) DEFAULT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `noten_dateien`
--

INSERT INTO `noten_dateien` (`id`, `noten_id`, `dateiname`, `original_name`, `dateityp`, `dateigroesse`, `beschreibung`, `sortierung`, `hochgeladen_von`, `erstellt_am`) VALUES
(1, 14, '14_698b07f6c2b28_1770719222.pdf', 'close.pdf', 'application/pdf', 33622, NULL, 1, 1, '2026-02-10 11:27:02'),
(2, 15, '15_698b084c5c814_1770719308.pdf', 'Architektenmappe_neu.pdf', 'application/pdf', 232451, NULL, 1, 1, '2026-02-10 11:28:28');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `register`
--

CREATE TABLE `register` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `sortierung` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `register`
--

INSERT INTO `register` (`id`, `name`, `beschreibung`, `sortierung`) VALUES
(1, 'Flöten/Klarinetten', 'Holzbläser - hohe Register', 1),
(2, 'Saxophone', 'Holzbläser - Saxophone', 2),
(3, 'Flügelhörner', 'Blechbläser - hohe Lage', 3),
(4, 'Trompeten', 'Blechbläser - hohe Lage', 4),
(5, 'Tenorhörner', 'Blechbläser - mittlere Lage', 5),
(6, 'Posaunen', 'Blechbläser - tiefe Lage', 6),
(7, 'Tuben', 'Blechbläser - Bass', 7),
(8, 'Schlagwerk', 'Perkussion', 8);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `rollen`
--

CREATE TABLE `rollen` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `ist_admin` tinyint(1) DEFAULT 0,
  `farbe` varchar(20) DEFAULT 'secondary',
  `sortierung` int(11) DEFAULT 100,
  `aktiv` tinyint(1) DEFAULT 1,
  `erstellt_am` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(10, 'notenwart', 'Notenwart - Verwaltung des Notenarchivs', 0, 'danger', 9, 1, '2026-02-10 10:30:25');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `uniform_kategorien`
--

CREATE TABLE `uniform_kategorien` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `sortierung` int(11) DEFAULT 100,
  `aktiv` tinyint(1) DEFAULT 1,
  `erstellt_am` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `uniform_kategorien`
--

INSERT INTO `uniform_kategorien` (`id`, `name`, `beschreibung`, `sortierung`, `aktiv`, `erstellt_am`) VALUES
(1, 'Tracht', 'Traditionelle Vereinstracht', 1, 1, '2026-02-10 09:25:15'),
(2, 'Festtracht', 'Tracht für besondere Anlässe', 2, 1, '2026-02-10 09:25:15'),
(3, 'Sommertracht', 'Leichte Tracht für Sommeranlässe', 3, 1, '2026-02-10 09:25:15'),
(4, 'Regenbekleidung', 'Wetterschutz bei Ausrückungen', 4, 1, '2026-02-10 09:25:15'),
(5, 'Accessoires', 'Hüte, Gürtel, Abzeichen etc.', 5, 1, '2026-02-10 09:25:15');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `uniform_kleidungsstuecke`
--

CREATE TABLE `uniform_kleidungsstuecke` (
  `id` int(11) NOT NULL,
  `kategorie_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `groessen_verfuegbar` varchar(255) DEFAULT NULL,
  `sortierung` int(11) DEFAULT 100,
  `aktiv` tinyint(1) DEFAULT 1,
  `erstellt_am` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `uniform_kleidungsstuecke`
--

INSERT INTO `uniform_kleidungsstuecke` (`id`, `kategorie_id`, `name`, `beschreibung`, `groessen_verfuegbar`, `sortierung`, `aktiv`, `erstellt_am`) VALUES
(1, 1, 'Jacke', 'Trachtenjacke für Festanlässe', 'XS, S, M, L, XL, XXL', 1, 1, '2026-02-10 09:41:06'),
(2, 1, 'Hose/Rock', 'Trachtenhose oder Trachtenrock', '34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54', 2, 1, '2026-02-10 09:41:06'),
(3, 1, 'Hemd/Bluse', 'Weißes Trachtenhemd oder Bluse', 'XS, S, M, L, XL, XXL', 3, 1, '2026-02-10 09:41:06'),
(4, 1, 'Weste', 'Trachtenweste', 'S, M, L, XL', 4, 1, '2026-02-10 09:41:06'),
(5, 2, 'Sommerhemd', 'Leichtes Hemd für Sommerauftritte', 'S, M, L, XL, XXL', 1, 1, '2026-02-10 09:41:06'),
(6, 2, 'Sommerhose', 'Leichte Hose', '34-54', 2, 1, '2026-02-10 09:41:06'),
(7, 3, 'Marschjacke', 'Jacke für Marschauftritte', 'S, M, L, XL, XXL', 1, 1, '2026-02-10 09:41:06'),
(8, 3, 'Marschhose', 'Hose für Marschauftritte', '34-54', 2, 1, '2026-02-10 09:41:06'),
(9, 4, 'Regencape', 'Transparentes Regencape', 'Einheitsgröße', 1, 1, '2026-02-10 09:41:06'),
(10, 4, 'Regenhut', 'Wasserdichter Hut', 'Einheitsgröße', 2, 1, '2026-02-10 09:41:06'),
(11, 5, 'Hut', 'Trachtenhut mit Gamsbart', '54, 56, 58, 60', 1, 1, '2026-02-10 09:41:06'),
(12, 5, 'Gürtel', 'Trachtengürtel', 'S, M, L, XL', 2, 1, '2026-02-10 09:41:06'),
(13, 5, 'Vereinsabzeichen', 'Offizielles Vereinsabzeichen', 'Einheitsgröße', 3, 1, '2026-02-10 09:41:06'),
(14, 5, 'Krawatte/Mascherl', 'Krawatte oder Mascherl', 'Einheitsgröße', 4, 1, '2026-02-10 09:41:06');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `uniform_zuweisungen`
--

CREATE TABLE `uniform_zuweisungen` (
  `id` int(11) NOT NULL,
  `mitglied_id` int(11) NOT NULL,
  `kleidungsstueck_id` int(11) NOT NULL,
  `groesse` varchar(20) DEFAULT NULL,
  `zustand` enum('sehr gut','gut','befriedigend','schlecht') DEFAULT 'gut',
  `ausgabe_datum` date DEFAULT NULL,
  `bemerkungen` text DEFAULT NULL,
  `erstellt_am` timestamp NULL DEFAULT current_timestamp(),
  `aktualisiert_am` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `uniform_zuweisungen`
--

INSERT INTO `uniform_zuweisungen` (`id`, `mitglied_id`, `kleidungsstueck_id`, `groesse`, `zustand`, `ausgabe_datum`, `bemerkungen`, `erstellt_am`, `aktualisiert_am`) VALUES
(1, 1, 1, 'L', 'gut', '2024-01-15', NULL, '2026-02-10 09:41:21', '2026-02-10 09:41:21'),
(2, 1, 2, '50', 'gut', '2024-01-15', NULL, '2026-02-10 09:41:21', '2026-02-10 09:41:21'),
(3, 1, 3, 'L', 'sehr gut', '2024-01-15', NULL, '2026-02-10 09:41:21', '2026-02-10 09:41:21'),
(4, 1, 11, '58', 'gut', '2024-01-15', NULL, '2026-02-10 09:41:21', '2026-02-10 09:41:21'),
(5, 2, 1, 'S', 'sehr gut', '2024-02-01', NULL, '2026-02-10 09:41:21', '2026-02-10 09:41:21'),
(6, 2, 2, '36', 'sehr gut', '2024-02-01', NULL, '2026-02-10 09:41:21', '2026-02-10 09:41:21'),
(7, 2, 3, 'S', 'sehr gut', '2024-02-01', NULL, '2026-02-10 09:41:21', '2026-02-10 09:41:21'),
(8, 3, 1, 'XL', 'befriedigend', '2024-01-20', NULL, '2026-02-10 09:41:21', '2026-02-10 09:41:21'),
(9, 3, 2, '52', 'gut', '2024-01-20', NULL, '2026-02-10 09:41:21', '2026-02-10 09:41:21');

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `aktivitaetslog`
--
ALTER TABLE `aktivitaetslog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `benutzer_id` (`benutzer_id`);

--
-- Indizes für die Tabelle `anwesenheit`
--
ALTER TABLE `anwesenheit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ausrueckung_id` (`ausrueckung_id`),
  ADD KEY `mitglied_id` (`mitglied_id`);

--
-- Indizes für die Tabelle `ausrueckungen`
--
ALTER TABLE `ausrueckungen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `ausrueckung_noten`
--
ALTER TABLE `ausrueckung_noten`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ausrueckung_id` (`ausrueckung_id`),
  ADD KEY `noten_id` (`noten_id`);

--
-- Indizes für die Tabelle `beitraege`
--
ALTER TABLE `beitraege`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mitglied_id` (`mitglied_id`);

--
-- Indizes für die Tabelle `benutzer`
--
ALTER TABLE `benutzer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `benutzername` (`benutzername`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `google_id` (`google_id`),
  ADD KEY `rolle_id` (`rolle_id`),
  ADD KEY `mitglied_id` (`mitglied_id`);

--
-- Indizes für die Tabelle `berechtigungen`
--
ALTER TABLE `berechtigungen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rolle_id` (`rolle_id`);

--
-- Indizes für die Tabelle `dokumente`
--
ALTER TABLE `dokumente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hochgeladen_von` (`hochgeladen_von`);

--
-- Indizes für die Tabelle `einstellungen`
--
ALTER TABLE `einstellungen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `schluessel` (`schluessel`);

--
-- Indizes für die Tabelle `finanzen`
--
ALTER TABLE `finanzen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `instrumente`
--
ALTER TABLE `instrumente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instrument_typ_id` (`instrument_typ_id`),
  ADD KEY `mitglied_id` (`mitglied_id`);

--
-- Indizes für die Tabelle `instrument_typen`
--
ALTER TABLE `instrument_typen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `register_id` (`register_id`);

--
-- Indizes für die Tabelle `instrument_wartungen`
--
ALTER TABLE `instrument_wartungen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instrument_id` (`instrument_id`);

--
-- Indizes für die Tabelle `kalender_termine`
--
ALTER TABLE `kalender_termine`
  ADD PRIMARY KEY (`id`),
  ADD KEY `erstellt_von` (`erstellt_von`);

--
-- Indizes für die Tabelle `mitglieder`
--
ALTER TABLE `mitglieder`
  ADD PRIMARY KEY (`id`),
  ADD KEY `benutzer_id` (`benutzer_id`),
  ADD KEY `register_id` (`register_id`);

--
-- Indizes für die Tabelle `mitglied_instrumente`
--
ALTER TABLE `mitglied_instrumente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mitglied_id` (`mitglied_id`),
  ADD KEY `instrument_typ_id` (`instrument_typ_id`);

--
-- Indizes für die Tabelle `noten`
--
ALTER TABLE `noten`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `noten_dateien`
--
ALTER TABLE `noten_dateien`
  ADD PRIMARY KEY (`id`),
  ADD KEY `noten_id` (`noten_id`),
  ADD KEY `hochgeladen_von` (`hochgeladen_von`);

--
-- Indizes für die Tabelle `register`
--
ALTER TABLE `register`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `rollen`
--
ALTER TABLE `rollen`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `uniform_kategorien`
--
ALTER TABLE `uniform_kategorien`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `uniform_kleidungsstuecke`
--
ALTER TABLE `uniform_kleidungsstuecke`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategorie_id` (`kategorie_id`);

--
-- Indizes für die Tabelle `uniform_zuweisungen`
--
ALTER TABLE `uniform_zuweisungen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mitglied_kleidung` (`mitglied_id`,`kleidungsstueck_id`),
  ADD KEY `kleidungsstueck_id` (`kleidungsstueck_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `aktivitaetslog`
--
ALTER TABLE `aktivitaetslog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT für Tabelle `anwesenheit`
--
ALTER TABLE `anwesenheit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT für Tabelle `ausrueckungen`
--
ALTER TABLE `ausrueckungen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT für Tabelle `ausrueckung_noten`
--
ALTER TABLE `ausrueckung_noten`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT für Tabelle `beitraege`
--
ALTER TABLE `beitraege`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT für Tabelle `benutzer`
--
ALTER TABLE `benutzer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `berechtigungen`
--
ALTER TABLE `berechtigungen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT für Tabelle `dokumente`
--
ALTER TABLE `dokumente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `einstellungen`
--
ALTER TABLE `einstellungen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT für Tabelle `finanzen`
--
ALTER TABLE `finanzen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT für Tabelle `instrumente`
--
ALTER TABLE `instrumente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT für Tabelle `instrument_typen`
--
ALTER TABLE `instrument_typen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT für Tabelle `instrument_wartungen`
--
ALTER TABLE `instrument_wartungen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `kalender_termine`
--
ALTER TABLE `kalender_termine`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT für Tabelle `mitglieder`
--
ALTER TABLE `mitglieder`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT für Tabelle `mitglied_instrumente`
--
ALTER TABLE `mitglied_instrumente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT für Tabelle `noten`
--
ALTER TABLE `noten`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT für Tabelle `noten_dateien`
--
ALTER TABLE `noten_dateien`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `register`
--
ALTER TABLE `register`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT für Tabelle `rollen`
--
ALTER TABLE `rollen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT für Tabelle `uniform_kategorien`
--
ALTER TABLE `uniform_kategorien`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `uniform_kleidungsstuecke`
--
ALTER TABLE `uniform_kleidungsstuecke`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT für Tabelle `uniform_zuweisungen`
--
ALTER TABLE `uniform_zuweisungen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
