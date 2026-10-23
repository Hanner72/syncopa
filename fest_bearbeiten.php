<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'schreiben');

$festObj = new Fest();
$id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isEdit  = $id !== null;

if ($isEdit) {
    $fest = $festObj->getById($id);
    if (!$fest) {
        Session::setFlashMessage('danger', 'Fest nicht gefunden.');
        header('Location: feste.php'); exit;
    }
} else {
    $fest = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'         => trim($_POST['name'] ?? ''),
        'jahr'         => (int)($_POST['jahr'] ?? date('Y')),
        'datum_von'    => $_POST['datum_von'] ?? '',
        'datum_bis'    => $_POST['datum_bis'] ?? '',
        'ort'          => trim($_POST['ort'] ?? ''),
        'adresse'      => trim($_POST['adresse'] ?? ''),
        'beschreibung' => trim($_POST['beschreibung'] ?? ''),
        'status'       => $_POST['status'] ?? 'geplant',
        'erstellt_von' => Session::getUserId()
    ];

    if (empty($data['name'])) {
        $error = 'Bitte einen Namen eingeben.';
    } elseif (empty($data['datum_von'])) {
        $error = 'Bitte ein Startdatum eingeben.';
    } else {
        try {
            if ($isEdit) {
                $festObj->update($id, $data);
                Session::setFlashMessage('success', 'Fest erfolgreich aktualisiert.');
            } else {
                $id = $festObj->create($data);
                Session::setFlashMessage('success', 'Fest erfolgreich angelegt.');
            }
            header('Location: fest_detail.php?id=' . $id); exit;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="bi bi-stars"></i>
        <?php echo $isEdit ? 'Fest bearbeiten' : 'Neues Fest anlegen'; ?>
    </h1>
    <a href="<?php echo $isEdit ? 'fest_detail.php?id='.$id : 'feste.php'; ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Zurück
    </a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-info-circle"></i> Grunddaten</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name des Festes <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required
                               value="<?php echo htmlspecialchars($fest['name'] ?? ''); ?>"
                               placeholder="z.B. Sommerfest 2026, Herbstfest, Vereinsfest">
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="jahr" class="form-label">Jahr <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="jahr" name="jahr" required
                                   value="<?php echo htmlspecialchars($fest['jahr'] ?? date('Y')); ?>"
                                   min="2000" max="2099">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="datum_von" class="form-label">Datum von <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="datum_von" name="datum_von" required
                                   value="<?php echo htmlspecialchars($fest['datum_von'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="datum_bis" class="form-label">Datum bis</label>
                            <input type="date" class="form-control" id="datum_bis" name="datum_bis"
                                   value="<?php echo htmlspecialchars($fest['datum_bis'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ort" class="form-label">Ort</label>
                            <input type="text" class="form-control" id="ort" name="ort"
                                   value="<?php echo htmlspecialchars($fest['ort'] ?? ''); ?>"
                                   placeholder="z.B. Gemeindeplatz, Festzelt">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="adresse" class="form-label">Adresse</label>
                            <input type="text" class="form-control" id="adresse" name="adresse"
                                   value="<?php echo htmlspecialchars($fest['adresse'] ?? ''); ?>"
                                   placeholder="Straße, PLZ Ort">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="beschreibung" class="form-label">Beschreibung / Notizen</label>
                        <textarea class="form-control" id="beschreibung" name="beschreibung" rows="4"
                                  placeholder="Allgemeine Informationen zum Fest..."><?php echo htmlspecialchars($fest['beschreibung'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="<?php echo $isEdit ? 'fest_detail.php?id='.$id : 'feste.php'; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Abbrechen
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Speichern
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-toggle-on"></i> Status</h5></div>
                <div class="card-body">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="geplant"      <?php echo ($fest['status'] ?? 'geplant') === 'geplant'      ? 'selected' : ''; ?>>Geplant</option>
                        <option value="aktiv"         <?php echo ($fest['status'] ?? '') === 'aktiv'         ? 'selected' : ''; ?>>Aktiv</option>
                        <option value="abgeschlossen" <?php echo ($fest['status'] ?? '') === 'abgeschlossen' ? 'selected' : ''; ?>>Abgeschlossen</option>
                        <option value="abgesagt"      <?php echo ($fest['status'] ?? '') === 'abgesagt'      ? 'selected' : ''; ?>>Abgesagt</option>
                    </select>
                </div>
            </div>

            <?php if ($isEdit && Session::checkPermission('fest', 'schreiben')): ?>
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-copy"></i> Vorjahr kopieren</h5></div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Daten aus einem anderen Fest in dieses Fest kopieren.</p>
                    <a href="fest_kopieren.php?ziel_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-copy"></i> Kopieren von…
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
