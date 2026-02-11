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
$zuweisungen = $uniformObj->getZuweisungenByMitglied($mitgliedId);
$verfuegbar = $uniformObj->getVerfuegbareKleidungsstuecke($mitgliedId);

// Zuweisung hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    Session::requirePermission('uniformen', 'schreiben');
    
    try {
        if ($_POST['action'] === 'zuweisen') {
            $data = [
                'groesse' => trim($_POST['groesse'] ?? '') ?: null,
                'zustand' => $_POST['zustand'] ?? 'gut',
                'ausgabe_datum' => $_POST['ausgabe_datum'] ?: date('Y-m-d'),
                'bemerkungen' => trim($_POST['bemerkungen'] ?? '') ?: null
            ];
            $uniformObj->zuweisen($mitgliedId, $_POST['kleidungsstueck_id'], $data);
            Session::setFlashMessage('success', 'Kleidungsstück erfolgreich zugewiesen.');
            
        } elseif ($_POST['action'] === 'entfernen') {
            Session::requirePermission('uniformen', 'loeschen');
            $uniformObj->zuweisungEntfernen($_POST['zuweisung_id']);
            Session::setFlashMessage('success', 'Zuweisung erfolgreich entfernt.');
            
        } elseif ($_POST['action'] === 'aktualisieren') {
            $data = [
                'groesse' => trim($_POST['groesse'] ?? '') ?: null,
                'zustand' => $_POST['zustand'] ?? 'gut',
                'bemerkungen' => trim($_POST['bemerkungen'] ?? '') ?: null
            ];
            $uniformObj->updateZuweisung($_POST['zuweisung_id'], $data);
            Session::setFlashMessage('success', 'Zuweisung erfolgreich aktualisiert.');
        }
    } catch (Exception $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    
    header('Location: uniform_mitglied.php?id=' . $mitgliedId);
    exit;
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
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
    <!-- Zugewiesene Kleidungsstücke -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-check-circle text-success"></i> Zugewiesene Kleidungsstücke</h5>
                <span class="badge bg-success"><?php echo count($zuweisungen); ?> Teile</span>
            </div>
            <div class="card-body">
                <?php if (empty($zuweisungen)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mb-0">Noch keine Kleidungsstücke zugewiesen</p>
                </div>
                <?php else: ?>
                <table class="table table-hover">
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
                                <?php if ($z['bemerkungen']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($z['bemerkungen']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($z['groesse'] ?? '-'); ?></strong></td>
                            <td>
                                <?php
                                $zustandColors = ['sehr gut' => 'success', 'gut' => 'info', 'befriedigend' => 'warning', 'schlecht' => 'danger'];
                                $color = $zustandColors[$z['zustand']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($z['zustand'] ?? 'gut'); ?></span>
                            </td>
                            <td>
                                <small><?php echo $z['ausgabe_datum'] ? date('d.m.Y', strtotime($z['ausgabe_datum'])) : '-'; ?></small>
                            </td>
                            <td class="text-end">
                                <?php if (Session::checkPermission('uniformen', 'schreiben')): ?>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-edit-zuweisung"
                                            data-id="<?php echo $z['id']; ?>"
                                            data-groesse="<?php echo htmlspecialchars($z['groesse'] ?? ''); ?>"
                                            data-zustand="<?php echo htmlspecialchars($z['zustand'] ?? 'gut'); ?>"
                                            data-bemerkungen="<?php echo htmlspecialchars($z['bemerkungen'] ?? ''); ?>"
                                            data-name="<?php echo htmlspecialchars($z['kleidungsstueck_name']); ?>"
                                            title="Bearbeiten">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if (Session::checkPermission('uniformen', 'loeschen')): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Zuweisung wirklich entfernen?');">
                                        <input type="hidden" name="action" value="entfernen">
                                        <input type="hidden" name="zuweisung_id" value="<?php echo $z['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger" title="Entfernen">
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
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Seitenleiste: Neues Kleidungsstück zuweisen -->
    <div class="col-lg-4">
        <?php if (Session::checkPermission('uniformen', 'schreiben')): ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Kleidungsstück zuweisen</h5>
            </div>
            <div class="card-body">
                <?php if (empty($verfuegbar)): ?>
                <p class="text-muted text-center mb-0">
                    Alle Kleidungsstücke bereits zugewiesen oder keine definiert.
                    <br><br>
                    <a href="uniform_kleidungsstuecke.php" class="btn btn-sm btn-outline-primary">
                        Kleidungsstücke verwalten
                    </a>
                </p>
                <?php else: ?>
                <form method="POST">
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
                                <option value="<?php echo $k['id']; ?>" data-groessen="<?php echo htmlspecialchars($k['groessen_verfuegbar'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($k['name']); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($aktuelleKategorie !== '') echo '</optgroup>'; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="groesse" class="form-label">Größe</label>
                        <input type="text" class="form-control" name="groesse" id="groesse" 
                               placeholder="z.B. M, L, 50" list="groessenList">
                        <datalist id="groessenList">
                            <option value="XS">
                            <option value="S">
                            <option value="M">
                            <option value="L">
                            <option value="XL">
                            <option value="XXL">
                        </datalist>
                    </div>
                    
                    <div class="mb-3">
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
                        <i class="bi bi-plus-circle"></i> Zuweisen
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
                        <td>Status:</td>
                        <td><span class="badge bg-<?php echo $mitglied['status'] === 'aktiv' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($mitglied['status']); ?>
                        </span></td>
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
                    <div class="mb-3">
                        <label for="edit_groesse" class="form-label">Größe</label>
                        <input type="text" class="form-control" name="groesse" id="edit_groesse">
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

<script>
document.querySelectorAll('.btn-edit-zuweisung').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_zuweisung_id').value = this.dataset.id;
        document.getElementById('edit_name').textContent = this.dataset.name;
        document.getElementById('edit_groesse').value = this.dataset.groesse;
        document.getElementById('edit_zustand').value = this.dataset.zustand;
        document.getElementById('edit_bemerkungen').value = this.dataset.bemerkungen;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});
</script>

<?php include 'includes/footer.php'; ?>
