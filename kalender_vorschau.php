<?php
/**
 * kalender_vorschau.php
 * Zeigt Vorschau der Ausrückungen die im Kalender-Export enthalten sind
 */
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

$db = Database::getInstance();

// Alle zukünftigen Ausrückungen laden (gleiche Query wie in kalender_export.php)
$sql = "SELECT 
            id,
            titel,
            start_datum,
            ende_datum,
            ganztaegig,
            ort,
            beschreibung,
            notizen,
            aktualisiert_am,
            erstellt_am,
            typ
        FROM ausrueckungen 
        WHERE DATE(start_datum) >= CURDATE()
        ORDER BY start_datum";

$ausrueckungen = $db->fetchAll($sql);

// Statistik
$anzahl = count($ausrueckungen);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2"><i class="bi bi-eye"></i> Kalender-Vorschau</h1>
            <div>
                <a href="kalender_export.php" class="btn btn-success me-2" target="_blank">
                    <i class="bi bi-download"></i> ICS herunterladen
                </a>
                <a href="kalender_abonnement.php" class="btn btn-primary me-2">
                    <i class="bi bi-calendar-check"></i> Abonnieren
                </a>
                <a href="ausrueckungen.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Zurück
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Info-Box -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Diese Ausrückungen werden im Kalender-Export enthalten sein.</strong>
                    <br>
                    <small>Nur zukünftige Termine ab heute werden exportiert. Vergangene Termine sind nicht enthalten.</small>
                </div>
                <div class="col-md-4 text-end">
                    <h3 class="mb-0">
                        <span class="badge bg-primary"><?php echo $anzahl; ?> Termine</span>
                    </h3>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($ausrueckungen)): ?>
<!-- Keine Ausrückungen -->
<div class="row">
    <div class="col-12">
        <div class="alert alert-warning">
            <h4><i class="bi bi-exclamation-triangle"></i> Keine zukünftigen Ausrückungen</h4>
            <p class="mb-0">
                Es sind aktuell keine zukünftigen Ausrückungen vorhanden. 
                <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
                <a href="ausrueckung_bearbeiten.php">Erstelle die erste Ausrückung</a>
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Ausrückungen-Liste -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Zukünftige Ausrückungen (ab heute)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Uhrzeit</th>
                                <th>Titel</th>
                                <th>Typ</th>
                                <th>Ort</th>
                                <th>Beschreibung</th>
                                <th class="text-center">Im Kalender</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ausrueckungen as $ausrueckung): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('d.m.Y', strtotime($ausrueckung['start_datum'])); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php
                                        $wochentag = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
                                        echo $wochentag[date('w', strtotime($ausrueckung['start_datum']))];
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <?php 
                                    if ($ausrueckung['ganztaegig']) {
                                        echo '<small class="text-muted">Ganztägig</small>';
                                    } else {
                                        echo date('H:i', strtotime($ausrueckung['start_datum']));
                                        if ($ausrueckung['ende_datum']) {
                                            echo ' - ' . date('H:i', strtotime($ausrueckung['ende_datum']));
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($ausrueckung['titel']); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $typColors = [
                                        'Probe' => 'secondary',
                                        'Konzert' => 'primary',
                                        'Ausflug' => 'success',
                                        'Auftritt' => 'info',
                                        'Sonstiges' => 'warning'
                                    ];
                                    $color = $typColors[$ausrueckung['typ']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo htmlspecialchars($ausrueckung['typ']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($ausrueckung['ort']): ?>
                                    <i class="bi bi-geo-alt"></i> 
                                    <?php echo htmlspecialchars($ausrueckung['ort']); ?>
                                    <?php else: ?>
                                    <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ausrueckung['beschreibung']): ?>
                                    <small><?php echo htmlspecialchars(mb_substr($ausrueckung['beschreibung'], 0, 100)); ?>...</small>
                                    <?php else: ?>
                                    <small class="text-muted">Keine Beschreibung</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Ja
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ICS-Beispiel -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-code-square"></i> ICS-Format Beispiel</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">So sieht der erste Termin im ICS-Format aus:</p>
                <?php
                $first = $ausrueckungen[0];
                $start = new DateTime($first['start_datum']);
                if ($first['ende_datum']) {
                    $end = new DateTime($first['ende_datum']);
                } else {
                    $end = clone $start;
                    $end->modify('+2 hours');
                }
                ?>
                <pre class="bg-light p-3 rounded"><code>BEGIN:VEVENT
UID:ausrueckung-<?php echo $first['id']; ?>@musikverein.local
DTSTART;TZID=Europe/Vienna:<?php echo $start->format('Ymd\THis'); ?>

DTEND;TZID=Europe/Vienna:<?php echo $end->format('Ymd\THis'); ?>

SUMMARY:<?php echo htmlspecialchars($first['titel']); ?>

LOCATION:<?php echo htmlspecialchars($first['ort'] ?: ''); ?>

DESCRIPTION:<?php echo htmlspecialchars($first['beschreibung'] ?: ''); ?>

STATUS:CONFIRMED
END:VEVENT</code></pre>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Test-Download -->
<div class="row mt-4 mb-4">
    <div class="col-12">
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-download"></i> Test-Download</h5>
            </div>
            <div class="card-body">
                <p>Teste ob der Kalender-Export funktioniert:</p>
                <div class="d-grid gap-2 d-md-block">
                    <a href="kalender_export.php" class="btn btn-success" download="musikverein_kalender.ics">
                        <i class="bi bi-download"></i> ICS-Datei herunterladen
                    </a>
                    <a href="kalender_export.php" class="btn btn-outline-success" target="_blank">
                        <i class="bi bi-eye"></i> Im Browser öffnen (ICS-Code)
                    </a>
                </div>
                <div class="alert alert-info mt-3 mb-0">
                    <small>
                        <i class="bi bi-info-circle"></i> 
                        <strong>Tipp:</strong> Öffne die ICS-Datei mit einem Text-Editor um das ICS-Format zu sehen,
                        oder importiere sie in deinen Kalender zum Testen.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-Refresh Info
console.log('Kalender-Vorschau geladen. <?php echo $anzahl; ?> zukünftige Ausrückungen gefunden.');
</script>

<?php include 'includes/footer.php'; ?>
