<?php
/**
 * ICS/iCal Helper Klasse für Kalender-Export
 * Erstellt RFC 5545 konforme iCalendar Dateien
 */
class ICalendar {
    private $events = [];
    private $timezone = 'Europe/Vienna';
    private $calendarName = 'Musikverein Ausrückungen';
    private $calendarDescription = 'Alle Ausrückungen und Termine des Musikvereins';
    private $productId = '-//Musikverein Verwaltung//Ausrueckungen//DE';
    
    /**
     * Fügt ein Event zum Kalender hinzu
     * 
     * @param int $id Eindeutige ID
     * @param string $summary Titel
     * @param DateTime $start Start-Zeit
     * @param DateTime $end End-Zeit
     * @param string $description Beschreibung
     * @param string $location Ort
     * @param DateTime $lastModified Letzte Änderung
     * @param string $status Status (CONFIRMED, TENTATIVE, CANCELLED)
     */
    public function addEvent($id, $summary, $start, $end = null, $description = '', $location = '', $lastModified = null, $status = 'CONFIRMED') {
        if (!$end) {
            // Wenn keine Endzeit, 2 Stunden Standarddauer
            $end = clone $start;
            $end->modify('+2 hours');
        }
        
        if (!$lastModified) {
            $lastModified = new DateTime();
        }
        
        $this->events[] = [
            'id' => $id,
            'summary' => $summary,
            'start' => $start,
            'end' => $end,
            'description' => $description,
            'location' => $location,
            'lastModified' => $lastModified,
            'status' => $status,
            'created' => $lastModified
        ];
    }
    
    /**
     * Generiert iCal-Format DateTime
     */
    private function formatDateTime($dt) {
        return $dt->format('Ymd\THis\Z');
    }
    
    /**
     * Escaped Text für iCal
     */
    private function escapeString($string) {
        $string = str_replace('\\', '\\\\', $string);
        $string = str_replace(',', '\\,', $string);
        $string = str_replace(';', '\\;', $string);
        $string = str_replace("\n", '\\n', $string);
        return $string;
    }
    
    /**
     * Generiert eindeutige UID für Event
     */
    private function generateUID($id) {
        return "ausrueckung-{$id}@musikverein.local";
    }
    
    /**
     * Generiert den kompletten iCal Content
     */
    public function render() {
        $output = "BEGIN:VCALENDAR\r\n";
        $output .= "VERSION:2.0\r\n";
        $output .= "PRODID:{$this->escapeString($this->productId)}\r\n";
        $output .= "CALSCALE:GREGORIAN\r\n";
        $output .= "METHOD:PUBLISH\r\n";
        $output .= "X-WR-CALNAME:{$this->escapeString($this->calendarName)}\r\n";
        $output .= "X-WR-CALDESC:{$this->escapeString($this->calendarDescription)}\r\n";
        $output .= "X-WR-TIMEZONE:{$this->timezone}\r\n";
        $output .= "REFRESH-INTERVAL;VALUE=DURATION:PT1H\r\n"; // Aktualisierung alle Stunde
        $output .= "X-PUBLISHED-TTL:PT1H\r\n";
        
        // Timezone Definition
        $output .= "BEGIN:VTIMEZONE\r\n";
        $output .= "TZID:Europe/Vienna\r\n";
        $output .= "BEGIN:DAYLIGHT\r\n";
        $output .= "TZOFFSETFROM:+0100\r\n";
        $output .= "TZOFFSETTO:+0200\r\n";
        $output .= "TZNAME:CEST\r\n";
        $output .= "DTSTART:19700329T020000\r\n";
        $output .= "RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\n";
        $output .= "END:DAYLIGHT\r\n";
        $output .= "BEGIN:STANDARD\r\n";
        $output .= "TZOFFSETFROM:+0200\r\n";
        $output .= "TZOFFSETTO:+0100\r\n";
        $output .= "TZNAME:CET\r\n";
        $output .= "DTSTART:19701025T030000\r\n";
        $output .= "RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\n";
        $output .= "END:STANDARD\r\n";
        $output .= "END:VTIMEZONE\r\n";
        
        // Events
        foreach ($this->events as $event) {
            $output .= "BEGIN:VEVENT\r\n";
            $output .= "UID:{$this->generateUID($event['id'])}\r\n";
            $output .= "DTSTAMP:{$this->formatDateTime(new DateTime())}\r\n";
            $output .= "DTSTART;TZID={$this->timezone}:{$event['start']->format('Ymd\THis')}\r\n";
            $output .= "DTEND;TZID={$this->timezone}:{$event['end']->format('Ymd\THis')}\r\n";
            $output .= "SUMMARY:{$this->escapeString($event['summary'])}\r\n";
            
            if (!empty($event['description'])) {
                $output .= "DESCRIPTION:{$this->escapeString($event['description'])}\r\n";
            }
            
            if (!empty($event['location'])) {
                $output .= "LOCATION:{$this->escapeString($event['location'])}\r\n";
            }
            
            $output .= "STATUS:{$event['status']}\r\n";
            $output .= "SEQUENCE:0\r\n";
            $output .= "CREATED:{$this->formatDateTime($event['created'])}\r\n";
            $output .= "LAST-MODIFIED:{$this->formatDateTime($event['lastModified'])}\r\n";
            $output .= "END:VEVENT\r\n";
        }
        
        $output .= "END:VCALENDAR\r\n";
        
        return $output;
    }
    
    /**
     * Sendet iCal als Download
     */
    public function download($filename = 'kalender.ics') {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo $this->render();
    }
    
    /**
     * Gibt iCal als String zurück (für Abonnement-URL)
     */
    public function toString() {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Cache-Control: max-age=3600'); // 1 Stunde Cache
        
        echo $this->render();
    }
}
