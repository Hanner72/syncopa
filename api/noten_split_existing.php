<?php
/**
 * API: Bereits gespeicherte PDF-Datei nach Stimmen aufteilen.
 * Liest die Datei anhand der datei_id aus der DB und ruft die Split-Logik auf.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
@set_time_limit(120);

try {
    require_once '../config.php';
    require_once '../includes.php';

    Session::start();

    if (!Session::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
        exit;
    }
    if (!Session::checkPermission('noten', 'schreiben')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
        exit;
    }

    $dateiId = isset($_POST['datei_id']) ? (int)$_POST['datei_id'] : 0;
    $notenId = isset($_POST['noten_id']) ? (int)$_POST['noten_id'] : 0;

    if ($dateiId <= 0 || $notenId <= 0) {
        echo json_encode(['success' => false, 'error' => 'datei_id oder noten_id fehlt']);
        exit;
    }

    $db     = Database::getInstance();
    $datei  = $db->fetchOne("SELECT * FROM noten_dateien WHERE id=? AND noten_id=?", [$dateiId, $notenId]);
    if (!$datei) {
        echo json_encode(['success' => false, 'error' => 'Datei nicht gefunden']);
        exit;
    }

    $quellPfad = NOTEN_DIR . DIRECTORY_SEPARATOR . $datei['dateiname'];
    if (!file_exists($quellPfad)) {
        echo json_encode(['success' => false, 'error' => 'Datei nicht auf dem Server gefunden: ' . $datei['dateiname']]);
        exit;
    }

    // FPDF + FPDI laden
    $fpdfFile = __DIR__ . '/../vendor/fpdf/fpdf.php';
    if (!file_exists($fpdfFile)) {
        echo json_encode(['success' => false, 'error' => 'FPDF fehlt (/vendor/fpdf/fpdf.php)']);
        exit;
    }
    require_once $fpdfFile;

    $fpdiAutoload = __DIR__ . '/../vendor/fpdi/autoload.php';
    if (!file_exists($fpdiAutoload)) {
        echo json_encode(['success' => false, 'error' => 'FPDI fehlt (/vendor/fpdi/autoload.php)']);
        exit;
    }
    require_once $fpdiAutoload;

    $notenObj = new Noten();
    $note     = $notenObj->getById($notenId);
    if (!$note) {
        echo json_encode(['success' => false, 'error' => 'Notenstück nicht gefunden']);
        exit;
    }

    // --- Ab hier identische Logik wie noten_split_stimmen.php ---

    $pageCount = fpdiSeitenanzahl($quellPfad);
    if ($pageCount <= 0) {
        echo json_encode(['success' => false, 'error' => 'PDF konnte nicht gelesen werden (0 Seiten)']);
        exit;
    }

    $seitenStimmen = [];
    $istScan       = false;
    $methode       = 'text';

    $ocrApiKey = defined('OCR_SPACE_API_KEY') ? OCR_SPACE_API_KEY : '';

    // Versuch 1: pdftotext
    for ($p = 1; $p <= $pageCount; $p++) {
        $seitenStimmen[$p] = erkenneStimmePerText($quellPfad, $p);
    }

    $erkannte = array_filter($seitenStimmen);

    if (empty($erkannte) && !empty($ocrApiKey)) {
        // Versuch 2: OCR.space API
        $methode = 'ocr_api';
        for ($p = 1; $p <= $pageCount; $p++) {
            $seitenStimmen[$p] = erkenneStimmePerOcrApi($quellPfad, $p, $ocrApiKey);
        }
        $erkannte = array_filter($seitenStimmen);
    }

    if (empty($erkannte)) {
        $istScan = true;
        $methode = 'scan';
    } else {
        $letzte = null;
        foreach ($seitenStimmen as $p => $s) {
            if ($s !== null) { $letzte = $s; }
            else             { $seitenStimmen[$p] = $letzte; }
        }
    }

    // Gruppierung: nur aufeinanderfolgende Seiten zusammenfassen
    $gruppen = [];
    if ($istScan) {
        for ($p = 1; $p <= $pageCount; $p++) {
            $gruppen[] = ['stimme' => null, 'seiten' => [$p]];
        }
    } else {
        $letzteGruppeStimme = null;
        $letzteSeite        = null;
        for ($p = 1; $p <= $pageCount; $p++) {
            $s             = $seitenStimmen[$p] ?? '__unbekannt__';
            $stimmeAnzeige = ($s === '__unbekannt__') ? null : $s;
            $istNeu = ($s !== $letzteGruppeStimme || $letzteSeite === null || $p !== $letzteSeite + 1);
            if ($istNeu) {
                $gruppen[] = ['stimme' => $stimmeAnzeige, 'seiten' => []];
            }
            $gruppen[count($gruppen) - 1]['seiten'][] = $p;
            $letzteGruppeStimme = $s;
            $letzteSeite        = $p;
        }
    }

    $titelSauber = sanitizeFilename($note['titel']);
    $benutzerId  = Session::getUserId();
    $ergebnis    = [];
    $gespeichert = 0;

    foreach ($gruppen as $gruppe) {
        $stimme  = $gruppe['stimme'];
        $seiten  = $gruppe['seiten'];
        $bekannt = ($stimme !== null);

        if ($istScan) {
            $anzeigeNamen = $titelSauber . '_Seite_' . $seiten[0] . '.pdf';
            $beschreibung = 'Seite ' . $seiten[0] . ' (Scan – bitte umbenennen)';
        } elseif ($bekannt) {
            list($instrSauber, $stimmNr) = normalizeStimme($stimme);
            $stimmNr      = naechsteStimmNummer($db, $notenId, $titelSauber, $instrSauber, $stimmNr);
            $anzeigeNamen = $titelSauber . '_' . $instrSauber . '_' . $stimmNr . '.pdf';
            $beschreibung = '[stimme] ' . $stimme;
        } else {
            $anzeigeNamen = $titelSauber . '_Komplett.pdf';
            $beschreibung = '[stimme] Stimme nicht erkannt';
        }

        $uniqueName = $notenId . '_' . uniqid() . '_' . time() . '.pdf';
        $zielPfad   = NOTEN_DIR . DIRECTORY_SEPARATOR . $uniqueName;

        $ok = fpdiExtrahiere($quellPfad, $seiten, $zielPfad);
        if (!$ok) {
            $ergebnis[] = ['name' => $anzeigeNamen, 'fehler' => 'Extraktion fehlgeschlagen'];
            continue;
        }

        $row        = $db->fetchOne("SELECT COALESCE(MAX(sortierung),0)+1 AS next FROM noten_dateien WHERE noten_id=?", [$notenId]);
        $db->execute(
            "INSERT INTO noten_dateien (noten_id, dateiname, original_name, dateityp, dateigroesse, beschreibung, sortierung, hochgeladen_von)
             VALUES (?, ?, ?, 'application/pdf', ?, ?, ?, ?)",
            [$notenId, $uniqueName, $anzeigeNamen, filesize($zielPfad), $beschreibung, $row['next'], $benutzerId]
        );

        $ergebnis[] = ['name' => $anzeigeNamen, 'stimme' => $stimme, 'erkannt' => $bekannt, 'fehler' => null];
        $gespeichert++;
    }

    echo json_encode([
        'success'       => true,
        'gespeichert'   => $gespeichert,
        'stimmen'       => $ergebnis,
        'seiten_gesamt' => $pageCount,
        'ist_scan'      => $istScan,
        'message'       => $gespeichert . ' Stimmen-PDF(s) aus ' . $pageCount . ' Seiten erzeugt',
    ]);

} catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'PDF-Format nicht unterstützt: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fehler: ' . $e->getMessage()]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'PHP-Fehler: ' . $e->getMessage()]);
}

// ============================================================
// HILFSFUNKTIONEN (identisch zu noten_split_stimmen.php)
// ============================================================

function fpdiSeitenanzahl(string $pdfPath): int
{
    try {
        $pdf = new \setasign\Fpdi\Fpdi();
        return $pdf->setSourceFile($pdfPath);
    } catch (Exception $e) { return 0; }
}

function fpdiExtrahiere(string $quellPdf, array $seiten, string $zielPfad): bool
{
    try {
        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->setSourceFile($quellPdf);
        foreach ($seiten as $seitenNr) {
            $tplId = $pdf->importPage((int)$seitenNr);
            $size  = $pdf->getTemplateSize($tplId);
            $pdf->addPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height']);
        }
        $pdf->Output('F', $zielPfad);
        return file_exists($zielPfad) && filesize($zielPfad) > 100;
    } catch (Exception $e) { return false; }
}

function erkenneStimmePerText(string $pdfPath, int $seite): ?string
{
    if (!function_exists('shell_exec')) return null;
    $esc  = escapeshellarg($pdfPath);
    $p    = (int)$seite;
    $text = (string)shell_exec("pdftotext -layout -f $p -l $p -y 0 -H 120 $esc - 2>/dev/null");
    if (empty(trim($text))) {
        $text = (string)shell_exec("pdftotext -layout -f $p -l $p $esc - 2>/dev/null");
        if (!empty($text)) {
            $zeilen = explode("\n", $text);
            $text   = implode("\n", array_slice($zeilen, 0, 15));
        }
    }
    if (empty(trim($text))) return null;
    return matcheStimme($text);
}

function erkenneStimmePerOcrApi(string $pdfPath, int $seite, string $apiKey): ?string
{
    $tmpSeite = tempnam(sys_get_temp_dir(), 'syncopa_ocr_') . '.pdf';
    if (!fpdiExtrahiere($pdfPath, [$seite], $tmpSeite)) return null;
    $pdfData = base64_encode(file_get_contents($tmpSeite));
    @unlink($tmpSeite);
    if (empty($pdfData)) return null;

    $ch = curl_init('https://api.ocr.space/parse/image');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'base64Image'       => 'data:application/pdf;base64,' . $pdfData,
            'language'          => 'ger',
            'isOverlayRequired' => 'false',
            'detectOrientation' => 'true',
            'scale'             => 'true',
            'OCREngine'         => '2',
        ],
        CURLOPT_HTTPHEADER     => ['apikey: ' . $apiKey],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) return null;
    $data = json_decode($response, true);
    if (empty($data['ParsedResults'][0]['ParsedText'])) return null;

    $text   = $data['ParsedResults'][0]['ParsedText'];
    $zeilen = array_slice(explode("\n", $text), 0, 10);
    return matcheStimme(implode("\n", $zeilen));
}

function matcheStimme(string $text): ?string
{
    $instrumente = [
        'Piccolo(?:fl[öu]te)?',
        'Querfl[öu]te[n]?', 'Bassfl[öu]te', 'Fl[öu]te[n]?',
        'Bassklarinette', 'Klarinette[n]?',
        'Englisch(?:es)?\s*Horn', 'Oboe[n]?', 'Fagott[e]?', 'Kontrafagott',
        'Sopransaxophon', 'Altsaxophon', 'Tenorsaxophon', 'Baritonsaxophon', 'Basssaxophon', 'Saxophon[e]?',
        'Fl[üu]gelhorn(?:er)?', 'Cornet(?:te)?', 'Trompete[n]?',
        'Tenorhorn(?:er)?', 'Waldhorn(?:er)?', 'Horn(?:er)?',
        'Bariton[e]?', 'Euphonium',
        'Kontrabassposaune', 'Bassposaune', 'Posaune[n]?',
        'Kontrabasstuba', 'Basstuba', 'Tuba[s]?', 'Es-Bass', 'B-Bass',
        'Schlagzeug', 'Drumset', 'Kleine\s*Trommel', 'Grosse?\s*Trommel',
        'Pauken?', 'Becken', 'Glockenspiel', 'Xylophon', 'Vibraphon', 'Marimba', 'Percussion',
        'Partitur', 'Direktion', 'Score', 'Kontrabass', 'Akkordeon', 'Klavier', 'Orgel', 'Gitarre',
    ];
    foreach ($instrumente as $instr) {
        $pat = '/(?:(\d+)\s*[.\-]\s*)?(' . $instr . ')(?:\s*in\s+[A-Za-z]+)?(?:\s*(?:[IVX]+|(\d+)))?\b/iu';
        if (preg_match($pat, $text, $m)) return trim(preg_replace('/\s+/', ' ', $m[0]));
    }
    return null;
}

function normalizeStimme(string $stimme): array
{
    $nr = 1; $rest = trim($stimme);
    if (preg_match('/^(\d+)\s*[.\-]\s*(.+)/u', $rest, $m)) {
        $nr = (int)$m[1]; $rest = trim($m[2]);
    } elseif (preg_match('/^(.+?)\s+(\d+)$/u', $rest, $m)) {
        $rest = trim($m[1]); $nr = (int)$m[2];
    } elseif (preg_match('/^(.+?)\s+(I{1,3}|IV|VI{0,3}|IX)$/iu', $rest, $m)) {
        $rest = trim($m[1]);
        $nr = ['I'=>1,'II'=>2,'III'=>3,'IV'=>4,'V'=>5,'VI'=>6,'VII'=>7,'VIII'=>8,'IX'=>9][strtoupper($m[2])] ?? 1;
    }
    $rest = preg_replace('/\s+in\s+[A-Za-z]+\s*$/u', '', $rest);
    return [sanitizeFilename(trim($rest)), $nr];
}

function naechsteStimmNummer($db, int $notenId, string $titel, string $instrument, int $gewuenscht): int
{
    $rows   = $db->fetchAll("SELECT original_name FROM noten_dateien WHERE noten_id=?", [$notenId]);
    $belegt = [];
    foreach ($rows as $row) {
        $pat = '/^' . preg_quote($titel, '/') . '_' . preg_quote($instrument, '/') . '_(\d+)\.pdf$/i';
        if (preg_match($pat, $row['original_name'], $m)) $belegt[] = (int)$m[1];
    }
    $nr = $gewuenscht;
    while (in_array($nr, $belegt)) $nr++;
    return $nr;
}

function sanitizeFilename(string $name): string
{
    $map = ['ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ß'=>'ss'];
    $name = strtr($name, $map);
    $name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
    return trim(preg_replace('/_+/', '_', $name), '_');
}
