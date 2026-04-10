# Changelog

## v2.2.4



## v2.2.3

### Hinzugefügt
- **Installation**
  - vollständiges Installationsscript hinzugefügt

- **Uniformen**
  - einfache Zuteilung der Uniformen bei Kleidungsstücke verwalten mit anzeige **Fehlend** und der Anzahl der Fehlenden um leicht rauszufinden wem was fehlt.

### Geändert

- **Dashboard - Startseite**
  - Ausrückungen und Geburtstage für Erstanmeldungen nur verschlüsselt zu sehen
  - Hinweis dass erst bei Freischaltung ganz sichtbar ist

- **Uniformen**
  - ein Button **nicht benötigt** wurde integriert um nicht benötigte Kleidungsstücke einzutragen um "fehlende" richtig darstellen zu können
  - Beispiel: Krawatte bei Männern, Tüchlein bei Frauen oder Hose bei Männern, Kleid bei Frauen etc.

## v2.2.2

### Hinzugefügt

- **Dokumentation**
  - vollständige Dokumentation der Applikation hinzugefügt

- **Ausrückungen**
  - Kalender Abos
    - zwei Varianten können jetzt aboniert werden
      - nur die Ausrückungen
      - Ausrückungen UND Termine (z.B. für Vorstandsitzung etc.)

### Geändert

- **Generell**
  - Logos und Favicon geändert
  - Sidebar-Menü öffnen und schließen Button aktualisiert

- **Stammdaten**
  - Nummernkreise für Instrumente, Noten und Mitglieder können vergeben werden

- **Dashboard**
  - Geburtstagsliste richtig sortiert
  - Deutsche Monatsnamen anzeigen

- **Kalender**
  - Bei Klick auf ein Datumsfeld kann ein Termin eingetragen werden

- **Ausrückungen**
  - Sortierung nach Datum stimmt jetzt
  - Buttons um die Anwesenheit einzutragen
    - mit Grund bei einer Absage "Urlaub, Krankheit, etc."
    - Auflistung der Anwesenden Mitglieder nach Register sortiert
    - Ansicht für Mobilgeräte optimiert

- **Instrumente**
  - auf der Haupttabelle die Spalte "Notizen" einblenden für weitere Infos

## v2.2.1

- 🐛 Bugfixes Uniformverwaltung
- 🐛 Kalender-Export Kompatibilität verbessert
- ⚡ Performance-Verbesserungen Mitgliederliste

---

## v2.2.0

- ✨ Google OAuth Login integriert
- ✨ Kalender-Abonnement (iCal-Feed)
- ✨ Öffentliche Kalendervorschau
- ✨ Granulares Berechtigungssystem (Rollen × Module × Aktionen)
- 🎨 UI-Überarbeitung mit Bootstrap 5.3

---

## v2.1.0

- ✨ Uniformverwaltung mit Ausgabe/Rücknahme
- ✨ Instrumentenwartungs-Tracking
- ✨ Dashboard-Widgets (Geburtstage, fällige Wartungen)
- ✨ Mitgliedsbeiträge-Verwaltung

---

## v2.0.0

- ✨ Komplette Neuentwicklung in PHP 8
- ✨ Modul: Mitglieder, Ausrückungen, Finanzen, Noten, Instrumente
- ✨ Session-basierte Authentifizierung
- ✨ PDF-Export mit FPDI
