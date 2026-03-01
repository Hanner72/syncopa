<?php
// mitglied_bearbeiten.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

$mitgliedObj = new Mitglied();
$db = Database::getInstance();

$id = $_GET['id'] ?? null;
$isEdit = !empty($id);

if ($isEdit) {
    Session::requirePermission('mitglieder', 'schreiben');
    $mitglied = $mitgliedObj->getById($id);
    if (!$mitglied) {
        Session::setFlashMessage('danger', 'Mitglied nicht gefunden');
        header('Location: mitglieder.php');
        exit;
    }
} else {
    Session::requirePermission('mitglieder', 'schreiben');
    $mitglied = [];
}

// Register laden
$register = $db->fetchAll("SELECT * FROM register ORDER BY sortierung");

// Nummernkreis: nächste Mitgliedsnummer vorberechnen
require_once __DIR__ . '/classes/Nummernkreis.php';
$nkObj = new Nummernkreis();
$naechsteMitgliedsnummer = $nkObj->naechsteNummer('mitglieder');

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'vorname' => $_POST['vorname'],
        'nachname' => $_POST['nachname'],
        'geburtsdatum' => $_POST['geburtsdatum'] ?? null,
        'geschlecht' => $_POST['geschlecht'] ?? 'd',
        'strasse' => $_POST['strasse'] ?? null,
        'plz' => $_POST['plz'] ?? null,
        'ort' => $_POST['ort'] ?? null,
        'land' => $_POST['land'] ?? 'Österreich',
        'telefon' => $_POST['telefon'] ?? null,
        'mobil' => $_POST['mobil'] ?? null,
        'email' => $_POST['email'] ?? null,
        'register_id' => $_POST['register_id'] ?? null,
        'status' => $_POST['status'] ?? 'aktiv',
        'notizen' => $_POST['notizen'] ?? null
    ];
    
    if (!$isEdit) {
        $data['mitgliedsnummer'] = trim($_POST['mitgliedsnummer'] ?? '');
        // Leer → automatisch aus Nummernkreis
        if ($data['mitgliedsnummer'] === '') {
            $data['mitgliedsnummer'] = $nkObj->naechsteNummer('mitglieder');
        }
        $data['eintritt_datum'] = $_POST['eintritt_datum'] ?? date('Y-m-d');
    }
    
    try {
        if ($isEdit) {
            $mitgliedObj->update($id, $data);
            Session::setFlashMessage('success', 'Mitglied erfolgreich aktualisiert');
        } else {
            $id = $mitgliedObj->create($data);
            Session::setFlashMessage('success', 'Mitglied erfolgreich erstellt');
        }
        header('Location: mitglied_detail.php?id=' . $id);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-person"></i> <?php echo $isEdit ? 'Mitglied bearbeiten' : 'Neues Mitglied'; ?>
    </h1>
    <a href="mitglieder.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Zurück
    </a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <!-- Stammdaten -->
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Stammdaten</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="vorname" class="form-label">Vorname *</label>
                            <input type="text" class="form-control" id="vorname" name="vorname" 
                                   value="<?php echo htmlspecialchars($mitglied['vorname'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="nachname" class="form-label">Nachname *</label>
                            <input type="text" class="form-control" id="nachname" name="nachname" 
                                   value="<?php echo htmlspecialchars($mitglied['nachname'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <?php if (!$isEdit): ?>
                        <div class="col-md-4 mb-3">
                            <label for="mitgliedsnummer" class="form-label">Mitgliedsnummer</label>
                            <input type="text" class="form-control" id="mitgliedsnummer" name="mitgliedsnummer" 
                                   value="<?php echo htmlspecialchars($mitglied['mitgliedsnummer'] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($naechsteMitgliedsnummer); ?>">
                            <small class="text-muted">Leer lassen für automatische Vergabe (nächste: <strong><?php echo htmlspecialchars($naechsteMitgliedsnummer); ?></strong>)</small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-4 mb-3">
                            <label for="geburtsdatum" class="form-label">Geburtsdatum</label>
                            <input type="date" class="form-control" id="geburtsdatum" name="geburtsdatum" 
                                   value="<?php echo htmlspecialchars($mitglied['geburtsdatum'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="geschlecht" class="form-label">Geschlecht</label>
                            <select class="form-select" id="geschlecht" name="geschlecht">
                                <option value="d" <?php echo ($mitglied['geschlecht'] ?? 'd') === 'd' ? 'selected' : ''; ?>>Divers</option>
                                <option value="m" <?php echo ($mitglied['geschlecht'] ?? '') === 'm' ? 'selected' : ''; ?>>Männlich</option>
                                <option value="w" <?php echo ($mitglied['geschlecht'] ?? '') === 'w' ? 'selected' : ''; ?>>Weiblich</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="strasse" class="form-label">Straße</label>
                            <input type="text" class="form-control" id="strasse" name="strasse" 
                                   value="<?php echo htmlspecialchars($mitglied['strasse'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="plz" class="form-label">PLZ</label>
                            <input type="text" class="form-control" id="plz" name="plz" 
                                   value="<?php echo htmlspecialchars($mitglied['plz'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="ort" class="form-label">Ort</label>
                            <input type="text" class="form-control" id="ort" name="ort" 
                                   value="<?php echo htmlspecialchars($mitglied['ort'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="land" class="form-label">Land</label>
                            <input type="text" class="form-control" id="land" name="land" 
                                   value="<?php echo htmlspecialchars($mitglied['land'] ?? 'Österreich'); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Kontaktdaten -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Kontaktdaten</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefon" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="telefon" name="telefon" 
                                   value="<?php echo htmlspecialchars($mitglied['telefon'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="mobil" class="form-label">Mobil</label>
                            <input type="tel" class="form-control" id="mobil" name="mobil" 
                                   value="<?php echo htmlspecialchars($mitglied['mobil'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-Mail</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($mitglied['email'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Notizen -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Notizen</h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" id="notizen" name="notizen" rows="4"><?php echo htmlspecialchars($mitglied['notizen'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Vereinsdaten -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Vereinsdaten</h5>
                </div>
                <div class="card-body">
                    <?php if (!$isEdit): ?>
                    <div class="mb-3">
                        <label for="eintritt_datum" class="form-label">Eintrittsdatum</label>
                        <input type="date" class="form-control" id="eintritt_datum" name="eintritt_datum" 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="register_id" class="form-label">Register</label>
                        <select class="form-select" id="register_id" name="register_id">
                            <option value="">Kein Register</option>
                            <?php foreach ($register as $reg): ?>
                            <option value="<?php echo $reg['id']; ?>" 
                                    <?php echo ($mitglied['register_id'] ?? '') == $reg['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($reg['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="aktiv" <?php echo ($mitglied['status'] ?? 'aktiv') === 'aktiv' ? 'selected' : ''; ?>>Aktiv</option>
                            <option value="passiv" <?php echo ($mitglied['status'] ?? '') === 'passiv' ? 'selected' : ''; ?>>Passiv</option>
                            <option value="ausgetreten" <?php echo ($mitglied['status'] ?? '') === 'ausgetreten' ? 'selected' : ''; ?>>Ausgetreten</option>
                            <option value="ehrenmitglied" <?php echo ($mitglied['status'] ?? '') === 'ehrenmitglied' ? 'selected' : ''; ?>>Ehrenmitglied</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <?php if ($isEdit): ?>
            <div class="card bg-light">
                <div class="card-body">
                    <h6>Mitgliedsnummer</h6>
                    <p class="h4"><?php echo htmlspecialchars($mitglied['mitgliedsnummer']); ?></p>
                    
                    <?php if ($mitglied['eintritt_datum']): ?>
                    <hr>
                    <h6>Mitglied seit</h6>
                    <p><?php echo date('d.m.Y', strtotime($mitglied['eintritt_datum'])); ?></p>
                    <?php
                    $jahre = date_diff(date_create($mitglied['eintritt_datum']), date_create('today'))->y;
                    echo "<small class='text-muted'>{$jahre} Jahre</small>";
                    ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="d-flex justify-content-between mt-3">
        <a href="mitglieder.php" class="btn btn-secondary">
            <i class="bi bi-x"></i> Abbrechen
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> <?php echo $isEdit ? 'Aktualisieren' : 'Erstellen'; ?>
        </button>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
