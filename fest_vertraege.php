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

$vObj     = new FestVertrag();
$vertraege = $vObj->getByFest($festId);
$summen   = $vObj->getHonorarSummen($festId);

$zahlungsLabels = [
    'offen'      => ['label' => 'Offen',       'badge' => 'danger'],
    'teilweise'  => ['label' => 'Teilweise',   'badge' => 'warning'],
    'bezahlt'    => ['label' => 'Bezahlt',     'badge' => 'success'],
    'storniert'  => ['label' => 'Storniert',   'badge' => 'secondary'],
];

include 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="feste.php">Feste</a></li>
        <li class="breadcrumb-item"><a href="fest_detail.php?id=<?php echo $festId; ?>"><?php echo htmlspecialchars($fest['name']); ?></a></li>
        <li class="breadcrumb-item active">Verträge</li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-file-earmark-text"></i> Verträge – <?php echo htmlspecialchars($fest['name']); ?></h1>
    <?php if (Session::checkPermission('fest', 'schreiben')): ?>
    <a href="fest_vertrag_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Vertrag hinzufügen
    </a>
    <?php endif; ?>
</div>

<!-- Stat-Karten -->
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-primary">
            <div class="card-body">
                <div><h6>Gesamt Honorar</h6><h2><?php echo number_format($summen['gesamt'], 2, ',', '.'); ?> €</h2></div>
                <i class="bi bi-currency-euro stat-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-danger">
            <div class="card-body">
                <div><h6>Offen</h6><h2><?php echo number_format($summen['offen'], 2, ',', '.'); ?> €</h2></div>
                <i class="bi bi-hourglass stat-icon text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-success">
            <div class="card-body">
                <div><h6>Bezahlt</h6><h2><?php echo number_format($summen['bezahlt'], 2, ',', '.'); ?> €</h2></div>
                <i class="bi bi-check-circle stat-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-info">
            <div class="card-body">
                <div><h6>Verträge</h6><h2><?php echo count($vertraege); ?></h2></div>
                <i class="bi bi-file-earmark-text stat-icon text-info"></i>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($vertraege)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-file-earmark-text fs-1 d-block mb-2 opacity-25"></i>
            Noch keine Verträge erfasst.
            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
            <div class="mt-2"><a href="fest_vertrag_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-sm btn-primary">Ersten Vertrag anlegen</a></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0" id="vertraegeTable">
            <thead>
                <tr>
                    <th>Band / Gruppe</th>
                    <th>Auftritt</th>
                    <th>Honorar</th>
                    <th>Zahlung</th>
                    <th>Dokument</th>
                    <th>Notizen</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vertraege as $v): ?>
                <?php $zl = $zahlungsLabels[$v['zahlungsstatus']] ?? ['label' => $v['zahlungsstatus'], 'badge' => 'secondary']; ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($v['band_name']); ?></strong></td>
                    <td class="small">
                        <?php if ($v['auftritt_datum']): ?>
                        <?php echo date('d.m.Y', strtotime($v['auftritt_datum'])); ?>
                        <?php if ($v['auftritt_zeit']): ?>
                        <br><?php echo substr($v['auftritt_zeit'], 0, 5); ?> Uhr
                        <?php endif; ?>
                        <?php else: ?> – <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $v['honorar'] !== null ? number_format($v['honorar'], 2, ',', '.') . ' €' : '–'; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $zl['badge']; ?>"><?php echo $zl['label']; ?></span>
                        <?php if ($v['zahlungsdatum']): ?>
                        <div class="small text-muted"><?php echo date('d.m.Y', strtotime($v['zahlungsdatum'])); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($v['dokument_pfad'] && file_exists($v['dokument_pfad'])): ?>
                        <a href="api/fest_vertrag_download.php?id=<?php echo $v['id']; ?>" class="btn btn-xs btn-outline-primary" style="font-size:11px;padding:2px 8px" title="<?php echo htmlspecialchars($v['dokument_name']); ?>">
                            <i class="bi bi-download"></i> PDF
                        </a>
                        <?php else: ?>
                        <span class="text-muted small">–</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?php echo htmlspecialchars(mb_substr($v['notizen'] ?? '', 0, 60)); ?></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                            <a href="fest_vertrag_bearbeiten.php?id=<?php echo $v['id']; ?>&fest_id=<?php echo $festId; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (Session::checkPermission('fest', 'loeschen')): ?>
                            <form method="POST" action="fest_vertrag_loeschen.php" class="d-inline"
                                  onsubmit="return confirm('Vertrag «<?php echo htmlspecialchars(addslashes($v['band_name'])); ?>» löschen?')">
                                <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
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
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $('#vertraegeTable').DataTable({ order: [[1, 'asc']] });
}
</script>
