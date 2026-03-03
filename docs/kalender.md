# Kalender

**Datei:** `kalender.php`  
**Berechtigung:** Öffentlich (ohne Login) oder eingeloggte Benutzer

Der Kalender bietet eine visuelle Übersicht aller Vereinstermine und ermöglicht den Export als iCal-Feed.

---

## Kalenderansicht

> 📸 **Screenshot:** *Monatsansicht des Kalenders mit eingetragenen Ausrückungen und Terminen in verschiedenen Farben*

Der Kalender zeigt:

- Alle **Ausrückungen** aus dem Ausrückungsmodul
- Zusätzliche **Vereinstermine** (Probe, Sitzung, etc.)
- Farbliche Unterscheidung nach Typ

---

## Termin hinzufügen

**Datei:** `kalender_termin_bearbeiten.php`

> 📸 **Screenshot:** *Modal-Formular „Neuer Termin" mit Datum, Uhrzeit, Bezeichnung und Typ*

1. Klicke im Kalender auf ein **Datum** oder den Button **+ Termin**
2. Fülle Bezeichnung, Datum und Uhrzeit aus
3. Wähle den **Termintyp** (Probe / Sitzung / Sonstiges)
4. **Speichern**

> ℹ️ **Hinweis:** Ausrückungen werden automatisch aus dem Ausrückungsmodul in den Kalender übernommen – sie müssen nicht separat eingetragen werden.

---

## Kalender abonnieren (iCal)

**Datei:** `kalender_abonnement.php` / `kalender_export.php`

> 📸 **Screenshot:** *Seite „Kalender-Abonnement" mit iCal-URL und Anleitung für Google Calendar, Apple Kalender, Outlook*

Mit dem iCal-Export können alle Vereinstermine in externe Kalender-Apps eingebunden werden:

### Google Calendar

1. Navigiere zu **Kalender → Abonnement**
2. Kopiere die **iCal-URL**
3. Öffne Google Calendar
4. Klicke auf **„+"** neben „Andere Kalender"
5. Wähle **„Per URL"** und füge die URL ein
6. Klicke **Kalender hinzufügen**

### Apple Kalender (iOS / macOS)

1. Kopiere die iCal-URL aus Syncopa
2. Öffne auf dem iPhone: **Einstellungen → Kalender → Account hinzufügen → Andere → Kalenderabo hinzufügen**
3. Füge die URL ein → **Weiter** → **Sichern**

### Microsoft Outlook

1. Öffne Outlook
2. Klicke auf **Kalender hinzufügen → Aus dem Internet**
3. Füge die iCal-URL ein → **OK**

---

## Kalendervorschau

**Datei:** `kalender_vorschau.php`

Die Vorschau zeigt eine **öffentlich zugängliche** Ansicht des Kalenders (ohne Login), die z.B. auf der Vereinswebsite eingebettet werden kann.

```html
<!-- Einbettung als iFrame auf der Vereinswebsite -->
<iframe src="https://meinverein.at/syncopa/kalender_vorschau.php"
        width="100%" height="600" frameborder="0">
</iframe>
```
