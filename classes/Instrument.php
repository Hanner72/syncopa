<?php
// classes/Instrument.php

class Instrument {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll($filter = []) {
        $where = [];
        $params = [];
        
        if (!empty($filter['typ'])) {
            $where[] = "i.instrument_typ_id = ?";
            $params[] = $filter['typ'];
        }
        
        if (!empty($filter['zustand'])) {
            $where[] = "i.zustand = ?";
            $params[] = $filter['zustand'];
        }
        
        if (!empty($filter['ausgeliehen'])) {
            if ($filter['ausgeliehen'] === 'ja') {
                $where[] = "i.mitglied_id IS NOT NULL";
            } else {
                $where[] = "i.mitglied_id IS NULL";
            }
        }
        
        if (!empty($filter['search'])) {
            $where[] = "(i.inventar_nummer LIKE ? OR i.hersteller LIKE ? OR i.modell LIKE ?)";
            $searchTerm = "%{$filter['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT i.*, it.name as instrument_name, r.name as register_name,
                m.vorname, m.nachname, m.mitgliedsnummer
                FROM instrumente i
                JOIN instrument_typen it ON i.instrument_typ_id = it.id
                LEFT JOIN register r ON it.register_id = r.id
                LEFT JOIN mitglieder m ON i.mitglied_id = m.id
                {$whereClause}
                ORDER BY it.name, i.inventar_nummer";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getById($id) {
        $sql = "SELECT i.*, it.name as instrument_name, r.name as register_name,
                m.vorname, m.nachname, m.mitgliedsnummer
                FROM instrumente i
                JOIN instrument_typen it ON i.instrument_typ_id = it.id
                LEFT JOIN register r ON it.register_id = r.id
                LEFT JOIN mitglieder m ON i.mitglied_id = m.id
                WHERE i.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    public function create($data) {
        $sql = "INSERT INTO instrumente (
                    inventar_nummer, instrument_typ_id, hersteller, modell, seriennummer,
                    baujahr, anschaffungsdatum, anschaffungspreis, zustand, standort,
                    versicherungswert, notizen
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['inventar_nummer'],
            $data['instrument_typ_id'],
            $data['hersteller'] ?? null,
            $data['modell'] ?? null,
            $data['seriennummer'] ?? null,
            $data['baujahr'] ?? null,
            $data['anschaffungsdatum'] ?? null,
            $data['anschaffungspreis'] ?? null,
            $data['zustand'] ?? 'gut',
            $data['standort'] ?? null,
            $data['versicherungswert'] ?? null,
            $data['notizen'] ?? null
        ];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE instrumente SET 
                instrument_typ_id = ?, hersteller = ?, modell = ?, seriennummer = ?,
                baujahr = ?, anschaffungsdatum = ?, anschaffungspreis = ?, zustand = ?,
                standort = ?, versicherungswert = ?, notizen = ?
                WHERE id = ?";
        
        $params = [
            $data['instrument_typ_id'],
            $data['hersteller'] ?? null,
            $data['modell'] ?? null,
            $data['seriennummer'] ?? null,
            $data['baujahr'] ?? null,
            $data['anschaffungsdatum'] ?? null,
            $data['anschaffungspreis'] ?? null,
            $data['zustand'] ?? 'gut',
            $data['standort'] ?? null,
            $data['versicherungswert'] ?? null,
            $data['notizen'] ?? null,
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM instrumente WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    public function ausleihen($instrumentId, $mitgliedId) {
        $sql = "UPDATE instrumente SET mitglied_id = ?, ausgeliehen_seit = ? WHERE id = ?";
        return $this->db->execute($sql, [$mitgliedId, date('Y-m-d'), $instrumentId]);
    }
    
    public function zurueckgeben($instrumentId) {
        $sql = "UPDATE instrumente SET mitglied_id = NULL, ausgeliehen_seit = NULL WHERE id = ?";
        return $this->db->execute($sql, [$instrumentId]);
    }
    
    public function getWartungen($instrumentId) {
        $sql = "SELECT * FROM instrument_wartungen 
                WHERE instrument_id = ? 
                ORDER BY datum DESC";
        return $this->db->fetchAll($sql, [$instrumentId]);
    }
    
    public function addWartung($data) {
        $sql = "INSERT INTO instrument_wartungen (
                    instrument_id, datum, art, beschreibung, kosten,
                    durchgefuehrt_von, naechste_wartung
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['instrument_id'],
            $data['datum'],
            $data['art'],
            $data['beschreibung'] ?? null,
            $data['kosten'] ?? null,
            $data['durchgefuehrt_von'] ?? null,
            $data['naechste_wartung'] ?? null
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function getFaelligeWartungen() {
        $sql = "SELECT i.inventar_nummer, i.id, it.name as instrument_name, 
                MAX(w.naechste_wartung) as naechste_wartung, 
                MAX(w.art) as letzte_art
                FROM instrumente i
                JOIN instrument_typen it ON i.instrument_typ_id = it.id
                JOIN instrument_wartungen w ON i.id = w.instrument_id
                WHERE w.naechste_wartung <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND w.naechste_wartung >= CURDATE()
                GROUP BY i.id, i.inventar_nummer, it.name
                ORDER BY MAX(w.naechste_wartung)";
        return $this->db->fetchAll($sql);
    }
    
    public function getTypen() {
        $sql = "SELECT it.*, r.name as register_name 
                FROM instrument_typen it
                LEFT JOIN register r ON it.register_id = r.id
                ORDER BY r.sortierung, it.name";
        return $this->db->fetchAll($sql);
    }
    
    public function getStatistik() {
        $stats = [];
        
        // Gesamtanzahl
        $sql = "SELECT COUNT(*) as total FROM instrumente";
        $result = $this->db->fetchOne($sql);
        $stats['total'] = $result['total'];
        
        // Ausgeliehen
        $sql = "SELECT COUNT(*) as ausgeliehen FROM instrumente WHERE mitglied_id IS NOT NULL";
        $result = $this->db->fetchOne($sql);
        $stats['ausgeliehen'] = $result['ausgeliehen'];
        
        // Nach Typ
        $sql = "SELECT it.name, COUNT(i.id) as anzahl 
                FROM instrument_typen it 
                LEFT JOIN instrumente i ON it.id = i.instrument_typ_id
                GROUP BY it.id, it.name
                ORDER BY anzahl DESC
                LIMIT 10";
        $stats['typen'] = $this->db->fetchAll($sql);
        
        // Gesamtwert
        $sql = "SELECT SUM(anschaffungspreis) as gesamtwert FROM instrumente";
        $result = $this->db->fetchOne($sql);
        $stats['gesamtwert'] = $result['gesamtwert'] ?? 0;
        
        // Fällige Wartungen
        $stats['faellige_wartungen'] = count($this->getFaelligeWartungen());
        
        return $stats;
    }
}
