<?php
// api/system_update.php – Version prüfen & Update via GitHub ZIP
ob_start();
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'PHP-Fehler: ' . $error['message']]);
    }
});

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

ob_clean();
header('Content-Type: application/json');
Session::requireLogin();

if (!Session::isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

$action   = $_POST['action'] ?? $_GET['action'] ?? '';
$appRoot  = realpath(__DIR__ . '/..');
$repoOwner = 'Hanner72';
$repoName  = 'syncopa';
$branch    = 'main';

// Dateien/Verzeichnisse die beim Update NICHT überschrieben werden
$skipFiles = [
    'config.php',
    'uploads',
];

/**
 * HTTP GET – cURL mit Fallback auf file_get_contents.
 */
function httpGet(string $url): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'syncopa-updater/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github.v3+json'],
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);
        return ['body' => $body, 'code' => $httpCode, 'error' => $err];
    }

    if (!ini_get('allow_url_fopen')) {
        return ['body' => false, 'code' => 0, 'error' => 'Weder cURL noch allow_url_fopen verfügbar'];
    }

    $ctx  = stream_context_create(['http' => [
        'timeout'     => 60,
        'user_agent'  => 'syncopa-updater/1.0',
        'follow_location' => 1,
        'header'      => "Accept: application/vnd.github.v3+json\r\n",
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return ['body' => false, 'code' => 0, 'error' => 'file_get_contents fehlgeschlagen'];
    }
    // HTTP-Statuscode aus den Response-Headern lesen
    $code = 200;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#HTTP/\S+\s+(\d+)#', $h, $m)) {
                $code = (int)$m[1];
            }
        }
    }
    return ['body' => $body, 'code' => $code, 'error' => ''];
}

/**
 * Versionsnummer aus CHANGELOG.md-Inhalt parsen (erstes ## [X.Y.Z]).
 */
function parseVersionFromChangelog(string $content): ?string {
    if (preg_match('/##\s*\[([0-9]+\.[0-9]+\.[0-9]+)\]/', $content, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Verzeichnis rekursiv kopieren, skipFiles überspringen.
 */
function copyDir(string $src, string $dst, array $skipFiles, array &$log): void {
    $items = scandir($src);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $skipFiles)) {
            $log[] = "↷ Übersprungen: {$item}";
            continue;
        }
        $srcPath = $src . DIRECTORY_SEPARATOR . $item;
        $dstPath = $dst . DIRECTORY_SEPARATOR . $item;
        if (is_dir($srcPath)) {
            if (!is_dir($dstPath)) mkdir($dstPath, 0755, true);
            copyDir($srcPath, $dstPath, $skipFiles, $log);
        } else {
            if (!copy($srcPath, $dstPath)) {
                $log[] = "✗ Fehler beim Kopieren: {$item}";
            }
        }
    }
}

/**
 * Verzeichnis rekursiv löschen.
 */
function rmRecursive(string $path): void {
    if (!is_dir($path)) return;
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $path . DIRECTORY_SEPARATOR . $item;
        is_dir($full) ? rmRecursive($full) : unlink($full);
    }
    rmdir($path);
}

// ─── Servervoraussetzungen prüfen ────────────────────────────────────────────
function canReachInternet(): bool {
    return function_exists('curl_init') || ini_get('allow_url_fopen');
}

// ─── CHECK ────────────────────────────────────────────────────────────────────
if ($action === 'check') {
    if (!canReachInternet()) {
        echo json_encode([
            'success'      => true,
            'localVersion' => APP_VERSION,
            'remoteVersion'=> null,
            'upToDate'     => true,
            'serverError'  => 'Dieser Server unterstützt keine ausgehenden HTTP-Verbindungen (kein cURL, kein allow_url_fopen). Automatische Updates sind nicht verfügbar – bitte Dateien manuell per FTP aktualisieren.',
            'newChanges'   => [],
        ]);
        exit;
    }

    $localVersion = APP_VERSION;

    // Remote Changelog holen
    $rawUrl = "https://raw.githubusercontent.com/{$repoOwner}/{$repoName}/{$branch}/docs/changelog.md";
    $resp = httpGet($rawUrl);

    if ($resp['code'] !== 200 || !$resp['body']) {
        echo json_encode([
            'success' => false,
            'error'   => 'GitHub nicht erreichbar (HTTP ' . $resp['code'] . ')' . ($resp['error'] ? ': ' . $resp['error'] : ''),
        ]);
        exit;
    }

    $remoteVersion = parseVersionFromChangelog($resp['body']);

    // Changelog-Einträge bis zur installierten Version sammeln
    $newChanges = [];
    if ($remoteVersion && $remoteVersion !== $localVersion) {
        $lines = explode("\n", $resp['body']);
        $collecting = false;
        foreach ($lines as $line) {
            if (preg_match('/^##\s*\[([0-9]+\.[0-9]+\.[0-9]+)\]/', $line, $m)) {
                if ($m[1] === $localVersion) break;
                $collecting = true;
            }
            if ($collecting) $newChanges[] = rtrim($line);
        }
    }

    echo json_encode([
        'success'       => true,
        'localVersion'  => $localVersion,
        'remoteVersion' => $remoteVersion,
        'upToDate'      => $remoteVersion === null || version_compare($localVersion, $remoteVersion, '>='),
        'newChanges'    => array_values($newChanges),
    ]);
    exit;
}

// ─── UPDATE ───────────────────────────────────────────────────────────────────
if ($action === 'update') {
    $log = [];

    // 1. ZIP herunterladen
    $zipUrl  = "https://github.com/{$repoOwner}/{$repoName}/archive/refs/heads/{$branch}.zip";
    $tmpZip  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'syncopa_update_' . time() . '.zip';
    $tmpDir  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'syncopa_update_' . time();

    $log[] = "↓ Lade Update von GitHub…";
    $resp = httpGet($zipUrl);

    if ($resp['code'] !== 200 || empty($resp['body'])) {
        echo json_encode(['success' => false, 'log' => $log, 'error' => 'Download fehlgeschlagen (HTTP ' . $resp['code'] . ')']);
        exit;
    }

    if (file_put_contents($tmpZip, $resp['body']) === false) {
        echo json_encode(['success' => false, 'log' => $log, 'error' => 'ZIP konnte nicht gespeichert werden (temp-Verzeichnis nicht schreibbar?)']);
        exit;
    }
    $log[] = '✓ ZIP heruntergeladen (' . round(strlen($resp['body']) / 1024) . ' KB)';

    // 2. ZIP entpacken
    if (!class_exists('ZipArchive')) {
        echo json_encode(['success' => false, 'log' => $log, 'error' => 'ZipArchive-Extension fehlt auf diesem Server']);
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) {
        unlink($tmpZip);
        echo json_encode(['success' => false, 'log' => $log, 'error' => 'ZIP konnte nicht geöffnet werden']);
        exit;
    }

    mkdir($tmpDir, 0755, true);
    $zip->extractTo($tmpDir);
    $zip->close();
    unlink($tmpZip);
    $log[] = '✓ ZIP entpackt';

    // Unterordner ermitteln (GitHub erstellt z.B. "syncopa-main/")
    $subDirs = glob($tmpDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
    if (empty($subDirs)) {
        rmRecursive($tmpDir);
        echo json_encode(['success' => false, 'log' => $log, 'error' => 'Unerwartete ZIP-Struktur']);
        exit;
    }
    $extractedRoot = $subDirs[0];

    // 3. Neue Version aus CHANGELOG ermitteln
    $changelogPath = $extractedRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'changelog.md';
    $newVersion = null;
    if (file_exists($changelogPath)) {
        $newVersion = parseVersionFromChangelog(file_get_contents($changelogPath));
    }

    // 4. Dateien kopieren (config.php und uploads/ überspringen)
    $log[] = "→ Kopiere Dateien…";
    copyDir($extractedRoot, $appRoot, $skipFiles, $log);
    $log[] = '✓ Dateien kopiert';

    // 5. Temp-Verzeichnis aufräumen
    rmRecursive($tmpDir);
    $log[] = '✓ Temporäre Dateien bereinigt';

    // 6. APP_VERSION aus config.php entfernen (wird jetzt in config.app.php verwaltet)
    $configPath = $appRoot . DIRECTORY_SEPARATOR . 'config.php';
    if (file_exists($configPath)) {
        $cfg = file_get_contents($configPath);
        $cleaned = preg_replace("/^[^\n]*define\('APP_VERSION',[^\n]*\n?/m", '', $cfg);
        if ($cleaned !== $cfg) {
            file_put_contents($configPath, $cleaned);
            $log[] = '✓ APP_VERSION aus config.php entfernt (wird jetzt über config.app.php verwaltet)';
        }
    }

    // OPcache leeren falls aktiv
    if (function_exists('opcache_reset')) {
        opcache_reset();
        $log[] = '✓ OPcache geleert';
    }

    // Verifikation: APP_VERSION in config.app.php prüfen
    $verifyPath = $appRoot . DIRECTORY_SEPARATOR . 'config.app.php';
    if (file_exists($verifyPath)) {
        $verifyContent = file_get_contents($verifyPath);
        if (strpos($verifyContent, 'APP_VERSION') !== false) {
            preg_match("/define\('APP_VERSION',\s*'([^']+)'\)/", $verifyContent, $vm);
            $log[] = '✓ config.app.php: APP_VERSION = ' . ($vm[1] ?? '(gefunden, kein Wert geparst)');
        } else {
            $log[] = '✗ WARNUNG: config.app.php enthält kein APP_VERSION!';
        }
    } else {
        $log[] = '✗ WARNUNG: config.app.php nicht gefunden!';
    }

    $log[] = '✓ Update abgeschlossen';

    // Session-Cache für Update-Check zurücksetzen
    if (session_status() !== PHP_SESSION_NONE) {
        unset($_SESSION['_update_check'], $_SESSION['_update_check_ts']);
    }

    echo json_encode([
        'success'    => true,
        'log'        => $log,
        'newVersion' => $newVersion,
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unbekannte Aktion']);
