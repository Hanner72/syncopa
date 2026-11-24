# Sicherheitsprüfung (SAST)

Bei jeder Änderung prüft GitHub den Quellcode von Syncopa automatisch auf typische Sicherheitslücken (statische Analyse, *SAST*). Auf dem Server läuft dafür nichts.

**Datei:** `.github/workflows/sast.yml` · **Regeln:** `.semgrep/syncopa.yml` · **Ausnahmen:** `.semgrepignore`

## Wann geprüft wird

- bei jedem Push auf `main` und auf die Versions-Branches (`v2.24.0` usw.)
- bei Pull Requests
- jeden Montag früh (neue Regeln von Semgrep greifen so auch ohne Änderung)
- per Hand: GitHub → **Actions → Sicherheitsprüfung → Run workflow**

## Was geprüft wird

| Prüfung | Was sie findet |
|---|---|
| **PHP-Syntax** | Jede PHP-Datei wird mit `php -l` geprüft – ein Tippfehler fällt auf, bevor er auf einem Server landet |
| **Semgrep – allgemeine Regeln** | Regelsätze `p/php`, `p/security-audit` und `p/secrets` (z. B. unsichere Funktionen, versehentlich eingecheckte Passwörter und Schlüssel) |
| **Semgrep – Syncopa-Regeln** | Verfolgen Eingaben aus `$_GET`, `$_POST` usw. bis zu einer gefährlichen Stelle |

Die eigenen Regeln:

| Regel | Lücke | So ist es richtig |
|---|---|---|
| `syncopa-sql-mit-benutzereingabe` | SQL-Injection | Werte nur als `?`-Platzhalter übergeben, Spaltennamen nur aus einer festen Liste |
| `syncopa-ausgabe-ohne-maskierung` | XSS | `htmlspecialchars()`, bei Zahlen `(int)`, in JavaScript `json_encode()` |
| `syncopa-shell-mit-benutzereingabe` | Befehls-Injection (z. B. `pdftotext`, `qpdf`) | jedes Argument mit `escapeshellarg()` |
| `syncopa-dateipfad-mit-benutzereingabe` | Path Traversal (`../../config.php`) | `basename()` oder Pfad aus der Datenbank |
| `syncopa-weiterleitung-mit-benutzereingabe` | Weiterleitung auf fremde Seiten | nur eigene Seiten erlauben |

## Ergebnis ansehen

- **Grün / Rot:** im Repository unter **Actions**. Jeder Fund macht den Lauf **rot**, GitHub schickt dann eine E-Mail.
- **Funde im Detail:** unter **Security → Code scanning**, mit Datei, Zeile und Erklärung.
- Die Zusammenfassung jedes Laufs zeigt die Anzahl der Funde.

## Falscher Alarm

Ist eine Stelle geprüft und in Ordnung (z. B. Tabellenname aus einer festen Liste), kommt in dieselbe Zeile oder die Zeile davor ein Kommentar mit Begründung:

```php
// nosemgrep: syncopa-sql-mit-benutzereingabe – Tabellenname nur aus der festen Liste $tabellen
$db->execute("UPDATE `$tabelle` SET sortierung = ? WHERE id = ?", [$pos, $id]);
```

Zwei allgemeine Regeln sind ganz abgeschaltet, weil sie in Syncopa fast nur falschen Alarm geben (`echoed-request`, `tainted-filename`); Ausgaben und Dateipfade prüfen die eigenen Regeln genauer.

## Lokal prüfen (optional)

Mit Docker, ohne Installation:

```bash
docker run --rm -v "$PWD":/src -w /src semgrep/semgrep semgrep scan \
  --config p/php --config p/security-audit --config p/secrets --config .semgrep/syncopa.yml \
  --exclude-rule php.lang.security.injection.echoed-request.echoed-request \
  --exclude-rule php.lang.security.injection.tainted-filename.tainted-filename \
  --exclude config.php --exclude config.mailkey.php --metrics off
```

> 💡 `config.php` und `config.mailkey.php` sind nicht im Repository – lokal deshalb ausschließen, sonst meldet die Prüfung die echten Zugangsdaten.
