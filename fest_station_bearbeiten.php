<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'schreiben');

$stationObj = new FestStation();
$festObj    = new Fest();

$id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$festId  = isset($_GET['fest_id']) ? (int)$_GET['fest_id'] : null;
$isEdit  = $id !== null;

if ($isEdit) {
    $station = $stationObj->getById($id);
    if (!$station) { Session::setFlashMessage('danger', 'Station nicht gefunden.'); header('Location: feste.php'); exit; }
    $festId = $station['fest_id'];
} else {
    $station = [];
}

$fest = $festObj->getById($festId);
if (!$fest) { Session::setFlashMessage('danger', 'Fest nicht gefunden.'); header('Location: feste.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'fest_id'           => $festId,
        'name'              => trim($_POST['name'] ?? ''),
        'beschreibung'      => trim($_POST['beschreibung'] ?? ''),
        'benoetigte_helfer' => (int)($_POST['benoetigte_helfer'] ?? 1),
        'oeffnung_von'      => $_POST['oeffnung_von'] ?? '',
        'oeffnung_bis'      => $_POST['oeffnung_bis'] ?? '',
        'sortierung'        => (int)($_POST['sortierung'] ?? 100),
    ];

    if (empty($data['name'])) {
        $error = 'Bitte einen Namen eingeben.';
    } else {
        try {
            if ($isEdit) {
                $stationObj->update($id, $data);
                Session::setFlashMessage('success', 'Station aktualisiert.');
            } else {
                $stationObj->create($data);
                Session::setFlashMessage('success', 'Station angelegt.');
            }
            header('Location: fest_stationen.php?fest_id=' . $festId); exit;
        } catch (\Throwable $e) {
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
        <li class="breadcrumb-item"><a href="fest_stationen.php?fest_id=<?php echo $festId; ?>">Stationen</a></li>
        <li class="breadcrumb-item active"><?php echo $isEdit ? 'Bearbeiten' : 'Neu'; ?></li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-shop"></i> <?php echo $isEdit ? 'Station bearbeiten' : 'Station hinzufügen'; ?></h1>
    <a href="fest_stationen.php?fest_id=<?php echo $festId; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Station</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required
                               value="<?php echo htmlspecialchars($station['name'] ?? ''); ?>"
                               placeholder="z.B. Getränkestand, Küche, Einlass, Kasse">
                    </div>
                    <div class="mb-3">
                        <label for="beschreibung" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="beschreibung" name="beschreibung" rows="3"
                                  placeholder="Aufgaben und Besonderheiten dieser Station..."><?php echo htmlspecialchars($station['beschreibung'] ?? ''); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="benoetigte_helfer" class="form-label">Benötigte Helfer</label>
                            <input type="number" class="form-control" id="benoetigte_helfer" name="benoetigte_helfer"
                                   value="<?php echo (int)($station['benoetigte_helfer'] ?? 1); ?>" min="1" max="99">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="oeffnung_von" class="form-label">Öffnung von</label>
                            <input type="time" class="form-control" id="oeffnung_von" name="oeffnung_von"
                                   value="<?php echo htmlspecialchars($station['oeffnung_von'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="oeffnung_bis" class="form-label">Öffnung bis</label>
                            <input type="time" class="form-control" id="oeffnung_bis" name="oeffnung_bis"
                                   value="<?php echo htmlspecialchars($station['oeffnung_bis'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="sortierung" class="form-label">Sortierung</label>
                        <input type="number" class="form-control" id="sortierung" name="sortierung"
                               value="<?php echo (int)($station['sortierung'] ?? 100); ?>" min="1">
                        <div class="form-text">Kleinere Zahlen erscheinen zuerst.</div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="fest_stationen.php?fest_id=<?php echo $festId; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Abbrechen
                        </a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
