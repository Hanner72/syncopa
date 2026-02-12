# 🚀 SYNCOPA - Installationsanleitung

Diese Anleitung führt dich Schritt für Schritt durch die Installation von SYNCOPA.

---

## 📋 Inhaltsverzeichnis

1. [Voraussetzungen](#1-voraussetzungen)
2. [Download](#2-download)
3. [Datenbank einrichten](#3-datenbank-einrichten)
4. [Dateien konfigurieren](#4-dateien-konfigurieren)
5. [Webserver konfigurieren](#5-webserver-konfigurieren)
6. [Erster Login](#6-erster-login)
7. [Nach der Installation](#7-nach-der-installation)
8. [Optionale Konfiguration](#8-optionale-konfiguration)
9. [Updates installieren](#9-updates-installieren)
10. [Deinstallation](#10-deinstallation)

---

## 1. Voraussetzungen

### Server-Anforderungen

| Komponente | Minimum | Empfohlen |
|------------|---------|-----------|
| PHP | 8.0 | 8.2+ |
| MySQL | 8.0 | 8.0+ |
| MariaDB | 10.4 | 10.6+ |
| Apache | 2.4 | 2.4+ |
| Nginx | 1.18 | 1.24+ |

### PHP-Erweiterungen

```bash
# Prüfen welche Extensions installiert sind:
php -m

# Benötigte Extensions:
- pdo_mysql    # Datenbankzugriff
- mbstring     # Zeichenkodierung
- json         # JSON-Verarbeitung
- fileinfo     # Dateiuploads
- intl         # Datumsformatierung (optional)
- gd           # Bildverarbeitung (optional)
```

#### Installation der Extensions (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install php8.2-mysql php8.2-mbstring php8.2-intl php8.2-gd
sudo systemctl restart apache2
```

#### Installation der Extensions (CentOS/RHEL)

```bash
sudo dnf install php-mysqlnd php-mbstring php-intl php-gd
sudo systemctl restart httpd
```

---

## 2. Download

### Option A: Git Clone (empfohlen)

```bash
cd /var/www/html
git clone https://github.com/yourname/syncopa.git
cd syncopa
```

### Option B: ZIP-Download

1. ZIP-Datei von GitHub herunterladen
2. Entpacken in das Webverzeichnis:

```bash
unzip syncopa.zip -d /var/www/html/
cd /var/www/html/syncopa
```

### Option C: FTP-Upload

1. Alle Dateien per FTP hochladen
2. Zielverzeichnis: `/public_html/syncopa/` oder `/htdocs/syncopa/`

---

## 3. Datenbank einrichten

### 3.1 Datenbank erstellen

#### Via MySQL-Konsole

```bash
mysql -u root -p
```

```sql
-- Datenbank erstellen
CREATE DATABASE syncopa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Benutzer erstellen (Passwort anpassen!)
CREATE USER 'syncopa_user'@'localhost' IDENTIFIED BY 'SICHERES_PASSWORT_HIER';

-- Berechtigungen vergeben
GRANT ALL PRIVILEGES ON syncopa.* TO 'syncopa_user'@'localhost';
FLUSH PRIVILEGES;

-- Prüfen
SHOW DATABASES;
EXIT;
```

#### Via phpMyAdmin

1. phpMyAdmin öffnen
2. Tab "Datenbanken"
3. Name: `syncopa`, Kollation: `utf8mb4_unicode_ci`
4. "Anlegen" klicken
5. Tab "Benutzerkonten" → "Benutzerkonto hinzufügen"
6. Benutzer und Passwort vergeben
7. "Erstelle Datenbank mit gleichem Namen und gewähre alle Rechte"

### 3.2 Schema importieren

#### Via Konsole

```bash
cd /var/www/html/syncopa
mysql -u syncopa_user -p syncopa < database.sql
```

#### Via phpMyAdmin

1. Datenbank `syncopa` auswählen
2. Tab "Importieren"
3. Datei `database.sql` auswählen
4. "Importieren" klicken

### 3.3 Import überprüfen

```sql
USE syncopa;
SHOW TABLES;
-- Sollte ca. 20 Tabellen anzeigen
```

---

## 4. Dateien konfigurieren

### 4.1 Konfigurationsdatei erstellen

```bash
cp config.example.php config.php
nano config.php
```

Falls `config.example.php` nicht existiert, `config.php` direkt bearbeiten.

### 4.2 Datenbank-Einstellungen

```php
<?php
// config.php

// ===== DATENBANK =====
define('DB_HOST', 'localhost');           // Datenbankserver
define('DB_NAME', 'syncopa');             // Datenbankname
define('DB_USER', 'syncopa_user');        // Datenbankbenutzer
define('DB_PASS', 'DEIN_PASSWORT');       // Datenbankpasswort
define('DB_CHARSET', 'utf8mb4');

// ===== ANWENDUNG =====
define('APP_NAME', 'Syncopa');            // Name deines Vereins
define('APP_VERSION', '2.1.0');
define('BASE_URL', 'https://example.com/syncopa');  // ANPASSEN!

// ===== SICHERHEIT =====
define('SESSION_LIFETIME', 3600);         // Session-Dauer: 1 Stunde
define('SESSION_NAME', 'syncopa_session');

// ===== UPLOADS =====
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);  // 10 MB
```

### 4.3 Verzeichnisrechte setzen

```bash
# Upload-Verzeichnisse
chmod -R 755 uploads/
chmod -R 755 uploads/noten/
chmod -R 755 uploads/fotos/
chmod -R 755 uploads/dokumente/

# Konfigurationsdatei schützen
chmod 640 config.php
```

---

## 5. Webserver konfigurieren

### Apache

#### Virtual Host erstellen

```bash
sudo nano /etc/apache2/sites-available/syncopa.conf
```

```apache
<VirtualHost *:80>
    ServerName syncopa.example.com
    DocumentRoot /var/www/html/syncopa
    
    <Directory /var/www/html/syncopa>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/syncopa_error.log
    CustomLog ${APACHE_LOG_DIR}/syncopa_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite syncopa.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

#### .htaccess (falls im Unterverzeichnis)

```apache
# Bereits in uploads/.htaccess vorhanden
Options -Indexes
<FilesMatch "\.php$">
    Deny from all
</FilesMatch>
```

### Nginx

```nginx
server {
    listen 80;
    server_name syncopa.example.com;
    root /var/www/html/syncopa;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location /uploads {
        location ~ \.php$ { deny all; }
    }
}
```

---

## 6. Erster Login

### 6.1 Browser öffnen

Navigiere zu deiner Installation:
- `http://localhost/syncopa/`
- `https://syncopa.example.com/`

### 6.2 Anmelden

| Feld | Wert |
|------|------|
| **Benutzername** | `admin` |
| **Passwort** | `admin123` |

### 6.3 Passwort sofort ändern!

1. Nach Login: Oben rechts auf Benutzername klicken
2. "Profil" oder "Einstellungen"
3. Neues sicheres Passwort setzen

---

## 7. Nach der Installation

### 7.1 Grundeinstellungen

1. **System → Einstellungen**
   - Vereinsname eintragen
   - E-Mail-Einstellungen (optional)
   - Mitgliedsbeiträge konfigurieren

2. **System → Stammdaten**
   - Register anlegen (Holz, Blech, Schlagwerk, etc.)
   - Instrumententypen anlegen

3. **System → Benutzer**
   - Weitere Benutzer anlegen
   - Rollen zuweisen

### 7.2 Erste Daten erfassen

1. **Mitglieder** anlegen
2. **Instrumente** im Inventar erfassen
3. **Noten** katalogisieren
4. **Ausrückungen** planen

---

## 8. Optionale Konfiguration

### 8.1 Google OAuth aktivieren

1. Google Cloud Console öffnen: https://console.cloud.google.com
2. Neues Projekt erstellen
3. APIs & Dienste → Anmeldedaten
4. OAuth 2.0-Client-ID erstellen
5. Autorisierte Redirect-URI hinzufügen:
   ```
   https://example.com/syncopa/login_google_callback.php
   ```
6. In `config.php` eintragen:

```php
define('GOOGLE_OAUTH_ENABLED', true);
define('GOOGLE_CLIENT_ID', 'xxx.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-xxx');
define('GOOGLE_REDIRECT_URI', BASE_URL . '/login_google_callback.php');
```

### 8.2 SSL/HTTPS einrichten

#### Let's Encrypt (empfohlen)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d syncopa.example.com
```

#### In config.php erzwingen

```php
define('FORCE_HTTPS', true);
```

### 8.3 Backup einrichten

```bash
# Backup-Script erstellen
nano /home/user/backup_syncopa.sh
```

```bash
#!/bin/bash
DATE=$(date +%Y%m%d)
BACKUP_DIR="/home/user/backups"

# Datenbank sichern
mysqldump -u syncopa_user -pPASSWORT syncopa > $BACKUP_DIR/syncopa_$DATE.sql

# Dateien sichern
tar -czf $BACKUP_DIR/syncopa_files_$DATE.tar.gz /var/www/html/syncopa/uploads/

# Alte Backups löschen (älter als 30 Tage)
find $BACKUP_DIR -name "syncopa_*" -mtime +30 -delete
```

```bash
chmod +x /home/user/backup_syncopa.sh

# Cronjob einrichten (täglich um 3 Uhr)
crontab -e
0 3 * * * /home/user/backup_syncopa.sh
```

---

## 9. Updates installieren

### 9.1 Backup erstellen

```bash
mysqldump -u syncopa_user -p syncopa > backup_vor_update.sql
cp -r /var/www/html/syncopa /var/www/html/syncopa_backup
```

### 9.2 Update herunterladen

```bash
cd /var/www/html/syncopa
git pull origin main
```

Oder: Neue Dateien per FTP überschreiben.

### 9.3 Datenbank-Migration (falls nötig)

Prüfe die Release Notes auf SQL-Änderungen:

```bash
mysql -u syncopa_user -p syncopa < migrations/v2.1.0.sql
```

### 9.4 Cache leeren

```bash
# Browser-Cache leeren (Strg+Shift+R)
# PHP OPcache leeren (falls aktiviert)
sudo systemctl reload apache2
```

---

## 10. Deinstallation

### Vollständige Entfernung

```bash
# 1. Datenbank löschen
mysql -u root -p -e "DROP DATABASE syncopa;"
mysql -u root -p -e "DROP USER 'syncopa_user'@'localhost';"

# 2. Dateien löschen
rm -rf /var/www/html/syncopa

# 3. Apache-Konfiguration entfernen
sudo a2dissite syncopa.conf
sudo rm /etc/apache2/sites-available/syncopa.conf
sudo systemctl reload apache2
```

---

## ❓ Hilfe & Support

### Häufige Fehler

| Fehler | Lösung |
|--------|--------|
| `Connection refused` | MySQL-Dienst starten: `sudo systemctl start mysql` |
| `Access denied` | Datenbankpasswort in config.php prüfen |
| `404 Not Found` | Apache mod_rewrite aktivieren |
| `500 Internal Server Error` | PHP-Fehlerlog prüfen: `/var/log/apache2/error.log` |
| `Permission denied` | Verzeichnisrechte: `chmod -R 755 uploads/` |

### Logs prüfen

```bash
# Apache
tail -f /var/log/apache2/error.log

# PHP
tail -f /var/log/php8.2-fpm.log

# MySQL
tail -f /var/log/mysql/error.log
```

### Community

- GitHub Issues: [github.com/yourname/syncopa/issues](https://github.com/yourname/syncopa/issues)
- Diskussionen: [github.com/yourname/syncopa/discussions](https://github.com/yourname/syncopa/discussions)

---

<p align="center">
  <strong>Viel Erfolg mit SYNCOPA!</strong> 🎵
</p>
