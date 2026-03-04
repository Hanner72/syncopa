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

// Nummernkreise verarbeiten
if ($action === 'nummernkreise_save') {
    $typen = ['mitglieder', 'noten', 'instrumente', 'uniformen'];
    try {
        foreach ($typen as $typ) {
            $prefix  = trim($_POST['prefix_' . $typ]  ?? '');
            $stellen = (int)($_POST['stellen_' . $typ] ?? 3);
            $stellen = max(1, min(10, $stellen));

            // Prefix speichern (Y/y als Platzhalter behalten – wird bei Nummerngenerierung aufgelöst)
            $db->execute(
                "INSERT INTO einstellungen (schluessel, wert, beschreibung)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE wert = VALUES(wert)",
                [
                    'nummernkreis_' . $typ . '_prefix',
                    $prefix,
                    'Nummernkreis ' . ucfirst($typ) . ' – Präfix'
                ]
            );
            $db->execute(
                "INSERT INTO einstellungen (schluessel, wert, beschreibung)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE wert = VALUES(wert)",
                [
                    'nummernkreis_' . $typ . '_stellen',
                    (string)$stellen,
                    'Nummernkreis ' . ucfirst($typ) . ' – Stellen'
                ]
            );
        }
        $success = 'Nummernkreise erfolgreich gespeichert';
    } catch (Exception $e) {
        $error = 'Fehler beim Speichern: ' . $e->getMessage();
    }
    $tab = 'nummernkreise';
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

// Nummernkreise aus einstellungen laden
$nkTypen = ['mitglieder', 'noten', 'instrumente'];
$nkConfig = [];
foreach ($nkTypen as $typ) {
    $pRow = $db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = ?", ['nummernkreis_' . $typ . '_prefix']);
    $sRow = $db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = ?", ['nummernkreis_' . $typ . '_stellen']);
    $nkConfig[$typ] = [
        'prefix'  => $pRow ? $pRow['wert'] : '',
        'stellen' => $sRow ? (int)$sRow['wert'] : 3,
    ];
}

// Hilfsfunktion: Y/y → aktuelles Jahr auflösen (für Vorschau)
function resolvePrefix(string $prefix): string {
    $year = date('Y');
    $yearShort = date('y');
    $prefix = str_replace('Y', $year,      $prefix);
    $prefix = str_replace('y', $yearShort, $prefix);
    return $prefix;
}

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
    <li class="nav-item">
        <a class="nav-link <?php echo $tab === 'nummernkreise' ? 'active' : ''; ?>" href="?tab=nummernkreise">
            <i class="bi bi-123"></i> Nummernkreise
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

<?php elseif ($tab === 'ausrueckungstypen'): ?>
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

<?php else: ?>
<!-- ==================== NUMMERNKREISE ==================== -->
<?php
$nkBadgeClass = [
    'mitglieder' => 'bg-secondary',
    'noten'      => 'bg-primary',
    'instrumente'=> 'bg-warning text-dark',
];
$nkLabel = [
    'mitglieder' => 'Mitglieder',
    'noten'      => 'Noten',
    'instrumente'=> 'Instrumente',
];
$nkIcon = [
    'mitglieder' => 'bi-people',
    'noten'      => 'bi-music-note-list',
    'instrumente'=> 'bi-trumpet',
];
?>
<form method="POST">
    <input type="hidden" name="action" value="nummernkreise_save">

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-123"></i> Nummernkreise konfigurieren
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle"></i>
                        <strong>Präfix-Platzhalter:</strong>
                        <code>Y</code> = aktuelles Jahr 4-stellig (<?php echo date('Y'); ?>),
                        <code>y</code> = aktuelles Jahr 2-stellig (<?php echo date('y'); ?>).
                        Beispiel: <code>Iy</code> ergibt <code>I<?php echo date('y'); ?>001</code>
                    </div>

                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:160px">Bereich</th>
                                <th>Präfix</th>
                                <th style="width:140px">Stellen (Zahl)</th>
                                <th>Vorschau nächste Nr.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nkTypen as $typ): ?>
                            <?php
                                $prefix  = $nkConfig[$typ]['prefix'];
                                $stellen = $nkConfig[$typ]['stellen'];
                                $resolvedPrefix = resolvePrefix($prefix);
                                $preview = $resolvedPrefix . str_pad('1', $stellen, '0', STR_PAD_LEFT);
                            ?>
                            <tr>
                                <td>
                                    <span class="badge <?php echo $nkBadgeClass[$typ]; ?>">
                                        <i class="bi <?php echo $nkIcon[$typ]; ?>"></i>
                                        <?php echo $nkLabel[$typ]; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm" style="max-width:180px">
                                        <input type="text"
                                               class="form-control nk-prefix-input"
                                               name="prefix_<?php echo $typ; ?>"
                                               value="<?php echo htmlspecialchars($prefix); ?>"
                                               data-typ="<?php echo $typ; ?>"
                                               maxlength="20"
                                               placeholder="z.B. M, NY, Iy">
                                    </div>
                                </td>
                                <td>
                                    <input type="number"
                                           class="form-control form-control-sm nk-stellen-input"
                                           name="stellen_<?php echo $typ; ?>"
                                           value="<?php echo $stellen; ?>"
                                           data-typ="<?php echo $typ; ?>"
                                           min="1" max="10"
                                           style="width:90px">
                                </td>
                                <td>
                                    <code class="nk-preview" id="preview_<?php echo $typ; ?>">
                                        <?php echo htmlspecialchars($preview); ?>
                                    </code>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Nummernkreise speichern
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <h6><i class="bi bi-info-circle"></i> Hinweise</h6>
                    <p class="small text-muted mb-2">
                        Der <strong>Präfix</strong> wird vor die fortlaufende Nummer gestellt.
                        Die Anzahl der <strong>Stellen</strong> bestimmt, wie breit die Zahl mit führenden Nullen aufgefüllt wird.
                    </p>
                    <ul class="small text-muted mb-0 ps-3">
                        <li><code>Y</code> → <?php echo date('Y'); ?> (4-stelliges Jahr)</li>
                        <li><code>y</code> → <?php echo date('y'); ?> (2-stelliges Jahr)</li>
                        <li>Stellen 3 + Präfix <code>M</code> → <code>M001</code></li>
                        <li>Stellen 4 + Präfix <code>Ny</code> → <code>N<?php echo date('y'); ?>0001</code></li>
                    </ul>
                </div>
            </div>
            <div class="card border-warning">
                <div class="card-body">
                    <h6 class="text-warning"><i class="bi bi-exclamation-triangle"></i> Achtung</h6>
                    <p class="small text-muted mb-0">
                        Änderungen am Präfix oder den Stellen wirken sich nur auf <strong>neu angelegte</strong> Datensätze aus.
                        Bestehende Nummern bleiben unverändert.
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const yearFull  = '<?php echo date('Y'); ?>';
    const yearShort = '<?php echo date('y'); ?>';

    function resolvePrefix(prefix) {
        return prefix.replace(/Y/g, yearFull).replace(/y/g, yearShort);
    }

    function updatePreview(typ) {
        const prefixInput  = document.querySelector('.nk-prefix-input[data-typ="' + typ + '"]');
        const stellenInput = document.querySelector('.nk-stellen-input[data-typ="' + typ + '"]');
        const previewEl    = document.getElementById('preview_' + typ);

        if (!prefixInput || !stellenInput || !previewEl) return;

        const resolved = resolvePrefix(prefixInput.value);
        const stellen  = Math.max(1, Math.min(10, parseInt(stellenInput.value) || 3));
        previewEl.textContent = resolved + String(1).padStart(stellen, '0');
    }

    document.querySelectorAll('.nk-prefix-input, .nk-stellen-input').forEach(function (el) {
        el.addEventListener('input', function () {
            updatePreview(this.dataset.typ);
        });
    });
})();
</script>

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
