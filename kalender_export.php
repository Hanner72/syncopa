<?php
/**
 * kalender_export.php
 * Generiert ICS-Feed für Ausrückungen zum Abonnieren
 */
require_once 'config.php';
require_once 'includes.php';

// Optional: Login erforderlich
// Session::requireLogin();

$db = Database::getInstance();

// Alle zukünftigen Ausrückungen laden
$sql = "SELECT 
            id,
            titel,
            start_datum,
            ende_datum,
            ganztaegig,
            ort,
            beschreibung,
            notizen,
            aktualisiert_am,
            erstellt_am
        FROM ausrueckungen 
        WHERE DATE(start_datum) >= CURDATE()
        ORDER BY start_datum";

$ausrueckungen = $db->fetchAll($sql);

// iCalendar erstellen
$calendar = new ICalendar();

foreach ($ausrueckungen as $ausrueckung) {
    // Start und Ende aus DATETIME
    $start = new DateTime($ausrueckung['start_datum']);
    
    // Endzeit: Wenn vorhanden, sonst +2 Stunden
    if ($ausrueckung['ende_datum']) {
        $end = new DateTime($ausrueckung['ende_datum']);
    } else {
        $end = clone $start;
        $end->modify('+2 hours');
    }
    
    // Beschreibung zusammenstellen
    $description = $ausrueckung['beschreibung'] ?: '';
    
    // Notizen nur für angemeldete Benutzer
    if (Session::isLoggedIn() && !empty($ausrueckung['notizen'])) {
        $description .= "\n\nInterne Notizen:\n" . $ausrueckung['notizen'];
    }
    
    // Last Modified
    $lastModified = new DateTime($ausrueckung['aktualisiert_am'] ?: $ausrueckung['erstellt_am']);
    
    // Event hinzufügen
    $calendar->addEvent(
        $ausrueckung['id'],
        $ausrueckung['titel'],
        $start,
        $end,
        $description,
        $ausrueckung['ort'] ?: '',
        $lastModified,
        'CONFIRMED'
    );
}

// ICS ausgeben
$calendar->toString();
exit;
