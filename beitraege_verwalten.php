<?php
// beitraege_verwalten.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'schreiben');

$db = Database::getInstance();

// Einstellungen laden
$einstellungen = $db->fetchAll("SELECT * FROM einstellungen WHERE schluessel LIKE 'beitrag%' OR schluessel = 'mitgliedsbeitrag_jahr'");
$settings = [];
foreach ($einstellungen as $e) {
    $settings[$e['schluessel']] = $e['wert'];
}

$jahr = $_GET['jahr'] ?? date('Y');

// Beiträge generieren für ein Jahr
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_beitraege'])) {
    $generierungsJahr = $_POST['jahr'];
    
    // Welche Status zahlen?
    $beitragspflichtige_status = [];
    if (($settings['beitrag_aktiv'] ?? '1') == '1') $beitragspflichtige_status[] = "'aktiv'";
    if (($settings['beitrag_passiv'] ?? '0') == '1') $beitragspflichtige_status[] = "'passiv'";
    if (($settings['beitrag_ehrenmitglied'] ?? '0') == '1') $beitragspflichtige_status[] = "'ehrenmitglied'";
    if (($settings['beitrag_ausgetreten'] ?? '0') == '1') $beitragspflichtige_status[] = "'ausgetreten'";
    
    if (empty($beitragspflichtige_status)) {
        Session::setFlashMessage('danger', 'Keine beitragspflichtigen Status definiert. Bitte in Einstellungen konfigurieren.');
        header('Location: beitraege_verwalten.php');
        exit;
    }
    
    $statusList = implode(',', $beitragspflichtige_status);
    
    try {
        // Mitglieder holen die Beiträge zahlen müssen
        $mitglieder = $db->fetchAll("
            SELECT * FROM mitglieder 
            WHERE status IN ($statusList)
            ORDER BY nachname, vorname
        ");
        
        $standardBeitrag = floatval($settings['mitgliedsbeitrag_jahr'] ?? 120);
        $passivBeitrag = floatval($settings['beitrag_passiv_betrag'] ?? 60);
        $generiert = 0;
        $bereits_vorhanden = 0;
        
        foreach ($mitglieder as $m) {
            // Prüfen ob schon vorhanden
            $exists = $db->fetchOne("SELECT id FROM beitraege WHERE mitglied_id = ? AND jahr = ?", [$m['id'], $generierungsJahr]);
            
            if (!$exists) {
                // Betrag je nach Status
                $betrag = ($m['status'] === 'passiv' && ($settings['beitrag_passiv'] ?? '0') == '1') 
                    ? $passivBeitrag 
                    : $standardBeitrag;
                
                $db->execute(
                    "INSERT INTO beitraege (mitglied_id, jahr, betrag, bezahlt, erstellt_am) VALUES (?, ?, ?, 0, NOW())",
                    [$m['id'], $generierungsJahr, $betrag]
                );
                $generiert++;
            } else {
                $bereits_vorhanden++;
            }
        }
        
        Session::setFlashMessage('success', "Beiträge generiert: $generiert neu, $bereits_vorhanden bereits vorhanden");
        header('Location: beitraege_verwalten.php?jahr=' . $generierungsJahr);
        exit;
    } catch (Exception $e) {
        Session::setFlashMessage('danger', 'Fehler: ' . $e->getMessage());
    }
}

// Beitrag als bezahlt markieren
if (isset($_GET['bezahlt']) && isset($_GET['beitrag_id'])) {
    try {
        $bezahlt = $_GET['bezahlt'] == '1' ? 1 : 0;
        $datum = $bezahlt ? date('Y-m-d') : null;
        
        $db->execute(
            "UPDATE beitraege SET bezahlt = ?, bezahlt_am = ? WHERE id = ?",
            [$bezahlt, $datum, $_GET['beitrag_id']]
        );
        
        Session::setFlashMessage('success', $bezahlt ? 'Als bezahlt markiert' : 'Als unbezahlt markiert');
        header('Location: beitraege_verwalten.php?jahr=' . $jahr);
        exit;
    } catch (Exception $e) {
        Session::setFlashMessage('danger', 'Fehler: ' . $e->getMessage());
    }
}

// Alle Beiträge des Jahres laden
$beitraege = $db->fetchAll("
    SELECT b.*, m.vorname, m.nachname, m.mitgliedsnummer, m.status, m.email
    FROM beitraege b
    JOIN mitglieder m ON b.mitglied_id = m.id
    WHERE b.jahr = ?
    ORDER BY b.bezahlt ASC, m.nachname, m.vorname
", [$jahr]);

// Statistiken
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as gesamt,
        SUM(CASE WHEN bezahlt = 1 THEN 1 ELSE 0 END) as bezahlt_anzahl,
        SUM(CASE WHEN bezahlt = 0 THEN 1 ELSE 0 END) as offen_anzahl,
        SUM(betrag) as summe_gesamt,
        SUM(CASE WHEN bezahlt = 1 THEN betrag ELSE 0 END) as summe_bezahlt,
        SUM(CASE WHEN bezahlt = 0 THEN betrag ELSE 0 END) as summe_offen
    FROM beitraege
    WHERE jahr = ?
", [$jahr]);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-currency-euro"></i> Mitgliedsbeiträge verwalten</h1>
    <a href="finanzen.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Zurück zu Finanzen
    </a>
</div>

<!-- Statistik -->
<?php if (!empty($beitraege)): ?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <h6 class="text-muted">Gesamt</h6>
                <h2><?php echo $stats['gesamt']; ?> Mitglieder</h2>
                <p class="mb-0">€ <?php echo number_format($stats['summe_gesamt'], 2, ',', '.'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <h6 class="text-muted">Bezahlt</h6>
                <h2 class="text-success"><?php echo $stats['bezahlt_anzahl']; ?></h2>
                <p class="mb-0">€ <?php echo number_format($stats['summe_bezahlt'], 2, ',', '.'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <h6 class="text-muted">Offen</h6>
                <h2 class="text-warning"><?php echo $stats['offen_anzahl']; ?></h2>
                <p class="mb-0">€ <?php echo number_format($stats['summe_offen'], 2, ',', '.'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <h6 class="text-muted">Quote</h6>
                <h2 class="text-info">
                    <?php echo $stats['gesamt'] > 0 ? round(($stats['bezahlt_anzahl'] / $stats['gesamt']) * 100) : 0; ?>%
                </h2>
                <p class="mb-0">bezahlt</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Jahr auswählen & Generieren -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-end">
            <div class="col-md-3">
                <label class="form-label">Jahr anzeigen</label>
                <select class="form-select" onchange="window.location.href='?jahr='+this.value">
                    <?php for ($y = date('Y') + 1; $y >= date('Y') - 5; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $jahr == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-md-6">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="jahr" value="<?php echo $jahr; ?>">
                    <button type="submit" name="generate_beitraege" class="btn btn-primary" 
                            onclick="return confirm('Beiträge für <?php echo $jahr; ?> generieren?\n\nBereits vorhandene Beiträge werden übersprungen.')">
                        <i class="bi bi-plus-circle"></i> Beiträge für <?php echo $jahr; ?> generieren
                    </button>
                    <small class="text-muted d-block mt-1">
                        Generiert Beiträge für alle beitragspflichtigen Mitglieder
                    </small>
                </form>
            </div>
            
            <div class="col-md-3 text-end">
                <a href="einstellungen.php" class="btn btn-secondary">
                    <i class="bi bi-gear"></i> Einstellungen
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Info wenn noch keine Beiträge -->
<?php if (empty($beitraege)): ?>
<div class="alert alert-info">
    <h5><i class="bi bi-info-circle"></i> Keine Beiträge für <?php echo $jahr; ?> vorhanden</h5>
    <p class="mb-0">
        Klicke auf "Beiträge generieren" um automatisch für alle beitragspflichtigen Mitglieder Beiträge zu erstellen.
        <br>
        <small class="text-muted">
            Beitragspflichtige Status können in den <a href="einstellungen.php">Einstellungen</a> konfiguriert werden.
        </small>
    </p>
</div>
<?php else: ?>

<!-- Beitragsliste -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Beiträge <?php echo $jahr; ?></h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="beitraegeTable">
                <thead>
                    <tr>
                        <th>Mitgliedsnr.</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Betrag</th>
                        <th>Bezahlt</th>
                        <th>Bezahlt am</th>
                        <th>E-Mail</th>
                        <th class="text-end">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($beitraege as $b): ?>
                    <tr class="<?php echo $b['bezahlt'] ? 'table-success' : ''; ?>">
                        <td><?php echo htmlspecialchars($b['mitgliedsnummer']); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($b['nachname'] . ' ' . $b['vorname']); ?></strong>
                        </td>
                        <td>
                            <?php
                            $statusColors = ['aktiv' => 'success', 'passiv' => 'warning', 'ehrenmitglied' => 'primary', 'ausgetreten' => 'secondary'];
                            $color = $statusColors[$b['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($b['status']); ?></span>
                        </td>
                        <td><strong>€ <?php echo number_format($b['betrag'], 2, ',', '.'); ?></strong></td>
                        <td>
                            <?php if ($b['bezahlt']): ?>
                            <span class="badge bg-success">Bezahlt</span>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark">Offen</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['bezahlt_am']): ?>
                            <small><?php echo date('d.m.Y', strtotime($b['bezahlt_am'])); ?></small>
                            <?php else: ?>
                            <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['email']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($b['email']); ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-envelope"></i>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($b['bezahlt']): ?>
                            <a href="?jahr=<?php echo $jahr; ?>&beitrag_id=<?php echo $b['id']; ?>&bezahlt=0" 
                               class="btn btn-sm btn-warning"
                               onclick="return confirm('Als UNBEZAHLT markieren?')">
                                <i class="bi bi-x-circle"></i> Unbezahlt
                            </a>
                            <?php else: ?>
                            <a href="?jahr=<?php echo $jahr; ?>&beitrag_id=<?php echo $b['id']; ?>&bezahlt=1" 
                               class="btn btn-sm btn-success">
                                <i class="bi bi-check-circle"></i> Bezahlt
                            </a>
                            <?php endif; ?>
                            
                            <a href="mitglied_detail.php?id=<?php echo $b['mitglied_id']; ?>" 
                               class="btn btn-sm btn-info" title="Mitglied anzeigen">
                                <i class="bi bi-person"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
$(document).ready(function() {
    $('#beitraegeTable').DataTable({
        order: [[4, 'asc'], [1, 'asc']], // Erst nach Status (offen zuerst), dann Name
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/de-DE.json'
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
