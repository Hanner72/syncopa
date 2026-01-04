<?php
// classes/Mitglied.php

class Mitglied {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll($filter = []) {
        $where = [];
        $params = [];
        
        if (!empty($filter['status'])) {
            $where[] = "m.status = ?";
            $params[] = $filter['status'];
        }
        
        if (!empty($filter['register'])) {
            $where[] = "m.register_id = ?";
            $params[] = $filter['register'];
        }
        
        if (!empty($filter['search'])) {
            $where[] = "(m.vorname LIKE ? OR m.nachname LIKE ? OR m.mitgliedsnummer LIKE ?)";
            $searchTerm = "%{$filter['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT m.*, r.name as register_name 
                FROM mitglieder m 
                LEFT JOIN register r ON m.register_id = r.id 
                {$whereClause}
                ORDER BY m.nachname, m.vorname";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getById($id) {
        $sql = "SELECT m.*, r.name as register_name, b.benutzername
                FROM mitglieder m 
                LEFT JOIN register r ON m.register_id = r.id
                LEFT JOIN benutzer b ON m.benutzer_id = b.id
                WHERE m.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    public function create($data) {
        // Generiere Mitgliedsnummer falls nicht vorhanden
        if (empty($data['mitgliedsnummer'])) {
            $data['mitgliedsnummer'] = $this->generateMitgliedsnummer();
        }
        
        $sql = "INSERT INTO mitglieder (
                    mitgliedsnummer, vorname, nachname, geburtsdatum, geschlecht,
                    strasse, plz, ort, land, telefon, mobil, email,
                    register_id, eintritt_datum, status, notizen
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['mitgliedsnummer'],
            $data['vorname'],
            $data['nachname'],
            $data['geburtsdatum'] ?? null,
            $data['geschlecht'] ?? 'd',
            $data['strasse'] ?? null,
            $data['plz'] ?? null,
            $data['ort'] ?? null,
            $data['land'] ?? 'Österreich',
            $data['telefon'] ?? null,
            $data['mobil'] ?? null,
            $data['email'] ?? null,
            $data['register_id'] ?? null,
            $data['eintritt_datum'] ?? date('Y-m-d'),
            $data['status'] ?? 'aktiv',
            $data['notizen'] ?? null
        ];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE mitglieder SET 
                vorname = ?, nachname = ?, geburtsdatum = ?, geschlecht = ?,
                strasse = ?, plz = ?, ort = ?, land = ?, telefon = ?, mobil = ?, email = ?,
                register_id = ?, status = ?, notizen = ?
                WHERE id = ?";
        
        $params = [
            $data['vorname'],
            $data['nachname'],
            $data['geburtsdatum'] ?? null,
            $data['geschlecht'] ?? 'd',
            $data['strasse'] ?? null,
            $data['plz'] ?? null,
            $data['ort'] ?? null,
            $data['land'] ?? 'Österreich',
            $data['telefon'] ?? null,
            $data['mobil'] ?? null,
            $data['email'] ?? null,
            $data['register_id'] ?? null,
            $data['status'] ?? 'aktiv',
            $data['notizen'] ?? null,
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM mitglieder WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    public function getInstrumente($mitgliedId) {
        $sql = "SELECT mi.*, it.name as instrument_name, r.name as register_name
                FROM mitglied_instrumente mi
                JOIN instrument_typen it ON mi.instrument_typ_id = it.id
                LEFT JOIN register r ON it.register_id = r.id
                WHERE mi.mitglied_id = ?
                ORDER BY mi.hauptinstrument DESC, it.name";
        return $this->db->fetchAll($sql, [$mitgliedId]);
    }
    
    public function addInstrument($mitgliedId, $instrumentTypId, $hauptinstrument = false) {
        $sql = "INSERT INTO mitglied_instrumente (mitglied_id, instrument_typ_id, hauptinstrument, seit_datum)
                VALUES (?, ?, ?, ?)";
        return $this->db->execute($sql, [$mitgliedId, $instrumentTypId, $hauptinstrument, date('Y-m-d')]);
    }
    
    public function removeInstrument($id) {
        $sql = "DELETE FROM mitglied_instrumente WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    public function getStatistik() {
        $stats = [];
        
        // Gesamtanzahl
        $sql = "SELECT COUNT(*) as total FROM mitglieder WHERE status = 'aktiv'";
        $result = $this->db->fetchOne($sql);
        $stats['total'] = $result['total'];
        
        // Nach Register
        $sql = "SELECT r.name, COUNT(m.id) as anzahl 
                FROM register r 
                LEFT JOIN mitglieder m ON r.id = m.register_id AND m.status = 'aktiv'
                GROUP BY r.id, r.name
                ORDER BY r.sortierung";
        $stats['register'] = $this->db->fetchAll($sql);
        
        // Nach Status
        $sql = "SELECT status, COUNT(*) as anzahl 
                FROM mitglieder 
                GROUP BY status";
        $stats['status'] = $this->db->fetchAll($sql);
        
        // Durchschnittsalter
        $sql = "SELECT AVG(YEAR(CURDATE()) - YEAR(geburtsdatum)) as durchschnittsalter 
                FROM mitglieder 
                WHERE status = 'aktiv' AND geburtsdatum IS NOT NULL";
        $result = $this->db->fetchOne($sql);
        $stats['durchschnittsalter'] = round($result['durchschnittsalter'] ?? 0);
        
        return $stats;
    }
    
    private function generateMitgliedsnummer() {
        $sql = "SELECT MAX(CAST(SUBSTRING(mitgliedsnummer, 3) AS UNSIGNED)) as max_nr 
                FROM mitglieder 
                WHERE mitgliedsnummer REGEXP '^MV[0-9]+$'";
        $result = $this->db->fetchOne($sql);
        $nextNr = ($result['max_nr'] ?? 0) + 1;
        return 'MV' . str_pad($nextNr, 4, '0', STR_PAD_LEFT);
    }
}
