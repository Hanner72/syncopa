# Changelog

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
    - Druckfunktion (Navigation wird beim Drucken ausgeblendet

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
