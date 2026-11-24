<?php
// classes/MailFreigabe.php
// Download-Links für große E-Mail-Anhänge (wie OneDrive-Links in Outlook): Die Datei wird in Stücken hochgeladen,
// liegt geschützt in uploads/mail_freigaben und ist über freigabe.php?t=<Schlüssel> bis zum Ablaufdatum abrufbar.

class MailFreigabe {

    public const MAX_GROESSE = 2147483648;        // 2 GB je Datei
    private const RESERVE    = 1073741824;        // 1 GB Speicher bleibt immer frei
    public const TAGE        = [7, 14, 30, 90];

    private static function db(): Database { return Database::getInstance(); }

    public static function ordner(): string {
        $o = UPLOAD_DIR . DIRECTORY_SEPARATOR . 'mail_freigaben';
        if (!is_dir($o)) { @mkdir($o, 02775, true); @chmod($o, 02775); }
        if (!is_file($o . '/.htaccess')) @file_put_contents($o . '/.htaccess', "Require all denied\n");
        return $o;
    }

    private static function pfad(array $f): string {
        return self::ordner() . DIRECTORY_SEPARATOR . basename($f['datei']);
    }

    /** Größe je hochgeladenem Stück: knapp unter den PHP-Grenzen, höchstens 8 MB */
    public static function stueckGroesse(): int {
        $bytes = function (string $w): int {
            $w = trim($w); $n = (int)$w; $e = strtolower(substr($w, -1));
            return $e === 'g' ? $n << 30 : ($e === 'm' ? $n << 20 : ($e === 'k' ? $n << 10 : $n));
        };
        $grenze = min($bytes(ini_get('upload_max_filesize') ?: '2M'), $bytes(ini_get('post_max_size') ?: '8M'));
        return max(512 * 1024, min(8 << 20, $grenze - (256 * 1024)));
    }

    /** Neue Freigabe anlegen (noch ohne Inhalt) */
    public static function starten(string $name, int $groesse, string $typ): array {
        if (!self::linksMoeglich()) throw new RuntimeException('„' . $name . '“ ist zu groß für einen Anhang. Für Download-Links fehlt die öffentliche Adresse von Syncopa; ein Administrator kann sie unter E-Mail → Funktions-Postfächer eintragen.');
        self::aufraeumen();
        $name = trim(str_replace(['/', '\\', "\0"], '_', $name)) ?: 'datei';
        if ($groesse <= 0) throw new RuntimeException('Die Datei ist leer.');
        if ($groesse > self::MAX_GROESSE) throw new RuntimeException('Die Datei ist größer als 2 GB.');
        $frei = @disk_free_space(self::ordner());
        if ($frei !== false && $frei - $groesse < self::RESERVE) throw new RuntimeException('Auf dem Server ist nicht genug Speicherplatz für diese Datei frei.');
        $token = bin2hex(random_bytes(20));
        $datei = bin2hex(random_bytes(10)) . '.bin';
        self::db()->execute("INSERT INTO mail_freigaben (token, dateiname, datei, groesse, typ, erstellt_von, erstellt_am) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$token, mb_substr($name, 0, 255), $datei, $groesse, mb_substr($typ, 0, 150) ?: null, Session::getUserId()]);
        $id = (int)self::db()->lastInsertId();
        touch(self::ordner() . DIRECTORY_SEPARATOR . $datei);
        return ['id' => $id, 'stueck' => self::stueckGroesse()];
    }

    private static function eigene(int $id): array {
        $f = self::db()->fetchOne("SELECT * FROM mail_freigaben WHERE id = ? AND erstellt_von = ?", [$id, (int)Session::getUserId()]);
        if (!$f) throw new RuntimeException('Freigabe nicht gefunden.');
        return $f;
    }

    /** Ein Stück anhängen. $start = erwartete Position, schützt vor doppelten oder verlorenen Stücken. */
    public static function teil(int $id, int $start, string $tmpDatei): int {
        $f = self::eigene($id);
        if ($f['fertig']) throw new RuntimeException('Die Datei ist bereits vollständig.');
        $pfad = self::pfad($f);
        clearstatcache(true, $pfad);
        $ist = is_file($pfad) ? filesize($pfad) : 0;
        if ($start !== $ist) return $ist; // Client setzt an der gemeldeten Stelle fort
        $inhalt = file_get_contents($tmpDatei);
        if ($inhalt === false || $ist + strlen($inhalt) > (int)$f['groesse']) throw new RuntimeException('Das Hochladen ist durcheinandergekommen. Bitte erneut versuchen.');
        file_put_contents($pfad, $inhalt, FILE_APPEND | LOCK_EX);
        @chmod($pfad, 0660);
        clearstatcache(true, $pfad);
        return filesize($pfad);
    }

    public static function abschliessen(int $id): void {
        $f = self::eigene($id);
        clearstatcache();
        if (filesize(self::pfad($f)) !== (int)$f['groesse']) throw new RuntimeException('Die Datei ist nicht vollständig angekommen.');
        self::db()->execute("UPDATE mail_freigaben SET fertig = 1 WHERE id = ?", [$id]);
    }

    /** Beim Senden: Gültigkeit setzen und die Freigaben für den Mailtext liefern */
    public static function versenden(array $ids, int $tage): array {
        if (!self::linksMoeglich()) throw new RuntimeException('Für Download-Links fehlt die öffentliche Adresse von Syncopa (derzeit „' . self::basisAdresse() . '“, von außen nicht erreichbar). Ein Administrator kann sie unter E-Mail → Funktions-Postfächer eintragen.');
        $tage = in_array($tage, self::TAGE, true) ? $tage : 30;
        $liste = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $f = self::eigene($id);
            if (!$f['fertig']) throw new RuntimeException('„' . $f['dateiname'] . '“ ist noch nicht fertig hochgeladen.');
            $bis = date('Y-m-d H:i:s', strtotime("+$tage days"));
            self::db()->execute("UPDATE mail_freigaben SET gueltig_bis = GREATEST(COALESCE(gueltig_bis, ?), ?) WHERE id = ?", [$bis, $bis, $id]);
            $liste[] = ['name' => $f['dateiname'], 'groesse' => (int)$f['groesse'], 'link' => self::link($f['token']), 'bis' => $bis];
        }
        return $liste;
    }

    /** Öffentliche Adresse von Syncopa für Links (Einstellung, sonst BASE_URL aus config.php) */
    public static function basisAdresse(): string {
        $e = self::db()->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'mail_link_adresse'")['wert'] ?? '';
        return rtrim(trim($e) !== '' ? trim($e) : (defined('BASE_URL') ? BASE_URL : ''), '/');
    }

    /** Ist die Adresse von außen erreichbar? (keine interne IP, kein localhost) */
    public static function istOeffentlich(string $adresse): bool {
        $host = parse_url($adresse, PHP_URL_HOST);
        if (!$host || !preg_match('#^https?://#i', $adresse)) return false;
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true) || str_ends_with(strtolower($host), '.local') || str_ends_with(strtolower($host), '.lan')) return false;
        if (filter_var($host, FILTER_VALIDATE_IP)) return (bool)filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        return str_contains($host, '.');
    }

    public static function linksMoeglich(): bool {
        return self::istOeffentlich(self::basisAdresse());
    }

    public static function adresseSpeichern(string $adresse): void {
        $adresse = rtrim(trim($adresse), '/');
        if ($adresse !== '' && !self::istOeffentlich($adresse)) throw new RuntimeException('Bitte eine öffentlich erreichbare Adresse eintragen, z. B. https://verein.at/syncopa (keine interne IP).');
        self::db()->execute("INSERT INTO einstellungen (schluessel, wert, beschreibung) VALUES ('mail_link_adresse', ?, 'Öffentliche Adresse von Syncopa für Download-Links in E-Mails')
                             ON DUPLICATE KEY UPDATE wert = VALUES(wert)", [$adresse]);
    }

    public static function link(string $token): string {
        return self::basisAdresse() . '/freigabe.php?t=' . $token;
    }

    /** HTML-Kasten mit den Download-Links für den Mailtext */
    public static function htmlKasten(array $liste): string {
        if (!$liste) return '';
        $h = '<div style="margin:16px 0;padding:12px 14px;border:1px solid #c9d6e6;border-radius:8px;background:#f4f8fc;font-family:Segoe UI,Arial,sans-serif;font-size:14px">'
           . '<div style="font-weight:600;margin-bottom:6px">Dateien zum Herunterladen</div>';
        foreach ($liste as $f) {
            $h .= '<div style="margin:4px 0">&#128206; <a href="' . htmlspecialchars($f['link']) . '">' . htmlspecialchars($f['name']) . '</a> '
                . '<span style="color:#5b6878">(' . self::groesseText($f['groesse']) . ', abrufbar bis ' . date('d.m.Y', strtotime($f['bis'])) . ')</span></div>';
        }
        return $h . '</div>';
    }

    public static function groesseText(int $b): string {
        return $b >= 1073741824 ? number_format($b / 1073741824, 1, ',', '.') . ' GB' : ($b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, round($b / 1024)) . ' KB');
    }

    /** Öffentlich: gültige Freigabe zum Schlüssel */
    public static function nachToken(string $token): ?array {
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) return null;
        $f = self::db()->fetchOne("SELECT * FROM mail_freigaben WHERE token = ? AND fertig = 1 AND gueltig_bis IS NOT NULL AND gueltig_bis > NOW()", [$token]);
        return $f && is_file(self::pfad($f)) ? $f : null;
    }

    public static function ausliefern(array $f): void {
        self::db()->execute("UPDATE mail_freigaben SET downloads = downloads + 1, letzter_download = NOW() WHERE id = ?", [$f['id']]);
        @set_time_limit(0);
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/octet-stream');
        header('X-Content-Type-Options: nosniff');
        header("Content-Disposition: attachment; filename=\"" . preg_replace('/[^\x20-\x7e]|"/', '_', $f['dateiname']) . "\"; filename*=UTF-8''" . rawurlencode($f['dateiname']));
        header('Content-Length: ' . filesize(self::pfad($f)));
        $h = fopen(self::pfad($f), 'rb');
        while (!feof($h)) { echo fread($h, 1 << 20); flush(); }
        fclose($h);
    }

    /** Eigene Freigaben (für „Mein Konto“) */
    public static function meine(): array {
        return self::db()->fetchAll("SELECT * FROM mail_freigaben WHERE erstellt_von = ? AND fertig = 1 ORDER BY erstellt_am DESC", [(int)Session::getUserId()]);
    }

    public static function loeschen(int $id): void {
        $f = Session::isAdmin() ? self::db()->fetchOne("SELECT * FROM mail_freigaben WHERE id = ?", [$id]) : self::eigene($id);
        if (!$f) return;
        @unlink(self::pfad($f));
        self::db()->execute("DELETE FROM mail_freigaben WHERE id = ?", [$id]);
    }

    /** Abgelaufene Freigaben und abgebrochene Uploads (älter als 1 Tag) entfernen */
    public static function aufraeumen(): int {
        $n = 0;
        foreach (self::db()->fetchAll("SELECT * FROM mail_freigaben WHERE (gueltig_bis IS NOT NULL AND gueltig_bis < NOW())
                                       OR (gueltig_bis IS NULL AND erstellt_am < NOW() - INTERVAL 1 DAY)") as $f) {
            @unlink(self::pfad($f));
            self::db()->execute("DELETE FROM mail_freigaben WHERE id = ?", [$f['id']]);
            $n++;
        }
        return $n;
    }
}
