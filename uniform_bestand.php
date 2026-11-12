<?php
// uniform_bestand.php – Bestand der Uniformen: je Kleidungsstück als Menge oder als Einzelstücke mit Nummer
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'lesen');

$uniformObj = new Uniform();
$darfSchreiben = Session::checkPermission('uniformen', 'schreiben');
$darfLoeschen = Session::checkPermission('uniformen', 'loeschen');
$ksId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$ks = $ksId ? $uniformObj->getKleidungsstueckById($ksId) : null;
if ($ksId && (!$ks || !$ks['aktiv'])) {
    Session::setFlashMessage('danger', 'Kleidungsstück nicht gefunden.');
    header('Location: uniform_bestand.php');
    exit;
}
$seite = 'uniform_bestand.php' . ($ksId ? '?id=' . $ksId : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ks) {
    try {
        if (!$darfSchreiben) throw new RuntimeException('Keine Berechtigung für diese Aktion.');
        $aktion = $_POST['aktion'] ?? '';
        $daten = ['grund' => $_POST['grund'] ?? '', 'bemerkung' => $_POST['bemerkung'] ?? '', 'datum' => $_POST['datum'] ?? null];
        switch ($aktion) {
            case 'zugang':
                $uniformObj->zugang($ksId, trim($_POST['groesse'] ?? ''), (int)($_POST['anzahl'] ?? 0), $daten);
                Session::setFlashMessage('success', 'Zugang gebucht.');
                break;
            case 'ausscheiden':
                $uniformObj->ausscheiden($ksId, trim($_POST['groesse'] ?? ''), (int)($_POST['anzahl'] ?? 0), $daten);
                Session::setFlashMessage('success', 'Ausgeschieden – der Bestand ist entsprechend gesunken.');
                break;
            case 'inventur':
                $n = $uniformObj->inventur($ksId, $_POST['gezaehlt'] ?? [], $_POST['datum'] ?? null);
                Session::setFlashMessage('success', $n ? "Inventur übernommen ($n Größen korrigiert)." : 'Inventur: Der Lagerstand stimmt überall.');
                break;
            case 'stuecke_anlegen':
                $neu = $uniformObj->stueckeAnlegen($ksId, ($_POST['vergabe'] ?? 'auto') === 'hand' ? ($_POST['nummern'] ?? '') : '', $_POST['groesse'] ?? '', [
                    'zustand' => $_POST['zustand'] ?? 'gut', 'grund' => $_POST['grund'] ?? '', 'datum' => $_POST['datum'] ?? null, 'bemerkungen' => $_POST['bemerkung'] ?? ''],
                    (int)($_POST['anzahl'] ?? 0));
                $anz = fn($n) => Uniform::nummerAnzeige($ks['kuerzel'], $n);
                Session::setFlashMessage('success', count($neu) === 1 ? 'Stück ' . $anz($neu[0]) . ' angelegt.'
                    : count($neu) . ' Stücke angelegt: ' . $anz($neu[0]) . ' bis ' . $anz(end($neu)) . '.');
                break;
            case 'stueck_bearbeiten':
                $uniformObj->stueckAktualisieren((int)$_POST['stueck_id'], $_POST);
                Session::setFlashMessage('success', 'Stück gespeichert.');
                break;
            case 'stueck_ausscheiden':
                $uniformObj->stueckAusscheiden((int)$_POST['stueck_id'], $_POST['grund'] ?? '', $_POST['datum'] ?? null);
                Session::setFlashMessage('success', 'Stück ausgeschieden.');
                break;
            case 'stueck_reaktivieren':
                $uniformObj->stueckReaktivieren((int)$_POST['stueck_id']);
                Session::setFlashMessage('success', 'Stück ist wieder im Lager.');
                break;
            case 'stueck_loeschen':
                if (!$darfLoeschen) throw new RuntimeException('Keine Berechtigung zum Löschen.');
                $uniformObj->stueckLoeschen((int)$_POST['stueck_id']);
                Session::setFlashMessage('success', 'Stück gelöscht.');
                break;
        }
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: ' . $seite);
    exit;
}

$zustandFarben = ['sehr gut' => 'success', 'gut' => 'info', 'befriedigend' => 'warning', 'schlecht' => 'danger'];
$artNamen = ['zugang' => ['Zugang', 'success', 'bi-box-arrow-in-down'], 'ausscheiden' => ['Ausgeschieden', 'danger', 'bi-x-circle'],
             'korrektur' => ['Inventur', 'secondary', 'bi-clipboard-check'], 'ausgabe' => ['Ausgabe', 'primary', 'bi-person-check'],
             'rueckgabe' => ['Rückgabe', 'info', 'bi-arrow-return-left']];
$g = fn($s) => $s === '' ? 'o. A.' : $s;   // Größe anzeigen

if ($ks) {
    $groessen = $uniformObj->bestandNachGroesse($ksId);
    $summe = Uniform::bestandSumme($groessen);
    $einzeln = $ks['bestandsart'] === 'einzeln';
    $stuecke = $einzeln ? $uniformObj->stuecke($ksId) : [];
    $verlauf = $uniformObj->verlauf($ksId);
    $ohneNummer = array_sum(array_map(fn($z) => $z['ohne_nummer'] ?? 0, $groessen));
    $nr = fn($n) => Uniform::nummerAnzeige($ks['kuerzel'], $n);
    try { $naechste = $einzeln ? $nr($uniformObj->naechsteNummern($ksId, 1)[0]) : ''; } catch (\Throwable $e) { $naechste = '–'; }
    $erfasst = $uniformObj->bestandErfasst($ks);
} else {
    $liste = $uniformObj->bestandUebersicht();
}

include 'includes/header.php';
?>

<?php if (!$ks): /* ======================= ÜBERSICHT ======================= */ ?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h2 mb-0"><i class="bi bi-boxes"></i> Uniform-Bestand</h1>
    <div class="d-flex gap-2">
        <?php if ($darfSchreiben): ?>
        <a href="uniform_kleidungsstuecke.php" class="btn btn-outline-primary"><i class="bi bi-tags"></i> Kleidungsstücke verwalten</a>
        <?php endif; ?>
        <a href="uniformen.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Uniformen</a>
    </div>
</div>

<div class="alert alert-info alert-permanent small">
    <i class="bi bi-info-circle"></i>
    <strong>Im Lager</strong> = Bestand − ausgegeben. Rückgaben ins Lager erhöhen den Lagerstand automatisch, ausgeschiedene Stücke zählen nicht mehr zum Bestand.
    Ob ein Kleidungsstück als <strong>Menge</strong> oder als <strong>Einzelstücke mit Nummer</strong> geführt wird, legst du unter „Kleidungsstücke verwalten“ fest.
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (!$liste): ?>
        <p class="text-muted text-center my-4">Noch keine Kleidungsstücke angelegt.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>Kleidungsstück</th><th>Geführt als</th>
                <th class="text-center">Bestand</th><th class="text-center">ausgegeben</th><th class="text-center">im Lager</th>
                <th>Im Lager je Größe</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($liste as $k): $s = $k['summe']; ?>
            <tr>
                <td>
                    <span class="badge bg-secondary"><?php echo htmlspecialchars($k['kategorie_name'] ?? 'Ohne'); ?></span><br>
                    <a href="uniform_bestand.php?id=<?php echo $k['id']; ?>" class="fw-semibold text-decoration-none"><?php echo htmlspecialchars($k['name']); ?></a>
                </td>
                <td><?php echo $k['bestandsart'] === 'einzeln' ? '<span class="badge text-bg-light border"><i class="bi bi-hash"></i> Einzelstücke</span>' : '<span class="badge text-bg-light border"><i class="bi bi-stack"></i> Menge</span>'; ?></td>
                <?php if (!$k['erfasst']): ?>
                <td colspan="4" class="text-muted small"><i class="bi bi-info-circle"></i> Bestand noch nicht erfasst<?php echo $s['ausgegeben'] ? ' · ' . $s['ausgegeben'] . ' ausgegeben' : ''; ?></td>
                <?php else: ?>
                <td class="text-center fw-semibold"><?php echo $s['bestand']; ?></td>
                <td class="text-center"><?php echo $s['ausgegeben']; ?></td>
                <td class="text-center fw-semibold <?php echo $s['lager'] < 0 ? 'text-danger' : ''; ?>"><?php echo $s['lager']; ?></td>
                <td>
                    <?php foreach ($k['groessen'] as $z): if ($z['bestand'] === 0 && $z['ausgegeben'] === 0) continue;
                        $cls = $z['lager'] < 0 ? 'bg-danger' : ($z['lager'] === 0 ? 'bg-warning text-dark' : 'text-bg-light border'); ?>
                    <span class="badge <?php echo $cls; ?> me-1 mb-1" title="Bestand <?php echo $z['bestand']; ?>, ausgegeben <?php echo $z['ausgegeben']; ?>"><?php echo htmlspecialchars($g($z['groesse'])); ?>: <?php echo $z['lager']; ?></span>
                    <?php endforeach; ?>
                </td>
                <?php endif; ?>
                <td class="text-end"><a href="uniform_bestand.php?id=<?php echo $k['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-seam"></i> Bestand</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: /* ======================= EIN KLEIDUNGSSTÜCK ======================= */ ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h2 mb-0"><i class="bi bi-box-seam"></i> <?php echo htmlspecialchars($ks['name']); ?>
        <small class="text-muted fs-6"><?php echo htmlspecialchars($ks['kategorie_name'] ?? ''); ?> · <?php echo $einzeln ? 'Einzelstücke mit Nummer' . ($ks['kuerzel'] ? ' · Kürzel ' . htmlspecialchars($ks['kuerzel']) : '') : 'Menge'; ?></small>
    </h1>
    <a href="uniform_bestand.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Bestand</a>
</div>

<div class="row g-3 mb-3">
    <?php foreach ([['Bestand', $summe['bestand'], 'primary'], ['ausgegeben', $summe['ausgegeben'], 'info'], ['im Lager', $summe['lager'], $summe['lager'] < 0 ? 'danger' : 'success'], ['ausgeschieden', $summe['ausgeschieden'], 'secondary']] as [$l, $v, $c]): ?>
    <div class="col-6 col-md-3"><div class="card stat-card border-<?php echo $c; ?> h-100"><div class="card-body py-2">
        <div class="text-muted small text-uppercase"><?php echo $l; ?></div><div class="fs-3 fw-semibold"><?php echo $v; ?></div>
    </div></div></div>
    <?php endforeach; ?>
</div>

<?php if (!$erfasst): ?>
<div class="alert alert-info alert-permanent small"><i class="bi bi-info-circle"></i> <strong>Bestand noch nicht erfasst.</strong>
    <?php echo $einzeln ? 'Lege mit „Stücke anlegen“ alle vorhandenen Stücke mit Nummer an – auch die, die schon ausgegeben sind; beim Mitglied ordnest du dann die Nummer zu.'
                        : 'Am einfachsten: „Inventur“ öffnen und je Größe eintragen, wie viele Stücke im Lager liegen. Ausgegebene Stücke zählt Syncopa selbst dazu.'; ?></div>
<?php endif; ?>
<?php if ($ohneNummer): ?>
<div class="alert alert-warning alert-permanent small"><i class="bi bi-exclamation-triangle"></i>
    <?php echo $ohneNummer; ?> ausgegebene Stücke haben noch keine Nummer. Lege die Stücke hier an und ordne sie beim jeweiligen Mitglied (Uniform bearbeiten → Stift) zu.</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-rulers"></i> Nach Größe</h5>
        <?php if ($darfSchreiben): ?>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($einzeln): ?>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#stueckeModal"><i class="bi bi-plus-circle"></i> Stücke anlegen</button>
            <?php else: ?>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#zugangModal"><i class="bi bi-box-arrow-in-down"></i> Zugang</button>
            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#ausscheidenModal"><i class="bi bi-x-circle"></i> Ausscheiden</button>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#inventurModal"><i class="bi bi-clipboard-check"></i> Inventur</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 text-center">
            <thead><tr><th class="text-start ps-3">Größe</th><th>Bestand</th><th>ausgegeben</th><th>im Lager</th><th>ausgeschieden</th></tr></thead>
            <tbody>
            <?php foreach ($groessen as $z): ?>
            <tr>
                <td class="text-start ps-3 fw-semibold"><?php echo htmlspecialchars($g($z['groesse'])); ?></td>
                <td><?php echo $z['bestand']; ?></td>
                <td><?php echo $z['ausgegeben']; ?></td>
                <td class="fw-semibold <?php echo $z['lager'] < 0 ? 'text-danger' : ($z['lager'] === 0 ? 'text-warning' : 'text-success'); ?>"><?php echo $z['lager']; ?></td>
                <td class="text-muted"><?php echo $z['ausgeschieden'] ?: ''; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$groessen): ?><tr><td colspan="5" class="text-muted py-3">Keine Größen hinterlegt – Zugang mit Größe buchen oder Größen beim Kleidungsstück eintragen.</td></tr><?php endif; ?>
            </tbody>
            <tfoot class="table-light"><tr class="fw-semibold"><td class="text-start ps-3">Summe</td><td><?php echo $summe['bestand']; ?></td><td><?php echo $summe['ausgegeben']; ?></td><td><?php echo $summe['lager']; ?></td><td><?php echo $summe['ausgeschieden'] ?: ''; ?></td></tr></tfoot>
        </table>
        </div>
        <?php if ($summe['lager'] < 0 && $erfasst): ?>
        <div class="small text-danger px-3 py-2"><i class="bi bi-exclamation-circle"></i> Negativer Lagerstand: Es ist mehr ausgegeben als im Bestand erfasst. <?php echo $einzeln ? '' : 'Mit „Inventur“ den tatsächlich vorhandenen Lagerstand eintragen oder fehlende Zugänge buchen.'; ?></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($einzeln): ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-hash"></i> Stücke</h5>
        <div class="form-check form-switch small mb-0">
            <input class="form-check-input" type="checkbox" id="zeigeAusgeschiedene">
            <label class="form-check-label" for="zeigeAusgeschiedene">Ausgeschiedene zeigen</label>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (!$stuecke): ?>
        <p class="text-muted text-center my-4">Noch keine Stücke angelegt.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th class="ps-3">Nr.</th><th>Größe</th><th>Zustand</th><th>Status</th><th>Bemerkung</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($stuecke as $st): $aus = $st['status'] === 'ausgeschieden'; ?>
            <tr class="<?php echo $aus ? 'text-muted jb-ausgeschieden d-none' : ''; ?>">
                <td class="ps-3 fw-semibold font-monospace"><?php echo htmlspecialchars($nr($st['nummer'])); ?></td>
                <td><?php echo htmlspecialchars($g($st['groesse'])); ?></td>
                <td><span class="badge bg-<?php echo $zustandFarben[$st['zustand']] ?? 'secondary'; ?>"><?php echo htmlspecialchars($st['zustand']); ?></span></td>
                <td>
                    <?php if ($aus): ?>
                    <span class="badge bg-secondary">ausgeschieden</span> <small><?php echo $st['ausgeschieden_am'] ? date('d.m.Y', strtotime($st['ausgeschieden_am'])) : ''; ?> · <?php echo htmlspecialchars($st['ausgeschieden_grund'] ?? ''); ?></small>
                    <?php elseif ($st['status'] === 'ausgegeben' && $st['mitglied_id']): ?>
                    <i class="bi bi-person"></i> <a href="uniform_mitglied.php?id=<?php echo $st['mitglied_id']; ?>"><?php echo htmlspecialchars($st['vorname'] . ' ' . $st['nachname']); ?></a>
                    <?php else: ?>
                    <span class="badge bg-success">im Lager</span>
                    <?php endif; ?>
                </td>
                <td class="small text-muted"><?php echo htmlspecialchars($st['bemerkungen'] ?? ''); ?></td>
                <td class="text-end pe-3 text-nowrap">
                    <?php if ($darfSchreiben): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary btn-stueck" title="Bearbeiten"
                            data-id="<?php echo $st['id']; ?>" data-nummer="<?php echo htmlspecialchars($nr($st['nummer'])); ?>"
                            data-groesse="<?php echo htmlspecialchars($st['groesse']); ?>" data-zustand="<?php echo htmlspecialchars($st['zustand']); ?>"
                            data-bemerkungen="<?php echo htmlspecialchars($st['bemerkungen'] ?? ''); ?>"><i class="bi bi-pencil"></i></button>
                    <?php if ($st['status'] === 'lager'): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-stueck-aus" title="Ausscheiden" data-id="<?php echo $st['id']; ?>" data-nummer="<?php echo htmlspecialchars($nr($st['nummer'])); ?>"><i class="bi bi-x-circle"></i></button>
                    <?php elseif ($aus): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Stück wieder in den Bestand aufnehmen?')">
                        <input type="hidden" name="id" value="<?php echo $ksId; ?>"><input type="hidden" name="stueck_id" value="<?php echo $st['id']; ?>">
                        <button name="aktion" value="stueck_reaktivieren" class="btn btn-sm btn-outline-success" title="Wieder aufnehmen"><i class="bi bi-arrow-counterclockwise"></i></button>
                    </form>
                    <?php endif; ?>
                    <?php if ($darfLoeschen && $st['status'] === 'lager'): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Stück <?php echo htmlspecialchars(addslashes($nr($st['nummer']))); ?> löschen? Nur für irrtümlich angelegte Stücke.')">
                        <input type="hidden" name="id" value="<?php echo $ksId; ?>"><input type="hidden" name="stueck_id" value="<?php echo $st['id']; ?>">
                        <button name="aktion" value="stueck_loeschen" class="btn btn-sm btn-outline-secondary" title="Löschen (Fehleingabe)"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history"></i> Verlauf</h5></div>
    <div class="card-body p-0">
        <?php if (!$verlauf): ?>
        <p class="text-muted text-center my-4">Noch keine Bewegungen.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th class="ps-3">Datum</th><th>Vorgang</th><th>Größe</th><?php if ($einzeln): ?><th>Nr.</th><?php endif; ?><th class="text-center">Anzahl</th><th>Mitglied</th><th>Grund / Bemerkung</th><th>gebucht von</th></tr></thead>
            <tbody>
            <?php foreach ($verlauf as $b): [$an, $af, $ai] = $artNamen[$b['art']] ?? [$b['art'], 'secondary', 'bi-dot']; ?>
            <tr>
                <td class="ps-3 text-nowrap"><?php echo date('d.m.Y', strtotime($b['datum'])); ?></td>
                <td><span class="badge bg-<?php echo $af; ?>"><i class="bi <?php echo $ai; ?>"></i> <?php echo $an; ?></span></td>
                <td><?php echo htmlspecialchars($g($b['groesse'])); ?></td>
                <?php if ($einzeln): ?><td class="font-monospace"><?php echo htmlspecialchars($nr($b['nummer'] ?? '')); ?></td><?php endif; ?>
                <td class="text-center"><?php echo $b['art'] === 'korrektur' ? sprintf('%+d', $b['anzahl']) : (int)$b['anzahl']; ?></td>
                <td><?php echo $b['mitglied_id'] ? '<a href="uniform_mitglied.php?id=' . (int)$b['mitglied_id'] . '">' . htmlspecialchars(trim($b['vorname'] . ' ' . $b['nachname'])) . '</a>' : ''; ?></td>
                <td class="small"><?php echo htmlspecialchars(trim(($b['grund'] ?? '') . ($b['grund'] && $b['bemerkung'] ? ' – ' : '') . ($b['bemerkung'] ?? ''))); ?></td>
                <td class="small text-muted"><?php echo htmlspecialchars($b['benutzername'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($darfSchreiben):
    $groessenOptionen = function (bool $mitLager = false) use ($groessen, $g) {
        $h = '';
        foreach ($groessen as $z) $h .= '<option value="' . htmlspecialchars($z['groesse']) . '">' . htmlspecialchars($g($z['groesse'])) . ($mitLager ? ' (' . $z['lager'] . ' im Lager)' : '') . '</option>';
        return $h;
    };
    $heute = date('Y-m-d');
?>
<datalist id="groessenListe"><?php foreach ($groessen as $z) if ($z['groesse'] !== '') echo '<option value="' . htmlspecialchars($z['groesse']) . '">'; ?></datalist>

<?php if (!$einzeln): ?>
<div class="modal fade" id="zugangModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <input type="hidden" name="id" value="<?php echo $ksId; ?>"><input type="hidden" name="aktion" value="zugang">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-box-arrow-in-down"></i> Zugang buchen</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label">Größe</label><input class="form-control" name="groesse" list="groessenListe" placeholder="z. B. M"></div>
        <div class="col-6"><label class="form-label">Anzahl *</label><input type="number" class="form-control" name="anzahl" min="1" value="1" required></div>
        <div class="col-6"><label class="form-label">Grund</label><select class="form-select" name="grund"><?php foreach (Uniform::GRUENDE_ZUGANG as $gr) echo '<option>' . htmlspecialchars($gr) . '</option>'; ?></select></div>
        <div class="col-6"><label class="form-label">Datum</label><input type="date" class="form-control" name="datum" value="<?php echo $heute; ?>"></div>
        <div class="col-12"><label class="form-label">Bemerkung</label><input class="form-control" name="bemerkung" maxlength="500" placeholder="z. B. Lieferant, Rechnung"></div>
        <div class="col-12 small text-muted">Erstbestand: gesamte vorhandene Menge eintragen – auch Stücke, die schon an Mitglieder ausgegeben sind. Oder einfach „Inventur“ verwenden und nur zählen, was im Lager liegt.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Buchen</button></div>
</form></div></div></div>

<div class="modal fade" id="ausscheidenModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <input type="hidden" name="id" value="<?php echo $ksId; ?>"><input type="hidden" name="aktion" value="ausscheiden">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-x-circle"></i> Aus dem Lager ausscheiden</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label">Größe</label><select class="form-select" name="groesse"><?php echo $groessenOptionen(true); ?></select></div>
        <div class="col-6"><label class="form-label">Anzahl *</label><input type="number" class="form-control" name="anzahl" min="1" value="1" required></div>
        <div class="col-6"><label class="form-label">Grund *</label><select class="form-select" name="grund"><?php foreach (Uniform::GRUENDE_AUSSCHEIDEN as $gr) echo '<option>' . htmlspecialchars($gr) . '</option>'; ?></select></div>
        <div class="col-6"><label class="form-label">Datum</label><input type="date" class="form-control" name="datum" value="<?php echo $heute; ?>"></div>
        <div class="col-12"><label class="form-label">Bemerkung</label><input class="form-control" name="bemerkung" maxlength="500"></div>
        <div class="col-12 small text-muted">Stücke, die ein Mitglied noch hat, werden beim Mitglied zurückgenommen – dort lässt sich „ausscheiden“ direkt wählen.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-danger"><i class="bi bi-x-circle"></i> Ausscheiden</button></div>
</form></div></div></div>

<div class="modal fade" id="inventurModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <input type="hidden" name="id" value="<?php echo $ksId; ?>"><input type="hidden" name="aktion" value="inventur">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clipboard-check"></i> Inventur – Lager zählen</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted">Trage ein, wie viele Stücke je Größe tatsächlich <strong>im Lager</strong> liegen. Ausgegebene Stücke zählt Syncopa selbst dazu. Leere Felder bleiben unverändert.</p>
        <table class="table table-sm align-middle text-center">
            <thead><tr><th class="text-start">Größe</th><th>laut Syncopa</th><th style="width:120px">gezählt</th></tr></thead>
            <tbody>
            <?php foreach ($groessen as $k => $z): ?>
            <tr><td class="text-start fw-semibold"><?php echo htmlspecialchars($g($z['groesse'])); ?></td><td><?php echo $z['lager']; ?></td>
                <td><input type="number" min="0" class="form-control form-control-sm text-center" name="gezaehlt[<?php echo htmlspecialchars($z['groesse']); ?>]"></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <label class="form-label">Datum</label><input type="date" class="form-control" name="datum" value="<?php echo $heute; ?>">
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Übernehmen</button></div>
</form></div></div></div>
<?php else: ?>
<div class="modal fade" id="stueckeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <input type="hidden" name="id" value="<?php echo $ksId; ?>"><input type="hidden" name="aktion" value="stuecke_anlegen">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-circle"></i> Stücke anlegen</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-12">
            <div class="form-check"><input class="form-check-input" type="radio" name="vergabe" id="vg_auto" value="auto" checked>
                <label class="form-check-label" for="vg_auto">Nächste freie Nummern vergeben <span class="text-muted">(nächste: <strong class="font-monospace"><?php echo htmlspecialchars($naechste); ?></strong>)</span></label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="vergabe" id="vg_hand" value="hand">
                <label class="form-check-label" for="vg_hand">Bestimmte Nummern – für schon beschriftete Stücke</label></div>
        </div>
        <div class="col-6" id="vg_anzahl"><label class="form-label">Anzahl *</label><input type="number" class="form-control" name="anzahl" min="1" max="500" value="1"></div>
        <div class="col-12 d-none" id="vg_nummern"><label class="form-label">Nummer(n) *</label><input class="form-control" name="nummern" placeholder="z. B. 17 oder 1-20 oder 3, 7, 12">
            <small class="text-muted">Bereich „1-20“ oder Liste „3, 7, 12“ – wird 3-stellig gespeichert (7 → <?php echo htmlspecialchars(($ks['kuerzel'] ?? '') . '007'); ?>).</small></div>
        <div class="col-6"><label class="form-label">Größe</label><input class="form-control" name="groesse" list="groessenListe"></div>
        <div class="col-6"><label class="form-label">Zustand</label><select class="form-select" name="zustand"><option>sehr gut</option><option selected>gut</option><option>befriedigend</option><option>schlecht</option></select></div>
        <div class="col-6"><label class="form-label">Herkunft</label><select class="form-select" name="grund"><?php foreach (Uniform::GRUENDE_ZUGANG as $gr) echo '<option>' . htmlspecialchars($gr) . '</option>'; ?></select></div>
        <div class="col-6"><label class="form-label">Datum</label><input type="date" class="form-control" name="datum" value="<?php echo $heute; ?>"></div>
        <div class="col-12"><label class="form-label">Bemerkung</label><input class="form-control" name="bemerkung" maxlength="500"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Anlegen</button></div>
</form></div></div></div>

<div class="modal fade" id="stueckModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <input type="hidden" name="id" value="<?php echo $ksId; ?>"><input type="hidden" name="aktion" value="stueck_bearbeiten"><input type="hidden" name="stueck_id" id="st_id">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil"></i> Stück bearbeiten</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label">Nummer *</label><input class="form-control font-monospace" name="nummer" id="st_nummer" required maxlength="10"></div>
        <div class="col-6"><label class="form-label">Größe</label><input class="form-control" name="groesse" id="st_groesse" list="groessenListe"></div>
        <div class="col-6"><label class="form-label">Zustand</label><select class="form-select" name="zustand" id="st_zustand"><option>sehr gut</option><option>gut</option><option>befriedigend</option><option>schlecht</option></select></div>
        <div class="col-12"><label class="form-label">Bemerkung</label><input class="form-control" name="bemerkungen" id="st_bemerkungen" maxlength="500"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button></div>
</form></div></div></div>

<div class="modal fade" id="stueckAusModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST">
    <input type="hidden" name="id" value="<?php echo $ksId; ?>"><input type="hidden" name="aktion" value="stueck_ausscheiden"><input type="hidden" name="stueck_id" id="sta_id">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-x-circle"></i> Stück <span id="sta_nummer"></span> ausscheiden</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-3">
        <div class="col-6"><label class="form-label">Grund *</label><select class="form-select" name="grund"><?php foreach (Uniform::GRUENDE_AUSSCHEIDEN as $gr) if ($gr !== 'Fehleingabe') echo '<option>' . htmlspecialchars($gr) . '</option>'; ?></select></div>
        <div class="col-6"><label class="form-label">Datum</label><input type="date" class="form-control" name="datum" value="<?php echo $heute; ?>"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-danger"><i class="bi bi-x-circle"></i> Ausscheiden</button></div>
</form></div></div></div>
<?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    ['vg_auto', 'vg_hand'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', function () {
            var hand = document.getElementById('vg_hand').checked;
            document.getElementById('vg_anzahl').classList.toggle('d-none', hand);
            document.getElementById('vg_nummern').classList.toggle('d-none', !hand);
            document.querySelector('#vg_nummern input').required = hand;
        });
    });
    var sw = document.getElementById('zeigeAusgeschiedene');
    if (sw) sw.addEventListener('change', function () {
        document.querySelectorAll('.jb-ausgeschieden').forEach(function (tr) { tr.classList.toggle('d-none', !sw.checked); });
    });
    document.querySelectorAll('.btn-stueck').forEach(function (b) {
        b.addEventListener('click', function () {
            ['id', 'nummer', 'groesse', 'zustand', 'bemerkungen'].forEach(function (k) { document.getElementById('st_' + k).value = b.dataset[k]; });
            new bootstrap.Modal(document.getElementById('stueckModal')).show();
        });
    });
    document.querySelectorAll('.btn-stueck-aus').forEach(function (b) {
        b.addEventListener('click', function () {
            document.getElementById('sta_id').value = b.dataset.id;
            document.getElementById('sta_nummer').textContent = b.dataset.nummer;
            new bootstrap.Modal(document.getElementById('stueckAusModal')).show();
        });
    });
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
