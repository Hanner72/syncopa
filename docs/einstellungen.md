# Einstellungen

**Datei:** `einstellungen.php`  
**Berechtigung:** Nur **Admin**

Unter Einstellungen wird das globale Verhalten der Applikation konfiguriert.

![Einstellungen](screenshots/einstellungen1.png)

---

## Vereinseinstellungen

### Vereinsdaten

| Feld | Beschreibung |
|---|---|
| Vereinsname | Erscheint in der Navigation und im PDF-Export |
| Ort | Vereinsort |
| Mitgliedsbeiträge | nur bei dem Mitgliederstatus der ein Häkchen bekommt werden auch die Mitgliedsbeiträge im Modul Finanzen -> Kassabuch -> Beiträge verwalten generiert

---

## E-Mail-Einstellungen

> 📸 **Screenshot:** *SMTP-Konfigurationsformular*

Falls Syncopa E-Mail-Benachrichtigungen versenden soll (z.B. für neue Benutzerregistrierungen):

| Feld | Beschreibung |
|---|---|
| SMTP-Server | Mailserver-Adresse |
| SMTP-Port | Meist 587 (TLS) oder 465 (SSL) |
| SMTP-Benutzer | E-Mail-Adresse / Loginname |
| SMTP-Passwort | Passwort des E-Mail-Kontos |
| Absender-Name | z.B. „Musikverein Syncopa" |
| Absender-Adresse | E-Mail-Adresse des Absenders |

> ⚠️ **Hinweis:** Diese Einstellungen bitte in der config.php vornehmen. Die E,ail Einstellungsverwaltung wird bei den Einstellungen erst später eingebaut.

---

## Google Calendar Integration

Diese Funktion ist noch nicht eingebaut - Coming soon...