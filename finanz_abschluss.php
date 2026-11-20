<?php
// finanz_abschluss.php – Jahresabschluss: Kassaprüfer, Feststellungen, Abschluss (Sperre) und Wiederöffnen (nur Admin)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'lesen');
$darfSchreiben = Session::checkPermission('finanzen', 'schreiben');

$jahr = (int)($_GET['jahr'] ?? $_POST['jahr'] ?? date('Y') - 1);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $darfSchreiben) {
    $aktion = $_POST['aktion'] ?? 'speichern';
    if ($aktion === 'speichern' && !Kassabuch::istGesperrt($jahr)) {
        Kassabuch::abschlussSpeichern($jahr, $_POST);
        Session::setFlashMessage('success', 'Angaben zur Kassaprüfung gespeichert.');
    } elseif ($aktion === 'abschliessen') {
        Kassabuch::abschlussSpeichern($jahr, $_POST);
        Kassabuch::abschliessen($jahr, true);
        Session::setFlashMessage('success', "Das Jahr $jahr ist abgeschlossen. Buchungen sind jetzt gesperrt.");
    } elseif ($aktion === 'oeffnen' && Session::isAdmin()) {
        Kassabuch::abschliessen($jahr, false);
        Session::setFlashMessage('success', "Das Jahr $jahr ist wieder offen.");
    }
    header('Location: finanz_abschluss.php?jahr=' . $jahr);
    exit;
}

$ab = Kassabuch::abschluss($jahr);
$gesperrt = (bool)$ab['abgeschlossen'];
$summen = Kassabuch::summen($jahr);
$konten = Kassabuch::kontenUebersicht($jahr);
$ohneBeleg = (int)Database::getInstance()->fetchOne("SELECT COUNT(*) c FROM finanzen WHERE YEAR(datum) = ? AND beleg_datei IS NULL", [$jahr])['c'];

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-lock"></i> Jahresabschluss <?php echo $jahr; ?></h1>
    <div class="d-flex gap-2">
        <a href="?jahr=<?php echo $jahr - 1; ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i> <?php echo $jahr - 1; ?></a>
        <a href="?jahr=<?php echo $jahr + 1; ?>" class="btn btn-outline-secondary"><?php echo $jahr + 1; ?> <i class="bi bi-chevron-right"></i></a>
        <a href="finanzen.php?jahr=<?php echo $jahr; ?>&tab=berichte" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kassabuch</a>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header">1. Zahlen prüfen</div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col"><div class="small text-muted">Einnahmen</div><div class="fw-bold text-success"><?php echo Kassabuch::euro($summen['einnahmen']); ?></div></div>
                    <div class="col"><div class="small text-muted">Ausgaben</div><div class="fw-bold text-danger"><?php echo Kassabuch::euro($summen['ausgaben']); ?></div></div>
                    <div class="col"><div class="small text-muted">Ergebnis</div><div class="fw-bold"><?php echo Kassabuch::euro($summen['ergebnis']); ?></div></div>
                    <div class="col"><div class="small text-muted">Buchungen</div><div class="fw-bold"><?php echo $summen['anzahl']; ?></div></div>
                </div>
                <table class="table table-sm mb-3">
                    <thead><tr><th>Konto</th><th class="text-end">1.1.</th><th class="text-end">31.12.</th></tr></thead>
                    <tbody><?php foreach ($konten as $k): ?><tr><td><?php echo htmlspecialchars($k['name']); ?></td><td class="text-end"><?php echo Kassabuch::euro($k['anfang']); ?></td><td class="text-end fw-semibold"><?php echo Kassabuch::euro($k['ende']); ?></td></tr><?php endforeach; ?></tbody>
                </table>
                <?php if ($ohneBeleg): ?><div class="alert alert-warning alert-permanent small mb-3"><i class="bi bi-exclamation-triangle"></i> <?php echo $ohneBeleg; ?> Buchungen haben keine Belegdatei. Details in der Belegliste.</div><?php endif; ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach (['journal' => 'Kassabuch', 'ea' => 'Einnahmen-Ausgaben-Rechnung', 'vermoegen' => 'Vermögensübersicht', 'belege' => 'Belegliste'] as $art => $titel): ?>
                    <a href="finanzen_bericht.php?art=<?php echo $art; ?>&jahr=<?php echo $jahr; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i> <?php echo $titel; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <form method="POST" class="card mb-4">
            <input type="hidden" name="jahr" value="<?php echo $jahr; ?>">
            <div class="card-header">2. Kassaprüfung</div>
            <fieldset class="card-body" <?php echo $gesperrt || !$darfSchreiben ? 'disabled' : ''; ?>>
                <div class="mb-2"><label class="form-label small" for="pruefer1">Rechnungsprüfer/in 1</label><input class="form-control" id="pruefer1" name="pruefer1" value="<?php echo htmlspecialchars($ab['pruefer1'] ?? ''); ?>"></div>
                <div class="mb-2"><label class="form-label small" for="pruefer2">Rechnungsprüfer/in 2</label><input class="form-control" id="pruefer2" name="pruefer2" value="<?php echo htmlspecialchars($ab['pruefer2'] ?? ''); ?>"></div>
                <div class="mb-2"><label class="form-label small" for="pruef_datum">Datum der Prüfung</label><input type="date" class="form-control" id="pruef_datum" name="pruef_datum" value="<?php echo htmlspecialchars($ab['pruef_datum'] ?? ''); ?>"></div>
                <div class="mb-3"><label class="form-label small" for="feststellungen">Feststellungen</label><textarea class="form-control" id="feststellungen" name="feststellungen" rows="3" placeholder="z. B. Keine Beanstandungen. Belege vollständig."><?php echo htmlspecialchars($ab['feststellungen'] ?? ''); ?></textarea></div>
                <div class="d-flex flex-wrap gap-2">
                    <button name="aktion" value="speichern" class="btn btn-outline-primary"><i class="bi bi-save"></i> Speichern</button>
                    <a href="finanzen_bericht.php?art=pruefung&jahr=<?php echo $jahr; ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Prüfbericht</a>
                </div>
            </fieldset>
            <div class="card-footer">
                <div class="small fw-semibold mb-2">3. Abschluss</div>
                <?php if ($gesperrt): ?>
                <div class="small mb-2"><i class="bi bi-lock-fill"></i> Abgeschlossen am <?php echo date('d.m.Y H:i', strtotime($ab['abgeschlossen_am'])); ?>. Buchungen, Anfangsbestände und Belege dieses Jahres sind gesperrt.</div>
                <?php if (Session::isAdmin()): ?><button name="aktion" value="oeffnen" class="btn btn-sm btn-outline-danger" onclick="return confirm('Das Jahr <?php echo $jahr; ?> wieder öffnen? Danach können Buchungen geändert werden.')"><i class="bi bi-unlock"></i> Wieder öffnen</button><?php endif; ?>
                <?php elseif ($darfSchreiben): ?>
                <div class="small text-muted mb-2">Nach der Prüfung abschließen. Danach kann nichts mehr geändert werden (nur ein Administrator kann das Jahr wieder öffnen).</div>
                <button name="aktion" value="abschliessen" class="btn btn-sm btn-danger" onclick="return confirm('Das Jahr <?php echo $jahr; ?> abschließen?')"><i class="bi bi-lock"></i> Jahr <?php echo $jahr; ?> abschließen</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
