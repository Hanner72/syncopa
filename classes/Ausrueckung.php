<?php
// classes/Ausrueckung.php

class Ausrueckung {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll($filter = []) {
        $where = [];
        $params = [];
        
        if (!empty($filter['typ'])) {
            $where[] = "typ = ?";
            $params[] = $filter['typ'];
        }
        
        if (!empty($filter['von_datum'])) {
            $where[] = "start_datum >= ?";
            $params[] = $filter['von_datum'];
        }
        
        if (!empty($filter['bis_datum'])) {
            $where[] = "start_datum <= ?";
            $params[] = $filter['bis_datum'];
        }
        
        if (!empty($filter['status'])) {
            $where[] = "status = ?";
            $params[] = $filter['status'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT a.*, b.benutzername as erstellt_von_name,
                (SELECT COUNT(*) FROM anwesenheit WHERE ausrueckung_id = a.id AND status = 'zugesagt') as zugesagt,
                (SELECT COUNT(*) FROM anwesenheit WHERE ausrueckung_id = a.id AND status = 'abgesagt') as abgesagt
                FROM ausrueckungen a
                LEFT JOIN benutzer b ON a.erstellt_von = b.id
                {$whereClause}
                ORDER BY a.start_datum DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getById($id) {
        $sql = "SELECT a.*, b.benutzername as erstellt_von_name
                FROM ausrueckungen a
                LEFT JOIN benutzer b ON a.erstellt_von = b.id
                WHERE a.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    public function getKalenderEvents($start, $end) {
        $sql = "SELECT id, titel, start_datum, ende_datum, 
                typ, status, ganztaegig, ort, beschreibung, treffpunkt, 
                treffpunkt_zeit, uniform, notizen, adresse
                FROM ausrueckungen 
                WHERE start_datum BETWEEN ? AND ?
                ORDER BY start_datum";
        
        $ausrueckungen = $this->db->fetchAll($sql, [$start, $end]);
        
        // Formatiere für FullCalendar
        $events = [];
        foreach ($ausrueckungen as $ausrueckung) {
            $event = [
                'id' => $ausrueckung['id'],
                'title' => $ausrueckung['titel'],
                'start' => $ausrueckung['start_datum'],
                'allDay' => (bool)$ausrueckung['ganztaegig']
            ];
            
            // Endzeit wenn vorhanden
            if ($ausrueckung['ende_datum']) {
                $event['end'] = $ausrueckung['ende_datum'];
            }
            
            // Farbe je nach Typ
            $event['backgroundColor'] = $this->getEventColor($ausrueckung['typ']);
            $event['borderColor'] = $event['backgroundColor'];
            
            // Status-Badge (abgesagt = rot)
            if ($ausrueckung['status'] === 'abgesagt') {
                $event['backgroundColor'] = '#dc3545';
                $event['borderColor'] = '#dc3545';
            }
            
            // Extended Props für Modal
            $event['extendedProps'] = [
                'typ' => $ausrueckung['typ'],
                'beschreibung' => $ausrueckung['beschreibung'],
                'ort' => $ausrueckung['ort'],
                'adresse' => $ausrueckung['adresse'],
                'treffpunkt' => $ausrueckung['treffpunkt'],
                'treffpunkt_zeit' => $ausrueckung['treffpunkt_zeit'],
                'uniform' => (bool)$ausrueckung['uniform'],
                'notizen' => $ausrueckung['notizen'],
                'status' => $ausrueckung['status']
            ];
            
            $events[] = $event;
        }
        
        return $events;
    }
    
    public function create($data) {
        $sql = "INSERT INTO ausrueckungen (
                    titel, beschreibung, typ, start_datum, ende_datum, ganztaegig,
                    ort, adresse, treffpunkt, treffpunkt_zeit, uniform, notizen,
                    status, erstellt_von
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['titel'],
            $data['beschreibung'] ?? null,
            $data['typ'],
            $data['start_datum'],
            $data['ende_datum'] ?? null,
            $data['ganztaegig'] ?? 0,
            $data['ort'] ?? null,
            $data['adresse'] ?? null,
            $data['treffpunkt'] ?? null,
            $data['treffpunkt_zeit'] ?? null,
            $data['uniform'] ?? 1,
            $data['notizen'] ?? null,
            $data['status'] ?? 'geplant',
            Session::getUserId()
        ];
        
        $this->db->execute($sql, $params);
        $id = $this->db->lastInsertId();
        
        // Google Calendar Event erstellen
        if (GOOGLE_CALENDAR_ENABLED && !empty($data['google_sync'])) {
            $this->syncToGoogleCalendar($id);
        }
        
        // Automatisch alle aktiven Mitglieder hinzufügen
        $this->addAllActiveMembers($id);
        
        return $id;
    }
    
    public function update($id, $data) {
        $sql = "UPDATE ausrueckungen SET 
                titel = ?, beschreibung = ?, typ = ?, start_datum = ?, ende_datum = ?, ganztaegig = ?,
                ort = ?, adresse = ?, treffpunkt = ?, treffpunkt_zeit = ?, uniform = ?, notizen = ?,
                status = ?
                WHERE id = ?";
        
        $params = [
            $data['titel'],
            $data['beschreibung'] ?? null,
            $data['typ'],
            $data['start_datum'],
            $data['ende_datum'] ?? null,
            $data['ganztaegig'] ?? 0,
            $data['ort'] ?? null,
            $data['adresse'] ?? null,
            $data['treffpunkt'] ?? null,
            $data['treffpunkt_zeit'] ?? null,
            $data['uniform'] ?? 1,
            $data['notizen'] ?? null,
            $data['status'] ?? 'geplant',
            $id
        ];
        
        $result = $this->db->execute($sql, $params);
        
        // Google Calendar Event aktualisieren
        if (GOOGLE_CALENDAR_ENABLED && !empty($data['google_sync'])) {
            $this->syncToGoogleCalendar($id);
        }
        
        return $result;
    }
    
    public function delete($id) {
        // Google Calendar Event löschen
        if (GOOGLE_CALENDAR_ENABLED) {
            $this->deleteFromGoogleCalendar($id);
        }
        
        $sql = "DELETE FROM ausrueckungen WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    public function getAnwesenheit($ausrueckungId) {
        $sql = "SELECT a.*, m.vorname, m.nachname, m.mitgliedsnummer
                FROM anwesenheit a
                JOIN mitglieder m ON a.mitglied_id = m.id
                WHERE a.ausrueckung_id = ?
                ORDER BY m.nachname, m.vorname";
        return $this->db->fetchAll($sql, [$ausrueckungId]);
    }
    
    public function setAnwesenheit($ausrueckungId, $mitgliedId, $status, $grund = null) {
        $sql = "INSERT INTO anwesenheit (ausrueckung_id, mitglied_id, status, grund, gemeldet_am)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE status = ?, grund = ?, gemeldet_am = NOW()";
        return $this->db->execute($sql, [$ausrueckungId, $mitgliedId, $status, $grund, $status, $grund]);
    }
    
    public function getNoten($ausrueckungId) {
        $sql = "SELECT an.*, n.titel, n.komponist, n.schwierigkeitsgrad
                FROM ausrueckung_noten an
                JOIN noten n ON an.noten_id = n.id
                WHERE an.ausrueckung_id = ?
                ORDER BY an.reihenfolge";
        return $this->db->fetchAll($sql, [$ausrueckungId]);
    }
    
    public function addNote($ausrueckungId, $notenId, $reihenfolge = 0) {
        $sql = "INSERT INTO ausrueckung_noten (ausrueckung_id, noten_id, reihenfolge)
                VALUES (?, ?, ?)";
        return $this->db->execute($sql, [$ausrueckungId, $notenId, $reihenfolge]);
    }
    
    public function removeNote($id) {
        $sql = "DELETE FROM ausrueckung_noten WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    private function addAllActiveMembers($ausrueckungId) {
        $sql = "INSERT INTO anwesenheit (ausrueckung_id, mitglied_id, status)
                SELECT ?, id, 'keine_antwort'
                FROM mitglieder
                WHERE status = 'aktiv'";
        return $this->db->execute($sql, [$ausrueckungId]);
    }
    
    private function getEventColor($typ) {
        $colors = [
            'Probe' => '#6c757d',
            'Konzert' => '#0d6efd',
            'Ausrückung' => '#198754',
            'Fest' => '#ffc107',
            'Wertung' => '#dc3545',
            'Sonstiges' => '#6c757d'
        ];
        return $colors[$typ] ?? '#6c757d';
    }
    
    public function syncToGoogleCalendar($id) {
        if (!GOOGLE_CALENDAR_ENABLED) {
            return false;
        }
        
        $event = $this->getById($id);
        if (!$event) {
            return false;
        }
        
        // Hier würde die Google Calendar API Integration erfolgen
        // Beispiel-Code (benötigt google-api-php-client):
        /*
        require_once 'vendor/autoload.php';
        
        $client = new Google_Client();
        $client->setApplicationName('Musikverein Verwaltung');
        $client->setScopes(Google_Service_Calendar::CALENDAR);
        $client->setAuthConfig('credentials.json');
        
        $service = new Google_Service_Calendar($client);
        
        $calendarEvent = new Google_Service_Calendar_Event([
            'summary' => $event['titel'],
            'location' => $event['ort'],
            'description' => $event['beschreibung'],
            'start' => [
                'dateTime' => $event['start_datum'],
                'timeZone' => 'Europe/Vienna',
            ],
            'end' => [
                'dateTime' => $event['ende_datum'] ?? $event['start_datum'],
                'timeZone' => 'Europe/Vienna',
            ],
        ]);
        
        if ($event['google_calendar_id']) {
            // Update existing event
            $calendarEvent = $service->events->update(GOOGLE_CALENDAR_ID, $event['google_calendar_id'], $calendarEvent);
        } else {
            // Create new event
            $calendarEvent = $service->events->insert(GOOGLE_CALENDAR_ID, $calendarEvent);
            
            // Speichere Google Calendar Event ID
            $sql = "UPDATE ausrueckungen SET google_calendar_id = ? WHERE id = ?";
            $this->db->execute($sql, [$calendarEvent->getId(), $id]);
        }
        */
        
        return true;
    }
    
    public function deleteFromGoogleCalendar($id) {
        if (!GOOGLE_CALENDAR_ENABLED) {
            return false;
        }
        
        $event = $this->getById($id);
        if (!$event || !$event['google_calendar_id']) {
            return false;
        }
        
        // Hier würde das Löschen aus Google Calendar erfolgen
        /*
        $service->events->delete(GOOGLE_CALENDAR_ID, $event['google_calendar_id']);
        */
        
        return true;
    }
    
    public function getUpcoming($limit = 5) {
        $sql = "SELECT * FROM ausrueckungen 
                WHERE start_datum >= CURDATE() AND status != 'abgesagt'
                ORDER BY start_datum 
                LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
}
