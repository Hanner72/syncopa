<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'schreiben');

$uniformObj = new Uniform();
$id = $_GET['id'] ?? null;
$isEdit = !empty($id);

if ($isEdit) {
    $uniform = $uniformObj->getById($id);
    if (!$uniform) {
        Session::setFlashMessage('danger', 'Uniformteil nicht gefunden');
        header('Location: uniformen.php');
        exit;
    }
} else {
    $uniform = [];
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'kategorie_id' => $_POST['kategorie_id'] ?? null,
        'bezeichnung' => trim($_POST['bezeichnung'] ?? '') ?: null,
        'groesse' => trim($_POST['groesse'] ?? '') ?: null,
        'farbe' => trim($_POST['farbe'] ?? '') ?: null,
        'anschaffungsdatum' => $_POST['anschaffungsdatum'] ?: null,
        'anschaffungspreis' => !empty($_POST['anschaffungspreis']) ? (float)$_POST['anschaffungspreis'] : null,
        'zustand' => $_POST['zustand'] ?? 'gut',
        'standort' => trim($_POST['standort'] ?? '') ?: null,
        'notizen' => trim($_POST['notizen'] ?? '') ?: null
    ];
    
    if (!$isEdit) {
        $data['inventar_nummer'] = trim($_POST['inventar_nummer'] ?? '') ?: null;
    }
    
    // Validierung
    if (empty($data['kategorie_id'])) {
        $error = 'Bitte eine Kategorie auswählen.';
    } else {
        try {
            if ($isEdit) {
                $uniformObj->update($id, $data);
                Session::setFlashMessage('success', 'Uniformteil erfolgreich aktualisiert.');
            } else {
                $id = $uniformObj->create($data);
                Session::setFlashMessage('success', 'Uniformteil erfolgreich angelegt.');
            }
            
            header('Location: uniform_detail.php?id=' . $id);
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$kategorien = $uniformObj->getKategorien();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-person-badge"></i> 
        <?php echo $isEdit ? 'Uniformteil bearbeiten' : 'Neues Uniformteil anlegen'; ?>
    </h1>
    <a href="uniformen.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Stammdaten</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="kategorie_id" class="form-label">Kategorie <span class="text-danger">*</span></label>
                            <select class="form-select" id="kategorie_id" name="kategorie_id" required>
                                <option value="">-- Kategorie wählen --</option>
                                <?php foreach ($kategorien as $kat): ?>
                                <option value="<?php echo $kat['id']; ?>" <?php echo ($uniform['kategorie_id'] ?? '') == $kat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="inventar_nummer" class="form-label">Inventar-Nr.</label>
                            <input type="text" class="form-control" id="inventar_nummer" name="inventar_nummer" 
                                   value="<?php echo htmlspecialchars($uniform['inventar_nummer'] ?? ''); ?>"
                                   placeholder="Wird automatisch generiert" <?php echo $isEdit ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bezeichnung" class="form-label">Bezeichnung</label>
                        <input type="text" class="form-control" id="bezeichnung" name="bezeichnung" 
                               value="<?php echo htmlspecialchars($uniform['bezeichnung'] ?? ''); ?>"
                               placeholder="z.B. Trachtenjacke Herren, Hut mit Gamsbart">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="groesse" class="form-label">Größe</label>
                            <input type="text" class="form-control" id="groesse" name="groesse" 
                                   value="<?php echo htmlspecialchars($uniform['groesse'] ?? ''); ?>"
                                   placeholder="z.B. M, L, XL, 50, 52"
                                   list="groessenList">
                            <datalist id="groessenList">
                                <option value="XS">
                                <option value="S">
                                <option value="M">
                                <option value="L">
                                <option value="XL">
                                <option value="XXL">
                                <option value="36">
                                <option value="38">
                                <option value="40">
                                <option value="42">
                                <option value="44">
                                <option value="46">
                                <option value="48">
                                <option value="50">
                                <option value="52">
                                <option value="54">
                                <option value="56">
                            </datalist>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="farbe" class="form-label">Farbe</label>
                            <input type="text" class="form-control" id="farbe" name="farbe" 
                                   value="<?php echo htmlspecialchars($uniform['farbe'] ?? ''); ?>"
                                   placeholder="z.B. Grün, Schwarz">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="zustand" class="form-label">Zustand</label>
                            <select class="form-select" id="zustand" name="zustand">
                                <option value="sehr gut" <?php echo ($uniform['zustand'] ?? 'gut') === 'sehr gut' ? 'selected' : ''; ?>>Sehr gut</option>
                                <option value="gut" <?php echo ($uniform['zustand'] ?? 'gut') === 'gut' ? 'selected' : ''; ?>>Gut</option>
                                <option value="befriedigend" <?php echo ($uniform['zustand'] ?? '') === 'befriedigend' ? 'selected' : ''; ?>>Befriedigend</option>
                                <option value="schlecht" <?php echo ($uniform['zustand'] ?? '') === 'schlecht' ? 'selected' : ''; ?>>Schlecht</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="anschaffungsdatum" class="form-label">Anschaffungsdatum</label>
                            <input type="date" class="form-control" id="anschaffungsdatum" name="anschaffungsdatum" 
                                   value="<?php echo htmlspecialchars($uniform['anschaffungsdatum'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="anschaffungspreis" class="form-label">Anschaffungspreis (€)</label>
                            <input type="number" class="form-control" id="anschaffungspreis" name="anschaffungspreis" 
                                   value="<?php echo htmlspecialchars($uniform['anschaffungspreis'] ?? ''); ?>"
                                   step="0.01" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="standort" class="form-label">Standort/Lagerplatz</label>
                            <input type="text" class="form-control" id="standort" name="standort" 
                                   value="<?php echo htmlspecialchars($uniform['standort'] ?? ''); ?>"
                                   placeholder="z.B. Schrank A, Regal 2">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notizen" class="form-label">Notizen</label>
                        <textarea class="form-control" id="notizen" name="notizen" rows="3"
                                  placeholder="Zusätzliche Informationen..."><?php echo htmlspecialchars($uniform['notizen'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between">
                        <a href="uniformen.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Abbrechen
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Speichern
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Seitenleiste -->
        <div class="col-lg-4">
            <?php if ($isEdit && $uniform['mitglied_id']): ?>
            <div class="card mb-3">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="bi bi-person"></i> Aktuell ausgegeben an</h5>
                </div>
                <div class="card-body text-center">
                    <h5><?php echo htmlspecialchars($uniform['vorname'] . ' ' . $uniform['nachname']); ?></h5>
                    <p class="text-muted mb-0">seit <?php echo date('d.m.Y', strtotime($uniform['ausgabe_datum'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card bg-light">
                <div class="card-body">
                    <h6><i class="bi bi-lightbulb text-warning"></i> Tipps</h6>
                    <ul class="small mb-0">
                        <li>Die Inventar-Nr. wird automatisch generiert</li>
                        <li>Tragen Sie die Größe ein, um später passende Teile zu finden</li>
                        <li>Der Standort hilft beim Auffinden des Teils</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
