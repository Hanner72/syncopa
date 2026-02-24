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

// Statistiken für Charts
$stats = $mitglied->getStatistik();
$statusLabels = array_column($stats['status'] ?? [], 'status');
$statusData = array_column($stats['status'] ?? [], 'anzahl');
$registerLabels = array_column($stats['register'] ?? [], 'name');
$registerData = array_column($stats['register'] ?? [], 'anzahl');

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
                            echo htmlspecialchars($m['nachname'] . ' ' . $m['vorname']); 
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
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($m['instrumente_anzahl']) && $m['instrumente_anzahl'] > 0): ?>
                                <span class="badge bg-secondary" 
                                      data-bs-toggle="tooltip" 
                                      data-bs-placement="top" 
                                      data-bs-html="true"
                                      title="<?php echo htmlspecialchars($m['instrumente_liste']); ?>">
                                    <i class="bi bi-music-note"></i> <?php echo $m['instrumente_anzahl']; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($m['email']): ?>
                                <a href="mailto:<?php echo htmlspecialchars($m['email']); ?>" class="text-muted">
                                    <i class="bi bi-envelope"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($m['mobil']): ?>
                                <a href="tel:<?php echo htmlspecialchars($m['mobil']); ?>" class="text-muted ms-1">
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
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo ucfirst($m['status']); ?>
                            </span>
                        </td>
                        <td class="text-end no-print">
                            <a href="mitglied_detail.php?id=<?php echo $m['id']; ?>" 
                               class="btn btn-sm btn-info" title="Details">
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
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-bar-chart me-2"></i>Nach Status
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pie-chart me-2"></i>Nach Register
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="registerChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#mitgliederTable').DataTable({
        order: [[1, 'asc']],
        columnDefs: [
            { targets: -1, orderable: false }
        ]
    });
    
    // Bootstrap Tooltips initialisieren
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Charts initialisieren
document.addEventListener('DOMContentLoaded', function() {
    const statusLabels = <?php echo json_encode($statusLabels); ?>;
    const statusData = <?php echo json_encode($statusData); ?>;
    const registerLabels = <?php echo json_encode($registerLabels); ?>;
    const registerData = <?php echo json_encode($registerData); ?>;
    
    // Status Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx && statusLabels.length > 0) {
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: statusLabels.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
                datasets: [{
                    label: 'Mitglieder',
                    data: statusData,
                    backgroundColor: ['#5b8a72', '#c9a227', '#8fa1b3', '#4f6d7a'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    } else if (statusCtx) {
        statusCtx.parentElement.innerHTML = '<p class="text-muted text-center mb-0" style="padding: 40px 0;">Keine Daten</p>';
    }
    
    // Register Chart
    const registerCtx = document.getElementById('registerChart');
    if (registerCtx && registerLabels.length > 0 && registerData.some(v => v > 0)) {
        new Chart(registerCtx, {
            type: 'pie',
            data: {
                labels: registerLabels,
                datasets: [{
                    data: registerData,
                    backgroundColor: [
                        '#4f6d7a', '#5b8a72', '#c9a227', '#6b8cae',
                        '#b54a4a', '#7a8f6d', '#8a6d5b', '#5a7a8f'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 12, padding: 8, font: { size: 11 } }
                    }
                }
            }
        });
    } else if (registerCtx) {
        registerCtx.parentElement.innerHTML = '<p class="text-muted text-center mb-0" style="padding: 40px 0;">Keine Daten</p>';
    }
});
</script>
