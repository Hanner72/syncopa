<?php
require_once 'config.php';
require_once 'includes.php';
Session::requireLogin();
Session::requirePermission('formationen', 'schreiben');

$formationObj = new Formation();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$formation = $formationObj->getById($id);
if (!$formation) { Session::setFlashMessage('danger', 'Formation nicht gefunden.'); header('Location: formationen.php'); exit; }

// AJAX-Aktionen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    if ($action === 'add') {
        $mid = (int)($_POST['mitglied_id'] ?? 0);
        $rolle = trim($_POST['rolle'] ?? '') ?: null;
        if ($mid) { $formationObj->addMitglied($id, $mid, $rolle); }
        echo json_encode(['success' => true]);
    } elseif ($action === 'remove') {
        $mid = (int)($_POST['mitglied_id'] ?? 0);
        if ($mid) { $formationObj->removeMitglied($id, $mid); }
        echo json_encode(['success' => true]);
    } elseif ($action === 'rolle') {
        $mid = (int)($_POST['mitglied_id'] ?? 0);
        $rolle = trim($_POST['rolle'] ?? '') ?: null;
        if ($mid) { $formationObj->updateMitgliedRolle($id, $mid, $rolle); }
        echo json_encode(['success' => true]);
    }
    exit;
}

$mitglieder       = $formationObj->getMitglieder($id);
$verfuegbare      = $formationObj->getMitgliederOhneFormation($id);

include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <span class="d-inline-block rounded-circle me-2" style="width:14px;height:14px;background:<?php echo htmlspecialchars($formation['farbe']); ?>;vertical-align:middle"></span>
        <?php echo htmlspecialchars($formation['name']); ?> – Mitglieder
    </h1>
    <div>
        <a href="formation_bearbeiten.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-1">
            <i class="bi bi-pencil"></i> Formation bearbeiten
        </a>
        <a href="formationen.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Zurück
        </a>
    </div>
</div>

<div class="row">
    <!-- Zugeordnete Mitglieder -->
    <div class="col-lg-7 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people me-2"></i>Zugeordnete Mitglieder
                    <span class="badge bg-primary ms-1"><?php echo count($mitglieder); ?></span>
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($mitglieder)): ?>
                <p class="text-muted p-3 mb-0">Noch keine Mitglieder zugeordnet.</p>
                <?php else: ?>
                <table class="table table-hover mb-0" id="tblMitglieder">
                    <thead>
                        <tr>
                            <th>Mitglied</th>
                            <th>Register</th>
                            <th>Rolle in Formation</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mitglieder as $m): ?>
                        <tr data-mid="<?php echo $m['id']; ?>">
                            <td><?php echo htmlspecialchars($m['nachname'] . ', ' . $m['vorname']); ?></td>
                            <td><span class="text-muted"><?php echo htmlspecialchars($m['register_name'] ?? '–'); ?></span></td>
                            <td>
                                <input type="text" class="form-control form-control-sm rolle-input"
                                       value="<?php echo htmlspecialchars($m['rolle'] ?? ''); ?>"
                                       placeholder="z.B. Stimmführer"
                                       data-mid="<?php echo $m['id']; ?>">
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger" onclick="removeMitglied(<?php echo $m['id']; ?>)">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Verfügbare Mitglieder zum Hinzufügen -->
    <div class="col-lg-5 mb-3">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-person-plus me-2"></i>Mitglied hinzufügen
            </div>
            <div class="card-body">
                <?php if (empty($verfuegbare)): ?>
                <p class="text-muted mb-0">Alle aktiven Mitglieder sind bereits zugeordnet.</p>
                <?php else: ?>
                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm" id="searchVerfuegbar"
                           placeholder="Mitglied suchen…">
                </div>
                <div style="max-height:400px;overflow-y:auto">
                    <table class="table table-sm table-hover mb-0" id="tblVerfuegbar">
                        <tbody>
                        <?php foreach ($verfuegbare as $m): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($m['nachname'] . ', ' . $m['vorname']); ?>
                                    <div class="text-muted" style="font-size:11px"><?php echo htmlspecialchars($m['register_name'] ?? ''); ?></div>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-success" onclick="addMitglied(<?php echo $m['id']; ?>, this)">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const formationId = <?php echo $id; ?>;

function addMitglied(mid, btn) {
    btn.disabled = true;
    fetch('formation_mitglieder.php?id=' + formationId, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add&mitglied_id=' + mid
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else { alert('Fehler'); btn.disabled = false; }
    });
}

function removeMitglied(mid) {
    if (!confirm('Mitglied aus dieser Formation entfernen?')) return;
    fetch('formation_mitglieder.php?id=' + formationId, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=remove&mitglied_id=' + mid
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
    });
}

// Rolle speichern (debounced)
let rolleTimers = {};
document.querySelectorAll('.rolle-input').forEach(inp => {
    inp.addEventListener('input', function() {
        const mid = this.dataset.mid;
        clearTimeout(rolleTimers[mid]);
        rolleTimers[mid] = setTimeout(() => {
            fetch('formation_mitglieder.php?id=' + formationId, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=rolle&mitglied_id=' + mid + '&rolle=' + encodeURIComponent(this.value)
            });
        }, 600);
    });
});

// Suche in verfügbaren Mitgliedern
const searchInput = document.getElementById('searchVerfuegbar');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#tblVerfuegbar tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });
}
</script>

<?php include 'includes/footer.php'; ?>
