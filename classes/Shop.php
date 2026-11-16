<?php
// classes/Shop.php
// Shop für Vereinsbekleidung. Diese Installation ist – wenn SHOP_IST_ZENTRALE true ist – der Katalog-Server:
// Hier werden Kategorien, Artikel, Varianten (Größen) und Bilder gepflegt und über die API (api/shop_katalog.php)
// an die einzelnen Vereins-Installationen geliefert. Bestellungen der Vereine laufen hier als Eingang auf.

class Shop {

    public const BILD_TYPEN = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    public const BILD_MAX_BYTES = 16 * 1024 * 1024;   // größte Datei beim Hochladen
    public const BILD_MAX_KANTE = 1600;               // längste Bildseite nach dem Verkleinern (Pixel)

    // Veredelung
    public const VEREDELUNG_ARTEN = ['stickerei' => 'Stickerei', 'druck' => 'Druck'];
    public const VEREDELUNG_POSITIONEN = [
        'schulter_rechts' => 'Rechte Schulter', 'schulter_links' => 'Linke Schulter',
        'brust_links' => 'Linke Brust', 'brust_rechts' => 'Rechte Brust', 'ruecken' => 'Rücken',
    ];
    /** Erlaubte Dateien für Logos/Schriftzüge der Besteller (bewusst ohne SVG – kann Skripte enthalten) */
    public const VORLAGE_TYPEN = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
    public const STATUS = [
        'neu' => 'Eingegangen', 'bestaetigt' => 'Bestätigt', 'in_arbeit' => 'In Bearbeitung', 'versendet' => 'Versendet',
        'geliefert' => 'Zugestellt', 'verrechnet' => 'Verrechnet', 'storniert' => 'Storniert',
    ];
    /** Schritte der Fortschrittsanzeige beim Verein (Verrechnet zählt als abgeschlossen) */
    public const STATUS_SCHRITTE = ['neu', 'bestaetigt', 'in_arbeit', 'versendet', 'geliefert'];
    public const STATUS_FARBE = ['neu' => 'warning text-dark', 'bestaetigt' => 'info', 'in_arbeit' => 'primary', 'versendet' => 'primary',
        'geliefert' => 'success', 'verrechnet' => 'success', 'storniert' => 'secondary'];

    private static function db(): Database { return Database::getInstance(); }

    /** Wie viel der Server pro Speichern annimmt (post_max_size) in Bytes – für die Prüfung im Browser. */
    public static function uploadGrenzeBytes(): int {
        return self::iniBytes('post_max_size');
    }
    /** Größte einzelne Datei, die dieser Server annimmt (upload_max_filesize, höchstens 16 MB). */
    public static function dateiGrenzeBytes(): int {
        $u = self::iniBytes('upload_max_filesize');
        return $u > 0 ? min($u, self::BILD_MAX_BYTES) : self::BILD_MAX_BYTES;
    }
    private static function iniBytes(string $schluessel): int {
        $w = trim((string)ini_get($schluessel));
        $n = (int)$w;
        $e = strtolower(substr($w, -1));
        return match ($e) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    }

    // ---------------------------------------------------------------- Veredelung (Preisliste)
    public static function veredelungen(bool $nurAktive = false): array {
        $liste = self::db()->fetchAll("SELECT * FROM shop_veredelungen" . ($nurAktive ? " WHERE aktiv = 1" : "") . " ORDER BY sortierung, id");
        foreach ($liste as &$v) {
            $v['art_name'] = self::VEREDELUNG_ARTEN[$v['art']] ?? $v['art'];
            $v['position_name'] = self::VEREDELUNG_POSITIONEN[$v['position']] ?? $v['position'];
            $v['bezeichnung'] = $v['art_name'] . ' – ' . $v['position_name'];
        }
        return $liste;
    }
    /** Preise und Freigaben aus dem Formular der Verwaltung übernehmen: preis[id], aktiv[id] */
    public static function veredelungenSpeichern(array $preise, array $aktiv): void {
        foreach (self::veredelungen() as $v) {
            $id = (int)$v['id'];
            $p = (float)str_replace(',', '.', trim((string)($preise[$id] ?? $v['preis'])));
            self::db()->execute("UPDATE shop_veredelungen SET preis = ?, aktiv = ? WHERE id = ?", [round(max(0, $p), 2), !empty($aktiv[$id]) ? 1 : 0, $id]);
        }
    }

    // ---------------------------------------------------------------- Vorlagen der Besteller (Logos, Schriftzüge)
    /** Zwischenablage beim Verein (bis zur Bestellung) bzw. Ablage beim Katalog-Server (zur Bestellung) – beide nicht öffentlich */
    public static function vorlagenOrdner(string $art): string {
        $dir = UPLOAD_DIR . DIRECTORY_SEPARATOR . ($art === 'bestellung' ? 'shop_bestellungen' : 'shop_auftrag');
        if (!is_dir($dir)) @mkdir($dir, 02775, true);
        if (is_dir($dir) && !is_file($dir . DIRECTORY_SEPARATOR . '.htaccess')) {
            @file_put_contents($dir . DIRECTORY_SEPARATOR . '.htaccess', "# Vorlagen der Besteller – nur über Syncopa abrufbar\nRequire all denied\n");
        }
        if (!is_writable($dir)) throw new RuntimeException('Der Ordner für Logos/Vorlagen (uploads/' . basename($dir) . ') ist nicht beschreibbar. Bitte die Ordnerrechte prüfen.');
        return $dir;
    }
    /** Vom Besteller hochgeladene Vorlage prüfen und in der Zwischenablage speichern. Gibt [datei, original] zurück. */
    public static function vorlageSpeichern(array $datei): array {
        if (($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Keine Datei ausgewählt.');
        if (in_array($datei['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new RuntimeException('Die Datei „' . ($datei['name'] ?? '') . '“ ist zu groß.');
        if ($datei['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($datei['tmp_name'])) throw new RuntimeException('Die Datei konnte nicht hochgeladen werden.');
        if (($datei['size'] ?? 0) > self::BILD_MAX_BYTES) throw new RuntimeException('Die Datei „' . $datei['name'] . '“ ist größer als 16 MB.');
        $typ = function_exists('mime_content_type') ? (string)mime_content_type($datei['tmp_name']) : '';
        $endung = array_search($typ, self::VORLAGE_TYPEN, true);
        if ($endung === false) throw new RuntimeException('„' . $datei['name'] . '“: bitte als JPG, PNG, WebP oder PDF hochladen.');
        $name = 'v_' . bin2hex(random_bytes(10)) . '.' . ($endung === 'jpeg' ? 'jpg' : $endung);
        if (!move_uploaded_file($datei['tmp_name'], self::vorlagenOrdner('auftrag') . DIRECTORY_SEPARATOR . $name)) throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
        @chmod(self::vorlagenOrdner('auftrag') . DIRECTORY_SEPARATOR . $name, 0664);
        return ['datei' => $name, 'original' => self::dateinameSauber((string)$datei['name'])];
    }
    /** Pfad einer Vorlage in der Zwischenablage (nur gültige, selbst erzeugte Namen) */
    public static function vorlagePfad(string $name): ?string {
        if (!preg_match('/^v_[a-f0-9]{20}\.(jpg|png|webp|pdf)$/', $name)) return null;
        $p = UPLOAD_DIR . DIRECTORY_SEPARATOR . 'shop_auftrag' . DIRECTORY_SEPARATOR . $name;
        return is_file($p) ? $p : null;
    }
    /** Alte Vorlagen in der Zwischenablage löschen (z. B. aus nie bestellten Warenkörben) */
    public static function vorlagenAufraeumen(int $tage = 14): void {
        $dir = UPLOAD_DIR . DIRECTORY_SEPARATOR . 'shop_auftrag';
        foreach (glob($dir . DIRECTORY_SEPARATOR . 'v_*') ?: [] as $f) if (filemtime($f) < time() - $tage * 86400) @unlink($f);
    }
    public static function dateinameSauber(string $n): string {
        $n = basename(str_replace('\\', '/', $n));
        $n = preg_replace('/[^\p{L}\p{N} ._()\-]+/u', '_', $n);
        return mb_substr($n !== '' ? $n : 'datei', 0, 120);
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
            self::aktionspreisParsen($d['aktionspreis'] ?? ''),
            !empty($d['ausverkauft']) ? 1 : 0,
            !empty($d['veredelung']) ? 1 : 0,
            (int)($d['sortierung'] ?? 100),
        ];
        if ($id) {
            self::db()->execute(
                "UPDATE shop_artikel SET kategorie_id = ?, name = ?, beschreibung = ?, artikelnummer = ?, preis = ?, lieferzeit = ?, aktiv = ?, aktion = ?, aktion_text = ?, aktionspreis = ?, ausverkauft = ?, veredelung = ?, sortierung = ?, aktualisiert_am = NOW() WHERE id = ?",
                array_merge($params, [$id])
            );
            return $id;
        }
        self::db()->execute(
            "INSERT INTO shop_artikel (kategorie_id, name, beschreibung, artikelnummer, preis, lieferzeit, aktiv, aktion, aktion_text, aktionspreis, ausverkauft, veredelung, sortierung, erstellt_am) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            $params
        );
        return (int)self::db()->lastInsertId();
    }
    /** Aktionspreis aus einer Eingabe: leer/0 = kein Aktionspreis (null) */
    public static function aktionspreisParsen($wert): ?float {
        $w = trim(str_replace(['€', ' '], '', (string)$wert));
        if ($w === '') return null;
        $p = round((float)str_replace(',', '.', $w), 2);
        if ($p < 0) throw new RuntimeException('Der Aktionspreis darf nicht negativ sein.');
        return $p > 0 ? $p : null;
    }
    /** Aufpreis einer Größe aus einer Eingabe wie „+2,50“, „-1“, „−1,00“, „3“ (leer = 0) */
    public static function aufschlagParsen($wert): float {
        $w = trim(str_replace(['€', ' ', '−', '–'], ['', '', '-', '-'], (string)$wert));
        return $w === '' ? 0.0 : round((float)str_replace(',', '.', $w), 2);
    }
    /** Gültiger Grundpreis eines Artikels: Aktionspreis, wenn „Aktion“ an und ein Aktionspreis gesetzt ist */
    public static function effektivPreis(array $a): float {
        return (!empty($a['aktion']) && (float)($a['aktionspreis'] ?? 0) > 0) ? (float)$a['aktionspreis'] : (float)$a['preis'];
    }
    /** Einzelnes Ja/Nein-Feld eines Artikels umschalten (aus der Artikelliste). Nur freigegebene Felder. */
    public static function artikelSchalten(int $id, string $feld, bool $wert): void {
        if (!in_array($feld, ['aktiv', 'ausverkauft', 'aktion'], true)) throw new RuntimeException('Dieses Feld kann nicht umgeschaltet werden.');
        if (!self::artikel($id)) throw new RuntimeException('Artikel nicht gefunden.');
        self::db()->execute("UPDATE shop_artikel SET `$feld` = ?, aktualisiert_am = NOW() WHERE id = ?", [$wert ? 1 : 0, $id]);
    }
    /** Aktionspreis direkt setzen (aus der Artikelliste); gibt den gespeicherten Wert zurück */
    public static function aktionspreisSetzen(int $id, $wert): ?float {
        $a = self::artikel($id);
        if (!$a) throw new RuntimeException('Artikel nicht gefunden.');
        $p = self::aktionspreisParsen($wert);
        if ($p !== null && $p >= (float)$a['preis']) throw new RuntimeException('Der Aktionspreis sollte unter dem normalen Preis (' . self::euro($a['preis']) . ') liegen.');
        self::db()->execute("UPDATE shop_artikel SET aktionspreis = ?, aktualisiert_am = NOW() WHERE id = ?", [$p, $id]);
        return $p;
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
    /** Alle Varianten eines Artikels ersetzen. $zeilen = Liste von [bezeichnung, artikelnummer, aufschlag (+/−), inaktiv] */
    public static function variantenSetzen(int $artikelId, array $zeilen): void {
        $db = self::db();
        $db->execute("DELETE FROM shop_varianten WHERE artikel_id = ?", [$artikelId]);
        $pos = 0;
        foreach ($zeilen as $z) {
            $bez = trim((string)($z['bezeichnung'] ?? ''));
            if ($bez === '') continue;
            $db->execute(
                "INSERT INTO shop_varianten (artikel_id, bezeichnung, artikelnummer, preis, aufschlag, sortierung, aktiv) VALUES (?, ?, ?, NULL, ?, ?, ?)",
                [$artikelId, mb_substr($bez, 0, 80), trim((string)($z['artikelnummer'] ?? '')) ?: null,
                 self::aufschlagParsen($z['aufschlag'] ?? ''), ++$pos * 10, empty($z['inaktiv']) ? 1 : 0]
            );
        }
    }
    /** Preis einer Variante: gültiger Grundpreis (ggf. Aktionspreis) + Aufpreis der Größe */
    public static function variantePreis(array $variante, array $artikel): float {
        return max(0, round(self::effektivPreis($artikel) + (float)($variante['aufschlag'] ?? 0), 2));
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
                'preis'         => self::effektivPreis($a),            // gültiger Preis (bei Aktion der Aktionspreis)
                'preis_regulaer'=> (float)$a['preis'],                 // normaler Preis – zum Durchstreichen, wenn höher
                'lieferzeit'    => $a['lieferzeit'],
                'sortierung'    => (int)$a['sortierung'],
                'aktion'        => (int)($a['aktion'] ?? 0) === 1,
                'aktion_text'   => ($a['aktion_text'] ?? '') !== '' ? $a['aktion_text'] : null,
                'ausverkauft'   => (int)($a['ausverkauft'] ?? 0) === 1,
                'veredelung'    => (int)($a['veredelung'] ?? 0) === 1,
                'varianten'     => array_values(array_map(fn($v) => [
                    'id' => (int)$v['id'], 'bezeichnung' => $v['bezeichnung'], 'artikelnummer' => $v['artikelnummer'],
                    'aufschlag' => (float)$v['aufschlag'],
                    'preis' => self::variantePreis($v, $a),
                    'preis_regulaer' => max(0, round((float)$a['preis'] + (float)$v['aufschlag'], 2)),
                ], array_filter(self::varianten((int)$a['id']), fn($v) => (int)$v['aktiv'] === 1))),
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
            'veredelungen' => array_map(fn($v) => [
                'id' => (int)$v['id'], 'art' => $v['art'], 'art_name' => $v['art_name'],
                'position' => $v['position'], 'position_name' => $v['position_name'], 'preis' => (float)$v['preis'],
            ], self::veredelungen(true)),
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
    /** Vorlage einer Bestellung zum Herunterladen – nur wenn sie wirklich zu dieser Bestellung gehört. */
    public static function bestellVorlage(int $bestellungId, string $datei): ?array {
        if (!preg_match('/^B-[0-9]{6}-[a-f0-9]{6}_[a-f0-9]{12}\.(jpg|png|webp|pdf)$/', $datei)) return null;
        foreach (self::db()->fetchAll("SELECT veredelung FROM shop_bestellpositionen WHERE bestellung_id = ? AND veredelung IS NOT NULL", [$bestellungId]) as $p) {
            foreach ((array)json_decode((string)$p['veredelung'], true) as $v) {
                if (($v['datei'] ?? '') === $datei) {
                    $pfad = UPLOAD_DIR . DIRECTORY_SEPARATOR . 'shop_bestellungen' . DIRECTORY_SEPARATOR . $datei;
                    if (!is_file($pfad)) return null;
                    $endung = pathinfo($datei, PATHINFO_EXTENSION);
                    return ['pfad' => $pfad, 'original' => (string)($v['original'] ?: $datei), 'typ' => self::VORLAGE_TYPEN[$endung] ?? 'application/octet-stream'];
                }
            }
        }
        return null;
    }
    public static function bestellStatusSetzen(int $id, string $status): void {
        self::bestellungAktualisieren($id, ['status' => $status]);
    }
    /** Status, Sendungsverfolgung und Nachricht an den Verein speichern; Statuswechsel landen im Verlauf. */
    public static function bestellungAktualisieren(int $id, array $d): void {
        $b = self::db()->fetchOne("SELECT * FROM shop_bestellungen WHERE id = ?", [$id]);
        if (!$b) throw new RuntimeException('Bestellung nicht gefunden.');
        $status = (string)($d['status'] ?? $b['status']);
        if (!isset(self::STATUS[$status])) throw new RuntimeException('Unbekannter Status.');
        $link = array_key_exists('tracking_url', $d) ? trim((string)$d['tracking_url']) : (string)$b['tracking_url'];
        if ($link !== '' && !preg_match('#^https?://[^\s]+$#i', $link)) throw new RuntimeException('Der Verfolgungslink muss mit http:// oder https:// beginnen.');
        $sendung = array_key_exists('sendungsnummer', $d) ? mb_substr(trim((string)$d['sendungsnummer']), 0, 100) : (string)$b['sendungsnummer'];
        $nachricht = array_key_exists('nachricht', $d) ? trim((string)$d['nachricht']) : (string)$b['nachricht'];
        $gewechselt = $status !== $b['status'];
        self::db()->execute(
            "UPDATE shop_bestellungen SET status = ?, sendungsnummer = ?, tracking_url = ?, nachricht = ?" . ($gewechselt ? ", status_geaendert_am = NOW()" : "") . " WHERE id = ?",
            [$status, $sendung ?: null, mb_substr($link, 0, 500) ?: null, $nachricht ?: null, $id]
        );
        if ($gewechselt) self::db()->execute("INSERT INTO shop_bestellstatus (bestellung_id, status, erstellt_am) VALUES (?, ?, NOW())", [$id, $status]);
    }
    public static function bestellVerlauf(int $id): array {
        return self::db()->fetchAll("SELECT status, erstellt_am FROM shop_bestellstatus WHERE bestellung_id = ? ORDER BY erstellt_am, id", [$id]);
    }

    /** Bestellungen einer Vereins-Installation – für die Ansicht „Meine Bestellungen“ (ohne interne Dateinamen) */
    public static function bestellungenFuerInstallation(string $installationId): array {
        if (!preg_match('/^[a-f0-9]{16,64}$/i', $installationId)) return [];
        $db = self::db(); $out = [];
        foreach ($db->fetchAll("SELECT * FROM shop_bestellungen WHERE installation_id = ? ORDER BY erstellt_am DESC LIMIT 200", [$installationId]) as $b) {
            $verlauf = [];
            foreach (self::bestellVerlauf((int)$b['id']) as $v) $verlauf[$v['status']] = $v['erstellt_am'];   // je Status der letzte Zeitpunkt
            $out[] = [
                'bestellnummer' => $b['bestellnummer'], 'erstellt_am' => $b['erstellt_am'],
                'status' => $b['status'], 'status_name' => self::STATUS[$b['status']] ?? $b['status'],
                'status_geaendert_am' => $b['status_geaendert_am'],
                'verlauf' => $verlauf,
                'sendungsnummer' => $b['sendungsnummer'], 'tracking_url' => $b['tracking_url'], 'nachricht' => $b['nachricht'],
                'besteller_name' => $b['besteller_name'], 'lieferadresse' => $b['lieferadresse'], 'bemerkung' => $b['bemerkung'],
                'versandkosten' => (float)$b['versandkosten'], 'summe' => (float)$b['summe'],
                'positionen' => array_map(fn($p) => [
                    'artikel_name' => $p['artikel_name'], 'variante' => $p['variante'], 'artikelnummer' => $p['artikelnummer'],
                    'einzelpreis' => (float)$p['einzelpreis'], 'menge' => (int)$p['menge'],
                    'veredelung' => array_map(fn($v) => ['bezeichnung' => $v['bezeichnung'] ?? '', 'preis' => (float)($v['preis'] ?? 0),
                        'original' => $v['original'] ?? null, 'hinweis' => $v['hinweis'] ?? ''], (array)json_decode((string)$p['veredelung'], true)),
                ], $db->fetchAll("SELECT * FROM shop_bestellpositionen WHERE bestellung_id = ? ORDER BY id", [$b['id']])),
            ];
        }
        return $out;
    }

    // ---------------------------------------------------------------- Benachrichtigung per E-Mail
    /** Funktions-Postfach (aus dem E-Mail-Programm), über das der Shop Mails verschickt – oder null */
    public static function versandPostfach(): ?array {
        $id = (int)self::einstellung('mail_konto', '0');
        if (!$id) return null;
        return self::db()->fetchOne("SELECT * FROM mail_konten WHERE id = ? AND benutzer_id IS NULL", [$id]) ?: null;
    }
    /**
     * Neue Bestellung an die hinterlegte Adresse melden. Gibt true zurück, wenn gesendet wurde;
     * ohne Adresse/Postfach passiert nichts. Fehler beim Senden werfen eine Exception.
     */
    public static function bestellungMelden(int $id): bool {
        $an = trim(self::einstellung('bestell_email'));
        $konto = self::versandPostfach();
        if ($an === '' || !$konto) return false;
        if (!filter_var($an, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Die Adresse für Bestell-Meldungen ist ungültig.');
        $inhalt = self::bestellMailInhalt($id);
        if (!$inhalt) return false;
        MailSender::senden($konto, ['an' => [[$an, '']], 'betreff' => $inhalt['betreff'], 'html' => $inhalt['html']]);
        return true;
    }
    /** Betreff und HTML der Bestell-Meldung (ohne zu senden) */
    public static function bestellMailInhalt(int $id): ?array {
        $b = self::bestellung($id);
        if (!$b) return null;
        $h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
        $zeilen = '';
        foreach ($b['positionen'] as $p) {
            $ved = '';
            foreach ((array)json_decode((string)$p['veredelung'], true) as $v) {
                $ved .= '<br><span style="color:#5a6c7d;font-size:12px">+ ' . $h($v['bezeichnung']) . ' (' . self::euro($v['preis']) . ')'
                      . ($v['original'] ? ' – Datei: ' . $h($v['original']) : '') . ($v['hinweis'] !== '' ? ' – „' . $h($v['hinweis']) . '“' : '') . '</span>';
            }
            $zeilen .= '<tr><td style="padding:6px 8px;border-bottom:1px solid #e0e4e8">' . $h($p['artikel_name']) . ($p['artikelnummer'] ? ' <span style="color:#8699ac">' . $h($p['artikelnummer']) . '</span>' : '') . $ved . '</td>'
                     . '<td style="padding:6px 8px;border-bottom:1px solid #e0e4e8">' . $h($p['variante'] ?: '–') . '</td>'
                     . '<td style="padding:6px 8px;border-bottom:1px solid #e0e4e8;text-align:right">' . (int)$p['menge'] . '</td>'
                     . '<td style="padding:6px 8px;border-bottom:1px solid #e0e4e8;text-align:right;white-space:nowrap">' . self::euro($p['einzelpreis'] * $p['menge']) . '</td></tr>';
        }
        $link = rtrim(defined('BASE_URL') ? BASE_URL : '', '/') . '/shop_bestellungen.php';
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#2c3e50;max-width:680px">'
            . '<h2 style="color:#2d4a6a;margin:0 0 4px">Neue Shop-Bestellung ' . $h($b['bestellnummer']) . '</h2>'
            . '<p style="margin:0 0 16px;color:#5a6c7d">' . $h($b['verein_name'] ?: 'Unbekannter Verein') . ' · ' . date('d.m.Y H:i', strtotime($b['erstellt_am'])) . '</p>'
            . '<table style="width:100%;border-collapse:collapse;margin-bottom:12px"><tr>'
            . '<td style="vertical-align:top;width:50%"><b>Besteller</b><br>' . $h($b['besteller_name'] ?: '–')
            . ($b['besteller_email'] ? '<br>' . $h($b['besteller_email']) : '') . ($b['besteller_telefon'] ? '<br>' . $h($b['besteller_telefon']) : '') . '</td>'
            . '<td style="vertical-align:top"><b>Lieferadresse</b><br>' . nl2br($h($b['lieferadresse'] ?: '–')) . '</td></tr></table>'
            . ($b['bemerkung'] ? '<p><b>Bemerkung:</b> ' . nl2br($h($b['bemerkung'])) . '</p>' : '')
            . '<table style="width:100%;border-collapse:collapse"><tr style="background:#f5f6f8">'
            . '<th style="padding:6px 8px;text-align:left">Artikel</th><th style="padding:6px 8px;text-align:left">Größe</th><th style="padding:6px 8px;text-align:right">Menge</th><th style="padding:6px 8px;text-align:right">Summe</th></tr>'
            . $zeilen
            . ((float)$b['versandkosten'] > 0 ? '<tr><td colspan="3" style="padding:6px 8px;text-align:right">Versand</td><td style="padding:6px 8px;text-align:right">' . self::euro($b['versandkosten']) . '</td></tr>' : '')
            . '<tr><td colspan="3" style="padding:8px;text-align:right"><b>Gesamt</b></td><td style="padding:8px;text-align:right"><b>' . self::euro($b['summe']) . '</b></td></tr></table>'
            . '<p style="margin-top:20px"><a href="' . $h($link) . '" style="background:#4471A3;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">Bestellung in Syncopa öffnen</a></p>'
            . '<p style="color:#8699ac;font-size:12px">Logos/Vorlagen der Veredelung sind in Syncopa unter Shop → Bestellungen abrufbar.</p></div>';
        return [
            'betreff' => 'Neue Shop-Bestellung ' . $b['bestellnummer'] . ' – ' . ($b['verein_name'] ?: 'Verein') . ' (' . self::euro($b['summe']) . ')',
            'html' => $html,
        ];
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
        $db = self::db();
        // eindeutige Bestellnummer (mit ein paar Versuchen gegen Kollision)
        for ($i = 0; $i < 5; $i++) {
            $nr = 'B-' . date('ymd') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            if (!$db->fetchOne("SELECT id FROM shop_bestellungen WHERE bestellnummer = ?", [$nr])) break;
        }

        // Veredelung: Preise und Bezeichnungen aus der eigenen Preisliste (nicht vom Besteller übernehmen)
        $angebot = [];
        foreach (self::veredelungen(true) as $v) $angebot[(int)$v['id']] = $v;
        $dateienRoh = (array)($d['dateien'] ?? []);
        $abgelegt = [];      // Verweis → [datei, original] – jede Datei nur einmal speichern
        $neueDateien = [];   // für das Aufräumen bei Fehlern
        $vorlageAblegen = function (string $ref) use ($dateienRoh, &$abgelegt, &$neueDateien, $nr): array {
            if (isset($abgelegt[$ref])) return $abgelegt[$ref];
            $roh = $dateienRoh[$ref] ?? null;
            if (!is_array($roh) || !isset($roh['inhalt'])) throw new RuntimeException('Eine Vorlage (Logo/Schriftzug) fehlt in der Bestellung. Bitte den Artikel erneut in den Warenkorb legen.');
            $inhalt = base64_decode((string)$roh['inhalt'], true);
            if ($inhalt === false || $inhalt === '') throw new RuntimeException('Eine Vorlage konnte nicht gelesen werden.');
            if (strlen($inhalt) > self::BILD_MAX_BYTES) throw new RuntimeException('Eine Vorlage ist größer als 16 MB.');
            $typ = (new finfo(FILEINFO_MIME_TYPE))->buffer($inhalt);
            $endung = array_search($typ, self::VORLAGE_TYPEN, true);
            if ($endung === false) throw new RuntimeException('Eine Vorlage hat ein nicht erlaubtes Dateiformat (erlaubt: JPG, PNG, WebP, PDF).');
            $name = $nr . '_' . bin2hex(random_bytes(6)) . '.' . ($endung === 'jpeg' ? 'jpg' : $endung);
            $pfad = self::vorlagenOrdner('bestellung') . DIRECTORY_SEPARATOR . $name;
            if (file_put_contents($pfad, $inhalt) === false) throw new RuntimeException('Eine Vorlage konnte nicht gespeichert werden.');
            @chmod($pfad, 0664);
            $neueDateien[] = $pfad;
            return $abgelegt[$ref] = ['datei' => $name, 'original' => self::dateinameSauber((string)($roh['original'] ?? $name))];
        };

        try {
            $positionen = [];
            foreach ((array)($d['positionen'] ?? []) as $p) {
                $name = trim((string)($p['artikel_name'] ?? ''));
                $menge = max(1, (int)($p['menge'] ?? 1));
                if ($name === '') continue;
                $ved = []; $vedPreis = 0.0;
                foreach ((array)($p['veredelung'] ?? []) as $w) {
                    $vid = (int)($w['id'] ?? 0);
                    if (!isset($angebot[$vid])) throw new RuntimeException('Eine gewählte Veredelung wird nicht mehr angeboten. Bitte den Artikel neu in den Warenkorb legen.');
                    $datei = trim((string)($w['datei'] ?? '')) !== '' ? $vorlageAblegen((string)$w['datei']) : ['datei' => null, 'original' => null];
                    $ved[] = [
                        'art' => $angebot[$vid]['art'], 'position' => $angebot[$vid]['position'], 'bezeichnung' => $angebot[$vid]['bezeichnung'],
                        'preis' => round((float)$angebot[$vid]['preis'], 2),
                        'hinweis' => mb_substr(trim((string)($w['hinweis'] ?? '')), 0, 300),
                        'datei' => $datei['datei'], 'original' => $datei['original'],
                    ];
                    $vedPreis += (float)$angebot[$vid]['preis'];
                }
                $positionen[] = [
                    'artikel_name'  => mb_substr($name, 0, 200),
                    'variante'      => mb_substr(trim((string)($p['variante'] ?? '')), 0, 100) ?: null,
                    'artikelnummer' => mb_substr(trim((string)($p['artikelnummer'] ?? '')), 0, 50) ?: null,
                    'einzelpreis'   => round((float)($p['einzelpreis'] ?? 0) + $vedPreis, 2),   // Grundpreis + Veredelung je Stück
                    'menge'         => $menge,
                    'veredelung'    => $ved ? json_encode($ved, JSON_UNESCAPED_UNICODE) : null,
                ];
            }
            if (!$positionen) throw new RuntimeException('Die Bestellung enthält keine Positionen.');
        } catch (\Throwable $e) {
            foreach ($neueDateien as $f) @unlink($f);
            throw $e;
        }
        $warenSumme = 0;
        foreach ($positionen as $p) $warenSumme += $p['einzelpreis'] * $p['menge'];
        $versand = self::versandkosten((float)$warenSumme);  // serverseitig aus eigenen Einstellungen
        $summe   = round($warenSumme + $versand, 2);

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
                    "INSERT INTO shop_bestellpositionen (bestellung_id, artikel_name, variante, artikelnummer, einzelpreis, menge, veredelung) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$id, $p['artikel_name'], $p['variante'], $p['artikelnummer'], $p['einzelpreis'], $p['menge'], $p['veredelung']]
                );
            }
            $db->execute("INSERT INTO shop_bestellstatus (bestellung_id, status, erstellt_am) VALUES (?, 'neu', NOW())", [$id]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            foreach ($neueDateien as $f) @unlink($f);
            throw $e;
        }
        // Benachrichtigung – ein Fehler beim Mailversand darf die Bestellung nicht verhindern
        try { self::bestellungMelden($id); }
        catch (\Throwable $e) { error_log('Syncopa Shop: Bestell-Mail ' . $nr . ' fehlgeschlagen: ' . $e->getMessage()); }
        return ['id' => $id, 'bestellnummer' => $nr, 'versandkosten' => $versand, 'summe' => $summe];
    }
}
