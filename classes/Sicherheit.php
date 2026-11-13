<?php
// classes/Sicherheit.php
// Zentrale Schutzmaßnahmen: Prüfzeichen gegen untergeschobene Aktionen (CSRF), Schutz-Kopfzeilen,
// Sperre nach Fehlversuchen bei der Anmeldung, persönliche Kalender-Schlüssel.

class Sicherheit {

    /** Fehlversuche je Benutzername innerhalb des Zeitfensters, ab denen gesperrt wird */
    public const MAX_FEHLVERSUCHE_BENUTZER = 5;
    /** Fehlversuche je IP-Adresse innerhalb des Zeitfensters, ab denen gesperrt wird */
    public const MAX_FEHLVERSUCHE_IP = 20;
    /** Zeitfenster und Sperrdauer in Minuten */
    public const SPERRE_MINUTEN = 15;
    /** Mindestlänge für Passwörter */
    public const PASSWORT_MIN = 8;
    /** Bekannte Test- und Standardpasswörter, die nie verwendet werden dürfen */
    public const STANDARD_PASSWOERTER = ['admin', 'admin123', 'passwort', 'password', '12345678', '123456789', 'syncopa', 'musikverein', 'test1234'];

    // ------------------------------------------------------------------ Verbindung

    /** Läuft die aktuelle Anfrage verschlüsselt? (direkt oder hinter einem Proxy wie Cloudflare) */
    public static function istHttps(): bool {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
        if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
        if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
        $cf = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');
        return $cf !== '' && str_contains($cf, '"https"');
    }

    /** IP-Adresse des Besuchers (hinter Cloudflare die echte Adresse) */
    public static function ip(): string {
        $ip = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }

    // ------------------------------------------------------------------ Anfrage schützen

    /**
     * Wird bei jeder Web-Anfrage aus includes.php aufgerufen:
     * Schutz-Kopfzeilen senden und POST-Anfragen angemeldeter Benutzer auf das Prüfzeichen prüfen.
     */
    public static function anfrageSchuetzen(): void {
        if (PHP_SAPI === 'cli' && empty($_SERVER['REQUEST_METHOD'])) return; // Cronjobs / Kommandozeile
        self::kopfzeilenSenden();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
        Session::start();
        if (!Session::isLoggedIn()) return; // Anmeldung selbst, öffentliche Seiten
        if (!self::pruefzeichenGueltig()) {
            self::abweisen();
        }
        // Prüfzeichen aus den Formulardaten entfernen, damit keine Seite es mitverarbeitet oder speichert
        unset($_POST['_csrf'], $_REQUEST['_csrf']);
    }

    public static function kopfzeilenSenden(): void {
        if (headers_sent()) return;
        header_remove('X-Powered-By');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        if (self::istHttps()) {
            header('Strict-Transport-Security: max-age=15552000');
        }
    }

    /** Prüfzeichen dieser Sitzung (wird bei Bedarf erzeugt) */
    public static function pruefzeichen(): string {
        Session::start();
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Verstecktes Formularfeld mit dem Prüfzeichen (für Formulare, die ohne Footer-Skript abgeschickt werden) */
    public static function feld(): string {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::pruefzeichen(), ENT_QUOTES) . '">';
    }

    public static function pruefzeichenGueltig(): bool {
        $soll = $_SESSION['csrf_token'] ?? '';
        $ist = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return is_string($soll) && $soll !== '' && $ist !== '' && hash_equals($soll, $ist);
    }

    private static function abweisen(): void {
        http_response_code(403);
        $text = 'Die Anfrage wurde aus Sicherheitsgründen abgelehnt (fehlendes oder veraltetes Prüfzeichen). Bitte die Seite neu laden und noch einmal versuchen.';
        $erwartetJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/api/')
            || !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
            || strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if ($erwartetJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => $text, 'message' => $text]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="de"><meta charset="utf-8"><title>Abgelehnt</title>'
               . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:60px auto;padding:0 16px;color:#2c3e50">'
               . '<h1 style="font-size:20px">Anfrage abgelehnt</h1><p>' . htmlspecialchars($text) . '</p>'
               . '<p><a href="javascript:history.back()">Zurück</a></p></body></html>';
        }
        exit;
    }

    // ------------------------------------------------------------------ Anmeldung

    /** Restliche Sperrzeit in Minuten (0 = nicht gesperrt) */
    public static function anmeldungGesperrt(string $benutzername): int {
        $db = Database::getInstance();
        $fenster = self::SPERRE_MINUTEN;
        $proBenutzer = $db->fetchOne(
            "SELECT COUNT(*) AS n, MAX(zeit) AS letzter FROM login_versuche
             WHERE benutzername = ? AND erfolg = 0 AND zeit > NOW() - INTERVAL $fenster MINUTE",
            [mb_strtolower(trim($benutzername))]
        );
        $proIp = $db->fetchOne(
            "SELECT COUNT(*) AS n, MAX(zeit) AS letzter FROM login_versuche
             WHERE ip = ? AND erfolg = 0 AND zeit > NOW() - INTERVAL $fenster MINUTE",
            [self::ip()]
        );
        $letzter = null;
        if ((int)($proBenutzer['n'] ?? 0) >= self::MAX_FEHLVERSUCHE_BENUTZER) $letzter = $proBenutzer['letzter'];
        if ((int)($proIp['n'] ?? 0) >= self::MAX_FEHLVERSUCHE_IP) $letzter = max((string)$letzter, (string)$proIp['letzter']);
        if (!$letzter) return 0;
        $ende = strtotime($letzter) + $fenster * 60;
        return max(1, (int)ceil(($ende - time()) / 60));
    }

    public static function anmeldungProtokollieren(string $benutzername, bool $erfolg): void {
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO login_versuche (benutzername, ip, erfolg, zeit) VALUES (?, ?, ?, NOW())",
            [mb_substr(mb_strtolower(trim($benutzername)), 0, 100), self::ip(), $erfolg ? 1 : 0]
        );
        if ($erfolg) {
            // Erfolgreiche Anmeldung hebt die Fehlversuche dieses Benutzers auf
            $db->execute("DELETE FROM login_versuche WHERE benutzername = ? AND erfolg = 0", [mb_strtolower(trim($benutzername))]);
        }
        if (random_int(1, 50) === 1) {
            $db->execute("DELETE FROM login_versuche WHERE zeit < NOW() - INTERVAL 30 DAY");
        }
    }

    /** Nach erfolgreicher Anmeldung: neue Sitzungskennung und neues Prüfzeichen */
    public static function sitzungErneuern(): void {
        Session::start();
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /** Fehlermeldung, wenn ein neues Passwort die Regeln nicht erfüllt – sonst null */
    public static function passwortFehler(string $passwort): ?string {
        if (mb_strlen($passwort) < self::PASSWORT_MIN) {
            return 'Das Passwort muss mindestens ' . self::PASSWORT_MIN . ' Zeichen lang sein.';
        }
        if (in_array(mb_strtolower($passwort), self::STANDARD_PASSWOERTER, true)) {
            return 'Dieses Passwort ist zu bekannt. Bitte ein anderes wählen.';
        }
        return null;
    }

    /** Aktive Benutzer, deren Passwort ein bekanntes Standardpasswort ist */
    public static function kontenMitStandardpasswort(): array {
        $treffer = [];
        foreach (Database::getInstance()->fetchAll("SELECT id, benutzername, passwort_hash FROM benutzer WHERE aktiv = 1 AND passwort_hash IS NOT NULL AND passwort_hash <> ''") as $b) {
            foreach (self::STANDARD_PASSWOERTER as $pw) {
                if (password_verify($pw, $b['passwort_hash'])) { $treffer[] = $b['benutzername']; break; }
            }
        }
        return $treffer;
    }

    // ------------------------------------------------------------------ Kalender-Abo

    /** Persönlicher, geheimer Schlüssel für das Kalender-Abo (wird bei Bedarf erzeugt) */
    public static function kalenderSchluessel(int $benutzerId, bool $neu = false): string {
        $db = Database::getInstance();
        if (!$neu) {
            $s = $db->fetchOne("SELECT kalender_token FROM benutzer WHERE id = ?", [$benutzerId])['kalender_token'] ?? '';
            if ($s) return $s;
        }
        $s = bin2hex(random_bytes(20));
        $db->execute("UPDATE benutzer SET kalender_token = ? WHERE id = ?", [$s, $benutzerId]);
        return $s;
    }

    /** Benutzer zum Kalender-Schlüssel (nur aktive), sonst null */
    public static function benutzerZuKalenderSchluessel(string $schluessel): ?array {
        if (!preg_match('/^[a-f0-9]{40}$/', $schluessel)) return null;
        return Database::getInstance()->fetchOne("SELECT id, benutzername FROM benutzer WHERE kalender_token = ? AND aktiv = 1", [$schluessel]) ?: null;
    }

    // ------------------------------------------------------------------ Geheimnisse in den Einstellungen

    /** Wert verschlüsselt zum Speichern (gleicher Schlüssel wie die Postfach-Passwörter) */
    public static function geheimVerschluesseln(string $klartext): string {
        return $klartext === '' ? '' : 'enc:' . MailKrypto::verschluesseln($klartext);
    }

    /** Gespeicherten Wert lesen – verschlüsselt oder (alt) im Klartext */
    public static function geheimLesen(?string $gespeichert): string {
        $gespeichert = (string)$gespeichert;
        if (!str_starts_with($gespeichert, 'enc:')) return $gespeichert;
        try {
            return MailKrypto::entschluesseln(substr($gespeichert, 4));
        } catch (\Throwable $e) {
            return '';
        }
    }
}
