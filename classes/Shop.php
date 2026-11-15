<?php
// classes/Shop.php
// Shop für Vereinsbekleidung. Diese Installation ist – wenn SHOP_IST_ZENTRALE true ist – der Katalog-Server:
// Hier werden Kategorien, Artikel, Varianten (Größen) und Bilder gepflegt und über die API (api/shop_katalog.php)
// an die einzelnen Vereins-Installationen geliefert. Bestellungen der Vereine laufen hier als Eingang auf.

class Shop {

    public const BILD_TYPEN = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    public const STATUS = ['neu' => 'Neu', 'bestaetigt' => 'Bestätigt', 'geliefert' => 'Geliefert', 'verrechnet' => 'Verrechnet', 'storniert' => 'Storniert'];

    private static function db(): Database { return Database::getInstance(); }

    public static function euro($betrag): string {
        return number_format((float)$betrag, 2, ',', '.') . ' €';
    }

    // ---------------------------------------------------------------- Einstellungen (shop_* in einstellungen)
    public static function einstellung(string $schluessel, string $standard = ''): string {
        $r = self::db()->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = ?", ['shop_' . $schluessel]);
        return $r ? (string)$r['wert'] : $standard;
    }
    public static function einstellungSetzen(string $schluessel, string $wert): void {
        self::db()->execute(
            "INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?) ON DUPLICATE KEY UPDATE wert = VALUES(wert)",
            ['shop_' . $schluessel, $wert]
        );
    }

    // ---------------------------------------------------------------- Kategorien
    public static function kategorien(bool $nurAktive = false): array {
        return self::db()->fetchAll("SELECT * FROM shop_kategorien" . ($nurAktive ? " WHERE aktiv = 1" : "") . " ORDER BY sortierung, name");
    }
    public static function kategorie(int $id): ?array {
        return self::db()->fetchOne("SELECT * FROM shop_kategorien WHERE id = ?", [$id]) ?: null;
    }
    public static function kategorieSpeichern(array $d, ?int $id = null): int {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Bitte einen Namen für die Kategorie angeben.');
        $params = [$name, trim((string)($d['beschreibung'] ?? '')) ?: null, (int)($d['sortierung'] ?? 100), !empty($d['aktiv']) ? 1 : 0];
        if ($id) {
            self::db()->execute("UPDATE shop_kategorien SET name = ?, beschreibung = ?, sortierung = ?, aktiv = ? WHERE id = ?", array_merge($params, [$id]));
            return $id;
        }
        self::db()->execute("INSERT INTO shop_kategorien (name, beschreibung, sortierung, aktiv) VALUES (?, ?, ?, ?)", $params);
        return (int)self::db()->lastInsertId();
    }
    public static function kategorieLoeschen(int $id): void {
        self::db()->execute("UPDATE shop_artikel SET kategorie_id = NULL WHERE kategorie_id = ?", [$id]);
        self::db()->execute("DELETE FROM shop_kategorien WHERE id = ?", [$id]);
    }

    // ---------------------------------------------------------------- Artikel
    public static function artikelListe(bool $nurAktive = false): array {
        return self::db()->fetchAll(
            "SELECT a.*, k.name AS kategorie_name,
                    (SELECT COUNT(*) FROM shop_varianten v WHERE v.artikel_id = a.id) AS anzahl_varianten,
                    (SELECT dateiname FROM shop_bilder b WHERE b.artikel_id = a.id ORDER BY sortierung, id LIMIT 1) AS hauptbild
             FROM shop_artikel a
             LEFT JOIN shop_kategorien k ON k.id = a.kategorie_id"
            . ($nurAktive ? " WHERE a.aktiv = 1" : "") .
            " ORDER BY k.sortierung, k.name, a.sortierung, a.name"
        );
    }
    public static function artikel(int $id): ?array {
        return self::db()->fetchOne("SELECT * FROM shop_artikel WHERE id = ?", [$id]) ?: null;
    }
    public static function artikelSpeichern(array $d, ?int $id = null): int {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Bitte einen Namen für den Artikel angeben.');
        $preis = (float)str_replace(',', '.', (string)($d['preis'] ?? '0'));
        $params = [
            ($d['kategorie_id'] ?? '') !== '' ? (int)$d['kategorie_id'] : null,
            $name,
            trim((string)($d['beschreibung'] ?? '')) ?: null,
            trim((string)($d['artikelnummer'] ?? '')) ?: null,
            $preis,
            trim((string)($d['lieferzeit'] ?? '')) ?: null,
            !empty($d['aktiv']) ? 1 : 0,
            (int)($d['sortierung'] ?? 100),
        ];
        if ($id) {
            self::db()->execute(
                "UPDATE shop_artikel SET kategorie_id = ?, name = ?, beschreibung = ?, artikelnummer = ?, preis = ?, lieferzeit = ?, aktiv = ?, sortierung = ?, aktualisiert_am = NOW() WHERE id = ?",
                array_merge($params, [$id])
            );
            return $id;
        }
        self::db()->execute(
            "INSERT INTO shop_artikel (kategorie_id, name, beschreibung, artikelnummer, preis, lieferzeit, aktiv, sortierung, erstellt_am) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            $params
        );
        return (int)self::db()->lastInsertId();
    }
    public static function artikelLoeschen(int $id): void {
        foreach (self::bilder($id) as $b) @unlink(SHOP_DIR . DIRECTORY_SEPARATOR . $b['dateiname']);
        self::db()->execute("DELETE FROM shop_bilder WHERE artikel_id = ?", [$id]);
        self::db()->execute("DELETE FROM shop_varianten WHERE artikel_id = ?", [$id]);
        self::db()->execute("DELETE FROM shop_artikel WHERE id = ?", [$id]);
    }

    // ---------------------------------------------------------------- Varianten (Größen)
    public static function varianten(int $artikelId): array {
        return self::db()->fetchAll("SELECT * FROM shop_varianten WHERE artikel_id = ? ORDER BY sortierung, id", [$artikelId]);
    }
    /** Alle Varianten eines Artikels ersetzen. $zeilen = Liste von [bezeichnung, artikelnummer, preis, aktiv] */
    public static function variantenSetzen(int $artikelId, array $zeilen): void {
        $db = self::db();
        $db->execute("DELETE FROM shop_varianten WHERE artikel_id = ?", [$artikelId]);
        $pos = 0;
        foreach ($zeilen as $z) {
            $bez = trim((string)($z['bezeichnung'] ?? ''));
            if ($bez === '') continue;
            $preis = trim((string)($z['preis'] ?? ''));
            $db->execute(
                "INSERT INTO shop_varianten (artikel_id, bezeichnung, artikelnummer, preis, sortierung, aktiv) VALUES (?, ?, ?, ?, ?, ?)",
                [$artikelId, mb_substr($bez, 0, 80), trim((string)($z['artikelnummer'] ?? '')) ?: null,
                 $preis !== '' ? (float)str_replace(',', '.', $preis) : null, ++$pos * 10, empty($z['inaktiv']) ? 1 : 0]
            );
        }
    }
    /** Preis einer Variante (eigener Preis oder Artikelpreis) */
    public static function variantePreis(array $variante, array $artikel): float {
        return $variante['preis'] !== null ? (float)$variante['preis'] : (float)$artikel['preis'];
    }

    // ---------------------------------------------------------------- Bilder
    public static function bilder(int $artikelId): array {
        return self::db()->fetchAll("SELECT * FROM shop_bilder WHERE artikel_id = ? ORDER BY sortierung, id", [$artikelId]);
    }
    public static function bildHinzufuegen(int $artikelId, array $datei): void {
        if (($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
        if ($datei['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($datei['tmp_name'])) throw new RuntimeException('Das Bild konnte nicht hochgeladen werden.');
        if (($datei['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('Das Bild ist größer als 5 MB.');
        $endung = strtolower(pathinfo((string)$datei['name'], PATHINFO_EXTENSION));
        if (!isset(self::BILD_TYPEN[$endung])) throw new RuntimeException('Bitte ein Bild als JPG, PNG oder WEBP hochladen.');
        $typ = function_exists('mime_content_type') ? (string)mime_content_type($datei['tmp_name']) : self::BILD_TYPEN[$endung];
        if (!in_array($typ, array_values(self::BILD_TYPEN), true)) throw new RuntimeException('Die Datei ist kein gültiges Bild.');
        if (!is_dir(SHOP_DIR)) @mkdir(SHOP_DIR, 0755, true);
        $name = $artikelId . '_' . bin2hex(random_bytes(6)) . '.' . ($endung === 'jpeg' ? 'jpg' : $endung);
        if (!move_uploaded_file($datei['tmp_name'], SHOP_DIR . DIRECTORY_SEPARATOR . $name)) throw new RuntimeException('Das Bild konnte nicht gespeichert werden.');
        $max = (int)(self::db()->fetchOne("SELECT MAX(sortierung) m FROM shop_bilder WHERE artikel_id = ?", [$artikelId])['m'] ?? 0);
        self::db()->execute("INSERT INTO shop_bilder (artikel_id, dateiname, sortierung) VALUES (?, ?, ?)", [$artikelId, $name, $max + 10]);
    }
    public static function bildLoeschen(int $id): void {
        $b = self::db()->fetchOne("SELECT * FROM shop_bilder WHERE id = ?", [$id]);
        if (!$b) return;
        @unlink(SHOP_DIR . DIRECTORY_SEPARATOR . $b['dateiname']);
        self::db()->execute("DELETE FROM shop_bilder WHERE id = ?", [$id]);
    }

    // ---------------------------------------------------------------- Katalog für die API
    /** Vollständiger Katalog als Array (nur aktive Kategorien/Artikel) für die Auslieferung an die Vereine */
    public static function katalog(string $bildBasis = ''): array {
        $bildBasis = rtrim($bildBasis, '/');
        $kategorien = array_map(fn($k) => ['id' => (int)$k['id'], 'name' => $k['name'], 'beschreibung' => $k['beschreibung'], 'sortierung' => (int)$k['sortierung']], self::kategorien(true));
        $artikel = [];
        foreach (self::artikelListe(true) as $a) {
            $artikel[] = [
                'id'            => (int)$a['id'],
                'kategorie_id'  => $a['kategorie_id'] !== null ? (int)$a['kategorie_id'] : null,
                'name'          => $a['name'],
                'beschreibung'  => $a['beschreibung'],
                'artikelnummer' => $a['artikelnummer'],
                'preis'         => (float)$a['preis'],
                'lieferzeit'    => $a['lieferzeit'],
                'sortierung'    => (int)$a['sortierung'],
                'varianten'     => array_map(fn($v) => [
                    'id' => (int)$v['id'], 'bezeichnung' => $v['bezeichnung'], 'artikelnummer' => $v['artikelnummer'],
                    'preis' => $v['preis'] !== null ? (float)$v['preis'] : (float)$a['preis'],
                ], array_filter(self::varianten((int)$a['id']), fn($v) => (int)$v['aktiv'] === 1)),
                'bilder'        => array_map(fn($b) => $bildBasis !== '' ? $bildBasis . '/' . rawurlencode($b['dateiname']) : $b['dateiname'], self::bilder((int)$a['id'])),
            ];
        }
        return [
            'stand'      => date('c'),
            'waehrung'   => 'EUR',
            'info'       => [
                'titel'      => self::einstellung('titel', 'Vereins-Shop'),
                'einleitung' => self::einstellung('einleitung', ''),
                'versand'    => self::einstellung('versand', ''),
                'kontakt'    => self::einstellung('kontakt', ''),
            ],
            'kategorien' => $kategorien,
            'artikel'    => $artikel,
        ];
    }

    // ---------------------------------------------------------------- Bestellungen (Eingang auf dem Katalog-Server)
    public static function bestellungen(string $status = ''): array {
        $sql = "SELECT b.*, (SELECT COUNT(*) FROM shop_bestellpositionen p WHERE p.bestellung_id = b.id) AS anzahl_positionen FROM shop_bestellungen b";
        $params = [];
        if ($status !== '' && isset(self::STATUS[$status])) { $sql .= " WHERE b.status = ?"; $params[] = $status; }
        return self::db()->fetchAll($sql . " ORDER BY b.erstellt_am DESC", $params);
    }
    public static function bestellung(int $id): ?array {
        $b = self::db()->fetchOne("SELECT * FROM shop_bestellungen WHERE id = ?", [$id]);
        if (!$b) return null;
        $b['positionen'] = self::db()->fetchAll("SELECT * FROM shop_bestellpositionen WHERE bestellung_id = ? ORDER BY id", [$id]);
        return $b;
    }
    public static function bestellStatusSetzen(int $id, string $status): void {
        if (!isset(self::STATUS[$status])) throw new RuntimeException('Unbekannter Status.');
        self::db()->execute("UPDATE shop_bestellungen SET status = ? WHERE id = ?", [$status, $id]);
    }

    /** Eine von einem Verein übermittelte Bestellung speichern. Gibt [id, bestellnummer] zurück. */
    public static function bestellungAnlegen(array $d): array {
        $positionen = [];
        foreach ((array)($d['positionen'] ?? []) as $p) {
            $name = trim((string)($p['artikel_name'] ?? ''));
            $menge = max(1, (int)($p['menge'] ?? 1));
            if ($name === '') continue;
            $positionen[] = [
                'artikel_name'  => mb_substr($name, 0, 200),
                'variante'      => mb_substr(trim((string)($p['variante'] ?? '')), 0, 100) ?: null,
                'artikelnummer' => mb_substr(trim((string)($p['artikelnummer'] ?? '')), 0, 50) ?: null,
                'einzelpreis'   => round((float)($p['einzelpreis'] ?? 0), 2),
                'menge'         => $menge,
            ];
        }
        if (!$positionen) throw new RuntimeException('Die Bestellung enthält keine Positionen.');
        $summe = 0;
        foreach ($positionen as $p) $summe += $p['einzelpreis'] * $p['menge'];

        $db = self::db();
        // eindeutige Bestellnummer (mit ein paar Versuchen gegen Kollision)
        for ($i = 0; $i < 5; $i++) {
            $nr = 'B-' . date('ymd') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            if (!$db->fetchOne("SELECT id FROM shop_bestellungen WHERE bestellnummer = ?", [$nr])) break;
        }
        $db->beginTransaction();
        try {
            $db->execute(
                "INSERT INTO shop_bestellungen (bestellnummer, verein_name, verein_url, installation_id, besteller_name, besteller_email, besteller_telefon, lieferadresse, bemerkung, summe, status, erstellt_am)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'neu', NOW())",
                [
                    $nr,
                    mb_substr(trim((string)($d['verein_name'] ?? '')), 0, 150) ?: null,
                    mb_substr(trim((string)($d['verein_url'] ?? '')), 0, 255) ?: null,
                    mb_substr(trim((string)($d['installation_id'] ?? '')), 0, 64) ?: null,
                    mb_substr(trim((string)($d['besteller_name'] ?? '')), 0, 150) ?: null,
                    mb_substr(trim((string)($d['besteller_email'] ?? '')), 0, 150) ?: null,
                    mb_substr(trim((string)($d['besteller_telefon'] ?? '')), 0, 50) ?: null,
                    trim((string)($d['lieferadresse'] ?? '')) ?: null,
                    trim((string)($d['bemerkung'] ?? '')) ?: null,
                    round($summe, 2),
                ]
            );
            $id = (int)$db->lastInsertId();
            foreach ($positionen as $p) {
                $db->execute(
                    "INSERT INTO shop_bestellpositionen (bestellung_id, artikel_name, variante, artikelnummer, einzelpreis, menge) VALUES (?, ?, ?, ?, ?, ?)",
                    [$id, $p['artikel_name'], $p['variante'], $p['artikelnummer'], $p['einzelpreis'], $p['menge']]
                );
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        return ['id' => $id, 'bestellnummer' => $nr, 'summe' => round($summe, 2)];
    }
}
