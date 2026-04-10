<?php
// classes/FestMitarbeiter.php
// Festverwaltung – Mitarbeiter/Helfer pro Fest

class FestMitarbeiter {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByFest(int $festId): array {
        $sql = "SELECT fm.*,
                    COALESCE(CONCAT(m.vorname, ' ', m.nachname), CONCAT(fm.vorname, ' ', fm.nachname)) as vollname,
                    m.mitgliedsnummer
                FROM fest_mitarbeiter fm
                LEFT JOIN mitglieder m ON fm.mitglied_id = m.id
                WHERE fm.fest_id = ?
                ORDER BY vollname";
        return $this->db->fetchAll($sql, [$festId]);
    }

    public function getById(int $id) {
        $sql = "SELECT fm.*,
                    COALESCE(CONCAT(m.vorname, ' ', m.nachname), CONCAT(fm.vorname, ' ', fm.nachname)) as vollname,
                    m.mitgliedsnummer
                FROM fest_mitarbeiter fm
                LEFT JOIN mitglieder m ON fm.mitglied_id = m.id
                WHERE fm.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function create(array $data): int {
        $sql = "INSERT INTO fest_mitarbeiter (fest_id, mitglied_id, vorname, nachname, telefon, email, funktion, ist_extern, notizen)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            (int)$data['fest_id'],
            !empty($data['mitglied_id']) ? (int)$data['mitglied_id'] : null,
            $data['vorname'] ?: null,
            $data['nachname'] ?: null,
            $data['telefon'] ?: null,
            $data['email'] ?: null,
            $data['funktion'] ?: null,
            isset($data['ist_extern']) ? (int)$data['ist_extern'] : 0,
            $data['notizen'] ?: null
        ]);
        return $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE fest_mitarbeiter SET mitglied_id=?, vorname=?, nachname=?, telefon=?, email=?, funktion=?, ist_extern=?, notizen=?
                WHERE id=?";
        return $this->db->execute($sql, [
            !empty($data['mitglied_id']) ? (int)$data['mitglied_id'] : null,
            $data['vorname'] ?: null,
            $data['nachname'] ?: null,
            $data['telefon'] ?: null,
            $data['email'] ?: null,
            $data['funktion'] ?: null,
            isset($data['ist_extern']) ? (int)$data['ist_extern'] : 0,
            $data['notizen'] ?: null,
            $id
        ]);
    }

    public function delete(int $id): bool {
        return $this->db->execute("DELETE FROM fest_mitarbeiter WHERE id = ?", [$id]);
    }

    /**
     * Vereinsmitglieder die noch NICHT in diesem Fest eingetragen sind
     */
    public function getFreieMitglieder(int $festId): array {
        $sql = "SELECT id, vorname, nachname, mitgliedsnummer
                FROM mitglieder
                WHERE status = 'aktiv'
                AND id NOT IN (
                    SELECT mitglied_id FROM fest_mitarbeiter
                    WHERE fest_id = ? AND mitglied_id IS NOT NULL
                )
                ORDER BY nachname, vorname";
        return $this->db->fetchAll($sql, [$festId]);
    }
}
