<?php
// telemetry/ping.php – Empfängt anonyme Pings von SYNCOPA-Installationen
// Zugriff nur via POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['id']) || !preg_match('/^[a-f0-9]{32}$/', $data['id'])) {
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

// Zugriff auf data/ per .htaccess sperren
$htaccess = $dataDir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Deny from all\n");
}

$file  = $dataDir . '/pings.json';
$pings = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

$id = $data['id'];
$pings[$id] = [
    'id'         => $id,
    'version'    => substr(preg_replace('/[^0-9.]/', '', $data['version'] ?? ''), 0, 20),
    'verein'     => substr(strip_tags($data['verein'] ?? ''), 0, 100),
    'last_seen'  => date('Y-m-d H:i:s'),
    'first_seen' => $pings[$id]['first_seen'] ?? date('Y-m-d H:i:s'),
    'pings'      => ($pings[$id]['pings'] ?? 0) + 1,
];

file_put_contents($file, json_encode($pings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['ok' => true]);
