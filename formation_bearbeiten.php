<?php
require_once 'config.php';
require_once 'includes.php';
Session::requireLogin();
Session::requirePermission('formationen', 'schreiben');

$formationObj = new Formation();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$f = $id ? $formationObj->getById($id) : null;
$isNew = !$f;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'         => trim($_POST['name'] ?? ''),
        'kuerzel'      => trim($_POST['kuerzel'] ?? '') ?: null,
        'farbe'        => $_POST['farbe'] ?? '#4471A3',
        'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
        'aktiv'        => isset($_POST['aktiv']) ? 1 : 0,
    ];

    if (!$data['name']) $errors[] = 'Name ist erforderlich.';
    if ($data['farbe'] && !preg_match('/^#[0-9a-fA-F]{6}$/', $data['farbe'])) {
        $data['farbe'] = '#4471A3';
    }

    if (empty($errors)) {
        if ($isNew) {
            $newId = $formationObj->create($data);
            Session::setFlashMessage('success', 'Formation "' . htmlspecialchars($data['name']) . '" erstellt.');
            header('Location: formation_mitglieder.php?id=' . $newId);
        } else {
            $formationObj->update($id, $data);
            Session::setFlashMessage('success', 'Formation gespeichert.');
            header('Location: formationen.php');
        }
        exit;
    }
}

$title = $isNew ? 'Neue Formation' : 'Formation bearbeiten';
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-collection"></i> <?php echo $title; ?></h1>
    <a href="formationen.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php foreach ($errors as $e): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($e); ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:600px">
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Name *</label>
                <input type="text" class="form-control" name="name" required
                       value="<?php echo htmlspecialchars($f['name'] ?? ''); ?>">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Kürzel <span class="text-muted">(optional)</span></label>
                    <input type="text" class="form-control" name="kuerzel" maxlength="10"
                           value="<?php echo htmlspecialchars($f['kuerzel'] ?? ''); ?>"
                           placeholder="z.B. BO, JK">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Farbe</label>
                    <div class="input-group">
                        <input type="color" class="form-control form-control-color" name="farbe"
                               value="<?php echo htmlspecialchars($f['farbe'] ?? '#4471A3'); ?>"
                               style="width:50px;padding:4px">
                        <input type="text" class="form-control" id="farbe_text"
                               value="<?php echo htmlspecialchars($f['farbe'] ?? '#4471A3'); ?>"
                               readonly style="font-family:monospace">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Beschreibung</label>
                <textarea class="form-control" name="beschreibung" rows="3"><?php echo htmlspecialchars($f['beschreibung'] ?? ''); ?></textarea>
            </div>
            <div class="mb-4">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="aktiv" id="aktiv"
                           <?php echo ($f['aktiv'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="aktiv">Formation aktiv</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg"></i> <?php echo $isNew ? 'Erstellen' : 'Speichern'; ?>
            </button>
        </form>
    </div>
</div>

<script>
document.querySelector('input[type=color]').addEventListener('input', function() {
    document.getElementById('farbe_text').value = this.value;
});
</script>

<?php include 'includes/footer.php'; ?>
