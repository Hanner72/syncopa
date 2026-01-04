<?php
// classes/Noten.php

class Noten {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll($filter = []) {
        $where = [];
        $params = [];
        
        if (!empty($filter['search'])) {
            $where[] = "(titel LIKE ? OR komponist LIKE ? OR arrangeur LIKE ?)";
            $searchTerm = "%{$filter['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filter['genre'])) {
            $where[] = "genre = ?";
            $params[] = $filter['genre'];
        }
        
        if (!empty($filter['schwierigkeitsgrad'])) {
            $where[] = "schwierigkeitsgrad = ?";
            $params[] = $filter['schwierigkeitsgrad'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM noten 
                {$whereClause}
                ORDER BY titel";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getById($id) {
        $sql = "SELECT * FROM noten WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    public function create($data) {
        // Generiere Archivnummer falls nicht vorhanden
        if (empty($data['archiv_nummer'])) {
            $data['archiv_nummer'] = $this->generateArchivNummer();
        }
        
        $sql = "INSERT INTO noten (
                    titel, untertitel, komponist, arrangeur, verlag, besetzung,
                    schwierigkeitsgrad, dauer_minuten, genre, archiv_nummer,
                    anzahl_stimmen, zustand, bemerkungen, standort
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['titel'],
            $data['untertitel'] ?? null,
            $data['komponist'] ?? null,
            $data['arrangeur'] ?? null,
            $data['verlag'] ?? null,
            $data['besetzung'] ?? null,
            $data['schwierigkeitsgrad'] ?? '3',
            $data['dauer_minuten'] ?? null,
            $data['genre'] ?? null,
            $data['archiv_nummer'],
            $data['anzahl_stimmen'] ?? null,
            $data['zustand'] ?? 'gut',
            $data['bemerkungen'] ?? null,
            $data['standort'] ?? null
        ];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    public function update($id, $data) {
        $sql = "UPDATE noten SET 
                titel = ?, untertitel = ?, komponist = ?, arrangeur = ?, verlag = ?, besetzung = ?,
                schwierigkeitsgrad = ?, dauer_minuten = ?, genre = ?, anzahl_stimmen = ?,
                zustand = ?, bemerkungen = ?, standort = ?
                WHERE id = ?";
        
        $params = [
            $data['titel'],
            $data['untertitel'] ?? null,
            $data['komponist'] ?? null,
            $data['arrangeur'] ?? null,
            $data['verlag'] ?? null,
            $data['besetzung'] ?? null,
            $data['schwierigkeitsgrad'] ?? '3',
            $data['dauer_minuten'] ?? null,
            $data['genre'] ?? null,
            $data['anzahl_stimmen'] ?? null,
            $data['zustand'] ?? 'gut',
            $data['bemerkungen'] ?? null,
            $data['standort'] ?? null,
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM noten WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    public function getGenres() {
        $sql = "SELECT DISTINCT genre FROM noten WHERE genre IS NOT NULL ORDER BY genre";
        $result = $this->db->fetchAll($sql);
        return array_column($result, 'genre');
    }
    
    public function search($term) {
        $sql = "SELECT * FROM noten 
                WHERE titel LIKE ? OR komponist LIKE ? OR arrangeur LIKE ?
                ORDER BY titel
                LIMIT 50";
        $searchTerm = "%{$term}%";
        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    public function getStatistik() {
        $stats = [];
        
        // Gesamtanzahl
        $sql = "SELECT COUNT(*) as total FROM noten";
        $result = $this->db->fetchOne($sql);
        $stats['total'] = $result['total'];
        
        // Nach Schwierigkeitsgrad
        $sql = "SELECT schwierigkeitsgrad, COUNT(*) as anzahl 
                FROM noten 
                GROUP BY schwierigkeitsgrad 
                ORDER BY schwierigkeitsgrad";
        $stats['schwierigkeitsgrad'] = $this->db->fetchAll($sql);
        
        // Nach Genre
        $sql = "SELECT genre, COUNT(*) as anzahl 
                FROM noten 
                WHERE genre IS NOT NULL
                GROUP BY genre 
                ORDER BY anzahl DESC
                LIMIT 10";
        $stats['genres'] = $this->db->fetchAll($sql);
        
        // Neueste Noten
        $sql = "SELECT titel, komponist, erstellt_am 
                FROM noten 
                ORDER BY erstellt_am DESC 
                LIMIT 5";
        $stats['neueste'] = $this->db->fetchAll($sql);
        
        return $stats;
    }
    
    public function uploadPDF($notenId, $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload fehlgeschlagen');
        }
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileExtension !== 'pdf') {
            throw new Exception('Nur PDF-Dateien erlaubt');
        }
        
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            throw new Exception('Datei zu groß (max. ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB)');
        }
        
        $fileName = $notenId . '_' . time() . '.pdf';
        $filePath = NOTEN_DIR . '/' . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Datei konnte nicht gespeichert werden');
        }
        
        // Update Datenbank
        $sql = "UPDATE noten SET pdf_datei = ? WHERE id = ?";
        $this->db->execute($sql, [$fileName, $notenId]);
        
        return $fileName;
    }
    
    private function generateArchivNummer() {
        $sql = "SELECT MAX(CAST(SUBSTRING(archiv_nummer, 2) AS UNSIGNED)) as max_nr 
                FROM noten 
                WHERE archiv_nummer REGEXP '^N[0-9]+$'";
        $result = $this->db->fetchOne($sql);
        $nextNr = ($result['max_nr'] ?? 0) + 1;
        return 'N' . str_pad($nextNr, 5, '0', STR_PAD_LEFT);
    }
}
