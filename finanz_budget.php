<?php
// finanz_budget.php – Voranschlag (Budget) je Kategorie und Jahr, mit Ist-Werten von Jahr und Vorjahr
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'lesen');
$darfSchreiben = Session::checkPermission('finanzen', 'schreiben');

$jahr = (int)($_GET['jahr'] ?? $_POST['jahr'] ?? date('Y'));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $darfSchreiben) {
    Kassabuch::budgetSpeichern($jahr, (array)($_POST['budget'] ?? []));
    Session::setFlashMessage('success', "Voranschlag $jahr gespeichert.");
    header('Location: finanz_budget.php?jahr=' . $jahr);
    exit;
}
if (isset($_GET['vorjahr_uebernehmen']) && $darfSchreiben) {
    $ist = Kassabuch::kategorieSummen($jahr - 1);
    $werte = [];
    foreach (Kassabuch::kategorien(null, true) as $c) {
        $s = $ist[(int)$c['id']] ?? null;
        if ($s) $werte[$c['id']] = number_format($c['typ'] === 'ausgabe' ? $s['ausgaben'] : ($c['typ'] === 'einnahme' ? $s['einnahmen'] : $s['einnahmen'] - $s['ausgaben']), 2, '.', '');
    }
    Kassabuch::budgetSpeichern($jahr, $werte);
    Session::setFlashMessage('success', 'Ist-Werte ' . ($jahr - 1) . ' als Voranschlag übernommen. Bitte anpassen und speichern.');
    header('Location: finanz_budget.php?jahr=' . $jahr);
    exit;
}

$budget = Kassabuch::budget($jahr);
$ist = Kassabuch::kategorieSummen($jahr);
$istVorjahr = Kassabuch::kategorieSummen($jahr - 1);
$gruppen = ['einnahme' => 'Einnahmen', 'ausgabe' => 'Ausgaben'];
$kat = Kassabuch::kategorien(null, true);

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-clipboard-data"></i> Voranschlag <?php echo $jahr; ?></h1>
    <div class="d-flex gap-2">
        <a href="?jahr=<?php echo $jahr - 1; ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i> <?php echo $jahr - 1; ?></a>
        <a href="?jahr=<?php echo $jahr + 1; ?>" class="btn btn-outline-secondary"><?php echo $jahr + 1; ?> <i class="bi bi-chevron-right"></i></a>
        <a href="finanzen_bericht.php?art=budget&jahr=<?php echo $jahr; ?>" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Drucken</a>
        <a href="finanzen.php?jahr=<?php echo $jahr; ?>&tab=berichte" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kassabuch</a>
    </div>
</div>
<form method="POST">
<input type="hidden" name="jahr" value="<?php echo $jahr; ?>">
<div class="card">
    <div class="table-responsive">
    <table class="table mb-0 align-middle">
        <thead class="table-light"><tr><th>Kategorie</th><th class="text-end">Ist <?php echo $jahr - 1; ?></th><th style="width:180px">Voranschlag <?php echo $jahr; ?></th><th class="text-end">Ist <?php echo $jahr; ?></th><th class="text-end">Abweichung</th></tr></thead>
        <tbody>
        <?php $summe = ['einnahme' => [0, 0, 0], 'ausgabe' => [0, 0, 0]];
        foreach ($gruppen as $gt => $gl): ?>
            <tr class="table-light"><td colspan="5" class="fw-semibold"><?php echo $gl; ?></td></tr>
            <?php foreach ($kat as $c): if ($c['typ'] !== $gt && !($c['typ'] === 'beide' && $gt === 'einnahme')) continue;
                $feld = $c['typ'] === 'beide' ? null : ($gt === 'einnahme' ? 'einnahmen' : 'ausgaben');
                $wert = fn($s) => $s ? ($feld ? $s[$feld] : $s['einnahmen'] - $s['ausgaben']) : 0;
                $vj = $wert($istVorjahr[(int)$c['id']] ?? null); $ij = $wert($ist[(int)$c['id']] ?? null); $bu = $budget[(int)$c['id']] ?? null;
                $summe[$gt][0] += $vj; $summe[$gt][1] += (float)$bu; $summe[$gt][2] += $ij;
                $abw = $bu !== null ? $ij - $bu : null; ?>
            <tr>
                <td><?php echo htmlspecialchars($c['name']); ?><?php echo $c['typ'] === 'beide' ? ' <span class="small text-muted">(Saldo)</span>' : ''; ?></td>
                <td class="text-end text-muted"><?php echo Kassabuch::euro($vj); ?></td>
                <td><div class="input-group input-group-sm"><input type="number" step="0.01" class="form-control text-end" name="budget[<?php echo $c['id']; ?>]" value="<?php echo $bu !== null ? number_format($bu, 2, '.', '') : ''; ?>" <?php echo $darfSchreiben ? '' : 'disabled'; ?>><span class="input-group-text">€</span></div></td>
                <td class="text-end"><?php echo Kassabuch::euro($ij); ?></td>
                <td class="text-end <?php echo $abw === null ? '' : (($gt === 'einnahme' ? $abw >= 0 : $abw <= 0) ? 'text-success' : 'text-danger'); ?>"><?php echo $abw === null ? '–' : ($abw >= 0 ? '+' : '') . Kassabuch::euro($abw); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-semibold"><td>Summe <?php echo $gl; ?></td><td class="text-end"><?php echo Kassabuch::euro($summe[$gt][0]); ?></td><td class="text-end pe-4"><?php echo Kassabuch::euro($summe[$gt][1]); ?></td><td class="text-end"><?php echo Kassabuch::euro($summe[$gt][2]); ?></td><td></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="fw-bold border-top"><td>Ergebnis</td>
            <td class="text-end"><?php echo Kassabuch::euro($summe['einnahme'][0] - $summe['ausgabe'][0]); ?></td>
            <td class="text-end pe-4"><?php echo Kassabuch::euro($summe['einnahme'][1] - $summe['ausgabe'][1]); ?></td>
            <td class="text-end"><?php echo Kassabuch::euro($summe['einnahme'][2] - $summe['ausgabe'][2]); ?></td><td></td></tr></tfoot>
    </table>
    </div>
    <?php if ($darfSchreiben): ?>
    <div class="card-footer d-flex flex-wrap justify-content-between gap-2">
        <a href="?jahr=<?php echo $jahr; ?>&vorjahr_uebernehmen=1" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Ist-Werte <?php echo $jahr - 1; ?> als Voranschlag übernehmen? Vorhandene Werte werden überschrieben.')"><i class="bi bi-clipboard-plus"></i> Ist-Werte <?php echo $jahr - 1; ?> übernehmen</a>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Voranschlag speichern</button>
    </div>
    <?php endif; ?>
</div>
</form>
<?php include 'includes/footer.php'; ?>
