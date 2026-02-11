<?php
// ausrueckungen.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('ausrueckungen', 'lesen');

$ausrueckung = new Ausrueckung();

// Filter
$filter = [];
if (!empty($_GET['typ'])) {
    $filter['typ'] = $_GET['typ'];
}
if (!empty($_GET['von_datum'])) {
    $filter['von_datum'] = $_GET['von_datum'];
}
if (!empty($_GET['bis_datum'])) {
    $filter['bis_datum'] = $_GET['bis_datum'];
}
if (!empty($_GET['status'])) {
    $filter['status'] = $_GET['status'];
}

$ausrueckungen = $ausrueckung->getAll($filter);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-flag"></i> Ausrückungen
    </h1>
    <div>
        <a href="kalender_abonnement.php" class="btn btn-outline-primary me-2">
            <i class="bi bi-calendar-check"></i> Kalender-Abo
        </a>
        <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
        <a href="ausrueckung_bearbeiten.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Neue Ausrückung
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="typ" class="form-label">Typ</label>
                <select class="form-select" id="typ" name="typ">
                    <option value="">Alle Typen</option>
                    <option value="Probe" <?php echo ($_GET['typ'] ?? '') === 'Probe' ? 'selected' : ''; ?>>Probe</option>
                    <option value="Konzert" <?php echo ($_GET['typ'] ?? '') === 'Konzert' ? 'selected' : ''; ?>>Konzert</option>
                    <option value="Ausrückung" <?php echo ($_GET['typ'] ?? '') === 'Ausrückung' ? 'selected' : ''; ?>>Ausrückung</option>
                    <option value="Fest" <?php echo ($_GET['typ'] ?? '') === 'Fest' ? 'selected' : ''; ?>>Fest</option>
                    <option value="Wertung" <?php echo ($_GET['typ'] ?? '') === 'Wertung' ? 'selected' : ''; ?>>Wertung</option>
                    <option value="Sonstiges" <?php echo ($_GET['typ'] ?? '') === 'Sonstiges' ? 'selected' : ''; ?>>Sonstiges</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Alle Status</option>
                    <option value="geplant" <?php echo ($_GET['status'] ?? '') === 'geplant' ? 'selected' : ''; ?>>Geplant</option>
                    <option value="bestaetigt" <?php echo ($_GET['status'] ?? '') === 'bestaetigt' ? 'selected' : ''; ?>>Bestätigt</option>
                    <option value="abgesagt" <?php echo ($_GET['status'] ?? '') === 'abgesagt' ? 'selected' : ''; ?>>Abgesagt</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="von_datum" class="form-label">Von</label>
                <input type="date" class="form-control" id="von_datum" name="von_datum" 
                       value="<?php echo htmlspecialchars($_GET['von_datum'] ?? ''); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="bis_datum" class="form-label">Bis</label>
                <input type="date" class="form-control" id="bis_datum" name="bis_datum" 
                       value="<?php echo htmlspecialchars($_GET['bis_datum'] ?? ''); ?>">
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Filtern
                </button>
                <a href="ausrueckungen.php" class="btn btn-secondary">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Liste -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive p-2">
            <table class="table table-hover" id="ausrueckungenTable">
                <thead>
                    <tr>
                        <th>Datum/Zeit</th>
                        <th>Titel</th>
                        <th>Typ</th>
                        <th>Ort</th>
                        <th>Status</th>
                        <th>Anwesenheit</th>
                        <th class="text-end no-print">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ausrueckungen as $a): ?>
                    <tr>
                        <td>
                            <strong><?php echo date('d.m.Y', strtotime($a['start_datum'])); ?></strong><br>
                            <small class="text-muted">
                                <?php echo date('H:i', strtotime($a['start_datum'])); ?> Uhr
                            </small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($a['titel']); ?></strong>
                            <?php if ($a['beschreibung']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($a['beschreibung'], 0, 50)); ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $typeColors = [
                                'Probe' => 'secondary',
                                'Konzert' => 'primary',
                                'Ausrückung' => 'success',
                                'Fest' => 'warning',
                                'Wertung' => 'danger',
                                'Sonstiges' => 'info'
                            ];
                            $color = $typeColors[$a['typ']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo htmlspecialchars($a['typ']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($a['ort']): ?>
                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($a['ort']); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusColors = [
                                'geplant' => 'warning',
                                'bestaetigt' => 'success',
                                'abgesagt' => 'danger'
                            ];
                            $statusColor = $statusColors[$a['status']] ?? 'secondary';
                            $statusText = [
                                'geplant' => 'Geplant',
                                'bestaetigt' => 'Bestätigt',
                                'abgesagt' => 'Abgesagt'
                            ];
                            ?>
                            <span class="badge bg-<?php echo $statusColor; ?>">
                                <?php echo $statusText[$a['status']]; ?>
                            </span>
                        </td>
                        <td>
                            <small>
                                <span class="text-success">✓ <?php echo $a['zugesagt'] ?? 0; ?></span> /
                                <span class="text-danger">✗ <?php echo $a['abgesagt'] ?? 0; ?></span>
                            </small>
                        </td>
                        <td class="text-end no-print">
                            <div class="table-actions">
                                <a href="ausrueckung_detail.php?id=<?php echo $a['id']; ?>" 
                                   class="btn btn-sm btn-info" title="Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
                                <a href="ausrueckung_bearbeiten.php?id=<?php echo $a['id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (Session::checkPermission('ausrueckungen', 'loeschen')): ?>
                                <a href="ausrueckung_loeschen.php?id=<?php echo $a['id']; ?>" 
                                   class="btn btn-sm btn-danger" title="Löschen"
                                   onclick="return confirmDelete('Ausrückung wirklich löschen?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#ausrueckungenTable').DataTable({
        order: [[0, 'desc']]
    });
});
</script>
