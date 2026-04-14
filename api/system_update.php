<?php
// api/system_update.php – Version prüfen & Update via GitHub ZIP
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

header('Content-Type: application/json');
Session::requireLogin();

if (Session::getRole() !== 'admin') {
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
 * HTTP GET via cURL mit GitHub-kompatiblen Headern.
 */
function httpGet(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'syncopa-updater/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github.v3+json'],
    ]);
    $body    = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err     = curl_error($ch);
    curl_close($ch);
    return ['body' => $body, 'code' => $httpCode, 'error' => $err];
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
            copy($srcPath, $dstPath);
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

// ─── CHECK ────────────────────────────────────────────────────────────────────
if ($action === 'check') {
    $localVersion = APP_VERSION;

    // Remote CHANGELOG.md holen
    $rawUrl = "https://raw.githubusercontent.com/{$repoOwner}/{$repoName}/{$branch}/CHANGELOG.md";
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
    $changelogPath = $extractedRoot . DIRECTORY_SEPARATOR . 'CHANGELOG.md';
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

    // 6. APP_VERSION in config.php aktualisieren
    if ($newVersion) {
        $configPath = $appRoot . DIRECTORY_SEPARATOR . 'config.php';
        if (file_exists($configPath)) {
            $cfg = file_get_contents($configPath);
            $cfg = preg_replace(
                "/define\('APP_VERSION',\s*'[^']+'\)/",
                "define('APP_VERSION', '{$newVersion}')",
                $cfg
            );
            file_put_contents($configPath, $cfg);
            $log[] = "✓ APP_VERSION auf {$newVersion} gesetzt";
        }
    }

    $log[] = '✓ Update abgeschlossen';

    echo json_encode([
        'success'    => true,
        'log'        => $log,
        'newVersion' => $newVersion,
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unbekannte Aktion']);
