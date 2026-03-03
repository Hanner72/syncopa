# Instrumente

**Datei:** `instrumente.php`  
**Berechtigung:** `instrumente – lesen`

Die Instrumentenverwaltung erfasst das komplette Instrumenteninventar des Vereins, inklusive Wartungsfristen und Mitgliederzuordnung.

---

## Inventarübersicht

> 📸 **Screenshot:** *Instrumentenliste mit Filterleiste, Spalten: Bezeichnung, Typ, Seriennummer, Zustand, zugeordnetes Mitglied*

| Spalte | Beschreibung |
|---|---|
| Inventarnummer | Eindeutige Nummer |
| Bezeichnung | Name des Instruments |
| Typ | Aus Stammdaten (z.B. Trompete, Tuba) |
| Seriennummer | Hersteller-Seriennummer |
| Zustand | `gut` · `reparaturbedürftig` · `außer Betrieb` |
| Mitglied | Aktuell ausgeliehenes Mitglied |
| Wartung fällig | Datum der nächsten Wartung |

---

## Instrument erfassen

**Datei:** `instrument_bearbeiten.php`  
**Berechtigung:** `instrumente – schreiben`

> 📸 **Screenshot:** *Formular „Neues Instrument" mit Feldern Bezeichnung, Typ, Kaufdatum*

1. Klicke auf **+ Neues Instrument**
2. Wähle den **Instrumententyp** (aus Stammdaten)
3. Ergänze Seriennummer, Kaufdatum und Zustand
4. Optional: Nächstes Wartungsdatum eintragen
5. **Speichern**

### Formularfelder

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Bezeichnung | ✅ | Name / Modell |
| Typ | ✅ | Aus den Stammdaten |
| Inventarnummer | – | Wird vorgeschlagen |
| Seriennummer | – | Hersteller-ID |
| Kaufdatum | – | Datum der Anschaffung |
| Kaufpreis | – | Anschaffungskosten |
| Zustand | – | Aktueller Zustand |
| Nächste Wartung | – | Datum der nächsten Fälligkeit |
| Notizen | – | Interne Anmerkungen |

---

## Wartungen {#wartungen}

> 📸 **Screenshot:** *Dashboard-Widget „Fällige Wartungen" mit roter Markierung bei überfälligen Instrumenten*

Das System erinnert automatisch an fällige Wartungen:

- Im **Dashboard** erscheint eine Warnung bei überfälligen Instrumenten
- In der Instrumentenliste werden fällige Wartungen **rot** markiert
- Nach einer Wartung: Datum in `instrument_bearbeiten.php` aktualisieren und neues Datum setzen

> 💡 **Empfehlung:** Trage für alle Instrumente ein Wartungsdatum ein, damit das System automatisch erinnern kann.

---

## Mitglied zuordnen

Ein Instrument einem Mitglied zuordnen (Ausleihe):

1. Öffne die **Detailseite** des Instruments (`instrument_detail.php`)
2. Klicke auf **„Mitglied zuordnen"**
3. Wähle das Mitglied aus der Liste
4. Optional: Ausleihdatum und Notizen ergänzen
5. **Speichern**

Das Instrument erscheint nun in der Mitgliederdetailseite unter dem Reiter **Instrumente**.
