# Erster Login

## Admin-Konto anlegen

Nach der Installation existiert noch kein Benutzer. Lege direkt in der Datenbank einen ersten Administrator an:

```sql
INSERT INTO benutzer (benutzername, email, passwort, rolle, aktiv, erstellt_am)
VALUES (
  'admin',
  'admin@meinverein.at',
  '$2y$10$HASH_HIER',   -- mit PHP: password_hash('meinpasswort', PASSWORD_DEFAULT)
  'admin',
  1,
  NOW()
);
```

Alternativ kannst du ein kleines PHP-Skript einmalig ausführen:

```php
<?php
// setup_admin.php  – NUR EINMALIG AUSFÜHREN, danach löschen!
require_once 'config.php';
require_once 'includes.php';

$db = Database::getInstance();
$hash = password_hash('MeinSicheresPasswort123!', PASSWORD_DEFAULT);

$db->execute(
  "INSERT INTO benutzer (benutzername, email, passwort, rolle, aktiv) VALUES (?,?,?,?,1)",
  ['admin', 'admin@meinverein.at', $hash, 'admin']
);
echo "Admin angelegt!";
```

> ⚠️ **Sicherheit:** Das Skript `setup_admin.php` nach der Ausführung sofort **löschen**!

---

## Login-Vorgang

> 📸 **Screenshot:** *Login-Seite mit Felder Benutzername, Passwort und "Mit Google anmelden"*

1. Öffne die Anwendung im Browser
2. Gib **Benutzername** und **Passwort** ein
3. Klicke auf **Anmelden**

Bei Erfolg wirst du zum **Dashboard** weitergeleitet.

---

## Das Dashboard

> 📸 **Screenshot:** *Dashboard mit Statistik-Kacheln, nächste Ausrückungen und Geburtstage*

Das Dashboard zeigt auf einen Blick:

- 📊 **Statistiken** – Mitgliederanzahl, Notenbestand, Instrumente
- 📅 **Nächste Ausrückungen** – die kommenden 5 Termine
- 🎂 **Geburtstage** – Mitglieder mit Geburtstag in diesem Monat
- 🔔 **Neue Benutzer** – Konten die noch keine Rolle zugewiesen haben *(nur Admin/Obmann)*
- 🔧 **Fällige Wartungen** – Instrumente deren Wartungsdatum überschritten ist

---

## Passwort ändern

Nach dem ersten Login solltest du dein Passwort ändern:

1. Klicke oben rechts auf deinen **Benutzernamen**
2. Wähle **Profil / Einstellungen**
3. Trage das neue Passwort ein und bestätige es
4. Klicke **Speichern**

---

## Nächste Schritte empfohlen

Nach dem ersten Login empfehlen wir folgende Reihenfolge:

1. ⚙️ [Stammdaten einrichten](stammdaten.md) – Register, Instrumententypen
2. 👥 [Mitglieder anlegen](mitglieder.md)
3. 🔐 [Weitere Benutzer anlegen](benutzer.md)
4. 🎺 [Erste Ausrückung erstellen](ausrueckung-anlegen.md)
