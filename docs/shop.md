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

**Menü:** Shop → Verwaltung · **Berechtigung:** Administrator

- **Artikel:** Name, Kategorie, Preis, Lieferzeit, Beschreibung, Bilder und Größen. Nur **aktive** Artikel sind für die Vereine sichtbar.
- **Größen / Varianten:** je Artikel beliebig viele (z. B. „Gr. 46“ … „Gr. 56“), optional mit eigenem Preis und eigener Artikelnummer. Ohne Größen wird nur der Artikelpreis verwendet (z. B. Zubehör).
- **Bilder:** JPG, PNG oder WEBP, mehrere je Artikel. Das erste Bild ist das Vorschaubild.
- **Kategorien:** zum Gruppieren im Shop. Artikel ohne Kategorie erscheinen unter „Sonstiges“.
- **Einstellungen:** Titel, Einleitung, Versand- und Kontakthinweis sowie die E-Mail-Adresse, an die neue Bestellungen gemeldet werden.

## Bestellungen (Katalog-Server)

**Menü:** Shop → Bestellungen

Jede Bestellung eines Vereins läuft hier auf, mit Verein, Ansprechpartner, Lieferadresse und Positionen. Der Status lässt sich setzen: **Neu → Bestätigt → Geliefert → Verrechnet** (oder **Storniert**). Die Abrechnung erfolgt außerhalb von Syncopa per Rechnung.

---

## Shop je Verein schalten

**Menü:** Shop → Installationen · **Berechtigung:** Administrator (nur Katalog-Server)

Jede Syncopa, die den Shop öffnet, meldet sich hier mit Name, Adresse und Version. **Neue Vereine sind standardmäßig freigeschaltet.** Mit dem Schalter schaltest du den Shop für einen Verein ab oder wieder ein. Die Änderung greift beim Verein innerhalb einer Stunde (so lange gilt der zwischengespeicherte Status). Ein abgeschalteter Verein sieht den Shop-Menüpunkt nicht mehr, und Bestellungen von ihm werden abgewiesen.

## Shop für den Verein

**Menü:** Shop · **Berechtigung:** Administrator (Vorstand)

Der Vorstand sieht den Katalog, legt Artikel in den Warenkorb und bestellt. Verein, Ansprechpartner und E-Mail sind vorausgefüllt; Lieferadresse und eine Bemerkung werden ergänzt. Nach dem Absenden erscheint eine Bestellnummer; die Bestellung wird an den Katalog-Server übermittelt.

Der Katalog wird bis zu 30 Minuten zwischengespeichert, damit nicht jeder Seitenaufruf den Server abfragt. Ist der Server einmal nicht erreichbar, zeigt Syncopa den zuletzt bekannten Stand.

---

## Technik

- **Katalog-API:** `api/shop_katalog.php` liefert den Katalog als JSON (nur mit gültigem `SHOP_API_KEY`).
- **Bestell-Eingang:** `api/shop_bestellung.php` nimmt Bestellungen als JSON entgegen (nur von freigeschalteten Installationen).
- **Status/Freischaltung:** `api/shop_status.php` – eine Installation meldet sich und erfährt, ob der Shop für sie aktiv ist.
- **Bilder** liegen unter `uploads/shop/` und werden öffentlich ausgeliefert; ihre Adressen stehen im Katalog.
- Die Logik steckt in `classes/Shop.php` (Server, inkl. Installations-Registry) und `classes/ShopKatalog.php` (Verein, inkl. Freischalt-Abfrage).

> 💡 Dies ist die lokale Lösung: Ein Katalog-Server, an den sich die Vereine anbinden. Ein eigenes Portal mit Installations-Liste und zentraler Modulsteuerung ist als späterer Ausbau vorgesehen.
