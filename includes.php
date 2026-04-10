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

// Festverwaltung
require_once __DIR__ . '/classes/Fest.php';
require_once __DIR__ . '/classes/FestStation.php';
require_once __DIR__ . '/classes/FestMitarbeiter.php';
require_once __DIR__ . '/classes/FestDienstplan.php';
require_once __DIR__ . '/classes/FestEinkauf.php';
require_once __DIR__ . '/classes/FestVertrag.php';
require_once __DIR__ . '/classes/FestTodo.php';
require_once __DIR__ . '/classes/FestKopieren.php';
