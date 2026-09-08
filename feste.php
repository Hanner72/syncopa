<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'lesen');

$festObj = new Fest();

$filterJahr   = $_GET['jahr'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$filter = [];
if ($filterJahr)   $filter['jahr']   = $filterJahr;
if ($filterStatus) $filter['status'] = $filterStatus;

$feste = $festObj->getAll($filter);
$jahre = $festObj->getJahre();

// Stat-Karten
$alle     = $festObj->getAll();
$aktiv    = array_filter($alle, fn($f) => $f['status'] === 'aktiv');
$geplant  = array_filter($alle, fn($f) => $f['status'] === 'geplant');

include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-stars"></i> Festverwaltung</h1>
    <?php if (Session::checkPermission('fest', 'schreiben')): ?>
    <a href="fest_bearbeiten.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Neues Fest
    </a>
    <?php endif; ?>
</div>

<!-- Stat-Karten -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-primary">
            <div class="card-body">
                <div>
                    <h6>Feste gesamt</h6>
                    <h2><?php echo count($alle); ?></h2>
                </div>
                <i class="bi bi-stars stat-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-success">
            <div class="card-body">
                <div>
                    <h6>Aktiv</h6>
                    <h2><?php echo count($aktiv); ?></h2>
                </div>
                <i class="bi bi-play-circle stat-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-warning">
            <div class="card-body">
                <div>
                    <h6>Geplant</h6>
                    <h2><?php echo count($geplant); ?></h2>
                </div>
                <i class="bi bi-calendar-event stat-icon text-warning"></i>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card border-info">
            <div class="card-body">
                <div>
                    <h6>Aktuelles Jahr</h6>
                    <h2><?php echo date('Y'); ?></h2>
                </div>
                <i class="bi bi-calendar3 stat-icon text-info"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Jahr</label>
                <select name="jahr" class="form-select form-select-sm">
                    <option value="">Alle Jahre</option>
                    <?php foreach ($jahre as $j): ?>
                    <option value="<?php echo $j['jahr']; ?>" <?php echo $filterJahr == $j['jahr'] ? 'selected' : ''; ?>>
                        <?php echo $j['jahr']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Alle Status</option>
                    <option value="geplant"       <?php echo $filterStatus === 'geplant'       ? 'selected' : ''; ?>>Geplant</option>
                    <option value="aktiv"          <?php echo $filterStatus === 'aktiv'          ? 'selected' : ''; ?>>Aktiv</option>
                    <option value="abgeschlossen"  <?php echo $filterStatus === 'abgeschlossen'  ? 'selected' : ''; ?>>Abgeschlossen</option>
                    <option value="abgesagt"       <?php echo $filterStatus === 'abgesagt'       ? 'selected' : ''; ?>>Abgesagt</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filtern</button>
                <a href="feste.php" class="btn btn-sm btn-outline-secondary">Zurücksetzen</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabelle -->
<div class="card">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-list-ul"></i> Alle Feste</h5></div>
    <div class="card-body p-0">
        <?php if (empty($feste)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-stars fs-1 d-block mb-2 opacity-25"></i>
            Noch keine Feste erfasst.
            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
            <div class="mt-2"><a href="fest_bearbeiten.php" class="btn btn-sm btn-primary">Erstes Fest anlegen</a></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0" id="festeTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Jahr</th>
                    <th>Datum</th>
                    <th>Ort</th>
                    <th>Status</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feste as $f): ?>
                <?php
                    $statusBadge = [
                        'geplant'      => 'bg-warning',
                        'aktiv'        => 'bg-success',
                        'abgeschlossen'=> 'bg-secondary',
                        'abgesagt'     => 'bg-danger',
                    ][$f['status']] ?? 'bg-secondary';
                    $statusLabel = [
                        'geplant'      => 'Geplant',
                        'aktiv'        => 'Aktiv',
                        'abgeschlossen'=> 'Abgeschlossen',
                        'abgesagt'     => 'Abgesagt',
                    ][$f['status']] ?? $f['status'];
                ?>
                <tr>
                    <td>
                        <a href="fest_detail.php?id=<?php echo $f['id']; ?>" class="fw-semibold">
                            <?php echo htmlspecialchars($f['name']); ?>
                        </a>
                    </td>
                    <td><?php echo $f['jahr']; ?></td>
                    <td>
                        <?php echo date('d.m.Y', strtotime($f['datum_von'])); ?>
                        <?php if ($f['datum_bis'] && $f['datum_bis'] !== $f['datum_von']): ?>
                        – <?php echo date('d.m.Y', strtotime($f['datum_bis'])); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($f['ort'] ?? '–'); ?></td>
                    <td><span class="badge <?php echo $statusBadge; ?>"><?php echo $statusLabel; ?></span></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="fest_detail.php?id=<?php echo $f['id']; ?>" class="btn btn-outline-primary" title="Details">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                            <a href="fest_bearbeiten.php?id=<?php echo $f['id']; ?>" class="btn btn-outline-secondary" title="Bearbeiten">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (Session::checkPermission('fest', 'loeschen')): ?>
                            <form method="POST" action="fest_loeschen.php" class="d-inline"
                                  onsubmit="return confirm('Fest «<?php echo htmlspecialchars(addslashes($f['name'])); ?>» wirklich löschen? Alle zugehörigen Daten werden gelöscht.')">
                                <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger" title="Löschen">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $('#festeTable').DataTable({ order: [[1, 'desc'], [2, 'desc']] });
}
</script>
