<?php
// classes/MailNachricht.php
// E-Mails zusammenbauen (zum Versenden) und zerlegen (zum Anzeigen) – MIME nach RFC 5322/2045/2047/2231.
// Ohne Fremdbibliothek: Kopfzeilen mit Umlauten, Text + HTML, Anhänge, eingebettete Bilder (cid:), Zeichensätze.

class MailNachricht {

    // ------------------------------------------------------------------------------------------------
    // Zusammenbauen
    // ------------------------------------------------------------------------------------------------

    /**
     * Baut eine Nachricht als Rohtext (CRLF). $n: von [email, name], an/cc/bcc [[email, name]], betreff, html,
     * anhaenge [[name, typ, daten]], antwort_auf (Message-ID), referenzen (Text).
     * Liefert ['roh' => ..., 'message_id' => ..., 'empfaenger' => [alle Adressen inkl. Bcc]].
     */
    public static function bauen(array $n): array {
        $von = $n['von'];
        $domain = substr(strrchr($von[0], '@') ?: '@syncopa.local', 1);
        $messageId = '<' . bin2hex(random_bytes(12)) . '.' . time() . '@' . $domain . '>';
        $html = (string)($n['html'] ?? '');
        $text = self::textAusHtml($html);

        $kopf = [
            'Date'         => date('r'),
            'From'         => self::adresse($von[0], $von[1] ?? ''),
            'To'           => implode(', ', array_map(fn($a) => self::adresse($a[0], $a[1] ?? ''), $n['an'] ?? [])),
        ];
        if (!empty($n['cc'])) {
            $kopf['Cc'] = implode(', ', array_map(fn($a) => self::adresse($a[0], $a[1] ?? ''), $n['cc']));
        }
        $kopf['Subject'] = self::kopfKodieren((string)($n['betreff'] ?? ''));
        $kopf['Message-ID'] = $messageId;
        if (!empty($n['antwort_auf'])) {
            $kopf['In-Reply-To'] = $n['antwort_auf'];
            $kopf['References'] = trim(($n['referenzen'] ?? '') . ' ' . $n['antwort_auf']);
        }
        $kopf['MIME-Version'] = '1.0';
        $kopf['X-Mailer'] = 'Syncopa';

        $alternativ = self::grenze();
        $teilAlternativ = "Content-Type: multipart/alternative; boundary=\"$alternativ\"\r\n\r\n"
            . "--$alternativ\r\n" . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . self::qp($text) . "\r\n"
            . "--$alternativ\r\n" . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . self::qp('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>') . "\r\n"
            . "--$alternativ--\r\n";

        if (empty($n['anhaenge'])) {
            $rumpfKopf = $teilAlternativ;
        } else {
            $gemischt = self::grenze();
            $rumpfKopf = "Content-Type: multipart/mixed; boundary=\"$gemischt\"\r\n\r\n--$gemischt\r\n" . $teilAlternativ;
            foreach ($n['anhaenge'] as [$name, $typ, $daten]) {
                $name = self::kopfKodieren($name);
                $rumpfKopf .= "--$gemischt\r\n"
                    . "Content-Type: " . ($typ ?: 'application/octet-stream') . "; name=\"$name\"\r\n"
                    . "Content-Transfer-Encoding: base64\r\n"
                    . "Content-Disposition: attachment; filename=\"$name\"\r\n\r\n"
                    . rtrim(chunk_split(base64_encode($daten), 76, "\r\n")) . "\r\n";
            }
            $rumpfKopf .= "--$gemischt--\r\n";
        }

        $roh = '';
        foreach ($kopf as $name => $wert) {
            $roh .= $name . ': ' . $wert . "\r\n";
        }
        $empfaenger = array_unique(array_map(fn($a) => strtolower($a[0]), array_merge($n['an'] ?? [], $n['cc'] ?? [], $n['bcc'] ?? [])));
        return ['roh' => $roh . $rumpfKopf, 'message_id' => $messageId, 'empfaenger' => array_values($empfaenger)];
    }

    /** "Name" <adresse> mit kodiertem Namen */
    public static function adresse(string $email, string $name = ''): string {
        $name = trim($name);
        if ($name === '') {
            return $email;
        }
        if (preg_match('/[^\x20-\x7e]/', $name)) {
            return self::kopfKodieren($name) . " <$email>";
        }
        return '"' . addcslashes($name, '"\\') . "\" <$email>";
    }

    /** Kopfzeilen-Text mit Umlauten nach RFC 2047 (Base64, UTF-8) kodieren */
    public static function kopfKodieren(string $text): string {
        if (!preg_match('/[^\x20-\x7e]/', $text)) {
            return $text;
        }
        // in Stücke teilen, ohne UTF-8-Zeichen zu zerschneiden (max. 45 Bytes je Stück → Zeilen unter 78 Zeichen)
        $stuecke = [];
        $aktuell = '';
        foreach (mb_str_split($text) as $zeichen) {
            if (strlen($aktuell . $zeichen) > 45) {
                $stuecke[] = $aktuell;
                $aktuell = '';
            }
            $aktuell .= $zeichen;
        }
        $stuecke[] = $aktuell;
        return implode("\r\n ", array_map(fn($s) => '=?UTF-8?B?' . base64_encode($s) . '?=', $stuecke));
    }

    private static function qp(string $text): string {
        return quoted_printable_encode(str_replace(["\r\n", "\r", "\n"], "\r\n", $text));
    }

    private static function grenze(): string {
        return '=_syncopa_' . bin2hex(random_bytes(10));
    }

    /** Lesbarer Text aus HTML (für den Text-Teil und für Vorschauen) */
    public static function textAusHtml(string $html): string {
        $html = preg_replace('#<(br|/p|/div|/li|/tr|/h[1-6])\b[^>]*>#i', "\n", $html);
        $html = preg_replace('#<li\b[^>]*>#i', '- ', $html);
        $html = preg_replace('#<(style|script)\b.*?</\1>#is', '', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace("/[ \t]+/", ' ', $text);
        return trim(preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text));
    }

    // ------------------------------------------------------------------------------------------------
    // Zerlegen
    // ------------------------------------------------------------------------------------------------

    /**
     * Zerlegt eine Rohnachricht. Liefert:
     *  kopf (alle Kopfzeilen, klein geschrieben, dekodiert), betreff, von/an/cc/antwort_an ([[email, name]]), datum,
     *  message_id, referenzen, text, html, anhaenge [[teil, name, typ, groesse, inline, cid]], teile (intern, für Anhänge)
     */
    public static function zerlegen(string $roh): array {
        $teile = [];
        $wurzel = self::teilZerlegen($roh, '1', $teile);
        $text = null;
        $html = null;
        $anhaenge = [];
        foreach ($teile as $nr => $t) {
            if ($t['multipart']) continue;
            $istAnhang = $t['disposition'] === 'attachment' || ($t['dateiname'] !== '' && $t['disposition'] !== 'inline')
                || !in_array($t['typ'], ['text/plain', 'text/html'], true);
            if (!$istAnhang && $t['typ'] === 'text/plain' && $text === null) {
                $text = self::zuUtf8($t['daten'], $t['charset']);
                continue;
            }
            if (!$istAnhang && $t['typ'] === 'text/html' && $html === null) {
                $html = self::zuUtf8($t['daten'], $t['charset']);
                continue;
            }
            if ($t['typ'] === 'text/plain' || $t['typ'] === 'text/html') {
                if ($t['dateiname'] === '' && $t['disposition'] !== 'attachment') continue; // weitere Textteile ohne Namen
            }
            $anhaenge[] = [
                'teil'   => $nr,
                'name'   => $t['dateiname'] !== '' ? $t['dateiname'] : ('anhang-' . $nr . self::endung($t['typ'])),
                'typ'    => $t['typ'],
                'groesse'=> strlen($t['daten']),
                'inline' => $t['cid'] !== '' && $t['disposition'] !== 'attachment',
                'cid'    => $t['cid'],
            ];
        }
        $k = $wurzel['kopf'];
        return [
            'kopf'       => $k,
            'betreff'    => $k['subject'][0] ?? '',
            'von'        => self::adressenZerlegen($k['from'][0] ?? ''),
            'an'         => self::adressenZerlegen(implode(', ', $k['to'] ?? [])),
            'cc'         => self::adressenZerlegen(implode(', ', $k['cc'] ?? [])),
            'antwort_an' => self::adressenZerlegen($k['reply-to'][0] ?? ''),
            'datum'      => isset($k['date'][0]) && strtotime($k['date'][0]) ? date('Y-m-d H:i:s', strtotime($k['date'][0])) : null,
            'message_id' => trim($k['message-id'][0] ?? ''),
            'referenzen' => trim($k['references'][0] ?? ''),
            'text'       => $text,
            'html'       => $html,
            'anhaenge'   => $anhaenge,
            'teile'      => $teile,
        ];
    }

    /** Zerlegt einen Teil rekursiv; sammelt alle Teile in $teile (Nummern wie 1, 1.1, 1.2 …) */
    private static function teilZerlegen(string $roh, string $nr, array &$teile): array {
        $roh = str_replace("\r\n", "\n", $roh);
        $pos = strpos($roh, "\n\n");
        $kopfText = $pos === false ? $roh : substr($roh, 0, $pos);
        $rumpf = $pos === false ? '' : substr($roh, $pos + 2);
        $kopf = self::kopfZerlegen($kopfText);

        [$typ, $param] = self::parameterZerlegen($kopf['content-type'][0] ?? 'text/plain');
        [$disposition, $dispParam] = self::parameterZerlegen($kopf['content-disposition'][0] ?? '');
        $dateiname = $dispParam['filename'] ?? ($param['name'] ?? '');
        $teil = [
            'typ'         => $typ ?: 'text/plain',
            'charset'     => $param['charset'] ?? '',
            'disposition' => $disposition,
            'dateiname'   => self::dateinameSaeubern($dateiname),
            'cid'         => trim($kopf['content-id'][0] ?? '', " <>"),
            'multipart'   => str_starts_with($typ, 'multipart/') && !empty($param['boundary']),
            'daten'       => '',
            'kopf'        => $kopf,
        ];

        if ($teil['multipart']) {
            $grenze = '--' . $param['boundary'];
            $abschnitte = explode("\n" . $grenze, "\n" . $rumpf);
            array_shift($abschnitte); // Präambel
            $i = 1;
            foreach ($abschnitte as $abschnitt) {
                if (str_starts_with($abschnitt, '--')) break; // Ende
                $abschnitt = preg_replace('/^[ \t]*\n/', '', $abschnitt);
                self::teilZerlegen($abschnitt, $nr . '.' . $i, $teile);
                $i++;
            }
        } else {
            $kodierung = strtolower(trim($kopf['content-transfer-encoding'][0] ?? ''));
            $teil['daten'] = match ($kodierung) {
                'base64'           => (string)base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $rumpf)),
                'quoted-printable' => quoted_printable_decode($rumpf),
                default            => $rumpf,
            };
            if ($typ === 'message/rfc822' && $teil['dateiname'] === '') {
                $teil['dateiname'] = 'weitergeleitete-nachricht.eml';
            }
        }
        $teile[$nr] = $teil;
        return $teil;
    }

    /** Kopfzeilen: entfaltet, Namen klein, Werte RFC-2047-dekodiert, Mehrfachnennungen als Liste */
    private static function kopfZerlegen(string $text): array {
        $text = preg_replace("/\n[ \t]+/", ' ', $text);
        $kopf = [];
        foreach (explode("\n", $text) as $zeile) {
            $p = strpos($zeile, ':');
            if ($p === false) continue;
            $name = strtolower(trim(substr($zeile, 0, $p)));
            $kopf[$name][] = self::kopfDekodieren(trim(substr($zeile, $p + 1)));
        }
        return $kopf;
    }

    /** RFC-2047-Wörter (=?charset?B|Q?...?=) zu UTF-8 */
    public static function kopfDekodieren(string $wert): string {
        if (!str_contains($wert, '=?')) {
            return self::zuUtf8($wert, '');
        }
        // Leerraum zwischen zwei kodierten Wörtern gehört nicht zum Text
        $wert = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $wert);
        return preg_replace_callback('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', function ($m) {
            $daten = strtoupper($m[2]) === 'B'
                ? base64_decode($m[3])
                : quoted_printable_decode(str_replace('_', ' ', $m[3]));
            return self::zuUtf8((string)$daten, preg_replace('/\*.*$/', '', $m[1]));
        }, $wert);
    }

    /** "typ/untertyp; a=b; c*=utf-8''..." → [typ, [param => wert]] (inkl. RFC 2231) */
    private static function parameterZerlegen(string $wert): array {
        $teile = self::geteiltOhneAnfuehrung($wert, ';');
        $haupt = strtolower(trim(array_shift($teile) ?? ''));
        $param = [];
        $fortsetzung = [];
        foreach ($teile as $t) {
            $p = strpos($t, '=');
            if ($p === false) continue;
            $name = strtolower(trim(substr($t, 0, $p)));
            $w = trim(substr($t, $p + 1));
            if (strlen($w) >= 2 && $w[0] === '"' && substr($w, -1) === '"') {
                $w = stripcslashes(substr($w, 1, -1));
            }
            if (preg_match('/^([a-z0-9_-]+)\*(\d+)?(\*)?$/', $name, $m)) {
                // RFC 2231: name*=charset''prozent-kodiert bzw. name*0*=…, name*1*=…
                $fortsetzung[$m[1]][(int)($m[2] ?? 0)] = [$w, isset($m[3]) || !isset($m[2])];
                continue;
            }
            $param[$name] = $w;
        }
        foreach ($fortsetzung as $name => $stuecke) {
            ksort($stuecke);
            $charset = '';
            $text = '';
            foreach ($stuecke as $i => [$w, $kodiert]) {
                if ($kodiert && $i === array_key_first($stuecke) && preg_match("/^([^']*)'[^']*'(.*)$/", $w, $m)) {
                    $charset = $m[1];
                    $w = $m[2];
                }
                $text .= $kodiert ? rawurldecode($w) : $w;
            }
            $param[$name] = self::zuUtf8($text, $charset);
        }
        return [$haupt, $param];
    }

    private static function geteiltOhneAnfuehrung(string $text, string $trenner): array {
        $teile = [];
        $aktuell = '';
        $inAnf = false;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $c = $text[$i];
            if ($c === '\\' && $inAnf && $i + 1 < $len) { $aktuell .= $c . $text[++$i]; continue; }
            if ($c === '"') $inAnf = !$inAnf;
            if ($c === $trenner && !$inAnf) { $teile[] = $aktuell; $aktuell = ''; continue; }
            $aktuell .= $c;
        }
        $teile[] = $aktuell;
        return $teile;
    }

    /** "Name <a@b>, c@d" → [[email, name], …] */
    public static function adressenZerlegen(string $text): array {
        $ergebnis = [];
        foreach (self::geteiltOhneAnfuehrung($text, ',') as $teil) {
            $teil = trim($teil);
            if ($teil === '') continue;
            if (preg_match('/^(.*)<([^>]+)>\s*$/s', $teil, $m)) {
                $name = trim(trim($m[1]), '"');
                $ergebnis[] = [trim($m[2]), stripcslashes($name)];
            } elseif (str_contains($teil, '@')) {
                $ergebnis[] = [trim($teil, " <>"), ''];
            }
        }
        return $ergebnis;
    }

    /** Text in einem Zeichensatz zu UTF-8 */
    public static function zuUtf8(string $text, string $charset): string {
        $charset = strtoupper(trim($charset));
        if ($charset === '' || $charset === 'UTF-8' || $charset === 'US-ASCII') {
            if (mb_check_encoding($text, 'UTF-8')) return $text;
            $charset = 'Windows-1252';
        }
        if (in_array($charset, ['ISO-8859-1', 'LATIN1'], true)) $charset = 'Windows-1252';
        $erg = @mb_convert_encoding($text, 'UTF-8', $charset);
        if ($erg === false || $erg === '') {
            $erg = @iconv($charset, 'UTF-8//IGNORE', $text);
        }
        return $erg !== false && $erg !== null ? $erg : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    private static function dateinameSaeubern(string $name): string {
        $name = trim(str_replace(["\0", '/', '\\'], ['', '_', '_'], $name));
        return mb_substr($name, 0, 180);
    }

    private static function endung(string $typ): string {
        return match ($typ) {
            'application/pdf' => '.pdf', 'image/jpeg' => '.jpg', 'image/png' => '.png', 'image/gif' => '.gif',
            'message/rfc822' => '.eml', 'text/calendar' => '.ics', 'text/plain' => '.txt', 'text/html' => '.html',
            default => '',
        };
    }

    // ------------------------------------------------------------------------------------------------
    // Anzeige
    // ------------------------------------------------------------------------------------------------

    /**
     * HTML für die Anzeige im abgeschotteten Rahmen (iframe sandbox, ohne Skripte): Skripte und Ereignis-Attribute
     * entfernen, eingebettete Bilder (cid:) als data:-Adresse einsetzen, externe Bilder optional blockieren.
     * Liefert [html, hatExterneBilder].
     */
    public static function anzeigeHtml(array $nachricht, bool $externeBilder): array {
        $html = $nachricht['html'];
        if ($html === null) {
            $text = htmlspecialchars((string)$nachricht['text']);
            $text = preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', $text);
            return ['<div style="white-space:pre-wrap;font-family:Segoe UI,Arial,sans-serif;font-size:14px">' . $text . '</div>', false];
        }
        $cids = [];
        foreach ($nachricht['teile'] as $t) {
            if ($t['cid'] !== '' && !$t['multipart'] && strlen($t['daten']) < 3 * 1024 * 1024) {
                $cids[strtolower($t['cid'])] = 'data:' . $t['typ'] . ';base64,' . base64_encode($t['daten']);
            }
        }
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        foreach (['script', 'iframe', 'object', 'embed', 'form', 'base', 'meta', 'link'] as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $el) {
                $el->parentNode->removeChild($el);
            }
        }
        $extern = false;
        foreach ((new DOMXPath($dom))->query('//*') as $el) {
            foreach (iterator_to_array($el->attributes) as $attr) {
                $n = strtolower($attr->name);
                $v = trim($attr->value);
                if (str_starts_with($n, 'on') || (in_array($n, ['href', 'src', 'action', 'background'], true) && preg_match('/^\s*(javascript|vbscript):/i', $v))) {
                    $el->removeAttribute($attr->name);
                }
            }
            if ($el->hasAttribute('src') || $el->hasAttribute('background')) {
                $attr = $el->hasAttribute('src') ? 'src' : 'background';
                $src = trim($el->getAttribute($attr));
                if (stripos($src, 'cid:') === 0) {
                    $el->setAttribute($attr, $cids[strtolower(substr($src, 4))] ?? '');
                } elseif (preg_match('#^(https?:)?//#i', $src)) {
                    $extern = true;
                    if (!$externeBilder) {
                        $el->setAttribute($attr, 'data:image/gif;base64,R0lGODlhAQABAAAAACw=');
                    }
                }
            }
        }
        $ergebnis = $dom->saveHTML();
        $ergebnis = preg_replace('/^<\?xml[^>]*>/', '', $ergebnis);
        if (!$externeBilder) {
            // Hintergrundbilder in style-Angaben ebenfalls blockieren
            $ergebnis = preg_replace_callback('/url\(\s*[\'"]?(https?:)?\/\/[^)]*\)/i', function () use (&$extern) { $extern = true; return 'none'; }, $ergebnis);
        }
        return [$ergebnis, $extern];
    }
}
