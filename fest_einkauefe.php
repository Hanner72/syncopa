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

$eObj       = new FestEinkauf();
$stationObj = new FestStation();

$statusLabelMap  = ['Geplant' => 'geplant', 'Bestellt' => 'bestellt', 'Erhalten' => 'erhalten', 'Storniert' => 'storniert'];
$vorlageLabelMap = ['Nur Vorlagen' => '1', 'Keine Vorlagen' => '0'];

$filter = [];
if (!empty($_GET['status'])) {
    $sv = $_GET['status'];
    $filter['status'] = $statusLabelMap[$sv] ?? $sv;
}
if (!empty($_GET['bezeichnung']))  $filter['bezeichnung']  = trim($_GET['bezeichnung']);
if (!empty($_GET['lieferant']))    $filter['lieferant']    = trim($_GET['lieferant']);
if (!empty($_GET['station_id']))   $filter['station_id']   = (int)$_GET['station_id'];
if (isset($_GET['ist_vorlage']) && $_GET['ist_vorlage'] !== '') {
    $vv = $_GET['ist_vorlage'];
    $mapped = $vorlageLabelMap[$vv] ?? $vv;
    if ($mapped !== '') $filter['ist_vorlage'] = (int)$mapped;
}

// Ansicht: kategorie | station | lieferant
$ansicht = in_array($_GET['ansicht'] ?? '', ['kategorie','station','lieferant']) ? $_GET['ansicht'] : 'kategorie';

if ($ansicht === 'station') {
    $grouped = $eObj->getByFestGroupedByStation($festId, $filter);
} elseif ($ansicht === 'lieferant') {
    $grouped = $eObj->getByFestGroupedByLieferant($festId, $filter);
} else {
    $grouped = $eObj->getByFestGrouped($festId, $filter);
}

$summen      = $eObj->getSummen($festId);
$lieferanten = $eObj->getLieferanten($festId);
$stationen   = $stationObj->getByFest($festId);

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
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bestelllisteModal">
            <i class="bi bi-printer"></i> Bestellliste PDF
        </button>
        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
        <a href="fest_einkauf_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Einkauf hinzufügen
        </a>
        <?php endif; ?>
    </div>
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

<!-- Filter + Ansicht -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" id="filterForm" class="row g-2 align-items-end">
            <input type="hidden" name="fest_id" value="<?php echo $festId; ?>">
            <input type="hidden" name="ansicht" value="<?php echo htmlspecialchars($ansicht); ?>">
            <div class="col-md-2">
                <label class="form-label">Bezeichnung</label>
                <input type="text" name="bezeichnung" id="filter-bezeichnung" class="form-control form-control-sm"
                       placeholder="Suchen…" value="<?php echo htmlspecialchars($_GET['bezeichnung'] ?? ''); ?>" autocomplete="off">
            </div>
            <div class="col-md-2">
                <label class="form-label">Lieferant</label>
                <select name="lieferant" id="filter-lieferant" class="form-select form-select-sm">
                    <option value="">Alle Lieferanten</option>
                    <?php foreach ($lieferanten as $l): ?>
                    <option value="<?php echo htmlspecialchars($l); ?>" <?php echo ($_GET['lieferant'] ?? '') === $l ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($l); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Station</label>
                <select name="station_id" id="filter-station" class="form-select form-select-sm">
                    <option value="">Alle Stationen</option>
                    <?php foreach ($stationen as $st): ?>
                    <option value="<?php echo $st['id']; ?>" <?php echo (string)($_GET['station_id'] ?? '') === (string)$st['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($st['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" id="filter-status" class="form-select form-select-sm">
                    <option value="">Alle Status</option>
                    <?php foreach ($statusLabels as $sv => $sl): ?>
                    <option value="<?php echo $sl['label']; ?>" <?php echo ($_GET['status'] ?? '') === $sl['label'] ? 'selected' : ''; ?>>
                        <?php echo $sl['label']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Vorlage</label>
                <select name="ist_vorlage" id="filter-vorlage" class="form-select form-select-sm">
                    <option value="">Alle</option>
                    <option value="Nur Vorlagen"  <?php echo ($_GET['ist_vorlage'] ?? '') === 'Nur Vorlagen'  ? 'selected' : ''; ?>>Nur Vorlagen</option>
                    <option value="Keine Vorlagen" <?php echo ($_GET['ist_vorlage'] ?? '') === 'Keine Vorlagen' ? 'selected' : ''; ?>>Keine Vorlagen</option>
                </select>
            </div>
            <div class="col-auto">
                <div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filtern</button>
                    <a href="fest_einkauefe.php?fest_id=<?php echo $festId; ?>&ansicht=<?php echo $ansicht; ?>" class="btn btn-sm btn-outline-secondary" id="btn-reset">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Ansicht-Umschalter -->
<div class="d-flex gap-2 mb-3">
    <span class="text-muted small align-self-center">Ansicht:</span>
    <?php
    $baseParams = http_build_query(array_merge($_GET, ['fest_id' => $festId]));
    foreach (['kategorie' => ['Kategorie','tag'], 'station' => ['Station','geo-alt'], 'lieferant' => ['Lieferant','truck']] as $av => [$al, $ai]):
        $url = 'fest_einkauefe.php?' . http_build_query(array_merge($_GET, ['fest_id' => $festId, 'ansicht' => $av]));
    ?>
    <a href="<?php echo $url; ?>" class="btn btn-sm <?php echo $ansicht === $av ? 'btn-primary' : 'btn-outline-secondary'; ?>">
        <i class="bi bi-<?php echo $ai; ?>"></i> <?php echo $al; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Einkäufe -->
<?php if (empty($grouped)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-cart3 fs-1 d-block mb-2 opacity-25"></i>
    Keine Einkäufe gefunden.
    <?php if (Session::checkPermission('fest', 'schreiben')): ?>
    <div class="mt-2"><a href="fest_einkauf_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-sm btn-primary">Ersten Einkauf hinzufügen</a></div>
    <?php endif; ?>
</div>
<?php elseif ($ansicht === 'lieferant'): ?>

<?php foreach ($grouped as $lKey => $lData): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--bg-body)">
        <h5 class="mb-0"><i class="bi bi-truck"></i> <?php echo htmlspecialchars($lData['name']); ?></h5>
        <span class="text-muted small">
            <?php
            $lSumme = 0;
            foreach ($lData['stationen'] as $stData) {
                foreach ($stData['items'] as $item) $lSumme += (float)($item['preis_gesamt'] ?? 0);
            }
            echo number_format($lSumme, 2, ',', '.') . ' €';
            ?>
        </span>
    </div>
    <?php foreach ($lData['stationen'] as $sKey => $stData): ?>
    <div class="card-body p-0 border-top">
        <div class="px-3 py-2 small fw-semibold text-muted bg-light" style="border-bottom:1px solid var(--border-light)">
            <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($stData['name']); ?>
        </div>
        <table class="table table-hover mb-0">
            <?php echo renderEinkaufTableHeader(false); ?>
            <tbody>
                <?php foreach ($stData['items'] as $e): renderEinkaufRow($e, $statusLabels, $festId, false); endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php else: ?>

<?php foreach ($grouped as $key => $grp): ?>
<div class="card mb-3 einkauf-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-<?php echo $ansicht === 'station' ? 'geo-alt' : 'tag'; ?>"></i>
            <?php echo htmlspecialchars($grp['name']); ?>
        </h5>
        <span class="text-muted small">
            <?php echo number_format(array_sum(array_map(fn($i) => (float)($i['preis_gesamt'] ?? 0), $grp['items'])), 2, ',', '.'); ?> €
        </span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <?php echo renderEinkaufTableHeader($ansicht === 'station'); ?>
            <tbody>
                <?php foreach ($grp['items'] as $e): renderEinkaufRow($e, $statusLabels, $festId, $ansicht === 'station'); endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Bestellliste Modal -->
<div class="modal fade" id="bestelllisteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-printer"></i> Bestellliste erstellen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Lieferant</label>
                    <select class="form-select" id="bl-lieferant">
                        <option value="">Alle Lieferanten</option>
                        <?php foreach ($lieferanten as $l): ?>
                        <option value="<?php echo htmlspecialchars($l); ?>"><?php echo htmlspecialchars($l); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status einschließen</label>
                    <div class="d-flex gap-3">
                        <?php foreach ($statusLabels as $sv => $sl): ?>
                        <div class="form-check">
                            <input class="form-check-input bl-status" type="checkbox" id="bl-s-<?php echo $sv; ?>"
                                   value="<?php echo $sv; ?>" <?php echo $sv !== 'storniert' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="bl-s-<?php echo $sv; ?>">
                                <span class="badge bg-<?php echo $sl['badge']; ?>"><?php echo $sl['label']; ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="bl-stationen" checked>
                    <label class="form-check-label" for="bl-stationen">Nach Stationen gliedern</label>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="bl-notizen" checked>
                    <label class="form-check-label" for="bl-notizen">Notizen anzeigen</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" id="btn-open-bestellliste">
                    <i class="bi bi-printer"></i> Bestellliste öffnen
                </button>
            </div>
        </div>
    </div>
</div>

<?php
function renderEinkaufTableHeader(bool $showLieferant = true): string {
    $lh = $showLieferant ? '<th>Lieferant</th>' : '';
    return '<thead><tr><th>Bezeichnung</th><th>Menge</th><th>Preis</th>' . $lh . '<th>Status</th>
            <th class="text-center"><i class="bi bi-bookmark"></i></th>
            <th class="text-end">Aktionen</th></tr></thead>';
}

function renderEinkaufRow(array $e, array $statusLabels, int $festId, bool $showLieferant = true): void {
    $sl = $statusLabels[$e['status']] ?? ['label' => $e['status'], 'badge' => 'secondary'];
    $lf = $showLieferant ? '<td class="small">' . htmlspecialchars($e['lieferant'] ?? '–') . '</td>' : '';
    ?>
    <tr class="einkauf-row" data-bezeichnung="<?php echo strtolower(htmlspecialchars($e['bezeichnung'])); ?>">
        <td>
            <strong <?php if ($e['notizen']): ?>data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="<?php echo htmlspecialchars($e['notizen']); ?>" style="cursor:default;border-bottom:1px dotted #adb5bd"<?php endif; ?>>
                <?php echo htmlspecialchars($e['bezeichnung']); ?>
            </strong>
            <?php if (!empty($e['station_name']) && !$showLieferant && false): ?>
            <div class="small text-muted"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($e['station_name']); ?></div>
            <?php endif; ?>
        </td>
        <td class="small"><?php echo $e['menge'] ? number_format((int)$e['menge'], 0, ',', '.') . ' ' . htmlspecialchars($e['einheit'] ?? '') : '–'; ?></td>
        <td><?php echo $e['preis_gesamt'] !== null ? number_format($e['preis_gesamt'], 2, ',', '.') . ' €' : '–'; ?></td>
        <?php echo $lf; ?>
        <td><span class="badge bg-<?php echo $sl['badge']; ?>"><?php echo $sl['label']; ?></span></td>
        <td class="text-center">
            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
            <button class="btn-vorlage-toggle"
                    data-id="<?php echo $e['id']; ?>"
                    data-vorlage="<?php echo $e['ist_vorlage'] ? '1' : '0'; ?>"
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
                <a href="fest_einkauf_bearbeiten.php?id=<?php echo $e['id']; ?>&fest_id=<?php echo $festId; ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
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
    <?php
}
?>

<?php include 'includes/footer.php'; ?>
<script>
var festId    = <?php echo $festId; ?>;
var STORE_KEY = 'einkauf_filter_<?php echo $festId; ?>';

// Sofortfilter: Selects und Bezeichnung submiten das Formular
var form = document.getElementById('filterForm');
['filter-lieferant','filter-station','filter-status','filter-vorlage'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('change', function() { saveFilter(); form.submit(); });
});
// Bezeichnung: submit nach kurzer Pause (Tipp-Debounce)
var bezeichnungTimer;
document.getElementById('filter-bezeichnung').addEventListener('input', function() {
    clearTimeout(bezeichnungTimer);
    bezeichnungTimer = setTimeout(function() { saveFilter(); form.submit(); }, 600);
});

// Filter in localStorage speichern
function saveFilter() {
    var data = {
        bezeichnung: document.getElementById('filter-bezeichnung').value,
        lieferant:   document.getElementById('filter-lieferant').value,
        station_id:  document.getElementById('filter-station').value,
        status:      document.getElementById('filter-status').value,
        ist_vorlage: document.getElementById('filter-vorlage').value,
    };
    localStorage.setItem(STORE_KEY, JSON.stringify(data));
}

// Reset löscht auch localStorage
document.getElementById('btn-reset').addEventListener('click', function() {
    localStorage.removeItem(STORE_KEY);
});

// Beim Laden: gespeicherten Filter nur anwenden wenn keine GET-Parameter aktiv sind
// (wenn GET-Parameter da sind hat PHP sie bereits angewendet)
var hasGetFilter = <?php echo (!empty($_GET['bezeichnung']) || !empty($_GET['lieferant']) || !empty($_GET['station_id']) || !empty($_GET['status']) || !empty($_GET['ist_vorlage'])) ? 'true' : 'false'; ?>;
if (!hasGetFilter) {
    var saved = localStorage.getItem(STORE_KEY);
    if (saved) {
        try {
            var d = JSON.parse(saved);
            if (d.bezeichnung) document.getElementById('filter-bezeichnung').value = d.bezeichnung;
            if (d.lieferant)   document.getElementById('filter-lieferant').value   = d.lieferant;
            if (d.station_id)  document.getElementById('filter-station').value     = d.station_id;
            if (d.status)      document.getElementById('filter-status').value      = d.status;
            if (d.ist_vorlage) document.getElementById('filter-vorlage').value     = d.ist_vorlage;
            // Nur submiten wenn wirklich ein Wert gespeichert ist
            if (d.bezeichnung || d.lieferant || d.station_id || d.status || d.ist_vorlage) {
                form.submit();
            }
        } catch(e) {}
    }
}

// Live-Suche (für Bezeichnung ohne Submit — nur clientseitig)
// Wird jetzt über Debounce+Submit gehandelt, kein clientseitiger Filter mehr nötig

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
        new bootstrap.Tooltip(el, { trigger: 'hover', customClass: 'tooltip-wide' });
    });

    // Vorlage-Toggle
    document.querySelectorAll('.btn-vorlage-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var btnEl   = this;
            var id      = btnEl.dataset.id;
            var vorlage = btnEl.dataset.vorlage === '1' ? 0 : 1;
            fetch('api/fest_einkauf_vorlage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id + '&ist_vorlage=' + vorlage
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    btnEl.dataset.vorlage = data.ist_vorlage ? '1' : '0';
                    btnEl.querySelector('i').className = data.ist_vorlage ? 'bi bi-bookmark-fill' : 'bi bi-bookmark';
                    btnEl.style.color = data.ist_vorlage ? '#198754' : '#dee2e6';
                }
            });
        });
    });

    // Bestellliste öffnen
    document.getElementById('btn-open-bestellliste').addEventListener('click', function() {
        var lieferant = document.getElementById('bl-lieferant').value;
        var stationen = document.getElementById('bl-stationen').checked ? '1' : '0';
        var notizen   = document.getElementById('bl-notizen').checked   ? '1' : '0';
        var statusArr = [];
        document.querySelectorAll('.bl-status:checked').forEach(function(cb) { statusArr.push(cb.value); });
        var url = 'fest_einkauf_bestellliste.php?fest_id=' + festId
            + '&lieferant=' + encodeURIComponent(lieferant)
            + '&stationen=' + stationen
            + '&notizen=' + notizen
            + '&status=' + encodeURIComponent(statusArr.join(','));
        window.open(url, '_blank');
    });
});
</script>
