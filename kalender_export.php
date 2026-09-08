<?php
/**
 * kalender_export.php
 * Generiert ICS-Feed für Ausrückungen (und optional Kalender-Termine) zum Abonnieren
 *
 * Parameter:
 *   ?include=termine  → Ausrückungen + Kalender-Termine exportieren
 *   ?download=1       → Als Datei herunterladen statt als Feed ausgeben
 *   ?formation=ID     → Nur Ausrückungen dieser Formation (+ Einträge ohne Formation)
 */
require_once 'config.php';
require_once 'includes.php';

// Optional: Login erforderlich
// Session::requireLogin();

$db = Database::getInstance();

// Parameter: include=termine → auch Kalender-Termine exportieren
$includeTermine = isset($_GET['include']) && $_GET['include'] === 'termine';

// Formation-Filter (optional)
$formationId = isset($_GET['formation']) ? max(0, (int)$_GET['formation']) : 0;
$formationName = '';
if ($formationId > 0) {
    $formRow = $db->fetchOne("SELECT name FROM formationen WHERE id = ? AND aktiv = 1", [$formationId]);
    if ($formRow) {
        $formationName = $formRow['name'];
    } else {
        $formationId = 0;
    }
}

// iCalendar erstellen
$calendar = new ICalendar();

// Kalender-Name je nach Export-Variante setzen
if ($formationName) {
    $calendar->setCalendarName($formationName . ($includeTermine ? ' – Ausrückungen & Termine' : ' – Ausrückungen'));
} elseif ($includeTermine) {
    $calendar->setCalendarName('Musikverein Ausrueckungen und Termine');
}

// -------------------------------------------------------
// Alle zukuenftigen Ausrueckungen laden
// -------------------------------------------------------
$ausrParams = [];
$whereFormation = '';
if ($formationId > 0) {
    $whereFormation = " AND (formation_id = ? OR formation_id IS NULL)";
    $ausrParams[] = $formationId;
}

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
        WHERE DATE(start_datum) >= CURDATE(){$whereFormation}
        ORDER BY start_datum";

$ausrueckungen = $db->fetchAll($sql, $ausrParams);

foreach ($ausrueckungen as $ausrueckung) {
    $start = new DateTime($ausrueckung['start_datum']);

    if ($ausrueckung['ende_datum']) {
        $end = new DateTime($ausrueckung['ende_datum']);
    } else {
        $end = clone $start;
        $end->modify('+2 hours');
    }

    $description = $ausrueckung['beschreibung'] ?: '';

    // Notizen nur fuer angemeldete Benutzer
    if (Session::isLoggedIn() && !empty($ausrueckung['notizen'])) {
        $description .= "\n\nInterne Notizen:\n" . $ausrueckung['notizen'];
    }

    $lastModified = new DateTime($ausrueckung['aktualisiert_am'] ?: $ausrueckung['erstellt_am']);

    // ID bleibt numerisch: UID wird "ausrueckung-1@musikverein.local" (wie original)
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

// -------------------------------------------------------
// Kalender-Termine laden (nur wenn ?include=termine)
// -------------------------------------------------------
if ($includeTermine) {
    $sqlTermine = "SELECT
                id,
                titel,
                typ,
                start_datum,
                ende_datum,
                ganztaegig,
                ort,
                beschreibung,
                aktualisiert_am,
                erstellt_am
            FROM kalender_termine
            WHERE DATE(start_datum) >= CURDATE()
            ORDER BY start_datum";

    $termine = $db->fetchAll($sqlTermine);

    foreach ($termine as $termin) {
        $start = new DateTime($termin['start_datum']);

        if ($termin['ende_datum']) {
            $end = new DateTime($termin['ende_datum']);
        } else {
            $end = clone $start;
            $end->modify('+2 hours');
        }

        // Beschreibung: Typ + eigentliche Beschreibung
        $description = '';
        if (!empty($termin['typ'])) {
            $description .= 'Typ: ' . $termin['typ'];
        }
        if (!empty($termin['beschreibung'])) {
            $description .= ($description ? "\n\n" : '') . $termin['beschreibung'];
        }

        $lastModified = new DateTime($termin['aktualisiert_am'] ?: $termin['erstellt_am']);

        // Prefix "t" vor der ID damit UIDs eindeutig bleiben
        // Ergebnis z.B.: "ausrueckung-t1@musikverein.local"
        $calendar->addEvent(
            't' . $termin['id'],
            $termin['titel'],
            $start,
            $end,
            $description,
            $termin['ort'] ?: '',
            $lastModified,
            'CONFIRMED'
        );
    }
}

// -------------------------------------------------------
// Ausgabe: Download oder Feed
// -------------------------------------------------------
if (isset($_GET['download']) && $_GET['download'] == '1') {
    $filename = $includeTermine ? 'kalender_komplett.ics' : 'ausrueckungen.ics';
    $calendar->download($filename);
} else {
    $calendar->toString();
}
exit;
