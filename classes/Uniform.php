<?php
// classes/Uniform.php
// Uniformverwaltung - Mitglieder bekommen Kleidungsstücke zugewiesen

class Uniform {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ==================== KATEGORIEN ====================
    
    /**
     * Alle Kategorien abrufen
     */
    public function getKategorien() {
        $sql = "SELECT * FROM uniform_kategorien WHERE aktiv = 1 ORDER BY sortierung, name";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Kategorie abrufen
     */
    public function getKategorieById($id) {
        $sql = "SELECT * FROM uniform_kategorien WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Kategorie erstellen
     */
    public function createKategorie($data) {
        $sql = "INSERT INTO uniform_kategorien (name, beschreibung, sortierung) VALUES (?, ?, ?)";
        $this->db->execute($sql, [
            $data['name'],
            $data['beschreibung'] ?? null,
            $data['sortierung'] ?? 100
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Kategorie aktualisieren
     */
    public function updateKategorie($id, $data) {
        $sql = "UPDATE uniform_kategorien SET name = ?, beschreibung = ?, sortierung = ? WHERE id = ?";
        return $this->db->execute($sql, [
            $data['name'],
            $data['beschreibung'] ?? null,
            $data['sortierung'] ?? 100,
            $id
        ]);
    }
    
    /**
     * Kategorie löschen
     */
    public function deleteKategorie($id) {
        // Prüfen ob noch Kleidungsstücke in der Kategorie existieren
        $sql = "SELECT COUNT(*) as anzahl FROM uniform_kleidungsstuecke WHERE kategorie_id = ? AND aktiv = 1";
        $result = $this->db->fetchOne($sql, [$id]);
        
        if ($result['anzahl'] > 0) {
            throw new Exception('Kategorie kann nicht gelöscht werden, da noch ' . $result['anzahl'] . ' Kleidungsstücke zugeordnet sind.');
        }
        
        $sql = "UPDATE uniform_kategorien SET aktiv = 0 WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    // ==================== KLEIDUNGSSTÜCKE (Typen) ====================
    
    /**
     * Alle Kleidungsstücke (Typen) abrufen
     */
    public function getKleidungsstuecke($kategorieId = null) {
        $sql = "SELECT k.*, uk.name as kategorie_name 
                FROM uniform_kleidungsstuecke k
                LEFT JOIN uniform_kategorien uk ON k.kategorie_id = uk.id
                WHERE k.aktiv = 1";
        $params = [];
        
        if ($kategorieId) {
            $sql .= " AND k.kategorie_id = ?";
            $params[] = $kategorieId;
        }
        
        $sql .= " ORDER BY uk.sortierung, uk.name, k.sortierung, k.name";
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Kleidungsstück abrufen
     */
    public function getKleidungsstueckById($id) {
        $sql = "SELECT k.*, uk.name as kategorie_name 
                FROM uniform_kleidungsstuecke k
                LEFT JOIN uniform_kategorien uk ON k.kategorie_id = uk.id
                WHERE k.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Kleidungsstück erstellen
     */
    public function createKleidungsstueck($data) {
        $kuerzel = $this->kuerzelPruefen($data['kuerzel'] ?? null);
        $sql = "INSERT INTO uniform_kleidungsstuecke (kategorie_id, name, kuerzel, beschreibung, groessen_verfuegbar, bestandsart, sortierung) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            $data['kategorie_id'],
            $data['name'],
            $kuerzel,
            $data['beschreibung'] ?? null,
            $data['groessen_verfuegbar'] ?? null,
            ($data['bestandsart'] ?? 'menge') === 'einzeln' ? 'einzeln' : 'menge',
            $data['sortierung'] ?? 100
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Kleidungsstück aktualisieren
     */
    public function updateKleidungsstueck($id, $data) {
        if (array_key_exists('kuerzel', $data)) {
            $this->db->execute("UPDATE uniform_kleidungsstuecke SET kuerzel = ? WHERE id = ?", [$this->kuerzelPruefen($data['kuerzel'], (int)$id), $id]);
        }
        $sql = "UPDATE uniform_kleidungsstuecke SET 
                kategorie_id = ?, name = ?, beschreibung = ?, groessen_verfuegbar = ?, sortierung = ?
                WHERE id = ?";
        $this->db->execute($sql, [
            $data['kategorie_id'],
            $data['name'],
            $data['beschreibung'] ?? null,
            $data['groessen_verfuegbar'] ?? null,
            $data['sortierung'] ?? 100,
            $id
        ]);
        if (isset($data['bestandsart'])) {
            $neu = $data['bestandsart'] === 'einzeln' ? 'einzeln' : 'menge';
            $alt = $this->getKleidungsstueckById($id)['bestandsart'] ?? 'menge';
            if ($neu !== $alt) {
                if (!$this->bestandsartWechselbar((int)$id)) {
                    throw new RuntimeException('Die Bestandsart lässt sich nicht mehr ändern, weil schon Bestand erfasst ist.');
                }
                $this->db->execute("UPDATE uniform_kleidungsstuecke SET bestandsart = ? WHERE id = ?", [$neu, $id]);
            }
        }
        return true;
    }

    /** Kürzel prüfen: 1–5 Buchstaben/Ziffern, beginnt mit Buchstabe, unter den aktiven Kleidungsstücken eindeutig. Leer → null */
    public function kuerzelPruefen(?string $kuerzel, int $ausser = 0): ?string {
        $k = mb_strtoupper(trim((string)$kuerzel));
        if ($k === '') return null;
        if (!preg_match('/^[A-ZÄÖÜ][A-ZÄÖÜ0-9]{0,4}$/u', $k)) throw new RuntimeException('Das Kürzel darf 1–5 Buchstaben oder Ziffern haben und muss mit einem Buchstaben beginnen.');
        $doppelt = $this->db->fetchOne("SELECT name FROM uniform_kleidungsstuecke WHERE kuerzel = ? AND aktiv = 1 AND id <> ?", [$k, $ausser]);
        if ($doppelt) throw new RuntimeException("Das Kürzel $k hat schon „{$doppelt['name']}“.");
        return $k;
    }

    /** Bestandsart darf nur gewechselt werden, solange weder Einzelstücke noch Mengenbuchungen erfasst sind */
    public function bestandsartWechselbar(int $id): bool {
        $stuecke = $this->db->fetchOne("SELECT COUNT(*) AS n FROM uniform_stuecke WHERE kleidungsstueck_id = ?", [$id]);
        $buchungen = $this->db->fetchOne("SELECT COUNT(*) AS n FROM uniform_bewegungen WHERE kleidungsstueck_id = ? AND art IN ('zugang','ausscheiden','korrektur')", [$id]);
        return (int)$stuecke['n'] === 0 && (int)$buchungen['n'] === 0;
    }
    
    /**
     * Kleidungsstück löschen
     */
    public function deleteKleidungsstueck($id) {
        $this->db->execute("DELETE FROM uniform_zuweisungen WHERE kleidungsstueck_id = ?", [$id]);
        $sql = "UPDATE uniform_kleidungsstuecke SET aktiv = 0 WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    // ==================== MITGLIEDER-ZUWEISUNGEN ====================
    
    /**
     * Alle aktiven Mitglieder mit Uniform-Status abrufen
     */
    public function getMitgliederMitUniformen() {
        $sql = "SELECT m.id, m.vorname, m.nachname, m.mitgliedsnummer,
                (SELECT COALESCE(SUM(uz.anzahl), 0) FROM uniform_zuweisungen uz WHERE uz.mitglied_id = m.id AND uz.nicht_benoetigt = 0) as anzahl_teile
                FROM mitglieder m
                WHERE m.mitgliedsart_code IN (SELECT code FROM mitgliedsarten WHERE aktiv = 1 AND gast = 0)
                ORDER BY m.nachname, m.vorname";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Zuweisungen eines Mitglieds abrufen
     */
    public function getZuweisungenByMitglied($mitgliedId) {
        $sql = "SELECT uz.*, 
                uk.name as kategorie_name,
                ks.name as kleidungsstueck_name,
                ks.beschreibung as kleidungsstueck_beschreibung,
                ks.bestandsart, ks.groessen_verfuegbar, ks.kuerzel,
                st.nummer AS stueck_nummer
                FROM uniform_zuweisungen uz
                JOIN uniform_kleidungsstuecke ks ON uz.kleidungsstueck_id = ks.id
                LEFT JOIN uniform_kategorien uk ON ks.kategorie_id = uk.id
                LEFT JOIN uniform_stuecke st ON st.id = uz.stueck_id
                WHERE uz.mitglied_id = ?
                ORDER BY uk.sortierung, uk.name, ks.sortierung, ks.name";
        return $this->db->fetchAll($sql, [$mitgliedId]);
    }
    
    /**
     * Zuweisung abrufen
     */
    public function getZuweisungById($id) {
        $sql = "SELECT uz.*, 
                m.vorname, m.nachname,
                uk.name as kategorie_name,
                ks.name as kleidungsstueck_name
                FROM uniform_zuweisungen uz
                JOIN mitglieder m ON uz.mitglied_id = m.id
                JOIN uniform_kleidungsstuecke ks ON uz.kleidungsstueck_id = ks.id
                LEFT JOIN uniform_kategorien uk ON ks.kategorie_id = uk.id
                WHERE uz.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Kleidungsstück einem Mitglied zuweisen.
     * Bei Einzelstücken muss $data['stueck_id'] ein Stück im Lager sein; Größe und Zustand kommen vom Stück.
     */
    public function zuweisen($mitgliedId, $kleidungsstueckId, $data = []) {
        $ks = $this->getKleidungsstueckById($kleidungsstueckId);
        if (!$ks) throw new RuntimeException('Kleidungsstück nicht gefunden.');
        $stueckId = null;
        $groesse = trim((string)($data['groesse'] ?? ''));
        $zustand = $data['zustand'] ?? 'gut';
        $datum = $data['ausgabe_datum'] ?? date('Y-m-d');
        $einzeln = ($ks['bestandsart'] ?? 'menge') === 'einzeln';
        $anzahl = $einzeln ? 1 : max(1, (int)($data['anzahl'] ?? 1));
        if ($anzahl > 99) throw new RuntimeException('Höchstens 99 Stück auf einmal.');

        // Mengen-Kleidungsstück, das das Mitglied schon hat: Anzahl erhöhen
        if (!$einzeln) {
            $vorhanden = $this->db->fetchOne("SELECT * FROM uniform_zuweisungen WHERE mitglied_id = ? AND kleidungsstueck_id = ? AND nicht_benoetigt = 0", [$mitgliedId, $kleidungsstueckId]);
            if ($vorhanden) {
                if (self::groessenSchluessel($vorhanden['groesse']) !== self::groessenSchluessel($groesse)) {
                    throw new RuntimeException('Das Mitglied hat dieses Kleidungsstück schon in Größe ' . ($vorhanden['groesse'] ?: 'o. A.')
                        . '. Weitere Stücke gehen nur in derselben Größe – sonst zuerst zurücknehmen oder die Größe ändern.');
                }
                $this->db->beginTransaction();
                try {
                    $this->db->execute("UPDATE uniform_zuweisungen SET anzahl = anzahl + ? WHERE id = ?", [$anzahl, $vorhanden['id']]);
                    $this->bewegung((int)$kleidungsstueckId, 'ausgabe', $groesse, $anzahl, ['mitglied_id' => $mitgliedId, 'datum' => $datum]);
                    $this->db->commit();
                } catch (\Throwable $e) {
                    $this->db->rollback();
                    throw $e;
                }
                return $vorhanden['id'];
            }
        }

        $this->db->beginTransaction();
        try {
            if ($einzeln && !empty($data['stueck_id'])) {
                $stueck = $this->stueckLaden((int)$data['stueck_id']);
                if (!$stueck || (int)$stueck['kleidungsstueck_id'] !== (int)$kleidungsstueckId) throw new RuntimeException('Stück nicht gefunden.');
                if ($stueck['status'] !== 'lager') throw new RuntimeException('Stück ' . self::nummerAnzeige($ks['kuerzel'] ?? null, $stueck['nummer']) . ' ist nicht im Lager.');
                $stueckId = (int)$stueck['id'];
                $groesse = $stueck['groesse'];
                $zustand = $stueck['zustand'];
                $this->db->execute("UPDATE uniform_stuecke SET status = 'ausgegeben' WHERE id = ?", [$stueckId]);
            }
            // Ein früherer Eintrag „nicht benötigt“ wird durch die Ausgabe ersetzt
            $this->db->execute("DELETE FROM uniform_zuweisungen WHERE mitglied_id = ? AND kleidungsstueck_id = ? AND nicht_benoetigt = 1", [$mitgliedId, $kleidungsstueckId]);
            $this->db->execute(
                "INSERT INTO uniform_zuweisungen (mitglied_id, kleidungsstueck_id, stueck_id, groesse, anzahl, zustand, ausgabe_datum, bemerkungen, nicht_benoetigt)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)",
                [$mitgliedId, $kleidungsstueckId, $stueckId, $groesse !== '' ? $groesse : null, $anzahl, $zustand, $datum, $data['bemerkungen'] ?? null]
            );
            $id = $this->db->lastInsertId();
            $this->bewegung((int)$kleidungsstueckId, 'ausgabe', $groesse, $anzahl, ['stueck_id' => $stueckId, 'mitglied_id' => $mitgliedId, 'datum' => $datum]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        return $id;
    }

    /**
     * Kleidungsstück für ein Mitglied als "nicht benötigt" markieren
     */
    public function nichtBenoetigt($mitgliedId, $kleidungsstueckId) {
        $vorhanden = $this->db->fetchOne(
            "SELECT id FROM uniform_zuweisungen WHERE mitglied_id = ? AND kleidungsstueck_id = ? AND nicht_benoetigt = 0",
            [$mitgliedId, $kleidungsstueckId]
        );
        if ($vorhanden) throw new RuntimeException('Das Mitglied hat dieses Kleidungsstück bereits – bitte zuerst zurücknehmen.');
        $this->db->execute(
            "DELETE FROM uniform_zuweisungen WHERE mitglied_id = ? AND kleidungsstueck_id = ?",
            [$mitgliedId, $kleidungsstueckId]
        );
        $sql = "INSERT INTO uniform_zuweisungen
                (mitglied_id, kleidungsstueck_id, nicht_benoetigt)
                VALUES (?, ?, 1)";
        $this->db->execute($sql, [$mitgliedId, $kleidungsstueckId]);
        return $this->db->lastInsertId();
    }

    /**
     * Zuweisung aktualisieren. Bei Einzelstücken kann ein anderes Stück aus dem Lager gewählt werden.
     */
    public function updateZuweisung($id, $data) {
        $z = $this->db->fetchOne("SELECT uz.*, ks.bestandsart FROM uniform_zuweisungen uz JOIN uniform_kleidungsstuecke ks ON ks.id = uz.kleidungsstueck_id WHERE uz.id = ?", [$id]);
        if (!$z) throw new RuntimeException('Zuweisung nicht gefunden.');
        $zustand = $data['zustand'] ?? 'gut';
        $groesse = $data['groesse'] ?? null;

        $this->db->beginTransaction();
        try {
            if ($z['bestandsart'] === 'einzeln') {
                $neuesStueck = !empty($data['stueck_id']) ? (int)$data['stueck_id'] : (int)$z['stueck_id'];
                if ($neuesStueck && $neuesStueck !== (int)$z['stueck_id']) {
                    $stueck = $this->stueckLaden($neuesStueck);
                    if (!$stueck || (int)$stueck['kleidungsstueck_id'] !== (int)$z['kleidungsstueck_id'] || $stueck['status'] !== 'lager') {
                        throw new RuntimeException('Das gewählte Stück ist nicht im Lager.');
                    }
                    if ($z['stueck_id']) $this->db->execute("UPDATE uniform_stuecke SET status = 'lager' WHERE id = ?", [$z['stueck_id']]);
                    $this->db->execute("UPDATE uniform_stuecke SET status = 'ausgegeben' WHERE id = ?", [$neuesStueck]);
                }
                if ($neuesStueck) {
                    $groesse = $this->stueckLaden($neuesStueck)['groesse'];
                    $this->db->execute("UPDATE uniform_stuecke SET zustand = ? WHERE id = ?", [$zustand, $neuesStueck]);
                }
                $this->db->execute("UPDATE uniform_zuweisungen SET stueck_id = ? WHERE id = ?", [$neuesStueck ?: null, $id]);
            }
            if ($z['bestandsart'] !== 'einzeln' && isset($data['anzahl']) && $data['anzahl'] !== '') {
                $neu = (int)$data['anzahl'];
                if ($neu < 1 || $neu > 99) throw new RuntimeException('Die Anzahl muss zwischen 1 und 99 liegen.');
                $diff = $neu - (int)$z['anzahl'];
                $gr = trim((string)($groesse ?? ''));
                if ($diff > 0) $this->bewegung((int)$z['kleidungsstueck_id'], 'ausgabe', $gr, $diff, ['mitglied_id' => $z['mitglied_id']]);
                if ($diff < 0) $this->bewegung((int)$z['kleidungsstueck_id'], 'rueckgabe', $gr, -$diff, ['mitglied_id' => $z['mitglied_id'], 'bemerkung' => 'Anzahl korrigiert']);
                $this->db->execute("UPDATE uniform_zuweisungen SET anzahl = ? WHERE id = ?", [$neu, $id]);
            }
            $this->db->execute(
                "UPDATE uniform_zuweisungen SET groesse = ?, zustand = ?, bemerkungen = ? WHERE id = ?",
                [$groesse !== '' ? $groesse : null, $zustand, $data['bemerkungen'] ?? null, $id]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        return true;
    }

    /**
     * Rückgabe eines Kleidungsstücks.
     * $ziel: 'lager' (Bestand bleibt, im Lager eins mehr) oder 'ausscheiden' (Bestand sinkt).
     */
    public function zurueckgeben(int $zuweisungId, string $ziel, array $data = []): void {
        $z = $this->db->fetchOne("SELECT * FROM uniform_zuweisungen WHERE id = ? AND nicht_benoetigt = 0", [$zuweisungId]);
        if (!$z) throw new RuntimeException('Zuweisung nicht gefunden.');
        $datum = $this->datum($data['datum'] ?? null);
        $grund = trim((string)($data['grund'] ?? '')) ?: null;
        if ($ziel === 'ausscheiden' && !$grund) throw new RuntimeException('Bitte einen Grund für das Ausscheiden angeben.');
        $zustand = $data['zustand'] ?? $z['zustand'] ?? 'gut';
        $groesse = trim((string)($z['groesse'] ?? ''));
        $hat = max(1, (int)$z['anzahl']);
        $anzahl = $z['stueck_id'] ? 1 : (int)($data['anzahl'] ?? $hat);
        if ($anzahl < 1 || $anzahl > $hat) throw new RuntimeException("Es können 1 bis $hat Stück zurückgenommen werden.");

        $this->db->beginTransaction();
        try {
            if ($anzahl < $hat) {
                $this->db->execute("UPDATE uniform_zuweisungen SET anzahl = anzahl - ? WHERE id = ?", [$anzahl, $zuweisungId]);
            } else {
                $this->db->execute("DELETE FROM uniform_zuweisungen WHERE id = ?", [$zuweisungId]);
            }
            $this->bewegung((int)$z['kleidungsstueck_id'], 'rueckgabe', $groesse, $anzahl, [
                'stueck_id' => $z['stueck_id'], 'mitglied_id' => $z['mitglied_id'], 'datum' => $datum,
                'bemerkung' => trim((string)($data['bemerkung'] ?? '')) ?: null,
            ]);
            if ($z['stueck_id']) {
                $this->db->execute("UPDATE uniform_stuecke SET status = 'lager', zustand = ? WHERE id = ?", [$zustand, $z['stueck_id']]);
            }
            if ($ziel === 'ausscheiden') {
                if ($z['stueck_id']) {
                    $this->stueckAusscheiden((int)$z['stueck_id'], $grund, $datum, false);
                } else {
                    $this->bewegung((int)$z['kleidungsstueck_id'], 'ausscheiden', $groesse, $anzahl, ['grund' => $grund, 'datum' => $datum, 'mitglied_id' => $z['mitglied_id']]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Zuweisung löschen (Fehleingabe oder „nicht benötigt“ zurücksetzen) – ein Einzelstück geht ohne Verlaufseintrag ins Lager zurück
     */
    public function zuweisungEntfernen($id) {
        $z = $this->db->fetchOne("SELECT stueck_id FROM uniform_zuweisungen WHERE id = ?", [$id]);
        if ($z && $z['stueck_id']) {
            $this->db->execute("UPDATE uniform_stuecke SET status = 'lager' WHERE id = ? AND status = 'ausgegeben'", [$z['stueck_id']]);
        }
        $sql = "DELETE FROM uniform_zuweisungen WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }

    /**
     * Kleidungsstücke, die dem Mitglied ausgegeben werden können:
     * noch nicht zugewiesene sowie Mengen-Kleidungsstücke, von denen es schon welche hat (weitere Stücke)
     */
    public function getVerfuegbareKleidungsstuecke($mitgliedId) {
        $sql = "SELECT k.*, uk.name as kategorie_name 
                FROM uniform_kleidungsstuecke k
                LEFT JOIN uniform_kategorien uk ON k.kategorie_id = uk.id
                WHERE k.aktiv = 1
                AND (k.bestandsart <> 'einzeln' OR k.id NOT IN (
                    SELECT kleidungsstueck_id FROM uniform_zuweisungen WHERE mitglied_id = ? AND nicht_benoetigt = 0
                ))
                ORDER BY uk.sortierung, uk.name, k.sortierung, k.name";
        return $this->db->fetchAll($sql, [$mitgliedId]);
    }
    
    // ==================== STATISTIK ====================
    
    /**
     * Statistiken abrufen
     */
    public function getStatistik() {
        $stats = [];
        
        // Anzahl Kategorien
        $sql = "SELECT COUNT(*) as anzahl FROM uniform_kategorien WHERE aktiv = 1";
        $result = $this->db->fetchOne($sql);
        $stats['kategorien'] = $result['anzahl'];
        
        // Anzahl Kleidungsstücke (Typen)
        $sql = "SELECT COUNT(*) as anzahl FROM uniform_kleidungsstuecke WHERE aktiv = 1";
        $result = $this->db->fetchOne($sql);
        $stats['kleidungsstuecke'] = $result['anzahl'];
        
        // Anzahl Zuweisungen
        $sql = "SELECT COALESCE(SUM(anzahl), 0) as anzahl FROM uniform_zuweisungen WHERE nicht_benoetigt = 0";
        $result = $this->db->fetchOne($sql);
        $stats['zuweisungen'] = $result['anzahl'];
        
        // Mitglieder mit kompletter Uniform (alle Pflicht-Teile)
        $sql = "SELECT COUNT(DISTINCT mitglied_id) as anzahl FROM uniform_zuweisungen WHERE nicht_benoetigt = 0";
        $result = $this->db->fetchOne($sql);
        $stats['mitglieder_mit_uniform'] = $result['anzahl'];
        
        // Aktive Mitglieder gesamt
        $sql = "SELECT COUNT(*) as anzahl FROM mitglieder WHERE mitgliedsart_code IN (SELECT code FROM mitgliedsarten WHERE aktiv = 1 AND gast = 0)";
        $result = $this->db->fetchOne($sql);
        $stats['mitglieder_aktiv'] = $result['anzahl'];
        
        return $stats;
    }

    // ==================== BESTAND ====================

    public const GRUENDE_AUSSCHEIDEN = ['verschlissen', 'beschädigt', 'verloren', 'nicht zurückgegeben', 'verkauft', 'verschenkt', 'Fehleingabe', 'sonstiges'];
    public const GRUENDE_ZUGANG = ['Neukauf', 'Spende', 'Erstbestand', 'gefunden', 'sonstiges'];

    /** Größe zum Vergleichen (Groß/Klein und Leerzeichen egal) */
    public static function groessenSchluessel(?string $g): string {
        return mb_strtolower(trim((string)$g));
    }

    private function datum(?string $d): string {
        return ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) ? $d : date('Y-m-d');
    }

    /** Verlaufseintrag schreiben */
    private function bewegung(int $ksId, string $art, string $groesse, int $anzahl, array $extra = []): void {
        $this->db->execute(
            "INSERT INTO uniform_bewegungen (kleidungsstueck_id, groesse, stueck_id, art, anzahl, mitglied_id, grund, bemerkung, datum, erstellt_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$ksId, trim($groesse), $extra['stueck_id'] ?? null, $art, $anzahl, $extra['mitglied_id'] ?? null,
             $extra['grund'] ?? null, $extra['bemerkung'] ?? null, $this->datum($extra['datum'] ?? null), Session::getUserId()]
        );
    }

    /**
     * Bestand eines Kleidungsstücks je Größe:
     * [schluessel => ['groesse', 'bestand', 'ausgegeben', 'lager', 'ausgeschieden']], Reihenfolge wie in „Verfügbare Größen“.
     */
    public function bestandNachGroesse(int $ksId): array {
        $ks = $this->getKleidungsstueckById($ksId);
        if (!$ks) return [];
        $zeilen = [];
        $add = function (string $g) use (&$zeilen) {
            $k = self::groessenSchluessel($g);
            if (!isset($zeilen[$k])) $zeilen[$k] = ['groesse' => trim($g), 'bestand' => 0, 'ausgegeben' => 0, 'lager' => 0, 'ausgeschieden' => 0];
            return $k;
        };
        foreach (array_filter(array_map('trim', explode(',', (string)$ks['groessen_verfuegbar'])), 'strlen') as $g) $add($g);

        if ($ks['bestandsart'] === 'einzeln') {
            foreach ($this->db->fetchAll("SELECT groesse, status, COUNT(*) AS n FROM uniform_stuecke WHERE kleidungsstueck_id = ? GROUP BY groesse, status", [$ksId]) as $r) {
                $k = $add((string)$r['groesse']);
                $n = (int)$r['n'];
                if ($r['status'] === 'ausgeschieden') { $zeilen[$k]['ausgeschieden'] += $n; continue; }
                $zeilen[$k]['bestand'] += $n;
                $zeilen[$k][$r['status'] === 'ausgegeben' ? 'ausgegeben' : 'lager'] += $n;
            }
            // Zuweisungen ohne Nummer (z. B. aus der Zeit vor der Bestandsführung) zählen als ausgegeben
            foreach ($this->db->fetchAll("SELECT COALESCE(groesse, '') AS groesse, COUNT(*) AS n FROM uniform_zuweisungen
                                          WHERE kleidungsstueck_id = ? AND nicht_benoetigt = 0 AND stueck_id IS NULL GROUP BY COALESCE(groesse, '')", [$ksId]) as $r) {
                $k = $add((string)$r['groesse']);
                $zeilen[$k]['ausgegeben'] += (int)$r['n'];
                $zeilen[$k]['ohne_nummer'] = ($zeilen[$k]['ohne_nummer'] ?? 0) + (int)$r['n'];
            }
            foreach ($zeilen as &$z) $z['bestand'] = $z['lager'] + $z['ausgegeben'];
            unset($z);
        } else {
            foreach ($this->db->fetchAll("SELECT groesse, art, SUM(anzahl) AS n FROM uniform_bewegungen
                                          WHERE kleidungsstueck_id = ? AND art IN ('zugang','ausscheiden','korrektur') GROUP BY groesse, art", [$ksId]) as $r) {
                $k = $add((string)$r['groesse']);
                $n = (int)$r['n'];
                if ($r['art'] === 'ausscheiden') { $zeilen[$k]['bestand'] -= $n; $zeilen[$k]['ausgeschieden'] += $n; }
                else $zeilen[$k]['bestand'] += $n;
            }
            foreach ($this->db->fetchAll("SELECT COALESCE(groesse, '') AS groesse, SUM(anzahl) AS n FROM uniform_zuweisungen
                                          WHERE kleidungsstueck_id = ? AND nicht_benoetigt = 0 GROUP BY COALESCE(groesse, '')", [$ksId]) as $r) {
                $k = $add((string)$r['groesse']);
                $zeilen[$k]['ausgegeben'] += (int)$r['n'];
            }
            foreach ($zeilen as &$z) $z['lager'] = $z['bestand'] - $z['ausgegeben'];
            unset($z);
        }
        // Größen ohne jede Bewegung, die nicht in der Größenliste stehen, nicht anzeigen
        return $zeilen;
    }

    /** Summen über alle Größen */
    public static function bestandSumme(array $zeilen): array {
        $s = ['bestand' => 0, 'ausgegeben' => 0, 'lager' => 0, 'ausgeschieden' => 0];
        foreach ($zeilen as $z) foreach ($s as $k => $_) $s[$k] += $z[$k];
        return $s;
    }

    /** Übersicht aller aktiven Kleidungsstücke mit Bestand */
    public function bestandUebersicht(): array {
        $liste = [];
        foreach ($this->getKleidungsstuecke() as $ks) {
            $ks['groessen'] = $this->bestandNachGroesse((int)$ks['id']);
            $ks['summe'] = self::bestandSumme($ks['groessen']);
            $ks['erfasst'] = $this->bestandErfasst($ks);
            $liste[] = $ks;
        }
        return $liste;
    }

    /** Wurde für das Kleidungsstück schon Bestand erfasst (Stücke angelegt bzw. Zugang/Inventur gebucht)? */
    public function bestandErfasst(array $ks): bool {
        return $ks['bestandsart'] === 'einzeln'
            ? (bool)$this->db->fetchOne("SELECT 1 FROM uniform_stuecke WHERE kleidungsstueck_id = ? LIMIT 1", [$ks['id']])
            : (bool)$this->db->fetchOne("SELECT 1 FROM uniform_bewegungen WHERE kleidungsstueck_id = ? AND art IN ('zugang','korrektur','ausscheiden') LIMIT 1", [$ks['id']]);
    }

    /** Lagerstand je Größe (Schlüssel → Anzahl) für die Größenauswahl bei der Ausgabe */
    public function lagerJeGroesse(int $ksId): array {
        $r = [];
        foreach ($this->bestandNachGroesse($ksId) as $k => $z) $r[$k] = $z['lager'];
        return $r;
    }

    private function mengenArtikel(int $ksId): array {
        $ks = $this->getKleidungsstueckById($ksId);
        if (!$ks || !$ks['aktiv']) throw new RuntimeException('Kleidungsstück nicht gefunden.');
        if ($ks['bestandsart'] === 'einzeln') throw new RuntimeException('Dieses Kleidungsstück wird als Einzelstücke geführt.');
        return $ks;
    }

    /** Mengen-Zugang (Neukauf, Spende …) */
    public function zugang(int $ksId, string $groesse, int $anzahl, array $data = []): void {
        $this->mengenArtikel($ksId);
        if ($anzahl < 1) throw new RuntimeException('Bitte eine Anzahl größer 0 angeben.');
        $this->bewegung($ksId, 'zugang', $groesse, $anzahl, ['grund' => trim((string)($data['grund'] ?? '')) ?: null,
            'bemerkung' => trim((string)($data['bemerkung'] ?? '')) ?: null, 'datum' => $data['datum'] ?? null]);
    }

    /** Mengen-Abgang aus dem Lager */
    public function ausscheiden(int $ksId, string $groesse, int $anzahl, array $data = []): void {
        $this->mengenArtikel($ksId);
        if ($anzahl < 1) throw new RuntimeException('Bitte eine Anzahl größer 0 angeben.');
        $grund = trim((string)($data['grund'] ?? ''));
        if ($grund === '') throw new RuntimeException('Bitte einen Grund für das Ausscheiden angeben.');
        $lager = $this->lagerJeGroesse($ksId)[self::groessenSchluessel($groesse)] ?? 0;
        if ($anzahl > $lager) throw new RuntimeException("Im Lager sind in dieser Größe nur $lager Stück. Ausgegebene Stücke bitte beim Mitglied zurücknehmen und dabei ausscheiden.");
        $this->bewegung($ksId, 'ausscheiden', $groesse, $anzahl, ['grund' => $grund,
            'bemerkung' => trim((string)($data['bemerkung'] ?? '')) ?: null, 'datum' => $data['datum'] ?? null]);
    }

    /** Inventur: gezählten Lagerstand je Größe übernehmen (bucht die Differenz als Korrektur) */
    public function inventur(int $ksId, array $gezaehlt, ?string $datum = null): int {
        $this->mengenArtikel($ksId);
        $bestand = $this->bestandNachGroesse($ksId);
        $n = 0;
        foreach ($gezaehlt as $groesse => $anzahl) {
            if ($anzahl === '' || $anzahl === null) continue;
            if (!preg_match('/^\d+$/', (string)$anzahl)) throw new RuntimeException('Bitte nur ganze Zahlen ab 0 eintragen.');
            $k = self::groessenSchluessel((string)$groesse);
            $diff = (int)$anzahl - ($bestand[$k]['lager'] ?? 0);
            if ($diff === 0) continue;
            $this->bewegung($ksId, 'korrektur', $bestand[$k]['groesse'] ?? (string)$groesse, $diff, ['grund' => 'Inventur', 'datum' => $datum]);
            $n++;
        }
        return $n;
    }

    /** Verlauf eines Kleidungsstücks (neueste zuerst) */
    public function verlauf(int $ksId, int $limit = 200): array {
        return $this->db->fetchAll(
            "SELECT b.*, m.vorname, m.nachname, st.nummer, be.benutzername
             FROM uniform_bewegungen b
             LEFT JOIN mitglieder m ON m.id = b.mitglied_id
             LEFT JOIN uniform_stuecke st ON st.id = b.stueck_id
             LEFT JOIN benutzer be ON be.id = b.erstellt_von
             WHERE b.kleidungsstueck_id = ?
             ORDER BY b.datum DESC, b.id DESC
             LIMIT " . (int)$limit,
            [$ksId]
        );
    }

    // ---------- Einzelstücke ----------

    public function stueckLaden(int $id): ?array {
        return $this->db->fetchOne("SELECT * FROM uniform_stuecke WHERE id = ?", [$id]);
    }

    /** Alle Stücke eines Kleidungsstücks, mit aktuellem Träger */
    public function stuecke(int $ksId, bool $mitAusgeschiedenen = true): array {
        return $this->db->fetchAll(
            "SELECT st.*, uz.mitglied_id, m.vorname, m.nachname
             FROM uniform_stuecke st
             LEFT JOIN uniform_zuweisungen uz ON uz.stueck_id = st.id AND uz.nicht_benoetigt = 0
             LEFT JOIN mitglieder m ON m.id = uz.mitglied_id
             WHERE st.kleidungsstueck_id = ?" . ($mitAusgeschiedenen ? '' : " AND st.status <> 'ausgeschieden'") . "
             ORDER BY st.status = 'ausgeschieden', st.nummer",
            [$ksId]
        );
    }

    /** Stücke im Lager (für die Ausgabe) */
    public function lagerStuecke(int $ksId): array {
        return $this->db->fetchAll(
            "SELECT id, nummer, groesse, zustand FROM uniform_stuecke WHERE kleidungsstueck_id = ? AND status = 'lager' ORDER BY nummer",
            [$ksId]
        );
    }

    // ---------- Stücknummern: 001–999, danach A00–Z99 (immer 3 Zeichen), angezeigt mit Kürzel davor ----------

    public const NUMMER_MAX = 3599;   // 999 + 26 × 100

    /** Laufende Zahl → Nummer: 1 → „001“, 999 → „999“, 1000 → „A00“, 1099 → „A99“, 1100 → „B00“ … */
    public static function nummerAusWert(int $n): string {
        if ($n < 1 || $n > self::NUMMER_MAX) throw new RuntimeException('Die Nummern sind ausgeschöpft (höchstens Z99).');
        if ($n <= 999) return str_pad((string)$n, 3, '0', STR_PAD_LEFT);
        $r = $n - 1000;
        return chr(65 + intdiv($r, 100)) . str_pad((string)($r % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Nummer → laufende Zahl (null, wenn kein gültiges Format) */
    public static function nummerWert(string $nr): ?int {
        if (preg_match('/^\d{3}$/', $nr)) return (int)$nr ?: null;
        if (preg_match('/^([A-Z])(\d{2})$/', $nr, $m)) return 1000 + (ord($m[1]) - 65) * 100 + (int)$m[2];
        return null;
    }

    /** Anzeige mit Kürzel: („L“, „007“) → „L007“ */
    public static function nummerAnzeige(?string $kuerzel, ?string $nummer): string {
        return ($nummer === null || $nummer === '') ? '' : ($kuerzel ?? '') . $nummer;
    }

    /** Eingabe normalisieren: „7“, „L7“, „l007“ → „007“; „a5“ → „A05“. Ungültig → Exception */
    public static function nummerNormalisieren(string $eingabe, ?string $kuerzel): string {
        $t = mb_strtoupper(trim($eingabe));
        if ($kuerzel && str_starts_with($t, $kuerzel) && mb_strlen($t) > mb_strlen($kuerzel)) $t = mb_substr($t, mb_strlen($kuerzel));
        if (preg_match('/^\d{1,3}$/', $t) && (int)$t >= 1) return str_pad((string)(int)$t, 3, '0', STR_PAD_LEFT);
        if (preg_match('/^([A-Z])(\d{1,2})$/', $t, $m)) return $m[1] . str_pad($m[2], 2, '0', STR_PAD_LEFT);
        throw new RuntimeException("Ungültige Nummer: $eingabe – erlaubt sind 1–999 und danach A00–Z99.");
    }

    /** Die nächsten $anzahl freien Nummern nach der höchsten bisher vergebenen (ausgeschiedene zählen mit, Nummern werden nie wiederverwendet) */
    public function naechsteNummern(int $ksId, int $anzahl = 1): array {
        $max = 0;
        foreach ($this->db->fetchAll("SELECT nummer FROM uniform_stuecke WHERE kleidungsstueck_id = ?", [$ksId]) as $r) {
            $max = max($max, self::nummerWert($r['nummer']) ?? 0);
        }
        $liste = [];
        for ($i = 1; $i <= $anzahl; $i++) $liste[] = self::nummerAusWert($max + $i);
        return $liste;
    }

    /**
     * Einzelstücke anlegen – entweder $anzahl Stücke mit den nächsten freien Nummern
     * oder bestimmte Nummern ($nummern: „7“, „L7“, Bereich „1-20“, Liste „3, 7, 12“).
     * Gibt die angelegten Nummern zurück.
     */
    public function stueckeAnlegen(int $ksId, string $nummern, string $groesse, array $data = [], int $anzahl = 0): array {
        $ks = $this->getKleidungsstueckById($ksId);
        if (!$ks || $ks['bestandsart'] !== 'einzeln') throw new RuntimeException('Dieses Kleidungsstück wird nicht als Einzelstücke geführt.');
        if (trim($nummern) !== '') {
            $liste = self::nummernListe($nummern, $ks['kuerzel'] ?? null);
        } else {
            if ($anzahl < 1 || $anzahl > 500) throw new RuntimeException('Bitte eine Anzahl von 1 bis 500 angeben.');
            $liste = $this->naechsteNummern($ksId, $anzahl);
        }
        if (!$liste) throw new RuntimeException('Bitte mindestens eine Nummer angeben.');
        $vorhanden = array_column($this->db->fetchAll("SELECT nummer FROM uniform_stuecke WHERE kleidungsstueck_id = ?", [$ksId]), 'nummer');
        $doppelt = array_values(array_intersect($liste, $vorhanden));
        if ($doppelt) {
            $anz = array_map(fn($n) => self::nummerAnzeige($ks['kuerzel'] ?? null, $n), array_slice($doppelt, 0, 10));
            throw new RuntimeException('Diese Nummern gibt es schon: ' . implode(', ', $anz) . (count($doppelt) > 10 ? ' …' : ''));
        }
        $zustand = in_array($data['zustand'] ?? '', ['sehr gut', 'gut', 'befriedigend', 'schlecht'], true) ? $data['zustand'] : 'gut';
        $this->db->beginTransaction();
        try {
            foreach ($liste as $nr) {
                $this->db->execute("INSERT INTO uniform_stuecke (kleidungsstueck_id, nummer, groesse, zustand, bemerkungen) VALUES (?, ?, ?, ?, ?)",
                    [$ksId, $nr, trim($groesse), $zustand, trim((string)($data['bemerkungen'] ?? '')) ?: null]);
                $this->bewegung($ksId, 'zugang', $groesse, 1, ['stueck_id' => (int)$this->db->lastInsertId(),
                    'grund' => trim((string)($data['grund'] ?? '')) ?: null, 'datum' => $data['datum'] ?? null]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        return $liste;
    }

    /** „1-5, 8, L12, A3“ → ['001','002','003','004','005','008','012','A03'] (höchstens 500) */
    public static function nummernListe(string $eingabe, ?string $kuerzel = null): array {
        $liste = [];
        foreach (preg_split('/[,;\s]+/', trim($eingabe), -1, PREG_SPLIT_NO_EMPTY) as $teil) {
            if (str_contains($teil, '-')) {
                [$a, $b] = array_pad(explode('-', $teil, 2), 2, '');
                $von = self::nummerWert(self::nummerNormalisieren($a, $kuerzel));
                $bis = self::nummerWert(self::nummerNormalisieren($b, $kuerzel));
                if ($bis < $von || $bis - $von >= 500) throw new RuntimeException("Ungültiger Bereich: $teil");
                for ($i = $von; $i <= $bis; $i++) $liste[] = self::nummerAusWert($i);
            } else {
                $liste[] = self::nummerNormalisieren($teil, $kuerzel);
            }
        }
        $liste = array_values(array_unique($liste));
        if (count($liste) > 500) throw new RuntimeException('Höchstens 500 Stücke auf einmal.');
        return $liste;
    }

    /** Nummer, Größe, Zustand oder Bemerkung eines Stücks ändern */
    public function stueckAktualisieren(int $id, array $data): void {
        $st = $this->stueckLaden($id);
        if (!$st) throw new RuntimeException('Stück nicht gefunden.');
        $kuerzel = $this->getKleidungsstueckById($st['kleidungsstueck_id'])['kuerzel'] ?? null;
        $eingabe = trim((string)($data['nummer'] ?? ''));
        $nummer = ($eingabe === '' || $eingabe === $st['nummer'] || $eingabe === self::nummerAnzeige($kuerzel, $st['nummer']))
            ? $st['nummer'] : self::nummerNormalisieren($eingabe, $kuerzel);
        $doppelt = $this->db->fetchOne("SELECT id FROM uniform_stuecke WHERE kleidungsstueck_id = ? AND nummer = ? AND id <> ?", [$st['kleidungsstueck_id'], $nummer, $id]);
        if ($doppelt) throw new RuntimeException('Die Nummer ' . self::nummerAnzeige($kuerzel, $nummer) . ' gibt es schon.');
        $zustand = in_array($data['zustand'] ?? '', ['sehr gut', 'gut', 'befriedigend', 'schlecht'], true) ? $data['zustand'] : $st['zustand'];
        $groesse = trim((string)($data['groesse'] ?? $st['groesse']));
        $this->db->execute("UPDATE uniform_stuecke SET nummer = ?, groesse = ?, zustand = ?, bemerkungen = ? WHERE id = ?",
            [$nummer, $groesse, $zustand, trim((string)($data['bemerkungen'] ?? '')) ?: null, $id]);
        // Größe und Zustand beim Träger mitführen
        $this->db->execute("UPDATE uniform_zuweisungen SET groesse = ?, zustand = ? WHERE stueck_id = ? AND nicht_benoetigt = 0", [$groesse !== '' ? $groesse : null, $zustand, $id]);
    }

    /** Einzelstück ausscheiden (nur aus dem Lager; ausgegebene Stücke werden beim Mitglied zurückgenommen) */
    public function stueckAusscheiden(int $id, ?string $grund, ?string $datum = null, bool $eigeneTransaktion = true): void {
        $st = $this->stueckLaden($id);
        if (!$st) throw new RuntimeException('Stück nicht gefunden.');
        if ($st['status'] === 'ausgeschieden') throw new RuntimeException('Das Stück ist bereits ausgeschieden.');
        if ($st['status'] !== 'lager') throw new RuntimeException('Das Stück ist ausgegeben – bitte beim Mitglied zurücknehmen und dabei ausscheiden.');
        $grund = trim((string)$grund);
        if ($grund === '') throw new RuntimeException('Bitte einen Grund für das Ausscheiden angeben.');
        $datum = $this->datum($datum);
        if ($eigeneTransaktion) $this->db->beginTransaction();
        try {
            $this->db->execute("UPDATE uniform_stuecke SET status = 'ausgeschieden', ausgeschieden_am = ?, ausgeschieden_grund = ? WHERE id = ?", [$datum, $grund, $id]);
            $this->bewegung((int)$st['kleidungsstueck_id'], 'ausscheiden', $st['groesse'], 1, ['stueck_id' => $id, 'grund' => $grund, 'datum' => $datum]);
            if ($eigeneTransaktion) $this->db->commit();
        } catch (\Throwable $e) {
            if ($eigeneTransaktion) $this->db->rollback();
            throw $e;
        }
    }

    /** Ausgeschiedenes Stück wieder in den Bestand nehmen (z. B. wiedergefunden) */
    public function stueckReaktivieren(int $id): void {
        $st = $this->stueckLaden($id);
        if (!$st || $st['status'] !== 'ausgeschieden') throw new RuntimeException('Das Stück ist nicht ausgeschieden.');
        $this->db->execute("UPDATE uniform_stuecke SET status = 'lager', ausgeschieden_am = NULL, ausgeschieden_grund = NULL WHERE id = ?", [$id]);
        $this->bewegung((int)$st['kleidungsstueck_id'], 'zugang', $st['groesse'], 1, ['stueck_id' => $id, 'grund' => 'wieder aufgenommen']);
    }

    /** Irrtümlich angelegtes Stück löschen – nur wenn es nie ausgegeben wurde */
    public function stueckLoeschen(int $id): void {
        $st = $this->stueckLaden($id);
        if (!$st) throw new RuntimeException('Stück nicht gefunden.');
        $ausgegeben = $this->db->fetchOne("SELECT 1 FROM uniform_bewegungen WHERE stueck_id = ? AND art = 'ausgabe' LIMIT 1", [$id]);
        if ($ausgegeben || $st['status'] === 'ausgegeben') throw new RuntimeException('Das Stück war schon ausgegeben und kann nur ausgeschieden werden.');
        $this->db->execute("DELETE FROM uniform_bewegungen WHERE stueck_id = ?", [$id]);
        $this->db->execute("DELETE FROM uniform_stuecke WHERE id = ?", [$id]);
    }
}
