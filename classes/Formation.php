<?php
// classes/Formation.php

class Formation {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll($nurAktive = false) {
        $where = $nurAktive ? 'WHERE aktiv = 1' : '';
        $sql = "SELECT f.*,
                    (SELECT COUNT(*) FROM mitglied_formationen mf WHERE mf.formation_id = f.id) as mitglieder_anzahl
                FROM formationen f
                {$where}
                ORDER BY f.name";
        return $this->db->fetchAll($sql);
    }

    public function getById($id) {
        $sql = "SELECT f.*,
                    (SELECT COUNT(*) FROM mitglied_formationen mf WHERE mf.formation_id = f.id) as mitglieder_anzahl
                FROM formationen f
                WHERE f.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function create($data) {
        $sql = "INSERT INTO formationen (name, kuerzel, farbe, beschreibung, aktiv)
                VALUES (?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            $data['name'],
            $data['kuerzel']     ?? null,
            $data['farbe']       ?? '#4471A3',
            $data['beschreibung'] ?? null,
            isset($data['aktiv']) ? (int)$data['aktiv'] : 1,
        ]);
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $sql = "UPDATE formationen SET name = ?, kuerzel = ?, farbe = ?, beschreibung = ?, aktiv = ?
                WHERE id = ?";
        return $this->db->execute($sql, [
            $data['name'],
            $data['kuerzel']     ?? null,
            $data['farbe']       ?? '#4471A3',
            $data['beschreibung'] ?? null,
            isset($data['aktiv']) ? (int)$data['aktiv'] : 1,
            $id,
        ]);
    }

    public function delete($id) {
        return $this->db->execute("DELETE FROM formationen WHERE id = ?", [$id]);
    }

    // --- Mitglieder-Zuordnung ---

    public function getMitglieder($formationId) {
        $sql = "SELECT m.*, mf.rolle, mf.seit_datum, r.name as register_name
                FROM mitglied_formationen mf
                JOIN mitglieder m  ON mf.mitglied_id  = m.id
                LEFT JOIN register r ON m.register_id = r.id
                WHERE mf.formation_id = ?
                ORDER BY m.nachname, m.vorname";
        return $this->db->fetchAll($sql, [$formationId]);
    }

    public function getMitgliederIdsForFormation($formationId) {
        $rows = $this->db->fetchAll(
            "SELECT mitglied_id FROM mitglied_formationen WHERE formation_id = ?",
            [$formationId]
        );
        return array_column($rows, 'mitglied_id');
    }

    public function addMitglied($formationId, $mitgliedId, $rolle = null, $seitDatum = null) {
        $sql = "INSERT IGNORE INTO mitglied_formationen (mitglied_id, formation_id, rolle, seit_datum)
                VALUES (?, ?, ?, ?)";
        return $this->db->execute($sql, [
            $mitgliedId,
            $formationId,
            $rolle,
            $seitDatum ?? date('Y-m-d'),
        ]);
    }

    public function removeMitglied($formationId, $mitgliedId) {
        return $this->db->execute(
            "DELETE FROM mitglied_formationen WHERE formation_id = ? AND mitglied_id = ?",
            [$formationId, $mitgliedId]
        );
    }

    public function updateMitgliedRolle($formationId, $mitgliedId, $rolle) {
        return $this->db->execute(
            "UPDATE mitglied_formationen SET rolle = ? WHERE formation_id = ? AND mitglied_id = ?",
            [$rolle, $formationId, $mitgliedId]
        );
    }

    // Alle Formationen eines Mitglieds
    public function getFormationenFuerMitglied($mitgliedId) {
        $sql = "SELECT f.*, mf.rolle, mf.seit_datum
                FROM mitglied_formationen mf
                JOIN formationen f ON mf.formation_id = f.id
                WHERE mf.mitglied_id = ? AND f.aktiv = 1
                ORDER BY f.name";
        return $this->db->fetchAll($sql, [$mitgliedId]);
    }

    // Alle Formationen, denen der eingeloggte Benutzer über sein Mitglied angehört
    public function getFormationenFuerBenutzer($benutzerId) {
        $sql = "SELECT f.*, mf.rolle, mf.seit_datum
                FROM mitglied_formationen mf
                JOIN mitglieder m  ON mf.mitglied_id  = m.id
                JOIN formationen f ON mf.formation_id = f.id
                WHERE m.benutzer_id = ? AND f.aktiv = 1
                ORDER BY f.name";
        return $this->db->fetchAll($sql, [$benutzerId]);
    }

    // Mitglieder die NICHT in dieser Formation sind (für Zuweisung)
    public function getMitgliederOhneFormation($formationId) {
        $sql = "SELECT m.*, r.name as register_name
                FROM mitglieder m
                LEFT JOIN register r ON m.register_id = r.id
                WHERE m.status = 'aktiv'
                  AND m.id NOT IN (
                      SELECT mitglied_id FROM mitglied_formationen WHERE formation_id = ?
                  )
                ORDER BY m.nachname, m.vorname";
        return $this->db->fetchAll($sql, [$formationId]);
    }

    // Hilfsmethode für Queries: gibt SQL-Snippet + Parameter zurück
    // Gibt zurück: ['condition' => '...', 'params' => [...]]
    // Wenn formation_id null (kein Filter): leeres condition
    public static function getFilterCondition(?int $formationId, string $alias = ''): array {
        $col = $alias ? "{$alias}.formation_id" : 'formation_id';
        if ($formationId === null) {
            return ['condition' => '', 'params' => []];
        }
        return [
            'condition' => "({$col} = ? OR {$col} IS NULL)",
            'params'    => [$formationId],
        ];
    }
}
