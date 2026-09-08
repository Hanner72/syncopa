# Installation & Einrichtung

Für eine leichte Installation wurde ein komplettes Installationsscript implementiert.

## Systemvoraussetzungen

| Komponente | Mindestanforderung |
|---|---|
| PHP | 7.4 oder höher |
| MySQL / MariaDB | 5.7 / 10.3 oder höher |
| Webserver | Apache oder Nginx (Syncopa nutzt normale `.php`-Adressen, `mod_rewrite` wird nicht benötigt) |
| PHP-Extensions (Pflicht) | `pdo`, `pdo_mysql`, `intl`, `fileinfo` |
| PHP-Extensions (empfohlen) | `curl` (Google-Login, automatisches System-Update), `zip` (automatisches System-Update) |

Der Installationsassistent (`install.php`) prüft die Pflicht-Voraussetzungen automatisch und zeigt an, falls etwas fehlt.

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

Erstelle eine neue MySQL-Datenbank und einen dedizierten Datenbankbenutzer – entweder per SQL-Befehl oder ganz einfach in der Admin-Oberfläche deines Hosters (z.B. phpMyAdmin, Plesk, cPanel).

Zugangsdaten (Datenbankname, Benutzername, Passwort, Host) unbedingt notieren – sie werden gleich bei der Installation benötigt.

SQL-Befehl:
```sql
CREATE DATABASE syncopa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> 💡 **Tipp:** Der Installationsassistent kann die Datenbank auch selbst anlegen (`CREATE DATABASE IF NOT EXISTS`), sofern der angegebene Datenbankbenutzer dafür berechtigt ist. Reicht die Berechtigung nicht aus, einfach die Datenbank vorher wie oben manuell anlegen.

---

### 3.1 Installation starten

Navigiere per Browser zur install.php um die Installation zu starten.

```
https://meinverein.at/syncopa/install.php
```

![Dashboard Screenshot](screenshots/install1.png)

Falls Systemvoraussetzungen nicht erfüllt werden, bitte die Servereinstellungen ändern, andernfalls kann die Installation nicht fortgesetzt werden.

---

### 3.2 Datenbankverbindung

Hier einfach deine Zugangsdaten zur Datenbank eintragen und auf **Verbindung testen & weiter** klicken

![Dashboard Screenshot](screenshots/install2.png)

---

### 3.3 Anwendungsdaten

Hier die Vereinsdaten (Vereinsname, Ort) eintragen und den ersten Admin-Benutzer anlegen (Benutzername, E-Mail, Passwort – mindestens 8 Zeichen).

> 💡 Diese Zugangsdaten benötigst du gleich danach für den [ersten Login](erster-login.md). Die Vereinsdaten können später jederzeit in den [Einstellungen](einstellungen.md) angepasst werden.

![Dashboard Screenshot](screenshots/install3.png)

---

### 3.4 Integrationen

Google OAuth wird verwendet damit sich User per Google anmelden können. Einfach Client-ID anlegen und hier eintragen. Tutorials dazu gibt es auf [Youtube](https://youtu.be/D8DMj2lQMwo?si=ZrmyEeRz5g5ueB8V).

![Dashboard Screenshot](screenshots/install4.png)

Mit der Google Calendar API können Google Kalender Termine angezeigt werden (noch nicht getestet!).

Die OCR Space API wird benötigt um die Aufsplittung der Noten auf die einzelnen Stimmen mit Benennung zu ermöglichen. Ohne dieses API werden alle Stimmen gleich benannt.

Beim Email-Versand werden Mails bei Benutzeranmeldungen etc. dem Admin gesendet.

---

### 3.5 Installation

Hier wird die Zusammenfassung der Anwendung angezeigt.

Weiters kann hier angeklickt werden ob Beispieldaten geladen werden sollen. Dies ist hilfreich beim ersten Kennenlernen der Anwendung.

![Dashboard Screenshot](screenshots/install5.png)

---

### 3.6 fertige Installation

Die Installation ist abgeschlossen und die Anwendung kann geöffnet werden.

![Dashboard Screenshot](screenshots/install6.png)

---

### 3.7 Konfigurationsdateien

Nach der Installation gibt es zwei Konfigurationsdateien:

| Datei | Zweck | Wird bei Update überschrieben? |
|---|---|---|
| `config.php` | DB-Zugangsdaten, BASE_URL, API-Keys | **Nein** |
| `config.app.php` | App-Konstanten, Autoloader, Hilfsfunktionen | Ja |

> ⚠️ Eigene Anpassungen nur in `config.php` vornehmen – `config.app.php` wird bei jedem System-Update automatisch aktualisiert.

---

### 4. Neuinstallation

Bei nochmaligem Aufruf der install.php wird eine Meldung angezeigt dass SYNCOPA schon installiert ist. Wenn trotzdem neu installiert werden soll, muss einfach die Datei **install.lock** gelöscht werden.

> 💡 Die Datenbanktabellen müssen ebenfalls gelöscht werden da dies sonst auch eine Fehlermeldung wegen doppelter Tabelleneinträge hervorruft!

![Dashboard Screenshot](screenshots/install7.png)

---

## 5. Erster Aufruf

Rufe die Anwendung im Browser auf:

```
https://meinverein.at/syncopa/
```

![Dashboard Screenshot](screenshots/ersterlogin1.png)

Du wirst zur Login-Seite weitergeleitet. Weiter geht es unter [Erster Login →](erster-login.md)

---

## 6. erster Start

![Dashboard Screenshot](screenshots/install8.png)
