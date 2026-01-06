<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'lesen');

$uniformObj = new Uniform();

// Filter
$filter = [];
if (!empty($_GET['kategorie_id'])) $filter['kategorie_id'] = $_GET['kategorie_id'];
if (!empty($_GET['zustand'])) $filter['zustand'] = $_GET['zustand'];
if (!empty($_GET['ausgegeben'])) $filter['ausgegeben'] = $_GET['ausgegeben'];
if (!empty($_GET['groesse'])) $filter['groesse'] = $_GET['groesse'];
if (!empty($_GET['search'])) $filter['search'] = $_GET['search'];

$uniformen = $uniformObj->getAll($filter);
$kategorien = $uniformObj->getKategorien();
$groessen = $uniformObj->getVerfuegbareGroessen();
$stats = $uniformObj->getStatistik();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-person-badge"></i> Uniformenverwaltung</h1>
    <?php if (Session::checkPermission('uniformen', 'schreiben')): ?>
    <div>
        <a href="uniform_bearbeiten.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Neues Uniformteil
        </a>
        <a href="uniform_kategorien.php" class="btn btn-outline-secondary">
            <i class="bi bi-tags"></i> Kategorien
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Statistik-Karten -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Gesamt</h6>
                        <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                    </div>
                    <i class="bi bi-archive fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Ausgegeben</h6>
                        <h2 class="mb-0"><?php echo $stats['ausgegeben']; ?></h2>
                    </div>
                    <i class="bi bi-person-check fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Verfügbar</h6>
                        <h2 class="mb-0"><?php echo $stats['verfuegbar']; ?></h2>
                    </div>
                    <i class="bi bi-box-seam fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Gesamtwert</h6>
                        <h2 class="mb-0"><?php echo number_format($stats['gesamtwert'], 0, ',', '.'); ?> €</h2>
                    </div>
                    <i class="bi bi-currency-euro fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" placeholder="Suche..." 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="kategorie_id">
                    <option value="">Alle Kategorien</option>
                    <?php foreach ($kategorien as $kat): ?>
                    <option value="<?php echo $kat['id']; ?>" 
                            <?php echo ($_GET['kategorie_id'] ?? '') == $kat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($kat['name']); ?> (<?php echo $kat['anzahl_teile']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="groesse">
                    <option value="">Alle Größen</option>
                    <?php foreach ($groessen as $g): ?>
                    <option value="<?php echo htmlspecialchars($g['groesse']); ?>" 
                            <?php echo ($_GET['groesse'] ?? '') === $g['groesse'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g['groesse']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="ausgegeben">
                    <option value="">Alle Status</option>
                    <option value="ja" <?php echo ($_GET['ausgegeben'] ?? '') === 'ja' ? 'selected' : ''; ?>>Ausgegeben</option>
                    <option value="nein" <?php echo ($_GET['ausgegeben'] ?? '') === 'nein' ? 'selected' : ''; ?>>Verfügbar</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filtern</button>
                <a href="uniformen.php" class="btn btn-secondary"><i class="bi bi-x"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Uniformen-Tabelle -->
<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="uniformenTable">
            <thead>
                <tr>
                    <th>Inv.-Nr.</th>
                    <th>Kategorie</th>
                    <th>Bezeichnung</th>
                    <th>Größe</th>
                    <th>Zustand</th>
                    <th>Ausgegeben an</th>
                    <th>Standort</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($uniformen as $u): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($u['inventar_nummer']); ?></code></td>
                    <td>
                        <span class="badge bg-secondary">
                            <?php echo htmlspecialchars($u['kategorie_name'] ?? 'Ohne Kategorie'); ?>
                        </span>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($u['bezeichnung'] ?? '-'); ?></strong>
                        <?php if ($u['farbe']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($u['farbe']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($u['groesse'] ?? '-'); ?></td>
                    <td>
                        <?php
                        $zustandColors = ['sehr gut' => 'success', 'gut' => 'info', 'befriedigend' => 'warning', 'schlecht' => 'danger'];
                        $color = $zustandColors[$u['zustand']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($u['zustand'] ?? 'gut'); ?></span>
                    </td>
                    <td>
                        <?php if ($u['mitglied_id']): ?>
                        <a href="mitglied_detail.php?id=<?php echo $u['mitglied_id']; ?>" class="text-decoration-none">
                            <span class="badge bg-warning text-dark">
                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($u['nachname'] . ' ' . $u['vorname']); ?>
                            </span>
                        </a>
                        <br><small class="text-muted">seit <?php echo date('d.m.Y', strtotime($u['ausgabe_datum'])); ?></small>
                        <?php else: ?>
                        <span class="badge bg-success"><i class="bi bi-check"></i> Verfügbar</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($u['standort'] ?? '-'); ?></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="uniform_detail.php?id=<?php echo $u['id']; ?>" class="btn btn-outline-secondary" title="Details">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (Session::checkPermission('uniformen', 'schreiben')): ?>
                            <a href="uniform_bearbeiten.php?id=<?php echo $u['id']; ?>" class="btn btn-outline-primary" title="Bearbeiten">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($u['mitglied_id']): ?>
                            <button type="button" class="btn btn-outline-success btn-zuruecknehmen" 
                                    data-id="<?php echo $u['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($u['inventar_nummer']); ?>"
                                    title="Zurücknehmen">
                                <i class="bi bi-box-arrow-in-down"></i>
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-outline-warning btn-ausgeben" 
                                    data-id="<?php echo $u['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($u['inventar_nummer'] . ' - ' . ($u['kategorie_name'] ?? '')); ?>"
                                    title="Ausgeben">
                                <i class="bi bi-box-arrow-up"></i>
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Uniform ausgeben -->
<div class="modal fade" id="ausgebenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="uniform_ausgeben.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-arrow-up text-warning"></i> Uniform ausgeben</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="uniform_id" id="ausgebenUniformId">
                    <p>Uniformteil: <strong id="ausgebenUniformName"></strong></p>
                    
                    <div class="mb-3">
                        <label for="mitglied_id" class="form-label">Ausgeben an <span class="text-danger">*</span></label>
                        <select class="form-select" name="mitglied_id" id="mitglied_id" required>
                            <option value="">-- Mitglied wählen --</option>
                            <?php
                            $db = Database::getInstance();
                            $mitglieder = $db->fetchAll("SELECT id, vorname, nachname, mitgliedsnummer FROM mitglieder WHERE status = 'aktiv' ORDER BY nachname, vorname");
                            foreach ($mitglieder as $m):
                            ?>
                            <option value="<?php echo $m['id']; ?>">
                                <?php echo htmlspecialchars($m['nachname'] . ' ' . $m['vorname'] . ' (' . $m['mitgliedsnummer'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bemerkungen" class="form-label">Bemerkungen</label>
                        <textarea class="form-control" name="bemerkungen" id="bemerkungen" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-box-arrow-up"></i> Ausgeben
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Uniform zurücknehmen -->
<div class="modal fade" id="zuruecknehmenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="uniform_zuruecknehmen.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-arrow-in-down text-success"></i> Uniform zurücknehmen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="uniform_id" id="zuruecknehmenUniformId">
                    <p>Uniformteil: <strong id="zuruecknehmenUniformName"></strong></p>
                    
                    <div class="mb-3">
                        <label for="zustand_rueckgabe" class="form-label">Zustand bei Rückgabe</label>
                        <select class="form-select" name="zustand" id="zustand_rueckgabe">
                            <option value="">-- Unverändert --</option>
                            <option value="sehr gut">Sehr gut</option>
                            <option value="gut">Gut</option>
                            <option value="befriedigend">Befriedigend</option>
                            <option value="schlecht">Schlecht</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bemerkungen_rueckgabe" class="form-label">Bemerkungen</label>
                        <textarea class="form-control" name="bemerkungen" id="bemerkungen_rueckgabe" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-box-arrow-in-down"></i> Zurücknehmen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
(function() {
    // DataTable
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        $('#uniformenTable').DataTable({
            order: [[1, 'asc'], [3, 'asc']],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/de-DE.json'
            }
        });
    }
    
    // Ausgeben Modal
    document.querySelectorAll('.btn-ausgeben').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('ausgebenUniformId').value = this.dataset.id;
            document.getElementById('ausgebenUniformName').textContent = this.dataset.name;
            new bootstrap.Modal(document.getElementById('ausgebenModal')).show();
        });
    });
    
    // Zurücknehmen Modal
    document.querySelectorAll('.btn-zuruecknehmen').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('zuruecknehmenUniformId').value = this.dataset.id;
            document.getElementById('zuruecknehmenUniformName').textContent = this.dataset.name;
            new bootstrap.Modal(document.getElementById('zuruecknehmenModal')).show();
        });
    });
})();
</script>
