<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'schreiben');

$vObj    = new FestVertrag();
$festObj = new Fest();

$id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$festId  = isset($_GET['fest_id']) ? (int)$_GET['fest_id'] : null;
$isEdit  = $id !== null;

if ($isEdit) {
    $vertrag = $vObj->getById($id);
    if (!$vertrag) { Session::setFlashMessage('danger', 'Vertrag nicht gefunden.'); header('Location: feste.php'); exit; }
    $festId  = $vertrag['fest_id'];
} else {
    $vertrag = [];
}

$fest = $festObj->getById($festId);
if (!$fest) { Session::setFlashMessage('danger', 'Fest nicht gefunden.'); header('Location: feste.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'fest_id'        => $festId,
        'band_name'      => trim($_POST['band_name'] ?? ''),
        'vertrags_datum' => $_POST['vertrags_datum'] ?? '',
        'auftritt_datum' => $_POST['auftritt_datum'] ?? '',
        'auftritt_zeit'  => $_POST['auftritt_zeit'] ?? '',
        'honorar'        => $_POST['honorar'] !== '' ? (float)$_POST['honorar'] : null,
        'zahlungsstatus' => $_POST['zahlungsstatus'] ?? 'offen',
        'zahlungsdatum'  => $_POST['zahlungsdatum'] ?? '',
        'notizen'        => trim($_POST['notizen'] ?? ''),
        'erstellt_von'   => Session::getUserId(),
    ];

    if (empty($data['band_name'])) {
        $error = 'Bitte einen Band-/Gruppennamen eingeben.';
    } else {
        try {
            if ($isEdit) {
                $vObj->update($id, $data);
                $savedId = $id;
            } else {
                $savedId = $vObj->create($data);
            }

            // PDF-Upload verarbeiten
            if (!empty($_FILES['dokument']['name'])) {
                try {
                    $upload = $vObj->handleUpload($_FILES['dokument'], $festId);
                    $vObj->updateDokument($savedId, $upload['pfad'], $upload['name']);
                } catch (Exception $ue) {
                    Session::setFlashMessage('warning', 'Vertrag gespeichert, aber Upload-Fehler: ' . $ue->getMessage());
                    header('Location: fest_vertraege.php?fest_id=' . $festId); exit;
                }
            }

            Session::setFlashMessage('success', $isEdit ? 'Vertrag aktualisiert.' : 'Vertrag angelegt.');
            header('Location: fest_vertraege.php?fest_id=' . $festId); exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="feste.php">Feste</a></li>
        <li class="breadcrumb-item"><a href="fest_detail.php?id=<?php echo $festId; ?>"><?php echo htmlspecialchars($fest['name']); ?></a></li>
        <li class="breadcrumb-item"><a href="fest_vertraege.php?fest_id=<?php echo $festId; ?>">Verträge</a></li>
        <li class="breadcrumb-item active"><?php echo $isEdit ? 'Bearbeiten' : 'Neu'; ?></li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-file-earmark-text"></i> <?php echo $isEdit ? 'Vertrag bearbeiten' : 'Vertrag anlegen'; ?></h1>
    <a href="fest_vertraege.php?fest_id=<?php echo $festId; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Vertragsdaten</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="band_name" class="form-label">Band / Gruppe / Künstler <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="band_name" name="band_name" required
                               value="<?php echo htmlspecialchars($vertrag['band_name'] ?? ''); ?>"
                               placeholder="Name der Band oder des Künstlers">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="auftritt_datum" class="form-label">Auftritts-Datum</label>
                            <input type="date" class="form-control" id="auftritt_datum" name="auftritt_datum"
                                   value="<?php echo htmlspecialchars($vertrag['auftritt_datum'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="auftritt_zeit" class="form-label">Auftritts-Uhrzeit</label>
                            <input type="time" class="form-control" id="auftritt_zeit" name="auftritt_zeit"
                                   value="<?php echo htmlspecialchars($vertrag['auftritt_zeit'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="vertrags_datum" class="form-label">Vertrags-Datum</label>
                            <input type="date" class="form-control" id="vertrags_datum" name="vertrags_datum"
                                   value="<?php echo htmlspecialchars($vertrag['vertrags_datum'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="honorar" class="form-label">Honorar (€)</label>
                            <input type="number" class="form-control" id="honorar" name="honorar"
                                   value="<?php echo htmlspecialchars($vertrag['honorar'] ?? ''); ?>"
                                   step="0.01" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="dokument" class="form-label">Vertragsdokument (PDF)</label>
                        <?php if ($isEdit && !empty($vertrag['dokument_pfad']) && file_exists($vertrag['dokument_pfad'])): ?>
                        <div class="mb-2">
                            <i class="bi bi-file-earmark-pdf text-danger"></i>
                            <span class="small"><?php echo htmlspecialchars($vertrag['dokument_name']); ?></span>
                            <a href="api/fest_vertrag_download.php?id=<?php echo $id; ?>" class="btn btn-xs btn-outline-primary ms-2" style="font-size:11px;padding:1px 6px">
                                <i class="bi bi-download"></i> Download
                            </a>
                        </div>
                        <div class="form-text mb-1">Neues PDF hochladen ersetzt das vorhandene Dokument.</div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="dokument" name="dokument" accept=".pdf,application/pdf">
                        <div class="form-text">Nur PDF, max. <?php echo MAX_UPLOAD_SIZE / 1048576; ?> MB</div>
                    </div>
                    <div class="mb-3">
                        <label for="notizen" class="form-label">Notizen</label>
                        <textarea class="form-control" id="notizen" name="notizen" rows="3"
                                  placeholder="Besondere Vereinbarungen, technische Anforderungen, ..."><?php echo htmlspecialchars($vertrag['notizen'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="fest_vertraege.php?fest_id=<?php echo $festId; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Abbrechen
                        </a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Zahlung</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="zahlungsstatus" class="form-label">Zahlungsstatus</label>
                        <select class="form-select" id="zahlungsstatus" name="zahlungsstatus">
                            <option value="offen"     <?php echo ($vertrag['zahlungsstatus'] ?? 'offen') === 'offen'     ? 'selected' : ''; ?>>Offen</option>
                            <option value="teilweise" <?php echo ($vertrag['zahlungsstatus'] ?? '') === 'teilweise' ? 'selected' : ''; ?>>Teilweise bezahlt</option>
                            <option value="bezahlt"   <?php echo ($vertrag['zahlungsstatus'] ?? '') === 'bezahlt'   ? 'selected' : ''; ?>>Bezahlt</option>
                            <option value="storniert" <?php echo ($vertrag['zahlungsstatus'] ?? '') === 'storniert' ? 'selected' : ''; ?>>Storniert</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="zahlungsdatum" class="form-label">Zahlungsdatum</label>
                        <input type="date" class="form-control" id="zahlungsdatum" name="zahlungsdatum"
                               value="<?php echo htmlspecialchars($vertrag['zahlungsdatum'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
