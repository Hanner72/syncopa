# Changelog

## [2.24.1]

### Geändert

- Sicherheitsprüfung: Mit „nosemgrep“ ausgenommene Stellen werden nicht mehr an GitHub Code Scanning gemeldet
- Kassabuch-Sortierung, Ausrückung löschen, BMV Daten holen: ohne Ausnahmen sicher geschrieben (fester SQL-Text je Art, Rücksprung nur auf bekannte Seiten, maskierter Reiter-Name)

## [2.24.0]

### Hinzugefügt

- Automatische Sicherheitsprüfung (SAST) auf GitHub bei jedem Push, Pull Request und wöchentlich: PHP-Syntax aller Dateien und Semgrep mit allgemeinen PHP-/Sicherheitsregeln und eigenen Syncopa-Regeln (SQL, XSS, Shell, Dateipfad, Weiterleitung); jeder Fund macht den Lauf rot, Einzelheiten unter Security → Code scanning (`.github/workflows/sast.yml`, `.semgrep/syncopa.yml`, Hilfe: Sicherheitsprüfung)

### Geändert

- Mitglied bearbeiten / Detail: Feld „Kategorien (BMV)“ entfernt, wird beim Holen vom BMV nicht mehr übernommen (Spalte bleibt mit altem Inhalt)

### Sicherheit

- IDs aus Links und Formularen werden als Zahl gelesen (31 Stellen) – verhindert eingeschleusten HTML-/Skriptcode über präparierte Links (XSS)
- Beitragsverwaltung: Jahr aus dem Link wird als Zahl gelesen (XSS)
- Ausrückung löschen: Rücksprung nur noch auf eigene Seiten (keine Weiterleitung auf fremde Adressen)
- Notenpult leiten: Notenabfrage mit Platzhaltern statt eingefügter Werte

## [2.23.1]

### Bugs

- Festverwaltung → Verträge: Teilzahlungen wurden in den Karten „Bezahlt“ und „Offen“ nicht berücksichtigt – jetzt zählt der bezahlte Teilbetrag zu „Bezahlt“ und der Rest zu „Offen“
- Festverwaltung → Verträge: „Gesamt Honorar“ enthält keine stornierten Verträge mehr (Offen + Bezahlt = Gesamt)

## [2.23.0]

### Hinzugefügt

- Mitglied bearbeiten: zum vorigen/nächsten Mitglied blättern (Pfeile oben rechts mit Position, Alt+←/Alt+→) in der Reihenfolge der Mitgliederliste inkl. Suche, Sortierung und Filter; Rückfrage bei ungespeicherten Änderungen; Knopf „Aktualisieren & nächstes“
- Stammdaten → Register und Instrumententypen: Abgleich mit dem BMV (SetupRegister, SetupInstrumente) per Knopf „Mit BMV abgleichen“, beim Übernehmen der Mitglieder und einmal am Tag – die 15 BMV-Register ersetzen die bisherigen Register, alle 55 BMV-Instrumente werden angelegt bzw. mit vorhandenen Typen verknüpft (`classes/BmvInstrumente.php`)

### Geändert

- Register und Instrumententypen aus dem BMV sind in den Stammdaten gesperrt (Kennzeichen „BMV“); eigene Typen bleiben bearbeitbar
- BMV Daten holen: die Karte „Instrumente zuordnen“ entfällt, die Zuordnung kommt aus dem BMV

### Bugs

- Stammdaten: Erfolgs- und Fehlermeldungen (z. B. „kann nicht gelöscht werden“) wurden nicht angezeigt

## [2.22.0]

### Hinzugefügt

- System → BMV-API testen (nur Administratoren, `bmv_apitest.php`): alle Lese-Zugriffspunkte des BMV live aus der API-Beschreibung, Markierung „neu“ und „in Syncopa“, einzeln oder alle prüfen, Tabellenansicht mit Suche, Filter, CSV und JSON – Zugangsdaten aus den Einstellungen
- Stammdaten → Mitgliedsarten: Abgleich mit dem BMV (SetupMitgliederart) per Knopf und automatisch beim Holen der Mitglieder – Namen aus dem BMV, fehlende Arten werden angelegt, eigene Arten mit gleichem Namen auf die BMV-Nummer umgestellt; Kennzeichen „BMV“ / „eigene“
- Stammdaten → Mitgliedsarten: Reihenfolge per Drag & Drop, wird sofort gespeichert (`api/mitgliedsarten_sortieren.php`)

### Geändert

- BMV Daten holen: Namen zu Funktionen, Bereichen, Ehrenzeichen, Instrumenten, Prüfungen und Mitgliedsarten kommen direkt aus den Namenslisten des BMV (Setup-Zugriffspunkte), beim Übernehmen und einmal am Tag beim Öffnen des Reiters „Mitglieder“; der Reiter „Bezeichnungen“ entfällt
- BMV Daten holen: Zuordnung BMV-Instrument → Syncopa-Instrument als aufklappbare Karte im Reiter „Mitglieder“
- Eigene Einträge beim Mitglied (Laufbahn): Auswahl enthält alle Nummern des BMV; Nummern, die der BMV nicht mehr anbietet, nur wenn sie im Verein schon vorkommen

## [2.21.0]

### Hinzugefügt

- Festverwaltung: eigene Farbe je Station (Vorschlagsfarben oder freie Farbwahl, Vorschau), wird im Dienstplan und in der Stationsliste angezeigt und beim Kopieren eines Festes übernommen
- Festverwaltung: Stationen per Drag & Drop sortieren, Reihenfolge wird sofort gespeichert und gilt im Dienstplan (`api/fest_stationen_sortieren.php`)

### Bugs

- Festverwaltung: Farbe einer Station im Dienstplan hing an ihrer Position und änderte sich beim Umsortieren bzw. von Tag zu Tag – jede Station hat jetzt eine fest gespeicherte Farbe (bisherige Farben bleiben erhalten)

## [2.20.0]

### Hinzugefügt

- E-Mail: Entwürfe – automatisches Speichern beim Schreiben, Knopf "Entwurf speichern" und Strg+S; angefangene Nachrichten werden beim Wechsel zu einer anderen Mail gesichert statt verworfen
  - Entwürfe liegen im Ordner "Entwürfe" am Mailserver (auch in anderen Mailprogrammen sichtbar) und öffnen sich per Klick zum Weiterschreiben, mit Empfängern inkl. Gruppen, Cc/Bcc, Anhängen und Bezug zur Original-Mail
  - Senden bzw. Verwerfen entfernt den Entwurf; Anzahl der Entwürfe in eckigen Klammern beim Ordner ("Entwürfe [3]")
- E-Mail: Titelleiste "SyncoMail" im Outlook-Stil mit aktuellem Postfach und Umschalter E-Mail / Kontakte

### Geändert

- E-Mail: Befehlsleiste kompakter, damit mehr Knöpfe Platz haben; bei wenig Platz nur noch Symbole
- E-Mail-Kontakte im Stil von Outlook "Personen": Filter, Liste mit Buchstaben-Trennern, Visitenkarte mit E-Mail senden / Anrufen / Bearbeiten, Suche in der Befehlsleiste
- E-Mail: "+ Kontakt" beim Absender öffnet einen Dialog direkt in der Mail statt einer neuen Seite

### Bugs

- E-Mail: Umlaute in Outlook-Mails (Zeichensatz-Angabe im HTML) wurden falsch dargestellt, z. B. "GrÃ¼ÃŸe"
- E-Mail: zu großer Zeilenabstand in Outlook-Mails (Word-Tags wie `<o:p>`)

## [2.19.0]

### Hinzugefügt

- Shop: Veredelung (Stickerei und Druck) je Position – rechte/linke Schulter, linke/rechte Brust, Rücken; mehrere Positionen wählbar, je Position Logo/Schriftzug hochladen (JPG, PNG, WebP, PDF) und Hinweis
  - Preisliste je Position und Art in Verwaltung -> Veredelung; je Artikel Schalter "Veredelung möglich"; Aufpreis je Stück, Preise rechnet der Katalog-Server selbst
  - Vorlagen werden mit der Bestellung übertragen, in einem von außen gesperrten Ordner abgelegt und sind im Bestelleingang abrufbar (`shop_datei.php`)
- Shop: Bestellstatus für die Vereine – Menüpunkt "Bestellungen" mit Fortschritt (Eingegangen, Bestätigt, In Bearbeitung, Versendet, Zugestellt), Sendungsnummer, Link "Sendung verfolgen" und Nachricht des Shops (`api/shop_meine_bestellungen.php`)
- Shop-Bestelleingang: Status, Sendungsnummer, Verfolgungslink und Nachricht an den Verein je Bestellung, Verlauf der Statuswechsel; Bestellungen auf- und zuklappbar
- Shop: Mail bei neuer Bestellung über ein Funktions-Postfach des E-Mail-Programms (Einstellung "Versenden über Postfach")
- Shop: eigene Menüpunkte "Artikel" und "Kategorien"
  - Artikelliste mit Suche, Filtern (Kategorie inkl. Unterkategorien, Sichtbarkeit, Kennzeichen) und Sortierung; Sichtbar, Ausverkauft, Aktion und Aktionspreis direkt in der Liste umschaltbar (`api/shop_artikel_schalten.php`)
  - Kategorien mit Artikelanzahl, Suche, Filter und Sortierung; Drag & Drop in der eigenen Reihenfolge
- Shop: Aktionspreis je Artikel (normaler Preis durchgestrichen); Größen mit Aufpreis (+/-) statt eigenem Preis, bisherige Größenpreise werden umgerechnet
- Shop: Mengentabelle je Größe – mehrere Größen auf einmal in den Warenkorb; nach dem Hinzufügen bleibt man auf der Artikelseite

### Geändert

- Shop: Aktions-Kennzeichen rot und eckig
- Shop-Verwaltung enthält nur noch Veredelung, Angebote und Einstellungen

### Bugs

- Kalender: deutsche Sprachdatei wurde unter falschem Pfad geladen (Texte wie "all-day", "No events to display" waren englisch); überflüssigen Link auf eine nicht vorhandene FullCalendar-CSS-Datei entfernt
- Shop: Bestell-Benachrichtigung wurde nie versendet (alter Versandweg war abgeschaltet)
- Shop: Größenliste kam bei deaktivierter Größe im falschen Format bei den Vereinen an
- Shop-Artikelliste: Preissortierung (Dezimalpunkt) korrigiert

## [2.18.0]

### Hinzugefügt

- Shop: neues, modernes Design in den Syncopa-Farben (Petrol/Blau, Gold als Akzent; hell und dunkel)
  - Kopfbereich mit Navigation (Shop-Menue, Angebote, Warenkorb), Suche, Vorteils-Hinweisen
  - Mega-Menue "Shop": je Hauptkategorie eine Karte mit ihren Unterkategorien
  - Katalog als kompaktes Raster mit Kategorie-Leiste, Unterkategorien-Leiste, Sortierung und Merkliste (im Browser gespeichert)
  - Mehrere Bilder je Artikel direkt auf der Karte durchklickbar (Pfeile, Punkte, Wischen); Hauptbild in der Verwaltung waehlbar
  - Detailseite mit Bildergalerie, Groessen-Auswahl als Kaestchen und Mengen-Knoepfen
  - Warenkorb in Spalten (Artikel, Preis, Menge, Summe) mit Fortschrittsbalken bis "versandfrei"; Schrittanzeige Warenkorb -> Daten -> Fertig
- Shop: Unterkategorien (eine Ebene); Kategorien in der Verwaltung per Drag & Drop anordnen, unter- und wieder herausziehen (`api/shop_kategorien_sortieren.php`)
- Shop: Angebote – Karten mit Bild, Beschreibung und Aufzaehlung auf der Shop-Startseite, optional mit Verweis auf eine Kategorie (Verwaltung -> Angebote)
- Shop: Versandkosten (Pauschale und "versandfrei ab"), serverseitig berechnet und je Bestellung gespeichert
- Shop: Kennzeichen "Aktion" (mit eigenem Text) und "Ausverkauft" je Artikel; ausverkaufte Artikel sind nicht bestellbar
- Shop: Bilder bis 16 MB; beim Hochladen automatisch auf max. 1600 px verkleinert, komprimiert und nach EXIF gedreht
- Shop-Installationen: eigener Anzeigename je Verein (Stift in der Liste), Ort des Vereins; ohne Namen wird die Adresse angezeigt

### Geaendert

- Shop-Verwaltung: Artikelliste mit vollstaendigem Kategoriepfad und nach Kategorienbaum sortiert; Speichern-Leiste beim Artikel bleibt beim Scrollen sichtbar, mit Hinweis auf ungespeicherte Aenderungen
- Vereine melden ihren Vereinsnamen (ersatzweise den Kontonamen) und Ort an den Katalog-Server

### Bugs

- Shop: Vereinsname wurde unter falschem Schluessel gelesen und kam beim Katalog-Server als "unbenannt" an
- Shop: Migrationen ohne "ADD COLUMN IF NOT EXISTS" (wird von diesem MySQL nicht unterstuetzt)

## [2.17.0]

### Hinzugefügt

- Shop: zentrale Steuerung je Verein. Der Shop ist in jeder Installation standardmaessig aktiv; jede Installation fragt beim Katalog-Server, ob er fuer sie freigeschaltet ist
  - Neue Seite Shop -> Installationen (nur Katalog-Server): Liste aller angebundenen Vereine mit Ein-/Aus-Schalter je Verein; neue Vereine sind automatisch freigeschaltet
  - Statusabfrage `api/shop_status.php`; Vereine brauchen keine Shop-Einstellungen mehr in config.php (Standard-Server ueber SHOP_SERVER_URL)
  - Bestellungen werden nur von freigeschalteten Installationen angenommen

### Geaendert

- Shop-Zugang standardmaessig offen (SHOP_API_KEY optional); die Steuerung laeuft ueber die Installations-Liste

### Bugs

- Shop: falscher Einstellungsschluessel bei der Bestellung (installations_id) korrigiert

## [2.16.0]

### Hinzugefügt

- Shop für Vereinsbekleidung: ein zentral gepflegter Katalog, den die Vereine in ihrer eigenen Syncopa sehen und per Rechnung bestellen
  - Katalog-Server (`SHOP_IST_ZENTRALE`): Kategorien, Artikel, Groessen/Varianten, Bilder und Shop-Einstellungen pflegen (Menue Shop -> Verwaltung); Bestell-Eingang mit Status (Menue Shop -> Bestellungen)
  - Verein (`SHOP_ZENTRALE_URL`): Shop mit Katalog, Warenkorb und Bestellung (Menue Shop); Katalog wird vom Server geholt und bis zu 30 Minuten zwischengespeichert
  - Katalog- und Bestell-Schnittstelle (`api/shop_katalog.php`, `api/shop_bestellung.php`), abgesichert ueber den gemeinsamen Schluessel `SHOP_API_KEY`
  - Dokumentation: neue Seite "Shop (Vereinsbekleidung)"

## [2.15.0]

### Hinzugefügt

- System → Server (nur Administratoren): Sicherheits-Checkliste mit Ampel (erledigt/unsicher/prüfen) und Anleitungen für netcup/Plesk und eigene Server; Prüfungen von außen laufen im Browser; organisatorische Punkte (Datensicherung, zweiter Faktor) von Hand bestätigen
- Sicherheit: Sperre nach Fehlversuchen bei der Anmeldung (5 je Benutzer bzw. 20 je Adresse, 15 Minuten)
- Sicherheit: Prüfzeichen gegen untergeschobene Aktionen (CSRF) für alle Formulare und Hintergrund-Anfragen; Löschen nur noch über die Schaltfläche
- Kalender-Abo: persönliche Links mit geheimem Schlüssel, jederzeit erneuerbar

### Geändert

- Sicherheit: neue Sitzungskennung nach der Anmeldung; Cookie nur verschlüsselt und nur für Syncopa (SameSite); Schutz-Kopfzeilen (u. a. kein Einbetten in fremde Seiten, HSTS)
- Passwörter: überall mindestens 8 Zeichen, bekannte Standardpasswörter werden abgelehnt; Beispiel-Zugänge der Installation bekommen zufällige Passwörter und werden deaktiviert
- BMV-Zugangspasswort wird verschlüsselt gespeichert (bestehendes wird automatisch umgestellt)
- Meldungen werden grundsätzlich entschärft angezeigt; Datenbankfehler zeigen keine technischen Details mehr
- Kalender-Abo: bestehende Abos müssen einmal mit dem neuen persönlichen Link eingerichtet werden

## [2.14.2]

### Hinzugefügt

- Sicherheit: `.htaccess` im Hauptordner wird mitgeliefert – erzwingt https (außer lokal und hinter Cloudflare), keine Ordnerlisten, Belege/BMV-Jahresberichte/Freigaben nur über Syncopa, kein Programmcode in `uploads/`, Programm- und Hilfsdateien (`classes/`, `includes/`, `database.sql`, `version_sync.php`, `.git` …) gesperrt
- Dokumentation: Abschnitt „Schutz durch die .htaccess“ in der Installationsanleitung

### Bugs

- Dokumentation: „Weiter“/„Zurück“ unten führte auf Abschnitte derselben Seite und schien nichts zu tun

## [2.14.1]

### Hinzugefügt

- Dokumentation: neue Seiten „Ehrungen“ und „BMV Daten holen“; Abschnitte BMV-Anbindung (Einstellungen) und Mitgliedsarten (Stammdaten); Dateistruktur und Datenbank vervollständigt
- Dokumentation: alle Screenshots neu, fehlende ergänzt (Ehrungen, BMV, Kassabuch, Uniform-Bestand, Formationen, Festverwaltung, Notenbücher, E-Mail u. a.)

### Geändert

- Dokumentation: Aussehen wie die App, mit Hell- und Dunkelmodus (gleiche Einstellung wie in der App) und einheitlicher Seitenleiste

### Bugs

- Menüpunkt „Dokumentation“ führte ins Leere, wenn Syncopa in einem Unterordner installiert ist
- Mitgliedsbeiträge verwalten: Tabelle war nicht sortier- und durchsuchbar (Skript lief vor jQuery)
- Rollenrechte bearbeiten: Lesen/Schreiben/Löschen wurden nicht mehr automatisch mitgesetzt (Skript lief vor jQuery)
- Benutzer, Beiträge, Uniformen, Noten: Tabellentexte teils englisch – jetzt immer die mitgelieferte deutsche Übersetzung

## [2.14.0]

### Geändert

- Meldungen erscheinen als Toasts oben rechts statt als Box am Seitenanfang – der Inhalt verschiebt sich nicht mehr, wenn eine Meldung verschwindet
  - Erfolg und Info 5 Sekunden, Fehler und Warnungen 10 Sekunden; mit der Maus darüber bleiben sie offen
  - Grüne/rote Meldungen der Seiten werden automatisch zu Toasts; blaue/gelbe Hinweisboxen bleiben dauerhaft stehen
- Bestätigungsdialoge (z. B. „Kategorie löschen?“) als eigenes Fenster statt Browser-Dialog; Löschen mit rotem Knopf
- Hinweis-Fenster des Browsers (z. B. „Bitte Titel eingeben!“) als Toasts, Farbe je nach Inhalt

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
