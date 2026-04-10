<?php
// classes/FestVertrag.php
// Festverwaltung – Verträge mit Bands/Gruppen

class FestVertrag {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByFest(int $festId): array {
        $sql = "SELECT v.*, b.benutzername as erstellt_von_name
                FROM fest_vertraege v
                LEFT JOIN benutzer b ON v.erstellt_von = b.id
                WHERE v.fest_id = ?
                ORDER BY v.auftritt_datum, v.auftritt_zeit, v.band_name";
        return $this->db->fetchAll($sql, [$festId]);
    }

    public function getById(int $id) {
        return $this->db->fetchOne("SELECT * FROM fest_vertraege WHERE id = ?", [$id]);
    }

    public function create(array $data): int {
        $sql = "INSERT INTO fest_vertraege (fest_id, band_name, vertrags_datum, auftritt_datum, auftritt_zeit, honorar, zahlungsstatus, zahlungsdatum, dokument_pfad, dokument_name, notizen, erstellt_von)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $this->db->execute($sql, [
            (int)$data['fest_id'],
            $data['band_name'],
            $data['vertrags_datum'] ?: null,
            $data['auftritt_datum'] ?: null,
            $data['auftritt_zeit'] ?: null,
            !empty($data['honorar']) ? (float)$data['honorar'] : null,
            $data['zahlungsstatus'] ?? 'offen',
            $data['zahlungsdatum'] ?: null,
            $data['dokument_pfad'] ?? null,
            $data['dokument_name'] ?? null,
            $data['notizen'] ?: null,
            $data['erstellt_von'] ?? null
        ]);
        return $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE fest_vertraege SET band_name=?, vertrags_datum=?, auftritt_datum=?, auftritt_zeit=?, honorar=?, zahlungsstatus=?, zahlungsdatum=?, notizen=?
                WHERE id=?";
        return $this->db->execute($sql, [
            $data['band_name'],
            $data['vertrags_datum'] ?: null,
            $data['auftritt_datum'] ?: null,
            $data['auftritt_zeit'] ?: null,
            !empty($data['honorar']) ? (float)$data['honorar'] : null,
            $data['zahlungsstatus'] ?? 'offen',
            $data['zahlungsdatum'] ?: null,
            $data['notizen'] ?: null,
            $id
        ]);
    }

    /**
     * Dokument-Pfad eines Vertrags aktualisieren
     */
    public function updateDokument(int $id, string $pfad, string $name): bool {
        return $this->db->execute(
            "UPDATE fest_vertraege SET dokument_pfad=?, dokument_name=? WHERE id=?",
            [$pfad, $name, $id]
        );
    }

    public function delete(int $id): bool {
        $vertrag = $this->getById($id);
        if ($vertrag && !empty($vertrag['dokument_pfad']) && file_exists($vertrag['dokument_pfad'])) {
            @unlink($vertrag['dokument_pfad']);
        }
        return $this->db->execute("DELETE FROM fest_vertraege WHERE id = ?", [$id]);
    }

    /**
     * PDF-Datei hochladen und im richtigen Verzeichnis speichern
     * Gibt ['pfad' => ..., 'name' => ...] zurück oder wirft Exception
     */
    public function handleUpload(array $file, int $festId): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload-Fehler (Code ' . $file['error'] . ')');
        }
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            throw new Exception('Datei zu groß (max. ' . (MAX_UPLOAD_SIZE / 1048576) . ' MB)');
        }

        $erlaubteTypen = ['application/pdf', 'application/x-pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $erlaubteTypen)) {
            throw new Exception('Nur PDF-Dateien erlaubt');
        }

        $dir = FEST_VERTRAEGE_DIR . DIRECTORY_SEPARATOR . 'fest' . $festId;
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $dateiname = time() . '_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME)) . '.' . $ext;
        $zielPfad  = $dir . DIRECTORY_SEPARATOR . $dateiname;

        if (!move_uploaded_file($file['tmp_name'], $zielPfad)) {
            throw new Exception('Datei konnte nicht gespeichert werden');
        }

        return ['pfad' => $zielPfad, 'name' => $file['name']];
    }

    /**
     * Gesamthonorar-Summen pro Zahlungsstatus
     */
    public function getHonorarSummen(int $festId): array {
        $sql = "SELECT zahlungsstatus, COALESCE(SUM(honorar), 0) as summe, COUNT(*) as anzahl
                FROM fest_vertraege WHERE fest_id = ? GROUP BY zahlungsstatus";
        $rows  = $this->db->fetchAll($sql, [$festId]);
        $result = ['offen' => 0, 'teilweise' => 0, 'bezahlt' => 0, 'storniert' => 0, 'gesamt' => 0];
        foreach ($rows as $row) {
            $result[$row['zahlungsstatus']] = (float)$row['summe'];
            $result['gesamt'] += (float)$row['summe'];
        }
        return $result;
    }
}
