<?php
// classes/FestEinkauf.php
// Festverwaltung – Einkaufsliste

class FestEinkauf {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        // station_id Spalte nachrüsten falls noch nicht vorhanden
        try {
            $this->db->execute("ALTER TABLE fest_einkauefe ADD COLUMN station_id INT NULL DEFAULT NULL AFTER kategorie_id");
        } catch (\Throwable $e) { /* Spalte existiert bereits */ }
    }

    /**
     * Einkäufe eines Festes abrufen, optional gefiltert
     */
    public function getByFest(int $festId, array $filter = []): array {
        $sql = "SELECT e.*, k.name as kategorie_name, k.sortierung as kat_sortierung,
                    s.name as station_name, s.sortierung as station_sortierung
                FROM fest_einkauefe e
                LEFT JOIN fest_einkauf_kategorien k ON e.kategorie_id = k.id
                LEFT JOIN fest_stationen s ON e.station_id = s.id
                WHERE e.fest_id = ?";
        $params = [$festId];

        if (!empty($filter['status'])) {
            $sql .= " AND e.status = ?";
            $params[] = $filter['status'];
        }
        if (!empty($filter['kategorie_id'])) {
            $sql .= " AND e.kategorie_id = ?";
            $params[] = (int)$filter['kategorie_id'];
        }
        if (isset($filter['ist_vorlage']) && $filter['ist_vorlage'] !== '') {
            $sql .= " AND e.ist_vorlage = ?";
            $params[] = (int)$filter['ist_vorlage'];
        }
        if (!empty($filter['bezeichnung'])) {
            $sql .= " AND e.bezeichnung LIKE ?";
            $params[] = '%' . $filter['bezeichnung'] . '%';
        }
        if (!empty($filter['lieferant'])) {
            $sql .= " AND e.lieferant LIKE ?";
            $params[] = '%' . $filter['lieferant'] . '%';
        }
        if (!empty($filter['station_id'])) {
            $sql .= " AND e.station_id = ?";
            $params[] = (int)$filter['station_id'];
        }

        $sql .= " ORDER BY k.sortierung, k.name, e.bezeichnung";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Einkäufe gruppiert nach Kategorie (für Listenansicht)
     */
    public function getByFestGrouped(int $festId, array $filter = []): array {
        $rows = $this->getByFest($festId, $filter);
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['kategorie_id'] ?? 0;
            $grouped[$key]['name'] = $row['kategorie_name'] ?? 'Ohne Kategorie';
            $grouped[$key]['items'][] = $row;
        }
        return $grouped;
    }

    /**
     * Einkäufe gruppiert nach Station
     */
    public function getByFestGroupedByStation(int $festId, array $filter = []): array {
        $rows = $this->getByFest($festId, $filter);
        // Sortierung nach station_sortierung dann station_name
        usort($rows, function($a, $b) {
            $sa = (int)($a['station_sortierung'] ?? 9999);
            $sb = (int)($b['station_sortierung'] ?? 9999);
            if ($sa !== $sb) return $sa - $sb;
            return strcmp($a['station_name'] ?? '', $b['station_name'] ?? '');
        });
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['station_id'] ?? 0;
            $grouped[$key]['name'] = $row['station_name'] ?? 'Keine Station';
            $grouped[$key]['items'][] = $row;
        }
        return $grouped;
    }

    /**
     * Einkäufe gruppiert nach Lieferant → dann Station
     * Für Bestellliste
     */
    public function getByFestGroupedByLieferant(int $festId, array $filter = []): array {
        $rows = $this->getByFest($festId, $filter);
        usort($rows, function($a, $b) {
            $la = $a['lieferant'] ?? '';
            $lb = $b['lieferant'] ?? '';
            if ($la !== $lb) return strcmp($la, $lb);
            $sa = (int)($a['station_sortierung'] ?? 9999);
            $sb = (int)($b['station_sortierung'] ?? 9999);
            if ($sa !== $sb) return $sa - $sb;
            return strcmp($a['bezeichnung'], $b['bezeichnung']);
        });
        $grouped = [];
        foreach ($rows as $row) {
            $lKey = $row['lieferant'] ?: '__kein__';
            $lName = $row['lieferant'] ?: 'Kein Lieferant';
            $sKey  = $row['station_id'] ?? 0;
            $sName = $row['station_name'] ?? 'Keine Station';
            $grouped[$lKey]['name'] = $lName;
            $grouped[$lKey]['stationen'][$sKey]['name'] = $sName;
            $grouped[$lKey]['stationen'][$sKey]['items'][] = $row;
        }
        return $grouped;
    }

    /**
     * Geplante/bestellte Artikel gruppiert nach Lieferant (für Dashboard)
     */
    public function getGeplantByLieferant(int $festId): array {
        $sql = "SELECT
                    COALESCE(lieferant, '– Kein Lieferant –') as lieferant,
                    COUNT(*) as anzahl,
                    SUM(CASE WHEN status = 'bestellt' THEN 1 ELSE 0 END) as bestellt
                FROM fest_einkauefe
                WHERE fest_id = ? AND status IN ('geplant','bestellt')
                GROUP BY lieferant
                ORDER BY lieferant";
        return $this->db->fetchAll($sql, [$festId]);
    }

    /**
     * Stationen die einem Fest zugeordnete Einkäufe haben
     */
    public function getStationen(int $festId): array {
        $sql = "SELECT DISTINCT s.id, s.name, s.sortierung
                FROM fest_einkauefe e
                JOIN fest_stationen s ON e.station_id = s.id
                WHERE e.fest_id = ?
                ORDER BY s.sortierung, s.name";
        return $this->db->fetchAll($sql, [$festId]);
    }

    public function getById(int $id) {
        $sql = "SELECT e.*, k.name as kategorie_name
                FROM fest_einkauefe e
                LEFT JOIN fest_einkauf_kategorien k ON e.kategorie_id = k.id
                WHERE e.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function create(array $data): int {
        $sql = "INSERT INTO fest_einkauefe (fest_id, kategorie_id, station_id, bezeichnung, menge, einheit, preis_gesamt, lieferant, status, ist_vorlage, notizen, erstellt_von)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            (int)$data['fest_id'],
            !empty($data['kategorie_id']) ? (int)$data['kategorie_id'] : null,
            !empty($data['station_id'])   ? (int)$data['station_id']   : null,
            $data['bezeichnung'],
            !empty($data['menge']) ? (float)$data['menge'] : null,
            $data['einheit'] ?: null,
            !empty($data['preis_gesamt']) ? (float)$data['preis_gesamt'] : null,
            $data['lieferant'] ?: null,
            $data['status'] ?? 'geplant',
            isset($data['ist_vorlage']) ? (int)$data['ist_vorlage'] : 0,
            $data['notizen'] ?: null,
            $data['erstellt_von'] ?? null
        ]);
        return $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void {
        $sql = "UPDATE fest_einkauefe SET kategorie_id=?, station_id=?, bezeichnung=?, menge=?, einheit=?, preis_gesamt=?, lieferant=?, status=?, ist_vorlage=?, notizen=?
                WHERE id=?";
        $this->db->execute($sql, [
            !empty($data['kategorie_id']) ? (int)$data['kategorie_id'] : null,
            !empty($data['station_id'])   ? (int)$data['station_id']   : null,
            $data['bezeichnung'],
            !empty($data['menge']) ? (float)$data['menge'] : null,
            $data['einheit'] ?: null,
            !empty($data['preis_gesamt']) ? (float)$data['preis_gesamt'] : null,
            $data['lieferant'] ?: null,
            $data['status'] ?? 'geplant',
            isset($data['ist_vorlage']) ? (int)$data['ist_vorlage'] : 0,
            $data['notizen'] ?: null,
            $id
        ]);
    }

    public function delete(int $id): void {
        $this->db->execute("DELETE FROM fest_einkauefe WHERE id = ?", [$id]);
    }

    /**
     * Alle vorhandenen Lieferanten eines Festes (für Datalist)
     */
    public function getLieferanten(int $festId): array {
        $sql = "SELECT DISTINCT lieferant FROM fest_einkauefe
                WHERE fest_id = ? AND lieferant IS NOT NULL AND lieferant != ''
                ORDER BY lieferant";
        return array_column($this->db->fetchAll($sql, [$festId]), 'lieferant');
    }

    /**
     * Alle Einkauf-Kategorien
     */
    public function getKategorien(): array {
        return $this->db->fetchAll("SELECT * FROM fest_einkauf_kategorien ORDER BY sortierung, name");
    }

    /**
     * Gesamtsummen pro Status und gesamt
     */
    public function getSummen(int $festId): array {
        $sql = "SELECT status, COUNT(*) as anzahl, COALESCE(SUM(preis_gesamt), 0) as summe
                FROM fest_einkauefe WHERE fest_id = ? GROUP BY status";
        $rows = $this->db->fetchAll($sql, [$festId]);

        $result = ['geplant' => 0, 'bestellt' => 0, 'erhalten' => 0, 'storniert' => 0, 'gesamt' => 0, 'anzahl' => 0];
        foreach ($rows as $row) {
            $result[$row['status']] = (float)$row['summe'];
            $result['gesamt'] += (float)$row['summe'];
            $result['anzahl'] += (int)$row['anzahl'];
        }
        return $result;
    }
}
