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
// Mitgliedsart (ersetzt den früheren Status); ohne Auswahl werden nur aktuelle Mitglieder gezeigt
$filterArt = $_GET['mitgliedsart'] ?? 'mitglieder';
$filter['mitgliedsart'] = $filterArt;
if (!empty($_GET['register'])) {
    $filter['register'] = $_GET['register'];
}
// Die Suche filtert sofort in der Tabelle (ohne Neuladen), daher hier kein Suchfilter

$mitglieder = $mitglied->getAll($filter);

// Register für Filter laden
$register = $db->fetchAll("SELECT * FROM register ORDER BY sortierung");

// Statistiken für Charts
$stats = $mitglied->getStatistik();
$artLabels = array_column($stats['mitgliedsart'] ?? [], 'name');
$artData = array_column($stats['mitgliedsart'] ?? [], 'anzahl');
$artFarben = array_column($stats['mitgliedsart'] ?? [], 'farbe');
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
        <form method="GET" class="row g-3" id="mitgliederFilter">
            <div class="col-md-5">
                <label for="search" class="form-label">Suche</label>
                <input type="search" class="form-control" id="search" name="search" autocomplete="off"
                       placeholder="Name, Nummer, Instrument, Kontakt …"
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            
            <div class="col-md-3">
                <label for="mitgliedsart" class="form-label">Mitgliedsart</label>
                <select class="form-select" id="mitgliedsart" name="mitgliedsart">
                    <?php
                    $artOptionen = ['mitglieder' => 'Alle aktuellen Mitglieder', 'aktiv' => 'Nur aktive (spielen mit)'];
                    foreach (Mitgliedsart::alle() as $code => $a) $artOptionen[(string)$code] = $a['name'];
                    $artOptionen['gast'] = 'Alle Gäste (kein Vereinsmitglied)';
                    $artOptionen['ausgetreten'] = 'Ausgetreten';
                    $artOptionen['alle'] = 'Alle (auch ausgetretene)';
                    foreach ($artOptionen as $wert => $text): ?>
                    <option value="<?php echo htmlspecialchars($wert); ?>" <?php echo (string)$filterArt === (string)$wert ? 'selected' : ''; ?>><?php echo htmlspecialchars($text); ?></option>
                    <?php endforeach; ?>
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
            
            <div class="col-md-1 d-flex align-items-end">
                <a href="mitglieder.php" class="btn btn-outline-secondary" title="Filter zurücksetzen">
                    <i class="bi bi-x-lg"></i>
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
                        <th>Mitgliedsart</th>
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
                            <?php if (!empty($m['foto']) || !empty($m['bmv_foto'])):
                                $fotoUrl = 'api/mitglied_foto.php?id=' . (int)$m['id'] . '&v=' . strtotime($m['aktualisiert_am']);
                            ?>
                            <img src="<?php echo $fotoUrl; ?>" alt="" class="rounded me-2 align-middle"
                                 style="width:40px;height:40px;object-fit:cover"
                                 data-bs-toggle="tooltip" data-bs-placement="right" data-bs-html="true"
                                 data-bs-title="<img src='<?php echo $fotoUrl; ?>' style='width:220px;height:220px;object-fit:cover' class='rounded'>">
                            <?php endif; ?>
                            <?php
                            echo htmlspecialchars($m['nachname'] . ' ' . $m['vorname']);
                            if ($m['geburtsdatum']) {
                                $alter = date_diff(date_create($m['geburtsdatum']), date_create('today'))->y;
                                echo "<br><small class='text-muted'>{$alter} Jahre</small>";
                            }
                            if (empty($m['bmv_id']) && !empty($m['bmv_abgeglichen_am'])):
                            ?>
                            <br><span class="badge bg-warning text-dark" title="Beim Abgleich mit dem BMV-Datenservice nicht gefunden">nicht im BMV</span>
                            <?php endif; ?>
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
                            <?php echo Mitgliedsart::badge($m['mitgliedsart_code']); ?>
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
                <i class="bi bi-bar-chart me-2"></i>Nach Mitgliedsart
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
    // Ohne eigenes Suchfeld der Tabelle – gesucht wird über das Feld oben, sofort beim Tippen
    const tabelle = $('#mitgliederTable').DataTable({
        order: [[1, 'asc']],
        columnDefs: [
            { targets: -1, orderable: false }
        ],
        dom: '<"row align-items-center mb-2"<"col-auto"l>><"table-responsive"t><"row align-items-center mt-2"<"col-sm-5"i><"col-sm-7"p>>'
    });
    const suche = document.getElementById('search');
    tabelle.search(suche.value).draw();
    suche.addEventListener('input', () => tabelle.search(suche.value).draw());
    suche.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });

    // Auswahlfelder filtern sofort (Seite lädt mit den neuen Filtern neu, die Suche bleibt erhalten)
    document.querySelectorAll('#mitgliederFilter select').forEach(feld =>
        feld.addEventListener('change', () => document.getElementById('mitgliederFilter').submit()));
    
    // Bootstrap Tooltips initialisieren
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, { html: true });
    });
});

// Charts initialisieren
document.addEventListener('DOMContentLoaded', function() {
    const statusLabels = <?php echo json_encode($artLabels); ?>;
    const statusData = <?php echo json_encode($artData); ?>;
    // Farben wie die Abzeichen der Mitgliedsarten
    const farbe = n => getComputedStyle(document.documentElement).getPropertyValue('--bs-' + n).trim() || '#8fa1b3';
    const statusFarben = <?php echo json_encode($artFarben); ?>.map(farbe);
    const registerLabels = <?php echo json_encode($registerLabels); ?>;
    const registerData = <?php echo json_encode($registerData); ?>;
    
    // Status Chart
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx && statusLabels.length > 0) {
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: statusLabels,
                datasets: [{
                    label: 'Mitglieder',
                    data: statusData,
                    backgroundColor: statusFarben,
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
