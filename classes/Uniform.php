<?php
// classes/Uniform.php

class Uniform {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Alle Uniformteile abrufen
     */
    public function getAll($filter = []) {
        $where = ["u.aktiv = 1"];
        $params = [];
        
        if (!empty($filter['kategorie_id'])) {
            $where[] = "u.kategorie_id = ?";
            $params[] = $filter['kategorie_id'];
        }
        
        if (!empty($filter['zustand'])) {
            $where[] = "u.zustand = ?";
            $params[] = $filter['zustand'];
        }
        
        if (!empty($filter['ausgegeben'])) {
            if ($filter['ausgegeben'] === 'ja') {
                $where[] = "u.mitglied_id IS NOT NULL";
            } else {
                $where[] = "u.mitglied_id IS NULL";
            }
        }
        
        if (!empty($filter['groesse'])) {
            $where[] = "u.groesse = ?";
            $params[] = $filter['groesse'];
        }
        
        if (!empty($filter['search'])) {
            $where[] = "(u.inventar_nummer LIKE ? OR u.bezeichnung LIKE ? OR uk.name LIKE ?)";
            $searchTerm = "%{$filter['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $where);
        
        $sql = "SELECT u.*, uk.name as kategorie_name,
                m.vorname, m.nachname, m.mitgliedsnummer
                FROM uniformen u
                LEFT JOIN uniform_kategorien uk ON u.kategorie_id = uk.id
                LEFT JOIN mitglieder m ON u.mitglied_id = m.id
                {$whereClause}
                ORDER BY uk.sortierung, uk.name, u.groesse, u.inventar_nummer";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Einzelnes Uniformteil abrufen
     */
    public function getById($id) {
        $sql = "SELECT u.*, uk.name as kategorie_name,
                m.vorname, m.nachname, m.mitgliedsnummer
                FROM uniformen u
                LEFT JOIN uniform_kategorien uk ON u.kategorie_id = uk.id
                LEFT JOIN mitglieder m ON u.mitglied_id = m.id
                WHERE u.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Neues Uniformteil anlegen
     */
    public function create($data) {
        // Inventarnummer generieren falls nicht angegeben
        if (empty($data['inventar_nummer'])) {
            $data['inventar_nummer'] = $this->generateInventarNummer($data['kategorie_id']);
        }
        
        $sql = "INSERT INTO uniformen (
                    inventar_nummer, kategorie_id, bezeichnung, groesse, farbe,
                    anschaffungsdatum, anschaffungspreis, zustand, standort, notizen
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['inventar_nummer'],
            $data['kategorie_id'],
            $data['bezeichnung'] ?? null,
            $data['groesse'] ?? null,
            $data['farbe'] ?? null,
            $data['anschaffungsdatum'] ?? null,
            $data['anschaffungspreis'] ?? null,
            $data['zustand'] ?? 'gut',
            $data['standort'] ?? null,
            $data['notizen'] ?? null
        ];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    /**
     * Uniformteil aktualisieren
     */
    public function update($id, $data) {
        $sql = "UPDATE uniformen SET 
                kategorie_id = ?, bezeichnung = ?, groesse = ?, farbe = ?,
                anschaffungsdatum = ?, anschaffungspreis = ?, zustand = ?,
                standort = ?, notizen = ?
                WHERE id = ?";
        
        $params = [
            $data['kategorie_id'],
            $data['bezeichnung'] ?? null,
            $data['groesse'] ?? null,
            $data['farbe'] ?? null,
            $data['anschaffungsdatum'] ?? null,
            $data['anschaffungspreis'] ?? null,
            $data['zustand'] ?? 'gut',
            $data['standort'] ?? null,
            $data['notizen'] ?? null,
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Uniformteil löschen (soft delete)
     */
    public function delete($id) {
        $sql = "UPDATE uniformen SET aktiv = 0 WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Uniformteil an Mitglied ausgeben
     */
    public function ausgeben($uniformId, $mitgliedId, $bemerkungen = null) {
        // Uniform aktualisieren
        $sql = "UPDATE uniformen SET mitglied_id = ?, ausgabe_datum = ? WHERE id = ?";
        $this->db->execute($sql, [$mitgliedId, date('Y-m-d'), $uniformId]);
        
        // Ausgabe-Historie speichern
        $benutzerId = Session::getUserId();
        $uniform = $this->getById($uniformId);
        
        $sql = "INSERT INTO uniform_ausgaben 
                (uniform_id, mitglied_id, ausgabe_datum, zustand_bei_ausgabe, bemerkungen, ausgegeben_von)
                VALUES (?, ?, ?, ?, ?, ?)";
        return $this->db->execute($sql, [
            $uniformId, 
            $mitgliedId, 
            date('Y-m-d'), 
            $uniform['zustand'],
            $bemerkungen,
            $benutzerId
        ]);
    }
    
    /**
     * Uniformteil zurücknehmen
     */
    public function zuruecknehmen($uniformId, $zustand = null, $bemerkungen = null) {
        // Letzte Ausgabe finden und Rückgabe eintragen
        $sql = "UPDATE uniform_ausgaben 
                SET rueckgabe_datum = ?, zustand_bei_rueckgabe = ?
                WHERE uniform_id = ? AND rueckgabe_datum IS NULL
                ORDER BY ausgabe_datum DESC LIMIT 1";
        $this->db->execute($sql, [date('Y-m-d'), $zustand, $uniformId]);
        
        // Uniform aktualisieren
        $updateSql = "UPDATE uniformen SET mitglied_id = NULL, ausgabe_datum = NULL";
        if ($zustand) {
            $updateSql .= ", zustand = ?";
            $params = [$zustand, $uniformId];
        } else {
            $params = [$uniformId];
        }
        $updateSql .= " WHERE id = ?";
        
        return $this->db->execute($updateSql, $params);
    }
    
    /**
     * Ausgabe-Historie eines Uniformteils
     */
    public function getAusgabeHistorie($uniformId) {
        $sql = "SELECT ua.*, m.vorname, m.nachname, b.benutzername as ausgegeben_von_name
                FROM uniform_ausgaben ua
                JOIN mitglieder m ON ua.mitglied_id = m.id
                LEFT JOIN benutzer b ON ua.ausgegeben_von = b.id
                WHERE ua.uniform_id = ?
                ORDER BY ua.ausgabe_datum DESC";
        return $this->db->fetchAll($sql, [$uniformId]);
    }
    
    /**
     * Alle Uniformteile eines Mitglieds
     */
    public function getByMitglied($mitgliedId) {
        $sql = "SELECT u.*, uk.name as kategorie_name
                FROM uniformen u
                LEFT JOIN uniform_kategorien uk ON u.kategorie_id = uk.id
                WHERE u.mitglied_id = ? AND u.aktiv = 1
                ORDER BY uk.sortierung, uk.name";
        return $this->db->fetchAll($sql, [$mitgliedId]);
    }
    
    // ==================== KATEGORIEN ====================
    
    /**
     * Alle Kategorien abrufen
     */
    public function getKategorien() {
        $sql = "SELECT uk.*, 
                (SELECT COUNT(*) FROM uniformen u WHERE u.kategorie_id = uk.id AND u.aktiv = 1) as anzahl_teile,
                (SELECT COUNT(*) FROM uniformen u WHERE u.kategorie_id = uk.id AND u.aktiv = 1 AND u.mitglied_id IS NOT NULL) as anzahl_ausgegeben
                FROM uniform_kategorien uk 
                WHERE uk.aktiv = 1
                ORDER BY uk.sortierung, uk.name";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Kategorie abrufen
     */
    public function getKategorieById($id) {
        $sql = "SELECT * FROM uniform_kategorien WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Kategorie erstellen
     */
    public function createKategorie($data) {
        $sql = "INSERT INTO uniform_kategorien (name, beschreibung, sortierung) VALUES (?, ?, ?)";
        $this->db->execute($sql, [
            $data['name'],
            $data['beschreibung'] ?? null,
            $data['sortierung'] ?? 100
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Kategorie aktualisieren
     */
    public function updateKategorie($id, $data) {
        $sql = "UPDATE uniform_kategorien SET name = ?, beschreibung = ?, sortierung = ? WHERE id = ?";
        return $this->db->execute($sql, [
            $data['name'],
            $data['beschreibung'] ?? null,
            $data['sortierung'] ?? 100,
            $id
        ]);
    }
    
    /**
     * Kategorie löschen
     */
    public function deleteKategorie($id) {
        // Prüfen ob noch Uniformen in der Kategorie existieren
        $sql = "SELECT COUNT(*) as anzahl FROM uniformen WHERE kategorie_id = ? AND aktiv = 1";
        $result = $this->db->fetchOne($sql, [$id]);
        
        if ($result['anzahl'] > 0) {
            throw new Exception('Kategorie kann nicht gelöscht werden, da noch ' . $result['anzahl'] . ' Uniformteile zugeordnet sind.');
        }
        
        $sql = "UPDATE uniform_kategorien SET aktiv = 0 WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    // ==================== STATISTIK ====================
    
    /**
     * Statistiken abrufen
     */
    public function getStatistik() {
        $stats = [];
        
        // Gesamtanzahl
        $sql = "SELECT COUNT(*) as total FROM uniformen WHERE aktiv = 1";
        $result = $this->db->fetchOne($sql);
        $stats['total'] = $result['total'];
        
        // Ausgegeben
        $sql = "SELECT COUNT(*) as ausgegeben FROM uniformen WHERE mitglied_id IS NOT NULL AND aktiv = 1";
        $result = $this->db->fetchOne($sql);
        $stats['ausgegeben'] = $result['ausgegeben'];
        
        // Verfügbar
        $stats['verfuegbar'] = $stats['total'] - $stats['ausgegeben'];
        
        // Nach Kategorie
        $sql = "SELECT uk.name, COUNT(u.id) as anzahl,
                SUM(CASE WHEN u.mitglied_id IS NOT NULL THEN 1 ELSE 0 END) as ausgegeben
                FROM uniform_kategorien uk 
                LEFT JOIN uniformen u ON uk.id = u.kategorie_id AND u.aktiv = 1
                WHERE uk.aktiv = 1
                GROUP BY uk.id, uk.name
                ORDER BY uk.sortierung";
        $stats['kategorien'] = $this->db->fetchAll($sql);
        
        // Nach Zustand
        $sql = "SELECT zustand, COUNT(*) as anzahl FROM uniformen WHERE aktiv = 1 GROUP BY zustand";
        $stats['zustand'] = $this->db->fetchAll($sql);
        
        // Gesamtwert
        $sql = "SELECT SUM(anschaffungspreis) as gesamtwert FROM uniformen WHERE aktiv = 1";
        $result = $this->db->fetchOne($sql);
        $stats['gesamtwert'] = $result['gesamtwert'] ?? 0;
        
        return $stats;
    }
    
    /**
     * Verfügbare Größen abrufen
     */
    public function getVerfuegbareGroessen() {
        $sql = "SELECT DISTINCT groesse FROM uniformen WHERE aktiv = 1 AND groesse IS NOT NULL ORDER BY groesse";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Inventarnummer generieren
     */
    private function generateInventarNummer($kategorieId) {
        $kategorie = $this->getKategorieById($kategorieId);
        $prefix = strtoupper(substr($kategorie['name'] ?? 'UNI', 0, 3));
        
        $sql = "SELECT MAX(CAST(SUBSTRING(inventar_nummer, 5) AS UNSIGNED)) as max_nr 
                FROM uniformen 
                WHERE inventar_nummer LIKE ?";
        $result = $this->db->fetchOne($sql, [$prefix . '-%']);
        
        $nextNr = ($result['max_nr'] ?? 0) + 1;
        return $prefix . '-' . str_pad($nextNr, 4, '0', STR_PAD_LEFT);
    }
}
