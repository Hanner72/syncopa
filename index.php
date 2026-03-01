<?php
// index.php - Dashboard
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

setlocale(LC_TIME, 'de_AT.utf8', 'de_AT', 'German_Austria');

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
        AND MONTH(geburtsdatum) BETWEEN MONTH(CURDATE()) AND MONTH(CURDATE()) + 1
        ORDER BY MONTH(geburtsdatum), DAY(geburtsdatum)";
$geburtstage = $db->fetchAll($sql);

// Neue Benutzer mit Rolle "user" (nur für Admin und Obmann)
$neueBenutzer = [];
$currentRole = Session::getRole();
if ($currentRole === 'admin' || $currentRole === 'obmann') {
    $sql = "SELECT id, benutzername, email, erstellt_am FROM benutzer WHERE rolle = 'user' AND aktiv = 1 ORDER BY erstellt_am DESC";
    $neueBenutzer = $db->fetchAll($sql);
}

// Daten für Charts vorbereiten
$registerLabels = array_column($mitgliederStats['register'] ?? [], 'name');
$registerData = array_column($mitgliederStats['register'] ?? [], 'anzahl');

// Monatsname
$monatsnamen = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$aktuellerMonat = $monatsnamen[date('n')];

// Datumformatter für österreichische Monatsnamen (Langform: Jänner, Februar...)
$fmtLang = new IntlDateFormatter(
    'de_AT',
    IntlDateFormatter::NONE,
    IntlDateFormatter::NONE,
    'Europe/Vienna',
    IntlDateFormatter::GREGORIAN,
    'd. MMMM' // ergibt 8. Jänner
);


include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-grid"></i> Dashboard
    </h1>
    <div class="text-muted" style="font-size: 12px;">
        <i class="bi bi-calendar3"></i> <?php echo date('d.m.Y'); ?>
    </div>
</div>

<?php if (Session::getRole() === 'user'): ?>
<div class="alert alert-danger d-flex align-items-center mb-4">
    <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 20px;"></i>
    <div>
        <strong>Konto noch nicht freigeschaltet!</strong><br>
        <small>Ihr Benutzerkonto muss erst von einem Administrator freigeschaltet werden.</small>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($neueBenutzer)): ?>
<div class="alert alert-warning mb-4">
    <div class="d-flex align-items-center mb-2">
        <i class="bi bi-person-plus-fill me-2" style="font-size: 18px;"></i>
        <strong><?php echo count($neueBenutzer); ?> neue(r) Benutzer warte(n) auf Freischaltung</strong>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0" style="font-size: 12px;">
            <thead>
                <tr>
                    <th>Benutzername</th>
                    <th>E-Mail</th>
                    <th>Registriert</th>
                    <th class="text-end">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($neueBenutzer as $neuerUser): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($neuerUser['benutzername']); ?></strong></td>
                    <td><?php echo htmlspecialchars($neuerUser['email']); ?></td>
                    <td><?php echo date('d.m.Y', strtotime($neuerUser['erstellt_am'])); ?></td>
                    <td class="text-end">
                        <form method="POST" action="benutzer_befoerdern.php" class="d-inline">
                            <input type="hidden" name="id" value="<?php echo $neuerUser['id']; ?>">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="bi bi-check"></i> Freischalten
                            </button>
                        </form>
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
    <div class="col-6 col-md-3 mb-3">
        <div class="card stat-card border-primary">
            <div class="card-body">
                <div>
                    <h6>Aktive Mitglieder</h6>
                    <h2><?php echo $mitgliederStats['total'] ?? 0; ?></h2>
                </div>
                <div class="stat-icon text-primary">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3 mb-3">
        <div class="card stat-card border-success">
            <div class="card-body">
                <div>
                    <h6>Noten im Archiv</h6>
                    <h2><?php echo $notenStats['total'] ?? 0; ?></h2>
                </div>
                <div class="stat-icon text-success">
                    <i class="bi bi-music-note-list"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-md-3 mb-3">
        <div class="card stat-card border-warning">
            <div class="card-body">
                <div>
                    <h6>Instrumente</h6>
                    <h2><?php echo $instrumentStats['total'] ?? 0; ?></h2>
                    <small><?php echo $instrumentStats['ausgeliehen'] ?? 0; ?> verliehen</small>
                </div>
                <div class="stat-icon text-warning">
                    <i class="bi bi-disc"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-md-3 mb-3">
        <div class="card stat-card border-info">
            <div class="card-body">
                <div>
                    <h6>Instrumentenwert</h6>
                    <h2>€ <?php echo number_format($instrumentStats['gesamtwert'] ?? 0, 0, ',', '.'); ?></h2>
                </div>
                <div class="stat-icon text-info">
                    <i class="bi bi-currency-euro"></i>
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
                <span><i class="bi bi-calendar-event me-2"></i>Nächste Termine</span>
                <a href="kalender.php" class="btn btn-sm btn-primary">Kalender</a>
            </div>
            <div class="card-body">
                <?php if (empty($naechsteAusrueckungen)): ?>
                    <p class="text-muted mb-0">Keine bevorstehenden Termine</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($naechsteAusrueckungen as $termin): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start">
                            <div>
                                <strong style="font-size: 13px;"><?php echo htmlspecialchars($termin['titel']); ?></strong>
                                <div class="text-muted" style="font-size: 11px;">
                                    <i class="bi bi-clock"></i> <?php echo date('d.m.Y H:i', strtotime($termin['start_datum'])); ?>
                                    <?php if ($termin['ort']): ?>
                                    · <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($termin['ort']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="badge bg-<?php 
                                echo $termin['typ'] === 'Probe' ? 'secondary' : 
                                    ($termin['typ'] === 'Konzert' ? 'primary' : 'success'); 
                            ?>"><?php echo htmlspecialchars($termin['typ']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($geburtstage)): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-gift me-2"></i>Geburtstage im <?php echo $aktuellerMonat; ?>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($geburtstage as $geburtstag): ?>
                        <div class="list-group-item" style="font-size: 12px;">
                            <i class="bi bi-balloon text-danger me-1"></i>
                            <?php
                                // Name
                                $name = htmlspecialchars($geburtstag['vorname'] . ' ' . strtoupper($geburtstag['nachname']));
                                // Datum richtig formatiert
                                $datum = new DateTime($geburtstag['geburtsdatum']);
                                $geburtstagFormatiert = $fmtLang->format($datum); // zB: 8. Jänner
                                // Alter berechnen
                                $heute = new DateTime();
                                $alter = $heute->diff($datum)->y;
                            ?>
                            <strong><?= $name ?></strong> – <?= $geburtstagFormatiert ?> <span class="text-muted"> (<?= $alter ?> Jahre) </span>
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
                <i class="bi bi-pie-chart me-2"></i>Registerverteilung
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="registerChart"></canvas>
                </div>
            </div>
        </div>
        
        <?php if (!empty($faelligeWartungen)): ?>
        <div class="card">
            <div class="card-header" style="background: #fef6e6; color: #8a6d1b;">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Fällige Wartungen (<?php echo count($faelligeWartungen); ?>)
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($faelligeWartungen, 0, 5) as $wartung): ?>
                    <div class="list-group-item d-flex justify-content-between" style="font-size: 12px;">
                        <div>
                            <strong><?php echo htmlspecialchars($wartung['inventar_nummer']); ?></strong>
                            – <?php echo htmlspecialchars($wartung['instrument_name']); ?>
                        </div>
                        <small class="text-muted">
                            <?php echo date('d.m.Y', strtotime($wartung['naechste_wartung'])); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="instrumente.php" class="btn btn-sm btn-warning mt-3">
                    <i class="bi bi-tools"></i> Alle anzeigen
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning me-2"></i>Schnellaktionen
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="ausrueckung_bearbeiten.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus"></i> Ausrückung
                    </a>
                    <?php if (Session::checkPermission('mitglieder', 'schreiben')): ?>
                    <a href="mitglied_bearbeiten.php" class="btn btn-success btn-sm">
                        <i class="bi bi-person-plus"></i> Mitglied
                    </a>
                    <?php endif; ?>
                    <?php if (Session::checkPermission('noten', 'schreiben')): ?>
                    <a href="noten_bearbeiten.php" class="btn btn-info btn-sm">
                        <i class="bi bi-music-note"></i> Noten
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Chart nach DOM-Load initialisieren
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('registerChart');
    if (ctx && typeof Chart !== 'undefined') {
        const labels = <?php echo json_encode($registerLabels); ?>;
        const data = <?php echo json_encode($registerData); ?>;
        
        // Prüfen ob Daten vorhanden sind
        if (labels.length > 0 && data.some(v => v > 0)) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
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
                            labels: {
                                boxWidth: 12,
                                padding: 10,
                                font: { size: 11 }
                            }
                        }
                    }
                }
            });
        } else {
            ctx.parentElement.innerHTML = '<p class="text-muted text-center mb-0" style="padding: 40px 0;">Keine Registerdaten vorhanden</p>';
        }
    }
});
</script>
