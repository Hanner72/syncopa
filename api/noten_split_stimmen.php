<?php
/**
 * API: Gesamtnoten-PDF hochladen und nach Stimmen aufteilen.
 *
 * Verwendet FPDI (pure PHP) zum Aufteilen.
 * Stimmen-Erkennung: pdftotext für Text-PDFs, Nummerierung für Scans.
 *
 * VORAUSSETZUNG: FPDI-Dateien unter /vendor/fpdi/ ablegen.
 * Download: https://github.com/Setasign/FPDI/releases → "Source code (zip)"
 * Entpacken → Ordner "src" → umbenennen in "fpdi" → hochladen nach /vendor/fpdi/
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

    // FPDF laden (Basis-Klasse für FPDI) – http://www.fpdf.org/
    $fpdfFile = __DIR__ . '/../vendor/fpdf/fpdf.php';
    if (!file_exists($fpdfFile)) {
        echo json_encode(['success' => false, 'error' =>
            'FPDF fehlt. Bitte herunterladen: http://www.fpdf.org/ → Download → fpdf182.zip entpacken'
            . ' → fpdf.php hochladen nach: /vendor/fpdf/fpdf.php']);
        exit;
    }
    require_once $fpdfFile;

    // FPDI laden – https://github.com/Setasign/FPDI/releases
    $fpdiAutoload = __DIR__ . '/../vendor/fpdi/autoload.php';
    if (!file_exists($fpdiAutoload)) {
        echo json_encode(['success' => false, 'error' =>
            'FPDI fehlt. Bitte herunterladen: https://github.com/Setasign/FPDI/releases'
            . ' → Source code (zip) → Ordner "src" umbenennen in "fpdi"'
            . ' → hochladen nach: /vendor/fpdi/']);
        exit;
    }
    require_once $fpdiAutoload;

    if (!class_exists('setasign\Fpdi\Fpdi')) {
        echo json_encode(['success' => false, 'error' => 'FPDI-Klasse nicht gefunden. Ist die Ordnerstruktur korrekt?']);
        exit;
    }

    $notenId = isset($_POST['noten_id']) ? (int)$_POST['noten_id'] : 0;
    if ($notenId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Noten-ID fehlt']);
        exit;
    }

    $notenObj = new Noten();
    $note     = $notenObj->getById($notenId);
    if (!$note) {
        echo json_encode(['success' => false, 'error' => 'Notenstück nicht gefunden']);
        exit;
    }

    // Upload prüfen
    $fehlerTexte = [
        UPLOAD_ERR_INI_SIZE   => 'Datei zu groß (upload_max_filesize in php.ini erhöhen)',
        UPLOAD_ERR_PARTIAL    => 'Datei nur teilweise übertragen',
        UPLOAD_ERR_NO_FILE    => 'Keine Datei übertragen',
        UPLOAD_ERR_NO_TMP_DIR => 'Kein temporäres Verzeichnis',
        UPLOAD_ERR_CANT_WRITE => 'Schreibfehler',
    ];
    if (empty($_FILES['datei'])) {
        echo json_encode(['success' => false, 'error' => 'Kein Upload-Feld "datei"']);
        exit;
    }
    if ($_FILES['datei']['error'] !== UPLOAD_ERR_OK) {
        $code = $_FILES['datei']['error'];
        echo json_encode(['success' => false, 'error' => $fehlerTexte[$code] ?? 'Upload-Fehler ' . $code]);
        exit;
    }

    $file  = $_FILES['datei'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if ($finfo->file($file['tmp_name']) !== 'application/pdf') {
        echo json_encode(['success' => false, 'error' => 'Nur PDF-Dateien erlaubt']);
        exit;
    }
    if ($file['size'] > 52428800) { // 50 MB
        echo json_encode(['success' => false, 'error' => 'Datei zu groß (max. 50 MB)']);
        exit;
    }

    if (!is_dir(NOTEN_DIR)) mkdir(NOTEN_DIR, 0755, true);

    $tmpPdf = tempnam(sys_get_temp_dir(), 'syncopa_') . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $tmpPdf)) {
        echo json_encode(['success' => false, 'error' => 'Datei konnte nicht gespeichert werden']);
        exit;
    }

    // ----------------------------------------------------------------
    // Seitenanzahl ermitteln
    // ----------------------------------------------------------------
    $pageCount = fpdiSeitenanzahl($tmpPdf);
    if ($pageCount <= 0) {
        @unlink($tmpPdf);
        echo json_encode(['success' => false, 'error' => 'PDF konnte nicht gelesen werden (0 Seiten erkannt)']);
        exit;
    }

    // ----------------------------------------------------------------
    // Stimmen erkennen: pdftotext → OCR.space API → Seiten-Nummerierung
    // ----------------------------------------------------------------
    $seitenStimmen = [];
    $istScan       = false;
    $methode       = 'text';

    // ---------------------------------------------------------------
    // OCR-KONFIGURATION: API-Key hier eintragen ODER in config.php:
    //   define('OCR_SPACE_API_KEY', 'hireapi123...');
    //
    // Kostenlosen Key holen (25.000 Seiten/Monat gratis):
    //   https://ocr.space/ocrapi → "Get API Key FREE"
    // ---------------------------------------------------------------
    $ocrApiKey = defined('OCR_SPACE_API_KEY') ? OCR_SPACE_API_KEY : '';
    // Alternativ direkt hier eintragen:
    // $ocrApiKey = 'hireapi123...';

    // Versuch 1: pdftotext (Text-PDFs)
    for ($p = 1; $p <= $pageCount; $p++) {
        $seitenStimmen[$p] = erkenneStimmePerText($tmpPdf, $p);
    }

    $erkannte = array_filter($seitenStimmen);

    if (empty($erkannte) && !empty($ocrApiKey)) {
        // Versuch 2: OCR.space API (Scans)
        $methode = 'ocr_api';
        for ($p = 1; $p <= $pageCount; $p++) {
            $seitenStimmen[$p] = erkenneStimmePerOcrApi($tmpPdf, $p, $ocrApiKey);
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

    // ----------------------------------------------------------------
    // Seiten gruppieren: nur AUFEINANDERFOLGENDE Seiten mit gleicher Stimme
    // zusammenfassen – damit 1.Tenorhorn (Seite 6) und 2.Tenorhorn (Seite 16)
    // nicht in eine Datei landen.
    // ----------------------------------------------------------------
    $gruppen = [];
    if ($istScan) {
        // Scan: jede Seite = eigene Gruppe
        for ($p = 1; $p <= $pageCount; $p++) {
            $gruppen[] = ['stimme' => null, 'seiten' => [$p]];
        }
    } else {
        $letzteGruppeStimme = null;
        $letzteSeite        = null;
        for ($p = 1; $p <= $pageCount; $p++) {
            $s             = $seitenStimmen[$p] ?? '__unbekannt__';
            $stimmeAnzeige = ($s === '__unbekannt__') ? null : $s;

            // Neue Gruppe wenn Stimme wechselt ODER Seiten nicht aufeinanderfolgend
            $istNeu = ($s !== $letzteGruppeStimme || $letzteSeite === null || $p !== $letzteSeite + 1);

            if ($istNeu) {
                $gruppen[] = ['stimme' => $stimmeAnzeige, 'seiten' => []];
            }

            $gruppen[count($gruppen) - 1]['seiten'][] = $p;
            $letzteGruppeStimme = $s;
            $letzteSeite        = $p;
        }
    }

    // ----------------------------------------------------------------
    // Pro Gruppe ein PDF erzeugen
    // ----------------------------------------------------------------
    $titelSauber = sanitizeFilename($note['titel']);
    $benutzerId  = Session::getUserId();
    $db          = Database::getInstance();
    $ergebnis    = [];
    $gespeichert = 0;

    foreach ($gruppen as $gruppe) {
        $stimme  = $gruppe['stimme'];
        $seiten  = $gruppe['seiten'];
        $bekannt = ($stimme !== null);

        if ($istScan) {
            // Scan: Seite_1.pdf, Seite_2.pdf ...
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

        $ok = fpdiExtrahiere($tmpPdf, $seiten, $zielPfad);
        if (!$ok) {
            $ergebnis[] = [
                'name'    => $anzeigeNamen,
                'stimme'  => $stimme,
                'erkannt' => $bekannt,
                'seiten'  => $seiten,
                'fehler'  => 'Extraktion fehlgeschlagen',
            ];
            continue;
        }

        $row        = $db->fetchOne("SELECT COALESCE(MAX(sortierung),0)+1 AS next FROM noten_dateien WHERE noten_id=?", [$notenId]);
        $sortierung = $row['next'];
        $db->execute(
            "INSERT INTO noten_dateien (noten_id, dateiname, original_name, dateityp, dateigroesse, beschreibung, sortierung, hochgeladen_von)
             VALUES (?, ?, ?, 'application/pdf', ?, ?, ?, ?)",
            [$notenId, $uniqueName, $anzeigeNamen, filesize($zielPfad), $beschreibung, $sortierung, $benutzerId]
        );

        $ergebnis[] = [
            'name'    => $anzeigeNamen,
            'stimme'  => $stimme,
            'erkannt' => $bekannt,
            'seiten'  => $seiten,
            'fehler'  => null,
        ];
        $gespeichert++;
    }

    @unlink($tmpPdf);

    $hinweis = $istScan
        ? 'Hinweis: Das PDF ist ein Scan – Stimmen konnten nicht erkannt werden. Bitte die Dateien unten umbenennen.'
        : null;

    echo json_encode([
        'success'       => true,
        'gespeichert'   => $gespeichert,
        'stimmen'       => $ergebnis,
        'seiten_gesamt' => $pageCount,
        'ist_scan'      => $istScan,
        'hinweis'       => $hinweis,
        'message'       => $gespeichert . ' PDF(s) aus ' . $pageCount . ' Seiten erzeugt',
        'methode'       => $methode,
    ]);

} catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'PDF-Format nicht unterstützt (verschlüsselt oder beschädigt): ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fehler: ' . $e->getMessage()]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'PHP-Fehler: ' . $e->getMessage()]);
}

// ============================================================
// HILFSFUNKTIONEN
// ============================================================

/**
 * Seitenanzahl via FPDI ermitteln.
 */
function fpdiSeitenanzahl(string $pdfPath): int
{
    try {
        $pdf = new \setasign\Fpdi\Fpdi();
        return $pdf->setSourceFile($pdfPath);
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Seiten mit FPDI in neue Datei extrahieren.
 */
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
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Stimmenbezeichnung per pdftotext erkennen (nur Text-PDFs).
 */
/**
 * Stimme per OCR.space API erkennen.
 * Sendet die PDF-Seite direkt als base64 an die API.
 * API-Key: https://ocr.space/ocrapi (kostenlos)
 */
function erkenneStimmePerOcrApi(string $pdfPath, int $seite, string $apiKey): ?string
{
    // Einzelne Seite aus dem PDF extrahieren (via FPDI)
    $tmpSeite = tempnam(sys_get_temp_dir(), 'syncopa_ocr_') . '.pdf';
    if (!fpdiExtrahiere($pdfPath, [$seite], $tmpSeite)) {
        return null;
    }

    $pdfData   = base64_encode(file_get_contents($tmpSeite));
    @unlink($tmpSeite);

    if (empty($pdfData)) return null;

    // OCR.space API aufrufen
    $postData = [
        'base64Image' => 'data:application/pdf;base64,' . $pdfData,
        'language'    => 'ger',        // Deutsch (erkennt auch Englisch)
        'isOverlayRequired' => 'false',
        'detectOrientation' => 'true',
        'scale'       => 'true',
        'OCREngine'   => '2',          // Engine 2: besser für gedruckten Text
    ];

    $ch = curl_init('https://api.ocr.space/parse/image');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_HTTPHEADER     => ['apikey: ' . $apiKey],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) return null;

    $data = json_decode($response, true);
    if (empty($data['ParsedResults'][0]['ParsedText'])) return null;

    $text = $data['ParsedResults'][0]['ParsedText'];

    // Nur erste 10 Zeilen auswerten (Instrumentenname steht oben)
    $zeilen = array_slice(explode("
", $text), 0, 10);
    $text   = implode("
", $zeilen);

    return matcheStimme($text);
}

function erkenneStimmePerText(string $pdfPath, int $seite): ?string
{
    if (!function_exists('shell_exec')) return null;

    $esc  = escapeshellarg($pdfPath);
    $p    = (int)$seite;

    // Nur oberen Bereich der Seite lesen
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

function matcheStimme(string $text): ?string
{
    // WICHTIG: Spezifischere Namen ZUERST (Tenorhorn vor Horn, Bassklarinette vor Klarinette etc.)
    $instrumente = [
        // Holzbläser
        'Piccolo(?:fl[öu]te)?',
        'Querfl[öu]te[n]?', 'Bassfl[öu]te', 'Fl[öu]te[n]?',
        'Bassklarinette', 'Klarinette[n]?',
        'Englisch(?:es)?\s*Horn',
        'Oboe[n]?', 'Fagott[e]?', 'Kontrafagott',
        'Sopransaxophon', 'Altsaxophon', 'Tenorsaxophon', 'Baritonsaxophon', 'Basssaxophon', 'Saxophon[e]?',
        // Blechbläser – REIHENFOLGE WICHTIG: länger/spezifischer zuerst
        'Fl[üu]gelhorn(?:er)?',
        'Cornet(?:te)?',
        'Trompete[n]?',
        'Tenorhorn(?:er)?',      // VOR Horn!
        'Waldhorn(?:er)?',
        'Horn(?:er)?',           // NACH Tenorhorn und Waldhorn
        'Bariton[e]?', 'Euphonium',
        'Kontrabassposaune', 'Bassposaune', 'Posaune[n]?',
        'Kontrabasstuba', 'Basstuba', 'Tuba[s]?',
        'Es-Bass', 'B-Bass',
        // Schlagwerk
        'Schlagzeug', 'Drumset',
        'Kleine\s*Trommel', 'Grosse?\s*Trommel',
        'Pauken?', 'Becken',
        'Glockenspiel', 'Xylophon', 'Vibraphon', 'Marimba',
        'Percussion',
        // Sonstiges
        'Partitur', 'Direktion', 'Score',
        'Kontrabass', 'Akkordeon', 'Klavier', 'Orgel', 'Gitarre',
    ];
    foreach ($instrumente as $instr) {
        $pat = '/(?:(\d+)\s*[.\-]\s*)?(' . $instr . ')(?:\s*in\s+[A-Za-z]+)?(?:\s*(?:[IVX]+|(\d+)))?\b/iu';
        if (preg_match($pat, $text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[0]));
        }
    }
    return null;
}

function normalizeStimme(string $stimme): array
{
    $nr   = 1;
    $rest = trim($stimme);
    if (preg_match('/^(\d+)\s*[.\-]\s*(.+)/u', $rest, $m)) {
        $nr = (int)$m[1]; $rest = trim($m[2]);
    } elseif (preg_match('/^(.+?)\s+(\d+)$/u', $rest, $m)) {
        $rest = trim($m[1]); $nr = (int)$m[2];
    } elseif (preg_match('/^(.+?)\s+(I{1,3}|IV|VI{0,3}|IX)$/iu', $rest, $m)) {
        $rest = trim($m[1]);
        $romMap = ['I'=>1,'II'=>2,'III'=>3,'IV'=>4,'V'=>5,'VI'=>6,'VII'=>7,'VIII'=>8,'IX'=>9];
        $nr = $romMap[strtoupper($m[2])] ?? 1;
    }
    $rest = preg_replace('/\s+in\s+[A-Za-z]+\s*$/u', '', $rest);
    return [sanitizeFilename(trim($rest)), $nr];
}

function naechsteStimmNummer($db, int $notenId, string $titel, string $instrument, int $gewuenscht): int
{
    $rows = $db->fetchAll("SELECT original_name FROM noten_dateien WHERE noten_id=?", [$notenId]);
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
