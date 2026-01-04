<?php
// ausrueckung_bearbeiten.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

$ausrueckungObj = new Ausrueckung();
$notenObj = new Noten();
$db = Database::getInstance();

$id = $_GET['id'] ?? null;
$isEdit = !empty($id);

if ($isEdit) {
    Session::requirePermission('ausrueckungen', 'schreiben');
    $ausrueckung = $ausrueckungObj->getById($id);
    if (!$ausrueckung) {
        Session::setFlashMessage('danger', 'Ausrückung nicht gefunden');
        header('Location: ausrueckungen.php');
        exit;
    }
} else {
    Session::requirePermission('ausrueckungen', 'schreiben');
    $ausrueckung = [];
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'titel' => $_POST['titel'],
        'beschreibung' => $_POST['beschreibung'] ?? null,
        'typ' => $_POST['typ'],
        'start_datum' => $_POST['start_datum'],
        'ende_datum' => $_POST['ende_datum'] ?? null,
        'ganztaegig' => isset($_POST['ganztaegig']) ? 1 : 0,
        'ort' => $_POST['ort'] ?? null,
        'adresse' => $_POST['adresse'] ?? null,
        'treffpunkt' => $_POST['treffpunkt'] ?? null,
        'treffpunkt_zeit' => $_POST['treffpunkt_zeit'] ?? null,
        'uniform' => isset($_POST['uniform']) ? 1 : 0,
        'notizen' => $_POST['notizen'] ?? null,
        'status' => $_POST['status'] ?? 'geplant'
    ];
    
    try {
        if ($isEdit) {
            $ausrueckungObj->update($id, $data);
            Session::setFlashMessage('success', 'Ausrückung erfolgreich aktualisiert');
        } else {
            $id = $ausrueckungObj->create($data);
            Session::setFlashMessage('success', 'Ausrückung erfolgreich erstellt');
        }
        header('Location: ausrueckung_detail.php?id=' . $id);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-flag"></i> <?php echo $isEdit ? 'Ausrückung bearbeiten' : 'Neue Ausrückung'; ?>
    </h1>
    <a href="ausrueckungen.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Zurück
    </a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <!-- Titel -->
                <div class="col-md-8 mb-3">
                    <label for="titel" class="form-label">Titel *</label>
                    <input type="text" class="form-control" id="titel" name="titel" 
                           value="<?php echo htmlspecialchars($ausrueckung['titel'] ?? ''); ?>" required>
                </div>
                
                <!-- Typ -->
                <div class="col-md-4 mb-3">
                    <label for="typ" class="form-label">Typ *</label>
                    <select class="form-select" id="typ" name="typ" required>
                        <option value="">Bitte wählen</option>
                        <option value="Probe" <?php echo ($ausrueckung['typ'] ?? '') === 'Probe' ? 'selected' : ''; ?>>Probe</option>
                        <option value="Konzert" <?php echo ($ausrueckung['typ'] ?? '') === 'Konzert' ? 'selected' : ''; ?>>Konzert</option>
                        <option value="Ausrückung" <?php echo ($ausrueckung['typ'] ?? '') === 'Ausrückung' ? 'selected' : ''; ?>>Ausrückung</option>
                        <option value="Fest" <?php echo ($ausrueckung['typ'] ?? '') === 'Fest' ? 'selected' : ''; ?>>Fest</option>
                        <option value="Wertung" <?php echo ($ausrueckung['typ'] ?? '') === 'Wertung' ? 'selected' : ''; ?>>Wertung</option>
                        <option value="Sonstiges" <?php echo ($ausrueckung['typ'] ?? '') === 'Sonstiges' ? 'selected' : ''; ?>>Sonstiges</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <!-- Start Datum/Zeit -->
                <div class="col-md-6 mb-3">
                    <label for="start_datum" class="form-label">Start Datum/Zeit *</label>
                    <input type="datetime-local" class="form-control" id="start_datum" name="start_datum" 
                           value="<?php echo isset($ausrueckung['start_datum']) ? date('Y-m-d\TH:i', strtotime($ausrueckung['start_datum'])) : ''; ?>" required>
                </div>
                
                <!-- Ende Datum/Zeit -->
                <div class="col-md-6 mb-3">
                    <label for="ende_datum" class="form-label">Ende Datum/Zeit</label>
                    <input type="datetime-local" class="form-control" id="ende_datum" name="ende_datum" 
                           value="<?php echo isset($ausrueckung['ende_datum']) ? date('Y-m-d\TH:i', strtotime($ausrueckung['ende_datum'])) : ''; ?>">
                </div>
            </div>
            
            <div class="row">
                <!-- Status -->
                <div class="col-md-4 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="geplant" <?php echo ($ausrueckung['status'] ?? 'geplant') === 'geplant' ? 'selected' : ''; ?>>Geplant</option>
                        <option value="bestaetigt" <?php echo ($ausrueckung['status'] ?? '') === 'bestaetigt' ? 'selected' : ''; ?>>Bestätigt</option>
                        <option value="abgesagt" <?php echo ($ausrueckung['status'] ?? '') === 'abgesagt' ? 'selected' : ''; ?>>Abgesagt</option>
                    </select>
                </div>
                
                <!-- Checkboxen -->
                <div class="col-md-8 mb-3">
                    <label class="form-label d-block">Optionen</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="ganztaegig" name="ganztaegig" 
                               <?php echo ($ausrueckung['ganztaegig'] ?? 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ganztaegig">Ganztägig</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="uniform" name="uniform" 
                               <?php echo ($ausrueckung['uniform'] ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="uniform">Uniform erforderlich</label>
                    </div>
                </div>
            </div>
            
            <!-- Beschreibung -->
            <div class="mb-3">
                <label for="beschreibung" class="form-label">Beschreibung</label>
                <textarea class="form-control" id="beschreibung" name="beschreibung" rows="3"><?php echo htmlspecialchars($ausrueckung['beschreibung'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <!-- Ort -->
                <div class="col-md-6 mb-3">
                    <label for="ort" class="form-label">Ort</label>
                    <input type="text" class="form-control" id="ort" name="ort" 
                           value="<?php echo htmlspecialchars($ausrueckung['ort'] ?? ''); ?>">
                </div>
                
                <!-- Adresse -->
                <div class="col-md-6 mb-3">
                    <label for="adresse" class="form-label">Adresse</label>
                    <input type="text" class="form-control" id="adresse" name="adresse" 
                           value="<?php echo htmlspecialchars($ausrueckung['adresse'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row">
                <!-- Treffpunkt -->
                <div class="col-md-6 mb-3">
                    <label for="treffpunkt" class="form-label">Treffpunkt</label>
                    <input type="text" class="form-control" id="treffpunkt" name="treffpunkt" 
                           value="<?php echo htmlspecialchars($ausrueckung['treffpunkt'] ?? ''); ?>">
                </div>
                
                <!-- Treffpunkt Zeit -->
                <div class="col-md-6 mb-3">
                    <label for="treffpunkt_zeit" class="form-label">Treffpunkt Zeit</label>
                    <input type="time" class="form-control" id="treffpunkt_zeit" name="treffpunkt_zeit" 
                           value="<?php echo htmlspecialchars($ausrueckung['treffpunkt_zeit'] ?? ''); ?>">
                </div>
            </div>
            
            <!-- Notizen -->
            <div class="mb-3">
                <label for="notizen" class="form-label">Notizen (intern)</label>
                <textarea class="form-control" id="notizen" name="notizen" rows="3"><?php echo htmlspecialchars($ausrueckung['notizen'] ?? ''); ?></textarea>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="ausrueckungen.php" class="btn btn-secondary">
                    <i class="bi bi-x"></i> Abbrechen
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?php echo $isEdit ? 'Aktualisieren' : 'Erstellen'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Ganztägig Toggle
document.getElementById('ganztaegig').addEventListener('change', function() {
    const startInput = document.getElementById('start_datum');
    const endeInput = document.getElementById('ende_datum');
    
    if (this.checked) {
        startInput.type = 'date';
        endeInput.type = 'date';
    } else {
        startInput.type = 'datetime-local';
        endeInput.type = 'datetime-local';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
