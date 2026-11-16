<?php
// classes/Shop.php
// Shop für Vereinsbekleidung. Diese Installation ist – wenn SHOP_IST_ZENTRALE true ist – der Katalog-Server:
// Hier werden Kategorien, Artikel, Varianten (Größen) und Bilder gepflegt und über die API (api/shop_katalog.php)
// an die einzelnen Vereins-Installationen geliefert. Bestellungen der Vereine laufen hier als Eingang auf.

class Shop {

    public const BILD_TYPEN = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    public const BILD_MAX_BYTES = 16 * 1024 * 1024;   // größte Datei beim Hochladen
    public const BILD_MAX_KANTE = 1600;               // längste Bildseite nach dem Verkleinern (Pixel)
    public const STATUS = ['neu' => 'Neu', 'bestaetigt' => 'Bestätigt', 'geliefert' => 'Geliefert', 'verrechnet' => 'Verrechnet', 'storniert' => 'Storniert'];

    private static function db(): Database { return Database::getInstance(); }

    /** Wie viel der Server pro Speichern annimmt (post_max_size) in Bytes – für die Prüfung im Browser. */
    public static function uploadGrenzeBytes(): int {
        $w = trim((string)ini_get('post_max_size'));
        $n = (int)$w;
        $e = strtolower(substr($w, -1));
        return match ($e) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    }

    public static function euro($betrag): string {
        return number_format((float)$betrag, 2, ',', '.') . ' €';
    }

    /** Versandkosten für eine Warenkorbsumme (0, wenn kein Versand gesetzt oder Freigrenze erreicht). */
    public static function versandkosten(float $warenSumme): float {
        $versand = (float)str_replace(',', '.', self::einstellung('versandkosten', '0'));
        if ($versand <= 0) return 0.0;
        $freiAb = (float)str_replace(',', '.', self::einstellung('versandkosten_frei_ab', '0'));
        if ($freiAb > 0 && $warenSumme >= $freiAb) return 0.0;
        return round($versand, 2);
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
    /** Kategorien als Baum-Liste: jede Hauptkategorie, direkt gefolgt von ihren Unterkategorien (Feld 'ebene' = 0/1). */
    public static function kategorienBaum(bool $nurAktive = false): array {
        $alle = self::kategorien($nurAktive);
        $ids = array_flip(array_map(fn($k) => (int)$k['id'], $alle));
        $kinder = [];
        foreach ($alle as $k) { $p = (int)($k['parent_id'] ?? 0); if ($p && isset($ids[$p])) $kinder[$p][] = $k; }
        $out = [];
        foreach ($alle as $k) {
            $p = (int)($k['parent_id'] ?? 0);
            if ($p && isset($ids[$p])) continue;           // wird unter der Hauptkategorie ausgegeben
            $k['ebene'] = 0; $out[] = $k;
            foreach ($kinder[(int)$k['id']] ?? [] as $c) { $c['ebene'] = 1; $out[] = $c; }
        }
        return $out;
    }
    public static function kategorie(int $id): ?array {
        return self::db()->fetchOne("SELECT * FROM shop_kategorien WHERE id = ?", [$id]) ?: null;
    }
    public static function kategorieSpeichern(array $d, ?int $id = null): int {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Bitte einen Namen für die Kategorie angeben.');
        $parent = (int)($d['parent_id'] ?? 0) ?: null;
        if ($parent) {
            if ($id && $parent === $id) throw new RuntimeException('Eine Kategorie kann nicht ihre eigene Oberkategorie sein.');
            $p = self::kategorie($parent);
            if (!$p) $parent = null;
            elseif (!empty($p['parent_id'])) throw new RuntimeException('Unterkategorien können keine weiteren Unterkategorien haben (nur eine Ebene).');
            if ($parent && $id && self::db()->fetchOne("SELECT id FROM shop_kategorien WHERE parent_id = ? LIMIT 1", [$id])) {
                throw new RuntimeException('Diese Kategorie hat selbst Unterkategorien und kann daher keiner anderen untergeordnet werden.');
            }
        }
        $params = [$parent, $name, trim((string)($d['beschreibung'] ?? '')) ?: null, (int)($d['sortierung'] ?? 100), !empty($d['aktiv']) ? 1 : 0];
        if ($id) {
            self::db()->execute("UPDATE shop_kategorien SET parent_id = ?, name = ?, beschreibung = ?, sortierung = ?, aktiv = ? WHERE id = ?", array_merge($params, [$id]));
            return $id;
        }
        self::db()->execute("INSERT INTO shop_kategorien (parent_id, name, beschreibung, sortierung, aktiv) VALUES (?, ?, ?, ?, ?)", $params);
        return (int)self::db()->lastInsertId();
    }
    /**
     * Reihenfolge und Zuordnung aller Kategorien auf einmal setzen (Drag & Drop in der Verwaltung).
     * $struktur = [['id' => 3, 'kinder' => [9, 10]], ['id' => 5, 'kinder' => []], …] in der gewünschten Reihenfolge.
     * Nur eine Ebene: Unterkategorien werden als reine IDs übergeben und können selbst keine Kinder haben.
     */
    public static function kategorienAnordnen(array $struktur): void {
        $vorhanden = array_flip(array_map(fn($k) => (int)$k['id'], self::kategorien()));
        $gesehen = []; $zeilen = [];
        foreach (array_values($struktur) as $i => $e) {
            $id = (int)($e['id'] ?? 0);
            if (!isset($vorhanden[$id]) || isset($gesehen[$id])) throw new RuntimeException('Ungültige Kategorienliste. Bitte die Seite neu laden.');
            $gesehen[$id] = true;
            $zeilen[] = [null, ($i + 1) * 10, $id];
            foreach (array_values((array)($e['kinder'] ?? [])) as $j => $c) {
                $c = (int)$c;
                if (!isset($vorhanden[$c]) || isset($gesehen[$c])) throw new RuntimeException('Ungültige Kategorienliste. Bitte die Seite neu laden.');
                $gesehen[$c] = true;
                $zeilen[] = [$id, ($j + 1) * 10, $c];
            }
        }
        if (count($gesehen) !== count($vorhanden)) throw new RuntimeException('Die Kategorienliste ist nicht mehr aktuell. Bitte die Seite neu laden.');
        $db = self::db();
        $db->beginTransaction();
        try {
            foreach ($zeilen as $z) $db->execute("UPDATE shop_kategorien SET parent_id = ?, sortierung = ? WHERE id = ?", $z);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
    public static function kategorieLoeschen(int $id): void {
        self::db()->execute("UPDATE shop_artikel SET kategorie_id = NULL WHERE kategorie_id = ?", [$id]);
        self::db()->execute("UPDATE shop_kategorien SET parent_id = NULL WHERE parent_id = ?", [$id]);
        self::db()->execute("UPDATE shop_angebote SET kategorie_id = NULL WHERE kategorie_id = ?", [$id]);
        self::db()->execute("DELETE FROM shop_kategorien WHERE id = ?", [$id]);
    }

    // ---------------------------------------------------------------- Artikel
    public static function artikelListe(bool $nurAktive = false): array {
        return self::db()->fetchAll(
            "SELECT a.*, k.name AS kategorie_name, pk.name AS oberkategorie_name,
                    (SELECT COUNT(*) FROM shop_varianten v WHERE v.artikel_id = a.id) AS anzahl_varianten,
                    (SELECT dateiname FROM shop_bilder b WHERE b.artikel_id = a.id ORDER BY sortierung, id LIMIT 1) AS hauptbild
             FROM shop_artikel a
             LEFT JOIN shop_kategorien k ON k.id = a.kategorie_id
             LEFT JOIN shop_kategorien pk ON pk.id = k.parent_id"
            . ($nurAktive ? " WHERE a.aktiv = 1" : "") .
            // nach Kategorie-Baum sortieren: Hauptkategorie, dann ihre Unterkategorien, dann Artikel
            " ORDER BY a.kategorie_id IS NULL, COALESCE(pk.sortierung, k.sortierung), COALESCE(pk.name, k.name),
                       k.parent_id IS NOT NULL, k.sortierung, k.name, a.sortierung, a.name"
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
            !empty($d['aktion']) ? 1 : 0,
            mb_substr(trim((string)($d['aktion_text'] ?? '')), 0, 40) ?: null,
            !empty($d['ausverkauft']) ? 1 : 0,
            (int)($d['sortierung'] ?? 100),
        ];
        if ($id) {
            self::db()->execute(
                "UPDATE shop_artikel SET kategorie_id = ?, name = ?, beschreibung = ?, artikelnummer = ?, preis = ?, lieferzeit = ?, aktiv = ?, aktion = ?, aktion_text = ?, ausverkauft = ?, sortierung = ?, aktualisiert_am = NOW() WHERE id = ?",
                array_merge($params, [$id])
            );
            return $id;
        }
        self::db()->execute(
            "INSERT INTO shop_artikel (kategorie_id, name, beschreibung, artikelnummer, preis, lieferzeit, aktiv, aktion, aktion_text, ausverkauft, sortierung, erstellt_am) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
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
    /** Hochgeladenes Bild prüfen und unter SHOP_DIR speichern. Gibt den Dateinamen zurück, oder null, wenn keine Datei gewählt wurde. */
    private static function bildDateiSpeichern(array $datei, string $prefix): ?string {
        if (($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if ($datei['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($datei['tmp_name'])) throw new RuntimeException('Das Bild konnte nicht hochgeladen werden.');
        if (($datei['size'] ?? 0) > self::BILD_MAX_BYTES) throw new RuntimeException('Das Bild ist größer als 16 MB.');
        $endung = strtolower(pathinfo((string)$datei['name'], PATHINFO_EXTENSION));
        if (!isset(self::BILD_TYPEN[$endung])) throw new RuntimeException('Bitte ein Bild als JPG, PNG oder WEBP hochladen.');
        $typ = function_exists('mime_content_type') ? (string)mime_content_type($datei['tmp_name']) : self::BILD_TYPEN[$endung];
        if (!in_array($typ, array_values(self::BILD_TYPEN), true)) throw new RuntimeException('Die Datei ist kein gültiges Bild.');
        if (!is_dir(SHOP_DIR)) @mkdir(SHOP_DIR, 02775, true);
        if (!is_writable(SHOP_DIR)) throw new RuntimeException('Der Bilderordner (uploads/shop) ist für den Webserver nicht beschreibbar. Bitte die Ordnerrechte prüfen.');
        $basis = $prefix . '_' . bin2hex(random_bytes(6));
        // Verkleinern/komprimieren; klappt das nicht (z. B. GD fehlt), wird das Original gespeichert
        $ext = self::bildAufbereiten($datei['tmp_name'], array_search($typ, self::BILD_TYPEN, true) ?: $endung, SHOP_DIR . DIRECTORY_SEPARATOR . $basis);
        if ($ext === null) {
            $ext = $endung === 'jpeg' ? 'jpg' : $endung;
            if (!move_uploaded_file($datei['tmp_name'], SHOP_DIR . DIRECTORY_SEPARATOR . $basis . '.' . $ext)) throw new RuntimeException('Das Bild konnte nicht gespeichert werden.');
        }
        return $basis . '.' . $ext;
    }

    /**
     * Bild für den Shop aufbereiten: auf höchstens BILD_MAX_KANTE Pixel verkleinern, Handy-Drehung (EXIF) korrigieren
     * und komprimiert unter $zielOhneEndung.<ext> speichern. PNG ohne Transparenz wird zu JPG.
     * Gibt die Endung des Ergebnisses zurück – oder null, wenn das Original unverändert übernommen werden soll.
     */
    private static function bildAufbereiten(string $quelle, string $typ, string $zielOhneEndung): ?string {
        if (!function_exists('imagecreatetruecolor')) return null;
        $info = @getimagesize($quelle);
        if (!$info || $info[0] < 1 || $info[1] < 1) return null;
        [$w, $h] = $info;
        if ($w * $h > 100000000) throw new RuntimeException('Das Bild hat zu viele Pixel (über 100 Megapixel). Bitte vorher verkleinern.');
        // Speicher für große Fotos (GD braucht ca. 5 Byte je Pixel)
        $grenze = ini_get('memory_limit');
        if ($grenze !== '-1' && (int)$grenze > 0 && (int)$grenze < 768 && stripos((string)$grenze, 'G') === false) @ini_set('memory_limit', '768M');

        $typ = $typ === 'jpeg' ? 'jpg' : $typ;
        $bild = match ($typ) {
            'jpg'  => @imagecreatefromjpeg($quelle),
            'png'  => @imagecreatefrompng($quelle),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($quelle) : false,
            default => false,
        };
        if (!$bild) return null;

        // Handyfotos: Ausrichtung aus EXIF übernehmen
        $gedreht = false;
        if ($typ === 'jpg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($quelle);
            $winkel = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
            if ($winkel) {
                $neu = imagerotate($bild, $winkel, 0);
                if ($neu) { imagedestroy($bild); $bild = $neu; $gedreht = true; $w = imagesx($bild); $h = imagesy($bild); }
            }
        }

        // Transparenz? (PNG-Farbtyp 4/6 oder tRNS-Block; WebP bleibt WebP)
        $alpha = false;
        if ($typ === 'png') {
            $kopf = (string)@file_get_contents($quelle, false, null, 0, 4096);
            $alpha = strlen($kopf) > 25 && (in_array(ord($kopf[25]), [4, 6], true) || strpos($kopf, 'tRNS') !== false);
        }
        $aus = $typ === 'png' ? ($alpha ? 'png' : 'jpg') : $typ;

        $faktor = min(1, self::BILD_MAX_KANTE / max($w, $h));
        // Kleines, richtig gedrehtes Bild im selben Format: Original behalten (keine unnötige Qualitätseinbuße)
        if ($faktor >= 1 && !$gedreht && $aus === $typ && filesize($quelle) <= 1.5 * 1024 * 1024) { imagedestroy($bild); return null; }

        $nw = max(1, (int)round($w * $faktor)); $nh = max(1, (int)round($h * $faktor));
        $ziel = imagecreatetruecolor($nw, $nh);
        if ($aus === 'jpg') {
            imagefill($ziel, 0, 0, imagecolorallocate($ziel, 255, 255, 255));   // transparente Stellen werden weiß
        } else {
            imagealphablending($ziel, false); imagesavealpha($ziel, true);
            imagefill($ziel, 0, 0, imagecolorallocatealpha($ziel, 0, 0, 0, 127));
        }
        imagecopyresampled($ziel, $bild, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($bild);

        $datei = $zielOhneEndung . '.' . $aus;
        if ($aus === 'jpg') imageinterlace($ziel, true);   // progressives JPG: lädt im Browser schneller sichtbar
        $ok = match ($aus) {
            'jpg'  => imagejpeg($ziel, $datei, 85),
            'png'  => imagepng($ziel, $datei, 6),
            'webp' => imagewebp($ziel, $datei, 85),
        };
        imagedestroy($ziel);
        if (!$ok) { @unlink($datei); return null; }
        @chmod($datei, 0644);
        return $aus;
    }
    public static function bildHinzufuegen(int $artikelId, array $datei): void {
        $name = self::bildDateiSpeichern($datei, (string)$artikelId);
        if ($name === null) return;
        $max = (int)(self::db()->fetchOne("SELECT MAX(sortierung) m FROM shop_bilder WHERE artikel_id = ?", [$artikelId])['m'] ?? 0);
        self::db()->execute("INSERT INTO shop_bilder (artikel_id, dateiname, sortierung) VALUES (?, ?, ?)", [$artikelId, $name, $max + 10]);
    }
    /** Bild an die erste Stelle setzen (Hauptbild in der Übersicht). */
    public static function bildAlsHauptbild(int $id): void {
        $b = self::db()->fetchOne("SELECT * FROM shop_bilder WHERE id = ?", [$id]);
        if (!$b) return;
        $min = (int)(self::db()->fetchOne("SELECT MIN(sortierung) m FROM shop_bilder WHERE artikel_id = ?", [$b['artikel_id']])['m'] ?? 0);
        self::db()->execute("UPDATE shop_bilder SET sortierung = ? WHERE id = ?", [$min - 10, $id]);
    }
    public static function bildLoeschen(int $id): void {
        $b = self::db()->fetchOne("SELECT * FROM shop_bilder WHERE id = ?", [$id]);
        if (!$b) return;
        @unlink(SHOP_DIR . DIRECTORY_SEPARATOR . $b['dateiname']);
        self::db()->execute("DELETE FROM shop_bilder WHERE id = ?", [$id]);
    }

    // ---------------------------------------------------------------- Angebote (Karten auf der Shop-Startseite)
    public static function angebote(bool $nurAktive = false): array {
        return self::db()->fetchAll("SELECT * FROM shop_angebote" . ($nurAktive ? " WHERE aktiv = 1" : "") . " ORDER BY sortierung, id");
    }
    public static function angebot(int $id): ?array {
        return self::db()->fetchOne("SELECT * FROM shop_angebote WHERE id = ?", [$id]) ?: null;
    }
    public static function angebotSpeichern(array $d, ?int $id = null, ?array $datei = null): int {
        $titel = trim((string)($d['titel'] ?? ''));
        if ($titel === '') throw new RuntimeException('Bitte einen Titel für das Angebot angeben.');
        $params = [
            mb_substr($titel, 0, 150),
            trim((string)($d['beschreibung'] ?? '')) ?: null,
            trim((string)($d['punkte'] ?? '')) ?: null,
            ($d['kategorie_id'] ?? '') !== '' ? (int)$d['kategorie_id'] : null,
            (int)($d['sortierung'] ?? 100),
            !empty($d['aktiv']) ? 1 : 0,
        ];
        if ($id) {
            self::db()->execute("UPDATE shop_angebote SET titel = ?, beschreibung = ?, punkte = ?, kategorie_id = ?, sortierung = ?, aktiv = ? WHERE id = ?", array_merge($params, [$id]));
        } else {
            self::db()->execute("INSERT INTO shop_angebote (titel, beschreibung, punkte, kategorie_id, sortierung, aktiv, erstellt_am) VALUES (?, ?, ?, ?, ?, ?, NOW())", $params);
            $id = (int)self::db()->lastInsertId();
        }
        if ($datei) {
            $name = self::bildDateiSpeichern($datei, 'angebot' . $id);
            if ($name !== null) {
                $alt = self::angebot($id)['bild'] ?? null;
                if ($alt) @unlink(SHOP_DIR . DIRECTORY_SEPARATOR . $alt);
                self::db()->execute("UPDATE shop_angebote SET bild = ? WHERE id = ?", [$name, $id]);
            }
        }
        return $id;
    }
    public static function angebotLoeschen(int $id): void {
        $g = self::angebot($id);
        if ($g && $g['bild']) @unlink(SHOP_DIR . DIRECTORY_SEPARATOR . $g['bild']);
        self::db()->execute("DELETE FROM shop_angebote WHERE id = ?", [$id]);
    }
    /** Punkte-Text (eine Zeile je Punkt, optional "Text | https://…") in [text, url] zerlegen. Nur http(s)-Links. */
    public static function punkteParsen(?string $text): array {
        $out = [];
        foreach (preg_split('/\R/', (string)$text) as $z) {
            $z = trim($z);
            if ($z === '') continue;
            $url = null;
            if (strpos($z, '|') !== false) {
                [$z, $u] = array_map('trim', explode('|', $z, 2));
                if (preg_match('#^https?://#i', $u)) $url = $u;
            }
            if ($z !== '') $out[] = ['text' => $z, 'url' => $url];
        }
        return $out;
    }

    // ---------------------------------------------------------------- Katalog für die API
    /** Vollständiger Katalog als Array (nur aktive Kategorien/Artikel) für die Auslieferung an die Vereine */
    public static function katalog(string $bildBasis = ''): array {
        $bildBasis = rtrim($bildBasis, '/');
        $kategorien = array_map(fn($k) => ['id' => (int)$k['id'], 'parent_id' => !empty($k['parent_id']) ? (int)$k['parent_id'] : null,
            'name' => $k['name'], 'beschreibung' => $k['beschreibung'], 'sortierung' => (int)$k['sortierung']], self::kategorienBaum(true));
        $angebote = array_map(fn($g) => [
            'id' => (int)$g['id'], 'titel' => $g['titel'], 'beschreibung' => $g['beschreibung'],
            'punkte' => self::punkteParsen($g['punkte']),
            'kategorie_id' => $g['kategorie_id'] !== null ? (int)$g['kategorie_id'] : null,
            'bild' => $g['bild'] ? ($bildBasis !== '' ? $bildBasis . '/' . rawurlencode($g['bild']) : $g['bild']) : null,
        ], self::angebote(true));
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
                'aktion'        => (int)($a['aktion'] ?? 0) === 1,
                'aktion_text'   => ($a['aktion_text'] ?? '') !== '' ? $a['aktion_text'] : null,
                'ausverkauft'   => (int)($a['ausverkauft'] ?? 0) === 1,
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
                'titel'                 => self::einstellung('titel', 'Vereins-Shop'),
                'einleitung'            => self::einstellung('einleitung', ''),
                'versand'               => self::einstellung('versand', ''),
                'kontakt'               => self::einstellung('kontakt', ''),
                'versandkosten'         => (float)str_replace(',', '.', self::einstellung('versandkosten', '0')),
                'versandkosten_frei_ab' => (float)str_replace(',', '.', self::einstellung('versandkosten_frei_ab', '0')),
            ],
            'kategorien' => $kategorien,
            'angebote'   => $angebote,
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

    // ---------------------------------------------------------------- Angebundene Installationen (Registry)
    public static function installationen(): array {
        return self::db()->fetchAll("SELECT * FROM shop_installationen ORDER BY shop_aktiv DESC, letzte_meldung DESC, name");
    }
    /** Meldung einer Vereins-Installation verbuchen; neue sind standardmäßig aktiv. Gibt den Datensatz zurück. */
    public static function installationMelden(string $installationId, array $d): array {
        $db = self::db();
        $vorhanden = $db->fetchOne("SELECT * FROM shop_installationen WHERE installation_id = ?", [$installationId]);
        $ort = mb_substr(trim((string)($d['ort'] ?? '')), 0, 100);
        if ($vorhanden) {
            $db->execute("UPDATE shop_installationen SET name = ?, ort = ?, url = ?, version = ?, letzte_meldung = NOW() WHERE installation_id = ?",
                [mb_substr(trim((string)($d['name'] ?? '')), 0, 150) ?: $vorhanden['name'],
                 $ort ?: ($vorhanden['ort'] ?? null),
                 mb_substr(trim((string)($d['url'] ?? '')), 0, 255) ?: $vorhanden['url'],
                 mb_substr(trim((string)($d['version'] ?? '')), 0, 20) ?: $vorhanden['version'], $installationId]);
        } else {
            $db->execute("INSERT INTO shop_installationen (installation_id, name, ort, url, version, shop_aktiv, erstellt_am, letzte_meldung) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())",
                [$installationId, mb_substr(trim((string)($d['name'] ?? '')), 0, 150) ?: null, $ort ?: null,
                 mb_substr(trim((string)($d['url'] ?? '')), 0, 255) ?: null, mb_substr(trim((string)($d['version'] ?? '')), 0, 20) ?: null]);
        }
        return $db->fetchOne("SELECT * FROM shop_installationen WHERE installation_id = ?", [$installationId]);
    }
    /** Selbst vergebener Anzeigename einer Installation (leer = gemeldeten Namen verwenden). */
    public static function installationNameSetzen(int $id, string $name): void {
        self::db()->execute("UPDATE shop_installationen SET anzeigename = ? WHERE id = ?", [mb_substr(trim($name), 0, 150) ?: null, $id]);
    }
    /** Anzeigename: selbst vergeben → vom Verein gemeldet → Adresse ohne https:// → null */
    public static function installationAnzeige(array $i): ?string {
        foreach ([$i['anzeigename'] ?? '', $i['name'] ?? ''] as $n) if (trim((string)$n) !== '') return trim((string)$n);
        $host = parse_url((string)($i['url'] ?? ''), PHP_URL_HOST);
        return $host ?: null;
    }
    public static function installationAktivSetzen(int $id, bool $aktiv): void {
        self::db()->execute("UPDATE shop_installationen SET shop_aktiv = ? WHERE id = ?", [$aktiv ? 1 : 0, $id]);
    }
    /** Ist der Shop für diese Installation freigeschaltet? Unbekannte Installationen gelten als aktiv. */
    public static function installationAktiv(string $installationId): bool {
        if ($installationId === '') return true;
        $r = self::db()->fetchOne("SELECT shop_aktiv FROM shop_installationen WHERE installation_id = ?", [$installationId]);
        return $r ? (int)$r['shop_aktiv'] === 1 : true;
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
        $warenSumme = 0;
        foreach ($positionen as $p) $warenSumme += $p['einzelpreis'] * $p['menge'];
        $versand = self::versandkosten((float)$warenSumme);  // serverseitig aus eigenen Einstellungen
        $summe   = round($warenSumme + $versand, 2);

        $db = self::db();
        // eindeutige Bestellnummer (mit ein paar Versuchen gegen Kollision)
        for ($i = 0; $i < 5; $i++) {
            $nr = 'B-' . date('ymd') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            if (!$db->fetchOne("SELECT id FROM shop_bestellungen WHERE bestellnummer = ?", [$nr])) break;
        }
        $db->beginTransaction();
        try {
            $db->execute(
                "INSERT INTO shop_bestellungen (bestellnummer, verein_name, verein_url, installation_id, besteller_name, besteller_email, besteller_telefon, lieferadresse, bemerkung, versandkosten, summe, status, erstellt_am)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'neu', NOW())",
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
                    $versand,
                    $summe,
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
        return ['id' => $id, 'bestellnummer' => $nr, 'versandkosten' => $versand, 'summe' => $summe];
    }
}
