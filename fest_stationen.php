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

$stationObj = new FestStation();
$stationen  = $stationObj->getByFest($festId);

// Alle Festtage ermitteln
$alleDaten = [];
if (!empty($fest['datum_von'])) {
    $d   = new DateTime($fest['datum_von']);
    $end = new DateTime($fest['datum_bis'] ?: $fest['datum_von']);
    while ($d <= $end) {
        $alleDaten[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }
}

// Tages-Konfigs aller Stationen vorausladen (für Modal)
$tageKonfigs = [];
foreach ($stationen as $s) {
    $tageKonfigs[$s['id']] = $stationObj->getTageKonfigs($s['id'], $alleDaten);
}

include 'includes/header.php';
?>

<?php include 'includes/fest_tabs.php'; ?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-geo-alt"></i> Stationen</h1>
    <?php if (Session::checkPermission('fest', 'schreiben')): ?>
    <a href="fest_station_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Station hinzufügen
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($stationen)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-shop fs-1 d-block mb-2 opacity-25"></i>
            Noch keine Stationen erfasst.
            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
            <div class="mt-2"><a href="fest_station_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-sm btn-primary">Erste Station anlegen</a></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0" id="stationenTable">
            <thead>
                <tr>
                    <th>Sort.</th>
                    <th>Name</th>
                    <th>Beschreibung</th>
                    <th class="text-center">Helfer (Standard)</th>
                    <th>Öffnungszeiten (Standard)</th>
                    <?php if (!empty($alleDaten)): ?>
                    <th class="text-center">Tage aktiv</th>
                    <?php endif; ?>
                    <th class="text-center">Schichten</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stationen as $s):
                    $aktiveTagCount = 0;
                    foreach ($tageKonfigs[$s['id']] ?? [] as $tk) {
                        if ($tk['aktiv']) $aktiveTagCount++;
                    }
                ?>
                <tr>
                    <td class="text-muted"><?php echo $s['sortierung']; ?></td>
                    <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                    <td class="text-muted small"><?php echo htmlspecialchars($s['beschreibung'] ?? '–'); ?></td>
                    <td class="text-center">
                        <span class="badge bg-primary"><?php echo $s['benoetigte_helfer']; ?></span>
                    </td>
                    <td class="small">
                        <?php if ($s['oeffnung_von'] && $s['oeffnung_bis']): ?>
                        <?php echo substr($s['oeffnung_von'],0,5); ?> – <?php echo substr($s['oeffnung_bis'],0,5); ?>
                        <?php else: ?>
                        <span class="text-muted">–</span>
                        <?php endif; ?>
                    </td>
                    <?php if (!empty($alleDaten)): ?>
                    <td class="text-center">
                        <span class="badge bg-<?php echo $aktiveTagCount === count($alleDaten) ? 'success' : ($aktiveTagCount > 0 ? 'warning' : 'secondary'); ?>">
                            <?php echo $aktiveTagCount; ?>/<?php echo count($alleDaten); ?>
                        </span>
                    </td>
                    <?php endif; ?>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?php echo $s['schichten_anzahl']; ?></span>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <?php if (!empty($alleDaten) && Session::checkPermission('fest', 'schreiben')): ?>
                            <button type="button" class="btn btn-outline-primary btn-tage"
                                    data-station-id="<?php echo $s['id']; ?>"
                                    data-station-name="<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>"
                                    title="Tageszeiten konfigurieren">
                                <i class="bi bi-calendar3"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                            <a href="fest_station_bearbeiten.php?id=<?php echo $s['id']; ?>&fest_id=<?php echo $festId; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (Session::checkPermission('fest', 'loeschen')): ?>
                            <form method="POST" action="fest_station_loeschen.php" class="d-inline"
                                  onsubmit="return confirm('Station «<?php echo htmlspecialchars(addslashes($s['name'])); ?>» löschen?')">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <input type="hidden" name="fest_id" value="<?php echo $festId; ?>">
                                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Tage-Modal -->
<div class="modal fade" id="tageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar3"></i> Tageszeiten – <span id="tageModalName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm mb-0" id="tageTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:130px">Tag</th>
                            <th class="text-center" style="width:70px">Aktiv</th>
                            <th>Von</th>
                            <th>Bis</th>
                            <th class="text-center">Helfer</th>
                            <th style="width:90px"></th>
                        </tr>
                    </thead>
                    <tbody id="tageBody"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
var tageKonfigs = <?php echo json_encode($tageKonfigs); ?>;
var alleDaten   = <?php echo json_encode($alleDaten); ?>;
var tageModal   = new bootstrap.Modal(document.getElementById('tageModal'));

if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $('#stationenTable').DataTable({ order: [[0, 'asc']] });
}

function fillTageModal(stationId, konfigs) {
    var tbody = document.getElementById('tageBody');
    tbody.innerHTML = '';
    alleDaten.forEach(function(datum) {
        var k     = konfigs[datum] || { aktiv:1, oeffnung_von:'', oeffnung_bis:'', benoetigte_helfer:1 };
        var tVon  = k.oeffnung_von ? k.oeffnung_von.substring(0,5) : '';
        var tBis  = k.oeffnung_bis ? k.oeffnung_bis.substring(0,5) : '';
        var label = new Date(datum + 'T00:00:00').toLocaleDateString('de-AT', { weekday:'short', day:'2-digit', month:'2-digit' });

        var tr = document.createElement('tr');
        tr.dataset.stationId = stationId;
        tr.dataset.datum     = datum;
        tr.innerHTML =
            '<td class="align-middle small fw-semibold">' + label + '</td>' +
            '<td class="text-center align-middle">' +
                '<div class="form-check form-switch d-inline-block m-0">' +
                    '<input class="form-check-input tage-aktiv" type="checkbox" style="cursor:pointer"' + (parseInt(k.aktiv) ? ' checked' : '') + '>' +
                '</div>' +
            '</td>' +
            '<td><input type="time" class="form-control form-control-sm tage-von" value="' + tVon + '" style="min-width:100px"></td>' +
            '<td><input type="time" class="form-control form-control-sm tage-bis" value="' + tBis + '" style="min-width:100px"></td>' +
            '<td><input type="number" class="form-control form-control-sm tage-helfer text-center" value="' + k.benoetigte_helfer + '" min="0" style="width:60px;margin:auto"></td>' +
            '<td><button class="btn btn-sm btn-primary btn-save-tag w-100">Speichern</button></td>';
        tbody.appendChild(tr);

        tr.querySelector('.tage-aktiv').addEventListener('change', function() {
            var inputs = tr.querySelectorAll('.tage-von, .tage-bis, .tage-helfer');
            inputs.forEach(function(i) { i.disabled = !tr.querySelector('.tage-aktiv').checked; });
        });
        if (!parseInt(k.aktiv)) {
            tr.querySelectorAll('.tage-von, .tage-bis, .tage-helfer').forEach(function(i) { i.disabled = true; });
        }

        tr.querySelector('.btn-save-tag').addEventListener('click', function() {
            var btn    = this;
            var sid    = tr.dataset.stationId;
            var dat    = tr.dataset.datum;
            btn.disabled = true;
            btn.textContent = '…';
            var aktiv  = tr.querySelector('.tage-aktiv').checked ? 1 : 0;
            var von    = tr.querySelector('.tage-von').value;
            var bis    = tr.querySelector('.tage-bis').value;
            var helfer = tr.querySelector('.tage-helfer').value;

            fetch('api/fest_station_tag.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'station_id=' + sid +
                      '&datum=' + dat +
                      '&aktiv=' + aktiv +
                      '&oeffnung_von=' + encodeURIComponent(von) +
                      '&oeffnung_bis=' + encodeURIComponent(bis) +
                      '&benoetigte_helfer=' + (parseInt(helfer) || 0)
            })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (resp.success) {
                    btn.textContent = '✓';
                    btn.classList.replace('btn-primary', 'btn-success');
                    // Gespeicherte Werte direkt in die Zeile schreiben
                    if (resp.saved) {
                        var s = resp.saved;
                        var isAktiv = !!parseInt(s.aktiv);
                        tr.querySelector('.tage-aktiv').checked = isAktiv;
                        tr.querySelector('.tage-von').value  = s.oeffnung_von  ? s.oeffnung_von.substring(0,5)  : '';
                        tr.querySelector('.tage-bis').value  = s.oeffnung_bis  ? s.oeffnung_bis.substring(0,5)  : '';
                        tr.querySelector('.tage-helfer').value = s.benoetigte_helfer;
                        tr.querySelectorAll('.tage-von,.tage-bis,.tage-helfer').forEach(function(i){ i.disabled = !isAktiv; });
                    }
                    setTimeout(function() {
                        btn.textContent = 'Speichern';
                        btn.classList.replace('btn-success', 'btn-primary');
                        btn.disabled = false;
                    }, 1500);
                } else {
                    alert('Fehler: ' + (resp.error || 'Unbekannt'));
                    btn.textContent = 'Speichern';
                    btn.disabled = false;
                }
            })
            .catch(function() { btn.textContent = 'Speichern'; btn.disabled = false; });
        });
    });
}

document.querySelectorAll('.btn-tage').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var stationId   = this.dataset.stationId;
        var stationName = this.dataset.stationName;
        document.getElementById('tageModalName').textContent = stationName;
        document.getElementById('tageBody').innerHTML =
            '<tr><td colspan="6" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div> Lade…</td></tr>';
        tageModal.show();

        fetch('api/fest_station_tage_get.php?station_id=' + stationId + '&daten=' + encodeURIComponent(alleDaten.join(',')) + '&_=' + Date.now())
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (resp.success) {
                    fillTageModal(stationId, resp.konfigs);
                } else {
                    document.getElementById('tageBody').innerHTML =
                        '<tr><td colspan="6" class="text-center text-danger py-3">Fehler beim Laden</td></tr>';
                }
            })
            .catch(function() {
                document.getElementById('tageBody').innerHTML =
                    '<tr><td colspan="6" class="text-center text-danger py-3">Fehler beim Laden</td></tr>';
            });
    });
});
</script>
