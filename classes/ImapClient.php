<?php
// classes/ImapClient.php
// IMAP4rev1-Client (RFC 3501) ohne PHP-Erweiterung: Anmelden, Ordner (inkl. Sonderordner nach RFC 6154),
// Nachrichtenliste, Nachricht laden, Kennzeichen (gelesen, markiert, beantwortet), Verschieben, Löschen,
// Ablegen (APPEND, z. B. in „Gesendet“). Ordnernamen in „modified UTF-7“ werden umgerechnet.

class ImapClient {

    private MailVerbindung $v;
    private int $nr = 0;
    private array $faehigkeiten = [];
    private ?string $gewaehlt = null;
    private ?array $ordnerCache = null;

    /** Sonderordner: Rolle => [RFC-6154-Kennzeichen, übliche Namen] */
    private const SONDER = [
        'posteingang' => ['', ['INBOX']],
        'entwuerfe'   => ['\\Drafts', ['Drafts', 'Entwürfe', 'Entwuerfe', 'INBOX.Drafts', 'INBOX.Entwürfe']],
        'gesendet'    => ['\\Sent', ['Sent', 'Gesendet', 'Gesendete Elemente', 'Sent Items', 'Sent Messages', 'INBOX.Sent', 'INBOX.Gesendet', 'Gesendete Objekte']],
        'archiv'      => ['\\Archive', ['Archive', 'Archiv', 'INBOX.Archive']],
        'spam'        => ['\\Junk', ['Junk', 'Spam', 'Junk-E-Mail', 'INBOX.Junk', 'INBOX.Spam', 'Junk E-Mail']],
        'papierkorb'  => ['\\Trash', ['Trash', 'Papierkorb', 'Gelöschte Elemente', 'Deleted Items', 'Deleted Messages', 'INBOX.Trash', 'Gelöschte Objekte']],
    ];

    public function __construct(string $host, int $port, string $sicherheit) {
        $this->v = new MailVerbindung($host, $port, $sicherheit);
        $begruessung = $this->v->zeile();
        if (!preg_match('/^\* (OK|PREAUTH)/i', $begruessung)) {
            throw new RuntimeException('Der Posteingangsserver hat die Verbindung abgelehnt: ' . trim($begruessung));
        }
        $this->faehigkeitenLesen($begruessung);
        if ($sicherheit === 'tls') {
            if (!$this->kann('STARTTLS')) {
                $this->faehigkeitenAbfragen();
            }
            if (!$this->kann('STARTTLS')) {
                throw new RuntimeException('Der Posteingangsserver unterstützt kein STARTTLS – bitte „SSL/TLS“ (Port 993) wählen.');
            }
            $this->befehl('STARTTLS');
            $this->v->tlsStarten();
            $this->faehigkeiten = [];
        }
    }

    // ------------------------------------------------------------------------------------------------
    // Sitzung
    // ------------------------------------------------------------------------------------------------

    public function anmelden(string $benutzer, string $passwort): void {
        if (!$this->faehigkeiten) {
            $this->faehigkeitenAbfragen();
        }
        try {
            if ($this->kann('AUTH=PLAIN')) {
                $this->befehl('AUTHENTICATE PLAIN', [base64_encode("\0$benutzer\0$passwort")], true);
            } else {
                $this->befehl('LOGIN ' . $this->zeichenkette($benutzer) . ' ' . $this->zeichenkette($passwort));
            }
        } catch (RuntimeException $e) {
            throw new RuntimeException('Anmeldung am Posteingangsserver fehlgeschlagen – Benutzername oder Passwort falsch? (' . $e->getMessage() . ')');
        }
        $this->faehigkeitenAbfragen(); // nach der Anmeldung können weitere dazukommen (MOVE, UIDPLUS, SPECIAL-USE)
    }

    public function abmelden(): void {
        try {
            $this->befehl('LOGOUT');
        } catch (\Throwable $e) {
        }
        $this->v->schliessen();
    }

    public function kann(string $faehigkeit): bool {
        return isset($this->faehigkeiten[strtoupper($faehigkeit)]);
    }

    private function faehigkeitenAbfragen(): void {
        foreach ($this->befehl('CAPABILITY') as $antwort) {
            $this->faehigkeitenLesen($antwort['text']);
        }
    }

    private function faehigkeitenLesen(string $text): void {
        if (preg_match('/CAPABILITY ([^\]\r\n]*)/i', $text, $m)) {
            foreach (preg_split('/\s+/', trim($m[1])) as $f) {
                $this->faehigkeiten[strtoupper($f)] = true;
            }
        }
    }

    // ------------------------------------------------------------------------------------------------
    // Ordner
    // ------------------------------------------------------------------------------------------------

    /**
     * Alle Ordner: [['name' => Servername, 'anzeige' => UTF-8, 'trenner', 'rolle' => posteingang|gesendet|…|null,
     *              'auswaehlbar' => bool, 'ebene' => int]]
     */
    public function ordner(): array {
        if ($this->ordnerCache !== null) {
            return $this->ordnerCache;
        }
        $liste = [];
        foreach ($this->befehl('LIST "" "*"') as $a) {
            if ($a['typ'] !== 'LIST') continue;
            $teile = self::zerlegeAntwort($a['text'], 2); // LIST (flags) "trenner" name
            [$flags, $trenner, $name] = [$teile[1] ?? [], $teile[2] ?? null, (string)($teile[3] ?? '')];
            $flags = array_map('strtolower', (array)$flags);
            $liste[] = [
                'name'        => $name,
                'anzeige'     => strtoupper($name) === 'INBOX' ? 'Posteingang' : self::utf7Dekodieren($name),
                'trenner'     => (string)$trenner,
                'flags'       => $flags,
                'auswaehlbar' => !in_array('\\noselect', $flags, true) && !in_array('\\nonexistent', $flags, true),
            ];
        }
        // Baum: Liegen alle Ordner unter „INBOX.“ (Server mit Namensraum-Präfix), ist das nur ein Präfix;
        // sonst sind „INBOX.x“ echte Unterordner des Posteingangs.
        $trenner = '';
        foreach ($liste as $o) { if ($o['trenner'] !== '') { $trenner = $o['trenner']; break; } }
        $andere = array_filter($liste, fn($o) => strtoupper($o['name']) !== 'INBOX');
        $praefix = ($trenner !== '' && $andere && !array_filter($andere, fn($o) => !str_starts_with(strtoupper($o['name']), 'INBOX' . $trenner)))
            ? 'INBOX' . $trenner : '';
        foreach ($liste as &$o) {
            if (strtoupper($o['name']) === 'INBOX') {
                $o['pfad'] = ['INBOX'];
            } else {
                $rel = ($praefix !== '' && str_starts_with(strtoupper($o['name']), $praefix)) ? substr($o['name'], strlen($praefix)) : $o['name'];
                $o['pfad'] = $o['trenner'] !== '' ? explode($o['trenner'], $rel) : [$rel];
                $o['anzeige'] = self::utf7Dekodieren(end($o['pfad']));
            }
            $o['ebene'] = count($o['pfad']) - 1;
        }
        unset($o);

        // Rollen zuordnen: zuerst über Kennzeichen, sonst über bekannte Namen
        foreach (self::SONDER as $rolle => [$kennzeichen, $namen]) {
            $treffer = null;
            foreach ($liste as $i => $o) {
                if ($kennzeichen !== '' && in_array(strtolower($kennzeichen), $o['flags'], true)) { $treffer = $i; break; }
            }
            if ($treffer === null) {
                foreach ($namen as $n) {
                    foreach ($liste as $i => $o) {
                        if (strcasecmp($o['name'], $n) === 0 || strcasecmp($o['anzeige'], $n) === 0) { $treffer = $i; break 2; }
                    }
                }
            }
            if ($treffer !== null && !isset($liste[$treffer]['rolle'])) {
                $liste[$treffer]['rolle'] = $rolle;
            }
        }
        $reihenfolge = array_flip(array_keys(self::SONDER));
        $rangOben = [];
        foreach ($liste as &$o) {
            $o['rolle'] ??= null;
            if ($o['ebene'] === 0) $rangOben[$o['pfad'][0]] = $o['rolle'] !== null ? $reihenfolge[$o['rolle']] : 99;
        }
        unset($o);
        // Sortierung als Baum: oben Posteingang und Sonderordner, sonst alphabetisch; Unterordner direkt unter ihrem Elternordner
        usort($liste, function ($a, $b) use ($rangOben) {
            $n = max(count($a['pfad']), count($b['pfad']));
            for ($i = 0; $i < $n; $i++) {
                if (!isset($a['pfad'][$i])) return -1;
                if (!isset($b['pfad'][$i])) return 1;
                if ($a['pfad'][$i] === $b['pfad'][$i]) continue;
                if ($i === 0) {
                    $r = ($rangOben[$a['pfad'][0]] ?? 99) <=> ($rangOben[$b['pfad'][0]] ?? 99);
                    if ($r) return $r;
                }
                return strnatcasecmp(self::utf7Dekodieren($a['pfad'][$i]), self::utf7Dekodieren($b['pfad'][$i]));
            }
            return 0;
        });
        return $this->ordnerCache = $liste;
    }

    /** Servername des Sonderordners (posteingang, gesendet, entwuerfe, papierkorb, spam, archiv) oder null */
    public function sonderordner(string $rolle): ?string {
        foreach ($this->ordner() as $o) {
            if ($o['rolle'] === $rolle) return $o['name'];
        }
        return null;
    }

    /**
     * Legt einen Ordner auf dem Server an (dadurch auch in Outlook, am Handy usw. sichtbar) und abonniert ihn.
     * $eltern = Servername des übergeordneten Ordners oder null für die oberste Ebene. Liefert den Servernamen.
     */
    public function ordnerAnlegen(?string $eltern, string $name): string {
        $name = self::ordnernamePruefen($name);
        $trenner = $this->trenner($eltern);
        if ($eltern === null) {
            $ziel = $this->namensraum($trenner) . self::utf7Kodieren($name);
        } else {
            $ziel = $eltern . $trenner . self::utf7Kodieren($name);
        }
        if (str_contains($name, $trenner)) {
            throw new RuntimeException("Der Ordnername darf das Zeichen „{$trenner}“ nicht enthalten.");
        }
        foreach ($this->ordner() as $o) {
            if (strcasecmp($o['name'], $ziel) === 0) throw new RuntimeException("Den Ordner „{$name}“ gibt es schon.");
        }
        $this->befehl('CREATE ' . $this->zeichenkette($ziel));
        try { $this->befehl('SUBSCRIBE ' . $this->zeichenkette($ziel)); } catch (\Throwable $e) {}
        $this->ordnerCache = null;
        return $ziel;
    }

    /** Benennt einen Ordner um (bleibt an derselben Stelle). Liefert den neuen Servernamen. */
    public function ordnerUmbenennen(string $alt, string $neuerName): string {
        $neuerName = self::ordnernamePruefen($neuerName);
        $trenner = $this->trenner($alt);
        if ($trenner !== '' && str_contains($neuerName, $trenner)) {
            throw new RuntimeException("Der Ordnername darf das Zeichen „{$trenner}“ nicht enthalten.");
        }
        $pos = $trenner !== '' ? strrpos($alt, $trenner) : false;
        $neu = ($pos === false ? '' : substr($alt, 0, $pos + strlen($trenner))) . self::utf7Kodieren($neuerName);
        if ($neu === $alt) return $alt;
        $this->befehl('RENAME ' . $this->zeichenkette($alt) . ' ' . $this->zeichenkette($neu));
        try {
            $this->befehl('UNSUBSCRIBE ' . $this->zeichenkette($alt));
            $this->befehl('SUBSCRIBE ' . $this->zeichenkette($neu));
        } catch (\Throwable $e) {}
        $this->ordnerCache = null;
        $this->gewaehlt = null;
        return $neu;
    }

    /** Löscht einen Ordner auf dem Server (manche Server verlangen, dass er leer ist) */
    public function ordnerLoeschen(string $name): void {
        if ($this->gewaehlt === $name) {
            try { $this->befehl('CLOSE'); } catch (\Throwable $e) {}
            $this->gewaehlt = null;
        }
        try { $this->befehl('UNSUBSCRIBE ' . $this->zeichenkette($name)); } catch (\Throwable $e) {}
        $this->befehl('DELETE ' . $this->zeichenkette($name));
        $this->ordnerCache = null;
    }

    /** Hierarchie-Trennzeichen (vom Ordner bzw. vom Posteingang) */
    private function trenner(?string $ordner): string {
        $liste = $this->ordner();
        foreach ($liste as $o) {
            if ($ordner !== null && $o['name'] === $ordner && $o['trenner'] !== '') return $o['trenner'];
        }
        foreach ($liste as $o) {
            if ($o['trenner'] !== '') return $o['trenner'];
        }
        return '/';
    }

    /** Präfix für Ordner der obersten Ebene: manche Server legen alles unter „INBOX.“ ab */
    private function namensraum(string $trenner): string {
        $andere = array_filter($this->ordner(), fn($o) => strtoupper($o['name']) !== 'INBOX');
        if ($andere && !array_filter($andere, fn($o) => !str_starts_with(strtoupper($o['name']), 'INBOX' . $trenner))) {
            return 'INBOX' . $trenner;
        }
        return '';
    }

    private static function ordnernamePruefen(string $name): string {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($name === '') throw new RuntimeException('Bitte einen Ordnernamen eingeben.');
        if (mb_strlen($name) > 100) throw new RuntimeException('Der Ordnername ist zu lang.');
        if (preg_match('/[\x00-\x1f%*"\\\\]/', $name)) throw new RuntimeException('Der Ordnername enthält ungültige Zeichen (% * " \\).');
        return $name;
    }

    /** UTF-8 → IMAP „modified UTF-7“ */
    public static function utf7Kodieren(string $name): string {
        return mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8');
    }

    /** Anzahl Nachrichten und ungelesene: ['gesamt' => n, 'ungelesen' => n] */
    public function status(string $ordner): array {
        foreach ($this->befehl('STATUS ' . $this->zeichenkette($ordner) . ' (MESSAGES UNSEEN)') as $a) {
            if ($a['typ'] === 'STATUS' && preg_match('/MESSAGES (\d+)/i', $a['text'], $m1) && preg_match('/UNSEEN (\d+)/i', $a['text'], $m2)) {
                return ['gesamt' => (int)$m1[1], 'ungelesen' => (int)$m2[1]];
            }
        }
        return ['gesamt' => 0, 'ungelesen' => 0];
    }

    public function waehlen(string $ordner, bool $nurLesen = false): void {
        if ($this->gewaehlt === $ordner) return;
        $this->befehl(($nurLesen ? 'EXAMINE ' : 'SELECT ') . $this->zeichenkette($ordner));
        $this->gewaehlt = $ordner;
    }

    // ------------------------------------------------------------------------------------------------
    // Nachrichten
    // ------------------------------------------------------------------------------------------------

    /** UIDs im gewählten Ordner, aufsteigend; optional Volltextsuche */
    public function suchen(?string $text = null, bool $nurUngelesen = false): array {
        $kriterien = $nurUngelesen ? 'UNSEEN' : 'ALL';
        $literale = [];
        if ($text !== null && trim($text) !== '') {
            $kriterien = ($nurUngelesen ? 'UNSEEN ' : '') . 'TEXT {' . strlen($text) . '}';
            $literale[] = $text;
            $kriterien = 'CHARSET UTF-8 ' . $kriterien;
        }
        $uids = [];
        foreach ($this->befehl('UID SEARCH ' . $kriterien, $literale) as $a) {
            if ($a['typ'] === 'SEARCH' && preg_match('/^\* SEARCH(.*)$/i', $a['text'], $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) as $u) {
                    if (ctype_digit($u)) $uids[] = (int)$u;
                }
            }
        }
        sort($uids);
        return $uids;
    }

    /**
     * Kopfdaten für die Liste: [uid => ['uid', 'gelesen', 'markiert', 'beantwortet', 'datum', 'groesse',
     * 'von', 'an', 'betreff', 'anhang']]
     */
    public function uebersicht(array $uids): array {
        if (!$uids) return [];
        $ergebnis = [];
        $felder = 'UID FLAGS INTERNALDATE RFC822.SIZE BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE CONTENT-TYPE)]';
        foreach ($this->befehl('UID FETCH ' . implode(',', $uids) . " ($felder)") as $a) {
            if ($a['typ'] !== 'FETCH') continue;
            $daten = self::fetchDaten($a['text']);
            if (!isset($daten['UID'])) continue;
            $flags = array_map('strtolower', (array)($daten['FLAGS'] ?? []));
            $kopfRoh = '';
            foreach ($daten as $k => $w) {
                if (str_starts_with($k, 'BODY[')) $kopfRoh = (string)$w;
            }
            $kopf = MailNachricht::zerlegen($kopfRoh . "\r\n");
            $typ = strtolower($kopf['kopf']['content-type'][0] ?? '');
            $uid = (int)$daten['UID'];
            $ergebnis[$uid] = [
                'uid'         => $uid,
                'gelesen'     => in_array('\\seen', $flags, true),
                'markiert'    => in_array('\\flagged', $flags, true),
                'beantwortet' => in_array('\\answered', $flags, true),
                'datum'       => $kopf['datum'] ?? (isset($daten['INTERNALDATE']) ? date('Y-m-d H:i:s', strtotime($daten['INTERNALDATE'])) : null),
                'groesse'     => (int)($daten['RFC822.SIZE'] ?? 0),
                'von'         => $kopf['von'],
                'an'          => $kopf['an'],
                'betreff'     => $kopf['betreff'],
                'anhang'      => str_starts_with($typ, 'multipart/mixed') || str_starts_with($typ, 'multipart/report'),
            ];
        }
        return $ergebnis;
    }

    /**
     * Ausgewählte Kopfzeilen (ohne Inhalt, ohne als gelesen zu markieren): [uid => ['kopf' => [name => [werte]], 'flags' => [...]]]
     * $felder z. B. ['FROM', 'SUBJECT', 'X-SPAM-FLAG']
     */
    public function kopfzeilen(array $uids, array $felder): array {
        if (!$uids) return [];
        $felder = array_map(fn($f) => preg_replace('/[^A-Z0-9-]/', '', strtoupper($f)), $felder);
        $ergebnis = [];
        $antworten = $this->befehl('UID FETCH ' . implode(',', array_map('intval', $uids)) . ' (UID FLAGS BODY.PEEK[HEADER.FIELDS (' . implode(' ', $felder) . ')])');
        foreach ($antworten as $a) {
            if ($a['typ'] !== 'FETCH') continue;
            $daten = self::fetchDaten($a['text']);
            if (!isset($daten['UID'])) continue;
            $roh = '';
            foreach ($daten as $k => $w) {
                if (str_starts_with($k, 'BODY[')) $roh = (string)$w;
            }
            $ergebnis[(int)$daten['UID']] = [
                'kopf'  => MailNachricht::zerlegen($roh . "\r\n")['kopf'],
                'flags' => array_map('strtolower', (array)($daten['FLAGS'] ?? [])),
            ];
        }
        return $ergebnis;
    }

    /** Ganze Nachricht (Rohtext) ohne sie als gelesen zu markieren; null wenn nicht vorhanden */
    public function rohNachricht(int $uid): ?string {
        foreach ($this->befehl("UID FETCH $uid (UID FLAGS BODY.PEEK[])") as $a) {
            if ($a['typ'] !== 'FETCH') continue;
            $daten = self::fetchDaten($a['text']);
            if ((int)($daten['UID'] ?? 0) === $uid && isset($daten['BODY[]'])) {
                return (string)$daten['BODY[]'];
            }
        }
        return null;
    }

    /** Kennzeichen setzen/entfernen: $kennzeichen z. B. '\Seen', '\Flagged', '\Answered' */
    public function kennzeichnen(array $uids, string $kennzeichen, bool $setzen): void {
        if (!$uids) return;
        $this->befehl('UID STORE ' . implode(',', array_map('intval', $uids)) . ' ' . ($setzen ? '+' : '-') . "FLAGS.SILENT ($kennzeichen)");
    }

    /** In einen anderen Ordner verschieben (MOVE, sonst COPY + löschen) */
    public function verschieben(array $uids, string $ziel): void {
        if (!$uids) return;
        $menge = implode(',', array_map('intval', $uids));
        if ($this->kann('MOVE')) {
            $this->befehl("UID MOVE $menge " . $this->zeichenkette($ziel));
            return;
        }
        $this->befehl("UID COPY $menge " . $this->zeichenkette($ziel));
        $this->endgueltigLoeschen($uids);
    }

    /** Endgültig löschen (\Deleted + Expunge, wenn möglich nur diese UIDs) */
    public function endgueltigLoeschen(array $uids): void {
        if (!$uids) return;
        $menge = implode(',', array_map('intval', $uids));
        $this->befehl("UID STORE $menge +FLAGS.SILENT (\\Deleted)");
        $this->befehl($this->kann('UIDPLUS') ? "UID EXPUNGE $menge" : 'EXPUNGE');
    }

    /** Nachricht in einem Ordner ablegen (z. B. gesendete Mail in „Gesendet“) */
    public function ablegen(string $ordner, string $roh, array $kennzeichen = []): void {
        $roh = preg_replace("/\r?\n/", "\r\n", $roh);
        $this->befehl('APPEND ' . $this->zeichenkette($ordner) . ' (' . implode(' ', $kennzeichen) . ') {' . strlen($roh) . '}', [$roh]);
    }

    // ------------------------------------------------------------------------------------------------
    // Protokoll
    // ------------------------------------------------------------------------------------------------

    /**
     * Sendet einen Befehl. Literale ({n} am Befehlsende bzw. $literale) werden nach der Freigabe („+“) nachgeschickt.
     * $alsFortsetzung: $literale sind SASL-Antworten (AUTHENTICATE) statt Literale.
     * Liefert die unmarkierten Antworten [['typ' => 'FETCH'|'LIST'|…, 'text' => vollständiger Text inkl. Literal-Daten]].
     */
    private function befehl(string $befehl, array $literale = [], bool $alsFortsetzung = false): array {
        $tag = 'S' . (++$this->nr);
        // Literale im Befehl: Text bis {n}\r\n senden, auf „+“ warten, Daten senden, weiter
        $stuecke = $alsFortsetzung ? [$befehl] : self::befehlTeilen($befehl);
        $antworten = [];
        $this->v->schreiben($tag . ' ' . array_shift($stuecke) . "\r\n");
        foreach ($literale as $i => $literal) {
            // auf Fortsetzungsanfrage warten
            while (true) {
                $zeile = $this->v->zeile();
                if (str_starts_with($zeile, '+')) break;
                if (str_starts_with($zeile, $tag . ' ')) {
                    throw new RuntimeException('Server lehnt ab: ' . trim(substr($zeile, strlen($tag) + 1)));
                }
                $antworten[] = $this->antwortVervollstaendigen($zeile);
            }
            $rest = $stuecke[$i] ?? '';
            $this->v->schreiben($literal . $rest . "\r\n");
        }
        while (true) {
            $zeile = $this->v->zeile();
            if (str_starts_with($zeile, $tag . ' ')) {
                $status = trim(substr($zeile, strlen($tag) + 1));
                if (!preg_match('/^OK\b/i', $status)) {
                    throw new RuntimeException('Server meldet: ' . preg_replace('/^(NO|BAD)\s*/i', '', $status));
                }
                $this->faehigkeitenLesen($status);
                return $antworten;
            }
            if (str_starts_with($zeile, '+')) {
                continue; // unerwartete Fortsetzungsanfrage ignorieren
            }
            $antworten[] = $this->antwortVervollstaendigen($zeile);
        }
    }

    /** Teilt einen Befehl an Literal-Stellen {n}: Teil vor dem ersten Literal, dann die Reste nach jedem Literal */
    private static function befehlTeilen(string $befehl): array {
        $teile = preg_split('/(?<=\})/', $befehl);
        $ergebnis = [];
        $aktuell = '';
        foreach ($teile as $t) {
            $aktuell .= $t;
            if (preg_match('/\{\d+\}$/', $t)) {
                $ergebnis[] = $aktuell;
                $aktuell = '';
            }
        }
        if (!$ergebnis) return [$befehl];
        $ergebnis[] = $aktuell; // Rest nach dem letzten Literal
        // Form: [vor Literal 1, Rest nach Literal 1 (bis Literal 2), …]
        $erstes = array_shift($ergebnis);
        return array_merge([$erstes], $ergebnis);
    }

    /** Liest eine unmarkierte Antwort vollständig (inkl. Literal-Daten) */
    private function antwortVervollstaendigen(string $zeile): array {
        $text = $zeile;
        while (preg_match('/\{(\d+)\}\r?\n$/', $text, $m)) {
            $text .= $this->v->bytes((int)$m[1]);
            $text .= $this->v->zeile();
        }
        $text = rtrim($text, "\r\n");
        $typ = '';
        if (preg_match('/^\* (?:\d+ )?([A-Z]+)/i', $text, $m)) {
            $typ = strtoupper($m[1]);
        }
        return ['typ' => $typ, 'text' => $text];
    }

    /** Zeichenkette als IMAP-String in Anführungszeichen (Ordnernamen kommen bereits in modified UTF-7 vom Server) */
    private function zeichenkette(string $wert): string {
        return '"' . addcslashes($wert, '"\\') . '"';
    }

    // ------------------------------------------------------------------------------------------------
    // Antworten zerlegen
    // ------------------------------------------------------------------------------------------------

    /** Zerlegt den Text einer Antwort ab Wort $ab (0 = „*“) in Token: Atome, Strings, NIL (null), Listen (Arrays) */
    public static function zerlegeAntwort(string $text, int $ab = 0): array {
        $pos = 0;
        $token = [];
        $len = strlen($text);
        while ($pos < $len) {
            $t = self::token($text, $pos);
            if ($t === self::class) break;
            $token[] = $t;
        }
        return array_slice($token, max(0, $ab - 1));
    }

    /** Liefert das nächste Token ab $pos; self::class am Ende */
    private static function token(string $s, int &$pos) {
        $len = strlen($s);
        while ($pos < $len && ($s[$pos] === ' ' || $s[$pos] === "\r" || $s[$pos] === "\n")) $pos++;
        if ($pos >= $len) return self::class;
        $c = $s[$pos];
        if ($c === '(') {
            $pos++;
            $liste = [];
            while (true) {
                while ($pos < $len && $s[$pos] === ' ') $pos++;
                if ($pos >= $len) return $liste;
                if ($s[$pos] === ')') { $pos++; return $liste; }
                $liste[] = self::token($s, $pos);
            }
        }
        if ($c === ')') { $pos++; return self::token($s, $pos); }
        if ($c === '"') {
            $pos++;
            $wert = '';
            while ($pos < $len && $s[$pos] !== '"') {
                if ($s[$pos] === '\\' && $pos + 1 < $len) $pos++;
                $wert .= $s[$pos++];
            }
            $pos++;
            return $wert;
        }
        if ($c === '{' && preg_match('/\G\{(\d+)\}\r\n/', $s, $m, 0, $pos)) {
            $pos += strlen($m[0]);
            $wert = substr($s, $pos, (int)$m[1]);
            $pos += (int)$m[1];
            return $wert;
        }
        // Atom (mit [...] als Teil des Atoms, z. B. BODY[HEADER.FIELDS (FROM)])
        $start = $pos;
        $klammer = 0;
        while ($pos < $len) {
            $z = $s[$pos];
            if ($z === '[') $klammer++;
            elseif ($z === ']') $klammer--;
            elseif ($klammer === 0 && ($z === ' ' || $z === '(' || $z === ')' || $z === "\r" || $z === "\n")) break;
            $pos++;
        }
        $atom = substr($s, $start, $pos - $start);
        return strtoupper($atom) === 'NIL' ? null : $atom;
    }

    /** „* 12 FETCH (UID 5 FLAGS (\Seen) …)“ → ['UID' => '5', 'FLAGS' => [...], …] */
    public static function fetchDaten(string $text): array {
        $token = self::zerlegeAntwort($text);
        $liste = null;
        foreach ($token as $t) {
            if (is_array($t)) { $liste = $t; break; }
        }
        $daten = [];
        for ($i = 0; $liste !== null && $i + 1 < count($liste); $i += 2) {
            $schluessel = strtoupper((string)$liste[$i]);
            // BODY[...]<0> → BODY[...]
            $schluessel = preg_replace('/<\d+>$/', '', $schluessel);
            if (str_starts_with($schluessel, 'BODY.PEEK[')) $schluessel = 'BODY[' . substr($schluessel, 10);
            $daten[$schluessel] = $liste[$i + 1];
        }
        return $daten;
    }

    /** IMAP „modified UTF-7“ (RFC 3501, 5.1.3) → UTF-8 */
    public static function utf7Dekodieren(string $name): string {
        return preg_replace_callback('/&([^-]*)-/', function ($m) {
            if ($m[1] === '') return '&';
            $b64 = str_replace(',', '/', $m[1]);
            $roh = base64_decode($b64 . str_repeat('=', (4 - strlen($b64) % 4) % 4));
            return mb_convert_encoding($roh, 'UTF-8', 'UTF-16BE');
        }, $name);
    }
}
