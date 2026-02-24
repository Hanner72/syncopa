<?php

// === DEBUG START ===
if (isset($_GET['debug'])) {
    echo "<h3>Debug-Informationen</h3>";
    echo "<pre>";
    echo "Mitglied ID: " . $id . "\n";
    echo "Hat Schreibrecht: " . (Session::checkPermission('mitglieder', 'schreiben') ? 'JA' : 'NEIN') . "\n";
    echo "\nInstrumente:\n";
    var_dump($instrumente);
    echo "\nInstrumententypen verfügbar: " . count($instrumentTypen ?? []) . "\n";
    echo "\nPOST-Daten:\n";
    var_dump($_POST);
    echo "</pre>";
}
// === DEBUG ENDE ===

// mitglied_detail.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('mitglieder', 'lesen');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: mitglieder.php');
    exit;
}

$mitgliedObj = new Mitglied();
$mitglied = $mitgliedObj->getById($id);

if (!$mitglied) {
    Session::setFlashMessage('danger', 'Mitglied nicht gefunden');
    header('Location: mitglieder.php');
    exit;
}

$instrumente = $mitgliedObj->getInstrumente($id);

// Instrument hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_instrument'])) {
    if (Session::checkPermission('mitglieder', 'schreiben')) {
        try {
            $seitDatum = !empty($_POST['seit_datum']) ? $_POST['seit_datum'] : null;
            $mitgliedObj->addInstrument($id, $_POST['instrument_typ_id'], isset($_POST['hauptinstrument']) ? 1 : 0, $seitDatum);
            Session::setFlashMessage('success', 'Instrument hinzugefügt');
            header('Location: mitglied_detail.php?id=' . $id);
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Instrument entfernen
if (isset($_GET['remove_instrument'])) {
    if (Session::checkPermission('mitglieder', 'schreiben')) {
        try {
            $mitgliedObj->removeInstrument($_GET['remove_instrument']);
            Session::setFlashMessage('success', 'Instrument entfernt');
            header('Location: mitglied_detail.php?id=' . $id);
            exit;
        } catch (Exception $e) {
            Session::setFlashMessage('danger', 'Fehler: ' . $e->getMessage());
            header('Location: mitglied_detail.php?id=' . $id);
            exit;
        }
    }
}

// Verfügbare Instrumententypen laden
$db = Database::getInstance();
$instrumentTypen = $db->fetchAll("
    SELECT it.*, r.name as register_name 
    FROM instrument_typen it
    LEFT JOIN register r ON it.register_id = r.id
    ORDER BY r.sortierung, it.name
");

// Ausgeliehene Instrumente des Mitglieds laden
$ausgelieheneInstrumente = $db->fetchAll("
    SELECT i.*, it.name as instrument_name, r.name as register_name
    FROM instrumente i
    JOIN instrument_typen it ON i.instrument_typ_id = it.id
    LEFT JOIN register r ON it.register_id = r.id
    WHERE i.mitglied_id = ?
", [$id]);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-person"></i> <?php echo htmlspecialchars($mitglied['vorname'] . ' ' . $mitglied['nachname']); ?>
    </h1>
    <div>
        <a href="mitglieder.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Zurück
        </a>
        <?php if (Session::checkPermission('mitglieder', 'schreiben')): ?>
        <a href="mitglied_bearbeiten.php?id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Bearbeiten
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <!-- Stammdaten Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Stammdaten</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Mitgliedsnr.:</dt>
                    <dd class="col-sm-7"><strong><?php echo htmlspecialchars($mitglied['mitgliedsnummer']); ?></strong></dd>
                    
                    <dt class="col-sm-5">Status:</dt>
                    <dd class="col-sm-7">
                        <?php
                        $statusColors = ['aktiv' => 'success', 'passiv' => 'warning', 'ausgetreten' => 'secondary', 'ehrenmitglied' => 'primary'];
                        $color = $statusColors[$mitglied['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($mitglied['status']); ?></span>
                    </dd>
                    
                    <?php if ($mitglied['geburtsdatum']): ?>
                    <dt class="col-sm-5">Geburtsdatum:</dt>
                    <dd class="col-sm-7">
                        <?php echo date('d.m.Y', strtotime($mitglied['geburtsdatum'])); ?>
                        <?php
                        $alter = date_diff(date_create($mitglied['geburtsdatum']), date_create('today'))->y;
                        echo "<br><small class='text-muted'>{$alter} Jahre</small>";
                        ?>
                    </dd>
                    <?php endif; ?>
                    
                    <?php if ($mitglied['eintritt_datum']): ?>
                    <dt class="col-sm-5">Mitglied seit:</dt>
                    <dd class="col-sm-7">
                        <?php echo date('d.m.Y', strtotime($mitglied['eintritt_datum'])); ?>
                        <?php
                        $jahre = date_diff(date_create($mitglied['eintritt_datum']), date_create('today'))->y;
                        echo "<br><small class='text-muted'>{$jahre} Jahre</small>";
                        ?>
                    </dd>
                    <?php endif; ?>
                    
                    <?php if ($mitglied['register_name']): ?>
                    <dt class="col-sm-5">Register:</dt>
                    <dd class="col-sm-7"><span class="badge bg-info"><?php echo htmlspecialchars($mitglied['register_name']); ?></span></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        
        <!-- Kontakt Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Kontakt</h5>
            </div>
            <div class="card-body">
                <?php if ($mitglied['strasse'] || $mitglied['ort']): ?>
                <p class="mb-2">
                    <i class="bi bi-house"></i>
                    <?php echo htmlspecialchars($mitglied['strasse'] ?? ''); ?><br>
                    <?php echo htmlspecialchars(($mitglied['plz'] ?? '') . ' ' . ($mitglied['ort'] ?? '')); ?>
                    <?php if ($mitglied['land']): ?>
                    <br><?php echo htmlspecialchars($mitglied['land']); ?>
                    <?php endif; ?>
                </p>
                <hr>
                <?php endif; ?>
                
                <?php if ($mitglied['telefon']): ?>
                <p class="mb-2">
                    <i class="bi bi-telephone"></i>
                    <a href="tel:<?php echo htmlspecialchars($mitglied['telefon']); ?>">
                        <?php echo htmlspecialchars($mitglied['telefon']); ?>
                    </a>
                </p>
                <?php endif; ?>
                
                <?php if ($mitglied['mobil']): ?>
                <p class="mb-2">
                    <i class="bi bi-phone"></i>
                    <a href="tel:<?php echo htmlspecialchars($mitglied['mobil']); ?>">
                        <?php echo htmlspecialchars($mitglied['mobil']); ?>
                    </a>
                </p>
                <?php endif; ?>
                
                <?php if ($mitglied['email']): ?>
                <p class="mb-0">
                    <i class="bi bi-envelope"></i>
                    <a href="mailto:<?php echo htmlspecialchars($mitglied['email']); ?>">
                        <?php echo htmlspecialchars($mitglied['email']); ?>
                    </a>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($mitglied['notizen']): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Notizen</h5>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($mitglied['notizen'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-8">
        <!-- Instrumente die das Mitglied spielt -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Gespielte Instrumente</h5>
                <?php if (Session::checkPermission('mitglieder', 'schreiben')): ?>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addInstrumentModal">
                    <i class="bi bi-plus"></i> Instrument hinzufügen
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($instrumente)): ?>
                <p class="text-muted">Keine Instrumente zugeordnet</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Instrument</th>
                                <th>Register</th>
                                <th>Hauptinstrument</th>
                                <th>Seit</th>
                                <?php if (Session::checkPermission('mitglieder', 'schreiben')): ?>
                                <th>Aktion</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($instrumente as $instr): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($instr['instrument_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($instr['register_name'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($instr['hauptinstrument']): ?>
                                    <span class="badge bg-success"><i class="bi bi-star-fill"></i> Ja</span>
                                    <?php else: ?>
                                    <span class="text-muted">Nein</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $instr['seit_datum'] ? date('d.m.Y', strtotime($instr['seit_datum'])) : '-'; ?></td>
                                <?php if (Session::checkPermission('mitglieder', 'schreiben')): ?>
                                <td>
                                    <a href="?id=<?php echo $id; ?>&remove_instrument=<?php echo $instr['id']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Instrumentenzuordnung wirklich entfernen?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ausgeliehene Instrumente (aus Inventar) -->
        <?php if (!empty($ausgelieheneInstrumente)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-box"></i> Ausgeliehene Instrumente (Inventar)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Inventar-Nr.</th>
                                <th>Instrument</th>
                                <th>Hersteller/Modell</th>
                                <th>Ausgeliehen seit</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ausgelieheneInstrumente as $inv): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($inv['inventar_nummer']); ?></strong></td>
                                <td><?php echo htmlspecialchars($inv['instrument_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($inv['hersteller'] ?? '-'); ?>
                                    <?php if ($inv['modell']): ?>
                                    <br><small><?php echo htmlspecialchars($inv['modell']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($inv['ausgeliehen_seit'])); ?></td>
                                <td>
                                    <a href="instrument_detail.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Instrument hinzufügen Modal -->
<?php if (Session::checkPermission('mitglieder', 'schreiben')): ?>
<div class="modal fade" id="addInstrumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Instrument hinzufügen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="instrument_typ_id" class="form-label">Instrument *</label>
                        <select class="form-select" id="instrument_typ_id" name="instrument_typ_id" required>
                            <option value="">Bitte wählen</option>
                            <?php 
                            $currentRegister = null;
                            foreach ($instrumentTypen as $typ): 
                                if ($currentRegister !== $typ['register_name']):
                                    if ($currentRegister !== null) echo '</optgroup>';
                                    $currentRegister = $typ['register_name'];
                                    echo '<optgroup label="' . htmlspecialchars($typ['register_name'] ?? 'Ohne Register') . '">';
                                endif;
                            ?>
                            <option value="<?php echo $typ['id']; ?>">
                                <?php echo htmlspecialchars($typ['name']); ?>
                            </option>
                            <?php endforeach; ?>
                            <?php if ($currentRegister !== null) echo '</optgroup>'; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="seit_datum" class="form-label">Spielt seit</label>
                        <input type="date" class="form-control" id="seit_datum" name="seit_datum" 
                               value="<?php echo date('Y-m-d'); ?>">
                        <small class="text-muted">Wird auf heute gesetzt, falls leer</small>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="hauptinstrument" name="hauptinstrument">
                        <label class="form-check-label" for="hauptinstrument">
                            Als Hauptinstrument markieren
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" name="add_instrument" class="btn btn-primary">
                        <i class="bi bi-save"></i> Hinzufügen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
