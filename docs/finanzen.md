# Finanzen

**Datei:** `finanzen.php`  
**Berechtigung:** `finanzen – lesen`

Die Finanzverwaltung ermöglicht die Erfassung und Auswertung aller Einnahmen und Ausgaben des Vereins.

---

## Übersicht

> 📸 **Screenshot:** *Finanztabelle mit Spalten Datum, Kategorie, Beschreibung, Betrag (grün/rot eingefärbt), Saldo*

Die Übersicht zeigt:

- Alle Transaktionen chronologisch
- **Einnahmen** in Grün, **Ausgaben** in Rot
- Aktuellen **Kontostand / Saldo**
- Summen nach Zeitraum filterbar

---

## Transaktion erfassen

**Datei:** `transaktion_bearbeiten.php`  
**Berechtigung:** `finanzen – schreiben`

> 📸 **Screenshot:** *Formular „Neue Transaktion" mit Typ-Auswahl Einnahme/Ausgabe*

1. Klicke auf **+ Neue Transaktion**
2. Wähle **Einnahme** oder **Ausgabe**
3. Fülle Datum, Betrag, Kategorie und Beschreibung aus
4. Klicke **Speichern**

### Formularfelder

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Typ | ✅ | Einnahme oder Ausgabe |
| Datum | ✅ | Buchungsdatum |
| Betrag | ✅ | Betrag in Euro (ohne Währungssymbol) |
| Kategorie | – | Buchungskategorie |
| Beschreibung | – | Verwendungszweck |
| Beleg-Nr. | – | Referenz zu einem Beleg |

---

## Mitgliedsbeiträge verwalten

**Datei:** `beitraege_verwalten.php`  
**Berechtigung:** `finanzen – schreiben`

> 📸 **Screenshot:** *Beitragsverwaltung mit Liste aller Mitglieder und Bezahlt-Status je Jahr*

Über die Beitragsverwaltung können Mitgliedsbeiträge für das aktuelle (oder vergangene) Jahr erfasst werden:

1. Navigiere zu **Finanzen → Mitgliedsbeiträge**
2. Wähle das **Jahr**
3. Setze für jedes Mitglied den Status: `bezahlt` / `offen` / `befreit`
4. Änderungen werden automatisch gespeichert

> 💡 **Tipp:** Mitglieder mit Status `Ehrenmitglied` können pauschal als `befreit` markiert werden.
