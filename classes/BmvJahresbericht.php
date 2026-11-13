<?php
// classes/BmvJahresbericht.php
// Jahresbericht an den Blasmusikverband: Werte aus Kassabuch, Mitgliedern und Ausrückungen berechnen,
// Handeingaben speichern, abschließen, als PDF ausgeben und per E-Mail senden.

class BmvJahresbericht {

    /** Zeilen des Jahresberichts, denen eine Kassabuch-Kategorie zugeordnet werden kann */
    public const POSTEN = [
        'sub_land'       => 'Subventionen des Landes',
        'sub_gemeinde'   => 'Subventionen der Gemeinden',
        'sub_bund'       => 'Subvention des Bundes',
        'sub_npo'        => 'Subventionen NPO',
        'aus_instr_neu'  => 'Neuanschaffung von Instrumenten',
        'aus_instr_rep'  => 'Instrumentenreparatur',
        'aus_noten'      => 'Noten',
        'aus_bekleidung' => 'Bekleidung',
        'aus_neubau'     => 'Neubau von Musikerheimen/Probelokalen',
        'aus_erhaltung'  => 'Erhaltung von Musikerheimen/Probelokalen',
        'aus_akm'        => 'AKM vereinseigene Veranstaltungen',
        'aus_ausbildung' => 'Aus- und Fortbildung',
        'aus_uebrige'    => 'alle übrigen Ausgaben',
    ];
    public const SUBVENTIONEN = ['sub_land', 'sub_gemeinde', 'sub_bund', 'sub_npo'];
    public const AUSGABEN = ['aus_instr_neu', 'aus_instr_rep', 'aus_noten', 'aus_bekleidung', 'aus_neubau', 'aus_erhaltung', 'aus_akm', 'aus_ausbildung', 'aus_uebrige'];

    /** Anlässe der Ausrückungen laut Jahresbericht */
    public const ANLAESSE = [
        'musikfest'   => 'bei vereinseigenen Musikfesten',
        'konzert'     => 'bei vereinseigenen Konzerten',
        'oeffentlich' => 'bei öffentlichen Anlässen (Gemeinde, Parteien)',
        'tourismus'   => 'bei Veranstaltungen von Tourismusverbänden',
        'privat'      => 'bei privaten Anlässen',
        'kirchlich'   => 'bei kirchlichen Feierlichkeiten',
        'begraebnis'  => 'bei Begräbnissen',
        'sonstig'     => 'bei sonstigen Anlässen (Hochzeiten, Ständchen,...)',
        'wertung'     => 'bei Wettbewerben/Wertungsspielen',
    ];
    /** Weitere Auswahl für eine Ausrückung: zählt unter Verbandsveranstaltungen bzw. gar nicht */
    public const ANLAESSE_EXTRA = [
        'bezirksfest' => 'Besuch eines Bezirksmusikfests',
        'landesfest'  => 'Besuch eines Landesmusikfests',
        'keine'       => 'zählt nicht für den Jahresbericht',
    ];
    public const KLANGKOERPER = ['gesamt' => 'Gesamtorchester', 'ensemble' => 'Ensemble / kleine Gruppe', 'jugend' => 'Jugendorchester'];
    public const PROBENARTEN = ['voll' => 'Vollprobe', 'teil' => 'Teilprobe'];

    public const PROBELOKALE = [
        'eigentum' => 'Fix eingerichtetes Probenlokal im Eigentum der Musikkapelle',
        'fix'      => 'Sonstiges fix eingerichtetes Probenlokal (nicht im Eigentum der MK)',
        'sonstig'  => 'Sonstige Räumlichkeiten (Probeneinrichtungen können nicht stehen bleiben)',
    ];
    public const KLEIDUNG = [
        'tracht'      => 'nur Tracht oder Bergmannstracht',
        'uniform'     => 'nur Uniform',
        'abwechselnd' => 'Uniform und Tracht (abwechselnd)',
        'einheitlich' => 'sonstige einheitliche Kleidung',
        'keine'       => 'keine einheitliche Kleidung',
    ];

    /** Mitgliedsarten laut BMV */
    private const ART_AKTIV = 1, ART_UNTERSTUETZEND = 3, ART_SCHUELER = 5, FUNKTION_JUGENDREFERENT = 17;

    private static function db(): Database { return Database::getInstance(); }

    /** Vorgabe für den Anlass einer Ausrückung aus ihrem Typ */
    public static function anlassStandard(string $typ): ?string {
        return match ($typ) {
            'Probe'   => null,
            'Konzert' => 'konzert',
            'Fest'    => 'musikfest',
            'Wertung' => 'wertung',
            default   => 'sonstig',
        };
    }

    /**
     * Alle Felder: Schlüssel => [Art, Bezeichnung]. Art: 'auto' (aus Syncopa), 'hand' (jedes Jahr eintragen),
     * 'vorjahr' (wird aus dem Vorjahr übernommen).
     */
    public static function felder(): array {
        $f = [];
        foreach (self::POSTEN as $k => $l) $f[$k] = ['auto', $l];
        $f['jugendkapelle_statut']  = ['vorjahr', 'Ist die MK/MV eine Jugendkapelle (mit eigenem Statut)?'];
        $f['jugendkapellen_anzahl'] = ['vorjahr', 'Anzahl von vereinseigenen Jugendkapellen (ohne eigenem Statut)'];
        $f['jugendreferenten']      = ['auto', 'Anzahl Jugendfunktionäre (Jugendreferenten)'];
        foreach (['akt' => 'auto', 'son' => 'hand', 'ausb_akt' => 'hand', 'ausb_neu' => 'auto'] as $gruppe => $art) {
            foreach (['j', 'a'] as $alter) {
                foreach (['m', 'w', 'd'] as $g) $f["{$gruppe}_{$alter}_{$g}"] = [$art, self::gruppenText($gruppe, $alter, $g)];
            }
        }
        $f['unterstuetzend'] = ['auto', 'Anzahl der unterstützenden Mitglieder'];
        foreach (['voll', 'teil'] as $p) foreach (['gesamt', 'jugend'] as $k) $f["probe_{$p}_{$k}"] = ['auto', self::PROBENARTEN[$p] . 'n ' . self::KLANGKOERPER[$k]];
        foreach (self::ANLAESSE as $a => $l) foreach (self::KLANGKOERPER as $k => $kl) $f["ar_{$a}_{$k}"] = ['auto', "Ausrückungen $l – $kl"];
        $f['konzertreisen']   = ['hand', 'Anzahl Konzertreisen ins Ausland'];
        $f['besuche_ausland'] = ['hand', 'Anzahl von Besuchen von ausländischen MK/MV'];
        $f['bezirksfeste']    = ['auto', 'Anzahl von besuchten Bezirksmusikfesten'];
        $f['landesfeste']     = ['auto', 'Anzahl von besuchten Landesmusikfesten'];
        $f['probelokal']      = ['vorjahr', 'MK/MV probt in'];
        $f['kleidung']        = ['vorjahr', 'MK/MV trägt'];
        $f['bearbeiter_name']    = ['auto', 'Bearbeiter/in des Jahresberichtes'];
        $f['bearbeiter_email']   = ['auto', 'E-Mail – Bearbeiter/in'];
        $f['bearbeiter_telefon'] = ['auto', 'Telefon – Bearbeiter/in'];
        return $f;
    }

    private static function gruppenText(string $gruppe, string $alter, string $g): string {
        $gr = ['akt' => 'Aktive Mitglieder', 'son' => 'Sonstige nicht musizierende Mitglieder',
               'ausb_akt' => 'In Ausbildung, bereits aktiv', 'ausb_neu' => 'In Ausbildung, noch nicht aktiv'][$gruppe];
        return $gr . ' ' . ($alter === 'j' ? 'bis 30 Jahre' : 'über 30 Jahre') . ' ' . ['m' => 'männlich', 'w' => 'weiblich', 'd' => 'divers'][$g];
    }

    /** Gespeicherter Bericht eines Jahres (oder null) */
    public static function laden(int $jahr): ?array {
        $r = self::db()->fetchOne("SELECT * FROM bmv_jahresberichte WHERE jahr = ?", [$jahr]);
        if (!$r) return null;
        $r['eingaben'] = json_decode($r['eingaben'] ?? '', true) ?: [];
        $r['werte'] = json_decode($r['werte'] ?? '', true) ?: [];
        return $r;
    }

    public static function alle(): array {
        return self::db()->fetchAll("SELECT jahr, abgeschlossen_am, gesendet_am, gesendet_an FROM bmv_jahresberichte ORDER BY jahr DESC");
    }

    /**
     * Berechnet die Werte aus den Daten in Syncopa. Liefert ['werte' => [Schlüssel => Zahl|Text|null], 'hinweise' => [...]].
     * null bedeutet: dazu gibt es in Syncopa keine Daten.
     */
    public static function berechnen(int $jahr, ?int $benutzerId = null): array {
        $w = [];
        $hinweise = [];
        $db = self::db();

        // Finanzen aus dem Kassabuch
        $buchungen = (int)$db->fetchOne("SELECT COUNT(*) c FROM finanzen WHERE YEAR(datum) = ? AND typ IN ('einnahme','ausgabe')", [$jahr])['c'];
        foreach (array_keys(self::POSTEN) as $k) $w[$k] = $buchungen ? 0 : null;
        if ($buchungen) {
            foreach ($db->fetchAll(
                "SELECT k.bmv_posten posten, f.typ, SUM(f.betrag) summe FROM finanzen f
                 LEFT JOIN finanz_kategorien k ON k.id = f.kategorie_id
                 WHERE YEAR(f.datum) = ? AND f.typ IN ('einnahme','ausgabe') GROUP BY k.bmv_posten, f.typ", [$jahr]) as $r) {
                $p = (string)$r['posten'];
                if ($p === 'keine') continue;
                if ($r['typ'] === 'einnahme') {
                    if (in_array($p, self::SUBVENTIONEN, true)) $w[$p] += (float)$r['summe'];
                } else {
                    $ziel = in_array($p, self::AUSGABEN, true) ? $p : 'aus_uebrige';
                    $w[$ziel] += (float)$r['summe'];
                }
            }
            foreach (array_keys(self::POSTEN) as $k) $w[$k] = (int)round($w[$k]);
        } else {
            $hinweise[] = "Im Kassabuch gibt es für $jahr keine Buchungen.";
        }

        // Mitglieder zum Stichtag 31.12.
        $stichtag = "$jahr-12-31";
        $arten = self::mitgliedsartenZumStichtag($stichtag);
        foreach (['akt', 'ausb_neu'] as $gr) foreach (['j', 'a'] as $al) foreach (['m', 'w', 'd'] as $g) $w["{$gr}_{$al}_{$g}"] = 0;
        $w['unterstuetzend'] = 0;
        $ohneGeburt = [];
        if ($arten) {
            $ids = implode(',', array_map('intval', array_keys($arten)));
            foreach ($db->fetchAll("SELECT id, vorname, nachname, geschlecht, geburtsdatum FROM mitglieder WHERE id IN ($ids)") as $m) {
                $art = $arten[(int)$m['id']];
                if ($art === self::ART_UNTERSTUETZEND) { $w['unterstuetzend']++; continue; }
                $gruppe = $art === self::ART_AKTIV ? 'akt' : ($art === self::ART_SCHUELER ? 'ausb_neu' : null);
                if (!$gruppe) continue;
                if (empty($m['geburtsdatum'])) $ohneGeburt[] = trim($m['vorname'] . ' ' . $m['nachname']);
                $alter = !empty($m['geburtsdatum']) ? $jahr - (int)substr($m['geburtsdatum'], 0, 4) : 99;
                $g = in_array($m['geschlecht'], ['m', 'w'], true) ? $m['geschlecht'] : 'd';
                $w["{$gruppe}_" . ($alter <= 30 ? 'j' : 'a') . "_{$g}"]++;
            }
        } else {
            foreach ($w as $k => $v) if (str_starts_with($k, 'akt_') || str_starts_with($k, 'ausb_neu_')) $w[$k] = null;
            $w['unterstuetzend'] = null;
            $hinweise[] = 'Zum 31.12.' . $jahr . ' ist kein Mitglied mit einer Mitgliedsart erfasst.';
        }
        if ($ohneGeburt) {
            $hinweise[] = count($ohneGeburt) . ' Mitglieder ohne Geburtsdatum werden als „über 30 Jahre“ gezählt: ' . implode(', ', array_slice($ohneGeburt, 0, 12)) . (count($ohneGeburt) > 12 ? ' …' : '');
        }
        $w['jugendreferenten'] = (int)$db->fetchOne(
            "SELECT COUNT(DISTINCT mitglied_id) c FROM bmv_mitglied_daten
             WHERE art = 'funktion' AND code = ? AND (von IS NULL OR von <= ?) AND (bis IS NULL OR bis >= ?)",
            [self::FUNKTION_JUGENDREFERENT, $stichtag, $stichtag])['c'];

        // Proben und Ausrückungen
        $termine = $db->fetchAll(
            "SELECT a.typ, a.bmv_anlass, a.probenart, COALESCE(f.bmv_klangkoerper, 'gesamt') kk
             FROM ausrueckungen a LEFT JOIN formationen f ON f.id = a.formation_id
             WHERE YEAR(a.start_datum) = ? AND COALESCE(a.status, '') <> 'abgesagt'", [$jahr]);
        $hatProben = false; $hatAusrueckungen = false;
        foreach (['voll', 'teil'] as $p) foreach (['gesamt', 'jugend'] as $k) $w["probe_{$p}_{$k}"] = 0;
        foreach (array_keys(self::ANLAESSE) as $a) foreach (array_keys(self::KLANGKOERPER) as $k) $w["ar_{$a}_{$k}"] = 0;
        $w['bezirksfeste'] = 0; $w['landesfeste'] = 0;
        foreach ($termine as $t) {
            $kk = isset(self::KLANGKOERPER[$t['kk']]) ? $t['kk'] : 'gesamt';
            if ($t['typ'] === 'Probe') {
                $hatProben = true;
                $p = $t['probenart'] === 'teil' ? 'teil' : 'voll';
                $w["probe_{$p}_" . ($kk === 'jugend' ? 'jugend' : 'gesamt')]++;
                continue;
            }
            $hatAusrueckungen = true;
            $anlass = $t['bmv_anlass'] ?: self::anlassStandard($t['typ']);
            if ($anlass === 'bezirksfest') { $w['bezirksfeste']++; continue; }
            if ($anlass === 'landesfest') { $w['landesfeste']++; continue; }
            if (!isset(self::ANLAESSE[$anlass])) continue;
            $w["ar_{$anlass}_{$kk}"]++;
        }
        if (!$hatProben) {
            foreach ($w as $k => $v) if (str_starts_with($k, 'probe_')) $w[$k] = null;
            $hinweise[] = "Für $jahr sind keine Proben eingetragen.";
        }
        if (!$hatAusrueckungen) {
            foreach ($w as $k => $v) if (str_starts_with($k, 'ar_')) $w[$k] = null;
            $w['bezirksfeste'] = null; $w['landesfeste'] = null;
            $hinweise[] = "Für $jahr sind keine Ausrückungen eingetragen.";
        }

        // Bearbeiter/in: angemeldeter Benutzer
        $w['bearbeiter_name'] = $w['bearbeiter_email'] = $w['bearbeiter_telefon'] = null;
        if ($benutzerId) {
            $b = $db->fetchOne(
                "SELECT b.benutzername, b.email, m.vorname, m.nachname, m.email m_email, m.mobil, m.telefon
                 FROM benutzer b
                 LEFT JOIN mitglieder m ON (m.id = b.mitglied_id OR (b.mitglied_id IS NULL AND m.benutzer_id = b.id))
                 WHERE b.id = ? LIMIT 1", [$benutzerId]);
            if ($b) {
                $name = trim(($b['vorname'] ?? '') . ' ' . ($b['nachname'] ?? ''));
                $w['bearbeiter_name'] = $name !== '' ? $name : null;
                $w['bearbeiter_email'] = ($b['m_email'] ?: $b['email']) ?: null;
                $w['bearbeiter_telefon'] = ($b['mobil'] ?: $b['telefon']) ?: null;
            }
        }

        return ['werte' => $w, 'hinweise' => $hinweise];
    }

    /** Mitgliedsart je Mitglied zum Stichtag: [mitglied_id => code]. Verlauf aus dem BMV hat Vorrang. */
    private static function mitgliedsartenZumStichtag(string $stichtag): array {
        $db = self::db();
        $verlauf = [];
        foreach ($db->fetchAll("SELECT mitglied_id, code, von, bis FROM bmv_mitglied_daten WHERE art = 'mitgliedsart' AND code IS NOT NULL ORDER BY von") as $r) {
            $id = (int)$r['mitglied_id'];
            $verlauf[$id] ??= null;
            if ((!$r['von'] || $r['von'] <= $stichtag) && (!$r['bis'] || $r['bis'] >= $stichtag)) {
                $verlauf[$id] = (int)$r['code'];
            }
        }
        $arten = array_filter($verlauf, fn($c) => $c !== null);
        // Mitglieder ohne Verlauf: aktuelle Mitgliedsart, wenn sie zum Stichtag dabei waren
        foreach ($db->fetchAll(
            "SELECT id, mitgliedsart_code FROM mitglieder
             WHERE mitgliedsart_code IS NOT NULL AND COALESCE(status, '') <> 'ausgetreten'
               AND (eintritt_datum IS NULL OR eintritt_datum <= ?) AND (austritt_datum IS NULL OR austritt_datum > ?)",
            [$stichtag, $stichtag]) as $m) {
            if (!array_key_exists((int)$m['id'], $verlauf)) $arten[(int)$m['id']] = (int)$m['mitgliedsart_code'];
        }
        return $arten;
    }

    /**
     * Fertige Ansicht eines Jahres: berechnete Werte, Handeingaben, Vorjahr.
     * Liefert ['werte', 'auto', 'quelle' => [Schlüssel => auto|geaendert|hand|vorjahr|fehlt], 'fehlend', 'hinweise', 'bericht'].
     */
    public static function zusammenstellen(int $jahr, int $benutzerId): array {
        $bericht = self::laden($jahr);
        if ($bericht && $bericht['abgeschlossen_am']) {
            $werte = $bericht['werte'];
            return ['werte' => $werte, 'auto' => [], 'quelle' => array_map(fn() => 'abgeschlossen', $werte), 'fehlend' => [], 'hinweise' => [], 'bericht' => $bericht];
        }
        $calc = self::berechnen($jahr, $benutzerId);
        $eingaben = $bericht['eingaben'] ?? [];
        $vorjahr = self::laden($jahr - 1);
        $vorjahrWerte = $vorjahr ? ($vorjahr['abgeschlossen_am'] ? $vorjahr['werte'] : $vorjahr['eingaben']) : [];

        $werte = []; $quelle = []; $fehlend = [];
        foreach (self::felder() as $k => [$art]) {
            $auto = $calc['werte'][$k] ?? null;
            if (array_key_exists($k, $eingaben) && $eingaben[$k] !== '' && $eingaben[$k] !== null) {
                $werte[$k] = $eingaben[$k];
                $quelle[$k] = $art === 'auto' ? ((string)$auto === (string)$eingaben[$k] ? 'auto' : 'geaendert') : 'hand';
            } elseif ($art === 'auto' && $auto !== null) {
                $werte[$k] = $auto; $quelle[$k] = 'auto';
            } elseif ($art === 'vorjahr' && isset($vorjahrWerte[$k]) && $vorjahrWerte[$k] !== '') {
                $werte[$k] = $vorjahrWerte[$k]; $quelle[$k] = 'vorjahr';
            } else {
                $werte[$k] = null; $quelle[$k] = 'fehlt'; $fehlend[] = $k;
            }
        }
        $werte['abschlussdatum'] = date('d.m.Y');
        return ['werte' => $werte, 'auto' => $calc['werte'], 'quelle' => $quelle, 'fehlend' => $fehlend, 'hinweise' => $calc['hinweise'], 'bericht' => $bericht];
    }

    /** Handeingaben speichern. Gespeichert wird nur, was vom berechneten Wert abweicht (oder kein berechneter Wert ist). */
    public static function eingabenSpeichern(int $jahr, array $post, int $benutzerId): void {
        $bericht = self::laden($jahr);
        if ($bericht && $bericht['abgeschlossen_am']) throw new RuntimeException("Der Jahresbericht $jahr ist bereits abgeschlossen.");
        $auto = self::berechnen($jahr, $benutzerId)['werte'];
        $eingaben = $bericht['eingaben'] ?? [];
        foreach (self::felder() as $k => [$art]) {
            if (!array_key_exists($k, $post)) continue;
            unset($eingaben[$k]);
            $v = trim((string)$post[$k]);
            if ($v === '') continue;
            if (in_array($k, ['jugendkapelle_statut'], true)) {
                $v = $v === 'ja' ? 'ja' : 'nein';
            } elseif ($k === 'probelokal') {
                if (!isset(self::PROBELOKALE[$v])) continue;
            } elseif ($k === 'kleidung') {
                if (!isset(self::KLEIDUNG[$v])) continue;
            } elseif (str_starts_with($k, 'bearbeiter_')) {
                $v = mb_substr($v, 0, 150);
            } else {
                if (!preg_match('/^-?\d+$/', $v)) throw new RuntimeException('Bitte bei „' . self::felder()[$k][1] . '“ eine ganze Zahl eintragen.');
                $v = (int)$v;
            }
            if ($art === 'auto' && $auto[$k] !== null && (string)$auto[$k] === (string)$v) continue;
            $eingaben[$k] = $v;
        }
        self::db()->execute(
            "INSERT INTO bmv_jahresberichte (jahr, eingaben, aktualisiert_am) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE eingaben = VALUES(eingaben), aktualisiert_am = NOW()",
            [$jahr, json_encode($eingaben, JSON_UNESCAPED_UNICODE)]);
    }

    /** Abschließen: Werte festschreiben und PDF ablegen. Liefert den PDF-Pfad. */
    public static function abschliessen(int $jahr, int $benutzerId): string {
        $z = self::zusammenstellen($jahr, $benutzerId);
        if ($z['bericht'] && $z['bericht']['abgeschlossen_am']) throw new RuntimeException("Der Jahresbericht $jahr ist bereits abgeschlossen.");
        if ($z['fehlend']) throw new RuntimeException('Es fehlen noch ' . count($z['fehlend']) . ' Angaben (rot markiert).');
        $werte = $z['werte'];
        $datei = self::pdfSpeichern($jahr, $werte);
        self::db()->execute(
            "INSERT INTO bmv_jahresberichte (jahr, werte, abgeschlossen_am, abgeschlossen_von, pdf_datei, aktualisiert_am) VALUES (?, ?, NOW(), ?, ?, NOW())
             ON DUPLICATE KEY UPDATE werte = VALUES(werte), abgeschlossen_am = NOW(), abgeschlossen_von = VALUES(abgeschlossen_von), pdf_datei = VALUES(pdf_datei), aktualisiert_am = NOW()",
            [$jahr, json_encode($werte, JSON_UNESCAPED_UNICODE), $benutzerId, basename($datei)]);
        return $datei;
    }

    /** Abschluss aufheben (nur Admin): Handeingaben bleiben erhalten */
    public static function wiederOeffnen(int $jahr): void {
        self::db()->execute("UPDATE bmv_jahresberichte SET abgeschlossen_am = NULL, abgeschlossen_von = NULL, aktualisiert_am = NOW() WHERE jahr = ?", [$jahr]);
    }

    /** Abgeschlossenen Bericht per E-Mail senden */
    public static function senden(int $jahr, array $konto, string $an, string $text): void {
        $bericht = self::laden($jahr);
        if (!$bericht || !$bericht['abgeschlossen_am']) throw new RuntimeException('Bitte den Jahresbericht zuerst abschließen.');
        if (!filter_var($an, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Bitte eine gültige Empfänger-Adresse angeben.');
        $pfad = self::pdfPfad($jahr);
        if (!is_file($pfad)) $pfad = self::pdfSpeichern($jahr, $bericht['werte']);
        $verein = self::vereinsname();
        $kennung = self::vereinskennung();
        MailSender::senden($konto, [
            'an'       => [[$an, '']],
            'betreff'  => "Jahresbericht $jahr – $verein ($kennung)",
            'html'     => nl2br(htmlspecialchars($text)),
            'anhaenge' => [[self::pdfName($jahr), 'application/pdf', file_get_contents($pfad)]],
        ]);
        self::db()->execute("UPDATE bmv_jahresberichte SET gesendet_an = ?, gesendet_am = NOW() WHERE jahr = ?", [$an, $jahr]);
    }

    public static function vereinsname(): string {
        return (string)(self::db()->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'verein_name'")['wert'] ?? 'Musikverein');
    }

    /** Vereinskennung im Format ST/16/13 */
    public static function vereinskennung(): string {
        $e = [];
        foreach (self::db()->fetchAll("SELECT schluessel, wert FROM einstellungen WHERE schluessel IN ('bmv_land','bmv_bezirk','bmv_verein')") as $r) $e[$r['schluessel']] = trim((string)$r['wert']);
        if (empty($e['bmv_land']) || empty($e['bmv_bezirk']) || empty($e['bmv_verein'])) return '';
        return strtoupper($e['bmv_land']) . '/' . str_pad($e['bmv_bezirk'], 2, '0', STR_PAD_LEFT) . '/' . str_pad($e['bmv_verein'], 2, '0', STR_PAD_LEFT);
    }

    public static function empfaenger(): string {
        return trim((string)(self::db()->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'bmv_jahresbericht_email'")['wert'] ?? ''));
    }

    // ---------------------------------------------------------------- PDF

    public static function pdfName(int $jahr): string {
        return 'Jahresbericht_' . $jahr . '_' . preg_replace('/[^A-Za-z0-9]+/', '_', self::vereinskennung() ?: 'Verein') . '.pdf';
    }

    private static function ordner(): string {
        $dir = BASE_PATH . '/uploads/bmv_jahresberichte';
        if (!is_dir($dir)) {
            mkdir($dir, 02775, true);
            @chmod($dir, 02775);
        }
        if (!is_file("$dir/.htaccess")) file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n");
        return $dir;
    }

    public static function pdfPfad(int $jahr): string {
        return self::ordner() . '/jahresbericht_' . $jahr . '.pdf';
    }

    private static function pdfSpeichern(int $jahr, array $werte): string {
        $pfad = self::pdfPfad($jahr);
        file_put_contents($pfad, self::pdf($jahr, $werte));
        @chmod($pfad, 0660);
        return $pfad;
    }

    /** Erzeugt das PDF im Aufbau des BMV-Formulars */
    public static function pdf(int $jahr, array $w, bool $entwurf = false): string {
        require_once BASE_PATH . '/vendor/fpdf/fpdf.php';
        $t = fn($s) => iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$s);
        $z = fn($k) => isset($w[$k]) && $w[$k] !== null && $w[$k] !== '' ? (string)$w[$k] : '';
        $summe = function (array $keys) use ($w) {
            $s = 0; $leer = true;
            foreach ($keys as $k) if (isset($w[$k]) && $w[$k] !== null && $w[$k] !== '') { $s += (int)$w[$k]; $leer = false; }
            return $leer ? '' : (string)$s;
        };
        $stand = date('d.m.Y H:i:s');

        $pdf = new class('P', 'mm', 'A4') extends FPDF {
            public string $fuss = '';
            public bool $entwurf = false;
            function Header() {
                if ($this->entwurf) {
                    $this->SetFont('Helvetica', 'B', 9); $this->SetTextColor(200, 0, 0);
                    $this->Cell(0, 5, 'ENTWURF - noch nicht abgeschlossen', 0, 1, 'R'); $this->SetTextColor(0);
                }
            }
            function Footer() {
                $this->SetY(-15); $this->SetFont('Helvetica', '', 8);
                $this->Cell(95, 5, $this->fuss, 0, 0, 'L');
                $this->Cell(0, 5, $this->PageNo() . ' von {nb}', 0, 0, 'R');
            }
        };
        $pdf->fuss = $stand;
        $pdf->entwurf = $entwurf;
        $pdf->AliasNbPages();
        $pdf->SetMargins(22, 18, 22);
        $pdf->SetAutoPageBreak(true, 20);

        $kopf = function () use ($pdf, $t, $jahr) {
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(95, 6, $t('JAHRESBERICHT ÜBER DAS KALENDERJAHR   ' . $jahr), 0, 0);
            $pdf->Cell(0, 6, $t(self::vereinskennung()), 0, 1, 'R');
            $pdf->Cell(0, 6, $t(self::vereinsname()), 0, 1);
            $pdf->Ln(4);
        };
        $titel = function (string $s) use ($pdf, $t) {
            $pdf->SetFont('Helvetica', 'B', 10); $pdf->Cell(0, 7, $t($s), 0, 1); $pdf->Ln(1);
        };
        $zeile = function (string $l, string $v, bool $fett = true, float $breite = 0) use ($pdf, $t) {
            $breite = $breite ?: 166;
            $pdf->SetFont('Helvetica', $fett ? 'B' : '', 9);
            $pdf->Cell($breite * 0.57, 8, $t(' ' . $l), 1, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell($breite * 0.43, 8, $t($v), 1, 1, 'C');
        };
        $kasten = function (string $s) use ($pdf, $t) {
            $pdf->SetFont('Helvetica', 'BI', 11); $pdf->Cell(166, 9, $t($s), 1, 1, 'C');
        };
        $summenzeile = function (string $v) use ($pdf, $t) {
            $pdf->SetFont('Helvetica', 'BI', 9); $pdf->Cell(94.6, 8, $t('Summe'), 1, 0, 'C');
            $pdf->SetFont('Helvetica', '', 9); $pdf->Cell(71.4, 8, $t($v), 1, 1, 'C');
        };

        // Seite 1: Finanzielles
        $pdf->AddPage();
        $kopf();
        $titel('FINANZIELLES');
        $kasten('Subventionen der Musikkapelle im Berichtsjahr');
        foreach (self::SUBVENTIONEN as $k) $zeile(self::POSTEN[$k], $z($k));
        $summenzeile($summe(self::SUBVENTIONEN));
        $pdf->Ln(2);
        $kasten('Ausgaben der Musikkapelle im Berichtsjahr');
        foreach (self::AUSGABEN as $k) $zeile(self::POSTEN[$k], $z($k));
        $summenzeile($summe(self::AUSGABEN));
        $pdf->Ln(3);
        $sub = $summe(self::SUBVENTIONEN); $aus = $summe(self::AUSGABEN);
        $pdf->SetFont('Helvetica', 'BI', 9); $pdf->Cell(94.6, 8, $t(' Wertschöpfung'), 1, 0);
        $pdf->SetFont('Helvetica', '', 9); $pdf->Cell(71.4, 8, $sub !== '' && $aus !== '' ? (string)((int)$sub - (int)$aus) : '', 1, 1, 'C');

        // Seite 2: Organisation und Mitglieder
        $pdf->AddPage();
        $titel('ORGANISATION UND VEREINSWESEN');
        $pdf->SetFont('Helvetica', '', 10);
        foreach ([['Ist die o.a. MK/MV eine Jugendkapelle (mit eigenem Statut)?', $z('jugendkapelle_statut') === 'ja' ? 'ja' : ($z('jugendkapelle_statut') === '' ? '' : 'nein')],
                  ['Anzahl von vereinseigenen Jugendkapellen (ohne eigenem Statut)', $z('jugendkapellen_anzahl')],
                  ['Anzahl Jugendfunktionäre (Jugendreferenten)', $z('jugendreferenten')]] as [$l, $v]) {
            $pdf->Cell(130, 7, $t('   ' . $l), 0, 0); $pdf->Cell(30, 7, $t($v), 'B', 1, 'C');
        }
        $pdf->Ln(6);
        $tabelle = function (string $ueberschrift, array $gruppen) use ($pdf, $t, $w, $z) {
            $pdf->SetFont('Helvetica', '', 10); $pdf->Cell(0, 7, $t($ueberschrift), 0, 1);
            $b = 18.4; $erste = 19.8;
            $pdf->SetFont('Helvetica', 'B', 7.5);
            $pdf->Cell($erste, 7, '', 'LTR', 0);
            foreach ($gruppen as [$titel]) $pdf->Cell($b * 4, 7, $t($titel), 1, 0, 'C');
            $pdf->Ln();
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->Cell($erste, 6, '', 'LBR', 0);
            foreach ($gruppen as $_) foreach (['männl.', 'weibl.', 'divers', 'gesamt'] as $s) $pdf->Cell($b, 6, $t($s), 1, 0, 'C');
            $pdf->Ln();
            foreach (['j' => 'bis 30 Jahre', 'a' => 'über 30 Jahre', 's' => 'Summe'] as $al => $label) {
                $pdf->SetFont('Helvetica', 'B', 7.5); $pdf->Cell($erste, 7, $t($label), 1, 0, 'C');
                $pdf->SetFont('Helvetica', '', 8);
                foreach ($gruppen as [, $pre]) {
                    $ges = 0; $leer = false;
                    foreach (['m', 'w', 'd'] as $g) {
                        if ($al === 's') {
                            $vj = $z("{$pre}_j_{$g}"); $va = $z("{$pre}_a_{$g}");
                            $v = ($vj === '' || $va === '') ? '' : (string)((int)$vj + (int)$va);
                        } else {
                            $v = $z("{$pre}_{$al}_{$g}");
                        }
                        if ($v === '') $leer = true; else $ges += (int)$v;
                        $pdf->Cell($b, 7, $v, 1, 0, 'C');
                    }
                    $pdf->Cell($b, 7, $leer ? '' : (string)$ges, 1, 0, 'C');
                }
                $pdf->Ln();
            }
        };
        $tabelle('Mitgliederstand mit 31. 12. des Berichtsjahres:', [['Aktive Mitglieder', 'akt'], ['Sonstige nicht musizierende Mitglieder', 'son']]);
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(130, 7, $t('Anzahl der unterstützenden Mitglieder'), 0, 0); $pdf->Cell(30, 7, $z('unterstuetzend'), 'B', 1, 'C');
        $pdf->Ln(6);
        $tabelle('Anzahl der in Ausbildung stehenden Musiker mit Stand 31. 12.:', [['bereits aktive Mitglieder', 'ausb_akt'], ['noch nicht aktive Mitglieder', 'ausb_neu']]);
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->MultiCell(0, 5, $t('Alle in Ausbildung stehenden Musiker bis 30 Jahre, soweit sie über die Musikvereine organisiert werden - auch z.B. Blockflöte oder musikalische Früherziehung'));

        // Seite 3: Proben und Ausrückungen
        $pdf->AddPage();
        $titel('Probentätigkeit:');
        $x = 50;
        $pdf->SetX($x); $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell(40, 10, '', 1, 0); $pdf->Cell(30, 10, $t('Gesamt-Orchester'), 1, 0, 'C'); $pdf->Cell(30, 10, $t('Jugend-Orchester'), 1, 1, 'C');
        foreach (['voll' => 'Vollproben', 'teil' => 'Teilproben'] as $p => $l) {
            $pdf->SetX($x); $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell(40, 8, $t(' ' . $l), 1, 0);
            $pdf->SetFont('Helvetica', '', 9); $pdf->Cell(30, 8, $z("probe_{$p}_gesamt"), 1, 0, 'C'); $pdf->Cell(30, 8, $z("probe_{$p}_jugend"), 1, 1, 'C');
        }
        $pdf->SetX($x); $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell(40, 8, $t('Summe'), 1, 0, 'C');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(30, 8, $summe(['probe_voll_gesamt', 'probe_teil_gesamt']), 1, 0, 'C'); $pdf->Cell(30, 8, $summe(['probe_voll_jugend', 'probe_teil_jugend']), 1, 1, 'C');
        $pdf->Ln(6);
        $titel('Ausrückungen im Berichtsjahr:');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell(76, 10, '', 1, 0); $pdf->Cell(30, 10, $t('Gesamt-Orchester'), 1, 0, 'C');
        $x = $pdf->GetX(); $y = $pdf->GetY();
        $pdf->MultiCell(30, 5, $t("Ensembles\nKleine Gruppen"), 1, 'C');
        $pdf->SetXY($x + 30, $y); $pdf->Cell(30, 10, $t('Jugend-Orchester'), 1, 1, 'C');
        foreach (self::ANLAESSE as $a => $l) {
            $pdf->SetFont('Helvetica', '', strlen($l) > 40 ? 7 : 8.5); $pdf->Cell(76, 8, $t(' ' . $l), 1, 0);
            $pdf->SetFont('Helvetica', '', 9);
            foreach (array_keys(self::KLANGKOERPER) as $k) $pdf->Cell(30, 8, $z("ar_{$a}_{$k}"), 1, 0, 'C');
            $pdf->Ln();
        }
        $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell(76, 9, $t('Summe der Ausrückungen'), 1, 0, 'C');
        $pdf->SetFont('Helvetica', '', 9);
        foreach (array_keys(self::KLANGKOERPER) as $k) $pdf->Cell(30, 9, $summe(array_map(fn($a) => "ar_{$a}_{$k}", array_keys(self::ANLAESSE))), 1, 0, 'C');
        $pdf->Ln(14);
        foreach ([['Anzahl Konzertreisen ins Ausland', 'konzertreisen'], ['Anzahl von Besuchen von ausländischen MK/MV', 'besuche_ausland']] as [$l, $k]) {
            $pdf->SetFont('Helvetica', '', 9); $pdf->Cell(106, 8, $t(' ' . $l), 1, 0); $pdf->Cell(30, 8, $z($k), 1, 1, 'C');
        }

        // Seite 4: Verband, Probelokal, Kleidung, Bearbeiter
        $pdf->AddPage();
        $titel('VERBANDSVERANSTALTUNGEN');
        $pdf->SetFont('Helvetica', '', 10);
        foreach ([['Anzahl von besuchten Bezirksmusikfesten:', 'bezirksfeste'], ['Anzahl von besuchten Landesmusikfesten:', 'landesfeste']] as [$l, $k]) {
            $pdf->Cell(130, 7, $t('   ' . $l), 0, 0); $pdf->Cell(30, 7, $z($k), 'B', 1, 'C');
        }
        $pdf->Ln(6);
        $auswahl = function (string $ueberschrift, string $frage, array $optionen, string $gewaehlt) use ($pdf, $t, $titel) {
            $titel($ueberschrift);
            $pdf->SetFont('Helvetica', '', 9);
            $erste = true;
            foreach ($optionen as $k => $l) {
                $pdf->Cell(32, 7, $erste ? $t('   ' . $frage) : '', 0, 0);
                $erste = false;
                $x = $pdf->GetX(); $y = $pdf->GetY() + 1.5;
                $pdf->Rect($x, $y, 4, 4);
                if ($k === $gewaehlt) { $pdf->SetFont('ZapfDingbats', '', 9); $pdf->Text($x + 0.6, $y + 3.4, '4'); $pdf->SetFont('Helvetica', '', 9); }
                $pdf->SetX($x + 6);
                $pdf->Cell(0, 7, $t($l), 0, 1);
            }
            $pdf->Ln(4);
        };
        $auswahl('PROBELOKALE:', 'MK/MV probt in:', self::PROBELOKALE, $z('probelokal'));
        $auswahl('KLEIDUNG:', 'MK/MV trägt:', self::KLEIDUNG, $z('kleidung'));
        $pdf->Ln(4);
        foreach ([['Bearbeiter/in des Jahresberichtes:', 'bearbeiter_name'], ['E-Mail - Bearbeiter/in:', 'bearbeiter_email'],
                  ['Telefon - Bearbeiter/in:', 'bearbeiter_telefon'], ['', ''], ['Abschlussdatum:', 'abschlussdatum']] as [$l, $k]) {
            if ($l === '') { $pdf->Ln(3); continue; }
            $pdf->SetFont('Helvetica', 'B', 10); $pdf->Cell(70, 8, $t($l), 0, 0);
            $pdf->SetFont('Helvetica', '', 10); $pdf->Cell(70, 8, $t($z($k)), 'B', 1, 'C');
        }

        return $pdf->Output('S');
    }
}
