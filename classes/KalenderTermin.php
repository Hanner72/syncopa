<?php
/**
 * classes/KalenderTermin.php
 * Verwaltet interne Kalender-Termine (NICHT Ausrückungen)
 */

class KalenderTermin {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Alle Termine im Zeitraum laden
     */
    public function getAll($start, $end) {
        $sql = "SELECT kt.*, b.benutzername as erstellt_von_name
                FROM kalender_termine kt
                LEFT JOIN benutzer b ON kt.erstellt_von = b.id
                WHERE start_datum BETWEEN ? AND ?
                ORDER BY start_datum";
        
        return $this->db->fetchAll($sql, [$start, $end]);
    }
    
    /**
     * Einzelnen Termin laden
     */
    public function getById($id) {
        $sql = "SELECT kt.*, b.benutzername as erstellt_von_name
                FROM kalender_termine kt
                LEFT JOIN benutzer b ON kt.erstellt_von = b.id
                WHERE kt.id = ?";
        
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Neuen Termin erstellen
     */
    public function create($data) {
        $sql = "INSERT INTO kalender_termine (
                    titel, beschreibung, typ, start_datum, ende_datum, 
                    ganztaegig, ort, farbe, erstellt_von
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['titel'],
            $data['beschreibung'] ?? null,
            $data['typ'] ?? 'Termin',
            $data['start_datum'],
            $data['ende_datum'] ?? null,
            $data['ganztaegig'] ?? 0,
            $data['ort'] ?? null,
            $data['farbe'] ?? '#6c757d',
            Session::getUserId()
        ];
        
        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }
    
    /**
     * Termin aktualisieren
     */
    public function update($id, $data) {
        $sql = "UPDATE kalender_termine SET 
                titel = ?, beschreibung = ?, typ = ?, 
                start_datum = ?, ende_datum = ?, ganztaegig = ?,
                ort = ?, farbe = ?
                WHERE id = ?";
        
        $params = [
            $data['titel'],
            $data['beschreibung'] ?? null,
            $data['typ'] ?? 'Termin',
            $data['start_datum'],
            $data['ende_datum'] ?? null,
            $data['ganztaegig'] ?? 0,
            $data['ort'] ?? null,
            $data['farbe'] ?? '#6c757d',
            $id
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Termin löschen
     */
    public function delete($id) {
        $sql = "DELETE FROM kalender_termine WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Events für FullCalendar formatieren
     */
    public function getKalenderEvents($start, $end) {
        $termine = $this->getAll($start, $end);
        $events = [];
        
        foreach ($termine as $termin) {
            $event = [
                'id' => 'termin_' . $termin['id'], // Prefix um von Ausrückungen zu unterscheiden
                'title' => $termin['titel'],
                'start' => $termin['start_datum'],
                'allDay' => (bool)$termin['ganztaegig'],
                'backgroundColor' => $termin['farbe'],
                'borderColor' => $termin['farbe'],
                'textColor' => '#ffffff'
            ];
            
            if ($termin['ende_datum']) {
                $event['end'] = $termin['ende_datum'];
            }
            
            // Extended Props für Modal
            $event['extendedProps'] = [
                'typ' => $termin['typ'],
                'beschreibung' => $termin['beschreibung'],
                'ort' => $termin['ort'],
                'erstellt_von' => $termin['erstellt_von_name'],
                'isTermin' => true // Flag für Modal
            ];
            
            $events[] = $event;
        }
        
        return $events;
    }
    
    /**
     * Farbauswahl für Typen
     */
    public static function getTypColors() {
        return [
            'Termin' => '#6c757d',      // Grau
            'Besprechung' => '#0d6efd', // Blau
            'Geburtstag' => '#ffc107',  // Gelb
            'Feiertag' => '#dc3545',    // Rot
            'Reminder' => '#17a2b8',    // Cyan
            'Sonstiges' => '#6f42c1'    // Lila
        ];
    }
}
