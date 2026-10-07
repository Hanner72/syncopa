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

// Formationsspezifisches Register
$activeFormationId = Session::getFormationId();
$activeFormation   = null;
$formationRegister = null;
if ($activeFormationId) {
    try {
        $activeFormation = (new Formation())->getById($activeFormationId);
    } catch (\Throwable $e) {}
    if ($isEdit) {
        $mfRow = $db->fetchOne(
            "SELECT register_id FROM mitglied_formationen WHERE mitglied_id = ? AND formation_id = ?",
            [$id, $activeFormationId]
        );
        $formationRegister = $mfRow['register_id'] ?? null;
    }
}

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
    foreach (['bmv_anrede', 'bmv_titel', 'bmv_titeln', 'bmv_telefon2', 'bmv_fax', 'bmv_telefon_firma',
              'bmv_email2', 'bmv_beruf', 'bmv_firma', 'bmv_bemerkung', 'bmv_kategorien'] as $feld) {
        $data[$feld] = trim((string)($_POST[$feld] ?? '')) ?: null;
    }
    foreach (['bmv_exp_telnr1', 'bmv_exp_telnr2', 'bmv_exp_email1', 'bmv_exp_email2', 'bmv_exp_adresse', 'bmv_exp_internet'] as $feld) {
        $data[$feld] = isset($_POST[$feld]) ? 1 : 0;
    }
    // Leere Formularfelder als NULL speichern (Zahlen- und Datumsspalten vertragen keinen Leerstring)
    $data = array_map(fn($wert) => is_string($wert) && trim($wert) === '' ? null : $wert, $data);
    $data['register_id'] = !empty($_POST['register_id']) ? (int)$_POST['register_id'] : null;

    if (!$isEdit) {
        $data['mitgliedsnummer'] = trim($_POST['mitgliedsnummer'] ?? '');
        // Leer → automatisch aus Nummernkreis
        if ($data['mitgliedsnummer'] === '') {
            $data['mitgliedsnummer'] = $nkObj->naechsteNummer('mitglieder');
        }
        $data['eintritt_datum'] = $_POST['eintritt_datum'] ?? date('Y-m-d');
    }
    
    try {
        // Foto: bestehendes beibehalten, neues hochladen (BMV-Format) oder entfernen
        $data['bmv_foto'] = $isEdit ? ($mitglied['bmv_foto'] ?? null) : null;
        if (isset($_POST['foto_entfernen'])) {
            $data['bmv_foto'] = null;
        }
        $fotoTmp = null;
        if (!empty($_FILES['foto']['tmp_name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $data['bmv_foto'] = BmvMitgliederSync::fotoAusDatei($_FILES['foto']['tmp_name']);
            $fotoTmp = $_FILES['foto']['tmp_name'];
        }
        // Originalbild lokal behalten; die alte Datei wird nach dem Speichern entfernt
        $alteFotoDatei = $isEdit ? ($mitglied['foto'] ?? null) : null;
        $data['foto'] = $alteFotoDatei;
        if (isset($_POST['foto_entfernen'])) {
            $data['foto'] = null;
        }

        // Mitglieder mit BMV-Zuordnung: Änderungen in den BMV übertragen, außer bei „Nur lokal speichern".
        // Solange Änderungen nicht übertragen sind (Flag), werden beim nächsten Speichern alle Felder gesendet.
        $data['bmv_nicht_uebertragen'] = 0;
        if ($isEdit && !empty($mitglied['bmv_id'])) {
            $bmvApi = new BmvApi();
            $offen = !empty($mitglied['bmv_nicht_uebertragen']);
            $aenderungen = $offen
                ? BmvMitgliederSync::komplettZuBmv($data)
                : BmvMitgliederSync::aenderungenZuBmv($mitglied, $data);
            $nurLokal = isset($_POST['nur_lokal']) || !$bmvApi->istKonfiguriert();

            if ($nurLokal) {
                $data['bmv_nicht_uebertragen'] = ($offen || BmvMitgliederSync::aenderungenZuBmv($mitglied, $data)) ? 1 : 0;
            } elseif ($aenderungen) {
                try {
                    $bmvApi->musikerAktualisieren($mitglied['bmv_id'], $aenderungen);
                } catch (RuntimeException $e) {
                    throw new Exception('Nicht gespeichert, da der BMV-Datenservice die Änderung nicht übernommen hat: ' . $e->getMessage());
                }
            }
        }

        if ($fotoTmp !== null) {
            $data['foto'] = Mitglied::fotoSpeichern($fotoTmp);
        }

        if ($isEdit) {
            $mitgliedObj->update($id, $data);
            if ($alteFotoDatei && $alteFotoDatei !== $data['foto']) {
                Mitglied::fotoLoeschen($alteFotoDatei);
            }
            // Formationsspezifisches Register speichern
            if ($activeFormationId) {
                $formRegId = !empty($_POST['formation_register_id']) ? (int)$_POST['formation_register_id'] : null;
                $db->execute(
                    "UPDATE mitglied_formationen SET register_id = ? WHERE mitglied_id = ? AND formation_id = ?",
                    [$formRegId, $id, $activeFormationId]
                );
            }
            if (!empty($data['bmv_nicht_uebertragen'])) {
                Session::setFlashMessage('warning', 'Nur lokal gespeichert: noch nicht im BMV gespeichert.');
            } else {
                Session::setFlashMessage('success', 'Mitglied erfolgreich aktualisiert');
            }
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

<?php if ($isEdit && !empty($mitglied['bmv_nicht_uebertragen'])): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i>
    <strong>Nicht im BMV gespeichert:</strong> Die Änderungen sind nur in Syncopa gespeichert. Beim nächsten Speichern werden alle BMV-Felder übertragen.
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate enctype="multipart/form-data">
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
                        <label for="register_id" class="form-label">
                            Register
                            <?php if ($activeFormation): ?>
                            <small class="text-muted">(Standard / formationsübergreifend)</small>
                            <?php endif; ?>
                        </label>
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

                    <?php if ($activeFormation && $isEdit): ?>
                    <div class="mb-3">
                        <label for="formation_register_id" class="form-label">
                            Register in
                            <span class="badge ms-1" style="background-color:<?php echo htmlspecialchars($activeFormation['farbe']); ?>;color:#fff">
                                <?php echo htmlspecialchars($activeFormation['kuerzel'] ?: $activeFormation['name']); ?>
                            </span>
                            <?php echo htmlspecialchars($activeFormation['name']); ?>
                        </label>
                        <select class="form-select" id="formation_register_id" name="formation_register_id">
                            <option value="">– Standard verwenden –</option>
                            <?php foreach ($register as $reg): ?>
                            <option value="<?php echo $reg['id']; ?>"
                                    <?php echo ($formationRegister ?? '') == $reg['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($reg['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Überschreibt das Standard-Register nur für diese Formation.</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="aktiv" <?php echo ($mitglied['status'] ?? 'aktiv') === 'aktiv' ? 'selected' : ''; ?>>Aktiv</option>
                            <option value="passiv" <?php echo ($mitglied['status'] ?? '') === 'passiv' ? 'selected' : ''; ?>>Passiv</option>
                            <option value="ausgetreten" <?php echo ($mitglied['status'] ?? '') === 'ausgetreten' ? 'selected' : ''; ?>>Ausgetreten</option>
                            <option value="ehrenmitglied" <?php echo ($mitglied['status'] ?? '') === 'ehrenmitglied' ? 'selected' : ''; ?>>Ehrenmitglied</option>
                            <option value="ueberpruefen" <?php echo ($mitglied['status'] ?? '') === 'ueberpruefen' ? 'selected' : ''; ?>>Überprüfen</option>
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
    
    <div class="card mt-3">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-cloud"></i> BMV-Daten</h5></div>
        <div class="card-body">
            <p class="text-muted small">
                Diese Felder entsprechen dem BMV-Datenservice. Änderungen werden beim Speichern an den BMV übertragen,
                sofern das Mitglied zugeordnet ist und Zugangsdaten eingetragen sind.
            </p>
            <div class="row mb-3">
                <div class="col-md-3 text-center">
                    <?php if (!empty($mitglied['foto']) || !empty($mitglied['bmv_foto'])): ?>
                    <img src="api/mitglied_foto.php?id=<?php echo (int)$mitglied['id']; ?>&amp;v=<?php echo strtotime($mitglied['aktualisiert_am']); ?>" alt="Foto"
                         class="rounded border mb-2" style="width:128px;height:128px;object-fit:cover">
                    <div class="form-check small">
                        <input class="form-check-input" type="checkbox" id="foto_entfernen" name="foto_entfernen">
                        <label class="form-check-label" for="foto_entfernen">Foto entfernen</label>
                    </div>
                    <?php else: ?>
                    <div class="bg-light rounded border d-flex align-items-center justify-content-center mb-2"
                         style="width:128px;height:128px;margin:auto"><i class="bi bi-person fs-1 text-muted"></i></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-9">
                    <label for="foto" class="form-label">Foto hochladen</label>
                    <input type="file" class="form-control" id="foto" name="foto" accept="image/png,image/jpeg,image/gif,image/webp">
                    <div class="form-text">Wird quadratisch auf 128×128 Pixel zugeschnitten und im BMV-Format gespeichert (max. 5 MB).</div>
                </div>
            </div>
            <div class="row">
                <?php
                $bmvTextfelder = [
                    'bmv_anrede' => 'Anrede', 'bmv_titel' => 'Titel', 'bmv_titeln' => 'Titel nachgestellt',
                    'bmv_telefon2' => 'Telefon 2', 'bmv_fax' => 'Fax', 'bmv_telefon_firma' => 'Telefon Firma',
                    'bmv_email2' => 'E-Mail 2', 'bmv_beruf' => 'Beruf', 'bmv_firma' => 'Firma',
                    'bmv_kategorien' => 'Kategorien',
                ];
                foreach ($bmvTextfelder as $feld => $label): ?>
                <div class="col-md-6 mb-3">
                    <label for="<?php echo $feld; ?>" class="form-label"><?php echo $label; ?></label>
                    <input type="text" class="form-control" id="<?php echo $feld; ?>" name="<?php echo $feld; ?>"
                           value="<?php echo htmlspecialchars($mitglied[$feld] ?? ''); ?>">
                </div>
                <?php endforeach; ?>
                <div class="col-12 mb-3">
                    <label for="bmv_bemerkung" class="form-label">Bemerkung (BMV)</label>
                    <textarea class="form-control" id="bmv_bemerkung" name="bmv_bemerkung" rows="2"><?php echo htmlspecialchars($mitglied['bmv_bemerkung'] ?? ''); ?></textarea>
                </div>
            </div>

            <h6>Datenschutz-Freigaben (BMV)</h6>
            <div class="row mb-3">
                <?php
                $bmvFreigaben = [
                    'bmv_exp_telnr1' => 'Telefon 1 darf exportiert werden',
                    'bmv_exp_telnr2' => 'Telefon 2 darf exportiert werden',
                    'bmv_exp_email1' => 'E-Mail 1 darf exportiert werden',
                    'bmv_exp_email2' => 'E-Mail 2 darf exportiert werden',
                    'bmv_exp_adresse' => 'Adresse darf exportiert werden',
                    'bmv_exp_internet' => 'Im Internet veröffentlichen',
                ];
                foreach ($bmvFreigaben as $feld => $label): ?>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="<?php echo $feld; ?>" name="<?php echo $feld; ?>"
                               <?php echo !empty($mitglied[$feld]) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="<?php echo $feld; ?>"><?php echo $label; ?></label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <h6>Nur lesend (vom BMV)</h6>
            <dl class="row small mb-0">
                <dt class="col-sm-3">BMV-ID</dt>
                <dd class="col-sm-9"><?php echo htmlspecialchars($mitglied['bmv_id'] ?? '–'); ?></dd>
                <dt class="col-sm-3">BMV-Status (Rohwert)</dt>
                <dd class="col-sm-9"><?php echo htmlspecialchars($mitglied['bmv_status'] ?? '–'); ?></dd>
                <dt class="col-sm-3">Verein-ID (BMV)</dt>
                <dd class="col-sm-9"><?php echo htmlspecialchars((string)($mitglied['bmv_verein_id'] ?? '–')); ?></dd>
                <dt class="col-sm-3">Letzte Änderung (BMV)</dt>
                <dd class="col-sm-9"><?php echo htmlspecialchars($mitglied['bmv_aenderung'] ?? '–'); ?></dd>
                <dt class="col-sm-3">Angelegt von (BMV)</dt>
                <dd class="col-sm-9"><?php echo htmlspecialchars($mitglied['bmv_pcanmeldename'] ?? '–'); ?></dd>
            </dl>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-3">
        <a href="mitglieder.php" class="btn btn-secondary">
            <i class="bi bi-x"></i> Abbrechen
        </a>
        <div class="d-flex gap-2">
            <?php if ($isEdit && !empty($mitglied['bmv_id'])): ?>
            <button type="submit" name="nur_lokal" value="1" class="btn btn-outline-warning"
                    title="Speichert nur in Syncopa. Die Änderung wird als „nicht im BMV gespeichert" markiert.">
                <i class="bi bi-hdd"></i> Nur lokal speichern
            </button>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> <?php echo $isEdit ? 'Aktualisieren' : 'Erstellen'; ?>
            </button>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
