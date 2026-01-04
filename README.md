# 🎵 SYNCOPA - Musikvereinsverwaltung

**Version 2.0.0** | Entwickelt für österreichische Musikvereine

Eine umfassende Webapplikation zur Verwaltung von Musikvereinen, entwickelt mit PHP 8, Bootstrap 5 und MySQL 8.

---

## 📋 Inhaltsverzeichnis

1. [Funktionsübersicht](#funktionsübersicht)
2. [Systemvoraussetzungen](#systemvoraussetzungen)
3. [Installation](#installation)
4. [Konfiguration](#konfiguration)
5. [Benutzerrollen](#benutzerrollen--berechtigungen)
6. [Module im Detail](#module-im-detail)
7. [API-Dokumentation](#api-dokumentation)
8. [Datenbankstruktur](#datenbankstruktur)
9. [Sicherheit](#sicherheit)
10. [Troubleshooting](#troubleshooting)

---

## 🎯 Funktionsübersicht

### Kernmodule

| Modul | Beschreibung |
|-------|--------------|
| **Mitglieder** | Vollständige Stammdatenverwaltung mit Instrumentenzuordnung |
| **Instrumente** | Inventarverwaltung, Verleih und Wartungshistorie |
| **Noten** | Digitaler Notenkatalog mit Archivnummern |
| **Ausrückungen** | Termine, Anwesenheit und Programmplanung |
| **Kalender** | Interaktiver Kalender mit iCal-Export |
| **Finanzen** | Einnahmen, Ausgaben und Mitgliedsbeiträge |
| **Uniformen** | Trachtenverwaltung und Ausgabehistorie |
| **Benutzer** | Rollenbasierte Zugriffsverwaltung |

---

## 💻 Systemvoraussetzungen

### Server

- **PHP**: 8.0+ (empfohlen: 8.2+)
- **MySQL**: 8.0+
- **Webserver**: Apache 2.4+ oder Nginx

### PHP-Erweiterungen

- `pdo_mysql` - Datenbankzugriff
- `mbstring` - Zeichenkodierung
- `intl` - Datumsformatierung (de_DE)
- `json` - JSON-Verarbeitung
- `fileinfo` - Dateiuploads
- `gd` - Bildverarbeitung (optional)

---

## 🚀 Installation

### Schritt 1: Dateien hochladen

```bash
# Alle Dateien in das Webverzeichnis kopieren
cp -r syncopa/* /var/www/html/syncopa/
```

### Schritt 2: Datenbank erstellen

```sql
-- Neue Datenbank erstellen
CREATE DATABASE syncopa_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Benutzer erstellen
CREATE USER 'syncopa_user'@'localhost' IDENTIFIED BY 'sicheres_passwort';
GRANT ALL PRIVILEGES ON syncopa_db.* TO 'syncopa_user'@'localhost';
FLUSH PRIVILEGES;
```

### Schritt 3: Datenbank importieren

```bash
mysql -u syncopa_user -p syncopa_db < database.sql
```

### Schritt 4: Konfiguration anpassen

Datei `config.php` bearbeiten:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'syncopa_db');
define('DB_USER', 'syncopa_user');
define('DB_PASS', 'sicheres_passwort');
define('BASE_URL', 'https://ihre-domain.at/syncopa');
```

### Schritt 5: Verzeichnisrechte setzen

```bash
chmod 755 uploads/
chmod 755 uploads/noten/
chmod 755 uploads/fotos/
chmod 755 uploads/dokumente/
```

### Schritt 6: Erster Login

- URL: `https://ihre-domain.at/syncopa/`
- **Benutzername**: `admin`
- **Passwort**: `admin123`
- ⚠️ **WICHTIG**: Passwort sofort ändern!

---

## ⚙️ Konfiguration

### Grundeinstellungen (config.php)

| Einstellung | Beschreibung | Standardwert |
|-------------|--------------|--------------|
| `DB_HOST` | Datenbankserver | localhost |
| `DB_NAME` | Datenbankname | syncopa_db |
| `APP_NAME` | Anwendungsname | Syncopa |
| `BASE_URL` | Basis-URL | http://localhost/syncopa |
| `SESSION_LIFETIME` | Session-Dauer (Sek.) | 3600 |
| `MAX_UPLOAD_SIZE` | Max. Dateigröße | 10485760 (10 MB) |

### Google Calendar (optional)

```php
define('GOOGLE_CALENDAR_ENABLED', true);
define('GOOGLE_CALENDAR_API_KEY', 'Ihr_API_Key');
define('GOOGLE_CALENDAR_ID', 'Ihre_Calendar_ID');
```

### E-Mail (optional)

```php
define('EMAIL_ENABLED', true);
define('EMAIL_SMTP_HOST', 'smtp.provider.at');
define('EMAIL_SMTP_PORT', 587);
define('EMAIL_SMTP_USER', 'user@domain.at');
define('EMAIL_SMTP_PASS', 'passwort');
```

---

## 👥 Benutzerrollen & Berechtigungen

### Verfügbare Rollen

| Rolle | Beschreibung |
|-------|--------------|
| **Admin** | Vollzugriff auf alle Module und Einstellungen |
| **Obmann** | Mitglieder- und Terminverwaltung |
| **Kapellmeister** | Noten, Ausrückungen, Programmplanung |
| **Kassier** | Finanzen und Beitragsverwaltung |
| **Schriftführer** | Mitglieder und Dokumentation |
| **Instrumentenwart** | Instrumentenverwaltung und -wartung |
| **Trachtenwart** | Uniformverwaltung |
| **Jugendbeauftragter** | Jugendarbeit, Termine, Noten |
| **Mitglied** | Nur Lesezugriff auf relevante Daten |

### Berechtigungsmatrix

| Modul | Admin | Obmann | Kapellm. | Kassier | Mitglied |
|-------|:-----:|:------:|:--------:|:-------:|:--------:|
| Mitglieder | ✓✓✓ | ✓✓✓ | ✓ | ✓ | ✓ |
| Ausrückungen | ✓✓✓ | ✓✓✓ | ✓✓✓ | ✓ | ✓ |
| Noten | ✓✓✓ | ✓ | ✓✓✓ | ✓ | ✓ |
| Instrumente | ✓✓✓ | ✓ | ✓ | ✓ | ✓ |
| Finanzen | ✓✓✓ | ✓ | ✓ | ✓✓✓ | - |
| Benutzer | ✓✓✓ | ✓ | ✓ | ✓ | - |
| Einstellungen | ✓✓✓ | - | - | - | - |

*Legende: ✓ = Lesen, ✓✓ = Schreiben, ✓✓✓ = Vollzugriff*

---

## 📚 Module im Detail

### Mitgliederverwaltung

- Stammdaten (Name, Adresse, Kontakt)
- Automatische Mitgliedsnummer-Generierung
- Instrumentenzuordnung (Haupt-/Nebeninstrument)
- Registerzuordnung (Holz, Blech, Schlagwerk)
- Status: aktiv, passiv, ausgetreten, Ehrenmitglied
- Eintrittsdatum und Austrittsdatum

### Instrumentenverwaltung

- Inventarnummern und Seriennummern
- Hersteller, Modell, Baujahr
- Zustandsbewertung
- Anschaffungs- und Versicherungswert
- Verleih an Mitglieder
- Wartungshistorie mit Erinnerungen

### Notenverwaltung

- Titel, Komponist, Arrangeur
- Archivnummern-System
- Schwierigkeitsgrade (1-6)
- Genre-Kategorisierung
- PDF-Upload für Partituren
- Standortverwaltung

### Ausrückungen & Kalender

- Veranstaltungstypen: Probe, Konzert, Ausrückung, Fest, Wertung
- Treffpunkt und Treffpunktzeit
- Anwesenheitsverwaltung
- Programmzuordnung (verknüpfte Noten)
- iCal-Export für externe Kalender
- Status: geplant, bestätigt, abgesagt

### Finanzverwaltung

- Einnahmen und Ausgaben
- Kategorisierung
- Belegnummern
- Mitgliedsbeitragsverwaltung
- Zahlungsstatus-Tracking
- Finanzberichte

### Uniformverwaltung

- Größenverwaltung (Jacke, Hose, Hemd)
- Ausgabe- und Rückgabedatum
- Zustandserfassung
- Uniformtypen (Parade, Sommer, Winter)

---

## 🔌 API-Dokumentation

### Kalender-API

**Endpunkt**: `GET /api/kalender.php`

**Parameter**:
- `start` - Startdatum (YYYY-MM-DD)
- `end` - Enddatum (YYYY-MM-DD)

**Antwort** (JSON):
```json
[
  {
    "id": 1,
    "title": "Frühjahrskonzert",
    "start": "2026-04-18T19:00:00",
    "end": "2026-04-18T22:00:00",
    "allDay": false,
    "color": "#28a745",
    "extendedProps": {
      "typ": "Konzert",
      "ort": "Kulturhaus"
    }
  }
]
```

### Kalender-Termine-API

**Endpunkt**: `GET /api/kalender_termine.php`

Liefert allgemeine Kalendertermine (Besprechungen, Geburtstage, etc.)

---

## 🗄️ Datenbankstruktur

### Haupttabellen

| Tabelle | Beschreibung |
|---------|--------------|
| `benutzer` | Benutzerkonten und Login-Daten |
| `rollen` | Verfügbare Benutzerrollen |
| `berechtigungen` | Modul-Berechtigungen pro Rolle |
| `mitglieder` | Mitgliederstammdaten |
| `register` | Musikregister (Holz, Blech, etc.) |
| `instrument_typen` | Instrumentenkategorien |
| `instrumente` | Instrumenteninventar |
| `instrument_wartungen` | Wartungshistorie |
| `mitglied_instrumente` | Zuordnung Mitglied ↔ Instrument |
| `noten` | Notenkatalog |
| `ausrueckungen` | Termine und Events |
| `anwesenheit` | Zu-/Absagen für Events |
| `ausrueckung_noten` | Programmzuordnung |
| `kalender_termine` | Allgemeine Termine |
| `finanzen` | Einnahmen/Ausgaben |
| `beitraege` | Mitgliedsbeiträge |
| `uniformen` | Trachteninventar |
| `einstellungen` | Systemeinstellungen |
| `aktivitaetslog` | Audit-Trail |

### ER-Diagramm (vereinfacht)

```
benutzer ─┬─< aktivitaetslog
          └─< mitglieder ─┬─< mitglied_instrumente >─ instrument_typen
                          ├─< beitraege
                          ├─< anwesenheit >─ ausrueckungen ─< ausrueckung_noten >─ noten
                          ├─< uniformen
                          └─< instrumente >─ instrument_wartungen
```

---

## 🔒 Sicherheit

### Empfohlene Maßnahmen

1. **HTTPS aktivieren**
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

2. **Admin-Passwort ändern** (sofort nach Installation!)

3. **PHP-Fehler deaktivieren** (Produktion)
   ```php
   error_reporting(0);
   ini_set('display_errors', 0);
   ```

4. **Upload-Verzeichnis schützen**
   ```apache
   # uploads/.htaccess
   Options -Indexes
   <FilesMatch "\.php$">
       Deny from all
   </FilesMatch>
   ```

5. **Regelmäßige Backups**
   ```bash
   mysqldump -u user -p syncopa_db > backup_$(date +%Y%m%d).sql
   ```

---

## 🔧 Troubleshooting

### Häufige Probleme

**Problem**: Datenbankverbindung fehlgeschlagen
- Zugangsdaten in `config.php` prüfen
- MySQL-Server läuft? (`systemctl status mysql`)
- Benutzer hat Rechte auf die Datenbank?

**Problem**: Keine Berechtigung
- Benutzerrolle prüfen
- Berechtigungen in `berechtigungen`-Tabelle kontrollieren

**Problem**: Upload fehlgeschlagen
- Verzeichnisrechte prüfen (`chmod 755 uploads/`)
- PHP `upload_max_filesize` erhöhen

**Problem**: Kalender lädt nicht
- Browser-Console auf Fehler prüfen (F12)
- API-Endpunkt direkt testen: `/api/kalender.php`

**Problem**: Datumsformatierung funktioniert nicht
- PHP `intl`-Erweiterung installiert?
- `locale -a | grep de_DE` prüfen

---

## 📁 Projektstruktur

```
syncopa/
├── api/                    # API-Endpunkte
│   ├── kalender.php        # Ausrückungen-API
│   └── kalender_termine.php # Termine-API
├── classes/                # PHP-Klassen
│   ├── Ausrueckung.php
│   ├── Database.php
│   ├── ICalendar.php
│   ├── Instrument.php
│   ├── KalenderTermin.php
│   ├── Mitglied.php
│   ├── Noten.php
│   └── Session.php
├── includes/               # Gemeinsame Komponenten
│   ├── header.php
│   └── footer.php
├── uploads/                # Datei-Uploads
│   ├── dokumente/
│   ├── fotos/
│   └── noten/
├── config.php              # Konfiguration
├── database.sql            # Datenbankschema
├── index.php               # Dashboard
├── login.php               # Anmeldung
└── [weitere Module].php
```

---

## 📄 Lizenz

Dieses System wurde speziell für österreichische Musikvereine entwickelt und kann frei verwendet und angepasst werden.

---

## 🎵 Credits

Entwickelt mit:
- PHP 8
- Bootstrap 5
- MySQL 8
- FullCalendar
- DataTables
- Chart.js

---

**Version**: 2.0.0  
**Stand**: Dezember 2025  
**Entwickelt für**: Österreichische Musikvereine
