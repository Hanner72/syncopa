<?php
// instrument_detail.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('instrumente', 'lesen');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: instrumente.php');
    exit;
}

$instrumentObj = new Instrument();
$instrument = $instrumentObj->getById($id);

if (!$instrument) {
    Session::setFlashMessage('danger', 'Instrument nicht gefunden');
    header('Location: instrumente.php');
    exit;
}

// Wartungen laden
$wartungen = $instrumentObj->getWartungen($id);

// Wartung hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_wartung'])) {
    if (Session::checkPermission('instrumente', 'schreiben')) {
        $wartungData = [
            'instrument_id' => $id,
            'datum' => $_POST['wartung_datum'],
            'art' => $_POST['wartung_art'],
            'beschreibung' => $_POST['wartung_beschreibung'] ?? null,
            'kosten' => $_POST['wartung_kosten'] ?? null,
            'durchgefuehrt_von' => $_POST['wartung_durchgefuehrt'] ?? null,
            'naechste_wartung' => $_POST['wartung_naechste'] ?? null
        ];
        
        try {
            $instrumentObj->addWartung($wartungData);
            Session::setFlashMessage('success', 'Wartung hinzugefügt');
            header('Location: instrument_detail.php?id=' . $id);
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-diagram-3"></i> <?php echo htmlspecialchars($instrument['instrument_name']); ?>
    </h1>
    <div>
        <a href="instrumente.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Zurück
        </a>
        <?php if (Session::checkPermission('instrumente', 'schreiben')): ?>
        <a href="instrument_bearbeiten.php?id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Bearbeiten
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <!-- Stammdaten -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Stammdaten</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Inventar-Nr.:</dt>
                    <dd class="col-sm-7"><strong><?php echo htmlspecialchars($instrument['inventar_nummer']); ?></strong></dd>
                    
                    <dt class="col-sm-5">Typ:</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($instrument['instrument_name']); ?></dd>
                    
                    <?php if ($instrument['register_name']): ?>
                    <dt class="col-sm-5">Register:</dt>
                    <dd class="col-sm-7"><span class="badge bg-info"><?php echo htmlspecialchars($instrument['register_name']); ?></span></dd>
                    <?php endif; ?>
                    
                    <?php if ($instrument['hersteller']): ?>
                    <dt class="col-sm-5">Hersteller:</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($instrument['hersteller']); ?></dd>
                    <?php endif; ?>
                    
                    <?php if ($instrument['modell']): ?>
                    <dt class="col-sm-5">Modell:</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($instrument['modell']); ?></dd>
                    <?php endif; ?>
                    
                    <?php if ($instrument['seriennummer']): ?>
                    <dt class="col-sm-5">Seriennr.:</dt>
                    <dd class="col-sm-7"><small><?php echo htmlspecialchars($instrument['seriennummer']); ?></small></dd>
                    <?php endif; ?>
                    
                    <?php if ($instrument['baujahr']): ?>
                    <dt class="col-sm-5">Baujahr:</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($instrument['baujahr']); ?></dd>
                    <?php endif; ?>
                    
                    <dt class="col-sm-5">Zustand:</dt>
                    <dd class="col-sm-7">
                        <?php
                        $zustandColors = ['sehr gut' => 'success', 'gut' => 'info', 'befriedigend' => 'warning', 'schlecht' => 'danger', 'defekt' => 'dark'];
                        $color = $zustandColors[$instrument['zustand']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($instrument['zustand']); ?></span>
                    </dd>
                </dl>
            </div>
        </div>
        
        <!-- Finanzielle Daten -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Finanzen</h5>
            </div>
            <div class="card-body">
                <?php if ($instrument['anschaffungsdatum']): ?>
                <p class="mb-2">
                    <strong>Anschaffung:</strong><br>
                    <?php echo date('d.m.Y', strtotime($instrument['anschaffungsdatum'])); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($instrument['anschaffungspreis']): ?>
                <p class="mb-2">
                    <strong>Anschaffungspreis:</strong><br>
                    € <?php echo number_format($instrument['anschaffungspreis'], 2, ',', '.'); ?>
                </p>
                <?php endif; ?>
                
                <?php if ($instrument['versicherungswert']): ?>
                <p class="mb-0">
                    <strong>Versicherungswert:</strong><br>
                    € <?php echo number_format($instrument['versicherungswert'], 2, ',', '.'); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Status -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Status</h5>
            </div>
            <div class="card-body">
                <?php if ($instrument['mitglied_id']): ?>
                <div class="alert alert-warning mb-0">
                    <h6><i class="bi bi-person"></i> Ausgeliehen an:</h6>
                    <p class="mb-1">
                        <strong><?php echo htmlspecialchars($instrument['vorname'] . ' ' . $instrument['nachname']); ?></strong><br>
                        <small><?php echo htmlspecialchars($instrument['mitgliedsnummer']); ?></small>
                    </p>
                    <small class="text-muted">
                        Seit: <?php echo date('d.m.Y', strtotime($instrument['ausgeliehen_seit'])); ?>
                    </small>
                </div>
                <?php else: ?>
                <div class="alert alert-success mb-0">
                    <h6><i class="bi bi-check-circle"></i> Verfügbar</h6>
                    <p class="mb-0">Das Instrument ist nicht ausgeliehen</p>
                </div>
                <?php endif; ?>
                
                <?php if ($instrument['standort']): ?>
                <hr>
                <p class="mb-0">
                    <strong>Standort:</strong><br>
                    <?php echo htmlspecialchars($instrument['standort']); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($instrument['notizen']): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Notizen</h5>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($instrument['notizen'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-8">
        <!-- Wartungshistorie -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Wartungshistorie</h5>
                <?php if (Session::checkPermission('instrumente', 'schreiben')): ?>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#wartungModal">
                    <i class="bi bi-plus"></i> Wartung hinzufügen
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($wartungen)): ?>
                <p class="text-muted">Keine Wartungen erfasst</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Art</th>
                                <th>Beschreibung</th>
                                <th>Kosten</th>
                                <th>Nächste Wartung</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wartungen as $wartung): ?>
                            <tr>
                                <td><?php echo date('d.m.Y', strtotime($wartung['datum'])); ?></td>
                                <td>
                                    <?php
                                    $artColors = ['Wartung' => 'info', 'Reparatur' => 'warning', 'Überholung' => 'primary', 'Reinigung' => 'success'];
                                    $color = $artColors[$wartung['art']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>"><?php echo htmlspecialchars($wartung['art']); ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($wartung['beschreibung'] ?? '-'); ?>
                                    <?php if ($wartung['durchgefuehrt_von']): ?>
                                    <br><small class="text-muted">von: <?php echo htmlspecialchars($wartung['durchgefuehrt_von']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($wartung['kosten']): ?>
                                    € <?php echo number_format($wartung['kosten'], 2, ',', '.'); ?>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($wartung['naechste_wartung']): ?>
                                    <?php echo date('d.m.Y', strtotime($wartung['naechste_wartung'])); ?>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Wartung hinzufügen Modal -->
<?php if (Session::checkPermission('instrumente', 'schreiben')): ?>
<div class="modal fade" id="wartungModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Wartung hinzufügen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="wartung_datum" class="form-label">Datum *</label>
                        <input type="date" class="form-control" id="wartung_datum" name="wartung_datum" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="wartung_art" class="form-label">Art *</label>
                        <select class="form-select" id="wartung_art" name="wartung_art" required>
                            <option value="Wartung">Wartung</option>
                            <option value="Reparatur">Reparatur</option>
                            <option value="Überholung">Überholung</option>
                            <option value="Reinigung">Reinigung</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="wartung_beschreibung" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="wartung_beschreibung" name="wartung_beschreibung" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="wartung_kosten" class="form-label">Kosten (€)</label>
                        <input type="number" class="form-control" id="wartung_kosten" name="wartung_kosten" 
                               step="0.01" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label for="wartung_durchgefuehrt" class="form-label">Durchgeführt von</label>
                        <input type="text" class="form-control" id="wartung_durchgefuehrt" name="wartung_durchgefuehrt">
                    </div>
                    
                    <div class="mb-3">
                        <label for="wartung_naechste" class="form-label">Nächste Wartung</label>
                        <input type="date" class="form-control" id="wartung_naechste" name="wartung_naechste">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" name="add_wartung" class="btn btn-primary">
                        <i class="bi bi-save"></i> Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
