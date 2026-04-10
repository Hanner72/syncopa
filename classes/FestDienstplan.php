<?php
// classes/FestDienstplan.php
// Festverwaltung – Dienstpläne/Schichten

class FestDienstplan {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Alle Schichten eines Festes, optional nach Datum gefiltert
     * Gibt auch Station- und Mitarbeitername zurück (für Grid-Ansicht)
     */
    public function getByFest(int $festId, ?string $datum = null): array {
        $sql = "SELECT dp.*,
                    s.name as station_name,
                    s.sortierung as station_sortierung,
                    COALESCE(CONCAT(m.vorname, ' ', m.nachname), CONCAT(fm.vorname, ' ', fm.nachname)) as mitarbeiter_name,
                    fm.funktion as mitarbeiter_funktion
                FROM fest_dienstplaene dp
                JOIN fest_stationen s ON dp.station_id = s.id
                JOIN fest_mitarbeiter fm ON dp.mitarbeiter_id = fm.id
                LEFT JOIN mitglieder m ON fm.mitglied_id = m.id
                WHERE dp.fest_id = ?";
        $params = [$festId];

        if ($datum !== null) {
            $sql .= " AND dp.datum = ?";
            $params[] = $datum;
        }

        $sql .= " ORDER BY dp.datum, s.sortierung, s.name, dp.zeit_von";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Alle Schichten gruppiert nach Datum und Station (für Grid-Rendering)
     */
    public function getGridData(int $festId): array {
        $rows = $this->getByFest($festId);
        $grid = [];
        foreach ($rows as $row) {
            $grid[$row['datum']][$row['station_id']][] = $row;
        }
        return $grid;
    }

    /**
     * Alle unterschiedlichen Datum-Werte in einem Fest
     */
    public function getDaten(int $festId): array {
        $sql = "SELECT DISTINCT datum FROM fest_dienstplaene WHERE fest_id = ? ORDER BY datum";
        return array_column($this->db->fetchAll($sql, [$festId]), 'datum');
    }

    public function getById(int $id) {
        $sql = "SELECT dp.*,
                    s.name as station_name,
                    COALESCE(CONCAT(m.vorname, ' ', m.nachname), CONCAT(fm.vorname, ' ', fm.nachname)) as mitarbeiter_name
                FROM fest_dienstplaene dp
                JOIN fest_stationen s ON dp.station_id = s.id
                JOIN fest_mitarbeiter fm ON dp.mitarbeiter_id = fm.id
                LEFT JOIN mitglieder m ON fm.mitglied_id = m.id
                WHERE dp.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function create(array $data): int {
        $sql = "INSERT INTO fest_dienstplaene (fest_id, station_id, mitarbeiter_id, datum, zeit_von, zeit_bis, notizen)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            (int)$data['fest_id'],
            (int)$data['station_id'],
            (int)$data['mitarbeiter_id'],
            $data['datum'],
            $data['zeit_von'],
            $data['zeit_bis'],
            $data['notizen'] ?: null
        ]);
        return $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE fest_dienstplaene SET station_id=?, mitarbeiter_id=?, datum=?, zeit_von=?, zeit_bis=?, notizen=?
                WHERE id=?";
        return $this->db->execute($sql, [
            (int)$data['station_id'],
            (int)$data['mitarbeiter_id'],
            $data['datum'],
            $data['zeit_von'],
            $data['zeit_bis'],
            $data['notizen'] ?: null,
            $id
        ]);
    }

    public function delete(int $id): bool {
        return $this->db->execute("DELETE FROM fest_dienstplaene WHERE id = ?", [$id]);
    }
}
