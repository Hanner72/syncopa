<?php
// classes/ShopKatalog.php
// Client-Seite des Shops: holt den Katalog vom Katalog-Server (SHOP_ZENTRALE_URL) und schickt Bestellungen dorthin.
// Auf dem Katalog-Server selbst (SHOP_IST_ZENTRALE) wird direkt aus der Datenbank gelesen, ohne Umweg über HTTP.

class ShopKatalog {

    private const CACHE_MINUTEN = 30;

    public static function aktiv(): bool {
        return SHOP_IST_ZENTRALE || SHOP_ZENTRALE_URL !== '';
    }

    /** Vollständiger Katalog (aus der DB auf dem Server, sonst vom Server geholt und zwischengespeichert). */
    public static function katalog(bool $frisch = false): array {
        if (SHOP_IST_ZENTRALE) {
            return Shop::katalog(rtrim(SHOP_ZENTRALE_URL ?: '', '/') . '/uploads/shop');
        }
        $db = Database::getInstance();
        if (!$frisch) {
            $c = $db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'shop_katalog_cache'");
            $z = (int)($db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'shop_katalog_cache_zeit'")['wert'] ?? 0);
            if ($c && time() - $z < self::CACHE_MINUTEN * 60) {
                $k = json_decode((string)$c['wert'], true);
                if (is_array($k)) return $k;
            }
        }
        try {
            $k = self::holen();
            $db->execute("INSERT INTO einstellungen (schluessel, wert) VALUES ('shop_katalog_cache', ?) ON DUPLICATE KEY UPDATE wert = VALUES(wert)", [json_encode($k, JSON_UNESCAPED_UNICODE)]);
            $db->execute("INSERT INTO einstellungen (schluessel, wert) VALUES ('shop_katalog_cache_zeit', ?) ON DUPLICATE KEY UPDATE wert = VALUES(wert)", [time()]);
            return $k;
        } catch (\Throwable $e) {
            // Server nicht erreichbar → letzten bekannten Stand verwenden, sonst Fehler
            $c = $db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'shop_katalog_cache'");
            if ($c && ($k = json_decode((string)$c['wert'], true)) && is_array($k)) return $k;
            throw new RuntimeException('Der Shop ist gerade nicht erreichbar. Bitte später erneut versuchen.');
        }
    }

    private static function holen(): array {
        $url = rtrim(SHOP_ZENTRALE_URL, '/') . '/api/shop_katalog.php?key=' . urlencode(SHOP_API_KEY);
        $body = self::http('GET', $url);
        $d = json_decode($body, true);
        if (!is_array($d) || empty($d['success'])) throw new RuntimeException($d['error'] ?? 'Katalog konnte nicht geladen werden.');
        return $d['katalog'];
    }

    public static function artikel(array $katalog, int $id): ?array {
        foreach ($katalog['artikel'] ?? [] as $a) if ((int)$a['id'] === $id) return $a;
        return null;
    }
    public static function kategorieName(array $katalog, ?int $id): string {
        foreach ($katalog['kategorien'] ?? [] as $k) if ((int)$k['id'] === (int)$id) return $k['name'];
        return 'Sonstiges';
    }

    /** Bestellung an den Katalog-Server schicken. Gibt die Bestellnummer zurück. */
    public static function bestellungSenden(array $daten): string {
        if (SHOP_IST_ZENTRALE) {
            // Auf dem Server direkt anlegen
            return Shop::bestellungAnlegen($daten)['bestellnummer'];
        }
        $daten['key'] = SHOP_API_KEY;
        $body = self::http('POST', rtrim(SHOP_ZENTRALE_URL, '/') . '/api/shop_bestellung.php', json_encode($daten, JSON_UNESCAPED_UNICODE));
        $d = json_decode($body, true);
        if (!is_array($d) || empty($d['success'])) throw new RuntimeException($d['error'] ?? 'Die Bestellung konnte nicht übermittelt werden.');
        return (string)$d['bestellnummer'];
    }

    private static function http(string $methode, string $url, ?string $body = null): string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'syncopa-shop/1.0'];
            if ($methode === 'POST') { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = $body; $opt[CURLOPT_HTTPHEADER] = ['Content-Type: application/json']; }
            curl_setopt_array($ch, $opt);
            $res = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $fehler = curl_error($ch);
            curl_close($ch);
            if ($res === false) throw new RuntimeException('Verbindung fehlgeschlagen: ' . $fehler);
            if ($code >= 400 && $res === '') throw new RuntimeException('Server antwortete mit Fehler ' . $code);
            return (string)$res;
        }
        $ctx = stream_context_create(['http' => [
            'method' => $methode, 'timeout' => 8, 'ignore_errors' => true,
            'header' => $methode === 'POST' ? "Content-Type: application/json\r\n" : '',
            'content' => $body ?? '',
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) throw new RuntimeException('Verbindung zum Shop fehlgeschlagen.');
        return $res;
    }
}
