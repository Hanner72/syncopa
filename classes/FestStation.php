<?php
// classes/FestStation.php
// Festverwaltung – Stationen/Stände

class FestStation {
    private $db;

    private static bool $tableChecked = false;

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
        $sql = "INSERT INTO fest_stationen (fest_id, name, beschreibung, benoetigte_helfer, oeffnung_von, oeffnung_bis, sortierung)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            (int)$data['fest_id'],
            $data['name'],
            $data['beschreibung'] ?: null,
            (int)($data['benoetigte_helfer'] ?? 1),
            $data['oeffnung_von'] ?: null,
            $data['oeffnung_bis'] ?: null,
            (int)($data['sortierung'] ?? 100)
        ]);
        return $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void {
        $sql = "UPDATE fest_stationen SET name=?, beschreibung=?, benoetigte_helfer=?, oeffnung_von=?, oeffnung_bis=?, sortierung=?
                WHERE id=?";
        $this->db->execute($sql, [
            $data['name'],
            $data['beschreibung'] ?: null,
            (int)($data['benoetigte_helfer'] ?? 1),
            $data['oeffnung_von'] ?: null,
            $data['oeffnung_bis'] ?: null,
            (int)($data['sortierung'] ?? 100),
            $id
        ]);
    }

    public function delete(int $id): void {
        $this->db->execute("DELETE FROM fest_stationen WHERE id = ?", [$id]);
    }
}
