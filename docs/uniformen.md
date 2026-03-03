# Uniformen

**Datei:** `uniformen.php` / `uniform_kleidungsstuecke.php`  
**Berechtigung:** `uniformen – lesen`

Die Uniformverwaltung erfasst alle Kleidungsstücke des Vereins und ermöglicht die Ausgabe und Rücknahme an Mitglieder.

---

## Struktur der Uniformverwaltung

Syncopa unterscheidet zwei Ebenen:

```
Uniforms-Kategorien (z.B. "Ausgehuniform", "Sommerkleidung")
  └── Kleidungsstücke (z.B. "Jacke Gr. 52", "Hose Gr. 48")
        └── Zuordnung zu Mitglied
```

---

## Kategorien verwalten

**Datei:** `uniform_kategorien.php`

> 📸 **Screenshot:** *Kategorien-Liste mit Buttons zum Hinzufügen/Bearbeiten*

Kategorien sind übergeordnete Gruppen (z.B. „Trachtenanzug", „Sommeruniform").

1. Navigiere zu **Uniformen → Kategorien**
2. Klicke auf **+ Neue Kategorie**
3. Name eingeben → **Speichern**

---

## Kleidungsstücke verwalten

**Datei:** `uniform_kleidungsstuecke.php`

> 📸 **Screenshot:** *Liste der Kleidungsstücke mit Inventarnummer, Kategorie, Größe und Status (verfügbar/ausgegeben)*

| Spalte | Beschreibung |
|---|---|
| Inventarnummer | Eindeutige Nummer |
| Bezeichnung | z.B. „Jacke blau" |
| Kategorie | Übergeordnete Gruppe |
| Größe | Kleidergröße |
| Zustand | gut / beschädigt |
| Status | verfügbar / ausgegeben |
| Mitglied | Bei Ausgabe: wem zugeordnet |

---

## Uniform ausgeben

**Datei:** `uniform_ausgeben.php`  
**Berechtigung:** `uniformen – schreiben`

> 📸 **Screenshot:** *Dialog „Uniform ausgeben" mit Mitglieder-Dropdown und Datum*

1. Öffne die Liste der **Kleidungsstücke**
2. Klicke beim gewünschten Kleidungsstück auf **„Ausgeben"**
3. Wähle das **Mitglied** aus der Dropdown-Liste
4. Trage das **Ausgabedatum** ein
5. Klicke **Ausgeben**

Das Kleidungsstück wird als `ausgegeben` markiert und erscheint in der Mitgliederdetailseite.

---

## Uniform zurücknehmen

**Datei:** `uniform_zuruecknehmen.php`  
**Berechtigung:** `uniformen – schreiben`

> 📸 **Screenshot:** *Button „Zurücknehmen" neben ausgegebenen Kleidungsstücken in der Mitgliederdetailseite*

1. Öffne die **Detailseite des Mitglieds**
2. Gehe zum Reiter **Uniform**
3. Klicke beim Kleidungsstück auf **„Zurücknehmen"**
4. Optional: Zustandsnotiz hinterlegen
5. Bestätigen

Das Kleidungsstück ist wieder **verfügbar**.

---

## Uniform einem Mitglied zuordnen

**Datei:** `uniform_mitglied.php`

Alternativ zur Ausgabe aus der Kleidungsstückliste kann auch direkt aus der Mitgliedermaske eine Uniform zugeordnet werden.

> 📸 **Screenshot:** *Reiter „Uniform" in der Mitgliederdetailseite mit Button „Kleidungsstück hinzufügen"*
