<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('noten', 'schreiben');

$notenObj = new Noten();
$id = $_GET['id'] ?? null;
$isEdit = !empty($id);

// Nummernkreis
require_once __DIR__ . '/classes/Nummernkreis.php';
$nkObj = new Nummernkreis();
$naechsteArchivNummer = $nkObj->naechsteNummer('noten');

if ($isEdit) {
    $note = $notenObj->getById($id);
    if (!$note) {
        Session::setFlashMessage('danger', 'Noten nicht gefunden');
        header('Location: noten.php');
        exit;
    }
    $dateien = $notenObj->getDateien($id);
} else {
    $note = [];
    $dateien = [];
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'titel' => trim($_POST['titel'] ?? ''),
        'untertitel' => trim($_POST['untertitel'] ?? '') ?: null,
        'komponist' => trim($_POST['komponist'] ?? '') ?: null,
        'arrangeur' => trim($_POST['arrangeur'] ?? '') ?: null,
        'verlag' => trim($_POST['verlag'] ?? '') ?: null,
        'besetzung' => trim($_POST['besetzung'] ?? '') ?: null,
        'schwierigkeitsgrad' => $_POST['schwierigkeitsgrad'] ?? '3',
        'dauer_minuten' => !empty($_POST['dauer_minuten']) ? (int)$_POST['dauer_minuten'] : null,
        'genre' => trim($_POST['genre'] ?? '') ?: null,
        'anzahl_stimmen' => !empty($_POST['anzahl_stimmen']) ? (int)$_POST['anzahl_stimmen'] : null,
        'zustand' => $_POST['zustand'] ?? 'gut',
        'bemerkungen' => trim($_POST['bemerkungen'] ?? '') ?: null,
        'standort' => trim($_POST['standort'] ?? '') ?: null
    ];
    
    if (!$isEdit) {
        $archivNr = trim($_POST['archiv_nummer'] ?? '');
        $data['archiv_nummer'] = $archivNr !== '' ? $archivNr : $nkObj->naechsteNummer('noten');
    }
    
    // Validierung
    if (empty($data['titel'])) {
        $error = 'Bitte einen Titel eingeben.';
    } else {
        try {
            if ($isEdit) {
                $notenObj->update($id, $data);
                $message = 'Noten erfolgreich aktualisiert.';
            } else {
                $id = $notenObj->create($data);
                $isEdit = true;
                $note = $notenObj->getById($id);
                $dateien = [];
                $message = 'Noten erfolgreich erstellt. Sie können nun PDF-Dateien hochladen.';
            }
            Session::setFlashMessage('success', $message);
            
            // Bei "Speichern und Schließen"
            if (isset($_POST['save_close'])) {
                header('Location: noten.php');
                exit;
            }
            
            // Bei normalem Speichern: Seite neu laden mit der ID
            header('Location: noten_bearbeiten.php?id=' . $id);
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
        <i class="bi bi-music-note-list"></i> 
        <?php echo $isEdit ? 'Noten bearbeiten: ' . htmlspecialchars($note['titel']) : 'Neue Noten anlegen'; ?>
    </h1>
    <a href="noten.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück zur Liste</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Linke Spalte: Stammdaten -->
    <div class="col-lg-7">
        <form method="POST" id="notenForm">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Stammdaten</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="titel" class="form-label">Titel <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titel" name="titel" 
                                   value="<?php echo htmlspecialchars($note['titel'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="archiv_nummer" class="form-label">Archiv-Nr.</label>
                            <input type="text" class="form-control" id="archiv_nummer" name="archiv_nummer" 
                                   value="<?php echo htmlspecialchars($note['archiv_nummer'] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($naechsteArchivNummer); ?>" <?php echo $isEdit ? 'readonly' : ''; ?>>
                            <?php if (!$isEdit): ?>
                            <small class="text-muted">Leer lassen für automatische Vergabe (nächste: <strong><?php echo htmlspecialchars($naechsteArchivNummer); ?></strong>)</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="untertitel" class="form-label">Untertitel</label>
                        <input type="text" class="form-control" id="untertitel" name="untertitel" 
                               value="<?php echo htmlspecialchars($note['untertitel'] ?? ''); ?>"
                               placeholder="z.B. Polka, Walzer, Konzertmarsch">
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
                                   value="<?php echo htmlspecialchars($note['genre'] ?? ''); ?>"
                                   list="genreList" placeholder="z.B. Marsch, Polka">
                            <datalist id="genreList">
                                <option value="Marsch">
                                <option value="Polka">
                                <option value="Walzer">
                                <option value="Konzertwerk">
                                <option value="Filmmusik">
                                <option value="Pop">
                                <option value="Rock">
                                <option value="Klassik">
                                <option value="Volksmusik">
                                <option value="Musical">
                            </datalist>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="besetzung" class="form-label">Besetzung</label>
                            <input type="text" class="form-control" id="besetzung" name="besetzung" 
                                   value="<?php echo htmlspecialchars($note['besetzung'] ?? ''); ?>"
                                   placeholder="z.B. Blasorchester">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="schwierigkeitsgrad" class="form-label">Schwierigkeitsgrad</label>
                            <select class="form-select" id="schwierigkeitsgrad" name="schwierigkeitsgrad">
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($note['schwierigkeitsgrad'] ?? '3') == $i ? 'selected' : ''; ?>>
                                    Stufe <?php echo $i; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="dauer_minuten" class="form-label">Dauer (Min.)</label>
                            <input type="number" class="form-control" id="dauer_minuten" name="dauer_minuten" 
                                   value="<?php echo htmlspecialchars($note['dauer_minuten'] ?? ''); ?>" min="1" max="60">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="anzahl_stimmen" class="form-label">Anzahl Stimmen</label>
                            <input type="number" class="form-control" id="anzahl_stimmen" name="anzahl_stimmen" 
                                   value="<?php echo htmlspecialchars($note['anzahl_stimmen'] ?? ''); ?>" min="1" max="100">
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
                        <label for="standort" class="form-label">Standort im Archiv</label>
                        <input type="text" class="form-control" id="standort" name="standort" 
                               value="<?php echo htmlspecialchars($note['standort'] ?? ''); ?>"
                               placeholder="z.B. Schrank A, Fach 3">
                    </div>
                    
                    <div class="mb-3">
                        <label for="bemerkungen" class="form-label">Bemerkungen</label>
                        <textarea class="form-control" id="bemerkungen" name="bemerkungen" rows="3"
                                  placeholder="Zusätzliche Informationen..."><?php echo htmlspecialchars($note['bemerkungen'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between">
                        <a href="noten.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Abbrechen
                        </a>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> Speichern
                            </button>
                            <button type="submit" name="save_close" value="1" class="btn btn-success">
                                <i class="bi bi-check2-all"></i> Speichern & Schließen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Rechte Spalte: PDF-Upload -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-file-earmark-pdf text-danger"></i> PDF-Dateien</h5>
                <?php if ($isEdit): ?>
                <span class="badge bg-secondary" id="dateiAnzahl"><?php echo count($dateien); ?> Datei(en)</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$isEdit): ?>
                <!-- Hinweis bei neuem Notenstück -->
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle"></i>
                    <strong>Hinweis:</strong> Bitte speichern Sie zuerst die Stammdaten. 
                    Danach können Sie PDF-Dateien hochladen.
                </div>
                <?php else: ?>
                <!-- Upload-Zone -->
                <div id="dropZone" class="upload-zone mb-2">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <p class="mb-1"><strong>PDF-Dateien hierher ziehen</strong></p>
                    <p class="text-muted small mb-1">oder:</p>
                    <label class="btn btn-sm btn-outline-secondary mb-0">
                        <i class="bi bi-folder2-open"></i> Datei(en) auswählen
                        <input type="file" id="fileInput" multiple accept=".pdf,application/pdf" style="display:none;">
                    </label>
                    <p class="text-muted small mt-2 mb-0">
                        Erlaubt: PDF bis max. <?php echo MAX_UPLOAD_SIZE / 1024 / 1024; ?> MB
                    </p>
                </div>
                
                <!-- Upload Progress -->
                <div id="uploadProgress" class="mb-3" style="display: none;">
                    <label class="form-label small">Upload läuft...</label>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%;" id="progressBar">0%</div>
                    </div>
                </div>
                
                <!-- Upload Fehler -->
                <div id="uploadError" class="alert alert-danger mb-3" style="display: none;"></div>
                
                <!-- Dateiliste -->
                <div id="dateiListe">
                    <?php if (empty($dateien)): ?>
                    <div id="keineDateien" class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mb-0">Noch keine Dateien hochgeladen</p>
                    </div>
                    <?php else: ?>
                    <ul class="list-group" id="dateiListeUl">
                        <?php foreach ($dateien as $datei): ?>
                        <li class="list-group-item" data-id="<?php echo $datei['id']; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-file-earmark-pdf text-danger fs-4 me-2"></i>
                                    <div>
                                        <div class="fw-medium"><?php echo htmlspecialchars($datei['original_name']); ?></div>
                                        <small class="text-muted">
                                            <?php echo number_format($datei['dateigroesse'] / 1024, 1); ?> KB
                                            &bull; <?php echo date('d.m.Y H:i', strtotime($datei['erstellt_am'])); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <a href="api/noten_download.php?id=<?php echo $datei['id']; ?>" 
                                       class="btn btn-outline-primary" title="Herunterladen">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <?php 
                                    $istEinzelstimme = (strpos($datei['beschreibung'] ?? '', '[stimme]') === 0);
                                    $istPdf = strtolower(pathinfo($datei['original_name'], PATHINFO_EXTENSION)) === 'pdf';
                                    if (Session::checkPermission('noten', 'schreiben') && $istPdf): ?>
                                    <button type="button" 
                                            class="btn btn-sm <?php echo $istEinzelstimme ? 'btn-outline-secondary disabled' : 'btn-outline-success btn-split-datei'; ?>"
                                            <?php if (!$istEinzelstimme): ?>
                                            data-id="<?php echo $datei['id']; ?>"
                                            data-noten-id="<?php echo $note['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($datei['original_name']); ?>"
                                            <?php endif; ?>
                                            title="<?php echo $istEinzelstimme ? 'Bereits eine Einzelstimme' : 'Stimmen aufteilen'; ?>"
                                            <?php if ($istEinzelstimme): ?>disabled aria-disabled="true"<?php endif; ?>>
                                        <i class="bi bi-scissors"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (Session::checkPermission('noten', 'loeschen')): ?>
                                    <button type="button" class="btn btn-outline-danger btn-delete-datei" 
                                            data-id="<?php echo $datei['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($datei['original_name']); ?>"
                                            title="Löschen">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($isEdit): ?>
        <!-- Stimmen-Split: Gesamtnoten-PDF aufteilen -->
        <div class="card mt-3 border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-scissors"></i> Noten automatisch aufteilen
                </h5>
                <span class="badge bg-white text-primary">PDF → Stimmen</span>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Lade ein Gesamt-PDF hoch (alle Stimmen in einer Datei). Das System erkennt
                    automatisch welche Seiten zu welcher Stimme gehören – anhand der Beschriftung
                    oben links/rechts – und erzeugt für jede Stimme eine eigene Datei.<br>
                    <strong>Ergebnis:</strong>
                    <code><?php echo htmlspecialchars(preg_replace('/[^A-Za-z0-9]+/', '_', trim($note['titel']))); ?>_Fluegelhorn_1.pdf</code>
                </p>

                <div id="splitDropZone" class="upload-zone upload-zone-split mb-2">
                    <i class="bi bi-file-earmark-arrow-up"></i>
                    <p class="mb-1"><strong>Gesamt-PDF hier ablegen</strong></p>
                    <p class="text-muted small mb-1">oder:</p>
                    <label class="btn btn-sm btn-outline-primary mb-0">
                        <i class="bi bi-folder2-open"></i> Datei auswählen
                        <input type="file" id="splitFileInput" accept=".pdf,application/pdf" style="display:none;">
                    </label>
                    <p class="text-muted small mt-2 mb-0">Nur eine PDF-Datei</p>
                </div>

                <!-- Status während Upload/Verarbeitung -->
                <div id="splitStatus" style="display:none;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="spinner-border spinner-border-sm text-primary" id="splitSpinner"></div>
                        <span id="splitStatusText" class="small text-muted">Wird verarbeitet…</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                             id="splitProgressBar" style="width:100%;"></div>
                    </div>
                </div>

                <div id="splitError" class="alert alert-danger mb-0 small" style="display:none;"></div>
                <div id="splitErgebnis"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tipps -->
        <div class="card mt-3 bg-light">
            <div class="card-body">
                <h6 class="card-title"><i class="bi bi-lightbulb text-warning"></i> Tipps</h6>
                <ul class="small mb-0">
                    <li>Nutze <strong>Noten aufteilen</strong> für Gesamt-PDFs mit allen Stimmen</li>
                    <li>Einzelne PDFs kannst du oben direkt hochladen</li>
                    <li>Auch die Partitur kann als PDF hinzugefügt werden</li>
                </ul>
            </div>
        </div>


<style>
.upload-zone {
    border: 2px dashed #dee2e6;
    border-radius: 0.5rem;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #fafafa;
    position: relative;
}

.upload-zone:hover {
    border-color: #0d6efd;
    background-color: #f0f7ff;
}

.upload-zone.drag-over {
    border-color: #0d6efd;
    background-color: #e7f1ff;
    transform: scale(1.02);
}

.upload-zone i.bi-cloud-arrow-up {
    font-size: 3rem;
    color: #6c757d;
    display: block;
    margin-bottom: 0.5rem;
}

.list-group-item {
    transition: background-color 0.2s ease;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

.upload-zone-split {
    border-color: #0d6efd;
    background-color: #f0f7ff;
    padding: 1.5rem;
}

.upload-zone-split i {
    font-size: 2.5rem;
    color: #0d6efd;
    display: block;
    margin-bottom: 0.5rem;
}

.upload-zone-split:hover,
.upload-zone-split.drag-over {
    border-color: #0a58ca;
    background-color: #ddeeff;
    transform: scale(1.01);
}

</style>

<?php if ($isEdit): ?>
<script>
console.log('[Noten] Script 1 geladen');
(function() {
    // Warten bis DOM geladen ist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUpload);
    } else {
        initUpload();
    }
    
    function initUpload() {
        var dropZone = document.getElementById('dropZone');
        var fileInput = document.getElementById('fileInput');
        var uploadProgress = document.getElementById('uploadProgress');
        var progressBar = document.getElementById('progressBar');
        var uploadError = document.getElementById('uploadError');
        var dateiListe = document.getElementById('dateiListe');
        var dateiAnzahl = document.getElementById('dateiAnzahl');
        var notenId = <?php echo (int)$id; ?>;
        
        if (!fileInput) {
            console.error('fileInput nicht gefunden!');
            return;
        }
        
        console.log('Upload initialisiert für Noten-ID:', notenId);
        
        // ===== DATEI AUSGEWÄHLT (via Label/Button) =====
        fileInput.onchange = function(e) {
            console.log('Dateien ausgewählt:', this.files.length);
            if (this.files && this.files.length > 0) {
                handleFiles(this.files);
            }
            this.value = ''; // Reset
        };
        
        // ===== DRAG & DROP =====
        dropZone.ondragenter = function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('drag-over');
        };
        
        dropZone.ondragover = function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('drag-over');
        };
        
        dropZone.ondragleave = function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drag-over');
        };
        
        dropZone.ondrop = function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drag-over');
            console.log('Dateien gedroppt:', e.dataTransfer.files.length);
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                handleFiles(e.dataTransfer.files);
            }
        };
        
        // Auch auf Body dragover verhindern
        document.body.ondragover = function(e) {
            e.preventDefault();
        };
        document.body.ondrop = function(e) {
            e.preventDefault();
        };
        
        // ===== DATEIEN VERARBEITEN =====
        function handleFiles(files) {
            var pdfFiles = [];
            var skipped = [];
            
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var isPdf = file.type === 'application/pdf' || 
                           file.name.toLowerCase().indexOf('.pdf') === file.name.length - 4;
                
                if (isPdf) {
                    pdfFiles.push(file);
                } else {
                    skipped.push(file.name);
                }
            }
            
            if (skipped.length > 0) {
                alert('Folgende Dateien wurden übersprungen (nur PDF erlaubt):\n' + skipped.join('\n'));
            }
            
            if (pdfFiles.length > 0) {
                uploadFiles(pdfFiles);
            }
        }
        
        // ===== UPLOAD DURCHFÜHREN =====
        function uploadFiles(files) {
            var formData = new FormData();
            formData.append('noten_id', notenId);
            
            for (var i = 0; i < files.length; i++) {
                formData.append('dateien[]', files[i]);
            }
            
            // UI aktualisieren
            uploadProgress.style.display = 'block';
            uploadError.style.display = 'none';
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            
            var xhr = new XMLHttpRequest();
            
            // Progress
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    var percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressBar.textContent = percent + '%';
                }
            };
            
            // Fertig
            xhr.onload = function() {
                uploadProgress.style.display = 'none';
                console.log('Upload Response:', xhr.status, xhr.responseText);
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Seite neu laden
                            window.location.reload();
                        } else {
                            showError(response.error || 'Unbekannter Fehler');
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        console.error('Response Text:', xhr.responseText);
                        showError('Server-Antwort konnte nicht verarbeitet werden. Siehe Browser-Konsole (F12).');
                    }
                } else {
                    showError('HTTP-Fehler ' + xhr.status);
                }
            };
            
            // Fehler
            xhr.onerror = function() {
                uploadProgress.style.display = 'none';
                showError('Netzwerkfehler beim Upload');
            };
            
            xhr.open('POST', 'api/noten_upload.php', true);
            xhr.send(formData);
        }
        
        function showError(message) {
            uploadError.textContent = message;
            uploadError.style.display = 'block';
        }
        
        // ===== DATEI SPLIT =====
        document.querySelectorAll('.btn-split-datei').forEach(function(btn) {
            btn.onclick = function() {
                var dateiId   = this.getAttribute('data-id');
                var notenId   = this.getAttribute('data-noten-id');
                var dateiName = this.getAttribute('data-name');
                var selfBtn   = this;

                if (!confirm('Die Datei "' + dateiName + '" in einzelne Stimmen aufteilen?\nDie Originaldatei bleibt erhalten.')) {
                    return;
                }

                // Button deaktivieren & Spinner zeigen
                selfBtn.disabled = true;
                selfBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                fetch('api/noten_split_existing.php', {
                    method: 'POST',
                    body: (function() {
                        var fd = new FormData();
                        fd.append('datei_id', dateiId);
                        fd.append('noten_id', notenId);
                        return fd;
                    })()
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    selfBtn.disabled = false;
                    selfBtn.innerHTML = '<i class="bi bi-scissors"></i>';

                    if (!data.success) {
                        alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                        return;
                    }

                    // Erfolgsmeldung einblenden
                    var info = document.createElement('div');
                    info.className = 'alert alert-success alert-dismissible small py-2 mt-2';
                    info.innerHTML = '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
                        + '<i class="bi bi-check-circle-fill me-1"></i>'
                        + '<strong>' + data.message + '</strong>'
                        + (data.ist_scan ? '<br><em class="text-warning">Scan-PDF – bitte Dateien umbenennen.</em>' : '');

                    selfBtn.closest('li').insertAdjacentElement('afterend', info);

                    // Seite nach kurzer Pause neu laden damit neue Dateien sichtbar werden
                    setTimeout(function() { location.reload(); }, 1800);
                })
                .catch(function(err) {
                    selfBtn.disabled = false;
                    selfBtn.innerHTML = '<i class="bi bi-scissors"></i>';
                    alert('Netzwerkfehler beim Aufteilen');
                    console.error(err);
                });
            };
        });

        // ===== DATEI LÖSCHEN =====
        document.querySelectorAll('.btn-delete-datei').forEach(function(btn) {
            btn.onclick = function() {
                var dateiId = this.getAttribute('data-id');
                var dateiName = this.getAttribute('data-name');
                var listItem = this.closest('li');
                
                if (!confirm('Datei "' + dateiName + '" wirklich löschen?')) {
                    return;
                }
                
                var formData = new FormData();
                formData.append('datei_id', dateiId);
                
                fetch('api/noten_datei_loeschen.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        listItem.remove();
                        var remaining = document.querySelectorAll('#dateiListeUl li').length;
                        dateiAnzahl.textContent = remaining + ' Datei(en)';
                        
                        if (remaining === 0) {
                            dateiListe.innerHTML = '<div id="keineDateien" class="text-center text-muted py-4">' +
                                '<i class="bi bi-inbox fs-1"></i>' +
                                '<p class="mb-0">Noch keine Dateien hochgeladen</p></div>';
                        }
                    } else {
                        alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                    }
                })
                .catch(function(error) {
                    alert('Fehler beim Löschen');
                    console.error(error);
                });
            };
        });
    }
})();
</script>
<?php endif; ?>


<?php if ($isEdit): ?>
<script>
console.log('[Noten] Script 2 geladen');
// =====================================================================
// NOTEN-SPLIT: Gesamt-PDF hochladen und nach Stimmen aufteilen
// =====================================================================
(function() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSplit);
    } else {
        initSplit();
    }

    function initSplit() {
        var dropZone   = document.getElementById('splitDropZone');
        var fileInput  = document.getElementById('splitFileInput');
        var status     = document.getElementById('splitStatus');
        var statusText = document.getElementById('splitStatusText');
        var errorBox   = document.getElementById('splitError');
        var ergebnis   = document.getElementById('splitErgebnis');
        var notenId    = <?php echo (int)$id; ?>;

        if (!dropZone || !fileInput) {
            console.error('Split-Upload: Elemente nicht gefunden');
            return;
        }

        console.log('Split-Upload initialisiert, Noten-ID:', notenId);

        // ===== DATEI AUSGEWAEHLT (Input ist direkt in der Zone eingebettet) =====
        fileInput.onchange = function(e) {
            console.log('Split-Datei ausgewaehlt:', this.files.length);
            if (this.files && this.files.length > 0) {
                handleFile(this.files[0]);
            }
            this.value = '';
        };

        // ===== DRAG & DROP =====
        dropZone.ondragenter = function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('drag-over');
        };

        dropZone.ondragover = function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('drag-over');
        };

        dropZone.ondragleave = function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drag-over');
        };

        dropZone.ondrop = function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drag-over');
            console.log('Split: Datei gedroppt');
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                handleFile(e.dataTransfer.files[0]);
            }
        };

        function handleFile(file) {
            console.log('Split: handleFile', file.name, file.type, file.size);
            var isPdf = file.type === 'application/pdf' ||
                        file.name.toLowerCase().indexOf('.pdf') === file.name.length - 4;
            if (!isPdf) {
                showError('Bitte nur eine PDF-Datei hochladen.');
                return;
            }
            uploadUndSplitte(file);
        }

        function uploadUndSplitte(file) {
            var formData = new FormData();
            formData.append('noten_id', notenId);
            formData.append('datei', file);

            console.log('Split: Starte Upload, notenId=' + notenId + ', Datei=' + file.name);

            status.style.display   = 'block';
            errorBox.style.display = 'none';
            ergebnis.innerHTML     = '';
            statusText.textContent = 'Wird hochgeladen...';

            var xhr = new XMLHttpRequest();

            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    var pct = Math.round(e.loaded / e.total * 100);
                    statusText.textContent = 'Hochladen... ' + pct + '%';
                    if (pct === 100) statusText.textContent = 'Stimmen werden erkannt und aufgeteilt...';
                }
            };

            xhr.onreadystatechange = function() {
                if (xhr.readyState !== 4) return;
                status.style.display = 'none';

                var raw = xhr.responseText || '';
                console.log('Split: readyState=4, status=' + xhr.status + ', response=' + raw.substring(0, 300));

                // Fehler sofort als alert anzeigen – damit es auf keinen Fall still verschwindet
                function fail(msg) {
                    console.error('Split-Fehler:', msg);
                    alert('Fehler beim Aufteilen:\n\n' + msg);
                    showError(msg);
                }

                if (xhr.status === 0) {
                    fail('Keine Antwort vom Server (Status 0). Ist die Datei api/noten_split_stimmen.php auf dem Server vorhanden?');
                    return;
                }
                if (xhr.status === 404) {
                    fail('API nicht gefunden (404). Bitte api/noten_split_stimmen.php auf den Server hochladen!');
                    return;
                }
                if (xhr.status !== 200) {
                    fail('HTTP-Fehler ' + xhr.status + '\n' + raw.substring(0, 300));
                    return;
                }

                var resp;
                try {
                    resp = JSON.parse(raw);
                } catch(ex) {
                    fail('Server-Antwort ist kein JSON:\n' + raw.substring(0, 400));
                    return;
                }

                console.log('Split-Antwort:', resp);

                if (resp.success && resp.gespeichert > 0) {
                    zeigErgebnis(resp);
                    setTimeout(function() { window.location.reload(); }, 4000);
                } else if (resp.success && resp.gespeichert === 0) {
                    var fehler = '';
                    if (resp.stimmen) {
                        resp.stimmen.forEach(function(s) {
                            if (s.fehler) fehler += '\n- ' + s.name + ': ' + s.fehler;
                        });
                    }
                    fail('Keine PDFs gespeichert (' + (resp.seiten_gesamt||0) + ' Seiten analysiert).'
                        + (fehler || '\n\nSind pdftk und pdftotext auf dem Server installiert?'));
                } else {
                    fail(resp.error || 'Unbekannter Fehler');
                }
            };

            xhr.onerror = function() {
                console.error('Split: XHR-Fehler');
                status.style.display = 'none';
                showError('Netzwerkfehler beim Upload.');
            };

            xhr.open('POST', 'api/noten_split_stimmen.php', true);
            xhr.send(formData);
        }

        function zeigErgebnis(resp) {
            var html = '';

            if (resp.ist_scan) {
                html += '<div class="alert alert-warning small py-2 mb-2">'
                      + '<i class="bi bi-exclamation-triangle me-1"></i>'
                      + '<strong>Scan-PDF erkannt</strong> – Stimmen konnten nicht automatisch erkannt werden.<br>'
                      + 'Die Seiten wurden einzeln gespeichert. '
                      + '<strong>Bitte die Dateien unten umbenennen</strong> (Bleistift-Symbol).'
                      + '</div>';
            }

            html += '<div class="alert alert-success small py-2 mb-2">'
                  + '<i class="bi bi-check-circle-fill me-1"></i>'
                  + '<strong>' + resp.message + '</strong>'
                  + '</div>';

            if (resp.stimmen && resp.stimmen.length) {
                html += '<ul class="list-group">';
                resp.stimmen.forEach(function(s) {
                    if (s.fehler) {
                        html += '<li class="list-group-item list-group-item-warning py-1 small">'
                              + '<i class="bi bi-exclamation-triangle me-1"></i>'
                              + escHtml(s.name) + ': <em>' + escHtml(s.fehler) + '</em></li>';
                    } else {
                        var badge = s.erkannt
                            ? '<span class="badge bg-success ms-1">erkannt</span>'
                            : '<span class="badge bg-warning text-dark ms-1">bitte umbenennen</span>';
                        html += '<li class="list-group-item py-1 small">'
                              + '<i class="bi bi-file-earmark-pdf text-danger me-1"></i>'
                              + '<strong>' + escHtml(s.name) + '</strong>' + badge;
                        if (s.stimme) html += ' <span class="text-muted">(' + escHtml(s.stimme) + ')</span>';
                        html += '</li>';
                    }
                });
                html += '</ul>';
            }
            ergebnis.innerHTML = html;
        }

        function showError(msg) {
            errorBox.innerHTML = msg;
            errorBox.style.display = 'block';
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
    }
})();
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

