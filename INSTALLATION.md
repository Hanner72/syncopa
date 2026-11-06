# 🚀 SYNCOPA - Installationsanleitung

Diese Anleitung führt Sie Schritt für Schritt durch die Installation von Syncopa auf verschiedenen Systemen.

---

## 📋 Inhaltsverzeichnis

1. [Voraussetzungen prüfen](#1-voraussetzungen-prüfen)
2. [Installation unter Linux (Ubuntu/Debian)](#2-installation-unter-linux)
3. [Installation unter Windows (XAMPP)](#3-installation-unter-windows-xampp)
4. [Datenbank einrichten](#4-datenbank-einrichten)
5. [Anwendung konfigurieren](#5-anwendung-konfigurieren)
6. [Erster Start](#6-erster-start)
7. [Produktionsumgebung](#7-produktionsumgebung)

---

## 1. Voraussetzungen prüfen

### Systemanforderungen

| Komponente | Minimum | Empfohlen |
|------------|---------|-----------|
| PHP | 8.0 | 8.2+ |
| MySQL | 8.0 | 8.0+ |
| RAM | 512 MB | 1 GB+ |
| Speicher | 100 MB | 500 MB+ |

### PHP-Erweiterungen prüfen

```bash
# Linux
php -m | grep -E "pdo_mysql|mbstring|intl|json|fileinfo"

# Alle müssen angezeigt werden:
# fileinfo
# intl
# json
# mbstring
# pdo_mysql
```

Falls Erweiterungen fehlen:

```bash
# Ubuntu/Debian
sudo apt install php-mysql php-mbstring php-intl php-json php-gd

# Nach Installation Apache neu starten
sudo systemctl restart apache2
```

---

## 2. Installation unter Linux

### 2.1 LAMP-Stack installieren (falls nicht vorhanden)

```bash
# Apache, MySQL, PHP installieren
sudo apt update
sudo apt install apache2 mysql-server php php-mysql php-mbstring php-intl php-gd php-curl

# Services starten
sudo systemctl start apache2
sudo systemctl start mysql
sudo systemctl enable apache2
sudo systemctl enable mysql
```

### 2.2 Syncopa-Dateien installieren

```bash
# In das Webverzeichnis wechseln
cd /var/www/html

# Syncopa-Ordner erstellen und entpacken
sudo mkdir syncopa
sudo unzip /pfad/zu/syncopa.zip -d syncopa/
# oder
sudo cp -r /pfad/zu/syncopa/* syncopa/

# Berechtigungen setzen
sudo chown -R www-data:www-data syncopa/
sudo chmod -R 755 syncopa/
sudo chmod -R 775 syncopa/uploads/
```

### 2.3 Apache Virtual Host (optional)

```bash
sudo nano /etc/apache2/sites-available/syncopa.conf
```

Inhalt:
```apache
<VirtualHost *:80>
    ServerName syncopa.local
    DocumentRoot /var/www/html/syncopa
    
    <Directory /var/www/html/syncopa>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/syncopa_error.log
    CustomLog ${APACHE_LOG_DIR}/syncopa_access.log combined
</VirtualHost>
```

Aktivieren:
```bash
sudo a2ensite syncopa.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

---

## 3. Installation unter Windows (XAMPP)

### 3.1 XAMPP installieren

1. Download von: https://www.apachefriends.org/
2. Installation mit PHP 8.0+ wählen
3. Apache und MySQL im XAMPP Control Panel starten

### 3.2 Syncopa-Dateien kopieren

1. Entpacken Sie `syncopa.zip`
2. Kopieren Sie den Ordner nach `C:\xampp\htdocs\syncopa`
3. Stellen Sie sicher, dass die Struktur so aussieht:
   ```
   C:\xampp\htdocs\syncopa\
   ├── api\
   ├── classes\
   ├── includes\
   ├── uploads\
   ├── config.php
   ├── database.sql
   ├── index.php
   └── ...
   ```

### 3.3 PHP-Konfiguration anpassen

1. Öffnen Sie `C:\xampp\php\php.ini`
2. Aktivieren Sie (Semikolon entfernen):
   ```ini
   extension=intl
   extension=gd
   extension=mbstring
   extension=pdo_mysql
   ```
3. Apache neu starten

---

## 4. Datenbank einrichten

### 4.1 MySQL-Zugang (phpMyAdmin)

**Linux**: http://localhost/phpmyadmin  
**Windows/XAMPP**: http://localhost/phpmyadmin

### 4.2 Datenbank und Benutzer erstellen

**Option A: Via phpMyAdmin**

1. Tab "SQL" öffnen
2. Folgenden Code ausführen:

```sql
-- Datenbank erstellen
CREATE DATABASE IF NOT EXISTS syncopa_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Benutzer erstellen (Passwort anpassen!)
CREATE USER IF NOT EXISTS 'syncopa_user'@'localhost' 
IDENTIFIED BY 'IhrSicheresPasswort123!';

-- Rechte vergeben
GRANT ALL PRIVILEGES ON syncopa_db.* TO 'syncopa_user'@'localhost';
FLUSH PRIVILEGES;
```

**Option B: Via Kommandozeile**

```bash
# Als root einloggen
sudo mysql -u root -p

# Befehle ausführen (siehe oben)
```

### 4.3 Datenbankschema importieren

**Via phpMyAdmin:**
1. Datenbank `syncopa_db` auswählen
2. Tab "Importieren" klicken
3. Datei `database.sql` hochladen
4. "OK" klicken

**Via Kommandozeile:**
```bash
mysql -u syncopa_user -p syncopa_db < /pfad/zu/database.sql
```

### 4.4 Import überprüfen

```sql
-- In phpMyAdmin oder MySQL:
USE syncopa_db;
SHOW TABLES;

-- Sollte ca. 20 Tabellen anzeigen
```

---

## 5. Anwendung konfigurieren

### 5.1 config.php anpassen

Öffnen Sie `config.php` und passen Sie folgende Werte an:

```php
// ============================================================================
// DATENBANK-KONFIGURATION
// ============================================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'syncopa_db');
define('DB_USER', 'syncopa_user');
define('DB_PASS', 'IhrSicheresPasswort123!');  // ← Ihr Passwort!

// ============================================================================
// ANWENDUNGS-KONFIGURATION
// ============================================================================
define('BASE_URL', 'http://localhost/syncopa');  // ← Ihre URL!
```

### 5.2 Produktion: Fehler deaktivieren

```php
// Für Produktion ändern:
error_reporting(0);
ini_set('display_errors', 0);
```

### 5.3 Upload-Verzeichnisse prüfen

Die Ordner sollten bereits existieren, aber prüfen Sie:

```bash
ls -la uploads/
# Sollte zeigen: dokumente/ fotos/ noten/
```

Falls nicht:
```bash
mkdir -p uploads/{dokumente,fotos,noten}
chmod 775 uploads/*
```

---

## 6. Erster Start

### 6.1 Anwendung aufrufen

Öffnen Sie im Browser:
- **Linux**: http://localhost/syncopa/
- **Windows**: http://localhost/syncopa/
- **Mit Virtual Host**: http://syncopa.local/

### 6.2 Login

| Feld | Wert |
|------|------|
| Benutzername | `admin` |
| Passwort | `admin123` |

### 6.3 Nach dem ersten Login

1. **Passwort ändern!**
   - Benutzer → admin → Bearbeiten → Neues Passwort

2. **Vereinsdaten eintragen**
   - Einstellungen → Vereinsname, Adresse, etc.

3. **Weitere Benutzer anlegen**
   - Benutzer → Neuer Benutzer

### 6.4 Beispieldaten prüfen

Die Datenbank enthält bereits:
- 12 Beispiel-Mitglieder
- 10 Instrumente
- 12 Notentitel
- 11 Ausrückungen für 2026
- Finanztransaktionen
- Mitgliedsbeiträge

Diese können Sie bearbeiten oder löschen und durch echte Daten ersetzen.

---

## 7. Produktionsumgebung

### 7.1 Sicherheits-Checkliste

- [ ] Admin-Passwort geändert
- [ ] HTTPS aktiviert
- [ ] PHP-Fehleranzeige deaktiviert
- [ ] Backup-Strategie eingerichtet
- [ ] Upload-Verzeichnis geschützt

### 7.2 HTTPS aktivieren (Let's Encrypt)

```bash
# Certbot installieren
sudo apt install certbot python3-certbot-apache

# Zertifikat erstellen
sudo certbot --apache -d ihre-domain.at

# Auto-Renewal testen
sudo certbot renew --dry-run
```

### 7.3 .htaccess für Produktion

Erstellen Sie `/.htaccess`:

```apache
# HTTPS erzwingen
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Verzeichnisauflistung deaktivieren
Options -Indexes

# PHP-Dateien in uploads blockieren
<Directory "uploads">
    <FilesMatch "\.php$">
        Deny from all
    </FilesMatch>
</Directory>
```

### 7.4 Automatisches Backup einrichten

```bash
# Backup-Skript erstellen
sudo nano /usr/local/bin/syncopa-backup.sh
```

Inhalt:
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/syncopa"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Datenbank
mysqldump -u syncopa_user -p'IhrPasswort' syncopa_db > $BACKUP_DIR/db_$DATE.sql

# Dateien
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/html/syncopa/uploads/

# Alte Backups löschen (älter als 30 Tage)
find $BACKUP_DIR -mtime +30 -delete
```

```bash
# Ausführbar machen und Cronjob einrichten
sudo chmod +x /usr/local/bin/syncopa-backup.sh
sudo crontab -e
# Hinzufügen: 0 2 * * * /usr/local/bin/syncopa-backup.sh
```

> **E-Mail-Programm:** Sichern Sie zusätzlich die Datei `config.mailkey.php`. Damit sind die Postfach-Passwörter verschlüsselt. Geht sie verloren, müssen alle Postfach-Passwörter neu eingetragen werden.

### 7.5 Spamfilter für das E-Mail-Programm (Cronjob)

Der Spamfilter prüft neue Nachrichten, sobald jemand ein Postfach öffnet. Damit Junk-E-Mail auch aussortiert wird, wenn niemand hineinschaut, und der Filter laufend dazulernt, richten Sie einen Cronjob ein (alle 5 Minuten als Webserver-Benutzer):

```bash
echo '*/5 * * * * www-data /usr/bin/php /var/www/html/syncopa/cron/mail_spamfilter.php >/dev/null 2>&1' | sudo tee /etc/cron.d/syncopa-mailspam
```

Unter Windows (XAMPP/Laragon) geht das über die Aufgabenplanung mit `php.exe cron\mail_spamfilter.php`. Zum Testen: `php cron/mail_spamfilter.php -v`.

---

## ❓ Hilfe bei Problemen

### Häufige Fehler

**"Connection refused" bei Datenbankverbindung**
```bash
# MySQL läuft?
sudo systemctl status mysql
# Neu starten
sudo systemctl restart mysql
```

**"intl extension not found"**
```bash
sudo apt install php-intl
sudo systemctl restart apache2
```

**Seite lädt nicht (500 Error)**
```bash
# Apache Error Log prüfen
sudo tail -50 /var/log/apache2/error.log
```

**Hochladen von Dateien fehlgeschlagen**
```bash
# Rechte prüfen
ls -la uploads/
# Falls nötig:
sudo chown -R www-data:www-data uploads/
sudo chmod -R 775 uploads/
```

---

## 📞 Support

Bei weiteren Fragen steht die Dokumentation in `README.md` zur Verfügung.

---

**Version**: 2.0.0  
**Stand**: Dezember 2025
