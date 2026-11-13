# Changelog

## [2.13.2]

### Hinzugefügt

- Kassabuch: Konten und Kategorien per Drag & Drop sortieren (wird sofort gespeichert)

### Bugs

- BMV: Menüpunkt Jahresbericht fehlte, wenn die BMV-Anbindung deaktiviert und keine Vereinskennung eingetragen war

## [2.13.1]

### Bugs

- Ausrückungen: Anmeldeliste zeigte nur Mitglieder, die beim Anlegen als aktiv galten – kommende Ausrückungen werden jetzt laufend mit den aktuellen Mitgliedern der Formation (bzw. allen aktiven Mitgliedern) abgeglichen; nach einem Formationswechsel fallen nur unberührte Einträge weg

## [2.13.0]

### Hinzugefügt

- Ausrückungen: Anmeldeliste auf der Detailseite aktualisiert sich live (alle 15 Sekunden) – inkl. Zähler und Karten „Zusagen“ / „Absagen“ nach Register; geänderte Einträge leuchten kurz auf
- Ausrückungen: Status und Grund in der Anmeldeliste werden sofort gespeichert (mit Hinweis „Gespeichert“)
- Ausrückungen: eigener grauer Zähler für „keine Antwort“ in Liste und Detailseite

### Geändert

- Ausrückungen: Anmeldeliste mit dezentem Farbstreifen je Status (grün/gelb/rot)
- Ausrückungen: Status und Grund übersichtlicher; am Handy untereinander, „Gemeldet am“ unter dem Namen
- Ausrückungen: Feld „Grund“ nur bei Absage oder vorhandenem Grund

### Bugs

- Ausrückungen: Skript der Detailseite lief nicht (Syntaxfehler, vor jQuery geladen) – „Meine Anmeldung“-Knöpfe ohne Funktion
- Ausrückungen: Speichern der Anmeldeliste prüfte die Schreibberechtigung nicht

## [2.12.1]

### Geändert

- Ausrückungen: Filter auf dem Handy ein- und ausklappbar, standardmäßig eingeklappt; aktive Filter werden angezeigt

## [2.12.0]

### Hinzugefügt

- Uniformen: Bestand (Uniformen → Bestand)
  - je Kleidungsstück wählbar: als Menge (z. B. Hemden, Polos) oder als Einzelstücke mit Nummer (z. B. Lederhosen)
  - Übersicht mit Bestand, ausgegeben und im Lager je Größe; Verlauf aller Zugänge, Ausgaben, Rückgaben und Ausscheidungen
  - Menge: Zugang, Ausscheiden und Inventur (Lager zählen, Syncopa bucht die Differenz)
  - Einzelstücke: Stücke anlegen, ausscheiden und wieder aufnehmen; Stückliste mit aktuellem Träger
  - Kürzel je Kleidungsstück als Präfix der Nummer (L001, H001); Nummern immer 3-stellig, fortlaufend, nach 999 mit A00–Z99
- Uniformen: Zurücknehmen fragt „ins Lager“ oder „ausscheiden“ mit Grund; bei mehreren Stücken auch teilweise
- Uniformen: einem Mitglied mehrere Stück eines Mengen-Kleidungsstücks geben
- Uniformen: Größenauswahl zeigt den Lagerstand, bei Einzelstücken wird die Nummer aus dem Lager gewählt
- Uniformen: Mitgliederliste je Kleidungsstück mit Auswahl „Nur ohne“ / „Alle aktiven“; Zustand wird bei Einzelstücken vom Stück übernommen
- Mitglieder: Detailseite zeigt die ausgegebenen Kleidungsstücke

### Geändert

- Uniformen: alte, nicht mehr verlinkte Uniform-Seiten entfernt

### Bugs

- Uniformen: als „nicht benötigt“ markierte Kleidungsstücke wurden als ausgegebene Teile gezählt

## [2.11.0]

### Hinzugefügt

- BMV-Jahresbericht (Verband → BMV Online → Jahresbericht)
  - wird per Knopfdruck aus den Daten in Syncopa befüllt: Finanzen aus dem Kassabuch, Mitgliederstand zum 31.12., Proben und Ausrückungen, Jugendreferenten, Bearbeiter/in
  - fehlende Angaben sind rot markiert; Farben zeigen, was aus Syncopa, aus dem Vorjahr oder von Hand kommt
  - Abschließen erzeugt das PDF im Layout des BMV-Formulars und sendet es per E-Mail an den BMV
  - Erinnerung am Dashboard, solange der Bericht des Vorjahres nicht abgeschlossen ist
- Neue Felder für den Jahresbericht: BMV-Anlass und Probenart bei Ausrückungen, Klangkörper bei Formationen, Zuordnung der Kassabuch-Kategorien, Empfängeradresse in den Einstellungen
- Rechte-Matrix: neues Modul „BMV Online“ (lesen, schreiben, wieder öffnen) – BMV Online ist nicht mehr nur für Administratoren
- Rollen: frei wählbare Farben mit Farbwähler und Vorschlägen für noch nicht vergebene Farben; die Schrift im Badge passt sich automatisch an

### Geändert

- Menü: „BMV Daten holen“ ist jetzt ein Unterpunkt von „BMV Online“ in der neuen Gruppe „Verband“
- Topmenü: E-Mail und Formationsauswahl haben den Platz getauscht

### Bugs

- Ausrückungen: Speichern ohne Enddatum (offenes Ende) oder ohne Treffpunkt-Zeit führte zu einem Datenbankfehler
- Rollen: das Badge der Rolle Trachtenwart (Lila) war nicht lesbar

## [2.10.0]

### Hinzugefügt

- Notenpult: „Selbst üben“ – Musikanten können zuhause alleine üben
  - alle Stücke der eigenen Formation mit eingerichteten Stimmen, mit Suche
  - eigene und geteilte Notenbücher durchspielen, mit „Voriges“ und „Nächstes“
  - gewählte Stimme bleibt beim Blättern im Notenbuch erhalten, Favoriten werden vorausgewählt
  - Notizen sind beim Üben und bei der Probe dieselben
  - startet der Kapellmeister ein Stück, erscheint ein Hinweis statt einer Unterbrechung
- Notenpult: Umschalter „Mit der Kapelle“ / „Selbst üben“ und Wartebildschirm, solange der Kapellmeister kein Stück vorgibt

### Geändert

- „Live“ heißt jetzt „Notenpult“, „Live Leitung“ heißt „Notenpult leiten“ (Menü, Berechtigungen, Dokumentation)

### Bugs

- Notenpult: handschriftliche Notizen werden immer dem angemeldeten Benutzer zugeordnet, fremde Notizen lassen sich nicht mehr laden oder überschreiben

## [2.9.3]

### Geändert

- Mein Konto: Abstände in der Liste „Meine Download-Links“ angepasst

## [2.9.2]

### Hinzugefügt

- E-Mail: große Dateien als Download-Link (bis 2 GB)
  - automatisch für Dateien über 10 MB oder Anhänge über 20 MB zusammen
  - Hochladen in Stücken mit Fortschrittsanzeige
  - Link gültig 7, 14, 30 oder 90 Tage, danach wird die Datei automatisch gelöscht
  - Downloadseite für Empfänger ohne Anmeldung
  - „Mein Konto → Meine Download-Links“: Downloads ansehen, Link kopieren, löschen
  - öffentliche Adresse von Syncopa unter E-Mail → Funktions-Postfächer einstellbar

### Bugs

- E-Mail: zu große Anhänge brachen mit „Unbekannte Aktion“ ab, jetzt gibt es eine verständliche Meldung
- E-Mail: Anhänge über der Upload-Grenze wurden stillschweigend weggelassen, jetzt wird nicht gesendet und die Datei genannt

### Geändert

- Doku: PHP-Grenzen für Uploads (25 MB je Datei) in der Einrichtung beschrieben

## [2.9.1]

### Hinzugefügt

- E-Mail: Kontaktverwaltung (Knopf „Kontakte“)
  - Kontakte mit Name, Organisation, Funktion, E-Mail, Telefon, Adresse und Notizen
  - Haken „Für alle mit E-Mail-Zugang sichtbar“, sonst bleibt der Kontakt privat
  - ändern und löschen nur durch den Ersteller oder einen Administrator
  - „+ Kontakt“ beim Absender einer Mail, „E-Mail schreiben“ direkt aus der Kontaktliste
- Adressbuch beim Schreiben schlägt Kontakte vor, jetzt für alle mit E-Mail-Zugang

### Verbessert

- Spamfilter: Mails von Kontakten kommen nie in den Junk-Ordner

## [2.9.0]

### Hinzugefügt

- Kassabuch mit Konten: beliebig viele (Handkassa, Girokonto, Sparbuch, Jugendkassa …)
  - Handkassa und Girokonto als Vorlage angelegt
  - Anfangsbestand je Jahr, danach wird automatisch weitergerechnet
  - Kontoansicht mit laufendem Saldo wie ein Kontoauszug
- Feste Kategorien mit Vorlage für Musikvereine, frei erweiterbar
- Umbuchungen zwischen eigenen Konten (z. B. Bargeld aufs Girokonto)
- Belegnummer automatisch je Jahr (Nummernkreis unter Stammdaten), Beleg als PDF oder Foto hochladen
- Berichte zum Ausdrucken mit Vereinskopf
  - Kassabuch (Journal), Einnahmen-Ausgaben-Rechnung mit Vorjahresvergleich, Vermögensübersicht
  - Kassaprüfungsbericht mit Antrag auf Entlastung und Unterschriften
  - Kassabericht für die Jahreshauptversammlung mit Grafik
  - Belegliste mit fehlenden Nummern, Beitragsliste, Voranschlag / Soll-Ist
- Kassasturz: Bargeld nach Scheinen und Münzen zählen, Vergleich mit dem Kassastand, Protokoll
- Voranschlag (Budget) je Kategorie, Ist-Werte des Vorjahres übernehmbar
- Jahresabschluss: Rechnungsprüfer und Feststellungen eintragen, Jahr sperren (nur Admin kann wieder öffnen)
- CSV-Export aller Buchungen eines Jahres
- Bezahlte Mitgliedsbeiträge werden automatisch ins Kassabuch gebucht
- Fest-Abrechnung lässt sich ins Kassabuch übernehmen

### Geändert

- „Finanzen“ heißt jetzt „Kassabuch“; vorhandene Buchungen werden automatisch Handkassa (bar) oder Girokonto zugeordnet, bisherige Kategorien übernommen
- Buchungen werden nur noch über das Formular gelöscht, nicht mehr über einen einfachen Link

## [2.8.5]

### Geändert

- Festverwaltung, Abrechnung: „Deckungsgrad“ heißt jetzt „Kostendeckung“ (Einnahmen ÷ Ausgaben)

### Hinzugefügt

- Festverwaltung, Abrechnung: neue Kennzahl „Gewinnspanne“ (Ergebnis ÷ Einnahmen)
  - zeigt, wie viel von jedem eingenommenen Euro übrig bleibt

## [2.8.4]

### Hinzugefügt

- Festverwaltung, Verträge: bei „Teilweise bezahlt“ den bereits bezahlten Betrag eintragen
  - der offene Restbetrag wird gleich angezeigt
- Festverwaltung, Abrechnung: Anzahlung und Restbetrag eines Vertrags getrennt aufgelistet
  - offene Honorare sind als „noch offen“ gekennzeichnet und im voraussichtlichen Ergebnis eingerechnet

### Bugs

- Festverwaltung, Abrechnung: stornierte Verträge wurden bei den Ausgaben mitgezählt

## [2.8.3]

### Verbessert

- Festverwaltung, Abrechnung: Ausgaben übersichtlicher
  - eine Liste mit Gruppen (Einkäufe, Honorare, Sonstige) und Zwischensummen
  - erhaltene und bestellte Einkäufe gemeinsam, bestellte mit Kennzeichen
  - Details wie Lieferant, Station oder Auftritt klein unter dem Namen
  - Eingabeformulare unter den Listen

### Bugs

- Festverwaltung, Abrechnung: Zahlungsstatus „teilweise bezahlt“ bei Verträgen wurde als „–“ angezeigt

## [2.8.2]

### Hinzugefügt

- Festverwaltung, Abrechnung: bestellte Einkäufe (noch nicht erhalten) werden eigens aufgelistet
  - mit Hinweis „Noch nicht geliefert“ und Kennzeichen „bestellt“
  - zählen als voraussichtliche Ausgaben mit, das Ergebnis heißt dann „Voraussichtliches Ergebnis“

### Verbessert

- Festverwaltung, Einkäufe: Klick auf den Status öffnet eine Auswahl „Status ändern auf …“ (Geplant, Bestellt, Erhalten, Storniert)

## [2.8.1]

### Hinzugefügt

- Animierte Vorstellung von Syncopa für Vorführungen (eigene Seite, nicht in der App verlinkt)
  - 19 Kapitel mit Bildschirmfotos eines erfundenen Beispielvereins
  - abspielbar wie ein Video, mit Kapitelauswahl, Tempo und Vollbild

## [2.8.0]

### Hinzugefügt

- E-Mail-Programm im Stil von Outlook (neuer Menüpunkt „E-Mail“)
  - Lesen, Schreiben, Antworten, Allen antworten, Weiterleiten, Anhänge, Signatur
  - Adressbuch mit Mitgliedern und Gruppen (aktive Mitglieder, Formationen, Register, Mitgliedsarten), Gruppen gehen immer als Bcc
  - Zahl der ungelesenen Nachrichten im Menü und in der Kopfzeile
  - funktioniert mit jedem IMAP/SMTP-Postfach, Vorlagen für Gmail, Outlook, GMX, A1, World4You u. a.
- Persönliche Postfächer und Funktions-Postfächer (z. B. obmann@…) für Rollen
  - Funktions-Postfächer richtet der Admin unter „Funktions-Postfächer“ ein
  - wer die Rolle hat, sieht das Postfach, ohne das Passwort zu kennen
  - Passwörter werden verschlüsselt gespeichert (Schlüssel in config.mailkey.php, bitte mitsichern)
- Ordner im Postfach anlegen, umbenennen und löschen, direkt am Mailserver
  - „Verschieben nach“ und Verschieben mit der Maus
  - „Gesendete speichern in“: Ordner für die gesendete Nachricht wählen
- Spamfilter (Junk-E-Mail) für jedes Postfach
  - sichere Absender: Mitglieder, eigene Postfächer, Empfänger gesendeter Mails, eigene Liste
  - blockierte Absender und Domains
  - nutzt die Spam-Kennzeichnung des Mailservers
  - Lernfilter, lernt aus „Junk-E-Mail“ und „Kein Junk“
  - Hinweis im Junk-Ordner, warum eine Nachricht aussortiert wurde
  - prüft beim Öffnen des Posteingangs und per Cronjob alle 5 Minuten (cron/mail_spamfilter.php)
- „Mein Konto“: eigene E-Mail-Adresse und Passwort ändern, eigene Postfächer verwalten

### Verbessert

- Mitgliederliste merkt sich Suche, Filter, Sortierung und Seite beim Seitenwechsel
- Dokumentation: neue Seite „E-Mail“, Einrichtung des Cronjobs, Datensicherung

## [2.7.1]

### Hinzugefügt

- Gastmusiker/innen: neue Mitgliedsart mit der Eigenschaft „Gast“ (kein Vereinsmitglied)
  - spielen nur in den Formationen mit, denen sie zugeordnet sind
  - werden nie an den BMV übertragen
  - zählen nicht bei Beiträgen, Ehrungen, Statistik und in der Mitgliederliste
  - eigener Filter „Alle Gäste“ in der Mitgliederliste

### Verbessert

- Mitgliederliste filtert sofort beim Tippen bzw. Auswählen, ohne Filtern-Knopf
- Suche in der Mitgliederliste findet auch Register, Instrumente und Kontaktdaten

## [2.7.0]

### Hinzugefügt

- Mitgliedsarten unter Stammdaten (z. B. aktives Mitglied, unterstützendes Mitglied, MusikschülerIn, Ehrenmitglied)
  - je Art: zählt als aktiv, beitragspflichtig, eigener Beitrag, Farbe
  - eigene Arten möglich, die Nummer lässt sich später auf die BMV-Nummer ändern
- Mitgliedsart beim Anlegen und Bearbeiten eines Mitglieds wählen, mit Datum „gilt ab“

### Geändert

- Die Mitgliedsart ersetzt den bisherigen Status (aktiv, passiv, ausgetreten …) überall in Syncopa
  - kommt aus dem BMV oder wird selbst eingetragen, funktioniert auch ohne BMV
  - ohne laufende Mitgliedsart gilt ein Mitglied als ausgetreten
- Mitgliederliste zeigt standardmäßig nur aktuelle Mitglieder
- Mitgliedsbeiträge richten sich nach der Mitgliedsart, in den Einstellungen bleiben Standardbeitrag und Fälligkeit
- Bisherige Beitragseinstellungen und Status werden beim Update automatisch übernommen

## [2.6.1]

### Hinzugefügt

- Standardnamen für die BMV-Nummern: Funktionen, Instrumente, Prüfungen, Mitgliedsarten
  - Ehrenzeichen für den Steirischen Blasmusikverband
  - müssen nicht mehr selbst eingetragen werden

### Verbessert

- Bezeichnungen: Standardname wird grau angezeigt, ein eigener Eintrag hat Vorrang
- Auswahllisten enthalten alle bekannten Funktionen, Prüfungen usw., auch wenn sie noch niemand im Verein hat

## [2.6.0]

### Hinzugefügt

- Neuer Bereich „BMV Daten holen“ (unter System)
  - Vereinsdaten, Mitglieder, Noten und Inventar aus dem BMV übernehmen
  - Vorschau vor jeder Übernahme
  - Hinweis in den Einstellungen, wenn es im BMV neue Daten gibt
- Laufbahn beim Mitglied: Instrumente, Funktionen, Ehrungen, Prüfungen und Mitgliedsart
  - kommen aus dem BMV und können selbst ergänzt werden
  - eigene Einträge werden für die spätere Übertragung an den BMV vorgemerkt
- Neue Seite „Ehrungen“ mit anstehenden und verliehenen Ehrungen
  - Regeln einstellbar (welches Ehrenzeichen nach wie vielen Jahren)
- Noten aus dem BMV übernehmen
  - Syncopa vergibt eigene Archivnummern, die BMV-Nummer wird mitgespeichert
- Vereinsinventar aus dem CSV-Export des BMV-Portals importieren (nur Instrumente)
- Namen zu den BMV-Nummern (Ehrenzeichen, Funktionen, Instrumente …) selbst eintragen

### Geändert

- Syncopa schreibt nichts mehr in den BMV, Daten werden nur noch geholt
- Einstellungen: BMV Zugangsdaten getrennt von der Übernahme der Daten
- Gespielte Instrumente und Instrumente aus dem BMV sind eine gemeinsame Liste
- BMV-Felder beim Mitglied in die normalen Abschnitte eingebaut, Freigaben unter „BMV-Details“
- Mitglieder werden nicht mehr automatisch übernommen, stattdessen kommt ein Hinweis

### Verbessert

- Reiter im dunklen Design richtig dargestellt
- Notensuche findet auch Archiv- und BMV-Nummer
- Mitgliederliste zeigt nur aktuell gespielte Instrumente

### Bugs

- Wichtige Hinweise verschwinden nicht mehr nach 5 Sekunden

## [2.5.1]

### Verbessert

- Sticky Buttons
- Sicherheitsupdates

## [2.5.0]

### Hinzugefügt

- BMV (Blasmusikverband Österreich) Integration
  - Vereinsdaten werden mit dem BMV synchronisiert
  - Kontakte werden mit dem BMV synchronisiert

### Verbessert

- Sicherheit gegen Angriffe verbessert
- Buttons immer am unteren Bildschirmrand sichtbar

## [2.4.4]

### Hinzugefügt

- Tabellen habe jetzt immer Deutsch Beschreibungstexte und Pignationstexte

### Geändert

- optisches bei Tabellen der Festverwaltung angepasst
- Stationen und Dienstplan auch über Mitternacht möglich

### Bugs

- einige Bugs bei Installation und Updates behoben
- Bei der Festverwaltung können jetzt wieder Kategorien angeleght werden
- Datumsfehler bei Festeverwaltung Infoblock

## [2.4.3]

### Geändert

- der komplette Dark Mode wurde überarbeitet

## [2.4.2]

### Hinzugefügt

- Changelog bei den Updates hinzugefügt um Änderungen Nachvollziehbar sind
- Musikanten Statistik bei Live-Leitung hinzugefügt

### Geändert

- Statistikkarten am Dashboard angeglichen
- Geburtstagsliste Alter richtig darstellen mit Farbpatch bei runden Geburtstagen
- Live-Ansicht für Kapellmeister überarbeitet
- Instrumentendetail Ansicht überarbeitet
- komplette Dokumentation überarbeitet

### Bugs

- In der Live Ansicht für den Kapellmeister wurde die Stimmenzuordnung nicht richtig angezeigt
- Status von Fest ändern - Fehlermeldung - behoben

## [2.4.1]

### Bugs

- Noten Seite lässt sich nicht mehr öffnen bei Update von 2.3.9 auf 2.4.0
 - behoben

## [2.4.0]

### Hinzugefügt

- Formationsverwaltung
  - Formationen können angelegt werden (Musikkapelle, 7er Partie, etc.)
  - Jedes Mitglied kann einer oder mehrerer Formationen zugewiesen werden

- Notenaufteilung (Splitten)
  - Instrumenten Pattern in den Einstellungen eingefügt
  - Instrumente für die Notaufteilung bzw. Erkennung können selber ergänzt und erweitert werden
  - Hilfe zur Erstellung der Pattern eingefügt

- Live Noten lesen
 - der Kapellmeister wählt noten und die Musikanten bekommen automatisch die richtigen Noten angezeigt
 - Halbseitige Blätterfunktion für mehr Übersicht eingebaut
 - Noten bearbeiten bzw. Notizen machen
 - Stempel für Musiksymbole eingefügt
 - Notenbücher anlegen
  - getrennt nach Formation

- Rollenmatrix
 - einfaches bearbeiten der Rollenberechtigungen

### Geändert

- Noten PDF Aufteilung
  - PDF 1.5+ kann von FPDI nicht gelesen werden, Info zum speichern als PDF 1.4 oder qpdf am Server installieren

## [2.3.9]

- größere Updatescript Änderungen

## [2.3.8]

- erneutes Updatescript update
  - leider waren noch immer Fehler drin :-(

## [2.3.7]

- erneutes Updatescript update ;-)

## [2.3.6]

- kleine Updates

## [2.3.5]

### Geändert

- Installationsscript Update
  - auch ohne CURL lauffähig

## [2.3.4]

### Geändert

- kleine grafische Updates
- Installationsscript Update

## [2.3.3]

### Hinzugefügt

- **Update** - Anzeige ob ein Update vorhanden ist im Topmenü

### Geändert

- **Dokumentation** - an die Festverwaltung angepasst

## [2.3.2]

- update Test

## [2.3.1]

### Geändert

- install.php

## [2.3.0]

### Hinzugefügt

- **Festverwaltung** – vollständiges Modul zur Verwaltung von Vereinsfesten
  - **Übersicht / Dashboard** mit Statuskarten (Stationen, Mitarbeiter, Todos, Budget)
  - **Stationsverwaltung** – Stationen anlegen, pro Tag aktivieren/deaktivieren
  - **Mitarbeiterverwaltung** – Helfer erfassen und Stationen zuweisen
  - **Dienstplan** – Schichten per Drag & Drop planen, nach Tag und Station filtern
  - **Einkäufe** – Bestellungen erfassen, nach Station / Kategorie / Lieferant gruppiert
    - Sofortfilter mit localStorage-Persistenz
    - Druckbare Bestellliste als PDF (Lieferant, Stationen, Notizen, Status ein-/ausblendbar)
    - Mengen auf ganze Zahlen beschränkt
  - **Vertragsverwaltung** – Verträge für Bands, Händler etc. mit Honorar und Zahlungsstatus
  - **Todos** – Aufgaben mit Priorität, Fälligkeit und Zuständigkeit
    - Todo-Badge im Topmenü (rot = überfällig, gelb = offen)
    - Globale Todo-Übersicht über alle Feste hinweg
  - **Abrechnung** – vollständige Festabrechnung
    - Automatische Übernahme bezahlter Einkäufe und Honorare
    - Manuelle Einnahmen- und Ausgabenposten
    - Statuskarten: Einnahmen, Ausgaben, Ergebnis, Deckungsgrad
    - Druckfunktion (Navigation wird beim Drucken ausgeblendet)

- **Berechtigungssystem – Mehrfachrollen**
  - Ein Benutzer kann jetzt mehrere Rollen gleichzeitig haben
  - Neue Pivot-Tabelle `benutzer_rollen` (automatische Migration bestehender Daten)
  - `checkPermission()` prüft alle Rollen kombiniert
  - Neue Methoden: `Session::isAdmin()`, `Session::getRollenIds()`
  - Neues Modul **Festverwaltung** in der Rechteverwaltung

- **Rollenverwaltung**
  - Reihenfolge per Drag & Drop änderbar (SortableJS, automatisches Speichern)

- **System-Update**
  - Automatisches Update über GitHub direkt im Admin-Bereich
  - Versionsprüfung gegen aktuellen Changelog auf GitHub
  - Update lädt ZIP herunter, entpackt und kopiert Dateien
  - `config.php` wird dabei automatisch geschützt (nicht überschrieben)
  - Update-Protokoll wird live angezeigt

- **Konfiguration aufgeteilt**
  - `config.php` enthält nur noch server-spezifische Einstellungen (DB, URLs, API-Keys)
  - `config.app.php` enthält App-Konstanten, Helfer und Autoloader (wird per Update aktualisiert)
  - `config.example.php` als Vorlage für Neuinstallationen
  - `config.php` wird nicht mehr in git versioniert

### Geändert

- **Benutzerverwaltung**
  - Rollenzuweisung per Multi-Checkbox (statt Single-Dropdown)
  - Alle zugewiesenen Rollen werden als Badges angezeigt

---

## [2.2.3]

### Hinzugefügt

- **Installation**
  - Vollständiges Installationsscript hinzugefügt

- **Uniformen**
  - Einfache Zuteilung bei Kleidungsstücke verwalten mit Anzeige **Fehlend** und Anzahl der Fehlenden

### Geändert

- **Dashboard**
  - Ausrückungen und Geburtstage für Erstanmeldungen nur verschlüsselt sichtbar
  - Hinweis dass erst bei Freischaltung ganz sichtbar

- **Uniformen**
  - Button **nicht benötigt** integriert um nicht benötigte Kleidungsstücke einzutragen

---

## [2.2.2]

### Hinzugefügt

- **Dokumentation**
  - Vollständige Dokumentation der Applikation hinzugefügt

- **Ausrückungen**
  - Kalender-Abonnement (iCal-Feed)
    - Variante 1: nur Ausrückungen
    - Variante 2: Ausrückungen + Termine

### Geändert

- **Generell**
  - Logos und Favicon aktualisiert
  - Sidebar-Menü öffnen/schließen Button aktualisiert

- **Stammdaten**
  - Nummernkreise für Instrumente, Noten und Mitglieder

- **Dashboard**
  - Geburtstagsliste richtig sortiert
  - Deutsche Monatsnamen

- **Kalender**
  - Klick auf Datumsfeld öffnet neuen Termin

- **Ausrückungen**
  - Sortierung nach Datum korrigiert
  - Anwesenheits-Buttons mit Abwesenheitsgrund (Urlaub, Krankheit, etc.)
  - Auflistung der Anwesenden nach Register sortiert
  - Mobilansicht optimiert

- **Instrumente**
  - Spalte "Notizen" in der Haupttabelle einblendbar

---

## [2.2.1]

- Bugfixes Uniformverwaltung
- Kalender-Export Kompatibilität verbessert
- Performance-Verbesserungen Mitgliederliste

---

## [2.2.0]

- Google OAuth Login integriert
- Kalender-Abonnement (iCal-Feed)
- Öffentliche Kalendervorschau
- Granulares Berechtigungssystem (Rollen × Module × Aktionen)
- UI-Überarbeitung mit Bootstrap 5.3

---

## [2.1.0]

- Uniformverwaltung mit Ausgabe/Rücknahme
- Instrumentenwartungs-Tracking
- Dashboard-Widgets (Geburtstage, fällige Wartungen)
- Mitgliedsbeiträge-Verwaltung

---

## [2.0.0]

- Komplette Neuentwicklung in PHP 8
- Module: Mitglieder, Ausrückungen, Finanzen, Noten, Instrumente
- Session-basierte Authentifizierung
- PDF-Export mit FPDI
