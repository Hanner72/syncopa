<?php
// mitglieder.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('mitglieder', 'lesen');

$mitglied = new Mitglied();
$db = Database::getInstance();

// Filter verarbeiten
$filter = [];
if (!empty($_GET['status'])) {
    $filter['status'] = $_GET['status'];
}
if (!empty($_GET['register'])) {
    $filter['register'] = $_GET['register'];
}
if (!empty($_GET['search'])) {
    $filter['search'] = $_GET['search'];
}

$mitglieder = $mitglied->getAll($filter);

// Register für Filter laden
$register = $db->fetchAll("SELECT * FROM register ORDER BY sortierung");

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-people"></i> Mitglieder
    </h1>
    <?php if (Session::checkPermission('mitglieder', 'schreiben')): ?>
    <a href="mitglied_bearbeiten.php" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> Neues Mitglied
    </a>
    <?php endif; ?>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Suche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Name oder Mitgliedsnummer" 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Alle</option>
                    <option value="aktiv" <?php echo ($_GET['status'] ?? '') === 'aktiv' ? 'selected' : ''; ?>>Aktiv</option>
                    <option value="passiv" <?php echo ($_GET['status'] ?? '') === 'passiv' ? 'selected' : ''; ?>>Passiv</option>
                    <option value="ausgetreten" <?php echo ($_GET['status'] ?? '') === 'ausgetreten' ? 'selected' : ''; ?>>Ausgetreten</option>
                    <option value="ehrenmitglied" <?php echo ($_GET['status'] ?? '') === 'ehrenmitglied' ? 'selected' : ''; ?>>Ehrenmitglied</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="register" class="form-label">Register</label>
                <select class="form-select" id="register" name="register">
                    <option value="">Alle Register</option>
                    <?php foreach ($register as $reg): ?>
                    <option value="<?php echo $reg['id']; ?>" <?php echo ($_GET['register'] ?? '') == $reg['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($reg['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Filtern
                </button>
                <a href="mitglieder.php" class="btn btn-secondary">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Mitgliederliste -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="mitgliederTable">
                <thead>
                    <tr>
                        <th>Mitgliedsnr.</th>
                        <th>Name</th>
                        <th>Register</th>
                        <th>Instrumente</th>
                        <th>Kontakt</th>
                        <th>Status</th>
                        <th class="text-end no-print">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mitglieder as $m): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($m['mitgliedsnummer']); ?></strong>
                        </td>
                        <td>
                            <?php 
                            echo htmlspecialchars($m['vorname'] . ' ' . $m['nachname']); 
                            if ($m['geburtsdatum']) {
                                $alter = date_diff(date_create($m['geburtsdatum']), date_create('today'))->y;
                                echo "<br><small class='text-muted'>{$alter} Jahre</small>";
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($m['register_name']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($m['register_name']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($m['instrumente'])): ?>
                                <small><?php echo htmlspecialchars($m['instrumente']); ?></small>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                            <?php if (isset($m['ausgeliehene_instrumente']) && $m['ausgeliehene_instrumente'] > 0): ?>
                                <br><span class="badge bg-warning text-dark">
                                    <i class="bi bi-box"></i> <?php echo $m['ausgeliehene_instrumente']; ?> ausgeliehen
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($m['email']): ?>
                                <a href="mailto:<?php echo htmlspecialchars($m['email']); ?>">
                                    <i class="bi bi-envelope"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($m['mobil']): ?>
                                <a href="tel:<?php echo htmlspecialchars($m['mobil']); ?>">
                                    <i class="bi bi-phone"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusColors = [
                                'aktiv' => 'success',
                                'passiv' => 'warning',
                                'ausgetreten' => 'secondary',
                                'ehrenmitglied' => 'primary'
                            ];
                            $color = $statusColors[$m['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?> badge-status">
                                <?php echo ucfirst($m['status']); ?>
                            </span>
                        </td>
                        <td class="text-end no-print">
                            <div class="table-actions">
                                <a href="mitglied_detail.php?id=<?php echo $m['id']; ?>" 
                                   class="btn btn-sm btn-info" title="Details anzeigen">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (Session::checkPermission('mitglieder', 'schreiben')): ?>
                                <a href="mitglied_bearbeiten.php?id=<?php echo $m['id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (Session::checkPermission('mitglieder', 'loeschen')): ?>
                                <a href="mitglied_loeschen.php?id=<?php echo $m['id']; ?>" 
                                   class="btn btn-sm btn-danger" title="Löschen"
                                   onclick="return confirmDelete('Mitglied wirklich löschen?')">
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

<!-- Statistik -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Statistik</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Mitglieder nach Status</h6>
                        <canvas id="statusChart" height="150"></canvas>
                    </div>
                    <div class="col-md-6">
                        <h6>Mitglieder nach Register</h6>
                        <canvas id="registerChart" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#mitgliederTable').DataTable({
        order: [[1, 'asc']], // Nach Name sortieren
        columnDefs: [
            { targets: -1, orderable: false } // Aktionen-Spalte nicht sortierbar
        ]
    });
});

// Statistik-Charts
<?php
$stats = $mitglied->getStatistik();
?>

// Status Chart
new Chart(document.getElementById('statusChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($stats['status'], 'status')); ?>,
        datasets: [{
            label: 'Anzahl Mitglieder',
            data: <?php echo json_encode(array_column($stats['status'], 'anzahl')); ?>,
            backgroundColor: ['#198754', '#ffc107', '#6c757d', '#0d6efd']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        }
    }
});

// Register Chart
new Chart(document.getElementById('registerChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($stats['register'], 'name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($stats['register'], 'anzahl')); ?>,
            backgroundColor: [
                '#0d6efd', '#6c757d', '#198754', '#ffc107',
                '#dc3545', '#0dcaf0', '#6f42c1', '#fd7e14'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>

<?php include 'includes/footer.php'; ?>
