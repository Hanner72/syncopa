<?php
// classes/Fest.php
// Festverwaltung – Hauptdatensatz (feste)

class Fest {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Alle Feste abrufen, optional gefiltert
     */
    public function getAll(array $filter = []) {
        $sql = "SELECT f.*, b.benutzername as erstellt_von_name
                FROM feste f
                LEFT JOIN benutzer b ON f.erstellt_von = b.id
                WHERE 1=1";
        $params = [];

        if (!empty($filter['jahr'])) {
            $sql .= " AND f.jahr = ?";
            $params[] = (int)$filter['jahr'];
        }
        if (!empty($filter['status'])) {
            $sql .= " AND f.status = ?";
            $params[] = $filter['status'];
        }

        $sql .= " ORDER BY f.datum_von DESC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Einzelnes Fest abrufen
     */
    public function getById(int $id) {
        $sql = "SELECT f.*, b.benutzername as erstellt_von_name
                FROM feste f
                LEFT JOIN benutzer b ON f.erstellt_von = b.id
                WHERE f.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Fest anlegen
     */
    public function create(array $data): int {
        $sql = "INSERT INTO feste (name, jahr, datum_von, datum_bis, ort, adresse, beschreibung, status, erstellt_von)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            $data['name'],
            (int)$data['jahr'],
            $data['datum_von'],
            $data['datum_bis'] ?: null,
            $data['ort'] ?: null,
            $data['adresse'] ?: null,
            $data['beschreibung'] ?: null,
            $data['status'] ?? 'geplant',
            $data['erstellt_von'] ?? null
        ]);
        return $this->db->lastInsertId();
    }

    /**
     * Fest aktualisieren
     */
    public function update(int $id, array $data): bool {
        $sql = "UPDATE feste SET name=?, jahr=?, datum_von=?, datum_bis=?, ort=?, adresse=?, beschreibung=?, status=?
                WHERE id=?";
        return $this->db->execute($sql, [
            $data['name'],
            (int)$data['jahr'],
            $data['datum_von'],
            $data['datum_bis'] ?: null,
            $data['ort'] ?: null,
            $data['adresse'] ?: null,
            $data['beschreibung'] ?: null,
            $data['status'] ?? 'geplant',
            $id
        ]);
    }

    /**
     * Fest löschen (Cascade löscht alle Sub-Datensätze)
     */
    public function delete(int $id): bool {
        return $this->db->execute("DELETE FROM feste WHERE id = ?", [$id]);
    }

    /**
     * Dashboard-Statistiken für ein einzelnes Fest
     */
    public function getDashboardStats(int $festId): array {
        $stats = [];

        $stats['stationen'] = $this->db->fetchOne(
            "SELECT COUNT(*) as n FROM fest_stationen WHERE fest_id = ?", [$festId]
        )['n'] ?? 0;

        $stats['mitarbeiter'] = $this->db->fetchOne(
            "SELECT COUNT(*) as n FROM fest_mitarbeiter WHERE fest_id = ?", [$festId]
        )['n'] ?? 0;

        $stats['todos_offen'] = $this->db->fetchOne(
            "SELECT COUNT(*) as n FROM fest_todos WHERE fest_id = ? AND status IN ('offen','in_arbeit')", [$festId]
        )['n'] ?? 0;

        $stats['todos_gesamt'] = $this->db->fetchOne(
            "SELECT COUNT(*) as n FROM fest_todos WHERE fest_id = ?", [$festId]
        )['n'] ?? 0;

        $stats['einkauefe_gesamt'] = $this->db->fetchOne(
            "SELECT COUNT(*) as n FROM fest_einkauefe WHERE fest_id = ?", [$festId]
        )['n'] ?? 0;

        $stats['einkauefe_summe'] = $this->db->fetchOne(
            "SELECT COALESCE(SUM(preis_gesamt), 0) as n FROM fest_einkauefe WHERE fest_id = ?", [$festId]
        )['n'] ?? 0;

        $stats['vertraege'] = $this->db->fetchOne(
            "SELECT COUNT(*) as n FROM fest_vertraege WHERE fest_id = ?", [$festId]
        )['n'] ?? 0;

        $stats['vertraege_offen'] = $this->db->fetchOne(
            "SELECT COUNT(*) as n FROM fest_vertraege WHERE fest_id = ? AND zahlungsstatus = 'offen'", [$festId]
        )['n'] ?? 0;

        $stats['dienstplaene'] = $this->db->fetchOne(
            "SELECT COUNT(*) as n FROM fest_dienstplaene WHERE fest_id = ?", [$festId]
        )['n'] ?? 0;

        return $stats;
    }

    /**
     * Verfügbare Jahre (für Filter-Dropdown)
     */
    public function getJahre(): array {
        return $this->db->fetchAll("SELECT DISTINCT jahr FROM feste ORDER BY jahr DESC");
    }
}
