# Ausrückungen

**Datei:** `ausrueckungen.php`  
**Berechtigung:** `ausrueckungen – lesen`

Unter Ausrückungen werden alle öffentlichen und internen Auftritte des Vereins geplant und verwaltet.

---

## Übersicht

> 📸 **Screenshot:** *Ausrückungsliste mit Status-Badges (geplant/bestätigt/abgesagt) und Filterleiste*

Die Übersicht zeigt alle Ausrückungen mit:

| Spalte | Beschreibung |
|---|---|
| Datum & Uhrzeit | Termin der Ausrückung |
| Bezeichnung | Name / Titel der Ausrückung |
| Ort | Veranstaltungsort |
| Status | `geplant` · `bestätigt` · `abgesagt` |
| Anmeldungen | Anzahl zugesagt / abgesagt / offen |
| Aktionen | Detail · Bearbeiten · Löschen |

### Filter

- **Zeitraum:** Vergangene / aktuelle / zukünftige Ausrückungen
- **Status:** Nach Planungsstatus filtern

---

## Ausrückung anlegen

**Datei:** `ausrueckung_bearbeiten.php`  
**Berechtigung:** `ausrueckungen – schreiben`

> 📸 **Screenshot:** *Formular „Neue Ausrückung" mit Datum, Uhrzeit, Ort und Beschreibung*

1. Klicke auf **+ Neue Ausrückung**
2. Fülle das Formular aus
3. Klicke auf **Speichern**

### Formularfelder

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Bezeichnung | ✅ | Name der Ausrückung |
| Datum | ✅ | Datum der Veranstaltung |
| Uhrzeit | – | Beginn der Ausrückung |
| Treffpunkt-Uhrzeit | – | Vorankunft für Aufbau etc. |
| Ort | – | Veranstaltungsort |
| Adresse | – | Detaillierte Adresse |
| Status | ✅ | geplant / bestätigt / abgesagt |
| Beschreibung | – | Weitere Informationen |
| Uniform | – | Kleiderordnung für diesen Termin |

---

## Detailansicht einer Ausrückung

**Datei:** `ausrueckung_detail.php`

> 📸 **Screenshot:** *Detailseite mit Teilnehmerliste: grüne Häkchen (zugesagt), rote X (abgesagt), Fragezeichen (offen)*

Die Detailseite zeigt:

- Alle Termindaten auf einen Blick
- **Anmeldeliste** mit Status jedes Mitglieds:
  - ✅ Zugesagt
  - ❌ Abgesagt  
  - ❓ Noch keine Antwort
- **Kommentare** der Mitglieder (z.B. Begründung bei Absage)
- Zusammenfassung: Wie viele haben zugesagt / abgesagt

---

## An- und Abmeldung

Mitglieder können sich selbst zu Ausrückungen an- oder abmelden:

> 📸 **Screenshot:** *Buttons „Ich bin dabei" (grün) und „Ich bin nicht dabei" (rot) in der Ausrückungsdetail*

1. Ausrückung in der Liste oder im Kalender aufrufen
2. Auf **„Ich bin dabei"** oder **„Ich bin nicht dabei"** klicken
3. Optional: Kommentar hinterlassen
4. Status wird sofort gespeichert

Administratoren können den Status für beliebige Mitglieder setzen.
