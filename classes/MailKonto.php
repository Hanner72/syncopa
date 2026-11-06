<?php
// classes/MailKonto.php
// E-Mail-Postfächer: persönliche (gehören einem Benutzer, unter „Mein Konto“ gepflegt) und Funktions-Postfächer
// (ohne Besitzer, vom Admin angelegt und für Rollen freigegeben, z. B. obmann@ für die Rolle Obmann).

class MailKonto {

    /** Vorlagen für bekannte Anbieter: Name => [imap_host, imap_port, imap_sicherheit, smtp_host, smtp_port, smtp_sicherheit] */
    public const ANBIETER = [
        'world4you'  => ['World4You',             'imap.world4you.com',     993, 'ssl', 'smtp.world4you.com',     465, 'ssl'],
        'easyname'   => ['easyname',              'mail.easyname.com',      993, 'ssl', 'mail.easyname.com',      465, 'ssl'],
        'gmx'        => ['GMX',                   'imap.gmx.net',           993, 'ssl', 'mail.gmx.net',           587, 'tls'],
        'gmail'      => ['Gmail (App-Passwort)',  'imap.gmail.com',         993, 'ssl', 'smtp.gmail.com',         465, 'ssl'],
        'outlook'    => ['Outlook.com',           'outlook.office365.com',  993, 'ssl', 'smtp-mail.outlook.com',  587, 'tls'],
        'a1'         => ['A1',                    'securemail.a1.net',      993, 'ssl', 'securemail.a1.net',      587, 'tls'],
        'ionos'      => ['IONOS',                 'imap.ionos.de',          993, 'ssl', 'smtp.ionos.de',          465, 'ssl'],
    ];

    public const SICHERHEIT = ['ssl' => 'SSL/TLS', 'tls' => 'STARTTLS', 'keine' => 'keine (unsicher)'];

    /** Erlaubte Ports – damit Syncopa nicht als Werkzeug für Verbindungen zu beliebigen Diensten dienen kann */
    public const PORTS = ['imap' => [143, 993], 'smtp' => [25, 465, 587, 2525]];

    /**
     * Prüft Server und Port. Interne/lokale Adressen (z. B. 192.168.x.x, 127.0.0.1) nur für Administratoren,
     * Ports nur aus PORTS. Wirft RuntimeException.
     */
    public static function serverPruefen(string $art, string $host, int $port): void {
        if (!preg_match('/^[a-z0-9.-]+$/i', $host)) {
            throw new RuntimeException('Ungültiger Servername: ' . $host);
        }
        if (!in_array($port, self::PORTS[$art], true)) {
            throw new RuntimeException(strtoupper($art) . '-Port ' . $port . ' ist nicht erlaubt (möglich: ' . implode(', ', self::PORTS[$art]) . ').');
        }
        if (Session::isAdmin()) return;
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$ips) throw new RuntimeException("Server $host wurde nicht gefunden.");
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException("Server $host zeigt auf eine interne Adresse – das kann nur ein Administrator einrichten.");
            }
        }
    }

    /** Alle Postfächer, auf die ein Benutzer zugreifen darf (eigene + für seine Rollen freigegebene), sortiert */
    public static function fuerBenutzer(int $benutzerId, array $rollenIds): array {
        $rollen = array_map('intval', $rollenIds) ?: [0];
        return Database::getInstance()->fetchAll(
            "SELECT k.*, (k.benutzer_id IS NULL) AS funktion FROM mail_konten k
             WHERE k.benutzer_id = ?
                OR (k.benutzer_id IS NULL AND k.id IN (SELECT konto_id FROM mail_konto_rollen WHERE rolle_id IN (" . implode(',', $rollen) . ")))
             ORDER BY (k.benutzer_id IS NULL), k.sortierung, k.bezeichnung",
            [$benutzerId]
        );
    }

    /** Postfächer des angemeldeten Benutzers */
    public static function meine(): array {
        return self::fuerBenutzer((int)Session::getUserId(), Session::getRollenIds());
    }

    /** Ein Postfach, nur wenn der angemeldete Benutzer darauf zugreifen darf – sonst Exception */
    public static function laden(int $id): array {
        foreach (self::meine() as $k) {
            if ((int)$k['id'] === $id) {
                return $k;
            }
        }
        throw new RuntimeException('Postfach nicht gefunden oder keine Berechtigung.');
    }

    /** Ein Postfach für die Verwaltung (Besitzer bzw. Admin bei Funktions-Postfächern) */
    public static function zumBearbeiten(int $id): array {
        $k = Database::getInstance()->fetchOne("SELECT * FROM mail_konten WHERE id = ?", [$id]);
        $darf = $k && ($k['benutzer_id'] !== null ? (int)$k['benutzer_id'] === (int)Session::getUserId() : Session::isAdmin());
        if (!$darf) {
            throw new RuntimeException('Postfach nicht gefunden oder keine Berechtigung.');
        }
        $k['rollen'] = array_map('intval', array_column(
            Database::getInstance()->fetchAll("SELECT rolle_id FROM mail_konto_rollen WHERE konto_id = ?", [$id]), 'rolle_id'));
        return $k;
    }

    public static function passwort(array $konto): string {
        return MailKrypto::entschluesseln($konto['passwort_enc']);
    }

    /**
     * Speichert ein Postfach. $funktion = true: Funktions-Postfach (nur Admin), $rollen = freigegebene Rollen-IDs.
     * Leeres Passwort beim Bearbeiten = bisheriges behalten. Liefert die ID.
     */
    public static function speichern(array $e, ?int $id, bool $funktion, array $rollen = []): int {
        if ($funktion && !Session::isAdmin()) {
            throw new RuntimeException('Funktions-Postfächer kann nur ein Administrator anlegen.');
        }
        $alt = $id ? self::zumBearbeiten($id) : null;
        $t = fn($k, $max = 190) => mb_substr(trim((string)($e[$k] ?? '')), 0, $max);
        $email = $t('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Bitte eine gültige E-Mail-Adresse angeben.');
        foreach (['imap_host' => 'den Posteingangsserver (IMAP)', 'smtp_host' => 'den Postausgangsserver (SMTP)'] as $feld => $name) {
            if (!preg_match('/^[a-z0-9.-]+$/i', $t($feld))) throw new RuntimeException("Bitte $name angeben (z. B. imap.anbieter.at).");
        }
        self::serverPruefen('imap', $t('imap_host'), (int)($e['imap_port'] ?? 993));
        self::serverPruefen('smtp', $t('smtp_host'), (int)($e['smtp_port'] ?? 465));
        $passwort = (string)($e['passwort'] ?? '');
        if (!$alt && $passwort === '') throw new RuntimeException('Bitte das Passwort des Postfachs angeben.');
        $sicher = fn($k) => isset(self::SICHERHEIT[$e[$k] ?? '']) ? $e[$k] : 'ssl';
        $werte = [
            'bezeichnung'     => $t('bezeichnung', 100) ?: $email,
            'email'           => $email,
            'anzeigename'     => $t('anzeigename', 150) ?: null,
            'benutzername'    => $t('benutzername') ?: $email,
            'imap_host'       => $t('imap_host'),
            'imap_port'       => max(1, min(65535, (int)($e['imap_port'] ?? 993))),
            'imap_sicherheit' => $sicher('imap_sicherheit'),
            'smtp_host'       => $t('smtp_host'),
            'smtp_port'       => max(1, min(65535, (int)($e['smtp_port'] ?? 465))),
            'smtp_sicherheit' => $sicher('smtp_sicherheit'),
            'signatur'        => trim((string)($e['signatur'] ?? '')) ?: null,
        ];
        if ($passwort !== '') {
            $werte['passwort_enc'] = MailKrypto::verschluesseln($passwort);
        }
        $db = Database::getInstance();
        if ($alt) {
            $db->execute(
                "UPDATE mail_konten SET " . implode(', ', array_map(fn($s) => "$s = ?", array_keys($werte))) . " WHERE id = ?",
                array_merge(array_values($werte), [$id])
            );
        } else {
            $werte['benutzer_id'] = $funktion ? null : (int)Session::getUserId();
            $db->execute(
                "INSERT INTO mail_konten (" . implode(', ', array_keys($werte)) . ", erstellt_am) VALUES ("
                    . implode(', ', array_fill(0, count($werte), '?')) . ", NOW())",
                array_values($werte)
            );
            $id = (int)$db->lastInsertId();
        }
        if ($funktion) {
            $db->execute("DELETE FROM mail_konto_rollen WHERE konto_id = ?", [$id]);
            foreach (array_unique(array_map('intval', $rollen)) as $r) {
                if ($r > 0) $db->execute("INSERT INTO mail_konto_rollen (konto_id, rolle_id) VALUES (?, ?)", [$id, $r]);
            }
        }
        return $id;
    }

    public static function loeschen(int $id): void {
        self::zumBearbeiten($id); // prüft die Berechtigung
        Database::getInstance()->execute("DELETE FROM mail_konten WHERE id = ?", [$id]);
    }

    /** Alle Funktions-Postfächer mit ihren Rollen (für die Verwaltung) */
    public static function funktionsPostfaecher(): array {
        $db = Database::getInstance();
        $liste = $db->fetchAll("SELECT * FROM mail_konten WHERE benutzer_id IS NULL ORDER BY sortierung, bezeichnung");
        foreach ($liste as &$k) {
            $k['rollen_namen'] = array_column($db->fetchAll(
                "SELECT r.name FROM mail_konto_rollen mr JOIN rollen r ON r.id = mr.rolle_id WHERE mr.konto_id = ? ORDER BY r.name", [$k['id']]), 'name');
        }
        return $liste;
    }

    /** Verbindung zu Posteingang und Postausgang prüfen: ['imap' => true|Fehlertext, 'smtp' => true|Fehlertext] */
    public static function testen(array $konto, ?string $passwort = null): array {
        $passwort = $passwort ?? self::passwort($konto);
        $ergebnis = [];
        try {
            $imap = new ImapClient($konto['imap_host'], (int)$konto['imap_port'], $konto['imap_sicherheit']);
            $imap->anmelden($konto['benutzername'], $passwort);
            $imap->abmelden();
            $ergebnis['imap'] = true;
        } catch (\Throwable $e) {
            $ergebnis['imap'] = $e->getMessage();
        }
        try {
            MailSender::verbindungTesten($konto, $passwort);
            $ergebnis['smtp'] = true;
        } catch (\Throwable $e) {
            $ergebnis['smtp'] = $e->getMessage();
        }
        return $ergebnis;
    }
}
