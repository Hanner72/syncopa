# Dateistruktur

Diese Seite richtet sich an Entwickler und gibt einen Überblick über die Projektstruktur.

---

## Verzeichnisstruktur

```
syncopa/
│
├── classes/                        # PHP-Klassen (OOP, DAL-Pattern)
│   ├── Database.php                # Datenbankverbindung (PDO, Singleton)
│   ├── Session.php                 # Session-Management, Rollen & Berechtigungen
│   ├── Mitglied.php                # Mitglieder-Logik
│   ├── Formation.php                # Formationen (Musikkapelle, 7er-Partie, ...)
│   ├── Ausrueckung.php             # Ausrückungs-Logik, Anwesenheit
│   ├── Instrument.php              # Instrumenten-Inventar, Wartungen
│   ├── Noten.php                   # Noten-Archiv, PDF-Dateien
│   ├── Uniform.php                 # Uniform-Kategorien & Kleidungsstücke
│   ├── KalenderTermin.php          # Kalender-Logik
│   ├── ICalendar.php               # iCal-Export (Ausrückungen & Termine)
│   ├── Nummernkreis.php            # Automatische Nummerierung (Mitglieder, Noten, ...)
│   ├── Fest.php                    # Festverwaltung: Grunddaten eines Fests
│   ├── FestStation.php             # Festverwaltung: Stationen
│   ├── FestMitarbeiter.php         # Festverwaltung: Helfer/Mitarbeiter
│   ├── FestDienstplan.php          # Festverwaltung: Schichtplanung
│   ├── FestEinkauf.php             # Festverwaltung: Bestellungen/Einkäufe
│   ├── FestVertrag.php             # Festverwaltung: Verträge (Bands, Lieferanten, ...)
│   ├── FestTodo.php                # Festverwaltung: Aufgaben
│   ├── FestAbrechnung.php          # Festverwaltung: Abrechnung
│   └── FestKopieren.php            # Festverwaltung: Fest als Vorlage kopieren
│
├── api/                             # Reine JSON-Endpunkte (fetch()/AJAX aus den Seiten)
│   ├── formation_switch.php        # Aktive Formation im Topmenü wechseln
│   ├── formation_delete.php
│   ├── noten_upload.php, noten_dateien.php, noten_download.php, noten_datei_loeschen.php
│   ├── noten_split_stimmen.php     # PDF automatisch nach Stimmen aufteilen
│   ├── noten_split_existing.php    # bereits hochgeladene PDF nachträglich aufteilen
│   ├── notenbuch_sortierung.php    # Drag & Drop Reihenfolge in Notenbüchern
│   ├── probe_session.php           # Live-Probe: Heartbeat, Stück-/Stimmenwahl
│   ├── probe_annotations_save.php, probe_annotations_load.php  # Zeichnungen/Notizen
│   ├── anwesenheit_setzen.php      # An-/Abmeldung zu Ausrückungen
│   ├── kalender.php, kalender_termine.php
│   ├── uniform_mitglied.php        # Uniform-Zuordnung speichern
│   ├── rollen_sortierung.php       # Drag & Drop Rollen-Reihenfolge
│   ├── system_update.php           # Versionsprüfung & Update aus GitHub
│   └── fest_*.php                  # Dienstplan-Drag&Drop, Todo-Status, Einkauf-Vorlagen, Vertrags-Download, Stationstage
│
├── vendor/                          # Extern eingebundene Bibliotheken (kein Composer, manuell)
│   ├── fpdf/                       # FPDF (Basis-PDF-Erzeugung)
│   └── fpdi/                       # FPDI (PDF-Seiten aus bestehenden PDFs übernehmen)
│
├── assets/                          # Bilder, Logo, JS/CSS für Live-Probe (assets/js/)
├── docs/                            # Diese Dokumentation (Docsify)
│   └── screenshots/                # Screenshots für die Docs
├── uploads/                         # Hochgeladene Dateien (Noten-PDFs, Logo, Fest-Verträge, ...)
│
├── config.php                       # ⚠️ Zugangsdaten & API-Keys (nicht in Git!)
├── config.app.php                   # App-Konstanten (Version, Upload-Pfade, Klassen-Autoloader)
├── config.example.php               # Vorlage für neue Installationen
├── includes.php                     # Klassen-Includes + Datenbank-Migrationen (laufen bei jedem Request)
├── database.sql                     # Vollständiges Schema für Neuinstallationen
│
├── index.php                        # Dashboard
├── login.php, login_google.php, login_google_callback.php, logout.php
├── update.php                       # System-Update-Oberfläche (Admin)
│
├── mitglieder.php, mitglied_bearbeiten.php, mitglied_detail.php, mitglied_loeschen.php
├── formationen.php, formation_bearbeiten.php, formation_mitglieder.php
├── ausrueckungen.php, ausrueckung_bearbeiten.php, ausrueckung_detail.php, ausrueckung_loeschen.php
├── finanzen.php, transaktion_bearbeiten.php, transaktion_loeschen.php, beitraege_verwalten.php
├── noten.php, noten_bearbeiten.php, noten_loeschen.php, noten_instrumente.php
├── notenbucher.php, notenbuch_bearbeiten.php
├── probe.php, probe_leiter.php, probe_stimmen.php   # Live-Probe (Musiker- bzw. Kapellmeister-Ansicht)
├── instrumente.php, instrument_bearbeiten.php, instrument_detail.php, instrument_loeschen.php
├── uniformen.php, uniform_kleidungsstuecke.php, uniform_mitglied.php
│   # uniform_ausgeben.php, uniform_zuruecknehmen.php, uniform_kategorien.php, uniform_detail.php,
│   # uniform_bearbeiten.php, uniform_loeschen.php existieren noch im Dateisystem,
│   # sind aber aktuell aus keiner Navigation/Seite mehr verlinkt (Altlasten aus einer früheren Version)
├── kalender.php, kalender_termin_bearbeiten.php, kalender_loeschen.php,
│   kalender_export.php, kalender_abonnement.php, kalender_vorschau.php
├── fest_detail.php, fest_bearbeiten.php, fest_kopieren.php, fest_loeschen.php
│   fest_stationen.php, fest_station_bearbeiten.php, fest_station_loeschen.php
│   fest_mitarbeiter.php, fest_mitarbeiter_bearbeiten.php, fest_mitarbeiter_loeschen.php
│   fest_dienstplan.php, fest_dienstplan_bearbeiten.php, fest_dienstplan_loeschen.php
│   fest_einkauefe.php, fest_einkauf_bearbeiten.php, fest_einkauf_bestellliste.php, fest_einkauf_loeschen.php
│   fest_vertraege.php, fest_vertrag_bearbeiten.php, fest_vertrag_loeschen.php
│   fest_todos.php, fest_todos_alle.php, fest_todo_bearbeiten.php, fest_todo_loeschen.php
│   fest_abrechnung.php
├── benutzer.php, benutzer_bearbeiten.php, benutzer_befoerdern.php, benutzer_loeschen.php
├── rollen.php, rolle_bearbeiten.php, berechtigungen_bearbeiten.php, berechtigungen_matrix.php
├── stammdaten.php
└── einstellungen.php
```

---

## Architektur-Überblick

Syncopa folgt einem einfachen **MVC-ähnlichen Muster**:

- **Model:** Klassen in `/classes/` kapseln Datenbankzugriffe und Logik (Data-Access-Layer, keine ORM)
- **View:** PHP-Dateien im Root-Verzeichnis mischen PHP und HTML
- **Controller:** Logik am Seitenanfang jeder PHP-Datei (Formularverarbeitung, Weiterleitungen)
- **API:** Eigenständige JSON-Endpunkte in `/api/` für alles, was per `fetch()`/AJAX aus den Seiten heraus aufgerufen wird (Live-Probe, Drag & Drop, Uploads, System-Update)

### Request-Ablauf

```
config.php → includes.php → seite.php → includes/header.php → [Inhalt] → includes/footer.php
```

`includes.php` lädt alle Klassen und führt bei **jedem** Request alle Datenbank-Migrationen aus (siehe [Datenbankstruktur](datenbank.md#migrationen)).

### Datenbankzugriff

```php
// Singleton-Pattern
$db = Database::getInstance();

// Daten lesen
$mitglieder = $db->fetchAll("SELECT * FROM mitglieder WHERE status = ?", ['aktiv']);

// Einzelnen Datensatz
$mitglied = $db->fetchOne("SELECT * FROM mitglieder WHERE id = ?", [$id]);

// Schreiben
$db->execute("UPDATE mitglieder SET nachname = ? WHERE id = ?", [$name, $id]);
```

### Berechtigungen prüfen

```php
// Login erforderlich
Session::requireLogin();

// Bestimmte Berechtigung erforderlich (leitet um wenn nicht vorhanden)
Session::requirePermission('mitglieder', 'schreiben');

// Berechtigung prüfen ohne Redirect
if (Session::checkPermission('finanzen', 'lesen')) {
    // Nur anzeigen wenn Recht vorhanden
}
```

### Formations-Filterung

Seiten, die formationsbezogene Daten anzeigen, filtern über `Formation::getFilterCondition()`:

```php
$filter = Formation::getFilterCondition(Session::getFormationId(), 'n');
if ($filter['condition']) {
    $where[]  = $filter['condition'];
    $params   = array_merge($params, $filter['params']);
}
```

Ein `formation_id = NULL` in der Datenbank bedeutet dabei immer "für alle Formationen sichtbar".

Mehr dazu: [Formationen](formationen.md).
