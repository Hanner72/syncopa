# Notenbücher

**Datei:** `notenbucher.php`
**Berechtigung:** `noten – lesen`

Ein **Notenbuch** ist eine Mappe, die mehrere Notenstücke zu einer Sammlung zusammenfasst – z.B. „Hauptmappe", „Weihnachtskonzert" oder eine private Übungsmappe. Notenbücher ersetzen die frühere Zettelwirtschaft: Statt sich für jede Probe oder jeden Auftritt einzelne Stücke zusammenzusuchen, legt man einmal eine passende Mappe an.

---

## Private vs. geteilte Notenbücher

Es gibt zwei Arten von Notenbüchern:

| Typ | Sichtbar für | Beispiel |
|---|---|---|
| **Privat** | nur für dich selbst | persönliche Übungsmappe |
| **Geteilt** | alle Mitglieder einer bestimmten Formation | „Weihnachtskonzert 2026" für die ganze Kapelle |

> 💡 **Tipp:** Ein geteiltes Notenbuch ist besonders praktisch in Kombination mit der [Live-Probe](live-probe.md) – dort lässt sich die Stückauswahl direkt auf ein Notenbuch eingrenzen, sodass der Kapellmeister bei der Probe nicht durch das ganze Archiv scrollen muss.

---

## Übersicht

Die Startseite zeigt zwei Bereiche:

- **Meine Bücher** – alle Bücher, die du selbst angelegt hast (privat oder geteilt)
- **Geteilte Bücher** – Bücher anderer Mitglieder, die für deine Formation freigegeben sind

Jede Karte zeigt Name, Beschreibung, Anzahl der enthaltenen Stücke sowie (bei geteilten Büchern) die zugehörige Formation und den Ersteller.

---

## Notenbuch anlegen

**Datei:** `notenbuch_bearbeiten.php`
**Berechtigung:** `noten – lesen`

1. Klicke auf **+ Neues Notenbuch**
2. Fülle die Buchdetails aus
3. Klicke auf **Speichern**
4. Danach können auf derselben Seite Stücke hinzugefügt werden

### Formularfelder

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Name | ✅ | z.B. „Hauptmappe", „Weihnachtskonzert" |
| Sichtbarkeit | ✅ | Privat (nur ich) oder Geteilt (Formation) |
| Formation | ✅ *(nur bei „Geteilt")* | Für welche Formation das Buch sichtbar sein soll |
| Beschreibung | – | Freitext, z.B. „Programm für das Frühjahrskonzert" |

> ⚠️ **Achtung:** Ein geteiltes Notenbuch kann nur bearbeiten, wer entweder der Ersteller ist, Administrator ist, oder die Berechtigung `noten – schreiben` besitzt. Ein rein privates Buch kann grundsätzlich nur der Ersteller selbst (oder ein Administrator) bearbeiten bzw. löschen.

---

## Stücke hinzufügen und entfernen

Auf der Bearbeitungsseite eines Notenbuchs gibt es zwei Tabellen:

1. **Stücke im Buch** – die aktuell enthaltenen Notenstücke
2. **Stücke hinzufügen** – alle übrigen, für deine Formation verfügbaren Noten (mit Live-Suche zum schnellen Filtern)

**Ein Stück hinzufügen:**
1. In der unteren Tabelle **„Stücke hinzufügen"** das gewünschte Stück suchen
2. Auf das **+**-Symbol in der jeweiligen Zeile klicken
3. Das Stück erscheint sofort oben in der Liste „Stücke im Buch"

**Ein Stück entfernen:**
1. In der Liste „Stücke im Buch" bei dem gewünschten Stück auf das **✕**-Symbol klicken
2. Bestätigen – das Stück selbst wird dadurch **nicht** aus dem Notenarchiv gelöscht, es verschwindet nur aus diesem Buch

---

## Reihenfolge ändern (Drag & Drop)

Die Reihenfolge der Stücke innerhalb eines Notenbuchs lässt sich frei per Maus bzw. Fingertipp festlegen – praktisch, um die Stücke in der Reihenfolge zu sortieren, in der sie bei der Probe oder beim Konzert gespielt werden:

1. In der Liste **„Stücke im Buch"** links am Griff-Symbol (⋮⋮) klicken und halten
2. Die Zeile an die gewünschte Position ziehen
3. Loslassen – die neue Reihenfolge wird automatisch im Hintergrund gespeichert (Bestätigung erscheint kurz unter der Liste)

> 💡 **Tipp:** Diese Reihenfolge wird auch bei der [Live-Probe](live-probe.md) verwendet, wenn man dort die Stückauswahl auf dieses Notenbuch eingrenzt.

---

## Notenbuch löschen

Ein Notenbuch kann von der Übersichtsseite aus über das **Papierkorb-Symbol** gelöscht werden.

- **Private Bücher:** nur durch den Ersteller selbst oder einen Administrator
- **Geteilte Bücher:** nur durch den Ersteller oder einen Administrator (andere Mitglieder mit Schreibrecht dürfen das Buch zwar bearbeiten, aber nicht löschen)

> ⚠️ **Achtung:** Beim Löschen eines Notenbuchs werden nur die Verknüpfungen zu den Stücken entfernt – die Notenstücke selbst bleiben im Notenarchiv erhalten.

---

## Verwandte Themen

- → [Noten](noten.md) – Notenarchiv, PDF-Verwaltung, automatisches Aufteilen nach Stimmen
- → [Live-Probe](live-probe.md) – Notenbücher als Stückauswahl während der Live-Probe verwenden
