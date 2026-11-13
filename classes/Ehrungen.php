<?php
// classes/Ehrungen.php
// Verliehene und anstehende Ehrungen. Verliehene kommen aus dem BMV (bmv_mitglied_daten, art = 'ehrung').
// Anstehende werden aus den Regeln (ehrungsregeln: Ehrenzeichen nach X Jahren) und den Mitgliedsjahren
// berechnet. Mitgliedsjahre = Zeiträume der Mitgliedsarten, die in den Regeln als „zählt“ markiert sind.

class Ehrungen {

    /** Einstellung: Mitgliedsart-Nummern, die als Mitgliedsjahre zählen (Komma-Liste; leer = alle) */
    public const EINSTELLUNG_MITGLIEDSARTEN = 'ehrung_mitgliedsarten';

    private const TAGE_PRO_JAHR = 365.2425;

    /** Regeln, sortiert nach Jahren: [['id', 'ehrung_code', 'jahre', 'aktiv']] */
    public static function regeln(bool $nurAktive = true): array {
        return Database::getInstance()->fetchAll(
            "SELECT * FROM ehrungsregeln" . ($nurAktive ? " WHERE aktiv = 1" : "") . " ORDER BY jahre, ehrung_code"
        );
    }

    /** Regeln speichern: [ehrung_code => ['jahre' => int|'' , 'aktiv' => bool]]; leere Jahre löschen die Regel */
    public static function regelnSpeichern(array $eingaben): void {
        $db = Database::getInstance();
        foreach ($eingaben as $code => $e) {
            $jahre = trim((string)($e['jahre'] ?? ''));
            if ($jahre === '' || (int)$jahre <= 0) {
                $db->execute("DELETE FROM ehrungsregeln WHERE ehrung_code = ?", [(int)$code]);
                continue;
            }
            $db->execute(
                "INSERT INTO ehrungsregeln (ehrung_code, jahre, aktiv) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE jahre = VALUES(jahre), aktiv = VALUES(aktiv)",
                [(int)$code, (int)$jahre, !empty($e['aktiv']) ? 1 : 0]
            );
        }
    }

    /** Mitgliedsart-Nummern, die als Mitgliedsjahre zählen ([] = alle) */
    public static function gezaehlteMitgliedsarten(): array {
        $zeile = Database::getInstance()->fetchOne(
            "SELECT wert FROM einstellungen WHERE schluessel = ?", [self::EINSTELLUNG_MITGLIEDSARTEN]
        );
        $werte = array_filter(array_map('trim', explode(',', (string)($zeile['wert'] ?? ''))), fn($w) => $w !== '');
        return array_map('intval', $werte);
    }

    public static function gezaehlteMitgliedsartenSpeichern(array $codes): void {
        $wert = implode(',', array_map('intval', $codes));
        Database::getInstance()->execute(
            "INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?) ON DUPLICATE KEY UPDATE wert = ?",
            [self::EINSTELLUNG_MITGLIEDSARTEN, $wert, $wert]
        );
    }

    /**
     * Zusammengefasste Mitgliedszeiträume (Überschneidungen nur einmal gezählt).
     * Liefert [['von' => DateTimeImmutable, 'bis' => ?DateTimeImmutable]] – bis = null heißt „läuft noch“.
     */
    public static function zeitraeume(array $mitgliedsarten, array $gezaehlt): array {
        $liste = [];
        foreach ($mitgliedsarten as $m) {
            if (empty($m['von'])) continue;
            if ($gezaehlt && !in_array((int)$m['code'], $gezaehlt, true)) continue;
            $liste[] = [
                'von' => new DateTimeImmutable($m['von']),
                'bis' => !empty($m['bis']) ? new DateTimeImmutable($m['bis']) : null,
            ];
        }
        usort($liste, fn($a, $b) => $a['von'] <=> $b['von']);

        $zusammen = [];
        foreach ($liste as $z) {
            $letzter = count($zusammen) - 1;
            if ($letzter >= 0) {
                $l = $zusammen[$letzter];
                if ($l['bis'] === null || $z['von'] <= $l['bis']->modify('+1 day')) {
                    if ($l['bis'] !== null && ($z['bis'] === null || $z['bis'] > $l['bis'])) {
                        $zusammen[$letzter]['bis'] = $z['bis'];
                    }
                    continue;
                }
            }
            $zusammen[] = $z;
        }
        return $zusammen;
    }

    /** Mitgliedsjahre bis heute */
    public static function jahreBisHeute(array $zeitraeume): float {
        $heute = new DateTimeImmutable('today');
        $tage = 0;
        foreach ($zeitraeume as $z) {
            if ($z['von'] > $heute) continue;
            $ende = ($z['bis'] === null || $z['bis'] > $heute) ? $heute : $z['bis'];
            $tage += $z['von']->diff($ende)->days + 1;
        }
        return $tage / self::TAGE_PRO_JAHR;
    }

    /** Datum, an dem die Mitgliedsjahre erreicht werden (bei laufender Mitgliedschaft hochgerechnet), sonst null */
    public static function datumNachJahren(array $zeitraeume, int $jahre): ?DateTimeImmutable {
        $rest = (int)ceil($jahre * self::TAGE_PRO_JAHR);
        foreach ($zeitraeume as $z) {
            $tage = $z['bis'] === null ? PHP_INT_MAX : $z['von']->diff($z['bis'])->days + 1;
            if ($tage >= $rest) {
                return $z['von']->modify('+' . ($rest - 1) . ' days');
            }
            $rest -= $tage;
        }
        return null;
    }

    /**
     * Ehrungen eines Mitglieds: Mitgliedsjahre, verliehene und anstehende Ehrungen.
     * Anstehend sind aktive Regeln, deren Ehrenzeichen das Mitglied noch nicht hat. Hat es bereits ein Ehrenzeichen
     * einer Regel mit gleich vielen oder mehr Jahren, gelten die kleineren als erledigt. Dafür zählen auch
     * nicht aktive Regeln (z. B. alte Ehrenzeichen-Nummern, die nicht mehr vergeben werden).
     */
    public static function fuerMitglied(int $mitgliedId, ?array $daten = null, ?array $regeln = null, ?array $gezaehlt = null): array {
        $daten    = $daten ?? BmvZusatzdaten::fuerMitglied($mitgliedId);
        $regeln   = $regeln ?? self::regeln(false);
        $gezaehlt = $gezaehlt ?? self::gezaehlteMitgliedsarten();

        $zeitraeume = self::zeitraeume($daten['mitgliedsart'], $gezaehlt);
        $erhalteneCodes = array_map(fn($e) => (int)$e['code'], $daten['ehrung']);

        $hoechsteErhalteneJahre = 0;
        foreach ($regeln as $r) {
            if (in_array((int)$r['ehrung_code'], $erhalteneCodes, true)) {
                $hoechsteErhalteneJahre = max($hoechsteErhalteneJahre, (int)$r['jahre']);
            }
        }

        $heute = new DateTimeImmutable('today');
        $anstehend = [];
        foreach ($regeln as $r) {
            $code = (int)$r['ehrung_code'];
            if (!$r['aktiv']) continue;
            if (in_array($code, $erhalteneCodes, true) || (int)$r['jahre'] <= $hoechsteErhalteneJahre) continue;
            $datum = self::datumNachJahren($zeitraeume, (int)$r['jahre']);
            if ($datum === null) continue;
            $anstehend[] = [
                'code'   => $code,
                'name'   => BmvZusatzdaten::name('ehrung', $code),
                'jahre'  => (int)$r['jahre'],
                'datum'  => $datum,
                'faellig'=> $datum <= $heute,
            ];
        }

        // Von mehreren bereits erreichten Stufen nur die höchste anzeigen (niedrigere werden nicht nachverliehen)
        $faellige = array_filter($anstehend, fn($a) => $a['faellig']);
        if (count($faellige) > 1) {
            $hoechste = max(array_column($faellige, 'jahre'));
            $anstehend = array_values(array_filter($anstehend, fn($a) => !$a['faellig'] || $a['jahre'] === $hoechste));
        }

        $erhalten = $daten['ehrung'];
        usort($erhalten, fn($a, $b) => strcmp((string)$b['von'], (string)$a['von']));

        return [
            'jahre'      => self::jahreBisHeute($zeitraeume),
            'laufend'    => $zeitraeume && end($zeitraeume)['bis'] === null,
            'erhalten'   => $erhalten,
            'anstehend'  => $anstehend,
        ];
    }

    /**
     * Anstehende Ehrungen aller Mitglieder (ohne ausgetretene) bis Ende des angegebenen Jahres,
     * inklusive bereits fälliger, noch nicht verliehener. Sortiert nach Datum.
     */
    public static function anstehendBis(int $bisJahr): array {
        $db = Database::getInstance();
        $regeln = self::regeln(false);
        if (!array_filter($regeln, fn($r) => $r['aktiv'])) {
            return [];
        }
        $gezaehlt = self::gezaehlteMitgliedsarten();
        $grenze = new DateTimeImmutable($bisJahr . '-12-31');

        $daten = [];
        foreach ($db->fetchAll("SELECT * FROM bmv_mitglied_daten WHERE art IN ('ehrung', 'mitgliedsart')") as $z) {
            $daten[(int)$z['mitglied_id']][$z['art']][] = $z;
        }

        $liste = [];
        foreach ($db->fetchAll("SELECT id, vorname, nachname, mitgliedsnummer, mitgliedsart_code FROM mitglieder WHERE mitgliedsart_code IN (SELECT code FROM mitgliedsarten WHERE gast = 0)") as $m) {
            $d = $daten[(int)$m['id']] ?? [];
            $info = self::fuerMitglied((int)$m['id'], ['ehrung' => $d['ehrung'] ?? [], 'mitgliedsart' => $d['mitgliedsart'] ?? []], $regeln, $gezaehlt);
            foreach ($info['anstehend'] as $a) {
                if ($a['datum'] > $grenze) continue;
                $liste[] = $a + ['mitglied' => $m, 'mitgliedsjahre' => $info['jahre']];
            }
        }
        usort($liste, fn($a, $b) => $a['datum'] <=> $b['datum'] ?: strcmp($a['mitglied']['nachname'], $b['mitglied']['nachname']));
        return $liste;
    }
}
