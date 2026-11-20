<?php
// classes/MailSender.php
// E-Mails über SMTP versenden (eigene Umsetzung, ohne Fremdbibliothek): SSL/STARTTLS, Anmeldung PLAIN/LOGIN,
// danach Ablage einer Kopie im Ordner „Gesendet“ des Postfachs (IMAP APPEND).

class MailSender {

    /**
     * Versendet eine Nachricht aus dem Postfach $konto. $n wie MailNachricht::bauen (ohne 'von');
     * optional $n['ablage'] = Ordner für die gesendete Kopie (sonst „Gesendet“).
     * Liefert ['message_id' => …, 'gesendet_ordner' => Ordner|null].
     */
    public static function senden(array $konto, array $n): array {
        if (empty($n['an']) && empty($n['cc']) && empty($n['bcc'])) {
            throw new RuntimeException('Bitte mindestens einen Empfänger angeben.');
        }
        $passwort = MailKonto::passwort($konto);
        $n['von'] = [$konto['email'], $konto['anzeigename'] ?: ''];
        $nachricht = MailNachricht::bauen($n);

        $smtp = self::verbinden($konto, $passwort);
        try {
            $smtp->befehl('MAIL FROM:<' . $konto['email'] . '>', [250]);
            foreach ($nachricht['empfaenger'] as $adresse) {
                if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException("Ungültige Empfänger-Adresse: $adresse");
                }
                $smtp->befehl('RCPT TO:<' . $adresse . '>', [250, 251], "Empfänger $adresse wurde abgelehnt");
            }
            $smtp->befehl('DATA', [354]);
            // Punkt am Zeilenanfang verdoppeln (RFC 5321)
            $daten = preg_replace('/^\./m', '..', $nachricht['roh']);
            $smtp->schreibenRoh(rtrim($daten, "\r\n") . "\r\n.\r\n");
            $smtp->antwort([250], 'Die Nachricht wurde vom Server nicht angenommen');
            $smtp->befehl('QUIT', [221], null, true);
        } finally {
            $smtp->schliessen();
        }

        // Kopie in „Gesendet“ ablegen – ein Fehler hier macht das Versenden nicht ungeschehen
        $ordner = null;
        try {
            $imap = new ImapClient($konto['imap_host'], (int)$konto['imap_port'], $konto['imap_sicherheit']);
            $imap->anmelden($konto['benutzername'], $passwort);
            $ordner = $imap->sonderordner('gesendet');
            if (!empty($n['ablage']) && in_array($n['ablage'], array_column($imap->ordner(), 'name'), true)) {
                $ordner = $n['ablage'];
            }
            if ($ordner !== null) {
                $imap->ablegen($ordner, $nachricht['roh'], ['\\Seen']);
            }
            $imap->abmelden();
        } catch (\Throwable $e) {
            error_log('Syncopa Mail: Kopie in Gesendet fehlgeschlagen: ' . $e->getMessage());
        }
        return ['message_id' => $nachricht['message_id'], 'gesendet_ordner' => $ordner];
    }

    /** Prüft Verbindung und Anmeldung am Postausgangsserver */
    public static function verbindungTesten(array $konto, string $passwort): void {
        $smtp = self::verbinden($konto, $passwort);
        $smtp->befehl('QUIT', [221], null, true);
        $smtp->schliessen();
    }

    private static function verbinden(array $konto, string $passwort): SmtpVerbindung {
        $smtp = new SmtpVerbindung($konto['smtp_host'], (int)$konto['smtp_port'], $konto['smtp_sicherheit']);
        $smtp->anmelden($konto['benutzername'], $passwort);
        return $smtp;
    }
}

/** SMTP-Gespräch (RFC 5321) */
class SmtpVerbindung {
    private MailVerbindung $v;
    private array $faehigkeiten = [];

    public function __construct(string $host, int $port, string $sicherheit) {
        $this->v = new MailVerbindung($host, $port, $sicherheit);
        $this->antwort([220], 'Der Postausgangsserver hat die Verbindung abgelehnt');
        $this->hallo();
        if ($sicherheit === 'tls') {
            if (!isset($this->faehigkeiten['STARTTLS'])) {
                throw new RuntimeException('Der Postausgangsserver unterstützt kein STARTTLS – bitte „SSL/TLS“ oder einen anderen Port wählen.');
            }
            $this->befehl('STARTTLS', [220]);
            $this->v->tlsStarten();
            $this->hallo();
        }
    }

    private function hallo(): void {
        $name = preg_replace('/[^a-z0-9.-]/i', '', gethostname() ?: 'syncopa') ?: 'syncopa';
        $zeilen = $this->befehl('EHLO ' . $name, [250]);
        $this->faehigkeiten = [];
        foreach ($zeilen as $z) {
            $teile = preg_split('/\s+/', strtoupper(trim(substr($z, 4))));
            $this->faehigkeiten[$teile[0]] = array_slice($teile, 1);
        }
    }

    public function anmelden(string $benutzer, string $passwort): void {
        $verfahren = $this->faehigkeiten['AUTH'] ?? [];
        try {
            if (in_array('PLAIN', $verfahren, true) || !$verfahren) {
                $this->befehl('AUTH PLAIN ' . base64_encode("\0$benutzer\0$passwort"), [235]);
            } else {
                $this->befehl('AUTH LOGIN', [334]);
                $this->befehl(base64_encode($benutzer), [334]);
                $this->befehl(base64_encode($passwort), [235]);
            }
        } catch (RuntimeException $e) {
            throw new RuntimeException('Anmeldung am Postausgangsserver fehlgeschlagen – Benutzername oder Passwort falsch? (' . $e->getMessage() . ')');
        }
    }

    /** Befehl senden und Antwort prüfen; liefert die Antwortzeilen */
    public function befehl(string $befehl, array $erwartet, ?string $fehlertext = null, bool $fehlerEgal = false): array {
        $this->v->schreiben($befehl . "\r\n");
        try {
            return $this->antwort($erwartet, $fehlertext);
        } catch (RuntimeException $e) {
            if ($fehlerEgal) return [];
            throw $e;
        }
    }

    public function schreibenRoh(string $daten): void {
        $this->v->schreiben($daten);
    }

    /** Liest eine (mehrzeilige) Antwort und prüft den Code */
    public function antwort(array $erwartet, ?string $fehlertext = null): array {
        $zeilen = [];
        do {
            $z = $this->v->zeile();
            $zeilen[] = rtrim($z, "\r\n");
        } while (strlen($z) > 3 && $z[3] === '-');
        $code = (int)substr(end($zeilen), 0, 3);
        if (!in_array($code, $erwartet, true)) {
            $text = trim(substr(end($zeilen), 4));
            throw new RuntimeException(($fehlertext ? $fehlertext . ': ' : '') . "Server meldet $code $text");
        }
        return $zeilen;
    }

    public function schliessen(): void {
        $this->v->schliessen();
    }
}
