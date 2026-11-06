# Festverwaltung

**Datei:** `feste.php`  
**Berechtigung:** `fest – lesen`

Die Festverwaltung ist das Werkzeug für die komplette Organisation eines Vereinsfestes – vom Dorffest über den Kirtag bis zum großen Jubiläumsfest. Sie deckt alles ab: Stationen und Stände, Helfer und Dienstpläne, Einkäufe, Verträge mit Musikgruppen, Aufgabenlisten und am Ende die vollständige Abrechnung.

Es können beliebig viele Feste angelegt werden (z.B. „Kirtag 2026", „50-Jahr-Feier"), jedes Fest hat seine eigenen Stationen, Mitarbeiter, Einkäufe usw.

---

## Alle Feste im Überblick

**Datei:** `feste.php`

Die Startseite der Festverwaltung zeigt alle bisher angelegten Feste in einer Tabelle mit Name, Jahr, Datum, Ort und Status. Über das Auge-Symbol gelangt man zum Dashboard des jeweiligen Festes.

Vier Karten geben eine Schnellübersicht:

| Karte | Bedeutung |
|---|---|
| Feste gesamt | Anzahl aller jemals angelegten Feste |
| Aktiv | Feste mit Status „Aktiv" |
| Geplant | Feste mit Status „Geplant" |
| Aktuelles Jahr | Das laufende Kalenderjahr |

Mit den Filtern **Jahr** und **Status** lässt sich die Liste eingrenzen, z.B. um nur die Feste eines bestimmten Jahres zu sehen.

---

## Fest anlegen und bearbeiten

**Datei:** `fest_bearbeiten.php`  
**Berechtigung:** `fest – schreiben`

1. Klicke auf **+ Neues Fest**
2. Fülle die Grunddaten aus
3. Wähle den **Status**
4. Klicke **Speichern**

### Formularfelder

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Name des Festes | ✅ | z.B. „Sommerfest 2026", „Kirtag" |
| Jahr | ✅ | Kalenderjahr des Festes |
| Datum von | ✅ | Erster Festtag |
| Datum bis | – | Letzter Festtag (bei mehrtägigen Festen, z.B. Freitag bis Sonntag) |
| Ort | – | z.B. „Gemeindeplatz", „Festzelt" |
| Adresse | – | Straße, PLZ, Ort |
| Beschreibung / Notizen | – | Allgemeine Informationen |
| Status | – | `Geplant` · `Aktiv` · `Abgeschlossen` · `Abgesagt` |

> 💡 **Tipp:** Trage bei mehrtägigen Festen (z.B. Freitag bis Sonntag beim Kirtag) sowohl „Datum von" als auch „Datum bis" ein. Syncopa legt dann für jeden einzelnen Tag automatisch eine eigene Spalte im Dienstplan und in der Stationsverwaltung an.

Beim Bearbeiten eines bestehenden Festes steht zusätzlich der Schnellzugriff **„Kopieren von…"** zur Verfügung, mit dem sich Daten aus einem anderen Fest übernehmen lassen (siehe Abschnitt [Fest kopieren](#fest-kopieren)).

---

## Fest-Dashboard

**Datei:** `fest_detail.php`

Öffnet man ein Fest, erscheint oben eine durchgehende **Reiter-Navigation** (Übersicht, Stationen, Mitarbeiter, Dienstplan, Einkäufe, Verträge, Todos, Abrechnung), über die man zwischen allen Teilbereichen dieses Festes wechseln kann.

Das Dashboard selbst zeigt:

- **Stat-Karten** mit der Anzahl an Stationen, Mitarbeitern, offenen Todos und Verträgen (inkl. offener Zahlungen)
- Je eine **Kachel pro Teilbereich** mit einer Kurzinfo, z.B. beim Dienstplan der Besetzungsgrad jeder Station als Fortschrittsbalken
- **Schnellaktionen**: Daten aus einem anderen Fest kopieren, Fest löschen

> ⚠️ **Achtung:** Beim Löschen eines Festes werden **alle** zugehörigen Daten mitgelöscht – Stationen, Mitarbeiter, Dienstpläne, Einkäufe, Verträge und Todos. Das kann nicht rückgängig gemacht werden.

---

## Stationsverwaltung

**Datei:** `fest_stationen.php` / `fest_station_bearbeiten.php`  
**Berechtigung:** `fest – schreiben`

Stationen sind die Stände und Bereiche eines Festes – beim Dorffest zum Beispiel „Getränkeausschank", „Küche", „Kassa" oder „Einlass".

### Station anlegen

1. Öffne im Fest den Reiter **Stationen**
2. Klicke auf **+ Station hinzufügen**
3. Fülle das Formular aus
4. **Speichern**

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Name | ✅ | z.B. „Getränkestand", „Küche", „Einlass", „Kasse" |
| Beschreibung | – | Aufgaben und Besonderheiten der Station |
| Benötigte Helfer | – | Wie viele Personen pro Schicht gebraucht werden (Standardwert) |
| Öffnung von / bis | – | Standard-Öffnungszeiten |
| Sortierung | – | Kleinere Zahl erscheint zuerst |

### Stationen pro Tag aktivieren oder deaktivieren

Geht ein Fest über mehrere Tage (z.B. Freitag bis Sonntag beim Kirtag), muss nicht jede Station an jedem Tag geöffnet haben – die Küche macht vielleicht nur am Samstag und Sonntag auf, der Ausschank an allen drei Tagen.

1. Klicke bei der gewünschten Station auf das **Kalender-Symbol**
2. Für jeden Festtag lässt sich einzeln festlegen: aktiv/inaktiv, abweichende Öffnungszeiten und eine abweichende Helferanzahl
3. Klicke pro Tag auf **„Speichern"**

> 💡 **Tipp:** Wird eine Station an einem Tag auf „inaktiv" gesetzt, taucht sie an diesem Tag weder im Dienstplan noch in den Vorschlägen auf.

In der Stationsliste zeigt eine Spalte **„Tage aktiv"** auf einen Blick, an wie vielen der Festtage eine Station geöffnet hat.

---

## Mitarbeiterverwaltung

**Datei:** `fest_mitarbeiter.php` / `fest_mitarbeiter_bearbeiten.php`  
**Berechtigung:** `fest – schreiben`

Hier werden alle Helfer für das Fest erfasst – sowohl Vereinsmitglieder als auch externe Personen (z.B. Familienangehörige, Freunde, Nachbarn, die beim Kirtag mithelfen).

1. Öffne im Fest den Reiter **Mitarbeiter**
2. Klicke auf **+ Mitarbeiter hinzufügen**
3. Wähle den **Typ**: Vereinsmitglied oder externe Person
4. Fülle die passenden Felder aus
5. **Speichern**

### Formularfelder

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Typ | ✅ | Vereinsmitglied oder externe Person |
| Vereinsmitglied | ✅ bei Typ „Mitglied" | Auswahl aus der Mitgliederliste |
| Vorname / Nachname | ✅ bei Typ „Extern" | Name der externen Person |
| Telefon / E-Mail | – | Nur bei externen Personen erfassbar |
| Funktion / Rolle | – | z.B. „Stationsleiter", „Helfer", „Kassierer", „Springer", „Küche", „Einlass" |
| Notizen | – | z.B. Verfügbarkeit, Besonderheiten |

Die Übersicht zeigt separate Kennzahlen für Vereinsmitglieder und externe Helfer sowie deren Kontaktdaten und Funktion.

> 💡 **Tipp:** Trage die Funktion möglichst konkret ein (z.B. „Bierstand" statt nur „Helfer") – sie erscheint später auch im Dienstplan neben dem Namen und erleichtert die Einteilung.

---

## Dienstplan

**Datei:** `fest_dienstplan.php`  
**Berechtigung:** `fest – schreiben` zum Bearbeiten, `fest – lesen` zum Ansehen

Der Dienstplan zeigt für jeden Festtag eine grafische Zeitleiste: pro Station eine Zeile, darauf die eingeteilten Mitarbeiter mit ihrer jeweiligen Schichtdauer als farbiger Balken.

Voraussetzung: Es müssen bereits **Stationen** und **Mitarbeiter** angelegt sein.

### Schicht per Drag & Drop einplanen

1. Wähle oben den gewünschten **Festtag** (bei mehrtägigen Festen als Reihe von Tagesbuttons)
2. Ziehe eine Person aus der **Mitarbeiter-Bank** unten auf die Zeile der gewünschten Station
3. Ein Zeit-Auswahlfenster öffnet sich, Von/Bis-Zeit anpassen (Standard: 2 Stunden)
4. **Speichern**

Alternativ funktioniert es auch ohne Maus-Ziehen:

1. Klicke einfach auf eine freie Stelle in der Zeitleiste einer Station
2. Wähle im aufgehenden Fenster den Mitarbeiter, prüfe Von/Bis-Zeit
3. **Speichern**

### Bestehende Schichten anpassen

- **Verschieben**: Schicht-Balken mit der Maus an eine andere Uhrzeit ziehen
- **Verlängern/Verkürzen**: am linken oder rechten Rand des Balkens ziehen
- **Löschen**: auf das kleine **×** am Balken klicken und bestätigen

Jede Änderung wird sofort automatisch gespeichert – ein eigener Speichern-Button ist dafür nicht nötig.

### Besetzung im Blick behalten

Für jede Station zeigt der Kopfbereich, wie viele Personen bereits eingeteilt sind im Verhältnis zur benötigten Anzahl (z.B. „2/3"). Ist eine Station voll besetzt, wird das grün markiert, bei fehlenden Helfern rot oder gelb.

> 💡 **Tipp:** Über den Button **„PDF erstellen"** kann der Dienstplan eines Tages ausgedruckt oder als PDF gespeichert werden – praktisch als Aushang im Vereinsheim oder am Fest selbst.

---

## Einkäufe

**Datei:** `fest_einkauefe.php` / `fest_einkauf_bearbeiten.php`  
**Berechtigung:** `fest – schreiben`

Hier werden alle Bestellungen und Einkäufe für das Fest erfasst – vom Bierfass über Einwegbecher bis zu Tischdecken.

### Einkauf erfassen

1. Öffne im Fest den Reiter **Einkäufe**
2. Klicke auf **+ Einkauf hinzufügen**
3. Fülle das Formular aus
4. **Speichern**

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Bezeichnung | ✅ | z.B. „Bier 0,5l", „Einwegbecher", „Tischdecken" |
| Kategorie | – | Einkaufskategorie (Stammdaten) |
| Station | – | Für welche Station der Einkauf gedacht ist |
| Menge / Einheit | – | z.B. 50 Stück, 20 kg, 10 Kisten |
| Preis gesamt | – | Gesamtpreis in Euro |
| Lieferant | – | z.B. Getränkehändler, Großmarkt |
| Status | – | `Geplant` · `Bestellt` · `Erhalten` · `Storniert` |
| Als Vorlage markieren | – | Übernimmt den Eintrag beim „Fest kopieren" ins nächste Jahr |
| Notizen | – | Freitext |

### Ansichten und Filter

Die Einkaufsliste kann nach **Kategorie**, **Station** oder **Lieferant** gruppiert angezeigt werden (Umschalter oberhalb der Liste) und lässt sich nach Bezeichnung, Lieferant, Station, Status und Vorlage-Markierung filtern. Der zuletzt verwendete Filter wird im Browser gemerkt und beim nächsten Aufruf automatisch wieder angewendet.

Vier Stat-Karten zeigen die Gesamtsumme sowie die Summen je Status (Geplant, Bestellt, Erhalten).

### Bestellliste drucken

1. Klicke auf **„Bestellliste PDF"**
2. Wähle optional einen bestimmten Lieferanten sowie die einzuschließenden Status (voreingestellt: alle außer „Storniert")
3. Lege fest, ob nach Stationen gegliedert und ob Notizen angezeigt werden sollen
4. Klicke **„Bestellliste öffnen"** – die druckfertige Liste öffnet sich in einem neuen Tab

> 💡 **Tipp:** Markiere wiederkehrende Einkäufe (z.B. Standardmengen an Bier oder Einwegbechern) als **„Vorlage"**. Beim [Fest kopieren](#fest-kopieren) werden nur diese Vorlagen-Einträge automatisch mit Status „Geplant" ins neue Fest übernommen – so muss die Einkaufsliste nicht jedes Jahr komplett neu erfasst werden.

---

## Vertragsverwaltung

**Datei:** `fest_vertraege.php` / `fest_vertrag_bearbeiten.php`  
**Berechtigung:** `fest – schreiben`

Hier werden Verträge mit Musikgruppen, Bands oder anderen Künstlern verwaltet, die beim Fest auftreten.

1. Öffne im Fest den Reiter **Verträge**
2. Klicke auf **+ Vertrag hinzufügen**
3. Fülle das Formular aus, optional ein PDF-Dokument hochladen
4. **Speichern**

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Band / Gruppe / Künstler | ✅ | Name der Band oder des Künstlers |
| Auftritts-Datum / -Uhrzeit | – | Wann der Auftritt stattfindet |
| Vertrags-Datum | – | Datum des Vertragsabschlusses |
| Honorar | – | Vereinbartes Honorar in Euro |
| Vertragsdokument (PDF) | – | Nur PDF-Dateien, wird sicher im Fest-Ordner abgelegt |
| Zahlungsstatus | – | `Offen` · `Teilweise bezahlt` · `Bezahlt` · `Storniert` |
| Zahlungsdatum | – | Wann bezahlt wurde |
| Notizen | – | z.B. technische Anforderungen, besondere Vereinbarungen |

Ein bereits hochgeladenes Vertragsdokument kann jederzeit über den Download-Button abgerufen werden; ein neuer Upload ersetzt automatisch das alte Dokument.

Die Übersicht zeigt Gesamthonorar, offene und bereits bezahlte Summen sowie die Gesamtanzahl der Verträge.

---

## Todos

**Datei:** `fest_todos.php` / `fest_todo_bearbeiten.php`  
**Berechtigung:** `fest – schreiben`

Die Aufgabenliste hilft dabei, vor und während des Festes nichts zu vergessen – vom Zeltaufbau über die Bestellung des Eiswagens bis zur Behördenmeldung.

1. Öffne im Fest den Reiter **Todos**
2. Klicke auf **+ Todo hinzufügen**
3. Fülle das Formular aus
4. **Speichern**

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Fest | ✅ | Zu welchem Fest die Aufgabe gehört |
| Titel | ✅ | Kurze, klare Beschreibung |
| Beschreibung | – | Details, Links, Hinweise |
| Priorität | – | `Niedrig` · `Normal` · `Hoch` · `Kritisch` |
| Fällig am | – | Datum, bis wann erledigt sein muss |
| Zuständig | – | Verantwortlicher Benutzer |
| Status | – | `Offen` · `In Arbeit` · `Erledigt` · `Abgebrochen` |

### Status per Klick ändern

In der Todo-Liste muss man dafür nicht extra ins Bearbeiten-Formular wechseln: Ein Klick auf das Kreis-Symbol links in der Zeile schaltet den Status durch (Offen → In Arbeit → Erledigt → wieder Offen). Überfällige, noch nicht erledigte Todos werden in der Liste **rot** hervorgehoben.

### Todos über alle Feste

**Datei:** `fest_todos_alle.php`

Über **Meine Todos** in der Navigation sieht man eine festübergreifende Liste aller Aufgaben, die einem selbst zugewiesen sind. Administratoren sehen hier stattdessen die Todos **aller** Benutzer über alle Feste hinweg. Über den Button **„Erledigte anzeigen"** lassen sich auch bereits abgeschlossene Todos einblenden.

> 💡 **Tipp:** Vergib kritische Fristen (z.B. Anmeldung bei der Behörde, GEMA/AKM-Meldung) mit Priorität „Kritisch" und einem festen Fälligkeitsdatum – so gehen sie im Trubel der Vorbereitung nicht unter.

---

## Abrechnung

**Datei:** `fest_abrechnung.php`  
**Berechtigung:** `fest – schreiben` zum Erfassen, `fest – lesen` zum Ansehen

Die Abrechnung fasst alle Einnahmen und Ausgaben eines Festes zusammen und wird automatisch mit Daten aus den anderen Teilbereichen befüllt.

**Automatisch übernommen als Ausgaben:**
- Einkäufe mit Status **„Erhalten"** und hinterlegtem Preis
- Verträge mit hinterlegtem **Honorar**

**Manuell erfassbar:**
- Zusätzliche Einnahmen (z.B. Bardurchsatz, Eintrittsgelder, Sponsoring, Förderungen)
- Zusätzliche Ausgaben (z.B. Personal, Technik, Werbung, GEMA/AKM, Versicherung, Infrastruktur)

So erfasst du einen zusätzlichen Posten:

1. Öffne im Fest den Reiter **Abrechnung**
2. Fülle im passenden Bereich (Einnahmen oder Ausgaben) Bezeichnung, Kategorie, Betrag und optional Station/Notizen aus
3. Klicke auf das **Häkchen-Symbol**

Ein bereits erfasster Posten lässt sich über das Stift-Symbol bearbeiten oder über den Papierkorb löschen.

### Statuskarten

| Karte | Beschreibung |
|---|---|
| Einnahmen | Summe aller Einnahmen |
| Ausgaben | Summe aller Ausgaben (inkl. Einkäufe und Honorare) |
| Ergebnis | Einnahmen minus Ausgaben (Überschuss oder Verlust) |
| Deckungsgrad | Einnahmen in Prozent der Ausgaben |

> 🖨️ Über den Button **„Drucken / PDF"** lässt sich die Abrechnung ausdrucken – Navigation und Bearbeiten-Buttons werden dabei automatisch ausgeblendet, sodass ein sauberes Dokument für Vorstand oder Kassaprüfung entsteht.

---

## Fest kopieren {#fest-kopieren}

**Datei:** `fest_kopieren.php`  
**Berechtigung:** `fest – schreiben`

Findet ein Fest wie der Kirtag jedes Jahr wieder statt, muss nicht alles neu eingetippt werden: Mit „Fest kopieren" lassen sich Stationen, Mitarbeiter und als Vorlage markierte Einkäufe aus einem bestehenden Fest in ein neues übernehmen.

1. Lege zuerst das neue Fest an (siehe [Fest anlegen](#fest-anlegen-und-bearbeiten)), z.B. „Kirtag 2027"
2. Öffne beim neuen Fest **Bearbeiten → „Kopieren von…"**
3. **Schritt 1 – Auswahl:** Wähle das Quell-Fest (z.B. „Kirtag 2026") und hake an, was übernommen werden soll:
   - **Stationen** (ohne Dienstpläne)
   - **Mitarbeiter** (ohne Schichtpläne)
   - **Einkäufe** (nur die als Vorlage markierten, Status wird auf „Geplant" zurückgesetzt)
4. **Schritt 2 – Bestätigung:** Quell- und Ziel-Fest werden noch einmal gegenübergestellt, bestätige mit **„Jetzt kopieren"**
5. **Schritt 3 – Ergebnis:** Syncopa zeigt an, wie viele Datensätze je Bereich übernommen wurden

> 💡 **Hinweis:** Dienstpläne und Verträge werden **nicht** kopiert, da sie sich meist von Jahr zu Jahr stark unterscheiden (andere Termine, andere Bands). Bereits vorhandene Daten im Ziel-Fest werden dabei nicht überschrieben, sondern nur ergänzt – „Fest kopieren" kann also auch mehrmals hintereinander ausgeführt werden, ohne Duplikate im bestehenden Bestand zu löschen (es können aber doppelte Einträge entstehen, wenn man denselben Kopiervorgang zweimal startet).

---

## Berechtigungen im Überblick

| Aktion | Benötigte Berechtigung |
|---|---|
| Feste und alle Teilbereiche ansehen | `fest – lesen` |
| Fest, Stationen, Mitarbeiter, Dienstplan, Einkäufe, Verträge, Todos und Abrechnung anlegen/bearbeiten, Fest kopieren | `fest – schreiben` |
| Fest, Stationen, Mitarbeiter, Einkäufe, Verträge, Todos und Dienstplan-Einträge löschen | `fest – loeschen` |
