<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'lesen');

$festId  = isset($_GET['fest_id']) ? (int)$_GET['fest_id'] : 0;
$festObj = new Fest();
$fest    = $festObj->getById($festId);

if (!$fest) {
    Session::setFlashMessage('danger', 'Fest nicht gefunden.');
    header('Location: feste.php'); exit;
}

$abrObj     = new FestAbrechnung();
$stationObj = new FestStation();
$stationen  = $stationObj->getByFest($festId);

// POST: Posten speichern / löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Session::checkPermission('fest', 'schreiben')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $data = [
            'fest_id'     => $festId,
            'typ'         => in_array($_POST['typ'], ['einnahme','ausgabe']) ? $_POST['typ'] : 'einnahme',
            'kategorie'   => trim($_POST['kategorie'] ?? ''),
            'bezeichnung' => trim($_POST['bezeichnung'] ?? ''),
            'betrag'      => str_replace(',', '.', $_POST['betrag'] ?? '0'),
            'station_id'  => $_POST['station_id'] ?: null,
            'notizen'     => trim($_POST['notizen'] ?? ''),
            'erstellt_von'=> Session::getUserId(),
        ];
        if ($data['bezeichnung'] && $data['betrag'] > 0) {
            $editId = (int)($_POST['edit_id'] ?? 0);
            if ($editId) $abrObj->update($editId, $data);
            else         $abrObj->create($data);
        }
    } elseif ($action === 'delete') {
        $delId = (int)($_POST['del_id'] ?? 0);
        if ($delId) $abrObj->delete($delId);
    }
    header('Location: fest_abrechnung.php?fest_id=' . $festId . '#' . ($_POST['typ'] ?? 'einnahme'));
    exit;
}

// Daten laden
$posten         = $abrObj->getByFest($festId);
$einkaufAusg    = $abrObj->getEinkaufAusgaben($festId);
$einkaufBestellt = $abrObj->getEinkaufBestellt($festId);
$vertragsAusg   = $abrObj->getVertragsAusgaben($festId);
$summary        = $abrObj->getSummary($festId);

$einnahmenPosten = array_filter($posten, fn($p) => $p['typ'] === 'einnahme');
$ausgabenPosten  = array_filter($posten, fn($p) => $p['typ'] === 'ausgabe');

// Erhaltene und bestellte Einkäufe in einer Liste (erhaltene zuerst)
$einkaufAlle   = array_merge($einkaufAusg, $einkaufBestellt);
$darfSchreiben = Session::checkPermission('fest', 'schreiben');

$katEin = FestAbrechnung::kategorienEinnahmen();
$katAus = FestAbrechnung::kategorienAusgaben();

$ergebnisPositiv = $summary['ergebnis'] >= 0;

include 'includes/header.php';
?>

<?php include 'includes/fest_tabs.php'; ?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-calculator"></i> Abrechnung</h1>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
        <i class="bi bi-printer"></i> Drucken / PDF
    </button>
</div>

<!-- Zusammenfassung -->
<div class="row mb-4">
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-success h-100">
            <div class="card-body">
                <div>
                    <h6>Einnahmen</h6>
                    <h2><?php echo number_format($summary['einnahmen'], 2, ',', '.'); ?> €</h2>
                </div>
                <i class="bi bi-arrow-up-circle stat-icon text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-danger h-100">
            <div class="card-body">
                <div>
                    <h6>Ausgaben</h6>
                    <h2><?php echo number_format($summary['ausgaben'], 2, ',', '.'); ?> €</h2>
                    <small class="text-muted">
                        Einkäufe: <?php echo number_format($summary['einkauf_summe'] + $summary['bestellt_summe'], 2, ',', '.'); ?> €
                        <?php if ($summary['bestellt_summe'] > 0): ?>
                        (davon bestellt: <?php echo number_format($summary['bestellt_summe'], 2, ',', '.'); ?> €)
                        <?php endif; ?>
                        <?php if ($summary['vertrags_summe'] > 0): ?>
                        · Honorare: <?php echo number_format($summary['vertrags_summe'], 2, ',', '.'); ?> €
                        <?php endif; ?>
                    </small>
                </div>
                <i class="bi bi-arrow-down-circle stat-icon text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-<?php echo $ergebnisPositiv ? 'success' : 'danger'; ?> h-100">
            <div class="card-body">
                <div>
                    <h6><?php echo $summary['bestellt_anzahl'] ? 'Voraussichtliches Ergebnis' : 'Ergebnis'; ?></h6>
                    <h2 class="text-<?php echo $ergebnisPositiv ? 'success' : 'danger'; ?>">
                        <?php echo ($ergebnisPositiv ? '+' : '') . number_format($summary['ergebnis'], 2, ',', '.'); ?> €
                    </h2>
                    <small class="text-muted"><?php echo $ergebnisPositiv ? 'Überschuss' : 'Verlust'; ?><?php if ($summary['bestellt_anzahl']): ?> · inkl. bestellter Einkäufe<?php endif; ?></small>
                </div>
                <i class="bi bi-<?php echo $ergebnisPositiv ? 'graph-up-arrow text-success' : 'graph-down-arrow text-danger'; ?> stat-icon"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-secondary h-100">
            <div class="card-body">
                <div>
                    <h6>Deckungsgrad</h6>
                    <?php $deckung = $summary['ausgaben'] > 0 ? round($summary['einnahmen'] / $summary['ausgaben'] * 100) : 0; ?>
                    <h2><?php echo $deckung; ?> %</h2>
                    <small class="text-muted">Einnahmen / Ausgaben</small>
                </div>
                <i class="bi bi-pie-chart stat-icon text-secondary"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
<!-- ═══ EINNAHMEN ══════════════════════════════════════════════════════════ -->
<div class="col-lg-6 mb-4" id="einnahme">
    <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center" style="border-left:3px solid var(--c-success)">
            <h5 class="mb-0 text-success"><i class="bi bi-arrow-up-circle"></i> Einnahmen</h5>
            <strong><?php echo number_format($summary['einnahmen'], 2, ',', '.'); ?> €</strong>
        </div>
        <div class="card-body p-0">



            <?php if (!empty($einnahmenPosten)): ?>
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Bezeichnung</th><th>Kategorie</th><th>Station</th><th class="text-end">Betrag</th><th class="no-print"></th></tr>
                </thead>
                <tbody>
                <?php foreach ($einnahmenPosten as $p): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($p['bezeichnung']); ?></strong>
                        <?php if ($p['notizen']): ?><div class="small text-muted"><?php echo htmlspecialchars($p['notizen']); ?></div><?php endif; ?>
                    </td>
                    <td class="small text-muted"><?php echo htmlspecialchars($katEin[$p['kategorie']] ?? $p['kategorie']); ?></td>
                    <td class="small text-muted"><?php echo htmlspecialchars($p['station_name'] ?? '–'); ?></td>
                    <td class="text-end text-success fw-semibold"><?php echo number_format($p['betrag'], 2, ',', '.'); ?> €</td>
                    <td class="text-end no-print">
                        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                        <button class="btn btn-xs btn-outline-secondary btn-edit-posten"
                                data-id="<?php echo $p['id']; ?>"
                                data-typ="einnahme"
                                data-kategorie="<?php echo htmlspecialchars($p['kategorie']); ?>"
                                data-bezeichnung="<?php echo htmlspecialchars($p['bezeichnung'], ENT_QUOTES); ?>"
                                data-betrag="<?php echo number_format($p['betrag'], 2, '.', ''); ?>"
                                data-station="<?php echo $p['station_id'] ?? ''; ?>"
                                data-notizen="<?php echo htmlspecialchars($p['notizen'] ?? '', ENT_QUOTES); ?>"
                                style="font-size:11px;padding:1px 6px">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Posten löschen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="del_id" value="<?php echo $p['id']; ?>">
                            <input type="hidden" name="typ" value="einnahme">
                            <button type="submit" class="btn btn-xs btn-outline-danger" style="font-size:11px;padding:1px 6px"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-success">
                        <td colspan="3"><strong>Gesamt Einnahmen</strong></td>
                        <td class="text-end fw-bold"><?php echo number_format($summary['einnahmen'], 2, ',', '.'); ?> €</td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>

            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
            <div class="p-3 no-print border-top">
                <div class="small fw-semibold text-success mb-2"><i class="bi bi-plus-circle"></i> Einnahme hinzufügen</div>
                <form method="POST" id="form-einnahme" class="row g-2">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="typ" value="einnahme">
                    <input type="hidden" name="edit_id" id="ein-edit-id" value="">
                    <div class="col-md-5">
                        <input type="text" name="bezeichnung" id="ein-bezeichnung" class="form-control form-control-sm" placeholder="Bezeichnung *" required>
                    </div>
                    <div class="col-md-3">
                        <select name="kategorie" id="ein-kategorie" class="form-select form-select-sm">
                            <?php foreach ($katEin as $kv => $kl): ?>
                            <option value="<?php echo $kv; ?>"><?php echo $kl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <input type="number" name="betrag" id="ein-betrag" class="form-control" placeholder="Betrag" step="0.01" min="0.01" required>
                            <span class="input-group-text">€</span>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <select name="station_id" id="ein-station" class="form-select form-select-sm">
                            <option value="">– Keine Station –</option>
                            <?php foreach ($stationen as $st): ?>
                            <option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="notizen" id="ein-notizen" class="form-control form-control-sm" placeholder="Notizen (optional)">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-check-lg"></i></button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- ═══ AUSGABEN ══════════════════════════════════════════════════════════ -->
<div class="col-lg-6 mb-4" id="ausgabe">
    <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center" style="border-left:3px solid var(--c-danger)">
            <h5 class="mb-0 text-danger"><i class="bi bi-arrow-down-circle"></i> Ausgaben</h5>
            <strong><?php echo number_format($summary['ausgaben'], 2, ',', '.'); ?> €</strong>
        </div>
        <div class="card-body p-0">



            <!-- Alle Ausgaben in einer Liste, gruppiert -->
            <table class="table table-sm mb-0 abrechnung-liste">
                <thead class="table-light">
                    <tr><th>Posten</th><th class="text-end">Betrag</th><?php if ($darfSchreiben): ?><th class="no-print" style="width:70px"></th><?php endif; ?></tr>
                </thead>
                <tbody>
                <?php if ($einkaufAlle): ?>
                <tr class="gruppe">
                    <td><i class="bi bi-cart3"></i> Einkäufe
                        <?php if ($summary['bestellt_summe'] > 0): ?>
                        <span class="fw-normal text-muted small ms-1">davon bestellt: <?php echo number_format($summary['bestellt_summe'], 2, ',', '.'); ?> €</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-danger"><?php echo number_format($summary['einkauf_summe'] + $summary['bestellt_summe'], 2, ',', '.'); ?> €</td>
                    <?php if ($darfSchreiben): ?><td class="no-print text-end"><a href="fest_einkauefe.php?fest_id=<?php echo $festId; ?>" class="small" title="Zu den Einkäufen"><i class="bi bi-box-arrow-up-right"></i></a></td><?php endif; ?>
                </tr>
                <?php foreach ($einkaufAlle as $e): $istBestellt = $e['status'] === 'bestellt'; ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($e['bezeichnung']); ?>
                        <?php if ($e['menge']): ?><span class="text-muted">· <?php echo (int)$e['menge']; ?> <?php echo htmlspecialchars($e['einheit'] ?? ''); ?></span><?php endif; ?>
                        <?php if ($istBestellt): ?><span class="badge bg-warning text-dark ms-1" title="Noch nicht geliefert – als voraussichtliche Ausgabe eingerechnet">bestellt</span><?php endif; ?>
                        <?php $info = array_filter([$e['lieferant'] ?? '', $e['station_name'] ?? '']); if ($info): ?>
                        <div class="unterzeile"><?php echo htmlspecialchars(implode(' · ', $info)); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?php echo $e['preis_gesamt'] !== null ? number_format($e['preis_gesamt'], 2, ',', '.') . ' €' : '<em class="text-muted">Preis offen</em>'; ?></td>
                    <?php if ($darfSchreiben): ?><td class="no-print"></td><?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($vertragsAusg): ?>
                <tr class="gruppe">
                    <td><i class="bi bi-music-note-beamed"></i> Honorare / Verträge</td>
                    <td class="text-end text-danger"><?php echo number_format($summary['vertrags_summe'], 2, ',', '.'); ?> €</td>
                    <?php if ($darfSchreiben): ?><td class="no-print"></td><?php endif; ?>
                </tr>
                <?php
                $zStatus = ['offen' => ['offen', 'warning'], 'teilweise' => ['teilweise bezahlt', 'info'], 'bezahlt' => ['bezahlt', 'success'], 'storniert' => ['storniert', 'danger']];
                foreach ($vertragsAusg as $v):
                    $zs = $zStatus[$v['zahlungsstatus']] ?? [$v['zahlungsstatus'], 'secondary'];
                ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($v['band_name']); ?>
                        <span class="badge bg-<?php echo $zs[1]; ?> ms-1"><?php echo htmlspecialchars($zs[0]); ?></span>
                        <?php if ($v['auftritt_datum']): ?><div class="unterzeile">Auftritt <?php echo date('d.m.Y', strtotime($v['auftritt_datum'])); ?></div><?php endif; ?>
                    </td>
                    <td class="text-end"><?php echo number_format($v['honorar'], 2, ',', '.'); ?> €</td>
                    <?php if ($darfSchreiben): ?><td class="no-print"></td><?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($ausgabenPosten): ?>
                <tr class="gruppe">
                    <td><i class="bi bi-plus-square"></i> Sonstige Ausgaben</td>
                    <td class="text-end text-danger"><?php echo number_format($summary['manuelle_ausgaben'], 2, ',', '.'); ?> €</td>
                    <?php if ($darfSchreiben): ?><td class="no-print"></td><?php endif; ?>
                </tr>
                <?php foreach ($ausgabenPosten as $p): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($p['bezeichnung']); ?>
                        <div class="unterzeile"><?php echo htmlspecialchars(implode(' · ', array_filter([$katAus[$p['kategorie']] ?? $p['kategorie'], $p['notizen'] ?? '']))); ?></div>
                    </td>
                    <td class="text-end"><?php echo number_format($p['betrag'], 2, ',', '.'); ?> €</td>
                    <?php if ($darfSchreiben): ?>
                    <td class="text-end no-print text-nowrap">
                        <button class="btn btn-xs btn-outline-secondary btn-edit-posten" title="Bearbeiten"
                                data-id="<?php echo $p['id']; ?>" data-typ="ausgabe"
                                data-kategorie="<?php echo htmlspecialchars($p['kategorie']); ?>"
                                data-bezeichnung="<?php echo htmlspecialchars($p['bezeichnung'], ENT_QUOTES); ?>"
                                data-betrag="<?php echo number_format($p['betrag'], 2, '.', ''); ?>"
                                data-station="" data-notizen="<?php echo htmlspecialchars($p['notizen'] ?? '', ENT_QUOTES); ?>"
                                style="font-size:11px;padding:1px 6px"><i class="bi bi-pencil"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Posten löschen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="del_id" value="<?php echo $p['id']; ?>">
                            <input type="hidden" name="typ" value="ausgabe">
                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Löschen" style="font-size:11px;padding:1px 6px"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!$einkaufAlle && !$vertragsAusg && !$ausgabenPosten): ?>
                <tr><td colspan="3" class="text-muted text-center py-3">Noch keine Ausgaben.</td></tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="summe">
                        <td>Gesamt Ausgaben<?php if ($summary['bestellt_summe'] > 0): ?> <span class="fw-normal text-muted small">(inkl. bestellter Einkäufe)</span><?php endif; ?></td>
                        <td class="text-end text-danger"><?php echo number_format($summary['ausgaben'], 2, ',', '.'); ?> €</td>
                        <?php if ($darfSchreiben): ?><td class="no-print"></td><?php endif; ?>
                    </tr>
                </tfoot>
            </table>

            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
            <div class="p-3 no-print border-top">
                <div class="small fw-semibold text-danger mb-2"><i class="bi bi-plus-circle"></i> Sonstige Ausgabe hinzufügen</div>
                <form method="POST" id="form-ausgabe" class="row g-2">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="typ" value="ausgabe">
                    <input type="hidden" name="edit_id" id="aus-edit-id" value="">
                    <div class="col-md-5">
                        <input type="text" name="bezeichnung" id="aus-bezeichnung" class="form-control form-control-sm" placeholder="Bezeichnung *" required>
                    </div>
                    <div class="col-md-4">
                        <select name="kategorie" id="aus-kategorie" class="form-select form-select-sm">
                            <?php foreach ($katAus as $kv => $kl): ?>
                            <option value="<?php echo $kv; ?>"><?php echo $kl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <input type="number" name="betrag" id="aus-betrag" class="form-control" placeholder="Betrag" step="0.01" min="0.01" required>
                            <span class="input-group-text">€</span>
                        </div>
                    </div>
                    <div class="col-md-10">
                        <input type="text" name="notizen" id="aus-notizen" class="form-control form-control-sm" placeholder="Notizen (optional)">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-danger w-100"><i class="bi bi-check-lg"></i></button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</div><!-- /row -->


<?php include 'includes/footer.php'; ?>
<style>
.abrechnung-liste td, .abrechnung-liste th { vertical-align: middle; }
.abrechnung-liste tr.gruppe td { background: var(--bs-tertiary-bg, #f3f5f8); font-weight: 600; font-size: .85rem; padding-top: .55rem; padding-bottom: .55rem; }
.abrechnung-liste tr.gruppe .bi { margin-right: 4px; }
.abrechnung-liste tr:not(.gruppe) td:first-child { padding-left: 1.6rem; }
.abrechnung-liste .unterzeile { font-size: .78rem; color: var(--text-muted, #6c757d); }
.abrechnung-liste .badge { font-size: .68rem; font-weight: 600; }
.abrechnung-liste tfoot .summe td { font-weight: 700; border-top: 2px solid var(--border, #dee2e6); }
@media print {
    .no-print, .topbar, .sidebar, .sidebar-overlay,
    .fest-tabs-header, .page-header .btn { display: none !important; }
    .main-wrapper { margin-left: 0 !important; }
    .main-content { padding: 10px !important; }
    .card { box-shadow: none !important; break-inside: avoid; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>
<script>
// Posten bearbeiten – Felder vorausfüllen
document.querySelectorAll('.btn-edit-posten').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var d = this.dataset;
        if (d.typ === 'einnahme') {
            document.getElementById('ein-edit-id').value     = d.id;
            document.getElementById('ein-bezeichnung').value = d.bezeichnung;
            document.getElementById('ein-kategorie').value   = d.kategorie;
            document.getElementById('ein-betrag').value      = d.betrag;
            document.getElementById('ein-station').value     = d.station;
            document.getElementById('ein-notizen').value     = d.notizen;
            document.getElementById('ein-bezeichnung').focus();
            document.getElementById('form-einnahme').scrollIntoView({behavior:'smooth', block:'start'});
        } else {
            document.getElementById('aus-edit-id').value     = d.id;
            document.getElementById('aus-bezeichnung').value = d.bezeichnung;
            document.getElementById('aus-kategorie').value   = d.kategorie;
            document.getElementById('aus-betrag').value      = d.betrag;
            document.getElementById('aus-notizen').value     = d.notizen;
            document.getElementById('aus-bezeichnung').focus();
            document.getElementById('form-ausgabe').scrollIntoView({behavior:'smooth', block:'start'});
        }
    });
});
</script>
