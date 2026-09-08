# Changelog

## [2.4.1]

### Bugs

- Noten Seite lässt sich nicht mehr öffnen bei Update von 2.3.9 auf 2.4.0
 - behoben

## [2.4.0]

### Hinzugefügt

- Formationsverwaltung
  - Formationen können angelegt werden (Musikkapelle, 7er Partie, etc.)
  - Jedes Mitglied kann einer oder mehrerer Formationen zugewiesen werden

- Notenaufteilung (Splitten)
  - Instrumenten Pattern in den Einstellungen eingefügt
  - Instrumente für die Notaufteilung bzw. Erkennung können selber ergänzt und erweitert werden
  - Hilfe zur Erstellung der Pattern eingefügt

- Live Noten lesen
 - der Kapellmeister wählt noten und die Musikanten bekommen automatisch die richtigen Noten angezeigt
 - Halbseitige Blätterfunktion für mehr Übersicht eingebaut
 - Noten bearbeiten bzw. Notizen machen
 - Stempel für Musiksymbole eingefügt
 - Notenbücher anlegen
  - getrennt nach Formation

- Rollenmatrix
 - einfaches bearbeiten der Rollenberechtigungen

### Geändert

- Noten PDF Aufteilung
  - PDF 1.5+ kann von FPDI nicht gelesen werden, Info zum speichern als PDF 1.4 oder qpdf am Server installieren

## [2.3.9]

- größere Updatescript Änderungen

## [2.3.8]

- erneutes Updatescript update
  - leider waren noch immer Fehler drin :-(

## [2.3.7]

- erneutes Updatescript update ;-)

## [2.3.6]

- kleine Updates

## [2.3.5]

### Geändert

- Installationsscript Update
  - auch ohne CURL lauffähig

## [2.3.4]

### Geändert

- kleine grafische Updates
- Installationsscript Update

## [2.3.3]

### Hinzugefügt

- **Update** - Anzeige ob ein Update vorhanden ist im Topmenü

### Geändert

- **Dokumentation** - an die Festverwaltung angepasst

## [2.3.2]

- update Test

## [2.3.1]

### Geändert

- install.php

## [2.3.0]

### Hinzugefügt

- **Festverwaltung** – vollständiges Modul zur Verwaltung von Vereinsfesten
  - **Übersicht / Dashboard** mit Statuskarten (Stationen, Mitarbeiter, Todos, Budget)
  - **Stationsverwaltung** – Stationen anlegen, pro Tag aktivieren/deaktivieren
  - **Mitarbeiterverwaltung** – Helfer erfassen und Stationen zuweisen
  - **Dienstplan** – Schichten per Drag & Drop planen, nach Tag und Station filtern
  - **Einkäufe** – Bestellungen erfassen, nach Station / Kategorie / Lieferant gruppiert
    - Sofortfilter mit localStorage-Persistenz
    - Druckbare Bestellliste als PDF (Lieferant, Stationen, Notizen, Status ein-/ausblendbar)
    - Mengen auf ganze Zahlen beschränkt
  - **Vertragsverwaltung** – Verträge für Bands, Händler etc. mit Honorar und Zahlungsstatus
  - **Todos** – Aufgaben mit Priorität, Fälligkeit und Zuständigkeit
    - Todo-Badge im Topmenü (rot = überfällig, gelb = offen)
    - Globale Todo-Übersicht über alle Feste hinweg
  - **Abrechnung** – vollständige Festabrechnung
    - Automatische Übernahme bezahlter Einkäufe und Honorare
    - Manuelle Einnahmen- und Ausgabenposten
    - Statuskarten: Einnahmen, Ausgaben, Ergebnis, Deckungsgrad
    - Druckfunktion (Navigation wird beim Drucken ausgeblendet)

- **Berechtigungssystem – Mehrfachrollen**
  - Ein Benutzer kann jetzt mehrere Rollen gleichzeitig haben
  - Neue Pivot-Tabelle `benutzer_rollen` (automatische Migration bestehender Daten)
  - `checkPermission()` prüft alle Rollen kombiniert
  - Neue Methoden: `Session::isAdmin()`, `Session::getRollenIds()`
  - Neues Modul **Festverwaltung** in der Rechteverwaltung

- **Rollenverwaltung**
  - Reihenfolge per Drag & Drop änderbar (SortableJS, automatisches Speichern)

- **System-Update**
  - Automatisches Update über GitHub direkt im Admin-Bereich
  - Versionsprüfung gegen aktuellen Changelog auf GitHub
  - Update lädt ZIP herunter, entpackt und kopiert Dateien
  - `config.php` wird dabei automatisch geschützt (nicht überschrieben)
  - Update-Protokoll wird live angezeigt

- **Konfiguration aufgeteilt**
  - `config.php` enthält nur noch server-spezifische Einstellungen (DB, URLs, API-Keys)
  - `config.app.php` enthält App-Konstanten, Helfer und Autoloader (wird per Update aktualisiert)
  - `config.example.php` als Vorlage für Neuinstallationen
  - `config.php` wird nicht mehr in git versioniert

### Geändert

- **Benutzerverwaltung**
  - Rollenzuweisung per Multi-Checkbox (statt Single-Dropdown)
  - Alle zugewiesenen Rollen werden als Badges angezeigt

---

## [2.2.3]

### Hinzugefügt

- **Installation**
  - Vollständiges Installationsscript hinzugefügt

- **Uniformen**
  - Einfache Zuteilung bei Kleidungsstücke verwalten mit Anzeige **Fehlend** und Anzahl der Fehlenden

### Geändert

- **Dashboard**
  - Ausrückungen und Geburtstage für Erstanmeldungen nur verschlüsselt sichtbar
  - Hinweis dass erst bei Freischaltung ganz sichtbar

- **Uniformen**
  - Button **nicht benötigt** integriert um nicht benötigte Kleidungsstücke einzutragen

---

## [2.2.2]

### Hinzugefügt

- **Dokumentation**
  - Vollständige Dokumentation der Applikation hinzugefügt

- **Ausrückungen**
  - Kalender-Abonnement (iCal-Feed)
    - Variante 1: nur Ausrückungen
    - Variante 2: Ausrückungen + Termine

### Geändert

- **Generell**
  - Logos und Favicon aktualisiert
  - Sidebar-Menü öffnen/schließen Button aktualisiert

- **Stammdaten**
  - Nummernkreise für Instrumente, Noten und Mitglieder

- **Dashboard**
  - Geburtstagsliste richtig sortiert
  - Deutsche Monatsnamen

- **Kalender**
  - Klick auf Datumsfeld öffnet neuen Termin

- **Ausrückungen**
  - Sortierung nach Datum korrigiert
  - Anwesenheits-Buttons mit Abwesenheitsgrund (Urlaub, Krankheit, etc.)
  - Auflistung der Anwesenden nach Register sortiert
  - Mobilansicht optimiert

- **Instrumente**
  - Spalte "Notizen" in der Haupttabelle einblendbar

---

## [2.2.1]

- Bugfixes Uniformverwaltung
- Kalender-Export Kompatibilität verbessert
- Performance-Verbesserungen Mitgliederliste

---

## [2.2.0]

- Google OAuth Login integriert
- Kalender-Abonnement (iCal-Feed)
- Öffentliche Kalendervorschau
- Granulares Berechtigungssystem (Rollen × Module × Aktionen)
- UI-Überarbeitung mit Bootstrap 5.3

---

## [2.1.0]

- Uniformverwaltung mit Ausgabe/Rücknahme
- Instrumentenwartungs-Tracking
- Dashboard-Widgets (Geburtstage, fällige Wartungen)
- Mitgliedsbeiträge-Verwaltung

---

## [2.0.0]

- Komplette Neuentwicklung in PHP 8
- Module: Mitglieder, Ausrückungen, Finanzen, Noten, Instrumente
- Session-basierte Authentifizierung
- PDF-Export mit FPDI
