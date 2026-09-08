<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'lesen');

$festId    = isset($_GET['fest_id'])   ? (int)$_GET['fest_id']          : 0;
$lieferant = isset($_GET['lieferant']) ? trim($_GET['lieferant'])        : '';
$stationen = ($_GET['stationen'] ?? '1') === '1';
$notizen   = ($_GET['notizen']   ?? '1') === '1';
$statusRaw = isset($_GET['status'])    ? trim($_GET['status'])           : 'geplant,bestellt';
$statusList = array_filter(array_map('trim', explode(',', $statusRaw)));
if (empty($statusList)) $statusList = ['geplant', 'bestellt'];

$festObj = new Fest();
$fest    = $festObj->getById($festId);
if (!$fest) { echo 'Fest nicht gefunden.'; exit; }

$eObj   = new FestEinkauf();
$filter = [];
if ($lieferant !== '') $filter['lieferant'] = $lieferant;

// Alle Artikel holen, dann nach Status filtern
$alle = $eObj->getByFest($festId, $filter);
$alle = array_filter($alle, fn($e) => in_array($e['status'], $statusList));

// Nach Station gruppieren
$byStation = [];
foreach ($alle as $e) {
    $sk = $e['station_id'] ?? 0;
    $sn = $e['station_name'] ?? 'Keine Station';
    $byStation[$sk]['name']  = $sn;
    $byStation[$sk]['sort']  = (int)($e['station_sortierung'] ?? 9999);
    $byStation[$sk]['items'][] = $e;
}
uasort($byStation, fn($a, $b) => $a['sort'] !== $b['sort'] ? $a['sort'] - $b['sort'] : strcmp($a['name'], $b['name']));

$statusLabels = [
    'geplant'  => 'Geplant',
    'bestellt' => 'Bestellt',
    'erhalten' => 'Erhalten',
    'storniert'=> 'Storniert',
];

$gesamtSumme = array_sum(array_map(fn($e) => (float)($e['preis_gesamt'] ?? 0), $alle));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Bestellliste – <?php echo htmlspecialchars($fest['name']); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background: #fff; color: #111; font-size: 12px; padding: 20px; }

        .header { margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 12px; }
        .header h1 { font-size: 20px; font-weight: bold; }
        .header .meta { color: #555; font-size: 11px; margin-top: 4px; }

        .controls { margin-bottom: 16px; display: flex; gap: 12px; align-items: center; }
        .controls label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12px; }
        .controls button {
            padding: 6px 14px; background: #4471A3; color: #fff;
            border: none; border-radius: 4px; cursor: pointer; font-size: 12px;
        }
        .controls button:hover { background: #346090; }

        .station-block { margin-bottom: 20px; }
        .station-title {
            background: #4471A3; color: #fff;
            padding: 6px 10px; font-size: 13px; font-weight: bold;
            margin-bottom: 0;
        }

        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: #f0f0f0; padding: 6px 8px; text-align: left; border: 1px solid #ccc; font-size: 11px; }
        td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: top; }
        tr:nth-child(even) { background: #fafafa; }
        td.abhaken { width: 30px; text-align: center; }
        td.menge    { width: 90px; }
        td.preis    { width: 80px; text-align: right; }
        td.status   { width: 70px; }

        .summe-row { background: #f0f4f8 !important; font-weight: bold; }

        .total { margin-top: 16px; text-align: right; font-size: 14px; font-weight: bold; border-top: 2px solid #333; padding-top: 8px; }

        @media print {
            .controls { display: none !important; }
            body { padding: 10px; }
            .station-block { page-break-inside: avoid; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

<div class="controls no-print">
    <label>
        <input type="checkbox" id="toggle-stationen" <?php echo $stationen ? 'checked' : ''; ?>>
        Nach Stationen gliedern
    </label>
    <label>
        <input type="checkbox" id="toggle-notizen" <?php echo $notizen ? 'checked' : ''; ?>>
        Notizen anzeigen
    </label>
    <label>
        <input type="checkbox" id="toggle-status" checked>
        Status anzeigen
    </label>
    <label>
        <input type="checkbox" id="toggle-abgehakt">
        Abhak-Spalte anzeigen
    </label>
    <button onclick="window.print()">&#128438; Drucken / PDF</button>
</div>

<div class="header">
    <h1>Bestellliste – <?php echo htmlspecialchars($fest['name']); ?></h1>
    <div class="meta">
        <?php echo $lieferant ? 'Lieferant: <strong>' . htmlspecialchars($lieferant) . '</strong> &nbsp;|&nbsp; ' : ''; ?>
        Status: <?php echo implode(', ', array_map(fn($s) => $statusLabels[$s] ?? $s, $statusList)); ?>
        &nbsp;|&nbsp; Erstellt: <?php echo date('d.m.Y H:i'); ?>
        &nbsp;|&nbsp; <?php echo count($alle); ?> Positionen
    </div>
</div>

<div id="liste-container">
<?php if (empty($alle)): ?>
    <p style="color:#999;text-align:center;padding:30px">Keine Artikel gefunden.</p>
<?php elseif ($stationen): ?>

    <?php foreach ($byStation as $sk => $stData): ?>
    <div class="station-block">
        <div class="station-title"><i>&#128205;</i> <?php echo htmlspecialchars($stData['name']); ?></div>
        <?php renderBestellTable($stData['items']); ?>
    </div>
    <?php endforeach; ?>

<?php else: ?>
    <?php renderBestellTable(array_values($alle)); ?>
<?php endif; ?>
</div>


<?php
function renderBestellTable(array $items): void {
    $summe = array_sum(array_map(fn($e) => (float)($e['preis_gesamt'] ?? 0), $items));
    ?>
    <table>
        <thead>
            <tr>
                <th class="abhaken abk-col" style="display:none">✓</th>
                <th>Bezeichnung</th>
                <th class="menge">Menge</th>
                <th class="status-col">Status</th>
                <?php global $notizen; if ($notizen): ?><th>Notizen</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $e): ?>
            <tr>
                <td class="abhaken abk-col" style="display:none">&square;</td>
                <td><?php echo htmlspecialchars($e['bezeichnung']); ?></td>
                <td class="menge"><?php echo $e['menge'] ? number_format((int)$e['menge'], 0, ',', '.') . ' ' . htmlspecialchars($e['einheit'] ?? '') : '–'; ?></td>
                <td class="status-col"><?php echo htmlspecialchars($e['status']); ?></td>
                <?php global $notizen; if ($notizen): ?><td><?php echo htmlspecialchars($e['notizen'] ?? ''); ?></td><?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
?>

<script>
var stationenAktiv = <?php echo $stationen ? 'true' : 'false'; ?>;
var notizenAktiv   = <?php echo $notizen   ? 'true' : 'false'; ?>;
var statusAktiv    = true;
var alleItems = <?php echo json_encode(array_values($alle)); ?>;
var byStation = <?php echo json_encode(array_values(array_map(function($s) { return ['name' => $s['name'], 'items' => $s['items']]; }, $byStation))); ?>;

document.getElementById('toggle-abgehakt').addEventListener('change', function() {
    document.querySelectorAll('.abk-col').forEach(function(el) {
        el.style.display = this.checked ? '' : 'none';
    }.bind(this));
});

document.getElementById('toggle-stationen').addEventListener('change', function() {
    stationenAktiv = this.checked;
    rebuildListe();
});

document.getElementById('toggle-notizen').addEventListener('change', function() {
    notizenAktiv = this.checked;
    rebuildListe();
});

document.getElementById('toggle-status').addEventListener('change', function() {
    statusAktiv = this.checked;
    // Für PHP-gerenderte Tabelle direkt ein-/ausblenden
    document.querySelectorAll('.status-col').forEach(function(el) {
        el.style.display = statusAktiv ? '' : 'none';
    });
    // Für JS-generierte Tabellen neu aufbauen
    if (document.getElementById('liste-container').querySelector('table')) {
        rebuildListe();
    }
});

function rebuildListe() {
    var container = document.getElementById('liste-container');
    if (stationenAktiv) {
        var html = '';
        byStation.forEach(function(st) {
            html += '<div class="station-block"><div class="station-title">&#128205; ' + escHtml(st.name) + '</div>';
            html += buildTable(st.items);
            html += '</div>';
        });
        container.innerHTML = html;
    } else {
        container.innerHTML = buildTable(alleItems);
    }
    // Abhak-Spalte Status wiederherstellen
    var abk = document.getElementById('toggle-abgehakt');
    if (abk && abk.checked) {
        document.querySelectorAll('.abk-col').forEach(function(el) { el.style.display = ''; });
    }
}

function buildTable(items) {
    var summe = 0;
    var notizenTh = notizenAktiv ? '<th>Notizen</th>' : '';
    var statusTh  = statusAktiv  ? '<th class="status">Status</th>' : '';
    var rows = items.map(function(e) {
        var menge     = e.menge ? parseInt(e.menge) + ' ' + (e.einheit || '') : '–';
        var notizenTd = notizenAktiv ? '<td>' + escHtml(e.notizen || '') + '</td>' : '';
        var statusTd  = statusAktiv  ? '<td class="status">' + escHtml(e.status) + '</td>' : '';
        return '<tr>'
            + '<td class="abhaken abk-col" style="display:none">&square;</td>'
            + '<td>' + escHtml(e.bezeichnung) + '</td>'
            + '<td class="menge">' + menge + '</td>'
            + statusTd
            + notizenTd
            + '</tr>';
    }).join('');
    return '<table><thead><tr>'
        + '<th class="abhaken abk-col" style="display:none">✓</th>'
        + '<th>Bezeichnung</th><th class="menge">Menge</th>' + statusTh + notizenTh
        + '</tr></thead><tbody>' + rows + '</tbody></table>';
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function formatNum(n, dec) {
    return parseFloat(n).toFixed(dec).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
}
</script>
</body>
</html>
