# Dateistruktur

Diese Seite richtet sich an Entwickler und gibt einen Überblick über die Projektstruktur.

---

## Verzeichnisstruktur

```
syncopa/
│
├── classes/                    # PHP-Klassen (OOP)
│   ├── Database.php            # Datenbankverbindung (PDO, Singleton)
│   ├── Session.php             # Session-Management & Berechtigungen
│   ├── Mitglied.php            # Mitglieder-Logik
│   ├── Ausrueckung.php         # Ausrückungs-Logik
│   ├── Instrument.php          # Instrumenten-Logik
│   ├── Noten.php               # Noten-Logik
│   ├── Uniform.php             # Uniform-Logik
│   ├── KalenderTermin.php      # Kalender-Logik
│   ├── ICalendar.php           # iCal-Export
│   └── Nummernkreis.php        # Automatische Nummerierung
│
├── vendor/                     # Externe Bibliotheken
│   └── fpdi/                   # FPDI/FPDF (PDF-Export)
│
├── docs/                       # Diese Dokumentation
│   ├── index.html              # Docsify-Einstieg
│   └── screenshots/            # Screenshots für die Docs
│
├── uploads/                    # Hochgeladene Dateien (Logo etc.)
│
├── config.php                  # ⚠️ Konfiguration (nicht in Git!)
├── includes.php                # Gemeinsame Includes (Header, Footer, Nav)
│
├── index.php                   # Dashboard
├── login.php                   # Login-Seite
├── login_google.php            # Google OAuth Initiierung
├── login_google_callback.php   # Google OAuth Callback
├── logout.php                  # Logout
│
├── mitglieder.php              # Mitgliederliste
├── mitglied_bearbeiten.php     # Mitglied neu/bearbeiten
├── mitglied_detail.php         # Mitglied-Detailseite
├── mitglied_loeschen.php       # Mitglied löschen
│
├── ausrueckungen.php           # Ausrückungs-Übersicht
├── ausrueckung_bearbeiten.php  # Ausrückung neu/bearbeiten
├── ausrueckung_detail.php      # Ausrückungs-Detailseite
├── ausrueckung_loeschen.php    # Ausrückung löschen
│
├── finanzen.php                # Finanzübersicht
├── transaktion_bearbeiten.php  # Transaktion neu/bearbeiten
├── transaktion_loeschen.php    # Transaktion löschen
├── beitraege_verwalten.php     # Mitgliedsbeiträge
│
├── noten.php                   # Notenübersicht
├── noten_bearbeiten.php        # Noten neu/bearbeiten
├── noten_loeschen.php          # Noten löschen
│
├── instrumente.php             # Instrumentenübersicht
├── instrument_bearbeiten.php   # Instrument neu/bearbeiten
├── instrument_detail.php       # Instrument-Detailseite
├── instrument_loeschen.php     # Instrument löschen
│
├── uniformen.php               # Uniformen-Übersicht
├── uniform_bearbeiten.php      # Uniform neu/bearbeiten
├── uniform_detail.php          # Uniform-Detailseite
├── uniform_kleidungsstuecke.php # Kleidungsstücke
├── uniform_kategorien.php      # Uniform-Kategorien
├── uniform_ausgeben.php        # Ausgabe an Mitglied
├── uniform_zuruecknehmen.php   # Rücknahme
├── uniform_mitglied.php        # Zuordnung Mitglied
├── uniform_loeschen.php        # Löschen
│
├── kalender.php                # Kalenderansicht
├── kalender_termin_bearbeiten.php  # Termin neu/bearbeiten
├── kalender_loeschen.php       # Termin löschen
├── kalender_export.php         # iCal-Export
├── kalender_abonnement.php     # Abo-Seite
├── kalender_vorschau.php       # Öffentliche Vorschau
│
├── benutzer.php                # Benutzerverwaltung
├── benutzer_bearbeiten.php     # Benutzer neu/bearbeiten
├── benutzer_befoerdern.php     # Rolle zuweisen
├── benutzer_loeschen.php       # Benutzer löschen
│
├── rollen.php                  # Rollenverwaltung
├── rolle_bearbeiten.php        # Rolle neu/bearbeiten
├── berechtigungen_bearbeiten.php # Berechtigungen
│
├── stammdaten.php              # Stammdaten-Verwaltung
└── einstellungen.php           # Systemeinstellungen
```

---

## Architektur-Überblick

Syncopa folgt einem einfachen **MVC-ähnlichen Muster**:

- **Model:** Klassen in `/classes/` kapseln Datenbankzugriffe und Logik
- **View:** PHP-Dateien im Root-Verzeichnis mischen PHP und HTML
- **Controller:** Logik am Seitenanfang jeder PHP-Datei (Formularverarbeitung, Weiterleitungen)

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

// Bestimmte Berechtigung erforderlich
Session::requirePermission('mitglieder', 'schreiben');

// Berechtigung prüfen ohne Redirect
if (Session::checkPermission('finanzen', 'lesen')) {
    // Nur anzeigen wenn Recht vorhanden
}
```
