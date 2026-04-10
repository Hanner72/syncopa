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

$stationObj = new FestStation();
$stationen  = $stationObj->getByFest($festId);

include 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="feste.php">Feste</a></li>
        <li class="breadcrumb-item"><a href="fest_detail.php?id=<?php echo $festId; ?>"><?php echo htmlspecialchars($fest['name']); ?></a></li>
        <li class="breadcrumb-item active">Stationen</li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-shop"></i> Stationen – <?php echo htmlspecialchars($fest['name']); ?></h1>
    <?php if (Session::checkPermission('fest', 'schreiben')): ?>
    <a href="fest_station_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Station hinzufügen
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($stationen)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-shop fs-1 d-block mb-2 opacity-25"></i>
            Noch keine Stationen erfasst.
            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
            <div class="mt-2"><a href="fest_station_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-sm btn-primary">Erste Station anlegen</a></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0" id="stationenTable">
            <thead>
                <tr>
                    <th>Sort.</th>
                    <th>Name</th>
                    <th>Beschreibung</th>
                    <th class="text-center">Benötigte Helfer</th>
                    <th>Öffnungszeiten</th>
                    <th class="text-center">Schichten</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stationen as $s): ?>
                <tr>
                    <td class="text-muted"><?php echo $s['sortierung']; ?></td>
                    <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                    <td class="text-muted small"><?php echo htmlspecialchars($s['beschreibung'] ?? '–'); ?></td>
                    <td class="text-center">
                        <span class="badge bg-primary"><?php echo $s['benoetigte_helfer']; ?></span>
                    </td>
                    <td class="small">
                        <?php if ($s['oeffnung_von'] && $s['oeffnung_bis']): ?>
                        <?php echo substr($s['oeffnung_von'],0,5); ?> – <?php echo substr($s['oeffnung_bis'],0,5); ?>
                        <?php else: ?>
                        <span class="text-muted">–</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?php echo $s['schichten_anzahl']; ?></span>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                            <a href="fest_station_bearbeiten.php?id=<?php echo $s['id']; ?>&fest_id=<?php echo $festId; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (Session::checkPermission('fest', 'loeschen')): ?>
                            <form method="POST" action="fest_station_loeschen.php" class="d-inline"
                                  onsubmit="return confirm('Station «<?php echo htmlspecialchars(addslashes($s['name'])); ?>» löschen?')">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
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
    $('#stationenTable').DataTable({ order: [[0, 'asc']] });
}
</script>
