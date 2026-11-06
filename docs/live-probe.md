# Live-Probe / Noten live lesen

**Dateien:** `probe_leiter.php` (Leitung) · `probe.php` (Ansicht für Musiker) · `probe_stimmen.php` (Stimmenverwaltung)
**Berechtigung:** `probe – lesen` (Notenansicht) bzw. `probe – schreiben` (Live-Leitung starten/verwalten)

Die Live-Probe ist eine der praktischsten Funktionen von Syncopa: Der Kapellmeister startet während der Probe ein Notenstück „live", und **jeder Musiker, der die Live-Ansicht auf seinem Handy oder Tablet geöffnet hat, bekommt automatisch das passende Notenblatt für seine eigene Stimme angezeigt** – ganz ohne in einem Stapel Notenblätter suchen zu müssen. Zusätzlich kann jeder Musiker direkt auf dem Bildschirm handschriftliche Anmerkungen machen, die automatisch gespeichert werden.

---

## Grundidee in Kürze

- Der **Kapellmeister** (oder jede Person mit Schreibrecht im Modul „probe") wählt ein Stück aus und **startet eine Live-Session**.
- Alle **Musiker**, die ihre Live-Ansicht geöffnet haben, sehen sofort: „Ein Stück wurde gestartet". Sie wählen (einmalig) ihre Stimme aus einer Liste – danach merkt sich Syncopa das für den Rest der Probe.
- Wechselt der Kapellmeister zum nächsten Stück, aktualisiert sich die Ansicht bei allen Musikern automatisch (innerhalb weniger Sekunden), ohne dass jemand etwas tun muss.

> ⚠️ **Wichtig:** Es kann **immer nur eine einzige Live-Session gleichzeitig im ganzen Verein** laufen. Startet ein Kapellmeister ein Stück für seine Formation, während eine andere Formation gerade ebenfalls live probt, wird deren Live-Session automatisch beendet. Mehrere Formationen können also **nicht gleichzeitig unabhängig voneinander live proben** – wohl aber nacheinander.

---

## Ablauf für den Kapellmeister – „Live-Leitung"

**Datei:** `probe_leiter.php`

### 1. Stück auswählen und Live-Session starten

1. Öffne **Live-Leitung** im Menü
2. Im Bereich **„Stück für Live-Session auswählen"** eines der angezeigten Stücke suchen – optional lässt sich die Liste über das Dropdown oben rechts auf ein bestimmtes [Notenbuch](notenbucher.md) eingrenzen
3. Auf **Start** klicken

> 📌 In der Auswahl erscheinen **nur Stücke, für die bereits mindestens einmal die automatische [„PDF nach Stimmen aufteilen"](noten.md#pdf-nach-stimmen-aufteilen)-Funktion durchgeführt wurde**. Ein ganz neues, noch nicht aufgeteiltes Stück taucht hier also nicht auf – zuerst im Notenarchiv die Stimmen erzeugen (siehe [Noten](noten.md)).

Läuft bereits eine Session, wird sie farblich hervorgehoben angezeigt (grüner Balken „● Läuft" oben) inklusive Startzeit und Anzahl aktuell verbundener Musiker.

### 2. Stimmen-Belegung im Blick behalten

Während eine Session läuft, zeigt die Kachel-Übersicht **„Stimmen-Belegung"** für jede Stimme, wer gerade darauf spielt:

| Anzeige | Bedeutung |
|---|---|
| ✅ grüner Name | Musiker ist online und sieht gerade genau dieses Stück |
| ⏳ gelber Name | Musiker ist der Stimme zugeordnet, seine Ansicht hat sich aber (noch) nicht aktualisiert |
| „frei" (grau) | Für diese Stimme ist aktuell niemand eingetragen |

Darunter zeigt der Kasten **„Ohne Stimme"** alle aktiven Mitglieder der gewählten Formation, die noch keine Stimme ausgewählt haben – mit Statusfarbe:

- **Grün:** online, sieht das Stück, hat aber noch keine Stimme gewählt
- **Gelb:** online, aber gerade auf einer anderen Seite/einem anderen Stück
- **Grau:** aktuell gar nicht mit der Live-Probe verbunden

Diese Übersicht aktualisiert sich automatisch alle paar Sekunden, ein manuelles Neuladen ist nicht nötig.

### 3. Stimmen für ein Stück verwalten

**Datei:** `probe_stimmen.php`

Wurde eine Stimme bei der automatischen PDF-Aufteilung nicht erkannt, fehlt sie ganz, oder soll eine Stimme nachträglich einer anderen PDF-Datei zugeordnet werden, geschieht das hier:

1. Auf der Kachel eines Stücks (oder oben bei einer laufenden Session) auf **Stimmen** klicken
2. **Neue Stimme anlegen:** Namen eingeben (z.B. „1. Klarinette") und die passende PDF-Datei aus der Liste der bereits hochgeladenen Dateien auswählen
3. **Vorhandene Stimme bearbeiten:** über das Stift-Symbol Namen oder zugeordnete Datei ändern
4. **Stimme löschen:** über das Papierkorb-Symbol – Achtung, damit gehen auch alle handschriftlichen Notizen zu dieser Stimme verloren

> 💡 **Tipp:** Diese Seite ist die Anlaufstelle für alle Fälle, in denen die automatische Erkennung beim „PDF nach Stimmen aufteilen" ein Instrument nicht zuordnen konnte (siehe [Noten](noten.md#wenn-die-erkennung-nicht-klappt)) – hier lässt sich die betroffene PDF-Seite manuell der richtigen Stimme zuweisen.

### 4. Live-Session beenden

Auf **Beenden** klicken (mit Bestätigungsabfrage). Die Musiker-Ansichten springen danach automatisch zurück auf den Wartebildschirm „Warte auf Kapellmeister…".

---

## Ablauf für den Musiker – Notenansicht

**Datei:** `probe.php` (auf dem eigenen Handy/Tablet/Laptop öffnen)

### 1. Warten, bis der Kapellmeister ein Stück startet

Solange keine Live-Session läuft, zeigt die Seite nur „Warte auf Kapellmeister…". Sobald eine Session gestartet wird, aktualisiert sich die Seite von selbst.

### 2. Eigene Stimme auswählen

1. In der linken Leiste unter **„Stimme wählen"** die eigene Stimme aus der Liste auswählen (z.B. „1. Klarinette")
2. Auf **Auswählen** klicken
3. Optional das Häkchen **★** setzen, um die Stimme als **Favorit** zu speichern

Gespeicherte Favoriten erscheinen darunter als eigene Schaltflächen und lassen sich beim nächsten Mal per einfachem Klick auswählen, ohne erneut aus der vollständigen Liste suchen zu müssen. Ist bereits ein passender Favorit für das aktuell gestartete Stück vorhanden, wird er sogar automatisch vorausgewählt.

### 3. Notenblatt lesen

Sobald eine Stimme gewählt ist, wird das zugehörige PDF angezeigt.

- **Blättern:** nach links/rechts wischen (Touch) oder auf die Tap-Zonen am linken/rechten Bildschirmrand tippen
- Beim Wechsel zu einem neuen Stück durch den Kapellmeister lädt sich die Ansicht automatisch neu

### 4. Handschriftliche Notizen machen

Über das Stift-Symbol (oben rechts bzw. in der Seitenleiste unter „Notizen") lässt sich der Zeichenmodus einschalten. Dann erscheint am unteren Bildschirmrand eine Werkzeugleiste:

| Werkzeug | Funktion |
|---|---|
| ✏️ Stift | Freihand zeichnen/schreiben, Farbe und Strichstärke einstellbar |
| 🖌️ Textmarker | Transparentes Hervorheben von Notenpassagen |
| 🩹 Radierer | Einzelne Notizen wieder entfernen |
| Stempel | Feste Symbol-Stempel antippen (siehe unten) und direkt auf die Note setzen |
| ↩️ Rückgängig | Letzten Strich rückgängig machen |
| 🗑️ Seite löschen | Alle Notizen auf der aktuellen Seite entfernen |
| 💾 Speichern | Speichert sofort (geschieht zusätzlich automatisch im Hintergrund) |

Über die **Stempel-Palette** lassen sich gängige Musiksymbole ohne Freihandzeichnen setzen, z.B. Dynamikzeichen (**p, f, mf, sf** …), Artikulation (**Akzent, Marcato, Staccato, Bindebogen/Tenuto, Triller**), Spieltechnik (Auf-/Abstrich, gedämpft, Flageolett) sowie Vorzeichen (♯ ♭ ♮).

Alle Notizen werden **automatisch pro Musiker, pro Stimme und pro Seite gespeichert** – niemand sonst sieht oder verändert diese persönlichen Anmerkungen.

> 💡 **Tipp:** Der Zeichenmodus lässt sich jederzeit über das Stift-Symbol wieder ausschalten, ohne dass Notizen verloren gehen.

### 5. Halbseiten-Modus

Für zweiseitige oder schmal gedruckte Notenausschnitte ist der **Halbseiten-Modus** standardmäßig aktiviert: Eine PDF-Seite wird automatisch in zwei Hälften geteilt und nacheinander bildschirmfüllend angezeigt, damit auch auf kleinen Handybildschirmen nichts zu klein zum Lesen ist. Über die Schaltfläche **„½ Seite"** in der Seitenleiste lässt sich dieser Modus jederzeit ein- oder ausschalten – die Einstellung wird für die nächsten Male gemerkt.

### 6. Vollbildmodus und Seitenleiste

- Über das Symbol **„Ausblenden"** (Seitenleisten-Symbol oben in der Leiste) lässt sich die Seitenleiste komplett ausblenden – der Bildschirm wechselt dabei automatisch in den Vollbildmodus, damit möglichst viel Platz für die Noten bleibt
- Über den eingeblendeten Menü-Knopf (☰) oben links lässt sich die Seitenleiste jederzeit wieder einblenden

---

## Formation und mehrere Musikkapellen

Die Live-Probe bezieht sich immer auf die aktuell **im Topmenü gewählte Formation**. Ist eine Formation aktiv, sieht der Kapellmeister in der „Stimmen-Belegung" den vollständigen Abgleich mit allen aktiven Mitgliedern dieser Formation (auch wer offline ist), und Musiker sehen nur Live-Sessions, die entweder für ihre eigene Formation gestartet wurden oder für „alle Formationen" gelten.

Wie oben erwähnt, kann aber **technisch nur eine einzige Live-Session zur gleichen Zeit im gesamten Verein aktiv sein** – ein zeitgleiches, unabhängiges Live-Proben zweier Formationen ist aktuell nicht möglich.

---

## Für Administratoren

> 💡 **Tipp:** Die Live-Probe funktioniert nur, solange alle Geräte eine funktionierende **WLAN- oder Internetverbindung** haben – die Notenblätter, der Stückwechsel und die gespeicherten Notizen werden laufend mit dem Server abgeglichen. Bei schlechtem Empfang im Probelokal empfiehlt sich ein lokaler WLAN-Router.

---

## Verwandte Themen

- → [Noten](noten.md) – PDF-Dateien hochladen und automatisch nach Stimmen aufteilen
- → [Notenbücher](notenbucher.md) – Stückauswahl für die Live-Leitung über Mappen eingrenzen
