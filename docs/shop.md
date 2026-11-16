# Shop (Vereinsbekleidung)

Über den Shop können Vereine Vereinsbekleidung und Zubehör bestellen. Der Katalog wird **zentral an einer Stelle** gepflegt; alle angebundenen Vereine sehen denselben Shop in ihrer eigenen Syncopa und bestellen per Rechnung.

## Standardmäßig aktiv, zentral steuerbar

Der Shop ist **in jeder Installation von vornherein aktiv**. Jede Syncopa fragt beim Standard-Katalog-Server (`SHOP_SERVER_URL` in `config.app.php`) nach, ob der Shop für sie freigeschaltet ist, und holt dort den Katalog. Ein Verein muss nichts einrichten.

Der Betreiber kann den Shop **je Verein ein- oder ausschalten** (siehe [Installationen](#shop-je-verein-schalten)).

| Rolle | config.php | Aufgabe |
|---|---|---|
| **Katalog-Server** | `SHOP_IST_ZENTRALE = true` und `SHOP_ZENTRALE_URL` = eigene öffentliche Adresse | pflegt Artikel, Preise, Bilder; nimmt Bestellungen entgegen; schaltet Vereine frei |
| **Verein** | nichts | holt Katalog und Freischaltung automatisch vom Server |

```php
// config.php – nur auf dem Katalog-Server
define('SHOP_IST_ZENTRALE', true);
define('SHOP_ZENTRALE_URL', 'https://shop.deine-domain.at/syncopa'); // eigene öffentliche Adresse
define('SHOP_API_KEY',      '');                                     // leer = offen; optional ein Schlüssel
```

Ein optionaler `SHOP_API_KEY` (auf Server und Vereinen gleich) schließt Katalog und Status zusätzlich ab. Leer bedeutet offener Zugang; die Steuerung, welcher Verein den Shop sieht, läuft ohnehin über die Installations-Liste.

---

## Katalog pflegen (Katalog-Server)

**Menü:** Shop → Artikel · Shop → Kategorien · Shop → Verwaltung · **Berechtigung:** Administrator

- **Artikel** (Shop → Artikel): Liste aller Artikel mit Suche (Name, Artikelnummer, Kategorie), Filtern nach Kategorie (inkl. Unterkategorien), Sichtbarkeit und Kennzeichen (Aktion, Ausverkauft, Veredelung, ohne Bild, mit Größen) sowie Sortierung (nach Kategorie, Name, Preis, zuletzt geändert – oder per Klick auf eine Spalte). Filter und Sortierung bleiben für die Sitzung erhalten.
- **Kategorien** (Shop → Kategorien): Kategorienbaum mit Artikelanzahl, Suche, Filter (aktiv/inaktiv, mit/ohne Artikel) und Sortierung (eigene Reihenfolge, Name, meiste Artikel). Ein Klick auf die Artikelanzahl öffnet die Artikelliste gefiltert auf diese Kategorie.
- **Verwaltung** (Shop → Verwaltung): Veredelung, Angebote und Shop-Einstellungen.

- **Artikel:** Name, Kategorie, Preis, Lieferzeit, Beschreibung, Bilder und Größen. Nur **aktive** Artikel sind für die Vereine sichtbar.
- **Größen / Varianten:** je Artikel beliebig viele (z. B. „Gr. 46“ … „Gr. 56“), optional mit eigenem Preis und eigener Artikelnummer. Ohne Größen wird nur der Artikelpreis verwendet (z. B. Zubehör).
- **Bilder:** JPG, PNG oder WEBP, mehrere je Artikel. Das erste Bild ist das Vorschaubild.
- **Kategorien:** zum Gruppieren im Shop. Artikel ohne Kategorie erscheinen unter „Sonstiges“.
- **Einstellungen:** Titel, Einleitung, Versand- und Kontakthinweis sowie die E-Mail-Adresse, an die neue Bestellungen gemeldet werden.
- **Versandkosten:** eine Pauschale je Bestellung (leer oder 0 = kein Versand) und optional eine Freigrenze (**versandfrei ab** einer bestimmten Warensumme). Die Kosten gelten für alle Vereine gleich; sie werden beim Verein im Warenkorb und an der Kasse angezeigt und bei der Bestellung serverseitig autoritativ berechnet und mitgespeichert.
- **Kategorien per Drag & Drop:** Unter Shop → Kategorien lassen sich Kategorien am Griff ziehen – Reihenfolge ändern, unter eine andere Kategorie ziehen (wird Unterkategorie) oder wieder herausziehen (wird Hauptkategorie). Gespeichert wird sofort. Eine Kategorie, die selbst Unterkategorien hat, kann nicht untergeordnet werden. Ziehen geht nur in der eigenen Reihenfolge ohne Suche und Filter.
- **Unterkategorien:** Im Kategorie-Dialog eine **Oberkategorie** wählen – die Kategorie wird damit zur Unterkategorie (eine Ebene). Im Shop öffnet der Button **Kategorien** im Kopfbereich ein Menü mit allen Haupt- und Unterkategorien; wird eine Hauptkategorie gewählt, erscheint darunter eine Leiste mit ihren Unterkategorien. Eine Hauptkategorie zeigt auch die Artikel ihrer Unterkategorien.
- **Angebote:** Karten mit Bild, Titel, Beschreibung und optionaler Aufzählung (z. B. „Besticken & Beflocken“), die oben im Shop als Abschnitt „Unsere Angebote“ erscheinen – keine Artikel, nicht bestellbar. Gepflegt unter Shop → Verwaltung → **Angebote**. Aufzählungspunkte stehen je in einer Zeile; mit `Text | https://…` wird ein Punkt zum Link. Optional verweist ein Angebot auf eine Kategorie („Zu den Artikeln“).
- **Veredelung (Stickerei/Druck):** Unter Verwaltung → **Veredelung** je Position (rechte/linke Schulter, linke/rechte Brust, Rücken) und Art einen Preis je Stück festlegen und freischalten. Im Artikel den Schalter **„Veredelung möglich“** setzen. Im Shop kann der Besteller dann mehrere Positionen anhaken, lädt je Position ein Logo bzw. einen Schriftzug hoch (JPG, PNG, WebP, PDF) und kann einen Hinweis angeben; der Aufpreis gilt je Stück. Die Dateien werden mit der Bestellung an den Katalog-Server übertragen (gespeichert in `uploads/shop_bestellungen`, von außen gesperrt) und sind unter Shop → Bestellungen bei der jeweiligen Position abrufbar. Die Veredelungspreise rechnet der Katalog-Server selbst – nicht der Verein.
- **Aktionspreis:** Im Artikel (oder direkt in der Artikelliste) einen Aktionspreis eintragen. Solange **Aktion** an ist, gilt im Shop der Aktionspreis – der normale Preis wird durchgestrichen angezeigt. Ohne Aktionspreis zeigt „Aktion“ nur das Label.
- **Größen mit Aufpreis:** Größen haben keinen eigenen Preis mehr, sondern einen **Aufpreis (+/−)** auf den Artikelpreis (z. B. `+5` für große Größen, `-3` für kleine). Dadurch gilt ein Aktionspreis automatisch für alle Größen. Bisherige Größenpreise wurden einmalig in Aufpreise umgerechnet.
- **In der Artikelliste direkt umschaltbar:** Sichtbar, Ausverkauft, Aktion sowie der Aktionspreis – sofort gespeichert, ohne den Artikel öffnen zu müssen.
- **Aktion / Ausverkauft je Artikel:** im Artikel (Shop → Verwaltung → Artikel) lassen sich zwei Kennzeichen setzen. **Aktion** zeigt im Shop ein Label (eigener Text möglich, Standard „Aktion“). **Ausverkauft** zeigt ein Ausverkauft-Label, graut das Bild aus und sperrt das Bestellen dieses Artikels. Beide erscheinen auch in der Artikelliste der Verwaltung.

## Bestellstatus für die Vereine

Im Shop gibt es den Menüpunkt **Bestellungen**: Jeder Verein sieht dort nur seine eigenen Bestellungen mit Fortschritt (Eingegangen → Bestätigt → In Bearbeitung → Versendet → Zugestellt), Sendungsnummer, Button **„Sendung verfolgen“** und einer Nachricht des Shops. Abgerufen wird beim Katalog-Server über `api/shop_meine_bestellungen.php`; die Installation weist sich mit ihrer geheimen Installations-ID aus.

## Benachrichtigung per E-Mail

In den Shop-Einstellungen die Adresse für Bestell-Meldungen und das **Versand-Postfach** (ein Funktions-Postfach aus dem E-Mail-Programm) wählen. Dann geht bei jeder neuen Bestellung eine Mail mit allen Positionen, Veredelungen und Summen an diese Adresse. Ein Fehler beim Mailversand verhindert die Bestellung nicht.

## Bestellungen (Katalog-Server)

**Menü:** Shop → Bestellungen

Jede Bestellung eines Vereins läuft hier auf, mit Verein, Ansprechpartner, Lieferadresse und Positionen. Der Status lässt sich setzen: **Neu → Bestätigt → Geliefert → Verrechnet** (oder **Storniert**). Die Abrechnung erfolgt außerhalb von Syncopa per Rechnung.

---

## Shop je Verein schalten

**Menü:** Shop → Installationen · **Berechtigung:** Administrator (nur Katalog-Server)

Jede Syncopa, die den Shop öffnet, meldet sich hier mit Name, Adresse und Version. **Neue Vereine sind standardmäßig freigeschaltet.** Mit dem Schalter schaltest du den Shop für einen Verein ab oder wieder ein. Die Änderung greift beim Verein innerhalb einer Stunde (so lange gilt der zwischengespeicherte Status). Ein abgeschalteter Verein sieht den Shop-Menüpunkt nicht mehr, und Bestellungen von ihm werden abgewiesen.

## Shop für den Verein

**Menü:** Shop · **Berechtigung:** Administrator (Vorstand)

Der Shop öffnet mit einem Header im Trachtenstil; darunter filtern **Kategorie-Chips** („Alle“ + je Kategorie) die Ansicht. Artikel mit Aktion tragen ein farbiges Label, ausverkaufte ein Ausverkauft-Label. Der Vorstand sieht den Katalog, legt Artikel in den Warenkorb und bestellt. Verein, Ansprechpartner und E-Mail sind vorausgefüllt; Lieferadresse und eine Bemerkung werden ergänzt. Sind Versandkosten hinterlegt, erscheinen sie im Warenkorb und an der Kasse als eigene Zeile (ggf. „versandfrei“). Nach dem Absenden erscheint eine Bestellnummer; die Bestellung wird an den Katalog-Server übermittelt. Der Shop ist im Trachtenstil gehalten (Lodengrün, Cremetöne, Serifentitel), passt sich aber dem hellen/dunklen Thema an.

Der Katalog wird bis zu 30 Minuten zwischengespeichert, damit nicht jeder Seitenaufruf den Server abfragt. Ist der Server einmal nicht erreichbar, zeigt Syncopa den zuletzt bekannten Stand.

---

## Technik

- **Katalog-API:** `api/shop_katalog.php` liefert den Katalog als JSON (nur mit gültigem `SHOP_API_KEY`).
- **Bestell-Eingang:** `api/shop_bestellung.php` nimmt Bestellungen als JSON entgegen (nur von freigeschalteten Installationen).
- **Status/Freischaltung:** `api/shop_status.php` – eine Installation meldet sich und erfährt, ob der Shop für sie aktiv ist.
- **Bilder** liegen unter `uploads/shop/` und werden öffentlich ausgeliefert; ihre Adressen stehen im Katalog.
- Die Logik steckt in `classes/Shop.php` (Server, inkl. Installations-Registry) und `classes/ShopKatalog.php` (Verein, inkl. Freischalt-Abfrage).

> 💡 Dies ist die lokale Lösung: Ein Katalog-Server, an den sich die Vereine anbinden. Ein eigenes Portal mit Installations-Liste und zentraler Modulsteuerung ist als späterer Ausbau vorgesehen.
