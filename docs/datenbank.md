# Datenbankstruktur

Diese Seite richtet sich an Entwickler und gibt einen Überblick über die wichtigsten Tabellen. Für die vollständige Spaltenliste siehe `database.sql` im Projekt-Root bzw. die Migrationen in `includes.php`.

---

## Migrationen

Es gibt **kein externes Migrationstool**. Neue Tabellen/Spalten werden als anonyme Funktionen (IIFEs) direkt in `includes.php` definiert und laufen bei **jedem** Request erneut – deshalb müssen alle Statements idempotent sein:

```php
(function() {
    $db = Database::getInstance();
    $db->execute("CREATE TABLE IF NOT EXISTS ...");
    $db->execute("INSERT IGNORE INTO ...");
})();
```

> ⚠️ `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` wird nicht von jeder MySQL/MariaDB-Version unterstützt und schlägt dort als **Syntaxfehler** fehl (nicht nur als "Spalte existiert bereits"). Deshalb prüfen neuere Migrationen den Spaltennamen zuerst über `information_schema.COLUMNS`, bevor sie ein `ALTER TABLE` ausführen.

`database.sql` enthält das Schema für **Neuinstallationen**. Bei jeder neuen Tabelle/Spalte für bestehende Installationen muss sie zusätzlich in `includes.php` ergänzt werden (siehe CLAUDE.md, Abschnitt "Migration system").

---

## Benutzer, Rollen & Berechtigungen

| Tabelle | Zweck |
|---|---|
| `benutzer` | Login-Konten (Benutzername, Passwort-Hash, Google-OAuth-ID, optional `mitglied_id`) |
| `benutzer_rollen` | Pivot-Tabelle: welcher Benutzer hat welche Rolle(n) – **mehrere Rollen pro Benutzer möglich** |
| `rollen` | Rollen-Stammdaten (Name, Farbe, Sortierung, `ist_admin`) |
| `berechtigungen` | Rolle × Modul → `lesen`/`schreiben`/`loeschen` (UNIQUE auf `rolle`+`modul`) |

`Session::checkPermission()` gibt `true` zurück, sobald **irgendeine** Rolle des Benutzers die angefragte Berechtigung gewährt.

## Mitglieder & Formationen

| Tabelle | Zweck |
|---|---|
| `mitglieder` | Stammdaten der Vereinsmitglieder (Adresse, Kontakt, Status, optional `benutzer_id`, `register_id`) |
| `register` | Musikalische Register (Trompete, Klarinette, ...) |
| `formationen` | Formationen/Besetzungen eines Vereins (Name, Kürzel, Farbe) |
| `mitglied_formationen` | Zuordnung Mitglied ↔ Formation, inkl. Rolle und optionalem formationsspezifischem Register |

> Die Verknüpfung Benutzer↔Mitglied ist **doppelt** ausgelegt (`benutzer.mitglied_id` UND `mitglieder.benutzer_id`) – Abfragen, die Formationen eines Benutzers ermitteln, müssen beide Richtungen per `UNION` berücksichtigen (siehe CLAUDE.md, Abschnitt "Dual-direction member↔user link").

## Noten, Notenbücher & Live-Probe

| Tabelle | Zweck |
|---|---|
| `noten` | Notenstücke (Titel, Komponist, Kategorie, Schwierigkeit, optional `formation_id`) |
| `noten_dateien` | Mehrere PDF-Dateien pro Notenstück, `beschreibung` beginnt mit `[stimme] ...` wenn automatisch als Einzelstimme erkannt |
| `noten_stimmen` | Benannte Stimmen eines Stücks (Name, zugeordnete `datei_id`, Seitenbereich) – Grundlage für die Live-Probe-Auswahl |
| `noten_instrumente_pattern` | Erkennungsmuster (Regex-Fragmente) für die automatische Instrumenten-Erkennung beim PDF-Split, verwaltet über `noten_instrumente.php` |
| `notenbucher` / `notenbuch_noten` | Mappen aus mehreren Notenstücken, privat oder "geteilt" (formationsweit sichtbar) |
| `probe_session` | Die aktuell laufende Live-Probe-Session (nur eine gleichzeitig systemweit aktiv) |
| `probe_session_spieler` | Wer hat sich mit welcher Stimme verbunden (ein neuer Datensatz pro Stimmenwechsel, `last_seen`-Heartbeat) |
| `probe_session_viewer` | Wer hat welches Notenstück gerade tatsächlich geladen (für den "online/gerade dabei"-Status beim Kapellmeister) |
| `noten_annotationen` | Handschriftliche Zeichnungen/Stempel pro Benutzer, Stimme und Seite (Live-Probe) |
| `noten_favoriten` | Gemerkte Lieblings-Stimmen pro Benutzer für die schnelle Auswahl in der Live-Probe |

## Ausrückungen & Kalender

| Tabelle | Zweck |
|---|---|
| `ausrueckungen` | Termine/Auftritte (Datum, Ort, optional `formation_id`) |
| `anwesenheit` | Zu-/Absagen pro Mitglied und Ausrückung |
| `ausrueckung_noten` | Zugeordnetes Notenprogramm einer Ausrückung |
| `kalender_termine` | Frei angelegte Kalendereinträge (unabhängig von Ausrückungen) |

## Instrumente & Uniformen

| Tabelle | Zweck |
|---|---|
| `instrument_typen` | Instrumentenarten (mit Register-Zuordnung) |
| `instrumente` | Inventar (Anschaffungspreis, Versicherungswert, Zustand, Ausleihe an `mitglied_id`) |
| `instrument_wartungen` | Wartungshistorie mit Fälligkeitsdatum |
| `mitglied_instrumente` | Welches Mitglied spielt welches Instrument, optional formationsspezifisch (`formation_id`) |
| `uniform_kategorien` / `uniform_kleidungsstuecke` | Uniform-Bestandteile und ihre verfügbaren Größen |
| `uniform_zuweisungen` | Zuordnung Kleidungsstück ↔ Mitglied |

## Finanzen

| Tabelle | Zweck |
|---|---|
| `finanzen` | Einnahmen/Ausgaben-Buchungen, optional `formation_id` |
| `beitraege` | Jährlich generierte Mitgliedsbeiträge mit Bezahlt-Status |

## Festverwaltung

| Tabelle | Zweck |
|---|---|
| `feste` | Grunddaten eines Vereinsfests (Name, Jahr, Zeitraum, Status) |
| `fest_stationen` / `fest_station_tage` | Stationen und deren Aktivierung pro Veranstaltungstag |
| `fest_mitarbeiter` | Helfer (intern über Mitglied oder extern) |
| `fest_dienstplaene` | Schichten je Station/Tag/Mitarbeiter |
| `fest_einkauf_kategorien` / `fest_einkauefe` | Bestellungen, gruppiert nach Kategorie/Station/Lieferant |
| `fest_vertraege` | Verträge mit Honorar, Zahlungsstatus, PDF-Upload |
| `fest_todos` | Aufgaben mit Priorität, Fälligkeit, Zuständigkeit |
| `fest_abrechnung_posten` | Manuelle Zusatzposten der Festabrechnung (Einkäufe/Verträge fließen automatisch mit ein) |

## Sonstiges

| Tabelle | Zweck |
|---|---|
| `einstellungen` | Key-Value-Speicher für Systemeinstellungen |
| `dokumente` | Hochgeladene allgemeine Dokumente |
| `aktivitaetslog` | Protokoll sicherheitsrelevanter Aktionen |

---

Siehe auch: [Dateistruktur](dateistruktur.md) für den Überblick über Klassen und Seiten, die auf diese Tabellen zugreifen.
