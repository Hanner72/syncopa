<?php
// finanz_konten.php – Konten (beliebig viele) mit Anfangsbestand und Kategorien des Kassabuchs verwalten
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'schreiben');

$jahr = (int)($_GET['jahr'] ?? $_POST['jahr'] ?? date('Y'));
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['aktion'] ?? '') {
            case 'konto':
                $kid = Kassabuch::kontoSpeichern($_POST, (int)($_POST['id'] ?? 0) ?: null);
                if (($_POST['anfang'] ?? '') !== '') Kassabuch::anfangsbestandSetzen($kid, $jahr, (float)str_replace(',', '.', $_POST['anfang']));
                Session::setFlashMessage('success', 'Konto gespeichert.');
                break;
            case 'anfang':
                foreach ((array)($_POST['anfang'] ?? []) as $kid => $wert) {
                    $wert = trim((string)$wert);
                    Kassabuch::anfangsbestandSetzen((int)$kid, $jahr, $wert === '' ? null : (float)str_replace(',', '.', $wert));
                }
                Session::setFlashMessage('success', "Anfangsbestände $jahr gespeichert.");
                break;
            case 'konto_loeschen':
                Kassabuch::kontoLoeschen((int)$_POST['id']);
                Session::setFlashMessage('success', 'Konto gelöscht.');
                break;
            case 'kategorie':
                Kassabuch::kategorieSpeichern($_POST, (int)($_POST['id'] ?? 0) ?: null);
                Session::setFlashMessage('success', 'Kategorie gespeichert.');
                break;
            case 'kategorie_loeschen':
                Kassabuch::kategorieLoeschen((int)$_POST['id']);
                Session::setFlashMessage('success', 'Kategorie gelöscht.');
                break;
        }
    } catch (RuntimeException $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: finanz_konten.php?jahr=' . $jahr . (str_starts_with($_POST['aktion'] ?? '', 'kategorie') ? '#kategorien' : ''));
    exit;
}

$konten = Kassabuch::kontenUebersicht($jahr);
$alleKonten = Kassabuch::konten(false);
$kategorien = $db->fetchAll("SELECT c.*, (SELECT COUNT(*) FROM finanzen f WHERE f.kategorie_id = c.id) AS anzahl FROM finanz_kategorien c ORDER BY c.aktiv DESC, c.sortierung, c.name");
$formationen = $db->fetchAll("SELECT id, name FROM formationen WHERE aktiv = 1 ORDER BY name");
$gesperrt = Kassabuch::istGesperrt($jahr);
$typLabel = ['einnahme' => ['Einnahme', 'success'], 'ausgabe' => ['Ausgabe', 'danger'], 'beide' => ['beides', 'secondary']];
$artSymbol = ['kassa' => 'bi-cash-stack', 'bank' => 'bi-bank', 'sparbuch' => 'bi-piggy-bank', 'sonstig' => 'bi-wallet2'];
$anzahlBuchungen = [];
foreach ($db->fetchAll("SELECT k.id, (SELECT COUNT(*) FROM finanzen f WHERE f.konto_id = k.id OR f.gegenkonto_id = k.id) n FROM finanz_konten k") as $r) $anzahlBuchungen[$r['id']] = (int)$r['n'];

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-gear"></i> Konten &amp; Kategorien</h1>
    <a href="finanzen.php?jahr=<?php echo $jahr; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Kassabuch</a>
</div>

<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0"><i class="bi bi-bank"></i> Konten</h5>
            <div class="small text-muted"><i class="bi bi-grip-vertical"></i> Zeilen ziehen zum Umsortieren – wird sofort gespeichert. <span id="konten-sort-status"></span></div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" class="d-flex align-items-center gap-2">
                <label for="jahr" class="small text-muted text-nowrap">Anfangsbestand für</label>
                <select id="jahr" name="jahr" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php for ($j = (int)date('Y') + 1; $j >= (int)date('Y') - 6; $j--): ?><option <?php echo $j === $jahr ? 'selected' : ''; ?>><?php echo $j; ?></option><?php endfor; ?>
                </select>
            </form>
            <button class="btn btn-sm btn-success" data-konto='{}'><i class="bi bi-plus-lg"></i> Konto</button>
        </div>
    </div>
    <form method="POST">
        <input type="hidden" name="aktion" value="anfang">
        <input type="hidden" name="jahr" value="<?php echo $jahr; ?>">
        <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light"><tr><th style="width:2rem"></th><th>Konto</th><th>Art</th><th>IBAN</th><th style="width:220px">Anfangsbestand 1.1.<?php echo $jahr; ?></th><th class="text-end">Stand Ende <?php echo $jahr; ?></th><th></th></tr></thead>
            <tbody id="konten-tbody">
            <?php foreach ($alleKonten as $k): $u = current(array_filter($konten, fn($x) => $x['id'] == $k['id'])) ?: null; $automatisch = $u ? $u['anfang'] : Kassabuch::anfangsbestand((int)$k['id'], $jahr); ?>
            <tr class="<?php echo $k['aktiv'] ? '' : 'text-muted'; ?>" data-id="<?php echo (int)$k['id']; ?>">
                <td class="drag-handle text-muted" title="Ziehen zum Umsortieren"><i class="bi bi-grip-vertical"></i></td>
                <td><i class="bi <?php echo $artSymbol[$k['art']] ?? 'bi-wallet2'; ?>"></i> <strong><?php echo htmlspecialchars($k['name']); ?></strong>
                    <?php if (!$k['aktiv']): ?><span class="badge bg-secondary">inaktiv</span><?php endif; ?>
                    <?php if ($k['formation_name']): ?><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($k['formation_name']); ?></span><?php endif; ?></td>
                <td class="small"><?php echo Kassabuch::ARTEN[$k['art']]; ?></td>
                <td class="small"><?php echo htmlspecialchars($k['iban'] ?? ''); ?></td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.01" class="form-control" name="anfang[<?php echo $k['id']; ?>]" <?php echo $gesperrt ? 'disabled' : ''; ?>
                               value="<?php $e = Kassabuch::anfangsbestandEingetragen((int)$k['id'], $jahr); echo $e !== null ? number_format($e, 2, '.', '') : ''; ?>"
                               placeholder="<?php echo number_format($automatisch, 2, ',', '.'); ?> (automatisch)">
                        <span class="input-group-text">€</span>
                    </div>
                </td>
                <td class="text-end fw-semibold" style="font-variant-numeric:tabular-nums"><?php echo Kassabuch::euro($u ? $u['ende'] : Kassabuch::endbestand((int)$k['id'], $jahr)); ?></td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" title="Bearbeiten" data-konto='<?php echo htmlspecialchars(json_encode($k), ENT_QUOTES); ?>'><i class="bi bi-pencil"></i></button>
                    <?php if (empty($anzahlBuchungen[$k['id']])): ?>
                    <button type="submit" form="loeschen-konto-<?php echo $k['id']; ?>" class="btn btn-sm btn-outline-danger" title="Löschen"><i class="bi bi-trash"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small text-muted"><i class="bi bi-info-circle"></i> Den Anfangsbestand nur im ersten Jahr eintragen (Kassastand bzw. Kontoauszug am 1.1.). Danach rechnet Syncopa ihn aus dem Vorjahr weiter. Leer = automatisch.</div>
            <?php if (!$gesperrt): ?><button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Anfangsbestände speichern</button><?php endif; ?>
        </div>
    </form>
    <?php foreach ($alleKonten as $k): ?>
    <form method="POST" id="loeschen-konto-<?php echo $k['id']; ?>" onsubmit="return confirm('Konto „<?php echo htmlspecialchars(addslashes($k['name'])); ?>“ löschen?')">
        <input type="hidden" name="aktion" value="konto_loeschen"><input type="hidden" name="id" value="<?php echo $k['id']; ?>"><input type="hidden" name="jahr" value="<?php echo $jahr; ?>">
    </form>
    <?php endforeach; ?>
</div>

<div class="card" id="kategorien">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0"><i class="bi bi-tags"></i> Kategorien</h5>
            <div class="small text-muted"><i class="bi bi-grip-vertical"></i> Zeilen ziehen zum Umsortieren – wird sofort gespeichert. <span id="kategorien-sort-status"></span></div>
        </div>
        <button class="btn btn-sm btn-success" data-kategorie='{}'><i class="bi bi-plus-lg"></i> Kategorie</button>
    </div>
    <div class="table-responsive">
    <table class="table mb-0 align-middle">
        <thead class="table-light"><tr><th style="width:2rem"></th><th>Name</th><th>Für</th><th>Im BMV-Jahresbericht</th><th class="text-end">Buchungen</th><th></th></tr></thead>
        <tbody id="kategorien-tbody">
        <?php foreach ($kategorien as $c): ?>
        <tr class="<?php echo $c['aktiv'] ? '' : 'text-muted'; ?>" data-id="<?php echo (int)$c['id']; ?>">
            <td class="drag-handle text-muted" title="Ziehen zum Umsortieren"><i class="bi bi-grip-vertical"></i></td>
            <td><?php echo htmlspecialchars($c['name']); ?><?php if (!$c['aktiv']): ?> <span class="badge bg-secondary">inaktiv</span><?php endif; ?></td>
            <td><span class="badge bg-<?php echo $typLabel[$c['typ']][1]; ?>"><?php echo $typLabel[$c['typ']][0]; ?></span></td>
            <td class="small"><?php
                if ($c['bmv_posten'] === 'keine') echo '<span class="text-muted">zählt nicht</span>';
                elseif (isset(BmvJahresbericht::POSTEN[$c['bmv_posten'] ?? ''])) echo htmlspecialchars(BmvJahresbericht::POSTEN[$c['bmv_posten']]);
                elseif ($c['typ'] !== 'einnahme') echo '<span class="text-muted">übrige Ausgaben</span>';
                else echo '<span class="text-muted">–</span>';
            ?></td>
            <td class="text-end"><?php echo (int)$c['anzahl']; ?></td>
            <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-outline-primary" title="Bearbeiten" data-kategorie='<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES); ?>'><i class="bi bi-pencil"></i></button>
                <?php if (!$c['anzahl']): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Kategorie löschen?')">
                    <input type="hidden" name="aktion" value="kategorie_loeschen"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="jahr" value="<?php echo $jahr; ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Löschen"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Dialog Konto -->
<div class="modal fade" id="kontoModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" class="modal-content">
        <input type="hidden" name="aktion" value="konto"><input type="hidden" name="id"><input type="hidden" name="jahr" value="<?php echo $jahr; ?>">
        <div class="modal-header"><h5 class="modal-title">Konto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12"><label class="form-label" for="k-name">Name *</label><input class="form-control" id="k-name" name="name" required maxlength="100" placeholder="z. B. Handkassa, Girokonto Raiffeisen, Jugendkassa"></div>
            <div class="col-md-6"><label class="form-label" for="k-art">Art</label>
                <select class="form-select" id="k-art" name="art"><?php foreach (Kassabuch::ARTEN as $av => $al): ?><option value="<?php echo $av; ?>"><?php echo $al; ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label" for="k-sortierung">Reihenfolge</label><input type="number" class="form-control" id="k-sortierung" name="sortierung" value="100"></div>
            <div class="col-12"><label class="form-label" for="k-iban">IBAN</label><input class="form-control" id="k-iban" name="iban" maxlength="40"></div>
            <div class="col-md-6"><label class="form-label" for="k-formation">Formation</label>
                <select class="form-select" id="k-formation" name="formation_id"><option value="">Gesamter Verein</option><?php foreach ($formationen as $f): ?><option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6" id="k-anfang-feld"><label class="form-label" for="k-anfang">Anfangsbestand 1.1.<?php echo $jahr; ?></label><input type="number" step="0.01" class="form-control" id="k-anfang" name="anfang" placeholder="0,00"></div>
            <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="aktiv" value="1" id="k-aktiv" checked><label class="form-check-label" for="k-aktiv">aktiv</label></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-primary">Speichern</button></div>
    </form></div>
</div>

<!-- Dialog Kategorie -->
<div class="modal fade" id="kategorieModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" class="modal-content">
        <input type="hidden" name="aktion" value="kategorie"><input type="hidden" name="id"><input type="hidden" name="jahr" value="<?php echo $jahr; ?>">
        <div class="modal-header"><h5 class="modal-title">Kategorie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label" for="c-name">Name *</label><input class="form-control" id="c-name" name="name" required maxlength="100"></div>
            <div class="mb-3"><label class="form-label" for="c-typ">Für</label>
                <select class="form-select" id="c-typ" name="typ"><option value="einnahme">Einnahmen</option><option value="ausgabe">Ausgaben</option><option value="beide">beides</option></select></div>
            <div class="mb-3"><label class="form-label" for="c-bmv">Zeile im BMV-Jahresbericht</label>
                <select class="form-select" id="c-bmv" name="bmv_posten">
                    <option value="">automatisch (Ausgaben: „alle übrigen Ausgaben“)</option>
                    <?php foreach (BmvJahresbericht::POSTEN as $pv => $pl): if ($pv === 'aus_uebrige') continue; ?><option value="<?php echo $pv; ?>"><?php echo htmlspecialchars($pl); ?></option><?php endforeach; ?>
                    <option value="keine">zählt nicht für den Jahresbericht</option>
                </select>
                <div class="form-text">Subventionen zählen nur bei Einnahmen, die übrigen Zeilen nur bei Ausgaben.</div></div>
            <div class="mb-3"><label class="form-label" for="c-sortierung">Reihenfolge</label><input type="number" class="form-control" id="c-sortierung" name="sortierung" value="100"></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="aktiv" value="1" id="c-aktiv" checked><label class="form-check-label" for="c-aktiv">aktiv</label></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-primary">Speichern</button></div>
    </form></div>
</div>

<?php include 'includes/footer.php'; ?>
<style>
#konten-tbody .drag-handle, #kategorien-tbody .drag-handle { cursor: grab; user-select: none; touch-action: none; }
#konten-tbody .sortable-ghost, #kategorien-tbody .sortable-ghost { background: rgba(var(--bs-primary-rgb), .08); }
</style>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
// Konten und Kategorien per Drag & Drop umsortieren
function sortierbar(art) {
    var tbody = document.getElementById(art + '-tbody');
    var status = document.getElementById(art + '-sort-status');
    if (!tbody || typeof Sortable === 'undefined') return;
    Sortable.create(tbody, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function (ev) {
            if (ev.oldIndex === ev.newIndex) return;
            var ids = Array.from(tbody.querySelectorAll('tr[data-id]')).map(function (tr) { return parseInt(tr.dataset.id, 10); });
            status.innerHTML = '<i class="bi bi-arrow-repeat"></i> Speichert …';
            fetch('api/finanz_sortierung.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ art: art, ids: ids }) })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    status.innerHTML = d.success
                        ? '<span class="text-success"><i class="bi bi-check-circle"></i> Gespeichert</span>'
                        : '<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ' + (d.error || 'Fehler') + '</span>';
                    if (d.success) setTimeout(function () { status.innerHTML = ''; }, 2000);
                })
                .catch(function () { status.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle"></i> Verbindungsfehler</span>'; });
        }
    });
}
sortierbar('konten');
sortierbar('kategorien');

function dialogFuellen(modalId, daten, felder) {
    var m = document.getElementById(modalId), f = m.querySelector('form');
    f.reset();
    f.elements.namedItem('id').value = daten.id || '';
    felder.forEach(function (n) { var el = f.elements.namedItem(n); if (el && daten[n] !== undefined && daten[n] !== null) el.value = daten[n]; });
    var aktiv = f.elements.namedItem('aktiv'); aktiv.checked = daten.id ? daten.aktiv == 1 : true;
    bootstrap.Modal.getOrCreateInstance(m).show();
    return f;
}
document.querySelectorAll('[data-konto]').forEach(function (b) {
    b.addEventListener('click', function (e) {
        e.preventDefault();
        var d = JSON.parse(b.dataset.konto || '{}');
        dialogFuellen('kontoModal', d, ['name', 'art', 'iban', 'sortierung', 'formation_id']);
        document.getElementById('k-anfang-feld').hidden = !!d.id;
    });
});
document.querySelectorAll('[data-kategorie]').forEach(function (b) {
    b.addEventListener('click', function (e) { e.preventDefault(); dialogFuellen('kategorieModal', JSON.parse(b.dataset.kategorie || '{}'), ['name', 'typ', 'sortierung', 'bmv_posten']); });
});
</script>
