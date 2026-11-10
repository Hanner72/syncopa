# Notenpult – gemeinsam spielen und selbst üben

**Dateien:** `probe_leiter.php` (Notenpult leiten) · `probe.php` (Notenpult für Musiker) · `probe_stimmen.php` (Stimmenverwaltung)
**Berechtigung:** `probe – lesen` (Notenpult) bzw. `probe – schreiben` (Notenpult leiten)

Das Notenpult ist das digitale Notenblatt auf Handy, Tablet oder Laptop. Es kennt zwei Arten:

- **Mit der Kapelle** – bei Probe und Konzert: Der Kapellmeister gibt ein Stück vor, und **jeder Musiker bekommt automatisch das passende Notenblatt für seine eigene Stimme angezeigt** – ganz ohne in einem Stapel Notenblätter suchen zu müssen.
- **Selbst üben** – zuhause: Jeder Musiker sucht sich selbst ein Stück oder ein Notenbuch aus und übt seine Stimme.

In beiden Arten kann jeder Musiker direkt auf dem Bildschirm handschriftliche Anmerkungen machen. Sie werden automatisch gespeichert und erscheinen beim Üben und bei der Probe gleichermaßen.

---

## Grundidee in Kürze

- Der **Kapellmeister** (oder jede Person mit Schreibrecht im Modul „probe") wählt ein Stück aus und **sendet es an alle Notenpulte**.
- Alle **Musiker**, die ihr Notenpult geöffnet haben, sehen sofort: „Ein Stück wurde gestartet". Sie wählen (einmalig) ihre Stimme aus einer Liste – danach merkt sich Syncopa das für den Rest der Probe.
- Wechselt der Kapellmeister zum nächsten Stück, aktualisiert sich die Ansicht bei allen Musikern automatisch (innerhalb weniger Sekunden), ohne dass jemand etwas tun muss.

> ⚠️ **Wichtig:** Es kann **immer nur ein Kapellmeister gleichzeitig im ganzen Verein** Stücke vorgeben. Startet ein Kapellmeister ein Stück für seine Formation, während eine andere Formation gerade ebenfalls gemeinsam spielt, wird dort das gemeinsame Spielen automatisch beendet. Mehrere Formationen können also **nicht gleichzeitig unabhängig voneinander** gemeinsam spielen – wohl aber nacheinander. Selbst üben können dagegen beliebig viele Musiker gleichzeitig.

---

## Ablauf für den Kapellmeister – „Notenpult leiten"

**Datei:** `probe_leiter.php`

### 1. Stück auswählen und an alle Notenpulte senden

1. Öffne **Notenpult leiten** im Menü
2. Im Bereich **„Stück für alle Notenpulte auswählen"** eines der angezeigten Stücke suchen – optional lässt sich die Liste über das Dropdown oben rechts auf ein bestimmtes [Notenbuch](notenbucher.md) eingrenzen
3. Auf **Start** klicken

> 📌 In der Auswahl erscheinen **nur Stücke, für die bereits mindestens einmal die automatische [„PDF nach Stimmen aufteilen"](noten.md#pdf-nach-stimmen-aufteilen)-Funktion durchgeführt wurde**. Ein ganz neues, noch nicht aufgeteiltes Stück taucht hier also nicht auf – zuerst im Notenarchiv die Stimmen erzeugen (siehe [Noten](noten.md)).

Läuft bereits ein Stück, wird sie farblich hervorgehoben angezeigt (grüner Balken „● Läuft" oben) inklusive Startzeit und Anzahl aktuell verbundener Musiker.

### 2. Stimmen-Belegung im Blick behalten

Während ein Stück läuft, zeigt die Kachel-Übersicht **„Stimmen-Belegung"** für jede Stimme, wer gerade darauf spielt:

| Anzeige | Bedeutung |
|---|---|
| ✅ grüner Name | Musiker ist online und sieht gerade genau dieses Stück |
| ⏳ gelber Name | Musiker ist der Stimme zugeordnet, seine Ansicht hat sich aber (noch) nicht aktualisiert |
| „frei" (grau) | Für diese Stimme ist aktuell niemand eingetragen |

Darunter zeigt der Kasten **„Ohne Stimme"** alle aktiven Mitglieder der gewählten Formation, die noch keine Stimme ausgewählt haben – mit Statusfarbe:

- **Grün:** online, sieht das Stück, hat aber noch keine Stimme gewählt
- **Gelb:** online, aber gerade auf einer anderen Seite/einem anderen Stück
- **Grau:** aktuell gar nicht mit dem Notenpult verbunden

Diese Übersicht aktualisiert sich automatisch alle paar Sekunden, ein manuelles Neuladen ist nicht nötig.

### 3. Stimmen für ein Stück verwalten

**Datei:** `probe_stimmen.php`

Wurde eine Stimme bei der automatischen PDF-Aufteilung nicht erkannt, fehlt sie ganz, oder soll eine Stimme nachträglich einer anderen PDF-Datei zugeordnet werden, geschieht das hier:

1. Auf der Kachel eines Stücks (oder oben beim laufenden Stück) auf **Stimmen** klicken
2. **Neue Stimme anlegen:** Namen eingeben (z.B. „1. Klarinette") und die passende PDF-Datei aus der Liste der bereits hochgeladenen Dateien auswählen
3. **Vorhandene Stimme bearbeiten:** über das Stift-Symbol Namen oder zugeordnete Datei ändern
4. **Stimme löschen:** über das Papierkorb-Symbol – Achtung, damit gehen auch alle handschriftlichen Notizen zu dieser Stimme verloren

> 💡 **Tipp:** Diese Seite ist die Anlaufstelle für alle Fälle, in denen die automatische Erkennung beim „PDF nach Stimmen aufteilen" ein Instrument nicht zuordnen konnte (siehe [Noten](noten.md#wenn-die-erkennung-nicht-klappt)) – hier lässt sich die betroffene PDF-Seite manuell der richtigen Stimme zuweisen.

### 4. Gemeinsames Spielen beenden

Auf **Beenden** klicken (mit Bestätigungsabfrage). Die Notenpulte der Musiker wechseln danach automatisch zur Stückauswahl für „Selbst üben".

---

## Ablauf für den Musiker – mit der Kapelle

**Datei:** `probe.php` (im Menü **Notenpult**, auf dem eigenen Handy/Tablet/Laptop öffnen)

### 1. Warten, bis der Kapellmeister ein Stück startet

Solange der Kapellmeister kein Stück vorgibt, zeigt das Notenpult die Stückauswahl zum Üben. Sobald der Kapellmeister ein Stück startet, wechselt das Notenpult von selbst zu **„Mit der Kapelle"**.

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

## Selbst üben

Oben in der Seitenleiste des Notenpults schaltet man zwischen **„Mit der Kapelle"** und **„Selbst üben"** um.

1. **Selbst üben** wählen
2. Ein Stück aus der Liste antippen – über das Suchfeld lässt sich die Liste nach Titel oder Komponist filtern
3. Oder links ein **Notenbuch** wählen und mit **„Notenbuch von vorne spielen"** beginnen. Mit **Voriges** und **Nächstes** in der Seitenleiste blättert man dann durch die Stücke des Notenbuchs, die Liste darunter springt direkt zu einem Stück
4. Die Stimme wählen wie bei der Probe. Favoriten werden automatisch vorausgewählt, und beim Blättern durch ein Notenbuch bleibt die gewählte Stimme erhalten, wenn es sie im nächsten Stück gibt

Zum Üben erscheinen alle Stücke der eigenen Formation, für die Stimmen eingerichtet sind, sowie die eigenen und die geteilten [Notenbücher](notenbucher.md) der Formation.

> 💡 **Tipp:** Startet der Kapellmeister ein Stück, während man übt, wird man nicht unterbrochen. Ein oranger Punkt bei **„Mit der Kapelle"** und ein kurzer Hinweis zeigen es an – ein Tipp darauf genügt, um mitzuspielen.

---

## Formation und mehrere Musikkapellen

Das Notenpult bezieht sich immer auf die aktuell **im Topmenü gewählte Formation**. Ist eine Formation aktiv, sieht der Kapellmeister in der „Stimmen-Belegung" den vollständigen Abgleich mit allen aktiven Mitgliedern dieser Formation (auch wer offline ist), und Musiker sehen nur vorgegebene Stücke, die entweder für ihre eigene Formation gestartet wurden oder für „alle Formationen" gelten.

Wie oben erwähnt, kann aber **technisch nur ein Kapellmeister zur gleichen Zeit im gesamten Verein Stücke vorgeben** – ein zeitgleiches, unabhängiges gemeinsames Spielen zweier Formationen ist aktuell nicht möglich.

---

## Für Administratoren

> 💡 **Tipp:** Das Notenpult funktioniert nur, solange alle Geräte eine funktionierende **WLAN- oder Internetverbindung** haben – die Notenblätter, der Stückwechsel und die gespeicherten Notizen werden laufend mit dem Server abgeglichen. Bei schlechtem Empfang im Probelokal empfiehlt sich ein lokaler WLAN-Router.

---

## Verwandte Themen

- → [Noten](noten.md) – PDF-Dateien hochladen und automatisch nach Stimmen aufteilen
- → [Notenbücher](notenbucher.md) – Stückauswahl beim Leiten über Mappen eingrenzen und Notenbücher zum Üben durchspielen
