<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'schreiben');

$uniformObj = new Uniform();

// Kategorie speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                'sortierung' => (int)($_POST['sortierung'] ?? 100)
            ];
            
            if (empty($data['name'])) {
                throw new Exception('Bitte einen Namen eingeben.');
            }
            
            $uniformObj->createKategorie($data);
            Session::setFlashMessage('success', 'Kategorie erfolgreich angelegt.');
            
        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? null;
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                'sortierung' => (int)($_POST['sortierung'] ?? 100)
            ];
            
            if (empty($data['name'])) {
                throw new Exception('Bitte einen Namen eingeben.');
            }
            
            $uniformObj->updateKategorie($id, $data);
            Session::setFlashMessage('success', 'Kategorie erfolgreich aktualisiert.');
            
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? null;
            $uniformObj->deleteKategorie($id);
            Session::setFlashMessage('success', 'Kategorie erfolgreich gelöscht.');
        }
    } catch (Exception $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    
    header('Location: uniform_kategorien.php');
    exit;
}

$kategorien = $uniformObj->getKategorien();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-tags"></i> Uniform-Kategorien</h1>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#neueKategorieModal">
            <i class="bi bi-plus-circle"></i> Neue Kategorie
        </button>
        <a href="uniformen.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Zurück
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th width="50">Sort.</th>
                    <th>Name</th>
                    <th>Beschreibung</th>
                    <th class="text-center">Uniformteile</th>
                    <th class="text-center">Ausgegeben</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kategorien as $kat): ?>
                <tr>
                    <td><span class="badge bg-secondary"><?php echo $kat['sortierung']; ?></span></td>
                    <td><strong><?php echo htmlspecialchars($kat['name']); ?></strong></td>
                    <td><small class="text-muted"><?php echo htmlspecialchars($kat['beschreibung'] ?? '-'); ?></small></td>
                    <td class="text-center">
                        <span class="badge bg-primary"><?php echo $kat['anzahl_teile']; ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($kat['anzahl_ausgegeben'] > 0): ?>
                        <span class="badge bg-warning text-dark"><?php echo $kat['anzahl_ausgegeben']; ?></span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit" 
                                data-id="<?php echo $kat['id']; ?>"
                                data-name="<?php echo htmlspecialchars($kat['name']); ?>"
                                data-beschreibung="<?php echo htmlspecialchars($kat['beschreibung'] ?? ''); ?>"
                                data-sortierung="<?php echo $kat['sortierung']; ?>"
                                title="Bearbeiten">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if ($kat['anzahl_teile'] == 0): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Kategorie wirklich löschen?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $kat['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Löschen">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($kategorien)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        Noch keine Kategorien angelegt
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Neue Kategorie -->
<div class="modal fade" id="neueKategorieModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Neue Kategorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required
                               placeholder="z.B. Tracht, Festuniform, Regenbekleidung">
                    </div>
                    <div class="mb-3">
                        <label for="beschreibung" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="beschreibung" name="beschreibung" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="sortierung" class="form-label">Sortierung</label>
                        <input type="number" class="form-control" id="sortierung" name="sortierung" value="100" min="1">
                        <small class="text-muted">Kleinere Zahlen werden zuerst angezeigt</small>
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

<!-- Modal: Kategorie bearbeiten -->
<div class="modal fade" id="bearbeitenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Kategorie bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_beschreibung" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="edit_beschreibung" name="beschreibung" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_sortierung" class="form-label">Sortierung</label>
                        <input type="number" class="form-control" id="edit_sortierung" name="sortierung" min="1">
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

<script>
document.querySelectorAll('.btn-edit').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_name').value = this.dataset.name;
        document.getElementById('edit_beschreibung').value = this.dataset.beschreibung;
        document.getElementById('edit_sortierung').value = this.dataset.sortierung;
        new bootstrap.Modal(document.getElementById('bearbeitenModal')).show();
    });
});
</script>

<?php include 'includes/footer.php'; ?>
