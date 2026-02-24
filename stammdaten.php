<?php
// stammdaten.php - Verwaltung von Registern, Ausrückungstypen, Instrumententypen
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (Session::getRole() !== 'admin') {
    Session::setFlashMessage('danger', 'Nur Administratoren haben Zugriff');
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();
$tab = $_GET['tab'] ?? 'register';
$action = $_POST['action'] ?? null;
$error = null;
$success = null;

// ===================== VERARBEITUNG =====================

// Register verarbeiten
if ($action === 'register_save') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $sortierung = (int)($_POST['sortierung'] ?? 0);
    
    if (empty($name)) {
        $error = 'Bitte einen Namen eingeben';
    } else {
        try {
            if ($id) {
                $db->execute("UPDATE register SET name = ?, sortierung = ? WHERE id = ?", [$name, $sortierung, $id]);
                $success = 'Register aktualisiert';
            } else {
                $db->execute("INSERT INTO register (name, sortierung) VALUES (?, ?)", [$name, $sortierung]);
                $success = 'Register erstellt';
            }
        } catch (Exception $e) {
            $error = 'Fehler: ' . $e->getMessage();
        }
    }
    $tab = 'register';
}

if ($action === 'register_delete') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        $count = $db->fetchOne("SELECT COUNT(*) as cnt FROM mitglieder WHERE register_id = ?", [$id]);
        if ($count['cnt'] > 0) {
            $error = 'Register kann nicht gelöscht werden, da noch ' . $count['cnt'] . ' Mitglieder zugeordnet sind';
        } else {
            $db->execute("DELETE FROM register WHERE id = ?", [$id]);
            $success = 'Register gelöscht';
        }
    }
    $tab = 'register';
}

// Instrumententypen verarbeiten
if ($action === 'instrumenttyp_save') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $register_id = $_POST['register_id'] ?: null;
    
    if (empty($name)) {
        $error = 'Bitte einen Namen eingeben';
    } else {
        try {
            if ($id) {
                $db->execute("UPDATE instrument_typen SET name = ?, register_id = ? WHERE id = ?", [$name, $register_id, $id]);
                $success = 'Instrumententyp aktualisiert';
            } else {
                $db->execute("INSERT INTO instrument_typen (name, register_id) VALUES (?, ?)", [$name, $register_id]);
                $success = 'Instrumententyp erstellt';
            }
        } catch (Exception $e) {
            $error = 'Fehler: ' . $e->getMessage();
        }
    }
    $tab = 'instrumenttypen';
}

if ($action === 'instrumenttyp_delete') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        $countInstr = $db->fetchOne("SELECT COUNT(*) as cnt FROM instrumente WHERE instrument_typ_id = ?", [$id]);
        $countMitgl = $db->fetchOne("SELECT COUNT(*) as cnt FROM mitglied_instrumente WHERE instrument_typ_id = ?", [$id]);
        $total = $countInstr['cnt'] + $countMitgl['cnt'];
        
        if ($total > 0) {
            $error = 'Instrumententyp kann nicht gelöscht werden, da er noch verwendet wird';
        } else {
            $db->execute("DELETE FROM instrument_typen WHERE id = ?", [$id]);
            $success = 'Instrumententyp gelöscht';
        }
    }
    $tab = 'instrumenttypen';
}

// ===================== DATEN LADEN =====================

$register = $db->fetchAll("SELECT r.*, 
    (SELECT COUNT(*) FROM mitglieder WHERE register_id = r.id) as mitglieder_count,
    (SELECT COUNT(*) FROM instrument_typen WHERE register_id = r.id) as instrumente_count
    FROM register r ORDER BY r.sortierung, r.name");

$instrumentTypen = $db->fetchAll("SELECT it.*, r.name as register_name,
    (SELECT COUNT(*) FROM instrumente WHERE instrument_typ_id = it.id) as inventar_count,
    (SELECT COUNT(*) FROM mitglied_instrumente WHERE instrument_typ_id = it.id) as spieler_count
    FROM instrument_typen it
    LEFT JOIN register r ON it.register_id = r.id
    ORDER BY r.sortierung, it.name");

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-database-gear"></i> Stammdaten</h1>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'register' ? 'active' : ''; ?>" href="?tab=register">
            <i class="bi bi-collection"></i> Register
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'instrumenttypen' ? 'active' : ''; ?>" href="?tab=instrumenttypen">
            <i class="bi bi-music-note"></i> Instrumententypen
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'ausrueckungstypen' ? 'active' : ''; ?>" href="?tab=ausrueckungstypen">
            <i class="bi bi-calendar-event"></i> Ausrückungstypen
        </a>
    </li>
</ul>

<?php if ($tab === 'register'): ?>
<!-- ==================== REGISTER ==================== -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-collection"></i> Register</span>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal" onclick="editRegister()">
                    <i class="bi bi-plus"></i> Neues Register
                </button>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Sort.</th>
                            <th>Name</th>
                            <th>Mitglieder</th>
                            <th>Instrumente</th>
                            <th class="text-end">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($register as $r): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?php echo $r['sortierung']; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($r['name']); ?></strong></td>
                            <td><?php echo $r['mitglieder_count']; ?></td>
                            <td><?php echo $r['instrumente_count']; ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-primary" onclick="editRegister(<?php echo htmlspecialchars(json_encode($r)); ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($r['mitglieder_count'] == 0 && $r['instrumente_count'] == 0): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Register wirklich löschen?')">
                                    <input type="hidden" name="action" value="register_delete">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($register)): ?>
                        <tr><td colspan="5" class="text-muted text-center">Keine Register vorhanden</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card bg-light">
            <div class="card-body">
                <h6><i class="bi bi-info-circle"></i> Info</h6>
                <p class="small text-muted mb-0">
                    Register gruppieren Mitglieder und Instrumente nach Instrumentenfamilien 
                    (z.B. Holz, Blech, Schlagwerk). Die Sortierung bestimmt die Reihenfolge in Auswahllisten.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Register Modal -->
<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="register_save">
                <input type="hidden" name="id" id="register_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="registerModalTitle">Register bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" name="name" id="register_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sortierung</label>
                        <input type="number" class="form-control" name="sortierung" id="register_sortierung" value="0">
                        <small class="text-muted">Niedrigere Zahlen werden zuerst angezeigt</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php elseif ($tab === 'instrumenttypen'): ?>
<!-- ==================== INSTRUMENTENTYPEN ==================== -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-music-note"></i> Instrumententypen</span>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#instrumentTypModal" onclick="editInstrumentTyp()">
                    <i class="bi bi-plus"></i> Neuer Typ
                </button>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Register</th>
                            <th>Im Inventar</th>
                            <th>Spieler</th>
                            <th class="text-end">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instrumentTypen as $it): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($it['name']); ?></strong></td>
                            <td>
                                <?php if ($it['register_name']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($it['register_name']); ?></span>
                                <?php else: ?>
                                <span class="text-muted">–</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $it['inventar_count']; ?></td>
                            <td><?php echo $it['spieler_count']; ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-primary" onclick="editInstrumentTyp(<?php echo htmlspecialchars(json_encode($it)); ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($it['inventar_count'] == 0 && $it['spieler_count'] == 0): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Instrumententyp wirklich löschen?')">
                                    <input type="hidden" name="action" value="instrumenttyp_delete">
                                    <input type="hidden" name="id" value="<?php echo $it['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($instrumentTypen)): ?>
                        <tr><td colspan="5" class="text-muted text-center">Keine Instrumententypen vorhanden</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card bg-light">
            <div class="card-body">
                <h6><i class="bi bi-info-circle"></i> Info</h6>
                <p class="small text-muted mb-0">
                    Instrumententypen definieren die verschiedenen Instrumente (Trompete, Klarinette, etc.).
                    Sie werden einem Register zugeordnet und können bei Mitgliedern und im Inventar verwendet werden.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Instrumententyp Modal -->
<div class="modal fade" id="instrumentTypModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="instrumenttyp_save">
                <input type="hidden" name="id" id="instrumenttyp_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="instrumentTypModalTitle">Instrumententyp bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" name="name" id="instrumenttyp_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Register</label>
                        <select class="form-select" name="register_id" id="instrumenttyp_register">
                            <option value="">Kein Register</option>
                            <?php foreach ($register as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ==================== AUSRÜCKUNGSTYPEN ==================== -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-calendar-event"></i> Ausrückungstypen
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle"></i>
                    Die Ausrückungstypen sind fest im System definiert und können derzeit nicht geändert werden.
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Typ</th>
                            <th>Beschreibung</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><span class="badge bg-secondary">Probe</span></td><td>Reguläre Proben und Registerproben</td></tr>
                        <tr><td><span class="badge bg-primary">Konzert</span></td><td>Konzerte und musikalische Aufführungen</td></tr>
                        <tr><td><span class="badge bg-success">Ausrückung</span></td><td>Umzüge, Prozessionen, Ständchen</td></tr>
                        <tr><td><span class="badge bg-warning">Fest</span></td><td>Vereinsfeste und Feiern</td></tr>
                        <tr><td><span class="badge bg-danger">Wertung</span></td><td>Wertungsspiele und Wettbewerbe</td></tr>
                        <tr><td><span class="badge bg-info">Sonstiges</span></td><td>Sonstige Termine</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card bg-light">
            <div class="card-body">
                <h6><i class="bi bi-info-circle"></i> Info</h6>
                <p class="small text-muted mb-0">
                    Die Ausrückungstypen bestimmen die Kategorie eines Termins im Kalender. 
                    Bei Bedarf können zusätzliche Typen durch den Systemadministrator hinzugefügt werden.
                </p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function editRegister(data = null) {
    document.getElementById('register_id').value = data ? data.id : '';
    document.getElementById('register_name').value = data ? data.name : '';
    document.getElementById('register_sortierung').value = data ? data.sortierung : 0;
    document.getElementById('registerModalTitle').textContent = data ? 'Register bearbeiten' : 'Neues Register';
    new bootstrap.Modal(document.getElementById('registerModal')).show();
}

function editInstrumentTyp(data = null) {
    document.getElementById('instrumenttyp_id').value = data ? data.id : '';
    document.getElementById('instrumenttyp_name').value = data ? data.name : '';
    document.getElementById('instrumenttyp_register').value = data ? (data.register_id || '') : '';
    document.getElementById('instrumentTypModalTitle').textContent = data ? 'Instrumententyp bearbeiten' : 'Neuer Instrumententyp';
    new bootstrap.Modal(document.getElementById('instrumentTypModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
