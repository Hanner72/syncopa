# Noten

**Datei:** `noten.php`  
**Berechtigung:** `noten – lesen`

Das Notenarchiv verwaltet den gesamten Notenbestand des Vereins.

---

## Übersicht

> 📸 **Screenshot:** *Notenliste mit Such- und Filterleiste, Spalten: Titel, Komponist, Kategorie, Stimmen*

Die Tabelle listet alle erfassten Werke mit:

| Spalte | Beschreibung |
|---|---|
| Titel | Name des Musikstücks |
| Komponist / Arrangeur | Urheber |
| Kategorie | z.B. Marsch, Polka, Konzert |
| Stimmen | Anzahl vorhandener Stimmen |
| Aktionen | Anzeigen · Bearbeiten · Löschen |

---

## Noten erfassen

**Datei:** `noten_bearbeiten.php`  
**Berechtigung:** `noten – schreiben`

> 📸 **Screenshot:** *Formular mit Feldern Titel, Komponist, Kategorie und Notizen*

1. Klicke auf **+ Neue Noten**
2. Erfasse Titel, Komponist und Kategorie
3. Optional: Stimmenanzahl und Notizen ergänzen
4. **Speichern**

### Formularfelder

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Titel | ✅ | Name des Stücks |
| Komponist | – | Komponist oder Arrangeur |
| Kategorie | – | Musikgenre / Stücktyp |
| Katalognummer | – | Interne Archivnummer |
| Stimmen | – | Anzahl vorhandener Stimmhefte |
| Notizen | – | Interne Anmerkungen |

---

## Kategorien verwalten

Notenarten / Kategorien werden in den **Stammdaten** verwaltet:  
→ [Stammdaten](stammdaten.md#noten-kategorien)

> 💡 **Tipp:** Vergib konsequente Katalognummern (z.B. `MAR-001`, `POL-047`), so findest du Noten bei der Ausgabe schnell wieder.
