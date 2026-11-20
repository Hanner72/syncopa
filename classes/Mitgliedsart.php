<?php
// classes/Mitgliedsart.php
// Mitgliedsarten ersetzen den früheren Mitgliedsstatus (aktiv/passiv/ehrenmitglied/ausgetreten).
// - Katalog in der Tabelle mitgliedsarten (Stammdaten → Mitgliedsarten). Die Nummer ist die BMV-Nummer;
//   eigene Arten (z. B. Ehrenmitglied) bekommen eine eigene Nummer, die später auf die BMV-Nummer geändert werden kann.
// - Je Art: zählt als aktiv (Proben, Ausrückungen, Instrumente, Statistik …), beitragspflichtig, eigener Beitrag, Farbe.
// - Gast: kein Vereinsmitglied (z. B. Gastmusiker/in). Spielt nur in den Formationen mit, denen er zugeordnet ist,
//   wird nie an den BMV übertragen und zählt nicht bei Beiträgen, Ehrungen, Statistik und der Mitgliederliste.
// - Die aktuelle Art eines Mitglieds ergibt sich aus dem laufenden Zeitraum im Reiter „Mitgliedsart“
//   (bmv_mitglied_daten, art = 'mitgliedsart'), aus dem BMV oder selbst eingetragen – auch ohne BMV-Anbindung.
//   Sie wird in mitglieder.mitgliedsart_code zwischengespeichert. Ohne laufenden Zeitraum gilt ein Mitglied als ausgetreten.

class Mitgliedsart {

    public const FARBEN = [
        'success' => 'Grün', 'primary' => 'Blau', 'info' => 'Hellblau', 'warning' => 'Gelb',
        'danger' => 'Rot', 'secondary' => 'Grau', 'dark' => 'Dunkel',
    ];

    /** Beginn der Nummern für eigene Arten, die es (noch) nicht im BMV gibt */
    public const EIGENE_NUMMERN_AB = 9001;

    private static ?array $alle = null;

    /** Katalog: [code => Zeile], sortiert */
    public static function alle(): array {
        if (self::$alle === null) {
            self::$alle = [];
            foreach (Database::getInstance()->fetchAll("SELECT * FROM mitgliedsarten ORDER BY sortierung, name") as $r) {
                self::$alle[(int)$r['code']] = $r;
            }
        }
        return self::$alle;
    }

    public static function name(?int $code): string {
        if ($code === null) {
            return 'Ausgetreten';
        }
        return self::alle()[$code]['name'] ?? ('Mitgliedsart Nr. ' . $code);
    }

    /** Abzeichen für die aktuelle Mitgliedsart (ohne = „Ausgetreten“) */
    public static function badge($code): string {
        $code = ($code === null || $code === '') ? null : (int)$code;
        $farbe = $code === null ? 'secondary' : (self::alle()[$code]['farbe'] ?? 'secondary');
        $text = $code === null ? 'text-bg-light border' : 'bg-' . $farbe;
        return '<span class="badge ' . $text . '">' . htmlspecialchars(self::name($code)) . '</span>';
    }

    /**
     * SQL-Bedingung „aktives Vereinsmitglied“ (ohne Gäste) für die Mitglieder-Tabelle mit Alias $alias.
     * Für Abfragen innerhalb einer Formation (Proben, Ausrückungen der Formation) Gäste mitnehmen: $mitGaesten = true.
     */
    public static function sqlAktiv(string $alias = 'm', bool $mitGaesten = false): string {
        return "{$alias}.mitgliedsart_code IN (SELECT code FROM mitgliedsarten WHERE aktiv = 1" . ($mitGaesten ? "" : " AND gast = 0") . ")";
    }

    /** SQL-Bedingung „ist derzeit Vereinsmitglied“ (laufende Mitgliedsart, kein Gast) */
    public static function sqlMitglied(string $alias = 'm'): string {
        return "{$alias}.mitgliedsart_code IN (SELECT code FROM mitgliedsarten WHERE gast = 0)";
    }

    /** Ob die Mitgliedsart ein Gast ist (kein Vereinsmitglied) */
    public static function istGast($code): bool {
        return $code !== null && $code !== '' && !empty(self::alle()[(int)$code]['gast']);
    }

    /** Legt einmalig die Mitgliedsart „Gastmusiker/in“ an (Einstellung mitgliedsart_gast_angelegt) */
    public static function gastArtAnlegen(): void {
        $db = Database::getInstance();
        if ($db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'mitgliedsart_gast_angelegt'")) {
            return;
        }
        if (!$db->fetchOne("SELECT code FROM mitgliedsarten WHERE gast = 1 LIMIT 1")) {
            $db->execute(
                "INSERT INTO mitgliedsarten (code, name, aktiv, beitragspflichtig, gast, farbe, sortierung) VALUES (?, 'Gastmusiker/in', 1, 0, 1, 'dark', 90)",
                [self::naechsteEigeneNummer()]
            );
        }
        $db->execute("INSERT INTO einstellungen (schluessel, wert) VALUES ('mitgliedsart_gast_angelegt', ?)", [date('Y-m-d H:i:s')]);
        self::$alle = null;
    }

    // ------------------------------------------------------------------------------------------------
    // Aktuelle Mitgliedsart je Mitglied
    // ------------------------------------------------------------------------------------------------

    /**
     * Ermittelt die aktuelle Mitgliedsart (laufender Zeitraum) und speichert sie beim Mitglied.
     * Laufen mehrere Zeiträume gleichzeitig, gilt der zuletzt begonnene. Ohne $mitgliedId für alle Mitglieder.
     * Fehlen Arten im Katalog, werden sie mit ihrem Namen angelegt.
     */
    public static function aktualisieren(?int $mitgliedId = null): void {
        $db = Database::getInstance();
        $wo = $mitgliedId ? "WHERE m.id = ?" : "";
        $db->execute(
            "UPDATE mitglieder m SET m.mitgliedsart_code = (
                SELECT d.code FROM bmv_mitglied_daten d
                WHERE d.mitglied_id = m.id AND d.art = 'mitgliedsart' AND d.code IS NOT NULL
                  AND (d.von IS NULL OR d.von <= CURDATE()) AND (d.bis IS NULL OR d.bis >= CURDATE())
                ORDER BY d.von DESC, d.id DESC LIMIT 1
            ) $wo",
            $mitgliedId ? [$mitgliedId] : []
        );
        self::fehlendeArtenAnlegen();
        if (!$mitgliedId) {
            $db->execute(
                "INSERT INTO einstellungen (schluessel, wert) VALUES ('mitgliedsart_stand', ?) ON DUPLICATE KEY UPDATE wert = VALUES(wert)",
                [date('Y-m-d')]
            );
        }
    }

    /** Einmal am Tag neu berechnen (Zeiträume beginnen und enden mit dem Datum) */
    public static function aktualisierenFallsNoetig(): void {
        $stand = Database::getInstance()->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'mitgliedsart_stand'");
        if (($stand['wert'] ?? '') !== date('Y-m-d')) {
            self::aktualisieren();
        }
    }

    /** Nummern, die in Mitgliedsart-Zeiträumen vorkommen, aber nicht im Katalog sind, anlegen */
    private static function fehlendeArtenAnlegen(): void {
        $db = Database::getInstance();
        $fehlend = $db->fetchAll(
            "SELECT DISTINCT d.code FROM bmv_mitglied_daten d
             WHERE d.art = 'mitgliedsart' AND d.code IS NOT NULL AND d.code NOT IN (SELECT code FROM mitgliedsarten)"
        );
        foreach ($fehlend as $r) {
            $db->execute(
                "INSERT IGNORE INTO mitgliedsarten (code, name) VALUES (?, ?)",
                [(int)$r['code'], BmvZusatzdaten::name('mitgliedsart', (int)$r['code'])]
            );
        }
        if ($fehlend) {
            self::$alle = null;
        }
    }

    /**
     * Legt für ein Mitglied eine neue Mitgliedsart ab einem Datum an (eigener Eintrag, für den BMV vorgemerkt).
     * Eine laufende eigene Mitgliedsart wird am Vortag beendet.
     */
    public static function setzen(int $mitgliedId, int $code, string $ab): void {
        $db = Database::getInstance();
        $vortag = date('Y-m-d', strtotime($ab . ' -1 day'));
        $db->execute(
            "UPDATE bmv_mitglied_daten SET bis = ?, sync_status = IF(code IN (SELECT code FROM mitgliedsarten WHERE gast = 1), NULL, 'neu')
             WHERE mitglied_id = ? AND art = 'mitgliedsart' AND bis IS NULL AND bmv_eintrag_id IS NULL AND (von IS NULL OR von <= ?)",
            [$vortag, $mitgliedId, $vortag]
        );
        BmvZusatzdaten::eintragSpeichern('mitgliedsart', $mitgliedId, ['code' => $code, 'von' => $ab]);
        self::aktualisieren($mitgliedId);
    }

    /**
     * Änderung aus dem Mitglied-Formular: $wahl ist eine Mitgliedsart-Nummer oder 'ausgetreten', gültig ab $ab.
     * Eintritt in eine andere Art: neuer eigener Zeitraum (laufende eigene werden am Vortag beendet).
     * Austritt: laufende eigene Zeiträume enden am Vortag; läuft noch ein Zeitraum aus dem BMV, muss er dort beendet werden.
     */
    public static function aendern(int $mitgliedId, string $wahl, string $ab): void {
        $ab = DateTime::createFromFormat('Y-m-d', $ab) ? $ab : date('Y-m-d');
        $aktuell = Database::getInstance()->fetchOne("SELECT mitgliedsart_code FROM mitglieder WHERE id = ?", [$mitgliedId]);
        $aktuell = $aktuell['mitgliedsart_code'] !== null ? (string)(int)$aktuell['mitgliedsart_code'] : 'ausgetreten';
        if ($wahl === '' || $wahl === $aktuell) {
            return;
        }
        $db = Database::getInstance();
        if ($wahl === 'ausgetreten') {
            $bmvLaeuft = $db->fetchOne(
                "SELECT id FROM bmv_mitglied_daten WHERE mitglied_id = ? AND art = 'mitgliedsart' AND bmv_eintrag_id IS NOT NULL
                   AND (bis IS NULL OR bis >= ?) LIMIT 1",
                [$mitgliedId, $ab]
            );
            if ($bmvLaeuft) {
                throw new RuntimeException('Die Mitgliedsart kommt aus dem BMV. Bitte den Austritt im BMV-Portal eintragen; Syncopa übernimmt ihn beim nächsten Holen.');
            }
            $vortag = date('Y-m-d', strtotime($ab . ' -1 day'));
            $db->execute(
                "UPDATE bmv_mitglied_daten SET bis = ?, sync_status = IF(code IN (SELECT code FROM mitgliedsarten WHERE gast = 1), NULL, 'neu')
                 WHERE mitglied_id = ? AND art = 'mitgliedsart' AND bmv_eintrag_id IS NULL AND (bis IS NULL OR bis > ?)",
                [$vortag, $mitgliedId, $vortag]
            );
            self::aktualisieren($mitgliedId);
            return;
        }
        if (!isset(self::alle()[(int)$wahl])) {
            throw new RuntimeException('Unbekannte Mitgliedsart.');
        }
        self::setzen($mitgliedId, (int)$wahl, $ab);
    }

    /** Beginn des laufenden Zeitraums der aktuellen Mitgliedsart (für die Anzeige „seit …“) */
    public static function seit(int $mitgliedId): ?string {
        $r = Database::getInstance()->fetchOne(
            "SELECT d.von FROM bmv_mitglied_daten d JOIN mitglieder m ON m.id = d.mitglied_id AND m.mitgliedsart_code = d.code
             WHERE d.mitglied_id = ? AND d.art = 'mitgliedsart' AND (d.von IS NULL OR d.von <= CURDATE()) AND (d.bis IS NULL OR d.bis >= CURDATE())
             ORDER BY d.von DESC LIMIT 1",
            [$mitgliedId]
        );
        return $r['von'] ?? null;
    }

    // ------------------------------------------------------------------------------------------------
    // Katalog pflegen (Stammdaten)
    // ------------------------------------------------------------------------------------------------

    /** Anzahl Mitglieder je aktueller Mitgliedsart: [code => n] */
    public static function anzahlJeArt(): array {
        $n = [];
        foreach (Database::getInstance()->fetchAll(
            "SELECT mitgliedsart_code AS code, COUNT(*) AS n FROM mitglieder WHERE mitgliedsart_code IS NOT NULL GROUP BY mitgliedsart_code"
        ) as $r) {
            $n[(int)$r['code']] = (int)$r['n'];
        }
        return $n;
    }

    /**
     * Speichert eine Mitgliedsart. Ändert sich die Nummer (z. B. eigene Nummer → BMV-Nummer), werden alle
     * Zeiträume und Mitglieder mit umgestellt.
     */
    public static function speichern(array $e, ?int $alterCode = null): void {
        $db = Database::getInstance();
        $code = (int)($e['code'] ?? 0);
        $name = mb_substr(trim((string)($e['name'] ?? '')), 0, 100);
        if ($code <= 0) throw new RuntimeException('Bitte eine Nummer angeben (die Nummer aus dem BMV, sonst ab ' . self::EIGENE_NUMMERN_AB . ').');
        if ($name === '') throw new RuntimeException('Bitte einen Namen angeben.');
        if ($code !== $alterCode && $db->fetchOne("SELECT code FROM mitgliedsarten WHERE code = ?", [$code])) {
            throw new RuntimeException('Die Nummer ' . $code . ' ist schon vergeben.');
        }
        $betrag = trim(str_replace(',', '.', (string)($e['beitrag'] ?? '')));
        $werte = [
            $code, $name, !empty($e['aktiv']) ? 1 : 0, !empty($e['beitragspflichtig']) ? 1 : 0,
            $betrag === '' ? null : round((float)$betrag, 2),
            isset(self::FARBEN[$e['farbe'] ?? '']) ? $e['farbe'] : 'secondary',
            (int)($e['sortierung'] ?? 100),
            !empty($e['gast']) ? 1 : 0,
        ];
        $db->beginTransaction();
        try {
            if ($alterCode === null) {
                $db->execute("INSERT INTO mitgliedsarten (code, name, aktiv, beitragspflichtig, beitrag, farbe, sortierung, gast) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", $werte);
            } else {
                $db->execute(
                    "UPDATE mitgliedsarten SET code = ?, name = ?, aktiv = ?, beitragspflichtig = ?, beitrag = ?, farbe = ?, sortierung = ?, gast = ? WHERE code = ?",
                    array_merge($werte, [$alterCode])
                );
                if ($code !== $alterCode) {
                    $db->execute("UPDATE bmv_mitglied_daten SET code = ? WHERE art = 'mitgliedsart' AND code = ?", [$code, $alterCode]);
                    $db->execute("UPDATE mitglieder SET mitgliedsart_code = ? WHERE mitgliedsart_code = ?", [$code, $alterCode]);
                    $db->execute("UPDATE bmv_bezeichnungen SET code = ? WHERE art = 'mitgliedsart' AND code = ?", [$code, $alterCode]);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        self::$alle = null;
    }

    /** Löscht eine Mitgliedsart, wenn sie in keinem Zeitraum vorkommt */
    public static function loeschen(int $code): void {
        $db = Database::getInstance();
        if ($db->fetchOne("SELECT id FROM bmv_mitglied_daten WHERE art = 'mitgliedsart' AND code = ? LIMIT 1", [$code])) {
            throw new RuntimeException('Diese Mitgliedsart ist bei Mitgliedern eingetragen und kann nicht gelöscht werden.');
        }
        $db->execute("DELETE FROM mitgliedsarten WHERE code = ?", [$code]);
        self::$alle = null;
    }

    public static function naechsteEigeneNummer(): int {
        $max = Database::getInstance()->fetchOne("SELECT MAX(code) AS m FROM mitgliedsarten WHERE code >= ?", [self::EIGENE_NUMMERN_AB]);
        return max(self::EIGENE_NUMMERN_AB, (int)($max['m'] ?? 0) + 1);
    }

    // ------------------------------------------------------------------------------------------------
    // Einmalige Übernahme vom alten Status (läuft beim ersten Aufruf nach dem Update)
    // ------------------------------------------------------------------------------------------------

    /**
     * Legt den Katalog an (aus den Beitragseinstellungen des alten Status) und gibt Mitgliedern ohne Mitgliedsart
     * eine aus ihrem bisherigen Status. Läuft nur einmal (Einstellung mitgliedsart_uebernommen).
     */
    public static function einmaligUebernehmen(): void {
        $db = Database::getInstance();
        if ($db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'mitgliedsart_uebernommen'")) {
            return;
        }
        $s = [];
        foreach ($db->fetchAll("SELECT schluessel, wert FROM einstellungen WHERE schluessel LIKE 'beitrag%'") as $r) {
            $s[$r['schluessel']] = $r['wert'];
        }
        $passivBetrag = isset($s['beitrag_passiv_betrag']) && $s['beitrag_passiv_betrag'] !== '' ? (float)$s['beitrag_passiv_betrag'] : null;
        // Nummern wie im BMV (Steiermark): 1 aktiv, 3 unterstützend, 5 Musikschüler; Ehrenmitglied als eigene Art
        $standard = [
            1    => ['aktives Mitglied', 1, ($s['beitrag_aktiv'] ?? '1') === '1', null, 'success', 10],
            3    => ['unterstützendes Mitglied', 0, ($s['beitrag_passiv'] ?? '0') === '1', $passivBetrag, 'warning', 20],
            5    => ['MusikschülerIn', 1, false, null, 'info', 30],
            9001 => ['Ehrenmitglied', 0, ($s['beitrag_ehrenmitglied'] ?? '0') === '1', null, 'primary', 40],
        ];
        foreach ($standard as $code => [$name, $aktiv, $pflicht, $betrag, $farbe, $sort]) {
            $db->execute(
                "INSERT IGNORE INTO mitgliedsarten (code, name, aktiv, beitragspflichtig, beitrag, farbe, sortierung) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$code, BmvZusatzdaten::name('mitgliedsart', $code) !== 'Mitgliedsart Nr. ' . $code ? BmvZusatzdaten::name('mitgliedsart', $code) : $name,
                 $aktiv, $pflicht ? 1 : 0, $betrag, $farbe, $sort]
            );
        }
        // Mitglieder ohne Mitgliedsart: aus dem alten Status übernehmen
        $nachStatus = ['aktiv' => 1, 'passiv' => 3, 'ehrenmitglied' => 9001, 'ueberpruefen' => 1];
        foreach ($db->fetchAll(
            "SELECT m.* FROM mitglieder m WHERE NOT EXISTS (
                SELECT 1 FROM bmv_mitglied_daten d WHERE d.mitglied_id = m.id AND d.art = 'mitgliedsart')"
        ) as $m) {
            $von = $m['eintritt_datum'] ?: ($m['erstellt_am'] ? substr($m['erstellt_am'], 0, 10) : date('Y-m-d'));
            if ($m['status'] === 'ausgetreten') {
                if (empty($m['austritt_datum'])) continue; // ohne Daten: einfach keine laufende Mitgliedsart
                $code = 1;
                $bis = $m['austritt_datum'];
            } else {
                $code = $nachStatus[$m['status']] ?? 1;
                $bis = null;
            }
            $db->execute(
                "INSERT INTO bmv_mitglied_daten (art, mitglied_id, code, von, bis, bemerkung, sync_status, erstellt_am)
                 VALUES ('mitgliedsart', ?, ?, ?, ?, 'aus dem früheren Status übernommen', 'neu', NOW())",
                [$m['id'], $code, $von, $bis]
            );
        }
        $db->execute("INSERT INTO einstellungen (schluessel, wert) VALUES ('mitgliedsart_uebernommen', ?)", [date('Y-m-d H:i:s')]);
        self::$alle = null;
        self::aktualisieren();
    }
}
