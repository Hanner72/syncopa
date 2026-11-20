<?php
// classes/BmvInventar.php
// Vereinsinventar aus dem CSV-Export des BMV-Portals übernehmen (Inventar → CSV-Export).
// Die BMV-Schnittstelle liefert das Inventar nicht, daher der Weg über die Datei.
// Übernommen wird nur der Inventartyp „Instrument“. Das Inventar wird danach in Syncopa gepflegt; an den BMV
// wird nichts zurückgeschickt. Darum gilt beim Zusammenführen: Werte in Syncopa bleiben, nur leere Felder werden
// aus dem BMV ergänzt. Woher ein Instrument kommt, steht in den bmv_*-Spalten von instrumente.

class BmvInventar {

    /** Pflichtspalten im CSV-Export */
    private const PFLICHTSPALTEN = ['id', 'inv_nr', 'inv_typ', 'bezeich'];

    /** Felder, die beim Zusammenführen ergänzt werden (Syncopa-Spalte => Anzeigename) */
    public const FELDER = [
        'hersteller'        => 'Fabrikat',
        'seriennummer'      => 'Seriennummer',
        'anschaffungsdatum' => 'Kaufdatum',
        'anschaffungspreis' => 'Kaufpreis',
        'standort'          => 'Standort',
        'lieferant'         => 'Gekauft von',
        'mitglied_id'       => 'Zugeteilt an',
        'ausgeliehen_seit'  => 'Zugeteilt seit',
    ];

    // ------------------------------------------------------------------------------------------------
    // Datei lesen
    // ------------------------------------------------------------------------------------------------

    /**
     * Liest den CSV-Export. Liefert ['instrumente' => [Zeilen], 'andere' => [typ => anzahl], 'gesamt' => n].
     * Wirft RuntimeException mit verständlicher Meldung, wenn die Datei nicht passt.
     */
    public static function dateiLesen(string $inhalt): array {
        $inhalt = preg_replace('/^\xEF\xBB\xBF/', '', $inhalt); // BOM
        if (!mb_check_encoding($inhalt, 'UTF-8')) {
            $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $inhalt);
        rewind($stream);

        $kopf = fgetcsv($stream, 0, ';', '"', '');
        $kopf = $kopf ? array_map(fn($k) => strtolower(trim((string)$k)), $kopf) : [];
        $fehlend = array_diff(self::PFLICHTSPALTEN, $kopf);
        if ($fehlend) {
            throw new RuntimeException('Das ist kein Inventar-Export aus dem BMV-Portal (es fehlen die Spalten: ' . implode(', ', $fehlend) . ').');
        }

        $instrumente = [];
        $andere = [];
        $gesamt = 0;
        while (($werte = fgetcsv($stream, 0, ';', '"', '')) !== false) {
            if ($werte === [null] || !array_filter($werte, fn($w) => trim((string)$w) !== '')) continue;
            $gesamt++;
            $r = [];
            foreach ($kopf as $i => $spalte) {
                $r[$spalte] = trim((string)($werte[$i] ?? ''));
            }
            $typ = $r['inv_typ'] !== '' ? $r['inv_typ'] : 'ohne Typ';
            if (mb_strtolower($typ) !== 'instrument') {
                $andere[$typ] = ($andere[$typ] ?? 0) + 1;
                continue;
            }
            $instrumente[] = self::zeile($r);
        }
        fclose($stream);
        if (!$gesamt) {
            throw new RuntimeException('Die Datei enthält keine Einträge.');
        }
        usort($instrumente, fn($a, $b) => strnatcmp($a['inventar_nummer'], $b['inventar_nummer']));
        return ['instrumente' => $instrumente, 'andere' => $andere, 'gesamt' => $gesamt];
    }

    /** Eine CSV-Zeile in Syncopa-Felder übersetzen */
    private static function zeile(array $r): array {
        $text  = fn($w, $max = 150) => ($w = preg_replace('/\s+/u', ' ', trim((string)$w))) === '' ? null : mb_substr($w, 0, $max);
        $datum = function ($w) {
            if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})/', trim((string)$w), $m) && checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
            return null;
        };
        $betrag = function ($w) {
            $w = str_replace(['.', ' ', '€'], '', trim((string)$w));
            $w = str_replace(',', '.', $w);
            return is_numeric($w) && (float)$w > 0 ? round((float)$w, 2) : null;
        };
        $zugeteilt = $text($r['zugeteilt'] ?? null);
        $zurueck = $datum($r['rueckgabe'] ?? null);
        return [
            'bmv_inventar_id'   => $r['id'],
            'inventar_nummer'   => $text($r['inv_nr'], 50) ?? '',
            'bmv_bezeichnung'   => $text($r['bezeich']),
            'hersteller'        => $text($r['fabrikat'] ?? null, 100),
            'seriennummer'      => $text($r['fabr_nr'] ?? null, 100),
            'anschaffungsdatum' => $datum($r['kauf_dat'] ?? null),
            'anschaffungspreis' => $betrag($r['k_preis'] ?? null),
            'standort'          => $text($r['standort'] ?? null, 100),
            'lieferant'         => $text($r['kauf_von'] ?? null),
            'zugeteilt_text'    => $zurueck ? null : $zugeteilt,     // zurückgegeben = nicht mehr zugeteilt
            'zugeteilt_bmv_id'  => $zurueck ? null : $text($r['m_nr'] ?? null, 36),
            'ausgeliehen_seit'  => $zurueck ? null : $datum($r['zuteilung'] ?? null),
            'bmv_anmerkung'     => self::textAusHtml($r['anmerkung'] ?? ''),
            'bmv_aenderung'     => $text($r['aenderung'] ?? null, 50),
        ];
    }

    /**
     * Anmerkungen kommen als HTML; der Export ersetzt dabei jedes „;“ durch „,“ (z. B. „&euro,“ statt „&euro;“).
     * Liefert reinen Text mit Zeilenumbrüchen oder null.
     */
    public static function textAusHtml(string $html): ?string {
        $html = preg_replace('/&(#?[a-z0-9]+),/i', '&$1;', $html);
        $html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html);
        $html = preg_replace('#</\s*p\s*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = trim(preg_replace("/\n\s*\n+/", "\n", $text));
        return $text === '' ? null : $text;
    }

    // ------------------------------------------------------------------------------------------------
    // Vorschau: was passiert mit jedem Instrument?
    // ------------------------------------------------------------------------------------------------

    /**
     * Ermittelt für jede Zeile, was beim Übernehmen passiert:
     *  'neu'          – wird neu angelegt
     *  'zusammen'     – wird mit einem vorhandenen Syncopa-Instrument zusammengeführt (gleiche Inventarnummer
     *                   und passende Seriennummer bzw. Instrumentart)
     *  'vorhanden'    – wurde schon einmal übernommen; leere Felder werden ergänzt
     *  'konflikt'     – Inventarnummer ist in Syncopa an ein anderes Instrument vergeben
     * $zuordnung: [bmv_bezeichnung => instrument_typ_id | 'neu'] (sonst Vorschlag)
     */
    public static function vorschau(array $zeilen, array $zuordnung = []): array {
        $db = Database::getInstance();
        $nachBmvId = [];
        $nachNummer = [];
        foreach ($db->fetchAll("SELECT i.*, it.name AS typ_name FROM instrumente i JOIN instrument_typen it ON it.id = i.instrument_typ_id") as $i) {
            if ($i['bmv_inventar_id']) $nachBmvId[$i['bmv_inventar_id']] = $i;
            $nachNummer[self::nummer($i['inventar_nummer'])] = $i;
        }
        $ergebnis = [];
        foreach ($zeilen as $z) {
            $typ = $zuordnung[$z['bmv_bezeichnung']] ?? self::typVorschlag((string)$z['bmv_bezeichnung']);
            $mitglied = self::mitgliedFinden($z);
            $z['instrument_typ_id'] = $typ;
            $z['mitglied_id'] = $mitglied['id'] ?? null;
            $z['mitglied_name'] = $mitglied['name'] ?? null;

            if (isset($nachBmvId[$z['bmv_inventar_id']])) {
                $vorhanden = $nachBmvId[$z['bmv_inventar_id']];
                $aktion = 'vorhanden';
            } elseif (isset($nachNummer[self::nummer($z['inventar_nummer'])])) {
                $vorhanden = $nachNummer[self::nummer($z['inventar_nummer'])];
                $aktion = (!$vorhanden['bmv_inventar_id'] && self::passtZusammen($vorhanden, $z)) ? 'zusammen' : 'konflikt';
            } else {
                $vorhanden = null;
                $aktion = 'neu';
            }
            $ergaenzt = $vorhanden && $aktion !== 'konflikt' ? self::ergaenzteFelder($vorhanden, $z) : [];
            $ergebnis[] = $z + [
                'aktion'    => $aktion,
                'vorhanden' => $vorhanden ? ['id' => (int)$vorhanden['id'], 'inventar_nummer' => $vorhanden['inventar_nummer'],
                    'typ' => $vorhanden['typ_name'], 'hersteller' => $vorhanden['hersteller'], 'seriennummer' => $vorhanden['seriennummer']] : null,
                'ergaenzt'  => $ergaenzt,
            ];
        }
        return $ergebnis;
    }

    /** Gleiche Inventarnummer gilt nur als dasselbe Instrument, wenn Seriennummer oder Instrumentart passen */
    private static function passtZusammen(array $vorhanden, array $z): bool {
        $sn = fn($s) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$s));
        if ($sn($vorhanden['seriennummer']) !== '' && $sn($z['seriennummer']) !== '') {
            return $sn($vorhanden['seriennummer']) === $sn($z['seriennummer']);
        }
        return is_int($z['instrument_typ_id']) && (int)$vorhanden['instrument_typ_id'] === $z['instrument_typ_id'];
    }

    /** Anzeigenamen der Felder, die in Syncopa leer sind und aus dem BMV ergänzt würden */
    private static function ergaenzteFelder(array $vorhanden, array $z): array {
        $felder = [];
        foreach (self::FELDER as $spalte => $name) {
            if (($vorhanden[$spalte] === null || $vorhanden[$spalte] === '') && $z[$spalte] !== null && $z[$spalte] !== '') {
                if ($spalte === 'ausgeliehen_seit' && $vorhanden['mitglied_id']) continue;
                $felder[$spalte] = $name;
            }
        }
        return $felder;
    }

    /** Inventarnummer zum Vergleichen: ohne Leerzeichen, führende Nullen egal („027“ = „27“) */
    private static function nummer(?string $nr): string {
        $nr = strtolower(preg_replace('/\s+/', '', (string)$nr));
        return ctype_digit($nr) ? (string)(int)$nr : $nr;
    }

    // ------------------------------------------------------------------------------------------------
    // Instrumentart und Mitglied finden
    // ------------------------------------------------------------------------------------------------

    /** Gespeicherte Zuordnungen BMV-Bezeichnung => Instrumentart */
    public static function zuordnungen(): array {
        $z = [];
        foreach (Database::getInstance()->fetchAll(
            "SELECT z.bezeichnung, z.instrument_typ_id FROM bmv_inventar_zuordnung z JOIN instrument_typen t ON t.id = z.instrument_typ_id"
        ) as $r) {
            $z[$r['bezeichnung']] = (int)$r['instrument_typ_id'];
        }
        return $z;
    }

    /**
     * Schlägt eine Instrumentart für eine BMV-Bezeichnung vor („B-Bariton“ → Bariton, „Kinder B- Klarinette“ → Klarinette in Bb).
     * Reihenfolge: gespeicherte Zuordnung, sonst der längste Instrumentname (bzw. dessen Hauptwort), der in der Bezeichnung
     * vorkommt; BMV-Instrumentnamen zählen über ihre Zuordnung mit. Liefert die ID oder 'neu' (keine passende Art).
     */
    public static function typVorschlag(string $bezeichnung) {
        $gespeichert = self::zuordnungen();
        if (isset($gespeichert[$bezeichnung])) {
            return $gespeichert[$bezeichnung];
        }
        $norm = fn($s) => mb_strtolower(preg_replace('/[^\p{L}]/u', '', (string)$s));
        $ziel = $norm($bezeichnung);

        $schluessel = []; // [normierter Name, typ-id, automatisch]
        $db = Database::getInstance();
        foreach ($db->fetchAll("SELECT id, name, bmv_automatisch FROM instrument_typen") as $t) {
            $schluessel[] = [$norm($t['name']), (int)$t['id'], (int)$t['bmv_automatisch']];
            foreach (preg_split('/[\s\-]+/u', $t['name']) as $wort) {
                if (mb_strlen($wort) >= 5) $schluessel[] = [$norm($wort), (int)$t['id'], (int)$t['bmv_automatisch'] + 1];
            }
        }
        foreach ($db->fetchAll(
            "SELECT b.name, b.instrument_typ_id FROM bmv_bezeichnungen b JOIN instrument_typen t ON t.id = b.instrument_typ_id
             WHERE b.art = 'instrument' AND b.name <> ''"
        ) as $b) {
            $schluessel[] = [$norm($b['name']), (int)$b['instrument_typ_id'], 0];
        }

        $bester = null;
        foreach ($schluessel as [$s, $id, $rang]) {
            if ($s === '' || mb_strpos($ziel, $s) === false) continue;
            $wert = [mb_strlen($s), -$rang];
            if ($bester === null || $wert > $bester[0]) $bester = [$wert, $id];
        }
        return $bester ? $bester[1] : 'neu';
    }

    /**
     * Mitglied zur Zuteilung: über die BMV-ID (m_nr), sonst „Vorname NACHNAME (Geburtsdatum)“ bzw. „NACHNAME Vorname“.
     * Liefert ['id', 'name'] oder null; ohne Treffer bleibt der Text zur Anzeige erhalten.
     */
    public static function mitgliedFinden(array $z): ?array {
        if (!$z['zugeteilt_text'] && !$z['zugeteilt_bmv_id']) {
            return null;
        }
        $db = Database::getInstance();
        $name = fn($m) => $m['vorname'] . ' ' . $m['nachname'];
        if ($z['zugeteilt_bmv_id']) {
            $m = $db->fetchOne("SELECT id, vorname, nachname FROM mitglieder WHERE bmv_id = ?", [$z['zugeteilt_bmv_id']]);
            if ($m) return ['id' => (int)$m['id'], 'name' => $name($m)];
        }
        $text = (string)$z['zugeteilt_text'];
        $geburt = null;
        if (preg_match('/\((\d{1,2})\.(\d{1,2})\.(\d{4})\)/', $text, $g)) {
            $geburt = sprintf('%04d-%02d-%02d', $g[3], $g[2], $g[1]);
            $text = trim(str_replace($g[0], '', $text));
        }
        $woerter = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (count($woerter) < 2) return null;
        foreach ([[implode(' ', array_slice($woerter, 0, -1)), end($woerter)], [implode(' ', array_slice($woerter, 1)), $woerter[0]]] as [$vor, $nach]) {
            $sql = "SELECT id, vorname, nachname FROM mitglieder WHERE LOWER(vorname) = LOWER(?) AND LOWER(nachname) = LOWER(?)";
            $treffer = $db->fetchAll($sql . ($geburt ? " AND geburtsdatum = ?" : ""), $geburt ? [$vor, $nach, $geburt] : [$vor, $nach]);
            if (count($treffer) === 1) return ['id' => (int)$treffer[0]['id'], 'name' => $name($treffer[0])];
        }
        return null;
    }

    // ------------------------------------------------------------------------------------------------
    // Übernehmen
    // ------------------------------------------------------------------------------------------------

    /**
     * Übernimmt die Instrumente. $zuordnung: [bmv_bezeichnung => typ-id | 'neu'],
     * $konfliktZusammen: Liste von BMV-IDs, deren Konflikt trotzdem zusammengeführt werden soll.
     * Liefert ['neu' => n, 'zusammen' => n, 'ergaenzt' => n, 'unveraendert' => n, 'uebersprungen' => n].
     */
    public static function uebernehmen(array $zeilen, array $zuordnung, array $konfliktZusammen = []): array {
        $db = Database::getInstance();
        $zaehler = ['neu' => 0, 'zusammen' => 0, 'ergaenzt' => 0, 'unveraendert' => 0, 'uebersprungen' => 0];
        $jetzt = date('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            // Instrumentarten festlegen (neue anlegen) und Zuordnung für den nächsten Import merken
            $typen = [];
            foreach (array_unique(array_column($zeilen, 'bmv_bezeichnung')) as $bez) {
                $wahl = $zuordnung[$bez] ?? self::typVorschlag((string)$bez);
                if ($wahl === 'neu' || (int)$wahl <= 0) {
                    $db->execute("INSERT INTO instrument_typen (name, bmv_automatisch) VALUES (?, 1)", [mb_substr((string)$bez, 0, 100)]);
                    $wahl = (int)$db->lastInsertId();
                }
                $typen[$bez] = (int)$wahl;
                $db->execute(
                    "INSERT INTO bmv_inventar_zuordnung (bezeichnung, instrument_typ_id) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE instrument_typ_id = VALUES(instrument_typ_id)",
                    [$bez, (int)$wahl]
                );
            }
            $festeZuordnung = array_map('intval', $typen);

            foreach (self::vorschau($zeilen, $festeZuordnung) as $z) {
                $herkunft = [$z['bmv_inventar_id'], $z['bmv_bezeichnung'], $z['bmv_anmerkung'], $z['bmv_aenderung'], $jetzt];
                if ($z['aktion'] === 'konflikt' && !in_array($z['bmv_inventar_id'], $konfliktZusammen, true)) {
                    $zaehler['uebersprungen']++;
                    continue;
                }
                if ($z['aktion'] === 'neu') {
                    $db->execute(
                        "INSERT INTO instrumente (inventar_nummer, instrument_typ_id, hersteller, seriennummer, anschaffungsdatum,
                            anschaffungspreis, standort, lieferant, mitglied_id, ausgeliehen_seit,
                            bmv_inventar_id, bmv_bezeichnung, bmv_anmerkung, bmv_aenderung, bmv_import_am)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        array_merge([$z['inventar_nummer'], $z['instrument_typ_id'], $z['hersteller'], $z['seriennummer'],
                            $z['anschaffungsdatum'], $z['anschaffungspreis'], $z['standort'], $z['lieferant'],
                            $z['mitglied_id'], $z['mitglied_id'] ? $z['ausgeliehen_seit'] : null], $herkunft)
                    );
                    $zaehler['neu']++;
                    continue;
                }
                // Zusammenführen / erneut übernehmen: nur leere Felder ergänzen, Syncopa-Werte bleiben
                $set = [];
                $werte = [];
                foreach (array_keys($z['ergaenzt']) as $spalte) {
                    $set[] = "$spalte = ?";
                    $werte[] = $z[$spalte];
                }
                if (isset($z['ergaenzt']['mitglied_id']) && $z['ausgeliehen_seit'] && !isset($z['ergaenzt']['ausgeliehen_seit'])) {
                    $set[] = "ausgeliehen_seit = ?";
                    $werte[] = $z['ausgeliehen_seit'];
                }
                $set = array_merge($set, ['bmv_inventar_id = ?', 'bmv_bezeichnung = ?', 'bmv_anmerkung = ?', 'bmv_aenderung = ?', 'bmv_import_am = ?']);
                $db->execute("UPDATE instrumente SET " . implode(', ', $set) . " WHERE id = ?", array_merge($werte, $herkunft, [$z['vorhanden']['id']]));
                if ($z['aktion'] === 'vorhanden') {
                    $zaehler[$z['ergaenzt'] ? 'ergaenzt' : 'unveraendert']++;
                } else {
                    $zaehler['zusammen']++;
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        return $zaehler;
    }
}
