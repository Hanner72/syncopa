<?php
// instrument_bearbeiten.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

$instrumentObj = new Instrument();
$db = Database::getInstance();

$id = $_GET['id'] ?? null;
$isEdit = !empty($id);

if ($isEdit) {
    Session::requirePermission('instrumente', 'schreiben');
    $instrument = $instrumentObj->getById($id);
    if (!$instrument) {
        Session::setFlashMessage('danger', 'Instrument nicht gefunden');
        header('Location: instrumente.php');
        exit;
    }
} else {
    Session::requirePermission('instrumente', 'schreiben');
    $instrument = [];
}

// Instrumententypen und Mitglieder laden
$typen = $instrumentObj->getTypen();
$mitglieder = $db->fetchAll("SELECT id, mitgliedsnummer, vorname, nachname FROM mitglieder WHERE status = 'aktiv' ORDER BY nachname, vorname");

// Nummernkreis
require_once __DIR__ . '/classes/Nummernkreis.php';
$nkObj = new Nummernkreis();
$naechsteInventarNummer = $nkObj->naechsteNummer('instrumente');

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventarNr = trim($_POST['inventar_nummer'] ?? '');
    $data = [
        'inventar_nummer'  => $inventarNr !== '' ? $inventarNr : $nkObj->naechsteNummer('instrumente'),
        'instrument_typ_id' => $_POST['instrument_typ_id'],
        'hersteller' => $_POST['hersteller'] ?? null,
        'modell' => $_POST['modell'] ?? null,
        'seriennummer' => $_POST['seriennummer'] ?? null,
        'baujahr' => $_POST['baujahr'] ?? null,
        'anschaffungsdatum' => $_POST['anschaffungsdatum'] ?? null,
        'anschaffungspreis' => $_POST['anschaffungspreis'] ?? null,
        'zustand' => $_POST['zustand'] ?? 'gut',
        'standort' => $_POST['standort'] ?? null,
        'versicherungswert' => $_POST['versicherungswert'] ?? null,
        'notizen' => $_POST['notizen'] ?? null
    ];
    
    try {
        if ($isEdit) {
            $instrumentObj->update($id, $data);
            
            // Verleihstatus ändern
            if (!empty($_POST['mitglied_id'])) {
                $instrumentObj->ausleihen($id, $_POST['mitglied_id']);
            } else {
                $instrumentObj->zurueckgeben($id);
            }
            
            Session::setFlashMessage('success', 'Instrument erfolgreich aktualisiert');
        } else {
            $id = $instrumentObj->create($data);
            
            // Direkt ausleihen falls Mitglied ausgewählt
            if (!empty($_POST['mitglied_id'])) {
                $instrumentObj->ausleihen($id, $_POST['mitglied_id']);
            }
            
            Session::setFlashMessage('success', 'Instrument erfolgreich erstellt');
        }
        header('Location: instrument_detail.php?id=' . $id);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-diagram-3"></i> <?php echo $isEdit ? 'Instrument bearbeiten' : 'Neues Instrument'; ?>
    </h1>
    <a href="instrumente.php" class="btn btn-secondary">
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
                            <label for="inventar_nummer" class="form-label">Inventarnummer<?php echo $isEdit ? ' *' : ''; ?></label>
                            <input type="text" class="form-control" id="inventar_nummer" name="inventar_nummer" 
                                   value="<?php echo htmlspecialchars($instrument['inventar_nummer'] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($naechsteInventarNummer); ?>"
                                   <?php echo $isEdit ? 'required' : ''; ?>>
                            <?php if (!$isEdit): ?>
                            <small class="text-muted">Leer lassen für automatische Vergabe (nächste: <strong><?php echo htmlspecialchars($naechsteInventarNummer); ?></strong>)</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="instrument_typ_id" class="form-label">Instrumententyp *</label>
                            <select class="form-select" id="instrument_typ_id" name="instrument_typ_id" required>
                                <option value="">Bitte wählen</option>
                                <?php 
                                $currentRegister = null;
                                foreach ($typen as $typ): 
                                    if ($currentRegister !== $typ['register_name']):
                                        if ($currentRegister !== null) echo '</optgroup>';
                                        $currentRegister = $typ['register_name'];
                                        echo '<optgroup label="' . htmlspecialchars($typ['register_name'] ?? 'Ohne Register') . '">';
                                    endif;
                                ?>
                                <option value="<?php echo $typ['id']; ?>" 
                                        <?php echo ($instrument['instrument_typ_id'] ?? '') == $typ['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($typ['name']); ?>
                                </option>
                                <?php endforeach; ?>
                                <?php if ($currentRegister !== null) echo '</optgroup>'; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hersteller" class="form-label">Hersteller</label>
                            <input type="text" class="form-control" id="hersteller" name="hersteller" 
                                   value="<?php echo htmlspecialchars($instrument['hersteller'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="modell" class="form-label">Modell</label>
                            <input type="text" class="form-control" id="modell" name="modell" 
                                   value="<?php echo htmlspecialchars($instrument['modell'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="seriennummer" class="form-label">Seriennummer</label>
                            <input type="text" class="form-control" id="seriennummer" name="seriennummer" 
                                   value="<?php echo htmlspecialchars($instrument['seriennummer'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="baujahr" class="form-label">Baujahr</label>
                            <input type="number" class="form-control" id="baujahr" name="baujahr" 
                                   min="1800" max="<?php echo date('Y'); ?>"
                                   value="<?php echo htmlspecialchars($instrument['baujahr'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="anschaffungsdatum" class="form-label">Anschaffungsdatum</label>
                            <input type="date" class="form-control" id="anschaffungsdatum" name="anschaffungsdatum" 
                                   value="<?php echo htmlspecialchars($instrument['anschaffungsdatum'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="anschaffungspreis" class="form-label">Anschaffungspreis (€)</label>
                            <input type="number" class="form-control" id="anschaffungspreis" name="anschaffungspreis" 
                                   step="0.01" min="0"
                                   value="<?php echo htmlspecialchars($instrument['anschaffungspreis'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="versicherungswert" class="form-label">Versicherungswert (€)</label>
                            <input type="number" class="form-control" id="versicherungswert" name="versicherungswert" 
                                   step="0.01" min="0"
                                   value="<?php echo htmlspecialchars($instrument['versicherungswert'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="standort" class="form-label">Standort</label>
                            <input type="text" class="form-control" id="standort" name="standort" 
                                   value="<?php echo htmlspecialchars($instrument['standort'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="zustand" class="form-label">Zustand</label>
                        <select class="form-select" id="zustand" name="zustand">
                            <option value="sehr gut" <?php echo ($instrument['zustand'] ?? 'gut') === 'sehr gut' ? 'selected' : ''; ?>>Sehr gut</option>
                            <option value="gut" <?php echo ($instrument['zustand'] ?? 'gut') === 'gut' ? 'selected' : ''; ?>>Gut</option>
                            <option value="befriedigend" <?php echo ($instrument['zustand'] ?? '') === 'befriedigend' ? 'selected' : ''; ?>>Befriedigend</option>
                            <option value="schlecht" <?php echo ($instrument['zustand'] ?? '') === 'schlecht' ? 'selected' : ''; ?>>Schlecht</option>
                            <option value="defekt" <?php echo ($instrument['zustand'] ?? '') === 'defekt' ? 'selected' : ''; ?>>Defekt</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notizen" class="form-label">Notizen</label>
                        <textarea class="form-control" id="notizen" name="notizen" rows="3"><?php echo htmlspecialchars($instrument['notizen'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Verleihstatus -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Verleihstatus</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="mitglied_id" class="form-label">Ausgeliehen an</label>
                        <select class="form-select" id="mitglied_id" name="mitglied_id">
                            <option value="">Nicht ausgeliehen</option>
                            <?php foreach ($mitglieder as $m): ?>
                            <option value="<?php echo $m['id']; ?>" 
                                    <?php echo ($instrument['mitglied_id'] ?? '') == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['nachname'] . ' ' . $m['vorname'] . ' (' . $m['mitgliedsnummer'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($isEdit && $instrument['ausgeliehen_seit']): ?>
                    <div class="alert alert-info">
                        <small>
                            <i class="bi bi-info-circle"></i>
                            Ausgeliehen seit: <?php echo date('d.m.Y', strtotime($instrument['ausgeliehen_seit'])); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($isEdit): ?>
            <div class="card bg-light">
                <div class="card-body">
                    <h6>Inventarnummer</h6>
                    <p class="h4"><?php echo htmlspecialchars($instrument['inventar_nummer']); ?></p>
                    
                    <hr>
                    
                    <h6>Status</h6>
                    <p>
                        <?php if ($instrument['mitglied_id']): ?>
                        <span class="badge bg-warning">Ausgeliehen</span>
                        <?php else: ?>
                        <span class="badge bg-success">Verfügbar</span>
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($instrument['anschaffungsdatum']): ?>
                    <hr>
                    <h6>Angeschafft am</h6>
                    <p><?php echo date('d.m.Y', strtotime($instrument['anschaffungsdatum'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="d-flex justify-content-between mt-3">
        <a href="instrumente.php" class="btn btn-secondary">
            <i class="bi bi-x"></i> Abbrechen
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> <?php echo $isEdit ? 'Aktualisieren' : 'Erstellen'; ?>
        </button>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
