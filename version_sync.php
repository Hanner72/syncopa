#!/usr/bin/env php
<?php
/**
 * version_sync.php
 * Liest die aktuelle Version aus docs/changelog.md und synchronisiert
 * alle anderen Dateien automatisch.
 *
 * Aufruf: php version_sync.php
 * Wird automatisch als Git Pre-Commit Hook ausgeführt (siehe .githooks/pre-commit).
 */

$root = __DIR__;

// ── 1. Version aus docs/changelog.md lesen ───────────────────────────────────
$changelogPath = $root . '/docs/changelog.md';
if (!file_exists($changelogPath)) {
    echo "[version_sync] FEHLER: docs/changelog.md nicht gefunden.\n";
    exit(1);
}

$changelogContent = file_get_contents($changelogPath);
if (!preg_match('/##\s*\[([0-9]+\.[0-9]+\.[0-9]+)\]/', $changelogContent, $m)) {
    echo "[version_sync] FEHLER: Keine Version im Format ## [X.Y.Z] in docs/changelog.md gefunden.\n";
    exit(1);
}

$version = $m[1];  // z.B. "2.3.0"
$vVersion = 'v' . $version; // z.B. "v2.3.0"
echo "[version_sync] Version erkannt: {$vVersion}\n";

$updated = [];

// ── 2. config.php ─────────────────────────────────────────────────────────────
$configPath = $root . '/config.php';
if (file_exists($configPath)) {
    $content = file_get_contents($configPath);
    $new = preg_replace(
        "/define\('APP_VERSION',\s*'[^']+'\)/",
        "define('APP_VERSION', '{$version}')",
        $content
    );
    if ($new !== $content) {
        file_put_contents($configPath, $new);
        $updated[] = 'config.php';
    }
}

// ── 3. config.example.php ────────────────────────────────────────────────────
$examplePath = $root . '/config.example.php';
if (file_exists($examplePath)) {
    $content = file_get_contents($examplePath);
    $new = preg_replace(
        "/define\('APP_VERSION',\s*'[^']+'\)/",
        "define('APP_VERSION', '{$version}')",
        $content
    );
    if ($new !== $content) {
        file_put_contents($examplePath, $new);
        $updated[] = 'config.example.php';
    }
}

// ── 4. CHANGELOG.md (root) – Kopie von docs/changelog.md ────────────────────
$rootChangelog = $root . '/CHANGELOG.md';
$currentRoot = file_exists($rootChangelog) ? file_get_contents($rootChangelog) : '';
if ($currentRoot !== $changelogContent) {
    file_put_contents($rootChangelog, $changelogContent);
    $updated[] = 'CHANGELOG.md';
}

// ── 5. docs/README.md – "Version X.Y.Z" aktualisieren ───────────────────────
$readmePath = $root . '/docs/README.md';
if (file_exists($readmePath)) {
    $content = file_get_contents($readmePath);
    $new = preg_replace(
        '/\*\*Version [0-9]+\.[0-9]+\.[0-9]+\*\*/',
        "**Version {$version}**",
        $content
    );
    if ($new !== $content) {
        file_put_contents($readmePath, $new);
        $updated[] = 'docs/README.md';
    }
}

// ── 6. docs/_navbar.md – "vX.Y.Z (aktuell)" aktualisieren ───────────────────
$navbarPath = $root . '/docs/_navbar.md';
if (file_exists($navbarPath)) {
    $content = file_get_contents($navbarPath);
    $new = preg_replace(
        '/v[0-9]+\.[0-9]+\.[0-9]+\s*\(aktuell\)/',
        "{$vVersion} (aktuell)",
        $content
    );
    if ($new !== $content) {
        file_put_contents($navbarPath, $new);
        $updated[] = 'docs/_navbar.md';
    }
}

// ── Ergebnis ─────────────────────────────────────────────────────────────────
if (empty($updated)) {
    echo "[version_sync] Alle Dateien bereits auf {$vVersion} – nichts geändert.\n";
} else {
    foreach ($updated as $f) {
        echo "[version_sync] Aktualisiert: {$f}\n";
    }
    // Geänderte Dateien für den Commit vormerken
    $files = implode(' ', array_map('escapeshellarg', $updated));
    exec("git -C " . escapeshellarg($root) . " add {$files}");
    echo "[version_sync] Dateien gestaged.\n";
}

echo "[version_sync] Fertig.\n";
exit(0);
