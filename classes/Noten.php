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
        
        $sql = "SELECT n.*, 
                       (SELECT COUNT(*) FROM noten_dateien WHERE noten_id = n.id) as anzahl_dateien
                FROM noten n
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
        // Dateien löschen
        $dateien = $this->getDateien($id);
        foreach ($dateien as $datei) {
            $this->deleteDatei($datei['id']);
        }
        
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
    
    // ========================================================================
    // DATEIEN-VERWALTUNG (Mehrere PDFs pro Notenstück)
    // ========================================================================
    
    /**
     * Alle Dateien zu einem Notenstück abrufen
     */
    public function getDateien($notenId) {
        $sql = "SELECT nd.*, b.benutzername as hochgeladen_von_name
                FROM noten_dateien nd
                LEFT JOIN benutzer b ON nd.hochgeladen_von = b.id
                WHERE nd.noten_id = ?
                ORDER BY nd.sortierung, nd.erstellt_am";
        return $this->db->fetchAll($sql, [$notenId]);
    }
    
    /**
     * Eine einzelne Datei abrufen
     */
    public function getDateiById($dateiId) {
        $sql = "SELECT nd.*, n.titel as noten_titel
                FROM noten_dateien nd
                JOIN noten n ON nd.noten_id = n.id
                WHERE nd.id = ?";
        return $this->db->fetchOne($sql, [$dateiId]);
    }
    
    /**
     * PDF-Datei hochladen
     */
    public function uploadDatei($notenId, $file, $beschreibung = null, $benutzerId = null) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Datei überschreitet upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'Datei überschreitet MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'Datei nur teilweise hochgeladen',
                UPLOAD_ERR_NO_FILE => 'Keine Datei hochgeladen',
                UPLOAD_ERR_NO_TMP_DIR => 'Temporärer Ordner fehlt',
                UPLOAD_ERR_CANT_WRITE => 'Schreiben auf Festplatte fehlgeschlagen',
                UPLOAD_ERR_EXTENSION => 'Upload durch Extension gestoppt'
            ];
            throw new Exception($errorMessages[$file['error']] ?? 'Upload fehlgeschlagen');
        }
        
        // Dateityp prüfen
        $allowedTypes = ['application/pdf'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Nur PDF-Dateien sind erlaubt');
        }
        
        // Größe prüfen
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            throw new Exception('Datei zu groß (max. ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB)');
        }
        
        // Sicheren Dateinamen generieren
        $originalName = basename($file['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $uniqueName = $notenId . '_' . uniqid() . '_' . time() . '.' . $extension;
        $filePath = NOTEN_DIR . DIRECTORY_SEPARATOR . $uniqueName;
        
        // Upload-Verzeichnis erstellen falls nötig
        if (!is_dir(NOTEN_DIR)) {
            mkdir(NOTEN_DIR, 0755, true);
        }
        
        // Datei verschieben
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Datei konnte nicht gespeichert werden');
        }
        
        // Nächste Sortierung ermitteln
        $sql = "SELECT COALESCE(MAX(sortierung), 0) + 1 as next FROM noten_dateien WHERE noten_id = ?";
        $result = $this->db->fetchOne($sql, [$notenId]);
        $sortierung = $result['next'];
        
        // In Datenbank speichern
        $sql = "INSERT INTO noten_dateien (noten_id, dateiname, original_name, dateityp, dateigroesse, beschreibung, sortierung, hochgeladen_von)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            $notenId,
            $uniqueName,
            $originalName,
            $mimeType,
            $file['size'],
            $beschreibung,
            $sortierung,
            $benutzerId
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Mehrere Dateien auf einmal hochladen
     */
    public function uploadMultipleDateien($notenId, $files, $benutzerId = null) {
        $uploaded = [];
        $errors = [];
        
        // $_FILES Array normalisieren
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            // Leere Einträge überspringen
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            try {
                $id = $this->uploadDatei($notenId, $file, null, $benutzerId);
                $uploaded[] = [
                    'id' => $id,
                    'name' => $file['name']
                ];
            } catch (Exception $e) {
                $errors[] = [
                    'name' => $file['name'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return ['uploaded' => $uploaded, 'errors' => $errors];
    }
    
    /**
     * Datei löschen
     */
    public function deleteDatei($dateiId) {
        // Datei-Info holen
        $datei = $this->getDateiById($dateiId);
        if (!$datei) {
            return false;
        }
        
        // Physische Datei löschen
        $filePath = NOTEN_DIR . DIRECTORY_SEPARATOR . $datei['dateiname'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Aus Datenbank löschen
        $sql = "DELETE FROM noten_dateien WHERE id = ?";
        return $this->db->execute($sql, [$dateiId]);
    }
    
    /**
     * Datei-Beschreibung aktualisieren
     */
    public function updateDateiBeschreibung($dateiId, $beschreibung) {
        $sql = "UPDATE noten_dateien SET beschreibung = ? WHERE id = ?";
        return $this->db->execute($sql, [$beschreibung, $dateiId]);
    }
    
    /**
     * Sortierung der Dateien aktualisieren
     */
    public function updateDateiSortierung($dateiIds) {
        foreach ($dateiIds as $sortierung => $dateiId) {
            $sql = "UPDATE noten_dateien SET sortierung = ? WHERE id = ?";
            $this->db->execute($sql, [$sortierung, $dateiId]);
        }
        return true;
    }
    
    /**
     * Dateipfad für Download ermitteln
     */
    public function getDateiPfad($dateiId) {
        $datei = $this->getDateiById($dateiId);
        if (!$datei) {
            return null;
        }
        
        $filePath = NOTEN_DIR . DIRECTORY_SEPARATOR . $datei['dateiname'];
        if (!file_exists($filePath)) {
            return null;
        }
        
        return [
            'path' => $filePath,
            'name' => $datei['original_name'],
            'type' => $datei['dateityp'],
            'size' => $datei['dateigroesse']
        ];
    }
    
    // ========================================================================
    // LEGACY: Einzelne PDF (Rückwärtskompatibilität)
    // ========================================================================
    
    public function uploadPDF($notenId, $file) {
        return $this->uploadDatei($notenId, $file);
    }
    
    private function generateArchivNummer() {
        $sql = "SELECT MAX(CAST(SUBSTRING(archiv_nummer, 2) AS UNSIGNED)) as max_nr 
                FROM noten 
                WHERE archiv_nummer REGEXP '^N[0-9]+$'";
        $result = $this->db->fetchOne($sql);
        $nextNr = ($result['max_nr'] ?? 0) + 1;
        /* return str_pad($nextNr, 5, '0', STR_PAD_LEFT); */ // ohne Vorangestelltem N
        return 'N' . str_pad($nextNr, 4, '0', STR_PAD_LEFT); // mit Vorangestelltem N
    }
}
