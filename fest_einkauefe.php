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
$filter = [];
if (!empty($_GET['status']))      $filter['status']      = $_GET['status'];
if (!empty($_GET['kategorie_id'])) $filter['kategorie_id'] = (int)$_GET['kategorie_id'];

$grouped    = $eObj->getByFestGrouped($festId, $filter);
$summen     = $eObj->getSummen($festId);
$kategorien = $eObj->getKategorien();

$statusLabels = [
    'geplant'  => ['label' => 'Geplant',   'badge' => 'warning'],
    'bestellt' => ['label' => 'Bestellt',  'badge' => 'info'],
    'erhalten' => ['label' => 'Erhalten',  'badge' => 'success'],
    'storniert'=> ['label' => 'Storniert', 'badge' => 'danger'],
];

include 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="feste.php">Feste</a></li>
        <li class="breadcrumb-item"><a href="fest_detail.php?id=<?php echo $festId; ?>"><?php echo htmlspecialchars($fest['name']); ?></a></li>
        <li class="breadcrumb-item active">Einkäufe</li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-cart3"></i> Einkäufe – <?php echo htmlspecialchars($fest['name']); ?></h1>
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
                <label class="form-label">Kategorie</label>
                <select name="kategorie_id" class="form-select form-select-sm">
                    <option value="">Alle Kategorien</option>
                    <?php foreach ($kategorien as $k): ?>
                    <option value="<?php echo $k['id']; ?>" <?php echo ($_GET['kategorie_id'] ?? '') == $k['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($k['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Alle Status</option>
                    <?php foreach ($statusLabels as $sv => $sl): ?>
                    <option value="<?php echo $sv; ?>" <?php echo ($_GET['status'] ?? '') === $sv ? 'selected' : ''; ?>><?php echo $sl['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filtern</button>
                <a href="fest_einkauefe.php?fest_id=<?php echo $festId; ?>" class="btn btn-sm btn-outline-secondary">Zurücksetzen</a>
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
                    <th class="text-center" title="Vorlage für Vorjahr-Kopieren">
                        <i class="bi bi-copy" title="Vorlage"></i>
                    </th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kat['items'] as $e): ?>
                <?php $sl = $statusLabels[$e['status']] ?? ['label' => $e['status'], 'badge' => 'secondary']; ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($e['bezeichnung']); ?></strong>
                        <?php if ($e['notizen']): ?>
                        <div class="small text-muted"><?php echo htmlspecialchars($e['notizen']); ?></div>
                        <?php endif; ?>
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
                        <?php if ($e['ist_vorlage']): ?>
                        <i class="bi bi-copy text-success" title="Wird beim Kopieren übernommen"></i>
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

<?php include 'includes/footer.php'; ?>
