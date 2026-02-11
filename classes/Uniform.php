<?php
// classes/Uniform.php
// Uniformverwaltung - Mitglieder bekommen Kleidungsstücke zugewiesen

class Uniform {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // ==================== KATEGORIEN ====================
    
    /**
     * Alle Kategorien abrufen
     */
    public function getKategorien() {
        $sql = "SELECT * FROM uniform_kategorien WHERE aktiv = 1 ORDER BY sortierung, name";
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
        // Prüfen ob noch Kleidungsstücke in der Kategorie existieren
        $sql = "SELECT COUNT(*) as anzahl FROM uniform_kleidungsstuecke WHERE kategorie_id = ? AND aktiv = 1";
        $result = $this->db->fetchOne($sql, [$id]);
        
        if ($result['anzahl'] > 0) {
            throw new Exception('Kategorie kann nicht gelöscht werden, da noch ' . $result['anzahl'] . ' Kleidungsstücke zugeordnet sind.');
        }
        
        $sql = "UPDATE uniform_kategorien SET aktiv = 0 WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    // ==================== KLEIDUNGSSTÜCKE (Typen) ====================
    
    /**
     * Alle Kleidungsstücke (Typen) abrufen
     */
    public function getKleidungsstuecke($kategorieId = null) {
        $sql = "SELECT k.*, uk.name as kategorie_name 
                FROM uniform_kleidungsstuecke k
                LEFT JOIN uniform_kategorien uk ON k.kategorie_id = uk.id
                WHERE k.aktiv = 1";
        $params = [];
        
        if ($kategorieId) {
            $sql .= " AND k.kategorie_id = ?";
            $params[] = $kategorieId;
        }
        
        $sql .= " ORDER BY uk.sortierung, uk.name, k.sortierung, k.name";
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Kleidungsstück abrufen
     */
    public function getKleidungsstueckById($id) {
        $sql = "SELECT k.*, uk.name as kategorie_name 
                FROM uniform_kleidungsstuecke k
                LEFT JOIN uniform_kategorien uk ON k.kategorie_id = uk.id
                WHERE k.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Kleidungsstück erstellen
     */
    public function createKleidungsstueck($data) {
        $sql = "INSERT INTO uniform_kleidungsstuecke (kategorie_id, name, beschreibung, groessen_verfuegbar, sortierung) 
                VALUES (?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            $data['kategorie_id'],
            $data['name'],
            $data['beschreibung'] ?? null,
            $data['groessen_verfuegbar'] ?? null,
            $data['sortierung'] ?? 100
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Kleidungsstück aktualisieren
     */
    public function updateKleidungsstueck($id, $data) {
        $sql = "UPDATE uniform_kleidungsstuecke SET 
                kategorie_id = ?, name = ?, beschreibung = ?, groessen_verfuegbar = ?, sortierung = ?
                WHERE id = ?";
        return $this->db->execute($sql, [
            $data['kategorie_id'],
            $data['name'],
            $data['beschreibung'] ?? null,
            $data['groessen_verfuegbar'] ?? null,
            $data['sortierung'] ?? 100,
            $id
        ]);
    }
    
    /**
     * Kleidungsstück löschen
     */
    public function deleteKleidungsstueck($id) {
        $sql = "UPDATE uniform_kleidungsstuecke SET aktiv = 0 WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    // ==================== MITGLIEDER-ZUWEISUNGEN ====================
    
    /**
     * Alle aktiven Mitglieder mit Uniform-Status abrufen
     */
    public function getMitgliederMitUniformen() {
        $sql = "SELECT m.id, m.vorname, m.nachname, m.mitgliedsnummer,
                (SELECT COUNT(*) FROM uniform_zuweisungen uz WHERE uz.mitglied_id = m.id) as anzahl_teile
                FROM mitglieder m
                WHERE m.status = 'aktiv'
                ORDER BY m.nachname, m.vorname";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Zuweisungen eines Mitglieds abrufen
     */
    public function getZuweisungenByMitglied($mitgliedId) {
        $sql = "SELECT uz.*, 
                uk.name as kategorie_name,
                ks.name as kleidungsstueck_name,
                ks.beschreibung as kleidungsstueck_beschreibung
                FROM uniform_zuweisungen uz
                JOIN uniform_kleidungsstuecke ks ON uz.kleidungsstueck_id = ks.id
                LEFT JOIN uniform_kategorien uk ON ks.kategorie_id = uk.id
                WHERE uz.mitglied_id = ?
                ORDER BY uk.sortierung, uk.name, ks.sortierung, ks.name";
        return $this->db->fetchAll($sql, [$mitgliedId]);
    }
    
    /**
     * Zuweisung abrufen
     */
    public function getZuweisungById($id) {
        $sql = "SELECT uz.*, 
                m.vorname, m.nachname,
                uk.name as kategorie_name,
                ks.name as kleidungsstueck_name
                FROM uniform_zuweisungen uz
                JOIN mitglieder m ON uz.mitglied_id = m.id
                JOIN uniform_kleidungsstuecke ks ON uz.kleidungsstueck_id = ks.id
                LEFT JOIN uniform_kategorien uk ON ks.kategorie_id = uk.id
                WHERE uz.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Kleidungsstück einem Mitglied zuweisen
     */
    public function zuweisen($mitgliedId, $kleidungsstueckId, $data = []) {
        $sql = "INSERT INTO uniform_zuweisungen 
                (mitglied_id, kleidungsstueck_id, groesse, zustand, ausgabe_datum, bemerkungen)
                VALUES (?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            $mitgliedId,
            $kleidungsstueckId,
            $data['groesse'] ?? null,
            $data['zustand'] ?? 'gut',
            $data['ausgabe_datum'] ?? date('Y-m-d'),
            $data['bemerkungen'] ?? null
        ]);
        return $this->db->lastInsertId();
    }
    
    /**
     * Zuweisung aktualisieren
     */
    public function updateZuweisung($id, $data) {
        $sql = "UPDATE uniform_zuweisungen SET 
                groesse = ?, zustand = ?, bemerkungen = ?
                WHERE id = ?";
        return $this->db->execute($sql, [
            $data['groesse'] ?? null,
            $data['zustand'] ?? 'gut',
            $data['bemerkungen'] ?? null,
            $id
        ]);
    }
    
    /**
     * Zuweisung entfernen (Kleidungsstück zurücknehmen)
     */
    public function zuweisungEntfernen($id) {
        $sql = "DELETE FROM uniform_zuweisungen WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Alle Kleidungsstücke die dem Mitglied noch nicht zugewiesen sind
     */
    public function getVerfuegbareKleidungsstuecke($mitgliedId) {
        $sql = "SELECT k.*, uk.name as kategorie_name 
                FROM uniform_kleidungsstuecke k
                LEFT JOIN uniform_kategorien uk ON k.kategorie_id = uk.id
                WHERE k.aktiv = 1
                AND k.id NOT IN (
                    SELECT kleidungsstueck_id FROM uniform_zuweisungen WHERE mitglied_id = ?
                )
                ORDER BY uk.sortierung, uk.name, k.sortierung, k.name";
        return $this->db->fetchAll($sql, [$mitgliedId]);
    }
    
    // ==================== STATISTIK ====================
    
    /**
     * Statistiken abrufen
     */
    public function getStatistik() {
        $stats = [];
        
        // Anzahl Kategorien
        $sql = "SELECT COUNT(*) as anzahl FROM uniform_kategorien WHERE aktiv = 1";
        $result = $this->db->fetchOne($sql);
        $stats['kategorien'] = $result['anzahl'];
        
        // Anzahl Kleidungsstücke (Typen)
        $sql = "SELECT COUNT(*) as anzahl FROM uniform_kleidungsstuecke WHERE aktiv = 1";
        $result = $this->db->fetchOne($sql);
        $stats['kleidungsstuecke'] = $result['anzahl'];
        
        // Anzahl Zuweisungen
        $sql = "SELECT COUNT(*) as anzahl FROM uniform_zuweisungen";
        $result = $this->db->fetchOne($sql);
        $stats['zuweisungen'] = $result['anzahl'];
        
        // Mitglieder mit kompletter Uniform (alle Pflicht-Teile)
        $sql = "SELECT COUNT(DISTINCT mitglied_id) as anzahl FROM uniform_zuweisungen";
        $result = $this->db->fetchOne($sql);
        $stats['mitglieder_mit_uniform'] = $result['anzahl'];
        
        // Aktive Mitglieder gesamt
        $sql = "SELECT COUNT(*) as anzahl FROM mitglieder WHERE status = 'aktiv'";
        $result = $this->db->fetchOne($sql);
        $stats['mitglieder_aktiv'] = $result['anzahl'];
        
        return $stats;
    }
}
