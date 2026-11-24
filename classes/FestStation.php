<?php
// classes/FestStation.php
// Festverwaltung – Stationen/Stände

class FestStation {
    private $db;

    private static bool $tableChecked = false;

    /** Vorschlagsfarben (auch Ersatz, solange einer Station keine eigene Farbe zugewiesen ist) */
    public const PALETTE = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16','#f97316','#6366f1'];

    /** Gültige Farbe (#rrggbb) oder null – ältere Einträge enthalten z. B. noch „primary“ */
    public static function farbeOderNull($farbe): ?string {
        $farbe = strtolower(trim((string)$farbe));
        return preg_match('/^#[0-9a-f]{6}$/', $farbe) ? $farbe : null;
    }

    /**
     * Stationen ohne eigene Farbe bekommen die Farbe, die sie gerade im Dienstplan haben, fest gespeichert.
     * Sonst hinge die Farbe an der Position und würde sich beim Verschieben oder Löschen einer Station ändern.
     * $festId = null: alle Feste. Liefert die Anzahl der festgelegten Farben.
     */
    public function farbenFestlegen(?int $festId = null): int {
        $feste = $festId !== null ? [$festId] : array_map('intval', array_column($this->db->fetchAll(
            "SELECT DISTINCT fest_id FROM fest_stationen WHERE farbe IS NULL OR farbe NOT REGEXP '^#[0-9a-fA-F]{6}$'"), 'fest_id'));
        $anzahl = 0;
        foreach ($feste as $f) {
            $farben = $this->farbenFuerFest($f);
            foreach ($this->getByFest($f) as $s) {
                if (self::farbeOderNull($s['farbe'] ?? null) === null) {
                    $this->db->execute("UPDATE fest_stationen SET farbe = ? WHERE id = ?", [$farben[(int)$s['id']], $s['id']]);
                    $anzahl++;
                }
            }
        }
        return $anzahl;
    }

    /** Farben aller Stationen eines Festes: [station_id => #rrggbb]. Ohne eigene Farbe: Vorschlagsfarbe nach Reihenfolge. */
    public function farbenFuerFest(int $festId): array {
        $farben = [];
        foreach ($this->getByFest($festId) as $i => $s) {
            $farben[(int)$s['id']] = self::farbeOderNull($s['farbe'] ?? null) ?? self::PALETTE[$i % count(self::PALETTE)];
        }
        return $farben;
    }

    public function __construct() {
        $this->db = Database::getInstance();
        if (!self::$tableChecked) {
            self::$tableChecked = true;
            $this->db->execute("CREATE TABLE IF NOT EXISTS fest_station_tage (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                station_id       INT NOT NULL,
                datum            DATE NOT NULL,
                aktiv            TINYINT(1) NOT NULL DEFAULT 1,
                oeffnung_von     TIME NULL,
                oeffnung_bis     TIME NULL,
                benoetigte_helfer INT NOT NULL DEFAULT 1,
                UNIQUE KEY station_datum (station_id, datum),
                FOREIGN KEY (station_id) REFERENCES fest_stationen(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    /** Alle Stationen eines Festes (ohne Tagesfilter, für Verwaltung) */
    public function getByFest(int $festId): array {
        $sql = "SELECT s.*,
                    (SELECT COUNT(*) FROM fest_dienstplaene d WHERE d.station_id = s.id) as schichten_anzahl
                FROM fest_stationen s
                WHERE s.fest_id = ?
                ORDER BY s.sortierung, s.name";
        return $this->db->fetchAll($sql, [$festId]);
    }

    /** Nur aktive Stationen für einen bestimmten Tag, mit tagesspezifischen Zeiten */
    public function getByFestUndDatum(int $festId, string $datum): array {
        $sql = "SELECT s.*,
                    COALESCE(t.oeffnung_von,      s.oeffnung_von)      as oeffnung_von,
                    COALESCE(t.oeffnung_bis,      s.oeffnung_bis)      as oeffnung_bis,
                    COALESCE(t.benoetigte_helfer, s.benoetigte_helfer) as benoetigte_helfer,
                    COALESCE(t.aktiv, 1)                               as tages_aktiv,
                    (SELECT COUNT(*) FROM fest_dienstplaene d
                     WHERE d.station_id = s.id AND d.datum = ?) as schichten_anzahl
                FROM fest_stationen s
                LEFT JOIN fest_station_tage t ON t.station_id = s.id AND t.datum = ?
                WHERE s.fest_id = ?
                  AND COALESCE(t.aktiv, 1) = 1
                ORDER BY s.sortierung, s.name";
        return $this->db->fetchAll($sql, [$datum, $datum, $festId]);
    }

    /** Tages-Konfigurationen aller Tage für eine Station */
    public function getTageKonfigs(int $stationId, array $alleDaten): array {
        $existing = $this->db->fetchAll(
            "SELECT * FROM fest_station_tage WHERE station_id = ? ORDER BY datum",
            [$stationId]
        );
        $byDatum = [];
        foreach ($existing as $e) {
            $e['aktiv'] = (int)$e['aktiv'];
            $byDatum[$e['datum']] = $e;
        }

        $station = $this->getById($stationId);
        $result  = [];
        foreach ($alleDaten as $d) {
            $result[$d] = $byDatum[$d] ?? [
                'datum'             => $d,
                'aktiv'             => 1,
                'oeffnung_von'      => $station['oeffnung_von'],
                'oeffnung_bis'      => $station['oeffnung_bis'],
                'benoetigte_helfer' => $station['benoetigte_helfer'],
            ];
        }
        return $result;
    }

    /** Tages-Konfiguration speichern (atomares Upsert) */
    public function saveTageKonfig(int $stationId, string $datum, array $data): void {
        $this->db->execute(
            "INSERT INTO fest_station_tage (station_id, datum, aktiv, oeffnung_von, oeffnung_bis, benoetigte_helfer)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                aktiv              = VALUES(aktiv),
                oeffnung_von       = VALUES(oeffnung_von),
                oeffnung_bis       = VALUES(oeffnung_bis),
                benoetigte_helfer  = VALUES(benoetigte_helfer)",
            [
                $stationId,
                $datum,
                (int)$data['aktiv'],
                $data['oeffnung_von'] ?: null,
                $data['oeffnung_bis'] ?: null,
                (int)$data['benoetigte_helfer'],
            ]
        );
    }

    /** Eine einzelne gespeicherte Tages-Konfiguration lesen */
    public function getTageKonfigRow(int $stationId, string $datum): ?array {
        $row = $this->db->fetchOne(
            "SELECT * FROM fest_station_tage WHERE station_id = ? AND datum = ?",
            [$stationId, $datum]
        );
        if (!$row) return null;
        $row['aktiv'] = (int)$row['aktiv'];
        return $row;
    }

    public function getById(int $id) {
        return $this->db->fetchOne("SELECT * FROM fest_stationen WHERE id = ?", [$id]);
    }

    public function create(array $data): int {
        $sql = "INSERT INTO fest_stationen (fest_id, name, beschreibung, benoetigte_helfer, oeffnung_von, oeffnung_bis, farbe, sortierung)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            (int)$data['fest_id'],
            $data['name'],
            $data['beschreibung'] ?: null,
            (int)($data['benoetigte_helfer'] ?? 1),
            $data['oeffnung_von'] ?: null,
            $data['oeffnung_bis'] ?: null,
            self::farbeOderNull($data['farbe'] ?? null),
            (int)($data['sortierung'] ?? 100)
        ]);
        return $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void {
        $sql = "UPDATE fest_stationen SET name=?, beschreibung=?, benoetigte_helfer=?, oeffnung_von=?, oeffnung_bis=?, farbe=?, sortierung=?
                WHERE id=?";
        $this->db->execute($sql, [
            $data['name'],
            $data['beschreibung'] ?: null,
            (int)($data['benoetigte_helfer'] ?? 1),
            $data['oeffnung_von'] ?: null,
            $data['oeffnung_bis'] ?: null,
            self::farbeOderNull($data['farbe'] ?? null),
            (int)($data['sortierung'] ?? 100),
            $id
        ]);
    }

    /**
     * Neue Reihenfolge der Stationen eines Festes (Drag & Drop): $ids in gewünschter Reihenfolge.
     * Vergibt 10, 20, 30 … (Platz für spätere Einordnung per Hand). Liefert [station_id => sortierung].
     */
    public function anordnen(int $festId, array $ids): array {
        $vorhanden = array_map('intval', array_column($this->getByFest($festId), 'id'));
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($vorhanden);
        $pruefen = $ids;
        sort($pruefen);
        if (!$vorhanden || $pruefen !== $vorhanden) {
            throw new RuntimeException('Die Stationsliste ist nicht mehr aktuell. Bitte die Seite neu laden.');
        }
        $this->farbenFestlegen($festId); // Farben vor dem Umsortieren festhalten
        $ergebnis = [];
        $this->db->beginTransaction();
        try {
            foreach ($ids as $i => $id) {
                $ergebnis[$id] = ($i + 1) * 10;
                $this->db->execute("UPDATE fest_stationen SET sortierung = ? WHERE id = ? AND fest_id = ?", [$ergebnis[$id], $id, $festId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        return $ergebnis;
    }

    public function delete(int $id): void {
        $this->db->execute("DELETE FROM fest_stationen WHERE id = ?", [$id]);
    }
}
