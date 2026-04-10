<?php
// classes/FestStation.php
// Festverwaltung – Stationen/Stände

class FestStation {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByFest(int $festId): array {
        $sql = "SELECT s.*,
                    (SELECT COUNT(*) FROM fest_dienstplaene d WHERE d.station_id = s.id) as schichten_anzahl
                FROM fest_stationen s
                WHERE s.fest_id = ?
                ORDER BY s.sortierung, s.name";
        return $this->db->fetchAll($sql, [$festId]);
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
