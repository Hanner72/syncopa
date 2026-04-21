<?php
// telemetry/stats.php – Statistik-Ansicht (passwortgeschützt)
define('STATS_PASSWORD', 'syncopa-stats-2026'); // << BITTE ÄNDERN

session_start();
if ($_POST['pw'] ?? '' === STATS_PASSWORD) {
    $_SESSION['stats_auth'] = true;
}
if (empty($_SESSION['stats_auth'])) { ?>
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Syncopa Stats</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="card shadow" style="width:320px">
    <div class="card-body p-4">
        <h5 class="mb-3 text-center">🎵 Syncopa Stats</h5>
        <form method="post">
            <input type="password" name="pw" class="form-control mb-3" placeholder="Passwort" autofocus>
            <button class="btn btn-primary w-100">Anmelden</button>
        </form>
    </div>
</div>
</body></html>
<?php exit; }

$file  = __DIR__ . '/data/pings.json';
$pings = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

// Sortierung: zuletzt gesehen
uasort($pings, fn($a, $b) => strcmp($b['last_seen'], $a['last_seen']));

$total  = count($pings);
$active = count(array_filter($pings, fn($p) => strtotime($p['last_seen']) > strtotime('-30 days')));

// Versions-Verteilung
$versions = [];
foreach ($pings as $p) {
    $v = $p['version'] ?: '?';
    $versions[$v] = ($versions[$v] ?? 0) + 1;
}
arsort($versions);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Syncopa Installationen</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
body { background:#f5f7fa; font-size:14px; }
.stat-box { background:#fff; border-radius:8px; border:1px solid #e2e8f0; padding:1.25rem 1.5rem; }
</style>
</head>
<body>
<div class="container py-4" style="max-width:900px">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Syncopa Installationen</h4>
        <a href="?logout=1" class="btn btn-sm btn-outline-secondary">Abmelden</a>
    </div>

    <?php if (isset($_GET['logout'])) { $_SESSION['stats_auth'] = false; header('Location: stats.php'); exit; } ?>

    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="stat-box text-center">
                <div class="fs-1 fw-bold text-primary"><?= $total ?></div>
                <div class="text-muted small">Installationen gesamt</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="stat-box text-center">
                <div class="fs-1 fw-bold text-success"><?= $active ?></div>
                <div class="text-muted small">Aktiv (letzte 30 Tage)</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="stat-box text-center">
                <div class="fs-1 fw-bold text-warning"><?= $total - $active ?></div>
                <div class="text-muted small">Inaktiv (> 30 Tage)</div>
            </div>
        </div>
    </div>

    <?php if ($versions): ?>
    <div class="stat-box mb-4">
        <div class="fw-semibold mb-2"><i class="bi bi-layers me-1"></i>Versionsverteilung</div>
        <?php foreach ($versions as $v => $count): ?>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-secondary" style="min-width:60px"><?= htmlspecialchars($v) ?></span>
            <div class="progress flex-grow-1" style="height:14px">
                <div class="progress-bar bg-primary" style="width:<?= round($count / $total * 100) ?>%"></div>
            </div>
            <span class="text-muted small"><?= $count ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="stat-box">
        <div class="fw-semibold mb-3"><i class="bi bi-list me-1"></i>Alle Installationen</div>
        <?php if (empty($pings)): ?>
        <p class="text-muted text-center py-3">Noch keine Pings eingegangen.</p>
        <?php else: ?>
        <table class="table table-sm table-hover mb-0">
            <thead><tr>
                <th>Verein</th>
                <th>Version</th>
                <th>Zuerst gesehen</th>
                <th>Zuletzt gesehen</th>
                <th class="text-end">Pings</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($pings as $p):
                $inactive = strtotime($p['last_seen']) < strtotime('-30 days');
            ?>
            <tr class="<?= $inactive ? 'text-muted' : '' ?>">
                <td><?= htmlspecialchars($p['verein'] ?: '–') ?></td>
                <td><span class="badge bg-<?= $inactive ? 'secondary' : 'primary' ?>"><?= htmlspecialchars($p['version'] ?: '?') ?></span></td>
                <td><?= htmlspecialchars($p['first_seen']) ?></td>
                <td><?= htmlspecialchars($p['last_seen']) ?>
                    <?php if ($inactive): ?><span class="badge bg-secondary ms-1" style="font-size:10px">inaktiv</span><?php endif; ?>
                </td>
                <td class="text-end"><?= (int)($p['pings'] ?? 0) ?></td>
                <td class="text-end">
                    <a href="?delete=<?= urlencode($p['id']) ?>" class="text-danger" onclick="return confirm('Eintrag löschen?')" title="Löschen">
                        <i class="bi bi-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="text-muted text-center mt-3" style="font-size:11px">Letzte Aktualisierung: <?= date('d.m.Y H:i:s') ?></div>
</div>
</body></html>
<?php
// Eintrag löschen
if (!empty($_GET['delete'])) {
    $id = $_GET['delete'];
    if (isset($pings[$id])) {
        unset($pings[$id]);
        file_put_contents($file, json_encode($pings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    header('Location: stats.php');
    exit;
}
