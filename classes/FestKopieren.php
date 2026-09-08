<?php
// classes/FestKopieren.php
// Festverwaltung – Vorjahr-/Quell-Fest in ein Ziel-Fest kopieren

class FestKopieren {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Ausgewählte Daten vom Quell- ins Ziel-Fest kopieren
     *
     * @param int   $quellFestId  ID des Quell-Festes
     * @param int   $zielFestId   ID des Ziel-Festes
     * @param array $optionen     ['stationen' => bool, 'einkauefe' => bool, 'mitarbeiter' => bool]
     * @return array              Anzahl kopierter Datensätze pro Bereich
     */
    public function kopieren(int $quellFestId, int $zielFestId, array $optionen): array {
        $ergebnis = [];

        $this->db->beginTransaction();
        try {
            if (!empty($optionen['stationen'])) {
                $ergebnis['stationen'] = $this->kopiereStationen($quellFestId, $zielFestId);
            }
            if (!empty($optionen['mitarbeiter'])) {
                $ergebnis['mitarbeiter'] = $this->kopiereMitarbeiter($quellFestId, $zielFestId);
            }
            if (!empty($optionen['einkauefe'])) {
                $ergebnis['einkauefe'] = $this->kopiereEinkauefe($quellFestId, $zielFestId);
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }

        return $ergebnis;
    }

    private function kopiereStationen(int $von, int $nach): int {
        $stationen = $this->db->fetchAll(
            "SELECT * FROM fest_stationen WHERE fest_id = ? ORDER BY sortierung",
            [$von]
        );
        foreach ($stationen as $s) {
            $this->db->execute(
                "INSERT INTO fest_stationen (fest_id, name, beschreibung, benoetigte_helfer, oeffnung_von, oeffnung_bis, sortierung)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$nach, $s['name'], $s['beschreibung'], $s['benoetigte_helfer'], $s['oeffnung_von'], $s['oeffnung_bis'], $s['sortierung']]
            );
        }
        return count($stationen);
    }

    private function kopiereMitarbeiter(int $von, int $nach): int {
        $mitarbeiter = $this->db->fetchAll(
            "SELECT * FROM fest_mitarbeiter WHERE fest_id = ?",
            [$von]
        );
        foreach ($mitarbeiter as $ma) {
            $this->db->execute(
                "INSERT INTO fest_mitarbeiter (fest_id, mitglied_id, vorname, nachname, telefon, email, funktion, ist_extern, notizen)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$nach, $ma['mitglied_id'], $ma['vorname'], $ma['nachname'], $ma['telefon'], $ma['email'], $ma['funktion'], $ma['ist_extern'], $ma['notizen']]
            );
        }
        return count($mitarbeiter);
    }

    /**
     * Nur Einkäufe mit ist_vorlage=1 kopieren, Status zurück auf 'geplant'
     */
    private function kopiereEinkauefe(int $von, int $nach): int {
        $einkauefe = $this->db->fetchAll(
            "SELECT * FROM fest_einkauefe WHERE fest_id = ? AND ist_vorlage = 1",
            [$von]
        );
        foreach ($einkauefe as $e) {
            $this->db->execute(
                "INSERT INTO fest_einkauefe (fest_id, kategorie_id, bezeichnung, menge, einheit, preis_gesamt, lieferant, status, ist_vorlage, notizen)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'geplant', 1, ?)",
                [$nach, $e['kategorie_id'], $e['bezeichnung'], $e['menge'], $e['einheit'], $e['preis_gesamt'], $e['lieferant'], $e['notizen']]
            );
        }
        return count($einkauefe);
    }
}
