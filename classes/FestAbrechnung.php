<?php
// classes/FestAbrechnung.php
// Festverwaltung – Abrechnung / Einnahmen & Ausgaben

class FestAbrechnung {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->db->execute("CREATE TABLE IF NOT EXISTS fest_abrechnung_posten (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            fest_id         INT NOT NULL,
            typ             ENUM('einnahme','ausgabe') NOT NULL,
            kategorie       VARCHAR(80) NOT NULL DEFAULT 'sonstiges',
            bezeichnung     VARCHAR(255) NOT NULL,
            betrag          DECIMAL(10,2) NOT NULL DEFAULT 0,
            station_id      INT NULL,
            notizen         TEXT NULL,
            erstellt_von    INT NULL,
            erstellt_am     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (fest_id)   REFERENCES feste(id) ON DELETE CASCADE,
            FOREIGN KEY (station_id) REFERENCES fest_stationen(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /** Alle manuellen Posten eines Festes */
    public function getByFest(int $festId): array {
        $sql = "SELECT p.*, s.name as station_name, b.benutzername as erstellt_von_name
                FROM fest_abrechnung_posten p
                LEFT JOIN fest_stationen s ON p.station_id = s.id
                LEFT JOIN benutzer b ON p.erstellt_von = b.id
                WHERE p.fest_id = ?
                ORDER BY p.typ, p.kategorie, p.bezeichnung";
        return $this->db->fetchAll($sql, [$festId]);
    }

    /** Einkäufe mit Status 'erhalten' und Preis (= Ausgaben) */
    public function getEinkaufAusgaben(int $festId): array {
        $sql = "SELECT e.*, s.name as station_name, k.name as kategorie_name
                FROM fest_einkauefe e
                LEFT JOIN fest_stationen s ON e.station_id = s.id
                LEFT JOIN fest_einkauf_kategorien k ON e.kategorie_id = k.id
                WHERE e.fest_id = ? AND e.status = 'erhalten' AND e.preis_gesamt IS NOT NULL AND e.preis_gesamt > 0
                ORDER BY e.lieferant, e.bezeichnung";
        return $this->db->fetchAll($sql, [$festId]);
    }

    /** Verträge mit Honorar (= Ausgaben) */
    public function getVertragsAusgaben(int $festId): array {
        $sql = "SELECT * FROM fest_vertraege
                WHERE fest_id = ? AND honorar IS NOT NULL AND honorar > 0
                ORDER BY auftritt_datum, band_name";
        return $this->db->fetchAll($sql, [$festId]);
    }

    /** Gesamtübersicht */
    public function getSummary(int $festId): array {
        $posten     = $this->getByFest($festId);
        $einkauf    = $this->getEinkaufAusgaben($festId);
        $vertraege  = $this->getVertragsAusgaben($festId);

        $einnahmen  = 0;
        $ausgaben   = 0;

        foreach ($posten as $p) {
            if ($p['typ'] === 'einnahme') $einnahmen += (float)$p['betrag'];
            else                          $ausgaben   += (float)$p['betrag'];
        }
        foreach ($einkauf   as $e) $ausgaben += (float)$e['preis_gesamt'];
        foreach ($vertraege as $v) $ausgaben += (float)$v['honorar'];

        return [
            'einnahmen'         => $einnahmen,
            'ausgaben'          => $ausgaben,
            'ergebnis'          => $einnahmen - $ausgaben,
            'einkauf_summe'     => array_sum(array_column($einkauf,   'preis_gesamt')),
            'vertrags_summe'    => array_sum(array_column($vertraege, 'honorar')),
            'manuelle_einnahmen'=> array_sum(array_map(fn($p) => $p['typ'] === 'einnahme' ? (float)$p['betrag'] : 0, $posten)),
            'manuelle_ausgaben' => array_sum(array_map(fn($p) => $p['typ'] === 'ausgabe'  ? (float)$p['betrag'] : 0, $posten)),
        ];
    }

    public function getById(int $id): ?array {
        return $this->db->fetchOne("SELECT * FROM fest_abrechnung_posten WHERE id = ?", [$id]) ?: null;
    }

    public function create(array $data): int {
        $this->db->execute(
            "INSERT INTO fest_abrechnung_posten (fest_id, typ, kategorie, bezeichnung, betrag, station_id, notizen, erstellt_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                (int)$data['fest_id'],
                $data['typ'],
                $data['kategorie'] ?: 'sonstiges',
                $data['bezeichnung'],
                (float)$data['betrag'],
                !empty($data['station_id']) ? (int)$data['station_id'] : null,
                $data['notizen'] ?: null,
                $data['erstellt_von'] ?? null,
            ]
        );
        return $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void {
        $this->db->execute(
            "UPDATE fest_abrechnung_posten SET typ=?, kategorie=?, bezeichnung=?, betrag=?, station_id=?, notizen=? WHERE id=?",
            [
                $data['typ'],
                $data['kategorie'] ?: 'sonstiges',
                $data['bezeichnung'],
                (float)$data['betrag'],
                !empty($data['station_id']) ? (int)$data['station_id'] : null,
                $data['notizen'] ?: null,
                $id,
            ]
        );
    }

    public function delete(int $id): void {
        $this->db->execute("DELETE FROM fest_abrechnung_posten WHERE id = ?", [$id]);
    }

    public static function kategorienEinnahmen(): array {
        return [
            'bardurchsatz'  => 'Bardurchsatz / Kasseneinnahmen',
            'eintritt'      => 'Eintrittsgelder',
            'sponsoring'    => 'Sponsoring',
            'foerderung'    => 'Förderungen / Zuschüsse',
            'sonstiges'     => 'Sonstiges',
        ];
    }

    public static function kategorienAusgaben(): array {
        return [
            'personal'      => 'Personal / Helferkosten',
            'technik'       => 'Technik / Equipment',
            'werbung'       => 'Werbung / Marketing',
            'gema'          => 'GEMA / AKM',
            'versicherung'  => 'Versicherung',
            'infrastruktur' => 'Infrastruktur / Auf- u. Abbau',
            'sonstiges'     => 'Sonstiges',
        ];
    }
}
