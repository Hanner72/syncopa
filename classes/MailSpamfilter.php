<?php
// classes/MailSpamfilter.php
// Spamfilter je Postfach (Junk-E-Mail wie in Outlook). Prüft neue Nachrichten im Posteingang in dieser Reihenfolge:
//  1. sichere Absender (eigene Liste, Mitglieder aus Syncopa, eigene Postfächer, Empfänger gesendeter Mails) → nie Junk
//  2. blockierte Absender/Domains → Junk
//  3. Kennzeichnung des Mailservers (X-Spam-Flag, X-Spam-Status, Rspamd-Score …) → Junk
//  4. Lernfilter (Bayes, pro Postfach): lernt aus „Junk“/„Kein Junk“ und dem Inhalt von Junk-/Spam-Ordnern
// Junk wird in den Junk-Ordner verschoben, der Grund steht im Protokoll (mail_spam_protokoll).
// Läuft beim Öffnen des Posteingangs, bei der Ungelesen-Abfrage und per Cronjob (cron/mail_spamfilter.php).

class MailSpamfilter {

    /** Ab dieser Wahrscheinlichkeit gilt eine Nachricht als Junk */
    private const SCHWELLE = 0.95;
    /** Mindestens so viele gelernte Junk- und erwünschte Nachrichten, bevor der Lernfilter selbst entscheidet */
    private const MIN_GELERNT = 10;
    /** Größere Nachrichten werden nur über Kopfzeilen beurteilt */
    private const MAX_GROESSE = 400000;
    /** Protokoll-Eintrag für Nachrichten, die der Benutzer mit „Kein Junk“ freigegeben hat */
    public const KEIN_JUNK = '#kein_junk';

    // ------------------------------------------------------------------------------------------------
    // Prüfen
    // ------------------------------------------------------------------------------------------------

    /**
     * Prüft die neuen Nachrichten im Posteingang (seit dem letzten Lauf) und verschiebt Junk.
     * $imap muss angemeldet sein. Liefert die Anzahl verschobener Nachrichten.
     * Läuft nicht doppelt gleichzeitig (Datenbank-Sperre je Postfach).
     */
    public static function pruefen(array $konto, ImapClient $imap, bool $schnell = false): int {
        if (empty($konto['spamfilter'])) return 0;
        $db = Database::getInstance();
        $sperre = 'syncopa_spam_' . (int)$konto['id'];
        if ((int)($db->fetchOne("SELECT GET_LOCK(?, 0) AS l", [$sperre])['l'] ?? 0) !== 1) return 0;
        try {
            $junk = $imap->sonderordner('spam');
            $inbox = $imap->sonderordner('posteingang') ?? 'INBOX';
            if ($junk === null) return 0;
            $stand = self::stand((int)$konto['id']);
            if (!$stand['angelernt']) {
                // Im Browser nur kurz anlernen (der Cronjob lernt nicht mehr nach, das Anlernen passiert einmal)
                self::anlernen($konto, $imap, $schnell ? 40 : 150);
                $stand = self::stand((int)$konto['id']);
            }
            $imap->waehlen($inbox);
            $alle = $imap->suchen();
            if (!$schnell) self::nachlernen((int)$konto['id'], $imap, $alle, $stand);
            $uids = array_values(array_filter($alle, fn($u) => $u > (int)$stand['letzte_uid']));
            if (!$uids) {
                // Neueste Nachricht gelöscht oder Ordner neu angelegt: Stand an den Ordner anpassen
                $db->execute("UPDATE mail_spam_stand SET letzte_uid = ?, letzter_lauf = NOW() WHERE konto_id = ?", [$alle ? max($alle) : 0, $konto['id']]);
                return 0;
            }
            $uids = array_slice($uids, 0, 200);
            $kopf = $imap->kopfzeilen($uids, ['FROM', 'SUBJECT', 'MESSAGE-ID', 'X-SPAM-FLAG', 'X-SPAM-STATUS', 'X-SPAM', 'X-SPAM-SCORE', 'X-RSPAMD-SCORE', 'X-SPAMD-RESULT', 'X-SPAM-LEVEL']);
            $groessen = array_map(fn($u) => $u['groesse'], $imap->uebersicht($uids));
            $sicher = self::sichereAbsender($konto);
            // Vom Benutzer mit „Kein Junk“ zurückgeholte Nachrichten nicht wieder aussortieren
            $freigegeben = array_flip(array_column($db->fetchAll(
                "SELECT message_id FROM mail_spam_protokoll WHERE konto_id = ? AND grund = ? AND message_id IS NOT NULL", [$konto['id'], self::KEIN_JUNK]), 'message_id'));
            $verschieben = [];
            foreach ($uids as $uid) {
                $k = $kopf[$uid]['kopf'] ?? [];
                if (isset($freigegeben[trim($k['message-id'][0] ?? '')])) continue;
                $absender = strtolower(MailNachricht::adressenZerlegen($k['from'][0] ?? '')[0][0] ?? '');
                $urteil = self::urteil($konto, $imap, $uid, $k, $absender, $sicher, $groessen[$uid] ?? 0);
                if ($urteil !== null) {
                    $verschieben[] = $uid;
                    $db->execute(
                        "INSERT INTO mail_spam_protokoll (konto_id, message_id, absender, betreff, grund, zeit) VALUES (?, ?, ?, ?, ?, NOW())",
                        [$konto['id'], mb_substr(trim($k['message-id'][0] ?? ''), 0, 250) ?: null, mb_substr($absender, 0, 190) ?: null,
                         mb_substr($k['subject'][0] ?? '', 0, 250), $urteil]
                    );
                }
            }
            if ($verschieben) {
                $imap->verschieben($verschieben, $junk);
            }
            $db->execute("UPDATE mail_spam_stand SET letzte_uid = ?, letzter_lauf = NOW() WHERE konto_id = ?", [max($uids), $konto['id']]);
            // Protokoll nach 90 Tagen aufräumen
            $db->execute("DELETE FROM mail_spam_protokoll WHERE konto_id = ? AND zeit < NOW() - INTERVAL 90 DAY", [$konto['id']]);
            return count($verschieben);
        } finally {
            $db->fetchOne("SELECT RELEASE_LOCK(?) AS l", [$sperre]);
        }
    }

    /** Liefert den Grund, wenn die Nachricht Junk ist, sonst null */
    private static function urteil(array $konto, ImapClient $imap, int $uid, array $kopf, string $absender, array $sicher, int $groesse): ?string {
        $domain = substr(strrchr($absender, '@') ?: '', 1);
        if ($absender !== '' && (isset($sicher['adressen'][$absender]) || isset($sicher['domains'][$domain]))) {
            return null;
        }
        $blockiert = self::liste((int)$konto['id'], 'blockiert');
        if ($absender !== '' && (isset($blockiert[$absender]) || isset($blockiert['@' . $domain]))) {
            return 'Absender blockiert';
        }
        $server = self::serverKennzeichnung($kopf);
        if ($server !== null) {
            return $server;
        }
        // Lernfilter
        $stand = self::stand((int)$konto['id']);
        if ($stand['spam_mails'] < self::MIN_GELERNT || $stand['ham_mails'] < self::MIN_GELERNT) {
            return null;
        }
        $roh = null;
        if ($groesse > 0 && $groesse <= self::MAX_GROESSE) {
            $roh = $imap->rohNachricht($uid);
        }
        $woerter = $roh !== null ? self::woerter(MailNachricht::zerlegen($roh)) : self::woerterAusKopf($kopf);
        $p = self::wahrscheinlichkeit((int)$konto['id'], $woerter, $stand);
        return $p >= self::SCHWELLE ? 'Lernfilter (' . round($p * 100) . ' % Junk-Wahrscheinlichkeit)' : null;
    }

    /** Spam-Kennzeichnung des Mailservers (SpamAssassin, Rspamd, …), sonst null */
    public static function serverKennzeichnung(array $kopf): ?string {
        $wert = fn($n) => strtolower(trim($kopf[$n][0] ?? ''));
        if (str_starts_with($wert('x-spam-flag'), 'yes')) return 'vom Mailserver als Spam markiert';
        if (str_starts_with($wert('x-spam-status'), 'yes')) return 'vom Mailserver als Spam markiert';
        if (in_array($wert('x-spam'), ['yes', 'true'], true)) return 'vom Mailserver als Spam markiert';
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)/', $wert('x-rspamd-score'), $m) && (float)$m[1] >= 15) {
            return 'vom Mailserver als Spam bewertet (Rspamd ' . $m[1] . ')';
        }
        if (preg_match('/default:\s*(true|false)\s*\[\s*(-?[\d.]+)\s*\/\s*([\d.]+)\s*\]/', $wert('x-spamd-result'), $m) && $m[1] === 'true') {
            return 'vom Mailserver als Spam bewertet (' . $m[2] . '/' . $m[3] . ')';
        }
        return null;
    }

    // ------------------------------------------------------------------------------------------------
    // Junk / Kein Junk (Bedienung)
    // ------------------------------------------------------------------------------------------------

    /**
     * Markiert Nachrichten als Junk: verschiebt sie in den Junk-Ordner und lernt daraus.
     * $blockieren: 'absender' oder 'domain' blockiert zusätzlich die Absender.
     */
    public static function alsJunk(array $konto, ImapClient $imap, string $ordner, array $uids, string $blockieren = ''): void {
        $junk = $imap->sonderordner('spam');
        if ($junk === null) throw new RuntimeException('Dieses Postfach hat keinen Junk-E-Mail-Ordner.');
        $imap->waehlen($ordner);
        foreach ($uids as $uid) {
            $roh = $imap->rohNachricht($uid);
            if ($roh === null) continue;
            $n = MailNachricht::zerlegen(strlen($roh) <= self::MAX_GROESSE ? $roh : substr($roh, 0, self::MAX_GROESSE));
            self::lernen((int)$konto['id'], self::woerter($n), true);
            $absender = strtolower($n['von'][0][0] ?? '');
            if ($absender !== '') {
                self::listeEntfernen((int)$konto['id'], $absender, 'sicher');
                if ($blockieren === 'absender') self::listeEintragen((int)$konto['id'], $absender, 'blockiert');
                if ($blockieren === 'domain') self::listeEintragen((int)$konto['id'], '@' . substr(strrchr($absender, '@'), 1), 'blockiert');
            }
        }
        if ($ordner !== $junk) {
            $imap->waehlen($ordner);
            $imap->verschieben($uids, $junk);
        }
    }

    /** „Kein Junk“: zurück in den Posteingang, als erwünscht lernen, Absender optional als sicher eintragen */
    public static function keinJunk(array $konto, ImapClient $imap, string $ordner, array $uids, bool $absenderSicher = true): void {
        $inbox = $imap->sonderordner('posteingang') ?? 'INBOX';
        $imap->waehlen($ordner);
        foreach ($uids as $uid) {
            $roh = $imap->rohNachricht($uid);
            if ($roh === null) continue;
            $n = MailNachricht::zerlegen(strlen($roh) <= self::MAX_GROESSE ? $roh : substr($roh, 0, self::MAX_GROESSE));
            self::lernen((int)$konto['id'], self::woerter($n), false);
            if ($n['message_id'] !== '') {
                Database::getInstance()->execute("INSERT INTO mail_spam_protokoll (konto_id, message_id, absender, betreff, grund, zeit) VALUES (?, ?, ?, ?, ?, NOW())",
                    [$konto['id'], mb_substr($n['message_id'], 0, 250), mb_substr(strtolower($n['von'][0][0] ?? ''), 0, 190) ?: null, mb_substr($n['betreff'], 0, 250), self::KEIN_JUNK]);
            }
            $absender = strtolower($n['von'][0][0] ?? '');
            if ($absender !== '') {
                self::listeEntfernen((int)$konto['id'], $absender, 'blockiert');
                if ($absenderSicher) self::listeEintragen((int)$konto['id'], $absender, 'sicher');
            }
        }
        if ($ordner !== $inbox) {
            $imap->waehlen($ordner);
            $imap->verschieben($uids, $inbox);
        }
    }

    /** Grund aus dem Protokoll, warum eine Nachricht als Junk eingestuft wurde (für die Anzeige) */
    public static function grund(int $kontoId, string $messageId): ?array {
        if ($messageId === '') return null;
        return Database::getInstance()->fetchOne(
            "SELECT grund, zeit FROM mail_spam_protokoll WHERE konto_id = ? AND message_id = ? AND grund <> ? ORDER BY id DESC LIMIT 1", [$kontoId, $messageId, self::KEIN_JUNK]) ?: null;
    }

    // ------------------------------------------------------------------------------------------------
    // Anlernen beim ersten Lauf
    // ------------------------------------------------------------------------------------------------

    /**
     * Erstes Anlernen: Nachrichten in Junk- und Spam-Ordnern als Junk, gelesene Nachrichten im Posteingang
     * und gesendete als erwünscht. Danach werden nur neue Nachrichten geprüft (ältere bleiben, wo sie sind).
     */
    public static function anlernen(array $konto, ImapClient $imap, int $max = 150): void {
        $db = Database::getInstance();
        $kontoId = (int)$konto['id'];
        $alle = array_column($imap->ordner(), null, 'name');
        $spamOrdner = array_filter($alle, fn($o) => $o['rolle'] === 'spam' || preg_match('/(^|[.\/])(spam|junk)$/i', $o['name']));
        foreach (array_keys($spamOrdner) as $ordner) {
            self::ordnerLernen($kontoId, $imap, $ordner, true, $max);
        }
        foreach (['posteingang', 'gesendet'] as $rolle) {
            $ordner = $imap->sonderordner($rolle);
            if ($ordner !== null && !isset($spamOrdner[$ordner])) {
                self::ordnerLernen($kontoId, $imap, $ordner, false, $max, $rolle === 'posteingang');
            }
        }
        // Ab jetzt nur noch neue Nachrichten im Posteingang prüfen
        $inbox = $imap->sonderordner('posteingang') ?? 'INBOX';
        $imap->waehlen($inbox, true);
        $uids = $imap->suchen();
        $db->execute("UPDATE mail_spam_stand SET angelernt = 1, letzte_uid = ?, ham_uid = ? WHERE konto_id = ?", [$uids ? max($uids) : 0, $uids ? max($uids) : 0, $kontoId]);
    }

    /**
     * Laufend dazulernen (nur im Cronjob): Nachrichten, die geprüft, gelesen und im Posteingang behalten wurden, sind erwünscht.
     * Ungelesene werden erst nach 7 Tagen übernommen. Höchstens 30 je Lauf.
     */
    private static function nachlernen(int $kontoId, ImapClient $imap, array $alle, array $stand): void {
        $offen = array_values(array_filter($alle, fn($u) => $u > (int)$stand['ham_uid'] && $u <= (int)$stand['letzte_uid']));
        if (!$offen) return;
        $offen = array_slice($offen, 0, 30);
        $ueb = $imap->uebersicht($offen);
        $bis = (int)$stand['ham_uid'];
        foreach ($offen as $uid) {
            $u = $ueb[$uid] ?? null;
            if ($u && !$u['gelesen'] && (!$u['datum'] || strtotime($u['datum']) > time() - 7 * 86400)) break;
            if ($u && $u['gelesen'] && $u['groesse'] <= self::MAX_GROESSE) {
                $roh = $imap->rohNachricht($uid);
                if ($roh !== null) self::lernen($kontoId, self::woerter(MailNachricht::zerlegen($roh)), false);
            }
            $bis = $uid;
        }
        if ($bis !== (int)$stand['ham_uid']) {
            Database::getInstance()->execute("UPDATE mail_spam_stand SET ham_uid = ? WHERE konto_id = ?", [$bis, $kontoId]);
        }
    }

    private static function ordnerLernen(int $kontoId, ImapClient $imap, string $ordner, bool $istSpam, int $max, bool $nurGelesene = false): void {
        $imap->waehlen($ordner, true);
        $uids = array_slice($imap->suchen(), -$max);
        if (!$uids) return;
        $ueb = $imap->uebersicht($uids);
        foreach ($uids as $uid) {
            $u = $ueb[$uid] ?? null;
            if (!$u || ($nurGelesene && !$u['gelesen']) || $u['groesse'] > self::MAX_GROESSE) continue;
            $roh = $imap->rohNachricht($uid);
            if ($roh !== null) self::lernen($kontoId, self::woerter(MailNachricht::zerlegen($roh)), $istSpam);
        }
    }

    // ------------------------------------------------------------------------------------------------
    // Lernfilter (Bayes nach Paul Graham / Gary Robinson)
    // ------------------------------------------------------------------------------------------------

    /** Kennzeichnende Wörter einer Nachricht (Betreff, Absender-Domain, Links, Text) */
    public static function woerter(array $n): array {
        $w = [];
        $absender = strtolower($n['von'][0][0] ?? '');
        if ($absender !== '') {
            $w['d:' . substr(strrchr($absender, '@') ?: '@', 1)] = true;
        }
        foreach (self::zerlegeText((string)$n['betreff']) as $t) $w['s:' . $t] = true;
        $text = (string)($n['text'] ?? '');
        if ($text === '' && $n['html'] !== null) $text = MailNachricht::textAusHtml($n['html']);
        $html = (string)($n['html'] ?? '');
        if (preg_match_all('#https?://([a-z0-9.-]+)#i', $text . ' ' . $html, $m)) {
            foreach (array_slice(array_unique($m[1]), 0, 30) as $host) {
                $teile = explode('.', strtolower($host));
                $w['u:' . implode('.', array_slice($teile, -2))] = true;
            }
        }
        foreach (self::zerlegeText(mb_substr($text, 0, 20000)) as $t) $w[$t] = true;
        if ($n['anhaenge']) $w['x:anhang'] = true;
        if ($html !== '' && trim((string)($n['text'] ?? '')) === '') $w['x:nurhtml'] = true;
        return array_slice(array_keys($w), 0, 400);
    }

    private static function woerterAusKopf(array $kopf): array {
        $w = [];
        $absender = strtolower(MailNachricht::adressenZerlegen($kopf['from'][0] ?? '')[0][0] ?? '');
        if ($absender !== '') $w[] = 'd:' . substr(strrchr($absender, '@') ?: '@', 1);
        foreach (self::zerlegeText($kopf['subject'][0] ?? '') as $t) $w[] = 's:' . $t;
        return array_unique($w);
    }

    private static function zerlegeText(string $text): array {
        preg_match_all('/[\p{L}\p{N}$€%!][\p{L}\p{N}$€%!\'-]{2,29}/u', mb_strtolower($text), $m);
        return array_values(array_unique(array_filter($m[0], fn($t) => !ctype_digit($t))));
    }

    private static function lernen(int $kontoId, array $woerter, bool $istSpam): void {
        $db = Database::getInstance();
        self::stand($kontoId);
        $spalte = $istSpam ? 'spam' : 'ham';
        foreach (array_chunk($woerter, 100) as $teil) {
            $werte = [];
            $platz = [];
            foreach ($teil as $wort) {
                $platz[] = '(?, ?, ?, ?)';
                array_push($werte, $kontoId, mb_substr($wort, 0, 64), $istSpam ? 1 : 0, $istSpam ? 0 : 1);
            }
            $db->execute(
                "INSERT INTO mail_spam_woerter (konto_id, wort, spam, ham) VALUES " . implode(', ', $platz)
                    . " ON DUPLICATE KEY UPDATE $spalte = $spalte + 1",
                $werte
            );
        }
        $db->execute("UPDATE mail_spam_stand SET {$spalte}_mails = {$spalte}_mails + 1 WHERE konto_id = ?", [$kontoId]);
    }

    /** Junk-Wahrscheinlichkeit 0…1 aus den 15 aussagekräftigsten Wörtern (Robinson/Fisher) */
    private static function wahrscheinlichkeit(int $kontoId, array $woerter, array $stand): float {
        if (!$woerter) return 0.5;
        $db = Database::getInstance();
        $zaehler = [];
        foreach (array_chunk($woerter, 200) as $teil) {
            foreach ($db->fetchAll(
                "SELECT wort, spam, ham FROM mail_spam_woerter WHERE konto_id = ? AND wort IN (" . implode(',', array_fill(0, count($teil), '?')) . ")",
                array_merge([$kontoId], $teil)) as $r) {
                $zaehler[$r['wort']] = $r;
            }
        }
        $nSpam = max(1, (int)$stand['spam_mails']);
        $nHam = max(1, (int)$stand['ham_mails']);
        $werte = [];
        foreach ($zaehler as $r) {
            $s = (int)$r['spam'];
            $h = (int)$r['ham'];
            $anzahl = $s + $h;
            if ($anzahl < 2) continue;
            $pS = min(1, $s / $nSpam);
            $pH = min(1, $h / $nHam);
            $p = $pS / ($pS + $pH);
            // Robinson: Annäherung an 0,5 bei wenigen Belegen
            $f = (0.45 * 1 + $anzahl * $p) / (1 + $anzahl);
            $werte[] = max(0.01, min(0.99, $f));
        }
        if (!$werte) return 0.5;
        usort($werte, fn($a, $b) => abs($b - 0.5) <=> abs($a - 0.5));
        $werte = array_slice($werte, 0, 15);
        $spam = 1.0;
        $ham = 1.0;
        foreach ($werte as $f) {
            $spam *= $f;
            $ham *= (1 - $f);
        }
        return $spam / ($spam + $ham);
    }

    // ------------------------------------------------------------------------------------------------
    // Absenderlisten und Stand
    // ------------------------------------------------------------------------------------------------

    public static function stand(int $kontoId): array {
        $db = Database::getInstance();
        $db->execute("INSERT IGNORE INTO mail_spam_stand (konto_id) VALUES (?)", [$kontoId]);
        return $db->fetchOne("SELECT * FROM mail_spam_stand WHERE konto_id = ?", [$kontoId]);
    }

    /** Liste 'sicher' oder 'blockiert': [muster => true] */
    public static function liste(int $kontoId, string $art): array {
        $liste = [];
        foreach (Database::getInstance()->fetchAll("SELECT muster FROM mail_spam_absender WHERE konto_id = ? AND art = ? ORDER BY muster", [$kontoId, $art]) as $r) {
            $liste[$r['muster']] = true;
        }
        return $liste;
    }

    /** Muster: E-Mail-Adresse oder @domain.at */
    public static function listeEintragen(int $kontoId, string $muster, string $art): void {
        $muster = strtolower(trim($muster));
        $gueltig = str_starts_with($muster, '@') ? preg_match('/^@[a-z0-9.-]+\.[a-z]{2,}$/', $muster) : filter_var($muster, FILTER_VALIDATE_EMAIL);
        if (!$gueltig) throw new RuntimeException('Bitte eine E-Mail-Adresse oder eine Domain wie @beispiel.at angeben.');
        if (!in_array($art, ['sicher', 'blockiert'], true)) throw new RuntimeException('Unbekannte Liste.');
        $db = Database::getInstance();
        $db->execute("DELETE FROM mail_spam_absender WHERE konto_id = ? AND muster = ?", [$kontoId, $muster]);
        $db->execute("INSERT INTO mail_spam_absender (konto_id, muster, art, erstellt_am) VALUES (?, ?, ?, NOW())", [$kontoId, $muster, $art]);
    }

    public static function listeEntfernen(int $kontoId, string $muster, ?string $art = null): void {
        $sql = "DELETE FROM mail_spam_absender WHERE konto_id = ? AND muster = ?";
        $p = [$kontoId, strtolower(trim($muster))];
        if ($art !== null) { $sql .= " AND art = ?"; $p[] = $art; }
        Database::getInstance()->execute($sql, $p);
    }

    /** Sichere Absender: eigene Liste, Mitglieder, eigene Postfächer → ['adressen' => [...], 'domains' => [...]] */
    private static function sichereAbsender(array $konto): array {
        $adressen = [];
        $domains = [];
        foreach (array_keys(self::liste((int)$konto['id'], 'sicher')) as $m) {
            if (str_starts_with($m, '@')) $domains[substr($m, 1)] = true; else $adressen[$m] = true;
        }
        $db = Database::getInstance();
        foreach ($db->fetchAll("SELECT LOWER(email) e FROM mitglieder WHERE email LIKE '%@%' UNION SELECT LOWER(bmv_email2) FROM mitglieder WHERE bmv_email2 LIKE '%@%'
                                UNION SELECT LOWER(email) FROM mail_konten UNION SELECT LOWER(email) FROM benutzer WHERE email LIKE '%@%'") as $r) {
            $adressen[trim($r['e'])] = true;
        }
        return ['adressen' => $adressen, 'domains' => $domains];
    }

    /** Empfänger einer gesendeten Nachricht werden automatisch sichere Absender (wie in Outlook) */
    public static function empfaengerAlsSicher(int $kontoId, array $adressen): void {
        $db = Database::getInstance();
        foreach (array_slice($adressen, 0, 50) as $a) {
            $a = strtolower(trim($a));
            if (filter_var($a, FILTER_VALIDATE_EMAIL)) {
                $db->execute("INSERT IGNORE INTO mail_spam_absender (konto_id, muster, art, erstellt_am) VALUES (?, ?, 'sicher', NOW())", [$kontoId, $a]);
            }
        }
    }
}
