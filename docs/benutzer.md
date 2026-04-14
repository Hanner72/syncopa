# Benutzerverwaltung

**Datei:** `benutzer.php`  
**Berechtigung:** Nur **Admin**

Die Benutzerverwaltung steuert, wer Zugriff auf Syncopa hat und welche Berechtigungen dieser Benutzer besitzt.

---

## Benutzerliste

![Benutzer Übersicht](screenshots/benutzer1.png)

Die Übersicht zeigt alle angelegten Benutzer-Konten.

---

## Neuen Benutzer anlegen

**Datei:** `benutzer_bearbeiten.php`

![Benutzer Neu](screenshots/benutzer2.png)

1. Klicke auf **+ Neuer Benutzer**
2. Fülle das Formular aus
3. Weise dem Benutzer eine oder **mehrere Rollen** zu (Mehrfachauswahl per Checkboxen)
4. Optional: Verknüpfe den Benutzer mit einem **Vereinsmitglied** (ermöglicht Ausrückungs-Anmeldung)
5. Benutzer aktiv (Konto aktiviert / deaktiviert)?
6. **Speichern**

### Formularfelder

| Feld | Pflicht | Beschreibung |
|---|---|---|
| Benutzername | ✅ | Eindeutiger Loginname |
| E-Mail | ✅ | E-Mail-Adresse |
| Passwort | ✅ (neu) | Mindestens 8 Zeichen |
| Rollen | ✅ | Eine oder mehrere Rollen per Checkbox |
| Mitglied | – | Verknüpftes Vereinsmitglied |
| Aktiv | – | Konto aktiviert / deaktiviert |

> 💡 Ein Benutzer mit mehreren Rollen erhält die kombinierten Berechtigungen aller zugewiesenen Rollen. In der Benutzerliste werden alle Rollen als farbige Badges angezeigt.

---

## Rollen eines Benutzers ändern

**Datei:** `benutzer_bearbeiten.php`

![Benutzer Neu](screenshots/benutzer3.png)

1. Klicke in der Benutzerliste auf **„Bearbeiten"**
2. Haken bei den gewünschten Rollen setzen bzw. entfernen
3. **Speichern**

---

## Selbstregistrierung

Wenn Musiker sich selbst registrieren (über den Login-Button „Registrieren"), erhalten sie zunächst die Basisrolle `user`. 

Dieser User kann nur das Dashboard sehen mit dem hinweis, dass die Anmeldung noch von einem Admin freigeschaltet werden muss.

> 💡 **Info:** Momentan nur über einen Google-Account möglich. Neue Benutzer können jedoch von jedem angelegt der Schreibrechte bei den Rollenberechtigungen hat.

Im Dashboard erscheint für Admins und Obmänner eine **Benachrichtigung** über neue Benutzer ohne zugewiesene Rolle:

![Benutzer aktivieren](screenshots/benutzer4.png)

Bei einem Klick auf **Freischalten** wird dem User der Benutzer `Mitglied` zugewiesen.

---

## Google Login

Wenn Google OAuth aktiviert ist (`config.php`), können sich Benutzer auch mit ihrem Google-Konto anmelden.

- Beim ersten Google-Login wird automatisch ein Konto angelegt
- Das Konto erhält die Rolle `user`
- Ein Admin muss dem Konto manuell eine Rolle zuweisen

![Google Login](screenshots/ersterlogin1.png)
