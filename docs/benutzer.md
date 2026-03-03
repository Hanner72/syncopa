# Benutzerverwaltung

**Datei:** `benutzer.php`  
**Berechtigung:** Nur **Admin**

Die Benutzerverwaltung steuert, wer Zugriff auf Syncopa hat und welche Berechtigungen dieser Benutzer besitzt.

---

## Benutzerliste

> 📸 **Screenshot:** *Tabelle aller Benutzer mit Spalten: Benutzername, E-Mail, Rolle, Aktiv-Status, zuletzt angemeldet*

Die Übersicht zeigt alle angelegten Benutzer-Konten.

---

## Neuen Benutzer anlegen

**Datei:** `benutzer_bearbeiten.php`

> 📸 **Screenshot:** *Formular „Neuer Benutzer" mit Benutzername, E-Mail, Passwort und Rollen-Dropdown*

1. Klicke auf **+ Neuer Benutzer**
2. Fülle das Formular aus
3. Weise dem Benutzer eine **Rolle** zu
4. Optional: Verknüpfe den Benutzer mit einem **Vereinsmitglied** (ermöglicht Ausrückungs-Anmeldung)
5. **Speichern**

### Formularfelder

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Benutzername | ✅ | Eindeutiger Loginname |
| E-Mail | ✅ | E-Mail-Adresse |
| Passwort | ✅ (neu) | Mindestens 8 Zeichen |
| Rolle | ✅ | Zugriffsrolle aus der Rollenverwaltung |
| Mitglied | – | Verknüpftes Vereinsmitglied |
| Aktiv | – | Konto aktiviert / deaktiviert |

---

## Benutzer befördern

**Datei:** `benutzer_befoerdern.php`

Schnelle Rollen-Zuweisung für einen bestehenden Benutzer:

1. Klicke in der Benutzerliste auf **„Rolle ändern"**
2. Wähle die neue Rolle
3. **Speichern**

---

## Selbstregistrierung

Wenn Musiker sich selbst registrieren (über den Login-Button „Registrieren"), erhalten sie zunächst die Basisrolle `user`. 

Im Dashboard erscheint für Admins und Obmänner eine **Benachrichtigung** über neue Benutzer ohne zugewiesene Rolle:

> 📸 **Screenshot:** *Dashboard-Widget „Neue Benutzer" mit Liste und Button „Rolle zuweisen"*

1. Im Dashboard auf **„Rolle zuweisen"** klicken
2. Den Benutzer mit einem Mitglied verknüpfen
3. Passende Rolle auswählen
4. **Speichern**

---

## Google Login

Wenn Google OAuth aktiviert ist (`config.php`), können sich Benutzer auch mit ihrem Google-Konto anmelden.

- Beim ersten Google-Login wird automatisch ein Konto angelegt
- Das Konto erhält die Rolle `user`
- Ein Admin muss dem Konto manuell eine Rolle zuweisen

> 📸 **Screenshot:** *Login-Seite mit „Mit Google anmelden"-Button*
