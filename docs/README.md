# <img src="../assets/logo_full.png" alt="Syncopa" style="max-width:75px"> – Musikvereinsverwaltung

> **Version 2.3.7** · Benutzerhandbuch

Willkommen zur offiziellen Dokumentation von **Syncopa** – der Verwaltungssoftware für Musikvereine.

---

## Was kann Syncopa?

Syncopa vereint alle wichtigen Verwaltungsaufgaben eines Musikvereins in einer webbasierten Anwendung:

| Modul | Beschreibung |
|---|---|
| 👥 **Mitglieder** | Stammdaten, Register, Mitgliedsnummern, Geburtstage |
| 🎺 **Ausrückungen** | Termine planen, An-/Abmeldungen verwalten, iCal-Export |
| 📅 **Kalender** | Übersicht aller Termine, iCal-Export |
| 💰 **Finanzen** | Einnahmen & Ausgaben, Mitgliedsbeiträge |
| 🎼 **Noten** | Notenarchiv mit Kategorien |
| 🎻 **Instrumente** | Inventar, Wartungsfristen, Zuordnung |
| 👔 **Uniformen** | Bestand, Ausgabe und Rücknahme |
| 🎪 **Festverwaltung** | Stationen, Dienstplan, Einkäufe, Verträge, Abrechnung |
| 🔐 **Benutzerverwaltung** | Rollen, Mehrfachrollen, Berechtigungen, Google-Login |

---

## Schnellstart

1. **Installation** → [Einrichtung](einrichtung.md)
2. **Ersten Admin anlegen** → [Erster Login](erster-login.md)
3. **Mitglieder importieren** → [Mitglieder](mitglieder.md)
4. **Erste Ausrückung erstellen** → [Ausrückungen](ausrueckungen.md)
5. **Fest planen** → [Festverwaltung](festverwaltung.md)

---

## Benutzerrollen auf einen Blick

Syncopa verwendet ein flexibles **Mehrfachrollen-System** – ein Benutzer kann gleichzeitig mehrere Rollen haben. Die wichtigsten vordefinierten Rollen sind:

| Rolle | Zugriff |
|---|---|
| **Admin** | Vollzugriff auf alle Bereiche inkl. Systemeinstellungen |
| **Obmann** | Mitglieder, Ausrückungen, Übersichten |
| **Kassier** | Finanzen, Beiträge |
| **Schriftführer** | Mitglieder, Noten, Protokolle |
| **Musiker** | Eigene Daten, Ausrückungsanmeldung |

> 💡 Rollen und deren Berechtigungen können unter **Administration → Rollen** individuell angepasst werden. Die Reihenfolge der Rollen ist per Drag & Drop änderbar.

---

## System-Update

Syncopa kann sich direkt aus dem Admin-Bereich selbst aktualisieren:

**Einstellungen → System-Update → Update prüfen**

Das Update lädt die neue Version von GitHub herunter und installiert sie automatisch. Die `config.php` (Zugangsdaten) wird dabei nie überschrieben.

---

## Technische Basis

- **Sprache:** PHP 8+
- **Datenbank:** MySQL / MariaDB
- **Frontend:** Bootstrap 5, Bootstrap Icons, SortableJS
- **PDF-Export:** FPDI / FPDF
- **Authentifizierung:** Session-basiert + optionaler Google OAuth Login
- **Updates:** Automatisch via GitHub ZIP-Download
