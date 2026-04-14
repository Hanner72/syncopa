# Festverwaltung

**Berechtigung:** Rolle mit `fest`-Modul-Zugriff (oder Admin)

Die Festverwaltung ermöglicht die vollständige Organisation von Vereinsfesten – von der Stationsplanung über den Dienstplan bis zur Abrechnung.

---

## Übersicht / Dashboard

**Datei:** `fest_dashboard.php`

Das Dashboard eines Festes zeigt auf einen Blick:

| Karte | Inhalt |
|---|---|
| Stationen | Anzahl aktiver Stationen |
| Mitarbeiter | Anzahl eingetragener Helfer |
| Todos | Offene Aufgaben (Warnung bei überfälligen) |
| Budget | Einnahmen, Ausgaben, Ergebnis |

---

## Feste verwalten

**Datei:** `feste.php`

Übersicht aller angelegten Feste. Von hier aus gelangt man in die Detailansicht eines Festes.

---

## Stationsverwaltung

**Datei:** `fest_stationen.php`

Stationen (z.B. Ausschank, Küche, Kassa) können angelegt und pro Tag aktiviert bzw. deaktiviert werden.

---

## Mitarbeiterverwaltung

**Datei:** `fest_mitarbeiter.php`

Helfer erfassen und direkt einer oder mehreren Stationen zuweisen.

---

## Dienstplan

**Datei:** `fest_dienstplan.php`

- Schichten per **Drag & Drop** planen
- Filterung nach Tag und Station
- Übersicht welche Mitarbeiter wann eingeteilt sind

---

## Einkäufe

**Datei:** `fest_einkauefe.php`

Bestellungen und Einkäufe für das Fest erfassen:

- Gruppierung nach Station, Kategorie oder Lieferant
- Sofortfilter mit Speicherung (localStorage)
- **Druckbare Bestellliste** als PDF
  - Lieferant, Stationen, Notizen und Status ein-/ausblendbar
- Mengen sind auf ganze Zahlen beschränkt

---

## Vertragsverwaltung

**Datei:** `fest_vertraege.php`

Verträge mit Bands, Händlern oder anderen Dienstleistern verwalten:

- Honorar und Zahlungsstatus erfassen
- Dateianhänge hochladen (gespeichert in `uploads/fest_vertraege/`)

---

## Todos

**Datei:** `fest_todos.php`

Aufgabenverwaltung für das Fest:

| Feld | Beschreibung |
|---|---|
| Titel | Kurzbeschreibung der Aufgabe |
| Priorität | Niedrig / Normal / Hoch |
| Fälligkeit | Datum bis wann erledigt |
| Zuständigkeit | Verantwortliche Person |

**Todo-Badge im Topmenü:**
- 🔴 Rot = überfällige Todos vorhanden
- 🟡 Gelb = offene Todos vorhanden

Über **Administration → Todos (Übersicht)** können alle offenen Todos über alle Feste hinweg angezeigt werden.

---

## Abrechnung

**Datei:** `fest_abrechnung.php`

Vollständige Festabrechnung auf einen Blick:

**Automatisch übernommen:**
- Bezahlte Einkäufe aus der Einkaufsverwaltung
- Bezahlte Honorare aus der Vertragsverwaltung

**Manuell eintragbar:**
- Zusätzliche Einnahmen (z.B. Kartenverkauf, Spenden)
- Zusätzliche Ausgaben

**Statuskarten:**

| Karte | Beschreibung |
|---|---|
| Einnahmen | Summe aller Einnahmen |
| Ausgaben | Summe aller Ausgaben |
| Ergebnis | Einnahmen minus Ausgaben |
| Deckungsgrad | Einnahmen in % der Ausgaben |

> 🖨️ Die Abrechnung kann über den Drucken-Button als Ausdruck ausgegeben werden (Navigation wird dabei ausgeblendet).
