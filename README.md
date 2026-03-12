# 🎵 SYNCOPA - Musikvereinsverwaltung

[![Version](https://img.shields.io/badge/Version-2.2.1-blue.svg)](https://github.com/yourname/syncopa)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Made in Austria](https://img.shields.io/badge/Made%20in-Austria-red.svg)]()

Eine moderne, umfassende Webapplikation zur Verwaltung von Musikvereinen. Entwickelt mit PHP 8, Bootstrap 5 und MySQL.

![Dashboard Screenshot](docs/screenshots/dashboard.png)
---

## DEMO

https://syncopa.dannerbam.eu/

Admin Login

- Benutzer: admin
- Passwort: admin123

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

## ✨ Features

### Kernfunktionen

- **👥 Mitgliederverwaltung** - Stammdaten, Instrumente, Register, Status
- **🎺 Instrumenteninventar** - Verleih, Wartung, Versicherungswerte
- **🎼 Notenarchiv** - PDF-Upload, Schwierigkeitsgrade, Archivnummern
- **📅 Kalender & Ausrückungen** - Termine, Anwesenheit, iCal-Export
- **💰 Finanzen** - Kassenbuch, Mitgliedsbeiträge, Berichte
- **👔 Uniformverwaltung** - Trachten, Größen, Ausgabe/Rückgabe
- **🔐 Benutzerverwaltung** - Rollen, Berechtigungen, Google OAuth

### Highlights

- 🌓 **Dark/Light Mode** - Automatisch oder manuell umschaltbar
- 📱 **Responsive Design** - Optimiert für Desktop, Tablet & Smartphone
- 🔔 **Dashboard** - Geburtstage, anstehende Termine, Statistiken
- 📊 **Charts** - Visualisierung von Mitglieder- und Finanzstatistiken
- 📤 **iCal-Export** - Kalender-Abo für Google Calendar, Outlook, etc.

---

## 🚀 Schnellstart

### Voraussetzungen

- PHP 8.0+ mit Extensions: `pdo_mysql`, `mbstring`, `json`, `fileinfo`
- MySQL 8.0+ oder MariaDB 10.4+
- Apache 2.4+ mit `mod_rewrite` oder Nginx

### Installation

- ZIP Datei von den Releases runterladen
- diese ZIP auf deinen Server in den gewünschten Ordner (syncopa) laden und entpacken (ggf. zuerst entpacken und dann hochladen falls der Server das Entpacken nicht unterstützt)
- wenn keine vorhanden ist dann eine Datenbank erstellen
- Intallationsscript starten -> `http://DeinServer/syncopa/install.php`

![Dashboard Screenshot](docs/screenshots/install1.png)

### Erster Login

| | |
|---|---|
| **URL** | `http://DeinServer/syncopa/` |
| **Benutzer** | `admin` |
| **Passwort** | `admin123` |

⚠️ **Wichtig:** Passwort nach dem ersten Login sofort ändern!

---

## 📖 Dokumentation

### Benutzerrollen

| Rolle | Beschreibung |
|-------|--------------|
| **Admin** | Vollzugriff auf alle Module und Systemeinstellungen |
| **Obmann** | Mitglieder- und Terminverwaltung |
| **Kapellmeister** | Noten, Ausrückungen, Programmplanung |
| **Kassier** | Finanzen und Beitragsverwaltung |
| **Instrumentenwart** | Instrumentenverwaltung und Wartung |
| **Trachtenwart** | Uniformverwaltung |
| **Mitglied** | Lesezugriff auf relevante Bereiche |

### Projektstruktur

```
syncopa/
├── api/                    # REST-API Endpunkte
│   ├── kalender.php
│   ├── kalender_termine.php
│   └── noten_*.php
├── assets/                 # Statische Dateien
│   ├── logo.svg
│   └── favicon.svg
├── classes/                # PHP-Klassen (OOP)
│   ├── Database.php
│   ├── Session.php
│   ├── Mitglied.php
│   ├── Instrument.php
│   ├── Noten.php
│   ├── Ausrueckung.php
│   ├── KalenderTermin.php
│   └── Uniform.php
├── includes/               # Header, Footer
├── uploads/                # Datei-Uploads
│   ├── noten/
│   ├── fotos/
│   └── dokumente/
├── config.php              # Konfiguration
├── database.sql            # DB-Schema + Demodaten
└── *.php                   # Seiten-Module
```

---

## 🛡️ Sicherheit

### Empfohlene Maßnahmen

1. **HTTPS aktivieren** - SSL-Zertifikat einrichten
2. **Passwörter ändern** - Admin-Passwort sofort nach Installation ändern
3. **Backups erstellen** - Regelmäßige Datenbank-Backups
4. **PHP-Fehler verbergen** - In Produktion: `display_errors = Off`
5. **Upload-Verzeichnis schützen**:

```apache
# uploads/.htaccess
Options -Indexes
<FilesMatch "\.php$">
    Deny from all
</FilesMatch>
```

---

## 📋 Changelog

Alle Änderungen findest du hier: [CHANGELOG.md](./CHANGELOG.md)

---

## 🔧 Troubleshooting

| Problem | Lösung |
|---------|--------|
| **DB-Verbindung fehlgeschlagen** | Zugangsdaten in `config.php` prüfen |
| **Keine Berechtigung** | Benutzerrolle und Berechtigungen prüfen |
| **Upload fehlgeschlagen** | `chmod 755 uploads/` und PHP `upload_max_filesize` |
| **Kalender lädt nicht** | Browser-Console prüfen (F12), API testen |
| **Charts fehlen** | Chart.js wird im Header geladen, Cache leeren |

---

## 🤝 Contributing

Beiträge sind willkommen! Bitte erstelle einen Fork und einen Pull Request.

1. Fork des Repositories
2. Feature-Branch erstellen (`git checkout -b feature/NeuesFunktion`)
3. Änderungen committen (`git commit -m 'Neue Funktion hinzugefügt'`)
4. Branch pushen (`git push origin feature/NeuesFunktion`)
5. Pull Request erstellen

---

## 📄 Lizenz

Dieses Projekt ist unter der MIT-Lizenz lizenziert. Siehe [LICENSE](LICENSE) für Details.

---

## 🙏 Credits

Entwickelt mit:

- [PHP 8](https://php.net)
- [Bootstrap 5](https://getbootstrap.com)
- [MySQL](https://mysql.com)
- [FullCalendar](https://fullcalendar.io)
- [DataTables](https://datatables.net)
- [Chart.js](https://chartjs.org)
- [Bootstrap Icons](https://icons.getbootstrap.com)

---

<p align="center">
  <strong>🎵 SYNCOPA</strong><br>
  Entwickelt für deutschsprachige (DACH) Musikvereine<br>
  <sub>Made with ❤️ in Austria</sub>
</p>
