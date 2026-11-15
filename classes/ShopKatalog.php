<?php
// classes/ShopKatalog.php
// Client-Seite des Shops. Jede Installation fragt beim Standard-Katalog-Server (SHOP_SERVER_URL, in config.php
// per SHOP_ZENTRALE_URL überschreibbar), ob der Shop für sie freigeschaltet ist, und holt dort den Katalog.
// Auf dem Katalog-Server selbst (SHOP_IST_ZENTRALE) wird alles direkt aus der Datenbank gelesen.

class ShopKatalog {

    private const KATALOG_CACHE_MINUTEN = 30;
    private const STATUS_CACHE_MINUTEN  = 60;

    /** Adresse des Katalog-Servers (abweichend konfiguriert oder Standard). */
    public static function server(): string {
        return rtrim(SHOP_ZENTRALE_URL !== '' ? SHOP_ZENTRALE_URL : SHOP_SERVER_URL, '/');
    }

    public static function installationId(): string {
        return (string)(Database::getInstance()->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'installation_id'")['wert'] ?? '');
    }

    private static function einstellung(string $k): ?string {
        $r = Database::getInstance()->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = ?", [$k]);
        return $r ? (string)$r['wert'] : null;
    }
    private static function einstellungSetzen(string $k, string $v): void {
        Database::getInstance()->execute("INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?) ON DUPLICATE KEY UPDATE wert = VALUES(wert)", [$k, $v]);
    }

    // ---------------------------------------------------------------- Freischaltung (zentral gesteuert)
    /** Ohne Netzwerk: zuletzt bekannter Stand, ob der Shop freigeschaltet ist (Standard: ja). Für das Menü. */
    public static function aktivCache(): bool {
        if (SHOP_IST_ZENTRALE) return true;
        if (self::server() === '') return false;
        $c = self::einstellung('shop_status');
        if ($c && ($d = json_decode($c, true)) && is_array($d) && array_key_exists('aktiv', $d)) return (bool)$d['aktiv'];
        return true; // noch nicht gefragt → standardmäßig aktiv
    }

    /** Fragt bei Bedarf den Server (meldet diese Installation) und gibt ['aktiv'=>bool,'katalog_url'=>string] zurück. */
    public static function status(bool $frisch = false): array {
        if (SHOP_IST_ZENTRALE) return ['aktiv' => true, 'katalog_url' => self::server()];
        $c = self::einstellung('shop_status');
        $zeit = (int)(self::einstellung('shop_status_zeit') ?? 0);
        $cache = $c ? json_decode($c, true) : null;
        if (!$frisch && is_array($cache) && time() - $zeit < self::STATUS_CACHE_MINUTEN * 60) return $cache;
        try {
            $url = self::server() . '/api/shop_status.php?' . http_build_query([
                'key' => SHOP_API_KEY, 'id' => self::installationId(),
                'name' => (string)(self::einstellung('vereinsname') ?? ''), 'url' => BASE_URL, 'version' => APP_VERSION,
            ]);
            $d = json_decode(self::http('GET', $url), true);
            if (!is_array($d) || empty($d['success'])) throw new RuntimeException('Status unklar');
            $neu = ['aktiv' => !empty($d['shop_aktiv']), 'katalog_url' => (string)($d['katalog_url'] ?? self::server())];
        } catch (\Throwable $e) {
            // Server nicht erreichbar → letzten Stand behalten, sonst standardmäßig aktiv
            $neu = is_array($cache) ? $cache : ['aktiv' => true, 'katalog_url' => self::server()];
        }
        self::einstellungSetzen('shop_status', json_encode($neu));
        self::einstellungSetzen('shop_status_zeit', (string)time());
        return $neu;
    }

    public static function aktiv(bool $frisch = false): bool {
        return (bool)self::status($frisch)['aktiv'];
    }

    // ---------------------------------------------------------------- Katalog
    public static function katalog(bool $frisch = false): array {
        if (SHOP_IST_ZENTRALE) {
            return Shop::katalog(self::server() . '/uploads/shop');
        }
        $db = Database::getInstance();
        if (!$frisch) {
            $c = self::einstellung('shop_katalog_cache');
            $z = (int)(self::einstellung('shop_katalog_cache_zeit') ?? 0);
            if ($c && time() - $z < self::KATALOG_CACHE_MINUTEN * 60) {
                $k = json_decode($c, true);
                if (is_array($k)) return $k;
            }
        }
        try {
            $k = self::holen();
            self::einstellungSetzen('shop_katalog_cache', json_encode($k, JSON_UNESCAPED_UNICODE));
            self::einstellungSetzen('shop_katalog_cache_zeit', (string)time());
            return $k;
        } catch (\Throwable $e) {
            $c = self::einstellung('shop_katalog_cache');
            if ($c && ($k = json_decode($c, true)) && is_array($k)) return $k;
            throw new RuntimeException('Der Shop ist gerade nicht erreichbar. Bitte später erneut versuchen.');
        }
    }

    private static function holen(): array {
        $basis = self::status()['katalog_url'] ?: self::server();
        $url = rtrim($basis, '/') . '/api/shop_katalog.php?key=' . urlencode(SHOP_API_KEY);
        $d = json_decode(self::http('GET', $url), true);
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

    // ---------------------------------------------------------------- Bestellung
    public static function bestellungSenden(array $daten): string {
        if (SHOP_IST_ZENTRALE) {
            return Shop::bestellungAnlegen($daten)['bestellnummer'];
        }
        $daten['key'] = SHOP_API_KEY;
        $daten['installation_id'] = self::installationId();
        $basis = self::status()['katalog_url'] ?: self::server();
        $d = json_decode(self::http('POST', rtrim($basis, '/') . '/api/shop_bestellung.php', json_encode($daten, JSON_UNESCAPED_UNICODE)), true);
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
            $fehler = curl_error($ch);
            curl_close($ch);
            if ($res === false) throw new RuntimeException('Verbindung fehlgeschlagen: ' . $fehler);
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
