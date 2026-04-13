<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'lesen');

$festId  = isset($_GET['fest_id']) ? (int)$_GET['fest_id'] : 0;
$festObj = new Fest();
$fest    = $festObj->getById($festId);

if (!$fest) {
    Session::setFlashMessage('danger', 'Fest nicht gefunden.');
    header('Location: feste.php'); exit;
}

$eObj   = new FestEinkauf();

// Status-Label → DB-Wert Mapping
$statusLabelMap = ['Geplant' => 'geplant', 'Bestellt' => 'bestellt', 'Erhalten' => 'erhalten', 'Storniert' => 'storniert'];
$vorlageLabelMap = ['Nur Vorlagen' => '1', 'Keine Vorlagen' => '0'];

$filter = [];
if (!empty($_GET['status'])) {
    $sv = $_GET['status'];
    $filter['status'] = $statusLabelMap[$sv] ?? $sv;
}
if (!empty($_GET['bezeichnung']))  $filter['bezeichnung']  = trim($_GET['bezeichnung']);
if (!empty($_GET['lieferant']))    $filter['lieferant']    = trim($_GET['lieferant']);
if (isset($_GET['ist_vorlage']) && $_GET['ist_vorlage'] !== '') {
    $vv = $_GET['ist_vorlage'];
    $mapped = $vorlageLabelMap[$vv] ?? $vv;
    if ($mapped !== '') $filter['ist_vorlage'] = (int)$mapped;
}

$grouped    = $eObj->getByFestGrouped($festId, $filter);
$summen     = $eObj->getSummen($festId);
$kategorien = $eObj->getKategorien();
$lieferanten = $eObj->getLieferanten($festId);

$statusLabels = [
    'geplant'  => ['label' => 'Geplant',   'badge' => 'warning'],
    'bestellt' => ['label' => 'Bestellt',  'badge' => 'info'],
    'erhalten' => ['label' => 'Erhalten',  'badge' => 'success'],
    'storniert'=> ['label' => 'Storniert', 'badge' => 'danger'],
];

include 'includes/header.php';
?>

<?php include 'includes/fest_tabs.php'; ?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-cart3"></i> Einkäufe</h1>
    <?php if (Session::checkPermission('fest', 'schreiben')): ?>
    <a href="fest_einkauf_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Einkauf hinzufügen
    </a>
    <?php endif; ?>
</div>

<!-- Stat-Karten -->
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-primary">
            <div class="card-body">
                <div><h6>Gesamt</h6><h2><?php echo number_format($summen['gesamt'], 2, ',', '.'); ?> €</h2><small><?php echo $summen['anzahl']; ?> Positionen</small></div>
                <i class="bi bi-currency-euro stat-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-warning">
            <div class="card-body">
                <div><h6>Geplant</h6><h2><?php echo number_format($summen['geplant'], 2, ',', '.'); ?> €</h2></div>
                <i class="bi bi-clock stat-icon text-warning"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-info">
            <div class="card-body">
                <div><h6>Bestellt</h6><h2><?php echo number_format($summen['bestellt'], 2, ',', '.'); ?> €</h2></div>
                <i class="bi bi-truck stat-icon text-info"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-success">
            <div class="card-body">
                <div><h6>Erhalten</h6><h2><?php echo number_format($summen['erhalten'], 2, ',', '.'); ?> €</h2></div>
                <i class="bi bi-check-circle stat-icon text-success"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="fest_id" value="<?php echo $festId; ?>">
            <div class="col-md-3">
                <label class="form-label">Bezeichnung</label>
                <input type="text" name="bezeichnung" id="filter-bezeichnung" class="form-control form-control-sm"
                       placeholder="Bezeichnung suchen…"
                       value="<?php echo htmlspecialchars($_GET['bezeichnung'] ?? ''); ?>"
                       autocomplete="off">
            </div>
            <div class="col-md-2">
                <label class="form-label">Lieferant</label>
                <input type="text" name="lieferant" class="form-control form-control-sm"
                       placeholder="Lieferant suchen…"
                       list="lieferanten-list"
                       value="<?php echo htmlspecialchars($_GET['lieferant'] ?? ''); ?>">
                <datalist id="lieferanten-list">
                    <?php foreach ($lieferanten as $l): ?>
                    <option value="<?php echo htmlspecialchars($l); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <input type="text" name="status" class="form-control form-control-sm"
                       placeholder="Status wählen…"
                       list="status-list"
                       value="<?php echo htmlspecialchars($_GET['status'] ?? ''); ?>">
                <datalist id="status-list">
                    <?php foreach ($statusLabels as $sv => $sl): ?>
                    <option value="<?php echo $sl['label']; ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-2">
                <label class="form-label">Vorlage</label>
                <input type="text" name="ist_vorlage" class="form-control form-control-sm"
                       placeholder="Vorlage wählen…"
                       list="vorlage-list"
                       value="<?php echo htmlspecialchars($_GET['ist_vorlage'] ?? ''); ?>">
                <datalist id="vorlage-list">
                    <option value="Nur Vorlagen">
                    <option value="Keine Vorlagen">
                </datalist>
            </div>
            <div class="col-auto">
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filtern</button>
                    <a href="fest_einkauefe.php?fest_id=<?php echo $festId; ?>" class="btn btn-sm btn-outline-secondary">Zurücksetzen</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Einkäufe gruppiert nach Kategorie -->
<?php if (empty($grouped)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-cart3 fs-1 d-block mb-2 opacity-25"></i>
    Keine Einkäufe gefunden.
    <?php if (Session::checkPermission('fest', 'schreiben')): ?>
    <div class="mt-2"><a href="fest_einkauf_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-sm btn-primary">Ersten Einkauf hinzufügen</a></div>
    <?php endif; ?>
</div>
<?php else: ?>
<?php foreach ($grouped as $katId => $kat): ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-tag"></i> <?php echo htmlspecialchars($kat['name']); ?></h5>
        <span class="text-muted small">
            <?php
            $katSumme = array_sum(array_map(fn($i) => (float)($i['preis_gesamt'] ?? 0), $kat['items']));
            echo number_format($katSumme, 2, ',', '.') . ' €';
            ?>
        </span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Bezeichnung</th>
                    <th>Menge</th>
                    <th>Preis gesamt</th>
                    <th>Lieferant</th>
                    <th>Status</th>
                    <th class="text-center" title="Als Vorlage markieren – wird beim Kopieren ins nächste Fest übernommen">
                        <i class="bi bi-bookmark"></i> Vorlage
                    </th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kat['items'] as $e): ?>
                <?php $sl = $statusLabels[$e['status']] ?? ['label' => $e['status'], 'badge' => 'secondary']; ?>
                <tr class="einkauf-row" data-bezeichnung="<?php echo strtolower(htmlspecialchars($e['bezeichnung'])); ?>">
                    <td>
                        <strong <?php if ($e['notizen']): ?>
                            data-bs-toggle="tooltip" data-bs-placement="right"
                            data-bs-title="<?php echo htmlspecialchars($e['notizen']); ?>"
                            style="cursor:default;border-bottom:1px dotted #adb5bd"
                        <?php endif; ?>>
                            <?php echo htmlspecialchars($e['bezeichnung']); ?>
                        </strong>
                    </td>
                    <td class="small">
                        <?php if ($e['menge']): ?>
                        <?php echo number_format($e['menge'], 3, ',', '.') . ' ' . htmlspecialchars($e['einheit'] ?? ''); ?>
                        <?php else: ?> – <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $e['preis_gesamt'] !== null ? number_format($e['preis_gesamt'], 2, ',', '.') . ' €' : '–'; ?>
                    </td>
                    <td class="small"><?php echo htmlspecialchars($e['lieferant'] ?? '–'); ?></td>
                    <td><span class="badge bg-<?php echo $sl['badge']; ?>"><?php echo $sl['label']; ?></span></td>
                    <td class="text-center">
                        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                        <button class="btn-vorlage-toggle"
                                data-id="<?php echo $e['id']; ?>"
                                data-vorlage="<?php echo $e['ist_vorlage'] ? '1' : '0'; ?>"
                                data-bs-toggle="tooltip"
                                data-bs-placement="left"
                                data-bs-title="<?php echo $e['ist_vorlage'] ? 'Vorlage – klicken zum Entfernen' : 'Klicken um als Vorlage zu markieren'; ?>"
                                style="font-size:18px;padding:0;border:none;background:none;cursor:pointer;color:<?php echo $e['ist_vorlage'] ? '#198754' : '#dee2e6'; ?>">
                            <i class="bi <?php echo $e['ist_vorlage'] ? 'bi-bookmark-fill' : 'bi-bookmark'; ?>"></i>
                        </button>
                        <?php else: ?>
                        <?php if ($e['ist_vorlage']): ?><i class="bi bi-bookmark-fill text-success"></i><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                            <a href="fest_einkauf_bearbeiten.php?id=<?php echo $e['id']; ?>&fest_id=<?php echo $festId; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (Session::checkPermission('fest', 'loeschen')): ?>
                            <form method="POST" action="fest_einkauf_loeschen.php" class="d-inline"
                                  onsubmit="return confirm('Einkauf «<?php echo htmlspecialchars(addslashes($e['bezeichnung'])); ?>» löschen?')">
                                <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                                <input type="hidden" name="fest_id" value="<?php echo $festId; ?>">
                                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script>
// Live-Filter: Bezeichnung
document.getElementById('filter-bezeichnung').addEventListener('input', function() {
    var term = this.value.toLowerCase();
    document.querySelectorAll('.einkauf-row').forEach(function(row) {
        var match = !term || row.dataset.bezeichnung.includes(term);
        row.style.display = match ? '' : 'none';
    });
    // Kategoriekarten ausblenden wenn alle Zeilen darin versteckt sind
    document.querySelectorAll('.card.mb-3').forEach(function(card) {
        var rows = card.querySelectorAll('.einkauf-row');
        if (!rows.length) return;
        var anyVisible = Array.from(rows).some(function(r) { return r.style.display !== 'none'; });
        card.style.display = anyVisible ? '' : 'none';
    });
});

document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap Tooltips initialisieren (Notizen + Vorlage-Buttons)
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        if (!el.classList.contains('btn-vorlage-toggle')) {
            new bootstrap.Tooltip(el, { trigger: 'hover', customClass: 'tooltip-wide' });
        }
    });
    var tooltips = {};
    document.querySelectorAll('.btn-vorlage-toggle').forEach(function(btn) {
        tooltips[btn.dataset.id] = new bootstrap.Tooltip(btn, { trigger: 'hover' });
    });

    document.querySelectorAll('.btn-vorlage-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var btnEl   = this;
            var id      = btnEl.dataset.id;
            var vorlage = btnEl.dataset.vorlage === '1' ? 0 : 1;

            // Tooltip ausblenden bevor Änderung
            if (tooltips[id]) tooltips[id].hide();

            fetch('api/fest_einkauf_vorlage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id + '&ist_vorlage=' + vorlage
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    btnEl.dataset.vorlage = data.ist_vorlage ? '1' : '0';
                    var icon    = btnEl.querySelector('i');
                    var newText = data.ist_vorlage ? 'Vorlage – klicken zum Entfernen' : 'Klicken um als Vorlage zu markieren';
                    if (data.ist_vorlage) {
                        icon.className    = 'bi bi-bookmark-fill';
                        btnEl.style.color = '#198754';
                    } else {
                        icon.className    = 'bi bi-bookmark';
                        btnEl.style.color = '#dee2e6';
                    }
                    // Tooltip-Text aktualisieren
                    btnEl.setAttribute('data-bs-title', newText);
                    if (tooltips[id]) {
                        tooltips[id].dispose();
                        tooltips[id] = new bootstrap.Tooltip(btnEl, { trigger: 'hover' });
                    }
                } else {
                    alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                }
            })
            .catch(function(e) { console.error(e); });
        });
    });
});
</script>
<?php include 'includes/footer.php'; ?>
