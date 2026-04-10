<?php
// classes/FestTodo.php
// Festverwaltung – Todos/Aufgaben

class FestTodo {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Todos eines Festes, optional gefiltert
     */
    public function getByFest(int $festId, array $filter = []): array {
        $sql = "SELECT t.*,
                    f.name as fest_name,
                    CASE WHEN mz.id IS NOT NULL AND (mz.vorname IS NOT NULL OR mz.nachname IS NOT NULL)
     THEN TRIM(CONCAT_WS(' ', mz.vorname, mz.nachname))
     ELSE b.benutzername END as zustaendig_name,
                    bv.benutzername as erstellt_von_name
                FROM fest_todos t
                JOIN feste f ON t.fest_id = f.id
                LEFT JOIN benutzer b ON t.zustaendig_id = b.id
                LEFT JOIN mitglieder mz ON b.mitglied_id = mz.id
                LEFT JOIN benutzer bv ON t.erstellt_von = bv.id
                WHERE t.fest_id = ?";
        $params = [$festId];

        if (!empty($filter['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filter['status'];
        }
        if (!empty($filter['prioritaet'])) {
            $sql .= " AND t.prioritaet = ?";
            $params[] = $filter['prioritaet'];
        }
        if (!empty($filter['zustaendig_id'])) {
            $sql .= " AND t.zustaendig_id = ?";
            $params[] = (int)$filter['zustaendig_id'];
        }

        $sql .= " ORDER BY FIELD(t.prioritaet,'kritisch','hoch','normal','niedrig'), t.faellig_am, t.titel";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Todos die einem bestimmten Benutzer zugewiesen sind (über alle Feste)
     */
    public function getMeineTodos(int $benutzerId): array {
        $sql = "SELECT t.*, f.name as fest_name,
                    CASE WHEN mz.id IS NOT NULL AND (mz.vorname IS NOT NULL OR mz.nachname IS NOT NULL)
     THEN TRIM(CONCAT_WS(' ', mz.vorname, mz.nachname))
     ELSE b.benutzername END as zustaendig_name
                FROM fest_todos t
                JOIN feste f ON t.fest_id = f.id
                LEFT JOIN benutzer b ON t.zustaendig_id = b.id
                LEFT JOIN mitglieder mz ON b.mitglied_id = mz.id
                WHERE t.zustaendig_id = ? AND t.status NOT IN ('erledigt','abgebrochen')
                ORDER BY FIELD(t.prioritaet,'kritisch','hoch','normal','niedrig'), t.faellig_am";
        return $this->db->fetchAll($sql, [$benutzerId]);
    }

    public function getById(int $id) {
        $sql = "SELECT t.*, f.name as fest_name,
                    CASE WHEN mz.id IS NOT NULL AND (mz.vorname IS NOT NULL OR mz.nachname IS NOT NULL)
     THEN TRIM(CONCAT_WS(' ', mz.vorname, mz.nachname))
     ELSE b.benutzername END as zustaendig_name
                FROM fest_todos t
                JOIN feste f ON t.fest_id = f.id
                LEFT JOIN benutzer b ON t.zustaendig_id = b.id
                LEFT JOIN mitglieder mz ON b.mitglied_id = mz.id
                WHERE t.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function create(array $data): int {
        $sql = "INSERT INTO fest_todos (fest_id, titel, beschreibung, faellig_am, zustaendig_id, status, prioritaet, erstellt_von)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            (int)$data['fest_id'],
            $data['titel'],
            $data['beschreibung'] ?: null,
            $data['faellig_am'] ?: null,
            !empty($data['zustaendig_id']) ? (int)$data['zustaendig_id'] : null,
            $data['status'] ?? 'offen',
            $data['prioritaet'] ?? 'normal',
            $data['erstellt_von'] ?? null
        ]);
        return $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void {
        $sql = "UPDATE fest_todos SET fest_id=?, titel=?, beschreibung=?, faellig_am=?, zustaendig_id=?, status=?, prioritaet=?
                WHERE id=?";
        $this->db->execute($sql, [
            (int)$data['fest_id'],
            $data['titel'],
            $data['beschreibung'] ?: null,
            $data['faellig_am'] ?: null,
            !empty($data['zustaendig_id']) ? (int)$data['zustaendig_id'] : null,
            $data['status'] ?? 'offen',
            $data['prioritaet'] ?? 'normal',
            $id
        ]);
    }

    /**
     * Nur Status aktualisieren (für AJAX-Toggle)
     */
    public function updateStatus(int $id, string $status): bool {
        $erlaubt = ['offen', 'in_arbeit', 'erledigt', 'abgebrochen'];
        if (!in_array($status, $erlaubt)) return false;
        $this->db->execute("UPDATE fest_todos SET status = ? WHERE id = ?", [$status, $id]);
        return true;
    }

    public function delete(int $id): void {
        $this->db->execute("DELETE FROM fest_todos WHERE id = ?", [$id]);
    }
}
