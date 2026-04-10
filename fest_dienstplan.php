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

$dpObj      = new FestDienstplan();
$stationObj = new FestStation();

$stationen  = $stationObj->getByFest($festId);
$daten      = $dpObj->getDaten($festId);
$grid       = $dpObj->getGridData($festId);

// Datumsfilter
$filterDatum = $_GET['datum'] ?? ($daten[0] ?? null);

// Alle Schichten für diesen Tag holen (alle Stationen)
$tagesSchichten = $filterDatum ? ($grid[$filterDatum] ?? []) : [];

include 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="feste.php">Feste</a></li>
        <li class="breadcrumb-item"><a href="fest_detail.php?id=<?php echo $festId; ?>"><?php echo htmlspecialchars($fest['name']); ?></a></li>
        <li class="breadcrumb-item active">Dienstplan</li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-table"></i> Dienstplan – <?php echo htmlspecialchars($fest['name']); ?></h1>
    <?php if (Session::checkPermission('fest', 'schreiben')): ?>
    <a href="fest_dienstplan_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Schicht hinzufügen
    </a>
    <?php endif; ?>
</div>

<?php if (empty($stationen)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    Bitte zuerst <a href="fest_stationen.php?fest_id=<?php echo $festId; ?>">Stationen anlegen</a>, bevor der Dienstplan befüllt werden kann.
</div>
<?php elseif (empty($daten)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-table fs-1 d-block mb-2 opacity-25"></i>
    Noch keine Schichten eingetragen.
    <?php if (Session::checkPermission('fest', 'schreiben')): ?>
    <div class="mt-2"><a href="fest_dienstplan_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-sm btn-primary">Erste Schicht eintragen</a></div>
    <?php endif; ?>
</div>
<?php else: ?>

<!-- Datum-Tabs -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($daten as $d): ?>
            <a href="fest_dienstplan.php?fest_id=<?php echo $festId; ?>&datum=<?php echo $d; ?>"
               class="btn btn-sm <?php echo $filterDatum === $d ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                <?php echo date('D d.m.', strtotime($d)); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Dienstplan-Grid -->
<?php if ($filterDatum): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-calendar-day"></i> <?php echo date('l, d. F Y', strtotime($filterDatum)); ?></h5>
        <span class="text-muted small"><?php echo array_sum(array_map('count', $tagesSchichten)); ?> Schichten</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($tagesSchichten)): ?>
        <div class="text-center text-muted py-4">Für dieses Datum noch keine Schichten eingetragen.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr class="table-light">
                        <th style="width:180px">Station</th>
                        <th>Schichten / Mitarbeiter</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stationen as $s): ?>
                    <?php $schichten = $tagesSchichten[$s['id']] ?? []; ?>
                    <tr>
                        <td class="align-top">
                            <strong><?php echo htmlspecialchars($s['name']); ?></strong>
                            <?php if ($s['oeffnung_von'] && $s['oeffnung_bis']): ?>
                            <div class="small text-muted"><?php echo substr($s['oeffnung_von'],0,5); ?> – <?php echo substr($s['oeffnung_bis'],0,5); ?></div>
                            <?php endif; ?>
                            <div class="small mt-1">
                                <span class="badge bg-<?php echo count($schichten) >= $s['benoetigte_helfer'] ? 'success' : 'warning'; ?>">
                                    <?php echo count($schichten); ?>/<?php echo $s['benoetigte_helfer']; ?> Helfer
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php if (empty($schichten)): ?>
                            <span class="text-muted small">Keine Schichten</span>
                            <?php else: ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($schichten as $sch): ?>
                                <div class="border rounded px-2 py-1 small" style="background:var(--bg-body)">
                                    <strong><?php echo htmlspecialchars($sch['mitarbeiter_name']); ?></strong>
                                    <div class="text-muted"><?php echo substr($sch['zeit_von'],0,5); ?> – <?php echo substr($sch['zeit_bis'],0,5); ?></div>
                                    <?php if ($sch['mitarbeiter_funktion']): ?>
                                    <div class="text-muted" style="font-size:10px"><?php echo htmlspecialchars($sch['mitarbeiter_funktion']); ?></div>
                                    <?php endif; ?>
                                    <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                                    <div class="d-flex gap-1 mt-1">
                                        <a href="fest_dienstplan_bearbeiten.php?id=<?php echo $sch['id']; ?>&fest_id=<?php echo $festId; ?>" class="btn btn-xs btn-outline-secondary" style="font-size:10px;padding:1px 5px">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if (Session::checkPermission('fest', 'loeschen')): ?>
                                        <form method="POST" action="fest_dienstplan_loeschen.php" class="d-inline"
                                              onsubmit="return confirm('Schicht löschen?')">
                                            <input type="hidden" name="id" value="<?php echo $sch['id']; ?>">
                                            <input type="hidden" name="fest_id" value="<?php echo $festId; ?>">
                                            <input type="hidden" name="datum" value="<?php echo $filterDatum; ?>">
                                            <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:10px;padding:1px 5px">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                            <div class="mt-2">
                                <a href="fest_dienstplan_bearbeiten.php?fest_id=<?php echo $festId; ?>&station_id=<?php echo $s['id']; ?>&datum=<?php echo $filterDatum; ?>"
                                   class="btn btn-xs btn-outline-primary" style="font-size:11px;padding:2px 8px">
                                    <i class="bi bi-plus"></i> Schicht
                                </a>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
