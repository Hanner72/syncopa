<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'lesen');

$mitgliedId = $_GET['id'] ?? null;
if (!$mitgliedId) {
    header('Location: uniformen.php');
    exit;
}

$db = Database::getInstance();
$mitglied = $db->fetchOne("SELECT * FROM mitglieder WHERE id = ?", [$mitgliedId]);

if (!$mitglied) {
    Session::setFlashMessage('danger', 'Mitglied nicht gefunden');
    header('Location: uniformen.php');
    exit;
}

$uniformObj = new Uniform();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    Session::requirePermission('uniformen', 'schreiben');

    try {
        if ($_POST['action'] === 'zuweisen') {
            $data = [
                'groesse' => trim($_POST['groesse'] ?? '') ?: null,
                'stueck_id' => (int)($_POST['stueck_id'] ?? 0) ?: null,
                'anzahl' => (int)($_POST['anzahl'] ?? 1),
                'zustand' => $_POST['zustand'] ?? 'gut',
                'ausgabe_datum' => $_POST['ausgabe_datum'] ?: date('Y-m-d'),
                'bemerkungen' => trim($_POST['bemerkungen'] ?? '') ?: null
            ];
            $uniformObj->zuweisen($mitgliedId, (int)$_POST['kleidungsstueck_id'], $data);
            Session::setFlashMessage('success', 'Kleidungsstück ausgegeben.');

        } elseif ($_POST['action'] === 'zurueckgeben') {
            $ziel = ($_POST['ziel'] ?? 'lager') === 'ausscheiden' ? 'ausscheiden' : 'lager';
            $uniformObj->zurueckgeben((int)$_POST['zuweisung_id'], $ziel, [
                'grund' => $_POST['grund'] ?? '', 'zustand' => $_POST['zustand'] ?? null, 'anzahl' => $_POST['anzahl'] ?? null,
                'datum' => $_POST['datum'] ?? null, 'bemerkung' => $_POST['bemerkung'] ?? '',
            ]);
            Session::setFlashMessage('success', $ziel === 'lager' ? 'Zurückgenommen – das Stück ist wieder im Lager.' : 'Zurückgenommen und ausgeschieden.');

        } elseif ($_POST['action'] === 'entfernen') {
            Session::requirePermission('uniformen', 'loeschen');
            $uniformObj->zuweisungEntfernen($_POST['zuweisung_id']);
            Session::setFlashMessage('success', 'Eintrag gelöscht.');

        } elseif ($_POST['action'] === 'entfernen_nb') {
            // „Nicht benötigt“ zurücksetzen – nur solche Einträge dieses Mitglieds
            $db->execute("DELETE FROM uniform_zuweisungen WHERE id = ? AND mitglied_id = ? AND nicht_benoetigt = 1", [(int)$_POST['zuweisung_id'], $mitgliedId]);
            Session::setFlashMessage('success', '„Nicht benötigt“ zurückgesetzt.');

        } elseif ($_POST['action'] === 'aktualisieren') {
            $data = [
                'groesse' => trim($_POST['groesse'] ?? '') ?: null,
                'stueck_id' => (int)($_POST['stueck_id'] ?? 0) ?: null,
                'anzahl' => $_POST['anzahl'] ?? '',
                'zustand' => $_POST['zustand'] ?? 'gut',
                'bemerkungen' => trim($_POST['bemerkungen'] ?? '') ?: null
            ];
            $uniformObj->updateZuweisung($_POST['zuweisung_id'], $data);
            Session::setFlashMessage('success', 'Zuweisung aktualisiert.');
        }
    } catch (Exception $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }

    header('Location: uniform_mitglied.php?id=' . $mitgliedId);
    exit;
}

$alle = $uniformObj->getZuweisungenByMitglied($mitgliedId);
$zuweisungen = array_values(array_filter($alle, fn($z) => !$z['nicht_benoetigt']));
$nichtBenoetigt = array_values(array_filter($alle, fn($z) => $z['nicht_benoetigt']));
$verfuegbar = $uniformObj->getVerfuegbareKleidungsstuecke($mitgliedId);

// Lagerdaten für die Ausgabe und das Bearbeiten (Größen mit Lagerstand bzw. Einzelstücke im Lager)
$lagerDaten = [];
$ksIds = array_unique(array_merge(array_column($verfuegbar, 'id'), array_column($zuweisungen, 'kleidungsstueck_id')));
foreach ($ksIds as $kid) {
    $ksd = $uniformObj->getKleidungsstueckById($kid);
    $eintrag = ['art' => $ksd['bestandsart'] ?? 'menge', 'kuerzel' => $ksd['kuerzel'] ?? '', 'groessen' => []];
    foreach ($uniformObj->bestandNachGroesse((int)$kid) as $z) $eintrag['groessen'][] = ['g' => $z['groesse'], 'lager' => $z['lager']];
    if ($eintrag['art'] === 'einzeln') $eintrag['stuecke'] = $uniformObj->lagerStuecke((int)$kid);
    foreach ($zuweisungen as $zw) {
        if ((int)$zw['kleidungsstueck_id'] === (int)$kid && $eintrag['art'] !== 'einzeln') $eintrag['hat'] = ['groesse' => $zw['groesse'] ?? '', 'anzahl' => (int)$zw['anzahl']];
    }
    $lagerDaten[$kid] = $eintrag;
}
$darfSchreiben = Session::checkPermission('uniformen', 'schreiben');
$darfLoeschen = Session::checkPermission('uniformen', 'loeschen');
$zustandColors = ['sehr gut' => 'success', 'gut' => 'info', 'befriedigend' => 'warning', 'schlecht' => 'danger'];

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h1 class="h2">
        <i class="bi bi-person-badge"></i>
        Uniform: <?php echo htmlspecialchars($mitglied['vorname'] . ' ' . $mitglied['nachname']); ?>
    </h1>
    <div>
        <a href="mitglied_detail.php?id=<?php echo $mitgliedId; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-person"></i> Zum Mitglied
        </a>
        <a href="uniformen.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Zurück
        </a>
    </div>
</div>

<div class="row">
    <!-- Ausgegebene Kleidungsstücke -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-check-circle text-success"></i> Ausgegebene Kleidungsstücke</h5>
                <span class="badge bg-success"><?php echo array_sum(array_map(fn($z) => (int)$z['anzahl'], $zuweisungen)); ?> Teile</span>
            </div>
            <div class="card-body">
                <?php if (empty($zuweisungen)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mb-0">Noch keine Kleidungsstücke ausgegeben</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Kategorie / Kleidungsstück</th>
                            <th>Größe</th>
                            <th>Zustand</th>
                            <th>Ausgabe</th>
                            <th class="text-end">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zuweisungen as $z): ?>
                        <tr>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($z['kategorie_name'] ?? 'Ohne'); ?></span>
                                <br>
                                <strong><?php echo htmlspecialchars($z['kleidungsstueck_name']); ?></strong>
                                <?php if ($z['bestandsart'] === 'einzeln'): ?>
                                <?php if ($z['stueck_nummer'] !== null): ?>
                                <span class="badge text-bg-light border ms-1 font-monospace"><?php echo htmlspecialchars(Uniform::nummerAnzeige($z['kuerzel'], $z['stueck_nummer'])); ?></span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark ms-1" title="Über den Stift ein Stück zuordnen">ohne Nummer</span>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($z['bemerkungen']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($z['bemerkungen']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><strong><?php echo htmlspecialchars($z['groesse'] ?? '-'); ?></strong>
                                <?php if ((int)$z['anzahl'] > 1): ?><span class="badge bg-primary ms-1"><?php echo (int)$z['anzahl']; ?>×</span><?php endif; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $zustandColors[$z['zustand']] ?? 'secondary'; ?>"><?php echo ucfirst($z['zustand'] ?? 'gut'); ?></span>
                            </td>
                            <td>
                                <small><?php echo $z['ausgabe_datum'] ? date('d.m.Y', strtotime($z['ausgabe_datum'])) : '-'; ?></small>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($darfSchreiben): ?>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-edit-zuweisung"
                                            data-id="<?php echo $z['id']; ?>"
                                            data-ks="<?php echo $z['kleidungsstueck_id']; ?>"
                                            data-art="<?php echo htmlspecialchars($z['bestandsart']); ?>"
                                            data-stueck="<?php echo (int)$z['stueck_id']; ?>"
                                            data-stueck-nummer="<?php echo htmlspecialchars(Uniform::nummerAnzeige($z['kuerzel'], $z['stueck_nummer'])); ?>"
                                            data-groesse="<?php echo htmlspecialchars($z['groesse'] ?? ''); ?>"
                                            data-anzahl="<?php echo (int)$z['anzahl']; ?>"
                                            data-zustand="<?php echo htmlspecialchars($z['zustand'] ?? 'gut'); ?>"
                                            data-bemerkungen="<?php echo htmlspecialchars($z['bemerkungen'] ?? ''); ?>"
                                            data-name="<?php echo htmlspecialchars($z['kleidungsstueck_name']); ?>"
                                            title="Bearbeiten">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-rueckgabe"
                                            data-id="<?php echo $z['id']; ?>"
                                            data-anzahl="<?php echo $z['stueck_id'] ? 1 : (int)$z['anzahl']; ?>"
                                            data-zustand="<?php echo htmlspecialchars($z['zustand'] ?? 'gut'); ?>"
                                            data-name="<?php echo htmlspecialchars($z['kleidungsstueck_name'] . ($z['stueck_nummer'] !== null ? ' ' . Uniform::nummerAnzeige($z['kuerzel'], $z['stueck_nummer']) : '') . ($z['groesse'] ? ' (' . $z['groesse'] . ')' : '')); ?>"
                                            title="Zurücknehmen">
                                        <i class="bi bi-arrow-return-left"></i>
                                    </button>
                                    <?php if ($darfLoeschen): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Nur für Fehleingaben: Eintrag ohne Rückgabe löschen?');">
                                        <input type="hidden" name="action" value="entfernen">
                                        <input type="hidden" name="zuweisung_id" value="<?php echo $z['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Fehleingabe löschen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
                <?php if ($nichtBenoetigt): ?>
                <div class="small text-muted mt-2">
                    <i class="bi bi-slash-circle"></i> Nicht benötigt:
                    <?php foreach ($nichtBenoetigt as $nb): ?>
                    <span class="badge text-bg-light border me-1"><?php echo htmlspecialchars($nb['kleidungsstueck_name']); ?>
                        <?php if ($darfSchreiben): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('„Nicht benötigt“ zurücksetzen?');">
                            <input type="hidden" name="action" value="entfernen_nb"><input type="hidden" name="zuweisung_id" value="<?php echo $nb['id']; ?>">
                            <button class="btn btn-link btn-sm p-0 ms-1 text-muted" title="Zurücksetzen" style="font-size:11px;line-height:1"><i class="bi bi-x"></i></button>
                        </form>
                        <?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Seitenleiste: Neues Kleidungsstück ausgeben -->
    <div class="col-lg-4">
        <?php if ($darfSchreiben): ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Kleidungsstück ausgeben</h5>
            </div>
            <div class="card-body">
                <?php if (empty($verfuegbar)): ?>
                <p class="text-muted text-center mb-0">
                    Alle Kleidungsstücke bereits ausgegeben oder keine definiert.
                    <br><br>
                    <a href="uniform_kleidungsstuecke.php" class="btn btn-sm btn-outline-primary">
                        Kleidungsstücke verwalten
                    </a>
                </p>
                <?php else: ?>
                <form method="POST" id="ausgabeForm">
                    <input type="hidden" name="action" value="zuweisen">

                    <div class="mb-3">
                        <label for="kleidungsstueck_id" class="form-label">Kleidungsstück <span class="text-danger">*</span></label>
                        <select class="form-select" name="kleidungsstueck_id" id="kleidungsstueck_id" required>
                            <option value="">-- Auswählen --</option>
                            <?php
                            $aktuelleKategorie = '';
                            foreach ($verfuegbar as $k):
                                if ($k['kategorie_name'] !== $aktuelleKategorie):
                                    if ($aktuelleKategorie !== '') echo '</optgroup>';
                                    $aktuelleKategorie = $k['kategorie_name'];
                            ?>
                            <optgroup label="<?php echo htmlspecialchars($aktuelleKategorie ?? 'Ohne Kategorie'); ?>">
                            <?php endif; ?>
                                <option value="<?php echo $k['id']; ?>">
                                    <?php echo htmlspecialchars($k['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($aktuelleKategorie !== '') echo '</optgroup>'; ?>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="stueckWahl">
                        <label for="stueck_id" class="form-label">Stück (Nummer) <span class="text-danger">*</span></label>
                        <select class="form-select" name="stueck_id" id="stueck_id"></select>
                        <small class="text-muted" id="stueckHinweis"></small>
                    </div>

                    <div class="mb-3" id="groesseWahl">
                        <label for="groesse" class="form-label">Größe</label>
                        <select class="form-select d-none" id="groesseSelect"></select>
                        <input type="text" class="form-control" name="groesse" id="groesse" placeholder="z.B. M, L, 50">
                        <small class="text-warning d-none" id="lagerWarnung"><i class="bi bi-exclamation-triangle"></i> In dieser Größe ist laut Bestand nicht genug im Lager.</small>
                    </div>

                    <div class="mb-3" id="anzahlWahl">
                        <label for="anzahl" class="form-label">Anzahl</label>
                        <input type="number" class="form-control" name="anzahl" id="anzahl" min="1" max="99" value="1">
                        <small class="text-info d-none" id="hatSchon"></small>
                    </div>

                    <div class="mb-3" id="zustandWahl">
                        <label for="zustand" class="form-label">Zustand</label>
                        <select class="form-select" name="zustand" id="zustand">
                            <option value="sehr gut">Sehr gut</option>
                            <option value="gut" selected>Gut</option>
                            <option value="befriedigend">Befriedigend</option>
                            <option value="schlecht">Schlecht</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="ausgabe_datum" class="form-label">Ausgabedatum</label>
                        <input type="date" class="form-control" name="ausgabe_datum" id="ausgabe_datum"
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="bemerkungen" class="form-label">Bemerkungen</label>
                        <textarea class="form-control" name="bemerkungen" id="bemerkungen" rows="2"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle"></i> Ausgeben
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Mitglied-Info -->
        <div class="card mt-3 bg-light">
            <div class="card-body">
                <h6><i class="bi bi-person"></i> Mitglied</h6>
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td>Nr.:</td>
                        <td><strong><?php echo htmlspecialchars($mitglied['mitgliedsnummer']); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Mitgliedsart:</td>
                        <td><?php echo Mitgliedsart::badge($mitglied['mitgliedsart_code']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Zuweisung bearbeiten -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="aktualisieren">
                <input type="hidden" name="zuweisung_id" id="edit_zuweisung_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> <span id="edit_name"></span> bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 d-none" id="edit_stueck_wahl">
                        <label for="edit_stueck" class="form-label">Stück (Nummer)</label>
                        <select class="form-select" name="stueck_id" id="edit_stueck"></select>
                        <small class="text-muted">Ein anderes Stück wählen: das bisherige geht ins Lager zurück. Die Größe kommt vom Stück.</small>
                    </div>
                    <div class="row g-3 mb-3" id="edit_groesse_wahl">
                        <div class="col-8">
                            <label for="edit_groesse" class="form-label">Größe</label>
                            <input type="text" class="form-control" name="groesse" id="edit_groesse">
                        </div>
                        <div class="col-4">
                            <label for="edit_anzahl" class="form-label">Anzahl</label>
                            <input type="number" class="form-control" name="anzahl" id="edit_anzahl" min="1" max="99">
                        </div>
                        <div class="col-12"><small class="text-muted">Mehr Stück = weitere Ausgabe, weniger = Rückgabe ins Lager. Zum Ausscheiden „Zurücknehmen“ verwenden.</small></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_zustand" class="form-label">Zustand</label>
                        <select class="form-select" name="zustand" id="edit_zustand">
                            <option value="sehr gut">Sehr gut</option>
                            <option value="gut">Gut</option>
                            <option value="befriedigend">Befriedigend</option>
                            <option value="schlecht">Schlecht</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_bemerkungen" class="form-label">Bemerkungen</label>
                        <textarea class="form-control" name="bemerkungen" id="edit_bemerkungen" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Zurücknehmen -->
<div class="modal fade" id="rueckgabeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="zurueckgeben">
                <input type="hidden" name="zuweisung_id" id="rg_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-return-left"></i> <span id="rg_name"></span> zurücknehmen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="ziel" id="rg_lager" value="lager" checked>
                            <label class="form-check-label" for="rg_lager"><strong>Ins Lager</strong> – kann wieder ausgegeben werden</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ziel" id="rg_aus" value="ausscheiden">
                            <label class="form-check-label" for="rg_aus"><strong>Ausscheiden</strong> – zählt nicht mehr zum Bestand</label>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6 d-none" id="rg_grund_wahl">
                            <label class="form-label">Grund *</label>
                            <select class="form-select" name="grund" id="rg_grund">
                                <?php foreach (Uniform::GRUENDE_AUSSCHEIDEN as $gr) if ($gr !== 'Fehleingabe') echo '<option>' . htmlspecialchars($gr) . '</option>'; ?>
                            </select>
                        </div>
                        <div class="col-6" id="rg_zustand_wahl">
                            <label class="form-label">Zustand</label>
                            <select class="form-select" name="zustand" id="rg_zustand">
                                <option value="sehr gut">Sehr gut</option>
                                <option value="gut">Gut</option>
                                <option value="befriedigend">Befriedigend</option>
                                <option value="schlecht">Schlecht</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Datum</label>
                            <input type="date" class="form-control" name="datum" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-6 d-none" id="rg_anzahl_wahl">
                            <label class="form-label">Anzahl</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="anzahl" id="rg_anzahl" min="1">
                                <span class="input-group-text" id="rg_anzahl_von"></span>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Bemerkung</label>
                            <input type="text" class="form-control" name="bemerkung" maxlength="500">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success" id="rg_knopf"><i class="bi bi-check-lg"></i> Zurücknehmen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var LAGER = <?php echo json_encode($lagerDaten, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
    function stueckText(s, k) { return (k || '') + s.nummer + (s.groesse ? ' · ' + s.groesse : '') + ' · ' + s.zustand; }

    // ---------- Ausgabe ----------
    var ks = document.getElementById('kleidungsstueck_id');
    if (ks) {
        var stueckWahl = document.getElementById('stueckWahl'), stueck = document.getElementById('stueck_id'),
            groesseWahl = document.getElementById('groesseWahl'), zustandWahl = document.getElementById('zustandWahl'),
            gSel = document.getElementById('groesseSelect'), gIn = document.getElementById('groesse'),
            warnung = document.getElementById('lagerWarnung'), hinweis = document.getElementById('stueckHinweis');

        function lagerWarnen() {
            var opt = gSel.options[gSel.selectedIndex];
            var n = parseInt(document.getElementById('anzahl').value, 10) || 1;
            warnung.classList.toggle('d-none', !(opt && opt.value !== '' && parseInt(opt.dataset.lager, 10) < n));
        }
        ks.addEventListener('change', function () {
            var d = LAGER[ks.value];
            var einzeln = d && d.art === 'einzeln';
            stueckWahl.classList.toggle('d-none', !einzeln);
            groesseWahl.classList.toggle('d-none', einzeln);
            zustandWahl.classList.toggle('d-none', einzeln);
            document.getElementById('anzahlWahl').classList.toggle('d-none', einzeln);
            document.getElementById('anzahl').value = 1;
            var hs = document.getElementById('hatSchon');
            hs.classList.toggle('d-none', !(d && d.hat));
            if (d && d.hat) hs.innerHTML = '<i class="bi bi-info-circle"></i> Hat schon ' + d.hat.anzahl + '× in Größe ' + esc(d.hat.groesse || 'o. A.') + ' – weitere Stücke werden dazugezählt (gleiche Größe).';
            stueck.required = false;
            warnung.classList.add('d-none');
            if (einzeln) {
                var h = '';
                (d.stuecke || []).forEach(function (s) { h += '<option value="' + s.id + '">' + esc(stueckText(s, d.kuerzel)) + '</option>'; });
                h += '<option value="">– ohne Nummer ausgeben –</option>';
                stueck.innerHTML = h;
                hinweis.textContent = (d.stuecke || []).length ? (d.stuecke.length + ' Stück im Lager') : 'Kein Stück im Lager – zuerst unter „Bestand“ anlegen.';
                return;
            }
            var gr = d ? d.groessen.filter(function (z) { return z.g !== ''; }) : [];
            if (gr.length) {
                var h2 = '<option value="">–</option>';
                gr.forEach(function (z) { h2 += '<option value="' + esc(z.g) + '" data-lager="' + z.lager + '">' + esc(z.g) + ' (' + z.lager + ' im Lager)</option>'; });
                gSel.innerHTML = h2;
                gSel.classList.remove('d-none'); gIn.classList.add('d-none'); gIn.value = '';
                if (d.hat) { gSel.value = d.hat.groesse; gIn.value = gSel.value; lagerWarnen(); }
            } else {
                gSel.classList.add('d-none'); gIn.classList.remove('d-none');
            }
        });
        gSel.addEventListener('change', function () { gIn.value = gSel.value; lagerWarnen(); });
        document.getElementById('anzahl').addEventListener('input', lagerWarnen);
    }

    // ---------- Bearbeiten ----------
    document.querySelectorAll('.btn-edit-zuweisung').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var b = this.dataset, einzeln = b.art === 'einzeln';
            document.getElementById('edit_zuweisung_id').value = b.id;
            document.getElementById('edit_name').textContent = b.name;
            document.getElementById('edit_groesse').value = b.groesse;
            document.getElementById('edit_anzahl').value = b.anzahl;
            document.getElementById('edit_zustand').value = b.zustand;
            document.getElementById('edit_bemerkungen').value = b.bemerkungen;
            document.getElementById('edit_stueck_wahl').classList.toggle('d-none', !einzeln);
            document.getElementById('edit_groesse_wahl').classList.toggle('d-none', einzeln);
            if (einzeln) {
                var sel = document.getElementById('edit_stueck'), h = '';
                h += b.stueck !== '0' ? '<option value="' + b.stueck + '" selected>' + esc(b.stueckNummer) + ' (bisher)</option>' : '<option value="">– ohne Nummer –</option>';
                ((LAGER[b.ks] || {}).stuecke || []).forEach(function (s) { h += '<option value="' + s.id + '">' + esc(stueckText(s, (LAGER[b.ks] || {}).kuerzel)) + '</option>'; });
                sel.innerHTML = h;
            }
            new bootstrap.Modal(document.getElementById('editModal')).show();
        });
    });

    // ---------- Zurücknehmen ----------
    function zielUmschalten() {
        var aus = document.getElementById('rg_aus').checked;
        document.getElementById('rg_grund_wahl').classList.toggle('d-none', !aus);
        var k = document.getElementById('rg_knopf');
        k.className = 'btn ' + (aus ? 'btn-danger' : 'btn-success');
        k.innerHTML = aus ? '<i class="bi bi-x-circle"></i> Zurücknehmen und ausscheiden' : '<i class="bi bi-check-lg"></i> Ins Lager zurücknehmen';
    }
    ['rg_lager', 'rg_aus'].forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener('change', zielUmschalten); });
    document.querySelectorAll('.btn-rueckgabe').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('rg_id').value = this.dataset.id;
            document.getElementById('rg_name').textContent = this.dataset.name;
            document.getElementById('rg_zustand').value = this.dataset.zustand;
            var hat = parseInt(this.dataset.anzahl, 10) || 1;
            document.getElementById('rg_anzahl_wahl').classList.toggle('d-none', hat <= 1);
            var rga = document.getElementById('rg_anzahl');
            rga.max = hat; rga.value = hat;
            document.getElementById('rg_anzahl_von').textContent = 'von ' + hat;
            document.getElementById('rg_lager').checked = true;
            zielUmschalten();
            new bootstrap.Modal(document.getElementById('rueckgabeModal')).show();
        });
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
