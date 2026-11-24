<?php
// finanz_kassasturz.php – Bargeld zählen (Scheine/Münzen), mit dem Kassastand laut Kassabuch vergleichen, Protokoll speichern
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'lesen');
$darfSchreiben = Session::checkPermission('finanzen', 'schreiben');
$db = Database::getInstance();

$konten = array_values(array_filter(Kassabuch::konten(true), fn($k) => $k['art'] === 'kassa')) ?: Kassabuch::konten(true);
$kontoId = (int)($_GET['konto'] ?? $_POST['konto_id'] ?? ($konten[0]['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $darfSchreiben) {
    try {
        $neu = Kassabuch::kassasturzSpeichern($kontoId, (array)($_POST['anzahl'] ?? []), (string)($_POST['gezaehlt_von'] ?? ''), (string)($_POST['bemerkung'] ?? ''));
        Session::setFlashMessage('success', 'Kassasturz gespeichert.');
        header('Location: finanz_kassasturz.php?konto=' . $kontoId . '&gespeichert=' . $neu);
        exit;
    } catch (RuntimeException $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
}

$soll = $kontoId ? Kassabuch::stand($kontoId, date('Y-m-d')) : 0;
$protokolle = $db->fetchAll("SELECT s.*, k.name AS konto_name FROM finanz_kassasturz s JOIN finanz_konten k ON k.id = s.konto_id ORDER BY s.datum DESC LIMIT 30");

include 'includes/header.php';
?>
<style>.zaehl td { vertical-align: middle; } .zaehl input { max-width: 110px; } .zaehl .summe { font-variant-numeric: tabular-nums; }</style>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-coin"></i> Kassasturz</h1>
    <div class="d-flex gap-2">
        <a href="finanzen_bericht.php?art=kassasturz&konto=<?php echo $kontoId; ?>" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Leeres Zählblatt</a>
        <a href="finanzen.php?tab=berichte" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kassabuch</a>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-7">
        <form method="POST" class="card">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <label for="konto_id" class="mb-0">Kassa:</label>
                <select name="konto_id" id="konto_id" class="form-select form-select-sm w-auto" onchange="location='?konto='+this.value">
                    <?php foreach ($konten as $k): ?><option value="<?php echo $k['id']; ?>" <?php echo (int)$k['id'] === $kontoId ? 'selected' : ''; ?>><?php echo htmlspecialchars($k['name']); ?></option><?php endforeach; ?>
                </select>
                <span class="ms-auto small text-muted">Stand laut Kassabuch heute: <strong data-soll="<?php echo $soll; ?>"><?php echo Kassabuch::euro($soll); ?></strong></span>
            </div>
            <div class="table-responsive">
            <table class="table table-sm mb-0 zaehl">
                <thead class="table-light"><tr><th>Wert</th><th>Anzahl</th><th class="text-end">Summe</th></tr></thead>
                <tbody>
                <?php foreach (Kassabuch::STUECKELUNG as $w): ?>
                <tr>
                    <td><?php echo $w >= 5 ? '<i class="bi bi-cash"></i> ' . (int)$w . ' €-Schein' : '<i class="bi bi-coin"></i> ' . ($w >= 1 ? (int)$w . ' €' : (int)round($w * 100) . ' Cent'); ?></td>
                    <td><input type="number" min="0" step="1" class="form-control form-control-sm" name="anzahl[<?php echo $w; ?>]" data-wert="<?php echo $w; ?>" inputmode="numeric"></td>
                    <td class="text-end summe" data-summe="<?php echo $w; ?>">0,00 €</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot class="fw-bold">
                    <tr><td colspan="2">Gezählt (Ist)</td><td class="text-end summe" id="ist">0,00 €</td></tr>
                    <tr><td colspan="2">Laut Kassabuch (Soll)</td><td class="text-end summe"><?php echo Kassabuch::euro($soll); ?></td></tr>
                    <tr><td colspan="2">Differenz</td><td class="text-end summe" id="differenz"></td></tr>
                </tfoot>
            </table>
            </div>
            <?php if ($darfSchreiben): ?>
            <div class="card-body border-top row g-2">
                <div class="col-md-6"><label class="form-label small" for="gezaehlt_von">Gezählt von</label><input class="form-control form-control-sm" id="gezaehlt_von" name="gezaehlt_von" placeholder="z. B. Kassier und Kassaprüfer"></div>
                <div class="col-md-6"><label class="form-label small" for="bemerkung">Bemerkung</label><input class="form-control form-control-sm" id="bemerkung" name="bemerkung"></div>
                <div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-save"></i> Kassasturz speichern</button></div>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Gespeicherte Protokolle</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($protokolle as $p): $diff = $p['ist_betrag'] - $p['soll_betrag']; ?>
                <li class="list-group-item d-flex justify-content-between align-items-center gap-2 <?php echo (int)($_GET['gespeichert'] ?? 0) === (int)$p['id'] ? 'list-group-item-success' : ''; ?>">
                    <div><div><?php echo date('d.m.Y H:i', strtotime($p['datum'])); ?> · <?php echo htmlspecialchars($p['konto_name']); ?></div>
                        <div class="small text-muted">Ist <?php echo Kassabuch::euro((float)$p['ist_betrag']); ?> · Differenz <span class="<?php echo abs($diff) < 0.005 ? 'text-success' : 'text-danger'; ?>"><?php echo ($diff >= 0 ? '+' : '') . Kassabuch::euro($diff); ?></span></div></div>
                    <a href="finanzen_bericht.php?art=kassasturz&id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Protokoll drucken"><i class="bi bi-printer"></i></a>
                </li>
                <?php endforeach; ?>
                <?php if (!$protokolle): ?><li class="list-group-item text-muted small">Noch kein Kassasturz gespeichert.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script>
(function () {
    var fmt = function (b) { return b.toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'; };
    var soll = parseFloat(document.querySelector('[data-soll]').dataset.soll) || 0;
    function rechnen() {
        var ist = 0;
        document.querySelectorAll('[data-wert]').forEach(function (i) {
            var s = (parseInt(i.value, 10) || 0) * parseFloat(i.dataset.wert);
            ist += s; document.querySelector('[data-summe="' + i.dataset.wert + '"]').textContent = fmt(s);
        });
        ist = Math.round(ist * 100) / 100;
        document.getElementById('ist').textContent = fmt(ist);
        var d = Math.round((ist - soll) * 100) / 100, el = document.getElementById('differenz');
        el.textContent = (d >= 0 ? '+' : '') + fmt(d); el.className = 'text-end summe ' + (Math.abs(d) < 0.005 ? 'text-success' : 'text-danger');
    }
    document.querySelectorAll('[data-wert]').forEach(function (i) { i.addEventListener('input', rechnen); });
    rechnen();
})();
</script>
