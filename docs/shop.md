# Shop (Vereinsbekleidung)

Über den Shop können Vereine Vereinsbekleidung und Zubehör bestellen. Der Katalog wird **zentral an einer Stelle** gepflegt; alle angebundenen Vereine sehen denselben Shop in ihrer eigenen Syncopa und bestellen per Rechnung.

## Zwei Rollen

| Rolle | config.php | Aufgabe |
|---|---|---|
| **Katalog-Server** | `SHOP_IST_ZENTRALE = true` | pflegt Artikel, Preise und Bilder; nimmt Bestellungen entgegen |
| **Verein** | `SHOP_ZENTRALE_URL = '…'` | holt den Katalog vom Server, zeigt den Shop, schickt Bestellungen dorthin |

Beide verwenden denselben geheimen `SHOP_API_KEY`. Eine Installation kann beides zugleich sein (dann sieht man auf ihr sowohl die Verwaltung als auch den Shop).

```php
// config.php auf dem Katalog-Server
define('SHOP_IST_ZENTRALE', true);
define('SHOP_ZENTRALE_URL', 'https://shop.deine-domain.at/syncopa'); // eigene öffentliche Adresse
define('SHOP_API_KEY',      'langer-zufälliger-schlüssel');

// config.php bei jedem Verein
define('SHOP_ZENTRALE_URL', 'https://shop.deine-domain.at/syncopa'); // Adresse des Servers
define('SHOP_API_KEY',      'langer-zufälliger-schlüssel');           // derselbe Schlüssel
```

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

## Shop für den Verein

**Menü:** Shop · **Berechtigung:** Administrator (Vorstand)

Der Vorstand sieht den Katalog, legt Artikel in den Warenkorb und bestellt. Verein, Ansprechpartner und E-Mail sind vorausgefüllt; Lieferadresse und eine Bemerkung werden ergänzt. Nach dem Absenden erscheint eine Bestellnummer; die Bestellung wird an den Katalog-Server übermittelt.

Der Katalog wird bis zu 30 Minuten zwischengespeichert, damit nicht jeder Seitenaufruf den Server abfragt. Ist der Server einmal nicht erreichbar, zeigt Syncopa den zuletzt bekannten Stand.

---

## Technik

- **Katalog-API:** `api/shop_katalog.php` liefert den Katalog als JSON (nur mit gültigem `SHOP_API_KEY`).
- **Bestell-Eingang:** `api/shop_bestellung.php` nimmt Bestellungen als JSON entgegen (nur mit Schlüssel).
- **Bilder** liegen unter `uploads/shop/` und werden öffentlich ausgeliefert; ihre Adressen stehen im Katalog.
- Die Logik steckt in `classes/Shop.php` (Server) und `classes/ShopKatalog.php` (Verein).

> 💡 Dies ist die lokale Lösung: Ein Katalog-Server, an den sich die Vereine anbinden. Ein eigenes Portal mit Installations-Liste und zentraler Modulsteuerung ist als späterer Ausbau vorgesehen.
