# Einstellungen

**Datei:** `einstellungen.php`  
**Berechtigung:** Nur **Admin**

Unter Einstellungen wird das globale Verhalten der Applikation konfiguriert.

---

## Vereinseinstellungen

> 📸 **Screenshot:** *Einstellungsformular mit Tabs: Verein, Erscheinungsbild, E-Mail*

### Vereinsdaten

| Feld | Beschreibung |
|---|---|
| Vereinsname | Erscheint in der Navigation und im PDF-Export |
| Adresse | Vereinsadresse |
| Telefon / E-Mail | Kontaktdaten des Vereins |
| Gründungsjahr | Erscheint im Footer |
| ZVR-Zahl | Österreichisches Vereinsregister |

---

## Logo hochladen

> 📸 **Screenshot:** *Logo-Upload-Bereich mit Vorschau und Datei-Auswahl*

1. Navigiere zu **Einstellungen → Erscheinungsbild**
2. Klicke auf **„Logo auswählen"**
3. Wähle eine Bilddatei (PNG, JPG empfohlen, max. 2 MB)
4. Prüfe die Vorschau
5. **Speichern**

Das Logo erscheint in der Navigationsleiste und in PDF-Exporten.

> 💡 **Tipp:** Verwende ein Logo mit transparentem Hintergrund (PNG) für ein sauberes Erscheinungsbild in der Navigation.

---

## Mitgliedsnummer-Kreis

Syncopa vergibt Mitgliedsnummern automatisch aus einem Nummernkreis:

| Einstellung | Beschreibung |
|---|---|
| Startnummer | Erste vergebene Nummer (z.B. 1001) |
| Präfix | Optionales Präfix (z.B. „MG-") |
| Lücken füllen | Freigewordene Nummern wiederverwenden? |

---

## E-Mail-Einstellungen

> 📸 **Screenshot:** *SMTP-Konfigurationsformular*

Falls Syncopa E-Mail-Benachrichtigungen versenden soll (z.B. für neue Benutzerregistrierungen):

| Feld | Beschreibung |
|---|---|
| SMTP-Host | Mailserver-Adresse |
| SMTP-Port | Meist 587 (TLS) oder 465 (SSL) |
| SMTP-Benutzer | E-Mail-Adresse / Loginname |
| SMTP-Passwort | Passwort des E-Mail-Kontos |
| Absender-Name | z.B. „Musikverein Syncopa" |
| Absender-Adresse | E-Mail-Adresse des Absenders |
