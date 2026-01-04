<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

$notenObj = new Noten();
$id = $_GET['id'] ?? null;
$isEdit = !empty($id);

if ($isEdit) {
    Session::requirePermission('noten', 'schreiben');
    $note = $notenObj->getById($id);
    if (!$note) {
        Session::setFlashMessage('danger', 'Noten nicht gefunden');
        header('Location: noten.php');
        exit;
    }
} else {
    Session::requirePermission('noten', 'schreiben');
    $note = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'titel' => $_POST['titel'],
        'untertitel' => $_POST['untertitel'] ?? null,
        'komponist' => $_POST['komponist'] ?? null,
        'arrangeur' => $_POST['arrangeur'] ?? null,
        'verlag' => $_POST['verlag'] ?? null,
        'besetzung' => $_POST['besetzung'] ?? null,
        'schwierigkeitsgrad' => $_POST['schwierigkeitsgrad'] ?? '3',
        'dauer_minuten' => $_POST['dauer_minuten'] ?? null,
        'genre' => $_POST['genre'] ?? null,
        'anzahl_stimmen' => $_POST['anzahl_stimmen'] ?? null,
        'zustand' => $_POST['zustand'] ?? 'gut',
        'bemerkungen' => $_POST['bemerkungen'] ?? null,
        'standort' => $_POST['standort'] ?? null
    ];
    
    if (!$isEdit) {
        $data['archiv_nummer'] = $_POST['archiv_nummer'] ?? null;
    }
    
    try {
        if ($isEdit) {
            $notenObj->update($id, $data);
            Session::setFlashMessage('success', 'Noten aktualisiert');
        } else {
            $id = $notenObj->create($data);
            Session::setFlashMessage('success', 'Noten erstellt');
        }
        header('Location: noten.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-music-note-list"></i> <?php echo $isEdit ? 'Noten bearbeiten' : 'Neue Noten'; ?></h1>
    <a href="noten.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="titel" class="form-label">Titel *</label>
                    <input type="text" class="form-control" id="titel" name="titel" 
                           value="<?php echo htmlspecialchars($note['titel'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="archiv_nummer" class="form-label">Archiv-Nr.</label>
                    <input type="text" class="form-control" id="archiv_nummer" name="archiv_nummer" 
                           value="<?php echo htmlspecialchars($note['archiv_nummer'] ?? ''); ?>"
                           placeholder="Automatisch" <?php echo $isEdit ? 'readonly' : ''; ?>>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="untertitel" class="form-label">Untertitel</label>
                <input type="text" class="form-control" id="untertitel" name="untertitel" 
                       value="<?php echo htmlspecialchars($note['untertitel'] ?? ''); ?>">
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="komponist" class="form-label">Komponist</label>
                    <input type="text" class="form-control" id="komponist" name="komponist" 
                           value="<?php echo htmlspecialchars($note['komponist'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="arrangeur" class="form-label">Arrangeur</label>
                    <input type="text" class="form-control" id="arrangeur" name="arrangeur" 
                           value="<?php echo htmlspecialchars($note['arrangeur'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="verlag" class="form-label">Verlag</label>
                    <input type="text" class="form-control" id="verlag" name="verlag" 
                           value="<?php echo htmlspecialchars($note['verlag'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="genre" class="form-label">Genre</label>
                    <input type="text" class="form-control" id="genre" name="genre" 
                           value="<?php echo htmlspecialchars($note['genre'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="besetzung" class="form-label">Besetzung</label>
                    <input type="text" class="form-control" id="besetzung" name="besetzung" 
                           value="<?php echo htmlspecialchars($note['besetzung'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="schwierigkeitsgrad" class="form-label">Schwierigkeitsgrad</label>
                    <select class="form-select" id="schwierigkeitsgrad" name="schwierigkeitsgrad">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($note['schwierigkeitsgrad'] ?? '3') == $i ? 'selected' : ''; ?>>
                            Grad <?php echo $i; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="dauer_minuten" class="form-label">Dauer (Min.)</label>
                    <input type="number" class="form-control" id="dauer_minuten" name="dauer_minuten" 
                           value="<?php echo htmlspecialchars($note['dauer_minuten'] ?? ''); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="anzahl_stimmen" class="form-label">Anzahl Stimmen</label>
                    <input type="number" class="form-control" id="anzahl_stimmen" name="anzahl_stimmen" 
                           value="<?php echo htmlspecialchars($note['anzahl_stimmen'] ?? ''); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="zustand" class="form-label">Zustand</label>
                    <select class="form-select" id="zustand" name="zustand">
                        <option value="sehr gut" <?php echo ($note['zustand'] ?? 'gut') === 'sehr gut' ? 'selected' : ''; ?>>Sehr gut</option>
                        <option value="gut" <?php echo ($note['zustand'] ?? 'gut') === 'gut' ? 'selected' : ''; ?>>Gut</option>
                        <option value="befriedigend" <?php echo ($note['zustand'] ?? '') === 'befriedigend' ? 'selected' : ''; ?>>Befriedigend</option>
                        <option value="schlecht" <?php echo ($note['zustand'] ?? '') === 'schlecht' ? 'selected' : ''; ?>>Schlecht</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="standort" class="form-label">Standort</label>
                <input type="text" class="form-control" id="standort" name="standort" 
                       value="<?php echo htmlspecialchars($note['standort'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="bemerkungen" class="form-label">Bemerkungen</label>
                <textarea class="form-control" id="bemerkungen" name="bemerkungen" rows="3"><?php echo htmlspecialchars($note['bemerkungen'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-between">
        <a href="noten.php" class="btn btn-secondary"><i class="bi bi-x"></i> Abbrechen</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> <?php echo $isEdit ? 'Aktualisieren' : 'Erstellen'; ?>
        </button>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
