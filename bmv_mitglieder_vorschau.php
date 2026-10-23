<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin()) {
    Session::setFlashMessage('danger', 'Nur Administratoren haben Zugriff');
    header('Location: index.php');
    exit;
}

$api        = new BmvApi();
$vereinZeile = Database::getInstance()->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'bmv_verein_nr'");
$vereinNr    = $vereinZeile ? (int)$vereinZeile['wert'] : 0;
$vorschau   = null;
$fehler     = null;

if (!$api->istKonfiguriert()) {
    $fehler = 'Die BMV-Zugangsdaten sind nicht vollständig. Bitte unter Einstellungen eintragen.';
} elseif ($vereinNr <= 0) {
    $fehler = 'Bitte zuerst unter Einstellungen mit dem BMV synchronisieren, damit der Verein zugeordnet ist.';
} else {
    try {
        $vorschau = BmvMitgliederSync::vorschau($api);
    } catch (RuntimeException $e) {
        $fehler = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aktion'] ?? '') === 'uebernehmen' && $vorschau !== null) {
    try {
        $ergebnis = BmvMitgliederSync::uebernehmen($api);
        Session::setFlashMessage('success', 'Übernommen: ' . $ergebnis['neu'] . ' neu, '
            . $ergebnis['aktualisiert'] . ' aktualisiert, ' . $ergebnis['nicht_im_bmv'] . ' nicht im BMV markiert.');
    } catch (Throwable $e) {
        Session::setFlashMessage('danger', 'Übernahme fehlgeschlagen, es wurde nichts geändert: ' . $e->getMessage());
    }
    header('Location: bmv_mitglieder_vorschau.php');
    exit;
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-eye"></i> BMV-Mitglieder-Vorschau</h1>
    <a href="einstellungen.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Einstellungen</a>
</div>

<div class="alert alert-info small">
    <i class="bi bi-info-circle"></i>
    Diese Vorschau liest nur Daten aus dem BMV-Datenservice. Es wird nichts geändert, weder im BMV noch in Syncopa.
</div>

<?php if ($fehler): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($fehler); ?></div>
<?php else: ?>

<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-primary"><div class="card-body"><div>
            <h6>Im BMV (alle Musiker)</h6><h2><?php echo $vorschau['anzahl_bmv']; ?></h2>
        </div></div></div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-success"><div class="card-body"><div>
            <h6>Neu anzulegen</h6><h2><?php echo count($vorschau['neu']); ?></h2>
        </div></div></div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-info"><div class="card-body"><div>
            <h6>Zuordnung vorhanden</h6><h2><?php echo count($vorschau['aktualisiert']); ?></h2>
        </div></div></div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-warning"><div class="card-body"><div>
            <h6>Nicht im BMV</h6><h2><?php echo count($vorschau['nicht_im_bmv']); ?></h2>
        </div></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Neu anzulegen (nur im BMV)</h5></div>
    <div class="card-body">
        <?php if (empty($vorschau['neu'])): ?>
        <p class="text-muted mb-0">Keine.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover" id="bmvNeuTable">
            <thead><tr><th>Nachname</th><th>Vorname</th><th>Geburtsdatum</th><th>BMV-Status</th></tr></thead>
            <tbody>
            <?php foreach ($vorschau['neu'] as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars($m['nachname']); ?></td>
                <td><?php echo htmlspecialchars($m['vorname']); ?></td>
                <td><?php echo $m['geburtsdatum'] ? date('d.m.Y', strtotime($m['geburtsdatum'])) : '–'; ?></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($m['bmv_status']); ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Nicht im BMV (nur in Syncopa)</h5></div>
    <div class="card-body">
        <p class="text-muted small">Diese Mitglieder würden beim Abgleich nur markiert und nicht gelöscht.</p>
        <?php if (empty($vorschau['nicht_im_bmv'])): ?>
        <p class="text-muted mb-0">Keine.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover" id="bmvNichtTable">
            <thead><tr><th>Mitgliedsnr.</th><th>Nachname</th><th>Vorname</th><th>Geburtsdatum</th></tr></thead>
            <tbody>
            <?php foreach ($vorschau['nicht_im_bmv'] as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars((string)$m['mitgliedsnummer']); ?></td>
                <td><?php echo htmlspecialchars($m['nachname']); ?></td>
                <td><?php echo htmlspecialchars($m['vorname']); ?></td>
                <td><?php echo $m['geburtsdatum'] ? date('d.m.Y', strtotime($m['geburtsdatum'])) : '–'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Zuordnung vorhanden</h5></div>
    <div class="card-body">
        <p class="text-muted mb-0"><?php echo count($vorschau['aktualisiert']); ?> Mitglieder sind bereits zugeordnet und würden aktualisiert.</p>
    </div>
</div>

<form method="POST" class="d-flex justify-content-end"
      onsubmit="return confirm('Mitglieder aus dem BMV in Syncopa übernehmen? Neue Mitglieder werden angelegt, vorhandene aktualisiert. Im BMV wird nichts geändert.');">
    <input type="hidden" name="aktion" value="uebernehmen">
    <button type="submit" class="btn btn-primary btn-lg"
            <?php echo (empty($vorschau['neu']) && empty($vorschau['aktualisiert']) && empty($vorschau['nicht_im_bmv'])) ? 'disabled' : ''; ?>>
        <i class="bi bi-check-lg"></i> In Syncopa übernehmen
    </button>
</form>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
