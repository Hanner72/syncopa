<?php
// index.php - Dashboard
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

$db = Database::getInstance();
$mitglied = new Mitglied();
$ausrueckung = new Ausrueckung();
$noten = new Noten();
$instrument = new Instrument();

// Statistiken laden
$mitgliederStats = $mitglied->getStatistik();
$notenStats = $noten->getStatistik();
$instrumentStats = $instrument->getStatistik();
$naechsteAusrueckungen = $ausrueckung->getUpcoming(5);
$faelligeWartungen = $instrument->getFaelligeWartungen();

// Geburtstage diesen Monat
$sql = "SELECT vorname, nachname, geburtsdatum, 
        DAY(geburtsdatum) as tag, MONTH(geburtsdatum) as monat
        FROM mitglieder 
        WHERE status = 'aktiv' 
        AND MONTH(geburtsdatum) = MONTH(CURDATE())
        ORDER BY DAY(geburtsdatum)";
$geburtstage = $db->fetchAll($sql);

// Neue Benutzer mit Rolle "user" (nur für Admin und Obmann)
$neueBenutzer = [];
$currentRole = Session::getRole();
if ($currentRole === 'admin' || $currentRole === 'obmann') {
    $sql = "SELECT id, benutzername, email, erstellt_am FROM benutzer WHERE rolle = 'user' AND aktiv = 1 ORDER BY erstellt_am DESC";
    $neueBenutzer = $db->fetchAll($sql);
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-speedometer2"></i> Dashboard
    </h1>
    <div class="text-muted">
        <i class="bi bi-calendar3"></i> <?php echo format_date_german(new DateTime()); ?>
    </div>
</div>

<?php if (Session::getRole() === 'user'): ?>
<!-- Hinweis für noch nicht freigeschaltete Benutzer -->
<div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
    <div>
        <strong>Konto noch nicht freigeschaltet!</strong><br>
        Ihr Benutzerkonto muss erst von einem Administrator freigeschaltet werden. 
        Bitte wenden Sie sich an den Vorstand oder Administrator des Musikvereins.
    </div>
</div>
<?php endif; ?>

<?php if (!empty($neueBenutzer)): ?>
<!-- Hinweis für Admin/Obmann: Neue Benutzer warten auf Freischaltung -->
<div class="alert alert-warning mb-4" role="alert">
    <div class="d-flex align-items-center mb-2">
        <i class="bi bi-person-plus-fill fs-4 me-2"></i>
        <strong><?php echo count($neueBenutzer); ?> neue(r) Benutzer warte(n) auf Freischaltung</strong>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-borderless mb-0">
            <thead>
                <tr>
                    <th>Benutzername</th>
                    <th>E-Mail</th>
                    <th>Registriert am</th>
                    <th class="text-end">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($neueBenutzer as $neuerUser): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($neuerUser['benutzername']); ?></strong></td>
                    <td><?php echo htmlspecialchars($neuerUser['email']); ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($neuerUser['erstellt_am'])); ?></td>
                    <td class="text-end">
                        <form method="POST" action="benutzer_befoerdern.php" class="d-inline">
                            <input type="hidden" name="id" value="<?php echo $neuerUser['id']; ?>">
                            <button type="submit" class="btn btn-success btn-sm" title="Zum Mitglied befördern">
                                <i class="bi bi-person-check"></i> Freischalten
                            </button>
                        </form>
                        <a href="benutzer_bearbeiten.php?id=<?php echo $neuerUser['id']; ?>" class="btn btn-outline-primary btn-sm" title="Bearbeiten">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Statistik-Karten -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Aktive Mitglieder</h6>
                        <h2 class="mb-0"><?php echo $mitgliederStats['total']; ?></h2>
                    </div>
                    <div class="text-primary" style="font-size: 3rem;">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Noten im Archiv</h6>
                        <h2 class="mb-0"><?php echo $notenStats['total']; ?></h2>
                    </div>
                    <div class="text-success" style="font-size: 3rem;">
                        <i class="bi bi-music-note-list"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Instrumente</h6>
                        <h2 class="mb-0"><?php echo $instrumentStats['total']; ?></h2>
                        <small class="text-muted"><?php echo $instrumentStats['ausgeliehen']; ?> ausgeliehen</small>
                    </div>
                    <div class="text-warning" style="font-size: 3rem;">
                        <i class="bi bi-diagram-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Instrumentenwert</h6>
                        <h2 class="mb-0">€ <?php echo number_format($instrumentStats['gesamtwert'], 0, ',', '.'); ?></h2>
                    </div>
                    <div class="text-info" style="font-size: 3rem;">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Nächste Ausrückungen -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Nächste Termine</h5>
                <a href="kalender.php" class="btn btn-sm btn-primary">Zum Kalender</a>
            </div>
            <div class="card-body">
                <?php if (empty($naechsteAusrueckungen)): ?>
                    <p class="text-muted">Keine bevorstehenden Termine</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($naechsteAusrueckungen as $termin): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($termin['titel']); ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> 
                                        <?php echo date('d.m.Y H:i', strtotime($termin['start_datum'])); ?>
                                    </small>
                                    <?php if ($termin['ort']): ?>
                                    <br><small class="text-muted">
                                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($termin['ort']); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-<?php 
                                    echo $termin['typ'] === 'Probe' ? 'secondary' : 
                                        ($termin['typ'] === 'Konzert' ? 'primary' : 'success'); 
                                ?>">
                                    <?php echo htmlspecialchars($termin['typ']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Geburtstage -->
        <?php if (!empty($geburtstage)): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-cake"></i> Geburtstage im <?php echo strftime('%B'); ?></h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($geburtstage as $geburtstag): ?>
                    <div class="list-group-item px-0">
                        <i class="bi bi-gift text-danger"></i>
                        <strong><?php echo htmlspecialchars($geburtstag['vorname'] . ' ' . $geburtstag['nachname']); ?></strong>
                        - <?php echo $geburtstag['tag']; ?>. <?php echo strftime('%B', mktime(0, 0, 0, $geburtstag['monat'], 1)); ?>
                        <?php 
                        $alter = date('Y') - date('Y', strtotime($geburtstag['geburtsdatum']));
                        echo " ({$alter} Jahre)";
                        ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Registerverteilung & Wartungen -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Registerverteilung</h5>
            </div>
            <div class="card-body">
                <canvas id="registerChart" height="200"></canvas>
            </div>
        </div>
        
        <!-- Fällige Wartungen -->
        <?php if (!empty($faelligeWartungen)): ?>
        <div class="card mt-3">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle"></i> 
                    Fällige Wartungen (<?php echo count($faelligeWartungen); ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($faelligeWartungen as $wartung): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong><?php echo htmlspecialchars($wartung['inventar_nummer']); ?></strong>
                                - <?php echo htmlspecialchars($wartung['instrument_name']); ?>
                            </div>
                            <small class="text-muted">
                                <?php echo date('d.m.Y', strtotime($wartung['naechste_wartung'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="instrumente.php" class="btn btn-sm btn-warning mt-2">
                    Alle Wartungen anzeigen
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Schnellaktionen -->
        <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning"></i> Schnellaktionen</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="ausrueckungen.php?action=new" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Neue Ausrückung
                    </a>
                    <?php if (Session::checkPermission('mitglieder', 'schreiben')): ?>
                    <a href="mitglieder.php?action=new" class="btn btn-success">
                        <i class="bi bi-person-plus"></i> Neues Mitglied
                    </a>
                    <?php endif; ?>
                    <?php if (Session::checkPermission('noten', 'schreiben')): ?>
                    <a href="noten.php?action=new" class="btn btn-info">
                        <i class="bi bi-music-note"></i> Neue Noten
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Registerverteilung Chart
const registerData = {
    labels: <?php echo json_encode(array_column($mitgliederStats['register'], 'name')); ?>,
    datasets: [{
        data: <?php echo json_encode(array_column($mitgliederStats['register'], 'anzahl')); ?>,
        backgroundColor: [
            '#0d6efd', '#6c757d', '#198754', '#ffc107', 
            '#dc3545', '#0dcaf0', '#6f42c1', '#fd7e14'
        ]
    }]
};

new Chart(document.getElementById('registerChart'), {
    type: 'doughnut',
    data: registerData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
