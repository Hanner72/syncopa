<?php
// classes/BmvZusatzdaten.php
// Laufbahn je Mitglied: Funktionen, Ehrungen, Instrumente, Prüfungen, Mitgliedsarten.
//
// Jeder Eintrag kommt entweder aus dem BMV (bmv_eintrag_id gesetzt) oder wurde in Syncopa angelegt (leer).
// - Einträge aus dem BMV sind in Syncopa gesperrt und werden beim „BMV Daten holen“ aktualisiert/entfernt.
// - Eigene Einträge sind frei bearbeitbar und mit sync_status 'neu' für die spätere Übertragung vorgemerkt.
// - Taucht ein eigener Eintrag später auch im BMV auf, wird er mit dem BMV-Eintrag verknüpft (BMV hat Vorrang).
// Speicherort: Instrumente in mitglied_instrumente (die „Gespielten Instrumente“, auch von anderen Modulen
// genutzt), alles andere in bmv_mitglied_daten. Der BMV liefert nur Nummern; Namen stehen in bmv_bezeichnungen.

class BmvZusatzdaten {

    /** Art => BMV-Endpunkt, Feld mit der Nummer, Anzeigenamen */
    public const ARTEN = [
        'funktion'     => ['pfad' => '/api/MskFkt',    'code' => 'funktion_ID',            'name' => 'Funktion',     'mehrzahl' => 'Funktionen'],
        'ehrung'       => ['pfad' => '/api/Mskehre',   'code' => 'ehrenzeichen_ID',        'name' => 'Ehrenzeichen', 'mehrzahl' => 'Ehrungen'],
        'instrument'   => ['pfad' => '/api/MskInst',   'code' => 'instrument_ID',          'name' => 'Instrument',   'mehrzahl' => 'Instrumente'],
        'pruefung'     => ['pfad' => '/api/MskPrf',    'code' => 'prüfungsbezeichnung_ID', 'name' => 'Prüfung',      'mehrzahl' => 'Prüfungen'],
        'mitgliedsart' => ['pfad' => '/api/MskMitart', 'code' => 'mitgliedsart_ID',        'name' => 'Mitgliedsart', 'mehrzahl' => 'Mitgliedsarten'],
    ];

    /** Arten, für die Bezeichnungen gepflegt werden (zusätzlich zu ARTEN der Bereich einer Funktion) */
    public const BEZEICHNUNG_ARTEN = [
        'ehrung'       => 'Ehrenzeichen',
        'funktion'     => 'Funktionen',
        'bereich'      => 'Bereiche (Funktionen)',
        'instrument'   => 'Instrumente',
        'pruefung'     => 'Prüfungen',
        'mitgliedsart' => 'Mitgliedsarten',
    ];

    /** Vorschläge für das Ergebnis einer Prüfung (wie sie im BMV vorkommen) */
    public const ERGEBNISSE = ['Ausgezeichneter Erfolg', 'Sehr guter Erfolg', 'Guter Erfolg', 'Bestanden',
        'Sehr gut', 'Gut', 'Befriedigend', 'Genügend'];

    /** Spalten in bmv_mitglied_daten, die aus dem BMV kommen */
    private const SPALTEN = ['mitglied_id', 'code', 'bereich_code', 'instrument_code', 'von', 'bis', 'stimme',
        'ergebnis', 'ergebnistext', 'bemerkung', 'bmv_status', 'bmv_aenderung'];

    /** Spalten in mitglied_instrumente, die aus dem BMV kommen (Hauptinstrument und Formation bleiben Syncopa-Angaben) */
    private const INSTRUMENT_SPALTEN = ['mitglied_id', 'instrument_typ_id', 'bmv_code', 'stimme', 'seit_datum', 'bis_datum', 'bmv_aenderung'];

    private static ?array $bezeichnungen = null;
    private static ?array $instrumentZuordnung = null;

    // ------------------------------------------------------------------------------------------------
    // Aus dem BMV holen
    // ------------------------------------------------------------------------------------------------

    /** Alle Zusatzdaten aus dem BMV holen: [art => [Datensätze]] */
    public static function holen(BmvApi $api): array {
        $daten = [];
        foreach (self::ARTEN as $art => $info) {
            $daten[$art] = $api->get($info['pfad']);
        }
        return $daten;
    }

    /**
     * Vergleicht die BMV-Daten mit dem Stand in Syncopa (nur Einträge aus dem BMV, eigene zählen nicht).
     * Einträge zu Musikern, die es in Syncopa nicht gibt, werden nicht gezählt.
     */
    public static function vergleich(array $daten): array {
        $soll = self::sollZeilen($daten, false);
        $ist = self::istZeilen();
        $ergebnis = ['neu' => 0, 'geaendert' => 0, 'entfernt' => 0, 'je_art' => []];
        foreach (self::ARTEN as $art => $_) {
            $ergebnis['je_art'][$art] = ['bmv' => 0, 'neu' => 0, 'geaendert' => 0, 'entfernt' => 0];
        }
        foreach ($soll as $schluessel => $zeile) {
            $art = $zeile['art'];
            $ergebnis['je_art'][$art]['bmv']++;
            if (!isset($ist[$schluessel])) {
                $ergebnis['neu']++;
                $ergebnis['je_art'][$art]['neu']++;
            } elseif (self::fingerabdruck($ist[$schluessel]) !== self::fingerabdruck($zeile)) {
                $ergebnis['geaendert']++;
                $ergebnis['je_art'][$art]['geaendert']++;
            }
        }
        foreach ($ist as $schluessel => $zeile) {
            if (!isset($soll[$schluessel])) {
                $ergebnis['entfernt']++;
                $ergebnis['je_art'][$zeile['art']]['entfernt']++;
            }
        }
        return $ergebnis;
    }

    /**
     * Übernimmt alle Zusatzdaten in Syncopa: neue anlegen (oder einen gleichen eigenen Eintrag damit verknüpfen),
     * geänderte aktualisieren, im BMV gelöschte entfernen. Eigene Einträge bleiben unberührt.
     */
    public static function uebernehmen(BmvApi $api, ?array $daten = null): array {
        $daten = $daten ?? self::holen($api);
        self::bezeichnungenVorbefuellen($api, $daten); // zuerst, damit neue Instrumente ihren Namen bekommen
        $vergleich = self::vergleich($daten);

        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $soll = self::sollZeilen($daten, true);
            $ist = self::istZeilen();
            foreach ($soll as $schluessel => $zeile) {
                if (isset($ist[$schluessel])) {
                    if (self::fingerabdruck($ist[$schluessel]) !== self::fingerabdruck($zeile)) {
                        self::bmvZeileSchreiben($zeile, (int)$ist[$schluessel]['id']);
                    }
                    continue;
                }
                self::bmvZeileSchreiben($zeile, self::gleicherEigenerEintrag($zeile));
            }
            foreach ($ist as $schluessel => $zeile) {
                if (!isset($soll[$schluessel])) {
                    $tabelle = $zeile['art'] === 'instrument' ? 'mitglied_instrumente' : 'bmv_mitglied_daten';
                    $db->execute("DELETE FROM $tabelle WHERE id = ?", [$zeile['id']]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        return $vergleich;
    }

    /** Schreibt eine BMV-Zeile: neu anlegen ($id = null) oder einen vorhandenen Eintrag damit überschreiben */
    private static function bmvZeileSchreiben(array $zeile, ?int $id): void {
        $db = Database::getInstance();
        if ($zeile['art'] === 'instrument') {
            $tabelle = 'mitglied_instrumente';
            $spalten = array_merge(['bmv_eintrag_id'], self::INSTRUMENT_SPALTEN);
        } else {
            $tabelle = 'bmv_mitglied_daten';
            $spalten = array_merge(['bmv_eintrag_id', 'art'], self::SPALTEN);
        }
        $werte = array_map(fn($s) => $zeile[$s], $spalten);
        if ($id === null) {
            $extra = $tabelle === 'bmv_mitglied_daten' ? ', erstellt_am' : '';
            $db->execute(
                "INSERT INTO $tabelle (" . implode(', ', $spalten) . $extra . ") VALUES ("
                    . implode(', ', array_fill(0, count($spalten), '?')) . ($extra ? ', NOW()' : '') . ")",
                $werte
            );
        } else {
            $db->execute(
                "UPDATE $tabelle SET " . implode(', ', array_map(fn($s) => "$s = ?", $spalten)) . ", sync_status = NULL WHERE id = ?",
                array_merge($werte, [$id])
            );
        }
    }

    /**
     * Sucht einen eigenen Eintrag, der dem BMV-Eintrag entspricht (gleiches Mitglied, gleiche Nummer und
     * gleiches Jahr bzw. gleiches Instrument). Er wird dann mit dem BMV-Eintrag verknüpft statt doppelt geführt.
     */
    private static function gleicherEigenerEintrag(array $zeile): ?int {
        $db = Database::getInstance();
        if ($zeile['art'] === 'instrument') {
            $treffer = $db->fetchOne(
                "SELECT id FROM mitglied_instrumente WHERE bmv_eintrag_id IS NULL AND mitglied_id = ? AND instrument_typ_id = ?
                 ORDER BY id LIMIT 1",
                [$zeile['mitglied_id'], $zeile['instrument_typ_id']]
            );
        } else {
            $treffer = $db->fetchOne(
                "SELECT id FROM bmv_mitglied_daten WHERE bmv_eintrag_id IS NULL AND mitglied_id = ? AND art = ? AND code <=> ?
                   AND (von IS NULL OR ? IS NULL OR YEAR(von) = YEAR(?)) ORDER BY id LIMIT 1",
                [$zeile['mitglied_id'], $zeile['art'], $zeile['code'], $zeile['von'], $zeile['von']]
            );
        }
        return $treffer ? (int)$treffer['id'] : null;
    }

    /** BMV-Datensätze als Zeilen für Syncopa: [art|bmv-id => zeile]. $anlegen: fehlende Instrumentarten anlegen. */
    private static function sollZeilen(array $daten, bool $anlegen): array {
        $mitgliedNachBmvId = [];
        foreach (Database::getInstance()->fetchAll("SELECT id, bmv_id FROM mitglieder WHERE bmv_id IS NOT NULL") as $m) {
            $mitgliedNachBmvId[$m['bmv_id']] = (int)$m['id'];
        }
        $soll = [];
        foreach ($daten as $art => $datensaetze) {
            foreach ($datensaetze as $r) {
                $mitgliedId = $mitgliedNachBmvId[(string)($r['m_NR'] ?? '')] ?? null;
                if ($mitgliedId === null || empty($r['id'])) {
                    continue;
                }
                $zeile = self::zeile($art, $r) + ['mitglied_id' => $mitgliedId];
                if ($art === 'instrument') {
                    $zeile['instrument_typ_id'] = $zeile['bmv_code'] !== null ? self::instrumentTypFuerBmv($zeile['bmv_code'], $anlegen) : null;
                    if ($anlegen && $zeile['instrument_typ_id'] === null) {
                        continue; // ohne Instrumentnummer nicht übernehmbar
                    }
                }
                $soll[$art . '|' . $zeile['bmv_eintrag_id']] = $zeile;
            }
        }
        return $soll;
    }

    /** Einträge aus dem BMV, die in Syncopa liegen: [art|bmv-id => zeile] */
    private static function istZeilen(): array {
        $db = Database::getInstance();
        $ist = [];
        foreach ($db->fetchAll("SELECT * FROM bmv_mitglied_daten WHERE bmv_eintrag_id IS NOT NULL") as $z) {
            $ist[$z['art'] . '|' . $z['bmv_eintrag_id']] = $z;
        }
        foreach ($db->fetchAll("SELECT * FROM mitglied_instrumente WHERE bmv_eintrag_id IS NOT NULL") as $z) {
            $ist['instrument|' . $z['bmv_eintrag_id']] = $z + ['art' => 'instrument'];
        }
        return $ist;
    }

    /** Ein BMV-Datensatz als Zeile für Syncopa */
    private static function zeile(string $art, array $r): array {
        $datum = fn($w) => ($w === null || trim((string)$w) === '') ? null : substr(trim((string)$w), 0, 10);
        $text  = fn($w, $max = 255) => ($w === null || trim((string)$w) === '') ? null : mb_substr(trim((string)$w), 0, $max);
        $zahl  = fn($w) => ($w === null || $w === '') ? null : (int)$w;
        $code  = $zahl($r[self::ARTEN[$art]['code']] ?? null);

        if ($art === 'instrument') {
            return [
                'bmv_eintrag_id' => (string)$r['id'],
                'art'            => 'instrument',
                'bmv_code'       => $code,
                'stimme'         => $text($r['stimme'] ?? null, 50),
                'seit_datum'     => $datum($r['aktmusv'] ?? null),
                'bis_datum'      => $datum($r['aktmusb'] ?? null),
                'bmv_aenderung'  => $text($r['aenderung'] ?? null, 50),
            ];
        }

        $z = [
            'bmv_eintrag_id'  => (string)$r['id'],
            'art'             => $art,
            'code'            => $code,
            'bereich_code'    => null,
            'instrument_code' => null,
            'von'             => null,
            'bis'             => null,
            'stimme'          => null,
            'ergebnis'        => null,
            'ergebnistext'    => null,
            'bemerkung'       => $text($r['kurzbemerkung'] ?? null),
            'bmv_status'      => $text($r['status'] ?? null, 10),
            'bmv_aenderung'   => $text($r['aenderung'] ?? null, 50),
        ];
        switch ($art) {
            case 'funktion':
                $z['bereich_code'] = $zahl($r['bereich_ID'] ?? null);
                $z['von'] = $datum($r['fkT_VON'] ?? null);
                $z['bis'] = $datum($r['fkT_BIS'] ?? null);
                break;
            case 'ehrung':
                $z['von'] = $datum($r['v_DATUM'] ?? null);
                break;
            case 'pruefung':
                $z['instrument_code'] = $zahl($r['instrument_ID'] ?? null);
                $z['von'] = $datum($r['prF_DAT'] ?? null);
                $z['bis'] = $datum($r['prF_BIS'] ?? null);
                $z['ergebnis'] = $text($r['prF_PKTE'] ?? null);
                $z['ergebnistext'] = $text($r['ergebnistext'] ?? null, 2000);
                break;
            case 'mitgliedsart':
                $z['von'] = $datum($r['v_DATUM'] ?? null);
                $z['bis'] = $datum($r['b_DATUM'] ?? null);
                break;
        }
        return $z;
    }

    private static function fingerabdruck(array $zeile): string {
        $spalten = ($zeile['art'] ?? '') === 'instrument' ? self::INSTRUMENT_SPALTEN : self::SPALTEN;
        $werte = [];
        foreach ($spalten as $s) {
            $werte[] = trim((string)($zeile[$s] ?? ''));
        }
        return implode("\x1f", $werte);
    }

    /**
     * Syncopa-Instrumentart zu einer BMV-Instrumentnummer: zuerst die eingestellte Zuordnung, dann eine
     * Instrumentart mit gleichem Namen. Mit $anlegen wird sonst eine neue Instrumentart angelegt
     * (als „automatisch“ markiert, damit sie nach dem Umzuordnen wieder entfernt werden kann).
     */
    public static function instrumentTypFuerBmv(int $bmvCode, bool $anlegen): ?int {
        $zuordnung = self::instrumentZuordnung();
        if (isset($zuordnung[$bmvCode])) {
            return $zuordnung[$bmvCode];
        }
        if (!$anlegen) {
            return null;
        }
        $db = Database::getInstance();
        $name = self::bezeichnungen()['instrument'][$bmvCode] ?? '';
        $typ = $name !== '' ? $db->fetchOne("SELECT id FROM instrument_typen WHERE LOWER(name) = LOWER(?) LIMIT 1", [$name]) : null;
        if ($typ) {
            $typId = (int)$typ['id'];
        } else {
            $db->execute(
                "INSERT INTO instrument_typen (name, bmv_automatisch) VALUES (?, 1)",
                [$name !== '' ? $name : 'Instrument Nr. ' . $bmvCode]
            );
            $typId = (int)$db->lastInsertId();
        }
        $db->execute(
            "INSERT INTO bmv_bezeichnungen (art, code, name, instrument_typ_id) VALUES ('instrument', ?, ?, ?)
             ON DUPLICATE KEY UPDATE instrument_typ_id = VALUES(instrument_typ_id)",
            [$bmvCode, $name, $typId]
        );
        self::$instrumentZuordnung = null;
        return $typId;
    }

    /** Zuordnung BMV-Instrumentnummer => Syncopa-Instrumentart */
    public static function instrumentZuordnung(): array {
        if (self::$instrumentZuordnung === null) {
            self::$instrumentZuordnung = [];
            foreach (Database::getInstance()->fetchAll(
                "SELECT b.code, b.instrument_typ_id FROM bmv_bezeichnungen b
                 JOIN instrument_typen t ON t.id = b.instrument_typ_id WHERE b.art = 'instrument'"
            ) as $r) {
                self::$instrumentZuordnung[(int)$r['code']] = (int)$r['instrument_typ_id'];
            }
        }
        return self::$instrumentZuordnung;
    }

    /**
     * Ändert die Zuordnung BMV-Instrumentnummer => Syncopa-Instrumentart ([code => typ-id]) und zieht die
     * Einträge aus dem BMV mit um. Automatisch angelegte Instrumentarten, die danach niemand mehr nutzt, werden entfernt.
     */
    public static function instrumentZuordnungSpeichern(array $eingaben): void {
        $db = Database::getInstance();
        $alt = self::instrumentZuordnung();
        foreach ($eingaben as $code => $typId) {
            $code = (int)$code;
            $typId = (int)$typId;
            if ($typId <= 0 || ($alt[$code] ?? null) === $typId) continue;
            $db->execute(
                "INSERT INTO bmv_bezeichnungen (art, code, name, instrument_typ_id) VALUES ('instrument', ?, '', ?)
                 ON DUPLICATE KEY UPDATE instrument_typ_id = VALUES(instrument_typ_id)",
                [$code, $typId]
            );
            $db->execute(
                "UPDATE mitglied_instrumente SET instrument_typ_id = ? WHERE bmv_code = ? AND bmv_eintrag_id IS NOT NULL",
                [$typId, $code]
            );
        }
        $db->execute(
            "DELETE t FROM instrument_typen t
             WHERE t.bmv_automatisch = 1
               AND NOT EXISTS (SELECT 1 FROM mitglied_instrumente mi WHERE mi.instrument_typ_id = t.id)
               AND NOT EXISTS (SELECT 1 FROM instrumente i WHERE i.instrument_typ_id = t.id)
               AND NOT EXISTS (SELECT 1 FROM bmv_bezeichnungen b WHERE b.art = 'instrument' AND b.instrument_typ_id = t.id)"
        );
        self::$instrumentZuordnung = null;
    }

    /**
     * Legt für jede vorkommende Nummer einen Eintrag in bmv_bezeichnungen an (Name leer) und befüllt,
     * was sich ermitteln lässt: Bereiche aus SetupBereich, Prüfungen und Instrumente aus den Prüfungsurkunden.
     * Vom Verein eingetragene Namen werden nie überschrieben.
     */
    public static function bezeichnungenVorbefuellen(BmvApi $api, array $daten): void {
        $gefunden = [];
        foreach ($daten as $art => $datensaetze) {
            $codeFeld = self::ARTEN[$art]['code'];
            foreach ($datensaetze as $r) {
                if (isset($r[$codeFeld]) && $r[$codeFeld] !== '') {
                    $gefunden[$art][(int)$r[$codeFeld]] ??= '';
                }
                if ($art === 'funktion' && isset($r['bereich_ID'])) {
                    $gefunden['bereich'][(int)$r['bereich_ID']] ??= '';
                }
                if ($art === 'pruefung') {
                    if (!empty($r['instrument_ID'])) {
                        $gefunden['instrument'][(int)$r['instrument_ID']] ??= '';
                    }
                    $urkunde = (string)($r['urkundentext'] ?? '');
                    $abzeichen = self::urkundenFeld($urkunde, 'Leistungsabzeichen');
                    if ($abzeichen !== '' && isset($r['prüfungsbezeichnung_ID'])) {
                        $gefunden['pruefung'][(int)$r['prüfungsbezeichnung_ID']] = $abzeichen;
                    }
                    $instrument = self::urkundenFeld($urkunde, 'Instrument');
                    if ($instrument !== '' && !empty($r['instrument_ID'])) {
                        $gefunden['instrument'][(int)$r['instrument_ID']] = $instrument;
                    }
                }
            }
        }
        try {
            foreach ($api->get('/api/SetupBereich') as $b) {
                $gefunden['bereich'][(int)$b['id']] = trim((string)($b['bereich'] ?? ''));
            }
        } catch (\Throwable $e) { /* Bereiche sind optional */ }

        $db = Database::getInstance();
        foreach ($gefunden as $art => $codes) {
            foreach ($codes as $code => $name) {
                $db->execute(
                    "INSERT INTO bmv_bezeichnungen (art, code, name) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE name = IF(name = '', VALUES(name), name)",
                    [$art, $code, $name]
                );
            }
        }
        self::$bezeichnungen = null;
    }

    /** Liest ein Feld aus dem Urkunden-XML (als Text, ohne XML-Parser wegen falscher utf-16-Angabe) */
    private static function urkundenFeld(string $xml, string $feld): string {
        if ($xml === '' || !preg_match('#<' . $feld . '>(.*?)</' . $feld . '>#s', $xml, $m)) {
            return '';
        }
        return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    // ------------------------------------------------------------------------------------------------
    // Bezeichnungen
    // ------------------------------------------------------------------------------------------------

    /**
     * Alle Bezeichnungen: [art => [code => name]]. Eigene Einträge des Vereins haben Vorrang, sonst gilt der
     * Standardname aus includes/bmv_bezeichnungen_standard.php (Ehrenzeichen nur für das eigene BMV-Land).
     */
    public static function bezeichnungen(): array {
        if (self::$bezeichnungen === null) {
            $liste = self::standardBezeichnungen();
            foreach (self::eigeneBezeichnungen() as $art => $codes) {
                foreach ($codes as $code => $name) {
                    if ($name !== '' || !isset($liste[$art][$code])) {
                        $liste[$art][$code] = $name;
                    }
                }
            }
            foreach ($liste as &$codes) {
                ksort($codes);
            }
            self::$bezeichnungen = $liste;
        }
        return self::$bezeichnungen;
    }

    /** Nur die vom Verein eingetragenen bzw. beim Holen gefundenen Bezeichnungen: [art => [code => name]] */
    public static function eigeneBezeichnungen(): array {
        $liste = [];
        foreach (Database::getInstance()->fetchAll("SELECT art, code, name FROM bmv_bezeichnungen ORDER BY art, code") as $r) {
            $liste[$r['art']][(int)$r['code']] = $r['name'];
        }
        return $liste;
    }

    /** Standardnamen, die mit Syncopa ausgeliefert werden: [art => [code => name]] */
    public static function standardBezeichnungen(): array {
        $datei = __DIR__ . '/../includes/bmv_bezeichnungen_standard.php';
        if (!is_file($datei)) {
            return [];
        }
        $standard = require $datei;
        $land = Database::getInstance()->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'bmv_land'");
        $land = strtoupper(trim((string)($land['wert'] ?? '')));
        $liste = $standard['alle'] ?? [];
        foreach ($standard['land'][$land] ?? [] as $art => $codes) {
            $liste[$art] = ($liste[$art] ?? []) + $codes;
        }
        return $liste;
    }

    /** Name zu einer BMV-Nummer, sonst z. B. „Ehrenzeichen Nr. 31“ */
    public static function name(string $art, $code): string {
        if ($code === null || $code === '') {
            return '–';
        }
        $name = self::bezeichnungen()[$art][(int)$code] ?? '';
        if ($name !== '') {
            return $name;
        }
        $label = self::ARTEN[$art]['name'] ?? (self::BEZEICHNUNG_ARTEN[$art] ?? $art);
        return $label . ' Nr. ' . (int)$code;
    }

    /** Auswahlliste für eigene Einträge: [code => name], benannte alphabetisch, unbenannte („Nr. X“) am Ende */
    public static function auswahl(string $art): array {
        $liste = [];
        foreach (self::bezeichnungen()[$art] ?? [] as $code => $name) {
            $liste[$code] = $name !== '' ? $name : self::name($art, $code);
        }
        uksort($liste, function ($a, $b) use ($art) {
            $na = (self::bezeichnungen()[$art][$a] ?? '') === '';
            $nb = (self::bezeichnungen()[$art][$b] ?? '') === '';
            return $na <=> $nb ?: strcasecmp(self::name($art, $a), self::name($art, $b));
        });
        return $liste;
    }

    /** Speichert die vom Verein eingetragenen Namen: [art => [code => name]] */
    public static function bezeichnungenSpeichern(array $eingaben): void {
        $db = Database::getInstance();
        foreach ($eingaben as $art => $codes) {
            if (!isset(self::BEZEICHNUNG_ARTEN[$art]) || !is_array($codes)) continue;
            foreach ($codes as $code => $name) {
                $db->execute(
                    "INSERT INTO bmv_bezeichnungen (art, code, name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)",
                    [$art, (int)$code, mb_substr(trim((string)$name), 0, 150)]
                );
            }
        }
        self::$bezeichnungen = null;
    }

    // ------------------------------------------------------------------------------------------------
    // Einträge eines Mitglieds anzeigen und bearbeiten
    // ------------------------------------------------------------------------------------------------

    /** Alle Einträge eines Mitglieds: [art => [Zeilen]] – laufende zuerst, dann neueste zuerst */
    public static function fuerMitglied(int $mitgliedId): array {
        $db = Database::getInstance();
        $ergebnis = array_fill_keys(array_keys(self::ARTEN), []);
        foreach ($db->fetchAll(
            "SELECT * FROM bmv_mitglied_daten WHERE mitglied_id = ? ORDER BY (bis IS NULL) DESC, von DESC, id DESC", [$mitgliedId]
        ) as $z) {
            $ergebnis[$z['art']][] = $z;
        }
        $ergebnis['instrument'] = $db->fetchAll(
            "SELECT mi.*, mi.seit_datum AS von, mi.bis_datum AS bis, it.name AS instrument_name, r.name AS register_name,
                    f.name AS formation_name, f.farbe AS formation_farbe, f.kuerzel AS formation_kuerzel
             FROM mitglied_instrumente mi
             JOIN instrument_typen it ON it.id = mi.instrument_typ_id
             LEFT JOIN register r ON r.id = it.register_id
             LEFT JOIN formationen f ON f.id = mi.formation_id
             WHERE mi.mitglied_id = ?
             ORDER BY (mi.bis_datum IS NULL OR mi.bis_datum >= CURDATE()) DESC, mi.hauptinstrument DESC, mi.seit_datum DESC, it.name",
            [$mitgliedId]
        );
        return $ergebnis;
    }

    /** Ein Eintrag (Zeile aus der passenden Tabelle) oder null */
    public static function eintragLaden(string $art, int $id): ?array {
        $tabelle = $art === 'instrument' ? 'mitglied_instrumente' : 'bmv_mitglied_daten';
        $zeile = Database::getInstance()->fetchOne("SELECT * FROM $tabelle WHERE id = ?", [$id]);
        if (!$zeile || ($art !== 'instrument' && $zeile['art'] !== $art)) {
            return null;
        }
        return $zeile;
    }

    /** Ob ein Eintrag in Syncopa geändert werden darf (eigene immer, BMV-Einträge erst wenn Schreiben möglich ist) */
    public static function bearbeitbar(array $zeile): bool {
        return empty($zeile['bmv_eintrag_id']) || BmvApi::kannSchreiben();
    }

    /**
     * Legt einen eigenen Eintrag an oder ändert ihn. Liefert die ID. Wirft RuntimeException mit verständlicher Meldung.
     * Bei Instrumenten aus dem BMV lassen sich nur Hauptinstrument und Formation ändern (das sind Syncopa-Angaben).
     */
    public static function eintragSpeichern(string $art, int $mitgliedId, array $e, ?int $id = null): int {
        if (!isset(self::ARTEN[$art])) {
            throw new RuntimeException('Unbekannte Art.');
        }
        $alt = null;
        if ($id) {
            $alt = self::eintragLaden($art, $id);
            if (!$alt || (int)$alt['mitglied_id'] !== $mitgliedId) {
                throw new RuntimeException('Eintrag nicht gefunden.');
            }
        }
        $datum = function ($wert, string $feld, bool $pflicht) {
            $wert = trim((string)($wert ?? ''));
            if ($wert === '') {
                if ($pflicht) throw new RuntimeException("Bitte $feld angeben.");
                return null;
            }
            $d = DateTime::createFromFormat('Y-m-d', $wert);
            if (!$d || $d->format('Y-m-d') !== $wert) throw new RuntimeException(ucfirst($feld) . " ist kein gültiges Datum.");
            return $wert;
        };
        $text = fn($w, $max = 255) => trim((string)($w ?? '')) === '' ? null : mb_substr(trim((string)$w), 0, $max);
        $db = Database::getInstance();

        if ($art === 'instrument') {
            return self::instrumentSpeichern($mitgliedId, $e, $alt, $datum, $text);
        }

        if ($alt && !self::bearbeitbar($alt)) {
            throw new RuntimeException('Dieser Eintrag kommt aus dem BMV und kann nur dort geändert werden.');
        }
        $code = (int)($e['code'] ?? 0);
        if ($code <= 0) {
            throw new RuntimeException('Bitte ' . self::ARTEN[$art]['name'] . ' auswählen.');
        }
        $daten = [
            'code'            => $code,
            'bereich_code'    => $art === 'funktion' && !empty($e['bereich_code']) ? (int)$e['bereich_code'] : null,
            'instrument_code' => $art === 'pruefung' && !empty($e['instrument_code']) ? (int)$e['instrument_code'] : null,
            'von'             => $datum($e['von'] ?? null, $art === 'funktion' || $art === 'mitgliedsart' ? 'das Von-Datum' : 'das Datum', $art !== 'funktion'),
            'bis'             => in_array($art, ['funktion', 'mitgliedsart'], true) ? $datum($e['bis'] ?? null, 'das Bis-Datum', false) : null,
            'ergebnis'        => $art === 'pruefung' ? $text($e['ergebnis'] ?? null) : null,
            'bemerkung'       => $text($e['bemerkung'] ?? null),
        ];
        if ($daten['von'] && $daten['bis'] && $daten['bis'] < $daten['von']) {
            throw new RuntimeException('Das Bis-Datum liegt vor dem Von-Datum.');
        }
        $syncStatus = ($alt && !empty($alt['bmv_eintrag_id'])) ? 'geaendert' : 'neu';

        if ($alt) {
            $db->execute(
                "UPDATE bmv_mitglied_daten SET " . implode(', ', array_map(fn($s) => "$s = ?", array_keys($daten))) . ", sync_status = ? WHERE id = ?",
                array_merge(array_values($daten), [$syncStatus, $alt['id']])
            );
            return (int)$alt['id'];
        }
        $db->execute(
            "INSERT INTO bmv_mitglied_daten (art, mitglied_id, " . implode(', ', array_keys($daten)) . ", sync_status, erstellt_am)
             VALUES (?, ?, " . implode(', ', array_fill(0, count($daten), '?')) . ", 'neu', NOW())",
            array_merge([$art, $mitgliedId], array_values($daten))
        );
        return (int)$db->lastInsertId();
    }

    private static function instrumentSpeichern(int $mitgliedId, array $e, ?array $alt, callable $datum, callable $text): int {
        $db = Database::getInstance();
        $haupt = !empty($e['hauptinstrument']) ? 1 : 0;
        $formation = !empty($e['formation_id']) ? (int)$e['formation_id'] : null;

        if ($alt && !self::bearbeitbar($alt)) {
            // Aus dem BMV: nur die Syncopa-Angaben ändern
            $db->execute("UPDATE mitglied_instrumente SET hauptinstrument = ?, formation_id = ? WHERE id = ?", [$haupt, $formation, $alt['id']]);
            $id = (int)$alt['id'];
        } else {
            $typId = (int)($e['instrument_typ_id'] ?? 0);
            if ($typId <= 0 || !$db->fetchOne("SELECT id FROM instrument_typen WHERE id = ?", [$typId])) {
                throw new RuntimeException('Bitte ein Instrument auswählen.');
            }
            $seit = $datum($e['seit_datum'] ?? null, 'das Seit-Datum', false);
            $bis  = $datum($e['bis_datum'] ?? null, 'das Bis-Datum', false);
            if ($seit && $bis && $bis < $seit) {
                throw new RuntimeException('Das Bis-Datum liegt vor dem Seit-Datum.');
            }
            // BMV-Nummer zur Instrumentart merken, damit der Eintrag später übertragen werden kann
            $bmvCode = array_search($typId, self::instrumentZuordnung(), true);
            $werte = [$typId, $bmvCode === false ? null : (int)$bmvCode, $text($e['stimme'] ?? null, 50), $seit, $bis, $haupt, $formation];
            if ($alt) {
                $db->execute(
                    "UPDATE mitglied_instrumente SET instrument_typ_id = ?, bmv_code = ?, stimme = ?, seit_datum = ?, bis_datum = ?,
                            hauptinstrument = ?, formation_id = ?, sync_status = ? WHERE id = ?",
                    array_merge($werte, [!empty($alt['bmv_eintrag_id']) ? 'geaendert' : 'neu', $alt['id']])
                );
                $id = (int)$alt['id'];
            } else {
                $db->execute(
                    "INSERT INTO mitglied_instrumente (mitglied_id, instrument_typ_id, bmv_code, stimme, seit_datum, bis_datum,
                            hauptinstrument, formation_id, sync_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'neu')",
                    array_merge([$mitgliedId], $werte)
                );
                $id = (int)$db->lastInsertId();
            }
        }
        if ($haupt) {
            // Es gibt nur ein Hauptinstrument
            $db->execute("UPDATE mitglied_instrumente SET hauptinstrument = 0 WHERE mitglied_id = ? AND id <> ?", [$mitgliedId, $id]);
        }
        return $id;
    }

    /** Löscht einen eigenen Eintrag. Einträge aus dem BMV können (noch) nicht gelöscht werden. */
    public static function eintragLoeschen(string $art, int $mitgliedId, int $id): void {
        $alt = isset(self::ARTEN[$art]) ? self::eintragLaden($art, $id) : null;
        if (!$alt || (int)$alt['mitglied_id'] !== $mitgliedId) {
            throw new RuntimeException('Eintrag nicht gefunden.');
        }
        if (!self::bearbeitbar($alt)) {
            throw new RuntimeException('Dieser Eintrag kommt aus dem BMV und kann nur dort gelöscht werden.');
        }
        $tabelle = $art === 'instrument' ? 'mitglied_instrumente' : 'bmv_mitglied_daten';
        Database::getInstance()->execute("DELETE FROM $tabelle WHERE id = ?", [$id]);
    }

    /** Anzahl eigener Einträge, die noch an den BMV übertragen werden müssen: [art => anzahl, 'gesamt' => n] */
    public static function offeneUebertragungen(): array {
        $db = Database::getInstance();
        $ergebnis = array_fill_keys(array_keys(self::ARTEN), 0);
        foreach ($db->fetchAll("SELECT art, COUNT(*) AS n FROM bmv_mitglied_daten WHERE sync_status IS NOT NULL GROUP BY art") as $r) {
            $ergebnis[$r['art']] = (int)$r['n'];
        }
        $ergebnis['instrument'] = (int)$db->fetchOne("SELECT COUNT(*) AS n FROM mitglied_instrumente WHERE sync_status IS NOT NULL")['n'];
        $ergebnis['gesamt'] = array_sum($ergebnis);
        return $ergebnis;
    }

    /**
     * Für das spätere Schreiben in den BMV: ein Eintrag aus Syncopa als BMV-Datensatz (Gegenstück zu zeile()).
     * $mNr ist die BMV-ID des Mitglieds, $vereinNr die BMV-Vereinsnummer (Einstellung bmv_verein_nr).
     */
    public static function zuBmv(string $art, array $z, string $mNr, int $vereinNr): array {
        $datumZeit = fn($d) => $d ? substr((string)$d, 0, 10) . 'T00:00:00' : null;
        $basis = ['m_NR' => $mNr, 'verein_ID' => $vereinNr];
        if (!empty($z['bmv_eintrag_id'])) {
            $basis['id'] = $z['bmv_eintrag_id'];
        }
        switch ($art) {
            case 'funktion':
                return $basis + ['funktion_ID' => (int)$z['code'], 'bereich_ID' => $z['bereich_code'] !== null ? (int)$z['bereich_code'] : null,
                    'fkT_VON' => $datumZeit($z['von']), 'fkT_BIS' => $datumZeit($z['bis']), 'kurzbemerkung' => $z['bemerkung']];
            case 'ehrung':
                return $basis + ['ehrenzeichen_ID' => (int)$z['code'], 'v_DATUM' => $z['von'], 'kurzbemerkung' => $z['bemerkung']];
            case 'pruefung':
                return $basis + ['prüfungsbezeichnung_ID' => (int)$z['code'], 'instrument_ID' => $z['instrument_code'] !== null ? (int)$z['instrument_code'] : null,
                    'prF_DAT' => $z['von'], 'prF_PKTE' => $z['ergebnis'], 'kurzbemerkung' => $z['bemerkung']];
            case 'mitgliedsart':
                return $basis + ['mitgliedsart_ID' => (int)$z['code'], 'v_DATUM' => $datumZeit($z['von']), 'b_DATUM' => $datumZeit($z['bis']),
                    'kurzbemerkung' => $z['bemerkung']];
            case 'instrument':
                return $basis + ['instrument_ID' => $z['bmv_code'] !== null ? (int)$z['bmv_code'] : null, 'stimme' => $z['stimme'],
                    'aktmusv' => $datumZeit($z['seit_datum']), 'aktmusb' => $datumZeit($z['bis_datum'])];
        }
        throw new RuntimeException('Unbekannte Art.');
    }
}
