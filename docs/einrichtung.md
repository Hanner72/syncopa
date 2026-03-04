# Installation & Einrichtung

## Systemvoraussetzungen

| Komponente | Mindestanforderung |
|---|---|
| PHP | 8.0 oder höher |
| MySQL / MariaDB | 5.7 / 10.3 oder höher |
| Webserver | Apache (mod_rewrite) oder Nginx |
| PHP-Extensions | `pdo_mysql`, `intl`, `zip`, `gd` |

---

## 1. Dateien hochladen

Lade alle Dateien aus dem Projektordner in dein Webserver-Verzeichnis hoch, z.B.:

```
/var/www/html/syncopa/
```

oder in ein Unterverzeichnis (je nach Server):

```
/domains/deineDomain/public_html/meinverein/
```

![Dashboard Screenshot](screenshots/einrichtung1.png)

---

## 2. Datenbank anlegen

Erstelle eine neue MySQL-Datenbank und einen dedizierten Datenbankbenutzer:

```sql
CREATE DATABASE syncopa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'syncopa_user'@'localhost' IDENTIFIED BY 'sicheres_passwort';
GRANT ALL PRIVILEGES ON syncopa.* TO 'syncopa_user'@'localhost';
FLUSH PRIVILEGES;
```

Importiere anschließend die mitgelieferte SQL-Datei:

```bash
mysql -u syncopa_user -p syncopa < syncopa_setup.sql
```

---

## 3. Konfigurationsdatei anpassen

Öffne die Datei `config.php` und trage deine Zugangsdaten ein:

```php
// Datenbankverbindung
define('DB_HOST', 'localhost');
define('DB_NAME', 'syncopa');       // Dein Datenbankname
define('DB_USER', 'syncopa_user'); // Dein Datenbankbenutzer
define('DB_PASS', 'sicheres_passwort');

// App-URL (ohne abschließenden Slash!)
define('BASE_URL', 'https://meinverein.at/syncopa');

// App-Name (erscheint in der Navigation)
define('APP_NAME', 'Mein Musikverein');
```

> ⚠️ **Wichtig:** Die `config.php` enthält sensible Zugangsdaten. Stelle sicher, dass diese Datei **nicht** öffentlich zugänglich ist und füge sie zu `.gitignore` hinzu.

---

## 4. Schreibrechte setzen

Folgende Verzeichnisse benötigen Schreibrechte (z.B. `755` oder `775`):

```
syncopa/uploads/
syncopa/docs/screenshots/    ← optional
```

```bash
chmod -R 755 uploads/
```

---

## 5. Google Login (optional)

Falls du den Google OAuth Login aktivieren möchtest:

1. Öffne die [Google Cloud Console](https://console.cloud.google.com)
2. Erstelle ein neues Projekt
3. Aktiviere die **Google OAuth 2.0 API**
4. Erstelle OAuth-Zugangsdaten und notiere `Client ID` und `Client Secret`
5. Trage diese in `config.php` ein:

```php
define('GOOGLE_CLIENT_ID',     'deine-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'dein-client-secret');
define('GOOGLE_REDIRECT_URI',  BASE_URL . '/login_google_callback.php');
```

---

## 6. Erster Aufruf

Rufe die Anwendung im Browser auf:

```
https://meinverein.at/syncopa/
```

![Dashboard Screenshot](screenshots/ersterlogin1.png)

Du wirst zur Login-Seite weitergeleitet. Weiter geht es unter [Erster Login →](erster-login.md)

---

## Fehlersuche bei der Installation

| Problem | Lösung |
|---|---|
| Weiße Seite | PHP-Fehler in `config.php` – prüfe Syntax und Datenbankdaten |
| `DB_HOST`-Fehler | Datenbankverbindung prüfen, Firewall, Hostname |
| `403 Forbidden` | Dateirechte prüfen (`chmod 644 *.php`) |
| Session-Probleme | PHP `session.save_path` prüfen, Schreibrechte |
