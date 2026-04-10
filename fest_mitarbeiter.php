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

$maObj      = new FestMitarbeiter();
$mitarbeiter = $maObj->getByFest($festId);

$intern  = array_filter($mitarbeiter, fn($m) => !$m['ist_extern']);
$extern  = array_filter($mitarbeiter, fn($m) => $m['ist_extern']);

include 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="feste.php">Feste</a></li>
        <li class="breadcrumb-item"><a href="fest_detail.php?id=<?php echo $festId; ?>"><?php echo htmlspecialchars($fest['name']); ?></a></li>
        <li class="breadcrumb-item active">Mitarbeiter</li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-people"></i> Mitarbeiter – <?php echo htmlspecialchars($fest['name']); ?></h1>
    <?php if (Session::checkPermission('fest', 'schreiben')): ?>
    <a href="fest_mitarbeiter_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Mitarbeiter hinzufügen
    </a>
    <?php endif; ?>
</div>

<!-- Stat -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="card stat-card border-primary">
            <div class="card-body">
                <div><h6>Gesamt</h6><h2><?php echo count($mitarbeiter); ?></h2></div>
                <i class="bi bi-people stat-icon text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-success">
            <div class="card-body">
                <div><h6>Vereinsmitglieder</h6><h2><?php echo count($intern); ?></h2></div>
                <i class="bi bi-person-check stat-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card border-warning">
            <div class="card-body">
                <div><h6>Externe</h6><h2><?php echo count($extern); ?></h2></div>
                <i class="bi bi-person-plus stat-icon text-warning"></i>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($mitarbeiter)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
            Noch keine Mitarbeiter erfasst.
            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
            <div class="mt-2"><a href="fest_mitarbeiter_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-sm btn-primary">Ersten Mitarbeiter hinzufügen</a></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0" id="mitarbeiterTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Funktion</th>
                    <th>Typ</th>
                    <th>Kontakt</th>
                    <th>Notizen</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mitarbeiter as $m): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($m['vollname']); ?></strong>
                        <?php if (!$m['ist_extern'] && $m['mitgliedsnummer']): ?>
                        <br><code class="small"><?php echo htmlspecialchars($m['mitgliedsnummer']); ?></code>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($m['funktion'] ?? '–'); ?></td>
                    <td>
                        <?php if ($m['ist_extern']): ?>
                        <span class="badge bg-warning">Extern</span>
                        <?php else: ?>
                        <span class="badge bg-success">Mitglied</span>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if ($m['telefon']): ?><div><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($m['telefon']); ?></div><?php endif; ?>
                        <?php if ($m['email']): ?><div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($m['email']); ?></div><?php endif; ?>
                    </td>
                    <td class="small text-muted"><?php echo htmlspecialchars($m['notizen'] ?? ''); ?></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                            <a href="fest_mitarbeiter_bearbeiten.php?id=<?php echo $m['id']; ?>&fest_id=<?php echo $festId; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (Session::checkPermission('fest', 'loeschen')): ?>
                            <form method="POST" action="fest_mitarbeiter_loeschen.php" class="d-inline"
                                  onsubmit="return confirm('Mitarbeiter «<?php echo htmlspecialchars(addslashes($m['vollname'])); ?>» entfernen?')">
                                <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
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
    $('#mitarbeiterTable').DataTable({ order: [[0, 'asc']] });
}
</script>
