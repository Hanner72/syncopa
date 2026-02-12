<?php
// kalender_termin_bearbeiten.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Admin oder Schreib-Berechtigung erforderlich
if (Session::getRole() !== 'admin' && !Session::checkPermission('ausrueckungen', 'schreiben')) {
    Session::setFlashMessage('danger', 'Keine Berechtigung');
    header('Location: kalender.php');
    exit;
}

$db = Database::getInstance();
$id = $_GET['id'] ?? null;

if (!$id) {
    Session::setFlashMessage('danger', 'Keine ID angegeben');
    header('Location: kalender.php');
    exit;
}

$termin = $db->fetchOne("SELECT * FROM kalender_termine WHERE id = ?", [$id]);
if (!$termin) {
    Session::setFlashMessage('danger', 'Termin nicht gefunden');
    header('Location: kalender.php');
    exit;
}

// Speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'titel' => $_POST['titel'],
            'typ' => $_POST['typ'],
            'start_datum' => $_POST['start_datum'],
            'ende_datum' => $_POST['ende_datum'] ?: null,
            'ganztaegig' => isset($_POST['ganztaegig']) ? 1 : 0,
            'ort' => $_POST['ort'] ?: null,
            'beschreibung' => $_POST['beschreibung'] ?: null,
            'farbe' => $_POST['farbe'] ?: '#6c757d'
        ];
        
        $db->execute("UPDATE kalender_termine SET 
            titel = ?, typ = ?, start_datum = ?, ende_datum = ?, 
            ganztaegig = ?, ort = ?, beschreibung = ?, farbe = ?
            WHERE id = ?", [
            $data['titel'], $data['typ'], $data['start_datum'], $data['ende_datum'],
            $data['ganztaegig'], $data['ort'], $data['beschreibung'], $data['farbe'], $id
        ]);
        
        Session::setFlashMessage('success', 'Termin aktualisiert');
        header('Location: kalender.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-calendar-event"></i> Termin bearbeiten</h1>
    <a href="kalender.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Zurück
    </a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="titel" class="form-label">Titel *</label>
                    <input type="text" class="form-control" id="titel" name="titel" 
                           value="<?php echo htmlspecialchars($termin['titel']); ?>" required>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="typ" class="form-label">Typ</label>
                    <select class="form-select" id="typ" name="typ">
                        <?php
                        $typen = ['Termin', 'Besprechung', 'Geburtstag', 'Feiertag', 'Reminder', 'Sonstiges'];
                        foreach ($typen as $t):
                        ?>
                        <option value="<?php echo $t; ?>" <?php echo $termin['typ'] === $t ? 'selected' : ''; ?>>
                            <?php echo $t; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label for="start_datum" class="form-label">Start *</label>
                    <input type="<?php echo $termin['ganztaegig'] ? 'date' : 'datetime-local'; ?>" 
                           class="form-control" id="start_datum" name="start_datum" 
                           value="<?php echo $termin['ganztaegig'] ? substr($termin['start_datum'], 0, 10) : date('Y-m-d\TH:i', strtotime($termin['start_datum'])); ?>" required>
                </div>
                
                <div class="col-md-5 mb-3">
                    <label for="ende_datum" class="form-label">Ende</label>
                    <input type="<?php echo $termin['ganztaegig'] ? 'date' : 'datetime-local'; ?>" 
                           class="form-control" id="ende_datum" name="ende_datum" 
                           value="<?php echo $termin['ende_datum'] ? ($termin['ganztaegig'] ? substr($termin['ende_datum'], 0, 10) : date('Y-m-d\TH:i', strtotime($termin['ende_datum']))) : ''; ?>">
                </div>
                
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="ganztaegig" name="ganztaegig" 
                               <?php echo $termin['ganztaegig'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ganztaegig">Ganztägig</label>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="ort" class="form-label">Ort</label>
                    <input type="text" class="form-control" id="ort" name="ort" 
                           value="<?php echo htmlspecialchars($termin['ort'] ?? ''); ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="farbe" class="form-label">Farbe</label>
                    <select class="form-select" id="farbe" name="farbe">
                        <?php
                        $farben = [
                            '#6c757d' => 'Grau',
                            '#0d6efd' => 'Blau',
                            '#198754' => 'Grün',
                            '#ffc107' => 'Gelb',
                            '#dc3545' => 'Rot',
                            '#6f42c1' => 'Lila',
                            '#17a2b8' => 'Cyan'
                        ];
                        foreach ($farben as $hex => $name):
                        ?>
                        <option value="<?php echo $hex; ?>" <?php echo ($termin['farbe'] ?? '#6c757d') === $hex ? 'selected' : ''; ?>
                                style="background: <?php echo $hex; ?>; color: <?php echo in_array($hex, ['#ffc107']) ? 'black' : 'white'; ?>">
                            <?php echo $name; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="beschreibung" class="form-label">Beschreibung</label>
                <textarea class="form-control" id="beschreibung" name="beschreibung" rows="3"><?php echo htmlspecialchars($termin['beschreibung'] ?? ''); ?></textarea>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="kalender.php" class="btn btn-secondary">Abbrechen</a>
                <div>
                    <?php if (Session::getRole() === 'admin' || Session::checkPermission('ausrueckungen', 'loeschen')): ?>
                    <a href="kalender_loeschen.php?id=<?php echo $id; ?>" class="btn btn-danger" 
                       onclick="return confirm('Termin wirklich löschen?')">
                        <i class="bi bi-trash"></i> Löschen
                    </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Speichern
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('ganztaegig').addEventListener('change', function() {
    const startInput = document.getElementById('start_datum');
    const endeInput = document.getElementById('ende_datum');
    
    if (this.checked) {
        startInput.type = 'date';
        endeInput.type = 'date';
        // Nur Datum behalten
        if (startInput.value.includes('T')) startInput.value = startInput.value.split('T')[0];
        if (endeInput.value.includes('T')) endeInput.value = endeInput.value.split('T')[0];
    } else {
        startInput.type = 'datetime-local';
        endeInput.type = 'datetime-local';
        // Zeit hinzufügen wenn nur Datum
        if (startInput.value && !startInput.value.includes('T')) startInput.value += 'T00:00';
        if (endeInput.value && !endeInput.value.includes('T')) endeInput.value += 'T00:00';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
