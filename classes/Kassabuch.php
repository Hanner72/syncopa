<?php
// classes/Kassabuch.php
// Kassabuch des Vereins: Konten (Kassa, Bank, Sparbuch …) mit Anfangsbestand je Jahr, Buchungen mit Kategorie und Beleg,
// Umbuchungen zwischen Konten, Jahresabschluss (gesperrte Jahre), Budget, Kassasturz, Übernahme von Beiträgen und Festen.
// Buchungsarten in der Tabelle finanzen: einnahme, ausgabe, umbuchung (von konto_id nach gegenkonto_id).

class Kassabuch {

    public const ARTEN = ['kassa' => 'Kassa (bar)', 'bank' => 'Bankkonto', 'sparbuch' => 'Sparbuch', 'sonstig' => 'Sonstiges'];
    public const TYPEN = ['einnahme' => 'Einnahme', 'ausgabe' => 'Ausgabe', 'umbuchung' => 'Umbuchung'];
    private const BELEG_ENDUNGEN = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    private static function db(): Database { return Database::getInstance(); }

    // ------------------------------------------------------------------ Konten

    public static function konten(bool $nurAktive = true): array {
        return self::db()->fetchAll("SELECT k.*, f.name AS formation_name FROM finanz_konten k LEFT JOIN formationen f ON f.id = k.formation_id"
            . ($nurAktive ? " WHERE k.aktiv = 1" : "") . " ORDER BY k.aktiv DESC, k.sortierung, k.name");
    }

    public static function konto(int $id): ?array {
        return self::db()->fetchOne("SELECT * FROM finanz_konten WHERE id = ?", [$id]) ?: null;
    }

    /** Erstes aktives Konto einer Art ('kassa' oder alles außer Kassa) */
    public static function standardKonto(bool $bar): ?int {
        $r = self::db()->fetchOne("SELECT id FROM finanz_konten WHERE aktiv = 1 AND art " . ($bar ? "= 'kassa'" : "<> 'kassa'") . " ORDER BY sortierung, id LIMIT 1")
            ?: self::db()->fetchOne("SELECT id FROM finanz_konten WHERE aktiv = 1 ORDER BY sortierung, id LIMIT 1");
        return $r ? (int)$r['id'] : null;
    }

    public static function kontoSpeichern(array $d, ?int $id = null): int {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Bitte einen Namen für das Konto eingeben.');
        $art = isset(self::ARTEN[$d['art'] ?? '']) ? $d['art'] : 'bank';
        $werte = [$name, $art, trim((string)($d['iban'] ?? '')) ?: null, ($d['formation_id'] ?? '') !== '' ? (int)$d['formation_id'] : null,
                  (int)($d['sortierung'] ?? 100), !empty($d['aktiv']) ? 1 : 0];
        if ($id) {
            self::db()->execute("UPDATE finanz_konten SET name=?, art=?, iban=?, formation_id=?, sortierung=?, aktiv=? WHERE id=?", array_merge($werte, [$id]));
            return $id;
        }
        self::db()->execute("INSERT INTO finanz_konten (name, art, iban, formation_id, sortierung, aktiv, erstellt_am) VALUES (?, ?, ?, ?, ?, ?, NOW())", $werte);
        return (int)self::db()->lastInsertId();
    }

    public static function kontoLoeschen(int $id): void {
        $n = (int)self::db()->fetchOne("SELECT COUNT(*) c FROM finanzen WHERE konto_id = ? OR gegenkonto_id = ?", [$id, $id])['c'];
        if ($n) throw new RuntimeException("Auf diesem Konto gibt es $n Buchungen. Statt zu löschen bitte auf „inaktiv“ setzen.");
        self::db()->execute("DELETE FROM finanz_konten WHERE id = ?", [$id]);
    }

    // ------------------------------------------------------------------ Bestände

    /** Eingetragener Anfangsbestand genau für dieses Jahr (oder null) */
    public static function anfangsbestandEingetragen(int $kontoId, int $jahr): ?float {
        $r = self::db()->fetchOne("SELECT betrag FROM finanz_anfangsbestaende WHERE konto_id = ? AND jahr = ?", [$kontoId, $jahr]);
        return $r ? (float)$r['betrag'] : null;
    }

    public static function anfangsbestandSetzen(int $kontoId, int $jahr, ?float $betrag): void {
        if (self::istGesperrt($jahr)) throw new RuntimeException("Das Jahr $jahr ist abgeschlossen.");
        if ($betrag === null) {
            self::db()->execute("DELETE FROM finanz_anfangsbestaende WHERE konto_id = ? AND jahr = ?", [$kontoId, $jahr]);
        } else {
            self::db()->execute("INSERT INTO finanz_anfangsbestaende (konto_id, jahr, betrag) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE betrag = VALUES(betrag)", [$kontoId, $jahr, round($betrag, 2)]);
        }
    }

    /**
     * Anfangsbestand am 1.1. eines Jahres: letzter eingetragener Anfangsbestand (Jahr ≤ $jahr)
     * plus alle Buchungen von dort bis zum Vorjahresende. Ohne Eintrag: Summe aller früheren Buchungen.
     */
    public static function anfangsbestand(int $kontoId, int $jahr): float {
        $basis = self::db()->fetchOne("SELECT jahr, betrag FROM finanz_anfangsbestaende WHERE konto_id = ? AND jahr <= ? ORDER BY jahr DESC LIMIT 1", [$kontoId, $jahr]);
        $von = $basis ? $basis['jahr'] . '-01-01' : '1900-01-01';
        $wert = $basis ? (float)$basis['betrag'] : 0.0;
        return round($wert + self::bewegung($kontoId, $von, ($jahr - 1) . '-12-31'), 2);
    }

    /** Veränderung eines Kontos zwischen zwei Daten (inklusive) */
    public static function bewegung(int $kontoId, string $von, string $bis): float {
        if ($bis < $von) return 0.0;
        $r = self::db()->fetchOne(
            "SELECT COALESCE(SUM(CASE
                WHEN typ = 'einnahme' AND konto_id = ? THEN betrag
                WHEN typ = 'ausgabe'  AND konto_id = ? THEN -betrag
                WHEN typ = 'umbuchung' AND konto_id = ? THEN -betrag
                WHEN typ = 'umbuchung' AND gegenkonto_id = ? THEN betrag
                ELSE 0 END), 0) AS s
             FROM finanzen WHERE (konto_id = ? OR gegenkonto_id = ?) AND datum BETWEEN ? AND ?",
            [$kontoId, $kontoId, $kontoId, $kontoId, $kontoId, $kontoId, $von, $bis]);
        return round((float)$r['s'], 2);
    }

    /** Kontostand am Ende eines Tages */
    public static function stand(int $kontoId, string $datum): float {
        $jahr = (int)substr($datum, 0, 4);
        return round(self::anfangsbestand($kontoId, $jahr) + self::bewegung($kontoId, "$jahr-01-01", $datum), 2);
    }

    public static function endbestand(int $kontoId, int $jahr): float {
        return self::stand($kontoId, "$jahr-12-31");
    }

    /** Übersicht aller Konten für ein Jahr: Anfang, Einnahmen, Ausgaben, Umbuchungen, Ende */
    public static function kontenUebersicht(int $jahr, bool $nurAktive = false): array {
        $liste = [];
        foreach (self::konten($nurAktive) as $k) {
            $id = (int)$k['id'];
            $r = self::db()->fetchOne(
                "SELECT COALESCE(SUM(CASE WHEN typ='einnahme' AND konto_id=? THEN betrag END),0) ein,
                        COALESCE(SUM(CASE WHEN typ='ausgabe' AND konto_id=? THEN betrag END),0) aus,
                        COALESCE(SUM(CASE WHEN typ='umbuchung' AND gegenkonto_id=? THEN betrag END),0) umb_ein,
                        COALESCE(SUM(CASE WHEN typ='umbuchung' AND konto_id=? THEN betrag END),0) umb_aus,
                        COUNT(*) anzahl
                 FROM finanzen WHERE (konto_id=? OR gegenkonto_id=?) AND YEAR(datum)=?", [$id, $id, $id, $id, $id, $id, $jahr]);
            $anfang = self::anfangsbestand($id, $jahr);
            $ende = round($anfang + $r['ein'] - $r['aus'] + $r['umb_ein'] - $r['umb_aus'], 2);
            if (!$k['aktiv'] && !$r['anzahl'] && abs($anfang) < 0.005) continue;
            $liste[] = $k + ['anfang' => $anfang, 'einnahmen' => (float)$r['ein'], 'ausgaben' => (float)$r['aus'],
                             'umbuchung_ein' => (float)$r['umb_ein'], 'umbuchung_aus' => (float)$r['umb_aus'], 'ende' => $ende, 'anzahl' => (int)$r['anzahl'],
                             'anfang_eingetragen' => self::anfangsbestandEingetragen($id, $jahr)];
        }
        return $liste;
    }

    // ------------------------------------------------------------------ Kategorien

    public static function kategorien(?string $typ = null, bool $nurAktive = true): array {
        $sql = "SELECT * FROM finanz_kategorien WHERE 1=1";
        $p = [];
        if ($nurAktive) $sql .= " AND aktiv = 1";
        if ($typ === 'einnahme' || $typ === 'ausgabe') { $sql .= " AND typ IN (?, 'beide')"; $p[] = $typ; }
        return self::db()->fetchAll($sql . " ORDER BY sortierung, name", $p);
    }

    public static function kategorieNachName(string $name): ?int {
        $r = self::db()->fetchOne("SELECT id FROM finanz_kategorien WHERE LOWER(name) = LOWER(?) ORDER BY aktiv DESC LIMIT 1", [$name]);
        return $r ? (int)$r['id'] : null;
    }

    public static function kategorieSpeichern(array $d, ?int $id = null): int {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') throw new RuntimeException('Bitte einen Namen für die Kategorie eingeben.');
        $typ = in_array($d['typ'] ?? '', ['einnahme', 'ausgabe', 'beide'], true) ? $d['typ'] : 'beide';
        $werte = [$name, $typ, (int)($d['sortierung'] ?? 100), !empty($d['aktiv']) ? 1 : 0];
        $posten = $d['bmv_posten'] ?? '';
        $werte[] = isset(BmvJahresbericht::POSTEN[$posten]) || $posten === 'keine' ? $posten : null;
        if ($id) {
            self::db()->execute("UPDATE finanz_kategorien SET name=?, typ=?, sortierung=?, aktiv=?, bmv_posten=? WHERE id=?", array_merge($werte, [$id]));
            return $id;
        }
        self::db()->execute("INSERT INTO finanz_kategorien (name, typ, sortierung, aktiv, bmv_posten) VALUES (?, ?, ?, ?, ?)", $werte);
        return (int)self::db()->lastInsertId();
    }

    public static function kategorieLoeschen(int $id): void {
        $n = (int)self::db()->fetchOne("SELECT COUNT(*) c FROM finanzen WHERE kategorie_id = ?", [$id])['c'];
        if ($n) throw new RuntimeException("Diese Kategorie hat $n Buchungen. Statt zu löschen bitte auf „inaktiv“ setzen.");
        self::db()->execute("DELETE FROM finanz_kategorien WHERE id = ?", [$id]);
    }

    /** Summen je Kategorie für ein Jahr (ohne Umbuchungen): [id => [name, typ, einnahmen, ausgaben]] */
    public static function kategorieSummen(int $jahr): array {
        $liste = [];
        foreach (self::db()->fetchAll(
            "SELECT COALESCE(f.kategorie_id, 0) kid, COALESCE(k.name, 'Ohne Kategorie') name, k.sortierung, k.typ,
                    SUM(CASE WHEN f.typ='einnahme' THEN f.betrag ELSE 0 END) ein, SUM(CASE WHEN f.typ='ausgabe' THEN f.betrag ELSE 0 END) aus
             FROM finanzen f LEFT JOIN finanz_kategorien k ON k.id = f.kategorie_id
             WHERE f.typ IN ('einnahme','ausgabe') AND YEAR(f.datum) = ?
             GROUP BY kid, name, k.sortierung, k.typ ORDER BY k.sortierung IS NULL, k.sortierung, name", [$jahr]) as $r) {
            $liste[(int)$r['kid']] = ['name' => $r['name'], 'typ' => $r['typ'], 'einnahmen' => (float)$r['ein'], 'ausgaben' => (float)$r['aus']];
        }
        return $liste;
    }

    public static function summen(int $jahr): array {
        $r = self::db()->fetchOne("SELECT COALESCE(SUM(CASE WHEN typ='einnahme' THEN betrag END),0) ein, COALESCE(SUM(CASE WHEN typ='ausgabe' THEN betrag END),0) aus, COUNT(*) n
                                   FROM finanzen WHERE YEAR(datum) = ?", [$jahr]);
        return ['einnahmen' => (float)$r['ein'], 'ausgaben' => (float)$r['aus'], 'ergebnis' => round($r['ein'] - $r['aus'], 2), 'anzahl' => (int)$r['n']];
    }

    // ------------------------------------------------------------------ Buchungen

    /** Buchungen mit Konto- und Kategorienamen. Filter: jahr, konto_id, typ, kategorie_id, formation (Bedingung aus Formation::getFilterCondition) */
    public static function buchungen(array $f = []): array {
        $w = ['1=1'];
        $p = [];
        if (!empty($f['jahr'])) { $w[] = 'YEAR(f.datum) = ?'; $p[] = (int)$f['jahr']; }
        if (!empty($f['konto_id'])) { $w[] = '(f.konto_id = ? OR f.gegenkonto_id = ?)'; $p[] = (int)$f['konto_id']; $p[] = (int)$f['konto_id']; }
        if (!empty($f['typ'])) { $w[] = 'f.typ = ?'; $p[] = $f['typ']; }
        if (!empty($f['kategorie_id'])) { $w[] = 'f.kategorie_id = ?'; $p[] = (int)$f['kategorie_id']; }
        if (!empty($f['formation']['condition'])) { $w[] = $f['formation']['condition']; $p = array_merge($p, $f['formation']['params']); }
        return self::db()->fetchAll(
            "SELECT f.*, k.name AS konto_name, k.art AS konto_art, g.name AS gegenkonto_name, c.name AS kategorie_name, b.benutzername AS erstellt_von_name
             FROM finanzen f
             LEFT JOIN finanz_konten k ON k.id = f.konto_id
             LEFT JOIN finanz_konten g ON g.id = f.gegenkonto_id
             LEFT JOIN finanz_kategorien c ON c.id = f.kategorie_id
             LEFT JOIN benutzer b ON b.id = f.erstellt_von
             WHERE " . implode(' AND ', $w) . "
             ORDER BY f.datum, f.beleg_nummer, f.id", $p);
    }

    public static function buchung(int $id): ?array {
        return self::db()->fetchOne("SELECT * FROM finanzen WHERE id = ?", [$id]) ?: null;
    }

    /** Vorzeichenrichtiger Betrag einer Buchung aus Sicht eines Kontos */
    public static function betragFuerKonto(array $b, int $kontoId): float {
        $betrag = (float)$b['betrag'];
        if ($b['typ'] === 'einnahme') return $betrag;
        if ($b['typ'] === 'ausgabe') return -$betrag;
        return (int)$b['gegenkonto_id'] === $kontoId ? $betrag : -$betrag;
    }

    /** Nächste freie Belegnummer für ein Jahr (Nummernkreis „finanzen“, Standard Y-001) */
    public static function naechsteBelegnummer(int $jahr): string {
        $db = self::db();
        $prefix = $db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'nummernkreis_finanzen_prefix'")['wert'] ?? 'Y-';
        $stellen = (int)($db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'nummernkreis_finanzen_stellen'")['wert'] ?? 3) ?: 3;
        $prefix = str_replace(['Y', 'y'], [(string)$jahr, substr((string)$jahr, 2)], $prefix);
        $max = 0;
        foreach ($db->fetchAll("SELECT beleg_nummer FROM finanzen WHERE beleg_nummer LIKE ?", [$prefix . '%']) as $r) {
            $rest = substr($r['beleg_nummer'], strlen($prefix));
            if (ctype_digit($rest)) $max = max($max, (int)$rest);
        }
        return $prefix . str_pad((string)($max + 1), $stellen, '0', STR_PAD_LEFT);
    }

    /**
     * Buchung anlegen oder ändern. $d: typ, datum, betrag, konto_id, gegenkonto_id, kategorie_id, beschreibung, beleg_nummer, zahlungsart.
     * Prüft gesperrte Jahre. Liefert die ID.
     */
    public static function speichern(array $d, ?int $id = null): int {
        $db = self::db();
        $typ = $d['typ'] ?? '';
        if (!isset(self::TYPEN[$typ])) throw new RuntimeException('Bitte Einnahme, Ausgabe oder Umbuchung wählen.');
        $datum = (string)($d['datum'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) throw new RuntimeException('Bitte ein gültiges Datum eingeben.');
        $betrag = round((float)str_replace(',', '.', (string)($d['betrag'] ?? 0)), 2);
        if ($betrag <= 0) throw new RuntimeException('Bitte einen Betrag größer als 0 eingeben.');
        $konto = self::konto((int)($d['konto_id'] ?? 0));
        if (!$konto) throw new RuntimeException('Bitte ein Konto wählen.');
        $gegen = null;
        if ($typ === 'umbuchung') {
            $gegen = self::konto((int)($d['gegenkonto_id'] ?? 0));
            if (!$gegen || $gegen['id'] == $konto['id']) throw new RuntimeException('Bitte für die Umbuchung zwei verschiedene Konten wählen.');
        }
        $jahr = (int)substr($datum, 0, 4);
        if (self::istGesperrt($jahr)) throw new RuntimeException("Das Jahr $jahr ist abgeschlossen. Buchungen sind nicht mehr möglich.");
        $alt = $id ? self::buchung($id) : null;
        if ($id && !$alt) throw new RuntimeException('Buchung nicht gefunden.');
        if ($alt && self::istGesperrt((int)substr($alt['datum'], 0, 4))) throw new RuntimeException('Diese Buchung gehört zu einem abgeschlossenen Jahr.');
        $kategorie = $typ === 'umbuchung' ? null : (($d['kategorie_id'] ?? '') !== '' ? (int)$d['kategorie_id'] : null);
        $katName = $kategorie ? ($db->fetchOne("SELECT name FROM finanz_kategorien WHERE id = ?", [$kategorie])['name'] ?? null) : null;
        $beleg = trim((string)($d['beleg_nummer'] ?? ''));
        if ($beleg === '') $beleg = self::naechsteBelegnummer($jahr);
        $werte = [$typ, (int)$konto['id'], $gegen ? (int)$gegen['id'] : null, $datum, $betrag, $katName, $kategorie,
                  trim((string)($d['beschreibung'] ?? '')) ?: null, mb_substr($beleg, 0, 50),
                  ($d['zahlungsart'] ?? '') ?: ($konto['art'] === 'kassa' ? 'bar' : 'überweisung'), $konto['formation_id']];
        if ($id) {
            $db->execute("UPDATE finanzen SET typ=?, konto_id=?, gegenkonto_id=?, datum=?, betrag=?, kategorie=?, kategorie_id=?, beschreibung=?, beleg_nummer=?, zahlungsart=?, formation_id=? WHERE id=?",
                array_merge($werte, [$id]));
            return $id;
        }
        $db->execute("INSERT INTO finanzen (typ, konto_id, gegenkonto_id, datum, betrag, kategorie, kategorie_id, beschreibung, beleg_nummer, zahlungsart, formation_id, beitrag_id, fest_id, erstellt_von, erstellt_am)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            array_merge($werte, [$d['beitrag_id'] ?? null, $d['fest_id'] ?? null, Session::getUserId()]));
        return (int)$db->lastInsertId();
    }

    public static function loeschen(int $id): void {
        $b = self::buchung($id);
        if (!$b) return;
        if (self::istGesperrt((int)substr($b['datum'], 0, 4))) throw new RuntimeException('Diese Buchung gehört zu einem abgeschlossenen Jahr und kann nicht gelöscht werden.');
        self::belegEntfernen($b);
        self::db()->execute("DELETE FROM finanzen WHERE id = ?", [$id]);
    }

    // ------------------------------------------------------------------ Belege (Dateien)

    public static function belegOrdner(): string {
        $ordner = UPLOAD_DIR . DIRECTORY_SEPARATOR . 'belege';
        if (!is_dir($ordner)) {
            @mkdir($ordner, 02775, true);
            @chmod($ordner, 02775);
        }
        if (!is_file($ordner . '/.htaccess')) @file_put_contents($ordner . '/.htaccess', "Require all denied\n");
        return $ordner;
    }

    /** Prüft eine hochgeladene Belegdatei. Liefert die Dateiendung oder null, wenn keine Datei gewählt wurde. */
    public static function belegPruefen(array $datei): ?string {
        if (($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (in_array($datei['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($datei['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('Der Beleg ist größer als 10 MB.');
        if ($datei['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($datei['tmp_name'])) throw new RuntimeException('Der Beleg konnte nicht hochgeladen werden.');
        $endung = strtolower(pathinfo((string)$datei['name'], PATHINFO_EXTENSION));
        if (!isset(self::BELEG_ENDUNGEN[$endung])) throw new RuntimeException('Belege bitte als PDF, JPG, PNG oder WEBP hochladen.');
        $typ = function_exists('mime_content_type') ? (string)mime_content_type($datei['tmp_name']) : self::BELEG_ENDUNGEN[$endung];
        if (!in_array($typ, array_values(self::BELEG_ENDUNGEN), true)) throw new RuntimeException('Die Datei ist kein gültiges PDF oder Bild.');
        return $endung;
    }

    /** Hochgeladene Datei ($_FILES-Eintrag) als Beleg einer Buchung speichern */
    public static function belegHochladen(int $id, array $datei): void {
        $endung = self::belegPruefen($datei);
        if ($endung === null) return;
        $b = self::buchung($id);
        $ziel = self::belegOrdner() . DIRECTORY_SEPARATOR . $id . '_' . bin2hex(random_bytes(6)) . '.' . $endung;
        if (!move_uploaded_file($datei['tmp_name'], $ziel)) throw new RuntimeException('Der Beleg konnte nicht gespeichert werden.');
        @chmod($ziel, 0660);
        if ($b) self::belegEntfernen($b);
        self::db()->execute("UPDATE finanzen SET beleg_datei = ?, beleg_name = ? WHERE id = ?", [basename($ziel), mb_substr(basename((string)$datei['name']), 0, 255), $id]);
    }

    public static function belegEntfernen(array $b): void {
        if (!empty($b['beleg_datei'])) {
            $pfad = self::belegOrdner() . DIRECTORY_SEPARATOR . basename($b['beleg_datei']);
            if (is_file($pfad)) @unlink($pfad);
            self::db()->execute("UPDATE finanzen SET beleg_datei = NULL, beleg_name = NULL WHERE id = ?", [$b['id']]);
        }
    }

    public static function belegDatei(array $b): ?array {
        if (empty($b['beleg_datei'])) return null;
        $pfad = self::belegOrdner() . DIRECTORY_SEPARATOR . basename($b['beleg_datei']);
        if (!is_file($pfad)) return null;
        $endung = strtolower(pathinfo($pfad, PATHINFO_EXTENSION));
        return ['pfad' => $pfad, 'typ' => self::BELEG_ENDUNGEN[$endung] ?? 'application/octet-stream', 'name' => $b['beleg_name'] ?: basename($pfad)];
    }

    // ------------------------------------------------------------------ Jahresabschluss

    public static function abschluss(int $jahr): array {
        return self::db()->fetchOne("SELECT * FROM finanz_abschluesse WHERE jahr = ?", [$jahr])
            ?: ['jahr' => $jahr, 'pruefer1' => null, 'pruefer2' => null, 'pruef_datum' => null, 'feststellungen' => null, 'abgeschlossen' => 0, 'abgeschlossen_am' => null, 'abgeschlossen_von' => null];
    }

    public static function istGesperrt(int $jahr): bool {
        static $cache = [];
        if (!array_key_exists($jahr, $cache)) {
            $cache[$jahr] = (bool)(self::db()->fetchOne("SELECT abgeschlossen FROM finanz_abschluesse WHERE jahr = ?", [$jahr])['abgeschlossen'] ?? 0);
        }
        return $cache[$jahr];
    }

    public static function abschlussSpeichern(int $jahr, array $d): void {
        self::db()->execute(
            "INSERT INTO finanz_abschluesse (jahr, pruefer1, pruefer2, pruef_datum, feststellungen) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE pruefer1 = VALUES(pruefer1), pruefer2 = VALUES(pruefer2), pruef_datum = VALUES(pruef_datum), feststellungen = VALUES(feststellungen)",
            [$jahr, trim((string)($d['pruefer1'] ?? '')) ?: null, trim((string)($d['pruefer2'] ?? '')) ?: null, ($d['pruef_datum'] ?? '') ?: null, trim((string)($d['feststellungen'] ?? '')) ?: null]);
    }

    public static function abschliessen(int $jahr, bool $zu): void {
        self::abschlussSpeichern($jahr, self::abschluss($jahr));
        self::db()->execute("UPDATE finanz_abschluesse SET abgeschlossen = ?, abgeschlossen_am = ?, abgeschlossen_von = ? WHERE jahr = ?",
            [$zu ? 1 : 0, $zu ? date('Y-m-d H:i:s') : null, $zu ? Session::getUserId() : null, $jahr]);
    }

    // ------------------------------------------------------------------ Budget

    public static function budget(int $jahr): array {
        $b = [];
        foreach (self::db()->fetchAll("SELECT kategorie_id, betrag FROM finanz_budget WHERE jahr = ?", [$jahr]) as $r) $b[(int)$r['kategorie_id']] = (float)$r['betrag'];
        return $b;
    }

    public static function budgetSpeichern(int $jahr, array $betraege): void {
        $db = self::db();
        foreach ($betraege as $kid => $wert) {
            $wert = trim((string)$wert);
            if ($wert === '') { $db->execute("DELETE FROM finanz_budget WHERE jahr = ? AND kategorie_id = ?", [$jahr, (int)$kid]); continue; }
            $db->execute("INSERT INTO finanz_budget (jahr, kategorie_id, betrag) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE betrag = VALUES(betrag)",
                [$jahr, (int)$kid, round((float)str_replace(',', '.', $wert), 2)]);
        }
    }

    // ------------------------------------------------------------------ Kassasturz

    /** Österreichische Euro-Stückelung für die Zählung */
    public const STUECKELUNG = [500, 200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1, 0.05, 0.02, 0.01];

    public static function kassasturzSpeichern(int $kontoId, array $anzahl, string $gezaehltVon, string $bemerkung): int {
        if (!self::konto($kontoId)) throw new RuntimeException('Bitte ein Konto wählen.');
        $zaehlung = [];
        $ist = 0.0;
        foreach (self::STUECKELUNG as $wert) {
            $n = max(0, (int)($anzahl[(string)$wert] ?? 0));
            if ($n) { $zaehlung[(string)$wert] = $n; $ist += $n * $wert; }
        }
        $soll = self::stand($kontoId, date('Y-m-d'));
        self::db()->execute("INSERT INTO finanz_kassasturz (konto_id, datum, zaehlung, ist_betrag, soll_betrag, bemerkung, gezaehlt_von, erstellt_von) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)",
            [$kontoId, json_encode($zaehlung), round($ist, 2), $soll, trim($bemerkung) ?: null, trim($gezaehltVon) ?: null, Session::getUserId()]);
        return (int)self::db()->lastInsertId();
    }

    // ------------------------------------------------------------------ Verbindungen: Beiträge und Feste

    /** Bezahlten Mitgliedsbeitrag ins Kassabuch buchen bzw. die Buchung wieder entfernen */
    public static function beitragBuchen(int $beitragId, bool $bezahlt): void {
        $db = self::db();
        $vorhanden = $db->fetchOne("SELECT id FROM finanzen WHERE beitrag_id = ?", [$beitragId]);
        if (!$bezahlt) {
            if ($vorhanden) self::loeschen((int)$vorhanden['id']);
            return;
        }
        if ($vorhanden) return;
        $b = $db->fetchOne("SELECT b.*, m.vorname, m.nachname FROM beitraege b JOIN mitglieder m ON m.id = b.mitglied_id WHERE b.id = ?", [$beitragId]);
        $konto = $b ? self::standardKonto(($b['zahlungsart'] ?? '') === 'bar') : null;
        if (!$b || !$konto || (float)$b['betrag'] <= 0) return;
        self::speichern([
            'typ' => 'einnahme', 'datum' => $b['bezahlt_am'] ?: date('Y-m-d'), 'betrag' => $b['betrag'], 'konto_id' => $konto,
            'kategorie_id' => self::kategorieNachName('Mitgliedsbeiträge'), 'zahlungsart' => $b['zahlungsart'] ?? '',
            'beschreibung' => 'Mitgliedsbeitrag ' . $b['jahr'] . ': ' . $b['nachname'] . ' ' . $b['vorname'], 'beitrag_id' => $beitragId,
        ]);
    }

    /**
     * Fest-Abrechnung ins Kassabuch übernehmen: je eine Buchung für die tatsächlichen Einnahmen und Ausgaben
     * (bestellte Einkäufe und offene Honorare zählen nicht, die sind noch nicht geflossen). Wiederholt aufrufbar.
     */
    public static function festUebernehmen(int $festId, int $kontoId, string $datum): array {
        $abr = new FestAbrechnung();
        $einnahmen = 0.0;
        $ausgaben = 0.0;
        foreach ($abr->getByFest($festId) as $p) {
            if ($p['typ'] === 'einnahme') $einnahmen += (float)$p['betrag']; else $ausgaben += (float)$p['betrag'];
        }
        foreach ($abr->getEinkaufAusgaben($festId) as $e) $ausgaben += (float)$e['preis_gesamt'];
        foreach ($abr->getVertragsAusgaben($festId) as $v) $ausgaben += (float)$v['honorar'] - FestVertrag::offenerBetrag($v);
        $fest = self::db()->fetchOne("SELECT name FROM feste WHERE id = ?", [$festId]);
        if (!$fest) throw new RuntimeException('Fest nicht gefunden.');
        $kategorie = self::kategorieNachName('Feste und Veranstaltungen');
        $ergebnis = [];
        foreach (['einnahme' => $einnahmen, 'ausgabe' => $ausgaben] as $typ => $betrag) {
            $vorhanden = self::db()->fetchOne("SELECT id, beleg_nummer FROM finanzen WHERE fest_id = ? AND typ = ?", [$festId, $typ]);
            $betrag = round($betrag, 2);
            if ($betrag <= 0) {
                if ($vorhanden) self::loeschen((int)$vorhanden['id']);
                continue;
            }
            $ergebnis[$typ] = self::speichern([
                'typ' => $typ, 'datum' => $datum, 'betrag' => $betrag, 'konto_id' => $kontoId, 'kategorie_id' => $kategorie,
                'beleg_nummer' => $vorhanden['beleg_nummer'] ?? '', 'fest_id' => $festId,
                'beschreibung' => $fest['name'] . ': ' . ($typ === 'einnahme' ? 'Einnahmen' : 'Ausgaben') . ' laut Fest-Abrechnung',
            ], $vorhanden ? (int)$vorhanden['id'] : null);
        }
        return ['einnahmen' => round($einnahmen, 2), 'ausgaben' => round($ausgaben, 2)];
    }

    // ------------------------------------------------------------------ Vereinskopf für Ausdrucke

    public static function vereinskopf(): array {
        $e = [];
        foreach (self::db()->fetchAll("SELECT schluessel, wert FROM einstellungen WHERE schluessel IN ('verein_name','verein_adresse','verein_plz','verein_ort','verein_zvr_nr','verein_email','verein_telefon')") as $r) {
            $e[$r['schluessel']] = $r['wert'];
        }
        return [
            'name' => $e['verein_name'] ?? 'Musikverein',
            'adresse' => trim(($e['verein_adresse'] ?? '') . ', ' . trim(($e['verein_plz'] ?? '') . ' ' . ($e['verein_ort'] ?? '')), ', '),
            'zvr' => $e['verein_zvr_nr'] ?? '',
            'kontakt' => implode(' · ', array_filter([$e['verein_email'] ?? '', $e['verein_telefon'] ?? ''])),
        ];
    }

    public static function euro(float $b): string {
        return number_format($b, 2, ',', '.') . ' €';
    }
}
