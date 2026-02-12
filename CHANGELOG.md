# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

---

## [2.1.0] - 2026-02-12

### Hinzugefügt

- **Stammdaten-Verwaltung** (`stammdaten.php`)
  - Register erstellen, bearbeiten und löschen
  - Instrumententypen erstellen, bearbeiten und löschen
  - Übersicht der Ausrückungstypen
  - Schutz vor Löschen wenn noch Verknüpfungen bestehen

- **Löschfunktionen**
  - Kalendertermine können gelöscht werden
  - Ausrückungen können gelöscht werden (inkl. Anwesenheiten)
  - Noten können gelöscht werden (inkl. PDF-Dateien)
  - Instrumente können gelöscht werden
  - Finanztransaktionen können gelöscht werden
  - Lösch-Buttons in allen Listen hinzugefügt

- **Instrumentenanzeige in Mitgliederliste**
  - Neue Spalte "Instrumente" zeigt Anzahl
  - Tooltip bei Mouseover zeigt alle Instrumente

- **Datum bei Instrumentenzuordnung**
  - "Spielt seit"-Datum kann beim Hinzufügen gewählt werden
  - Standardwert: Heutiges Datum

### Geändert

- **Berechtigungssystem**
  - Admin-Rolle hat automatisch alle Rechte
  - Bearbeiten/Löschen-Buttons auch für Admin sichtbar
  - Verbesserte Berechtigungsprüfung in allen Lösch-Seiten

- **Navigation**
  - Neuer Menüpunkt "Stammdaten" unter System

### Behoben

- Kalender: Bearbeiten-Button für Termine funktioniert jetzt
- Kalender: Löschen-Button wird korrekt angezeigt
- Dashboard: Charts werden nach DOMContentLoaded initialisiert
- Mitglieder-Statistik: Charts laden korrekt

---

## [2.0.0] - 2025-12-29

### Hinzugefügt

- **Dark/Light Mode**
  - Theme-Toggle in der Topbar
  - Persistierung via localStorage
  - Automatische Anpassung aller Komponenten

- **Responsive Design**
  - Mobile-optimierte Sidebar (Hamburger-Menü)
  - Responsive Tabellen mit Stacking
  - Touch-optimierte Buttons

- **Google OAuth**
  - Login mit Google-Konto
  - Automatische Benutzeranlage
  - Konfigurierbar in config.php

- **Dashboard**
  - Statistik-Karten (Mitglieder, Noten, Instrumente, Inventarwert)
  - Anstehende Termine
  - Geburtstage im aktuellen Monat
  - Registerverteilung als Donut-Chart
  - Fällige Wartungen
  - Schnellaktionen

- **Kalender**
  - FullCalendar Integration
  - Monats-, Wochen-, Tages- und Listenansicht
  - Event-Details im Modal
  - Neuer Termin direkt im Kalender erstellen
  - iCal-Export für externe Kalender

- **Anwesenheitsverwaltung**
  - Zu-/Absagen für Ausrückungen
  - Absagegrund erfassen
  - Statistik pro Termin

- **Programmplanung**
  - Noten zu Ausrückungen zuordnen
  - Reihenfolge festlegen
  - Drag & Drop Sortierung

- **Wartungshistorie**
  - Wartungen für Instrumente erfassen
  - Nächste Wartung planen
  - Erinnerung bei fälligen Wartungen

- **Uniformverwaltung**
  - Kategorien (Jacke, Hose, Hemd, etc.)
  - Kleidungsstücke mit Größen
  - Ausgabe an Mitglieder
  - Rückgabe mit Zustandserfassung

### Geändert

- Komplett überarbeitetes UI mit Bootstrap 5
- Neue Sidebar-Navigation
- DataTables für alle Listen mit Sortierung/Suche
- Chart.js für Statistik-Visualisierung
- Verbesserte Session-Verwaltung

### Behoben

- Diverse Sicherheitsverbesserungen
- SQL-Injection Prevention
- XSS-Schutz durch htmlspecialchars

---

## [1.0.0] - 2025-10-15

### Hinzugefügt

- **Mitgliederverwaltung**
  - Stammdaten (Name, Adresse, Kontakt)
  - Automatische Mitgliedsnummer
  - Instrumentenzuordnung
  - Registerzuordnung
  - Status (aktiv, passiv, ausgetreten, Ehrenmitglied)

- **Instrumenteninventar**
  - Inventarnummern
  - Hersteller, Modell, Baujahr
  - Zustandsbewertung
  - Anschaffungs- und Versicherungswert
  - Verleih an Mitglieder

- **Notenarchiv**
  - Titel, Komponist, Arrangeur
  - Archivnummern-System
  - Schwierigkeitsgrade (1-6)
  - Genre-Kategorisierung
  - Standortverwaltung

- **Einfacher Kalender**
  - Termine erfassen
  - Listenansicht

- **Benutzerverwaltung**
  - Login/Logout
  - Benutzerrollen
  - Berechtigungen pro Modul

- **Finanzen**
  - Einnahmen/Ausgaben
  - Kategorien
  - Mitgliedsbeiträge

---

## Legende

- **Hinzugefügt** - Neue Funktionen
- **Geändert** - Änderungen an bestehenden Funktionen
- **Veraltet** - Funktionen die bald entfernt werden
- **Entfernt** - Entfernte Funktionen
- **Behoben** - Fehlerbehebungen
- **Sicherheit** - Sicherheitsrelevante Änderungen
