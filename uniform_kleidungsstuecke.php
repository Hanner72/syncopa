<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'schreiben');

$uniformObj = new Uniform();

// Aktionen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            // KATEGORIEN
            case 'kategorie_erstellen':
                $data = [
                    'name' => trim($_POST['name'] ?? ''),
                    'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                    'sortierung' => (int)($_POST['sortierung'] ?? 100)
                ];
                if (empty($data['name'])) throw new Exception('Bitte einen Namen eingeben.');
                $uniformObj->createKategorie($data);
                Session::setFlashMessage('success', 'Kategorie erfolgreich angelegt.');
                break;
                
            case 'kategorie_aktualisieren':
                $data = [
                    'name' => trim($_POST['name'] ?? ''),
                    'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                    'sortierung' => (int)($_POST['sortierung'] ?? 100)
                ];
                if (empty($data['name'])) throw new Exception('Bitte einen Namen eingeben.');
                $uniformObj->updateKategorie($_POST['id'], $data);
                Session::setFlashMessage('success', 'Kategorie erfolgreich aktualisiert.');
                break;
                
            case 'kategorie_loeschen':
                Session::requirePermission('uniformen', 'loeschen');
                $uniformObj->deleteKategorie($_POST['id']);
                Session::setFlashMessage('success', 'Kategorie erfolgreich gelöscht.');
                break;
                
            // KLEIDUNGSSTÜCKE
            case 'kleidungsstueck_erstellen':
                $data = [
                    'kategorie_id' => $_POST['kategorie_id'] ?: null,
                    'name' => trim($_POST['name'] ?? ''),
                    'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                    'groessen_verfuegbar' => trim($_POST['groessen_verfuegbar'] ?? '') ?: null,
                    'sortierung' => (int)($_POST['sortierung'] ?? 100)
                ];
                if (empty($data['name'])) throw new Exception('Bitte einen Namen eingeben.');
                $uniformObj->createKleidungsstueck($data);
                Session::setFlashMessage('success', 'Kleidungsstück erfolgreich angelegt.');
                break;
                
            case 'kleidungsstueck_aktualisieren':
                $data = [
                    'kategorie_id' => $_POST['kategorie_id'] ?: null,
                    'name' => trim($_POST['name'] ?? ''),
                    'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                    'groessen_verfuegbar' => trim($_POST['groessen_verfuegbar'] ?? '') ?: null,
                    'sortierung' => (int)($_POST['sortierung'] ?? 100)
                ];
                if (empty($data['name'])) throw new Exception('Bitte einen Namen eingeben.');
                $uniformObj->updateKleidungsstueck($_POST['id'], $data);
                Session::setFlashMessage('success', 'Kleidungsstück erfolgreich aktualisiert.');
                break;
                
            case 'kleidungsstueck_loeschen':
                Session::requirePermission('uniformen', 'loeschen');
                $uniformObj->deleteKleidungsstueck($_POST['id']);
                Session::setFlashMessage('success', 'Kleidungsstück erfolgreich gelöscht.');
                break;
        }
    } catch (Exception $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    
    header('Location: uniform_kleidungsstuecke.php');
    exit;
}

$kategorien = $uniformObj->getKategorien();
$kleidungsstuecke = $uniformObj->getKleidungsstuecke();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-tags"></i> Kleidungsstücke verwalten</h1>
    <a href="uniformen.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Zurück zur Übersicht
    </a>
</div>

<div class="row">
    <!-- Kategorien -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-folder"></i> Kategorien</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#neuKategorieModal">
                    <i class="bi bi-plus"></i>
                </button>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (empty($kategorien)): ?>
                <li class="list-group-item text-muted text-center">Keine Kategorien</li>
                <?php else: ?>
                <?php foreach ($kategorien as $kat): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?php echo htmlspecialchars($kat['name']); ?></strong>
                        <?php if ($kat['beschreibung']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($kat['beschreibung']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary btn-edit-kat"
                                data-id="<?php echo $kat['id']; ?>"
                                data-name="<?php echo htmlspecialchars($kat['name']); ?>"
                                data-beschreibung="<?php echo htmlspecialchars($kat['beschreibung'] ?? ''); ?>"
                                data-sortierung="<?php echo $kat['sortierung']; ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Kategorie löschen?');">
                            <input type="hidden" name="action" value="kategorie_loeschen">
                            <input type="hidden" name="id" value="<?php echo $kat['id']; ?>">
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <!-- Kleidungsstücke -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag"></i> Kleidungsstücke</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#neuKleidungModal">
                    <i class="bi bi-plus"></i> Neu
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($kleidungsstuecke)): ?>
                <p class="text-muted text-center mb-0">Keine Kleidungsstücke definiert</p>
                <?php else: ?>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Kategorie</th>
                            <th>Name</th>
                            <th>Größen</th>
                            <th class="text-end">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kleidungsstuecke as $k): ?>
                        <tr>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($k['kategorie_name'] ?? 'Ohne'); ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($k['name']); ?></strong>
                                <?php if ($k['beschreibung']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($k['beschreibung']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo htmlspecialchars($k['groessen_verfuegbar'] ?? '-'); ?></small></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-edit-ks"
                                            data-id="<?php echo $k['id']; ?>"
                                            data-kategorie="<?php echo $k['kategorie_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($k['name']); ?>"
                                            data-beschreibung="<?php echo htmlspecialchars($k['beschreibung'] ?? ''); ?>"
                                            data-groessen="<?php echo htmlspecialchars($k['groessen_verfuegbar'] ?? ''); ?>"
                                            data-sortierung="<?php echo $k['sortierung']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Kleidungsstück löschen?');">
                                        <input type="hidden" name="action" value="kleidungsstueck_loeschen">
                                        <input type="hidden" name="id" value="<?php echo $k['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Neue Kategorie -->
<div class="modal fade" id="neuKategorieModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="kategorie_erstellen">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Neue Kategorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="z.B. Festtracht, Sommertracht">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sortierung</label>
                        <input type="number" class="form-control" name="sortierung" value="100" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Kategorie bearbeiten -->
<div class="modal fade" id="editKategorieModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="kategorie_aktualisieren">
                <input type="hidden" name="id" id="edit_kat_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Kategorie bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_kat_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" id="edit_kat_beschreibung" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sortierung</label>
                        <input type="number" class="form-control" name="sortierung" id="edit_kat_sortierung" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Neues Kleidungsstück -->
<div class="modal fade" id="neuKleidungModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="kleidungsstueck_erstellen">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Neues Kleidungsstück</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategorie</label>
                        <select class="form-select" name="kategorie_id">
                            <option value="">-- Ohne Kategorie --</option>
                            <?php foreach ($kategorien as $kat): ?>
                            <option value="<?php echo $kat['id']; ?>"><?php echo htmlspecialchars($kat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="z.B. Jacke, Hose, Hut">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Verfügbare Größen</label>
                        <input type="text" class="form-control" name="groessen_verfuegbar" placeholder="z.B. S, M, L, XL">
                        <small class="text-muted">Komma-getrennt, optional</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sortierung</label>
                        <input type="number" class="form-control" name="sortierung" value="100" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Kleidungsstück bearbeiten -->
<div class="modal fade" id="editKleidungModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="kleidungsstueck_aktualisieren">
                <input type="hidden" name="id" id="edit_ks_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Kleidungsstück bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategorie</label>
                        <select class="form-select" name="kategorie_id" id="edit_ks_kategorie">
                            <option value="">-- Ohne Kategorie --</option>
                            <?php foreach ($kategorien as $kat): ?>
                            <option value="<?php echo $kat['id']; ?>"><?php echo htmlspecialchars($kat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_ks_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" id="edit_ks_beschreibung" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Verfügbare Größen</label>
                        <input type="text" class="form-control" name="groessen_verfuegbar" id="edit_ks_groessen">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sortierung</label>
                        <input type="number" class="form-control" name="sortierung" id="edit_ks_sortierung" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Kategorie bearbeiten
document.querySelectorAll('.btn-edit-kat').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_kat_id').value = this.dataset.id;
        document.getElementById('edit_kat_name').value = this.dataset.name;
        document.getElementById('edit_kat_beschreibung').value = this.dataset.beschreibung;
        document.getElementById('edit_kat_sortierung').value = this.dataset.sortierung;
        new bootstrap.Modal(document.getElementById('editKategorieModal')).show();
    });
});

// Kleidungsstück bearbeiten
document.querySelectorAll('.btn-edit-ks').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_ks_id').value = this.dataset.id;
        document.getElementById('edit_ks_kategorie').value = this.dataset.kategorie || '';
        document.getElementById('edit_ks_name').value = this.dataset.name;
        document.getElementById('edit_ks_beschreibung').value = this.dataset.beschreibung;
        document.getElementById('edit_ks_groessen').value = this.dataset.groessen;
        document.getElementById('edit_ks_sortierung').value = this.dataset.sortierung;
        new bootstrap.Modal(document.getElementById('editKleidungModal')).show();
    });
});
</script>

<?php include 'includes/footer.php'; ?>
