# BMV-Jahresbericht

**Datei:** `bmv_jahresbericht.php` · **Menü:** Verband → BMV Online → Jahresbericht · **Berechtigung:** Modul „BMV Online“ in der Rechte-Matrix (Administratoren immer)

Syncopa erstellt den Jahresbericht für den Blasmusikverband auf Knopfdruck. Alles, was Syncopa aus seinen Daten kennt, wird automatisch eingetragen. Was fehlt, ist **rot markiert** und wird von Hand ergänzt. Sind alle Angaben vorhanden, wird der Bericht abgeschlossen und als PDF per E-Mail an den Verband geschickt.

---

### Berechtigungen

In der **Rechte-Matrix** gibt es dafür das Modul **BMV Online**. Es gilt für den Jahresbericht und für „BMV Daten holen“:

| Recht | erlaubt |
|---|---|
| Lesen | Menü BMV Online, Jahresbericht und PDF ansehen, BMV-Daten ansehen |
| Schreiben | Jahresbericht ausfüllen, abschließen und senden; BMV-Daten in Syncopa übernehmen; Erinnerung am Dashboard |
| Löschen | abgeschlossenen Jahresbericht wieder öffnen |

Die BMV-Zugangsdaten und die Empfängeradresse in den Einstellungen bleiben Administratoren vorbehalten.

## Ablauf

1. **BMV Online → Jahresbericht** öffnen und oben das Jahr wählen (vorausgewählt ist das Vorjahr)
2. Rot markierte Felder ausfüllen und **Speichern**
3. Mit **PDF-Vorschau** prüfen, wie der Bericht aussieht
4. **Abschließen …** – im Dialog Empfänger, Absender-Postfach und Nachricht prüfen und abschließen

Solange der Bericht des Vorjahres nicht abgeschlossen ist, zeigt das **Dashboard** allen mit Schreibrecht für BMV Online eine Erinnerung mit Link zum Bericht.

Nach dem Abschließen sind die Werte festgeschrieben, das PDF wird abgelegt und lässt sich jederzeit wieder herunterladen oder erneut senden. Mit **Wieder öffnen** (braucht das Löschrecht für BMV Online) wird der Abschluss aufgehoben, die Eingaben bleiben erhalten.

### Farben

| Rahmen | Bedeutung |
|---|---|
| grün | Wert aus Syncopa |
| blau | aus dem Vorjahresbericht übernommen (Jugendkapelle, Probelokal, Kleidung) |
| gelb | von Hand eingetragen oder geändert – bei geänderten Werten steht der Wert aus Syncopa im Tooltip |
| rot | fehlt – bitte eintragen |

Ein Feld leeren und speichern holt wieder den Wert aus Syncopa.

---

## Woher die Werte kommen

| Bereich | Quelle |
|---|---|
| Subventionen und Ausgaben | Kassabuch: Summe der Buchungen des Jahres nach Kategorie. Welche Kategorie in welche Zeile zählt, steht bei **Kassabuch → Konten & Kategorien** in der Spalte „Im BMV-Jahresbericht“. Ausgaben ohne Zuordnung zählen als „alle übrigen Ausgaben“. Beträge werden auf ganze Euro gerundet. |
| Jugendreferenten | Mitglieder mit der BMV-Funktion „Jugendreferent“ am 31.12. |
| Aktive Mitglieder | Mitgliedsart „aktives Mitglied“ am 31.12. (aus dem BMV-Verlauf, sonst die aktuelle Mitgliedsart), aufgeteilt nach Geschlecht und Alter am 31.12. |
| Unterstützende Mitglieder | Mitgliedsart „unterstützendes Mitglied“ am 31.12. |
| In Ausbildung, noch nicht aktiv | Mitgliedsart „MusikschülerIn“ am 31.12. |
| Proben | Ausrückungen vom Typ „Probe“, nach Vollprobe/Teilprobe |
| Ausrückungen | alle übrigen Ausrückungen nach **BMV-Anlass** und Formation, abgesagte zählen nicht |
| Bezirks- und Landesmusikfeste | Ausrückungen mit dem BMV-Anlass „Besuch eines Bezirks-/Landesmusikfests“ – sie zählen nur hier, nicht bei den Ausrückungen |
| Bearbeiter/in | angemeldeter Benutzer bzw. sein Mitgliedsdatensatz |

**Von Hand** kommen jedes Jahr: sonstige nicht musizierende Mitglieder, bereits aktive Mitglieder in Ausbildung, Konzertreisen ins Ausland und Besuche ausländischer Kapellen.

> 💡 **Tipp:** Die Mitgliederzahlen sind nur so gut wie die Mitgliederdaten. Mitglieder ohne Geburtsdatum werden als „über 30“ gezählt – der Bericht nennt sie in einem Hinweis.

---

## Damit möglichst viel automatisch kommt

- **Ausrückung bearbeiten → BMV-Anlass:** z. B. „bei kirchlichen Feierlichkeiten“, „bei Begräbnissen“. Ohne Auswahl gilt: Konzert → vereinseigene Konzerte, Fest → vereinseigene Musikfeste, Wertung → Wettbewerbe, sonst → sonstige Anlässe. „zählt nicht“ nimmt einen Termin ganz heraus.
- **Probe → Probenart:** Vollprobe oder Teilprobe
- **Formation bearbeiten → Im BMV-Jahresbericht als:** Gesamtorchester, Ensemble / kleine Gruppe oder Jugendorchester – bestimmt die Spalte
- **Einstellungen → BMV → Jahresbericht senden an:** die E-Mail-Adresse des Verbands

Gesendet wird über ein E-Mail-Postfach in Syncopa (siehe [E-Mail-Programm](email.md)). Ohne Postfach lässt sich der Bericht abschließen und das PDF herunterladen.
