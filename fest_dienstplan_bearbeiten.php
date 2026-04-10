<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'schreiben');

$dpObj      = new FestDienstplan();
$stationObj = new FestStation();
$maObj      = new FestMitarbeiter();
$festObj    = new Fest();

$id         = isset($_GET['id']) ? (int)$_GET['id'] : null;
$festId     = isset($_GET['fest_id']) ? (int)$_GET['fest_id'] : null;
$preStation = isset($_GET['station_id']) ? (int)$_GET['station_id'] : null;
$preDatum   = $_GET['datum'] ?? null;
$isEdit     = $id !== null;

if ($isEdit) {
    $dp = $dpObj->getById($id);
    if (!$dp) { Session::setFlashMessage('danger', 'Schicht nicht gefunden.'); header('Location: feste.php'); exit; }
    $festId = $dp['fest_id'];
} else {
    $dp = [];
}

$fest = $festObj->getById($festId);
if (!$fest) { Session::setFlashMessage('danger', 'Fest nicht gefunden.'); header('Location: feste.php'); exit; }

$stationen   = $stationObj->getByFest($festId);
$mitarbeiter = $maObj->getByFest($festId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'fest_id'        => $festId,
        'station_id'     => (int)$_POST['station_id'],
        'mitarbeiter_id' => (int)$_POST['mitarbeiter_id'],
        'datum'          => $_POST['datum'] ?? '',
        'zeit_von'       => $_POST['zeit_von'] ?? '',
        'zeit_bis'       => $_POST['zeit_bis'] ?? '',
        'notizen'        => trim($_POST['notizen'] ?? ''),
    ];

    if (!$data['station_id'] || !$data['mitarbeiter_id'] || !$data['datum'] || !$data['zeit_von'] || !$data['zeit_bis']) {
        $error = 'Bitte alle Pflichtfelder ausfüllen.';
    } else {
        try {
            if ($isEdit) {
                $dpObj->update($id, $data);
                Session::setFlashMessage('success', 'Schicht aktualisiert.');
            } else {
                $dpObj->create($data);
                Session::setFlashMessage('success', 'Schicht eingetragen.');
            }
            header('Location: fest_dienstplan.php?fest_id=' . $festId . '&datum=' . $data['datum']); exit;
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
        <li class="breadcrumb-item"><a href="fest_dienstplan.php?fest_id=<?php echo $festId; ?>">Dienstplan</a></li>
        <li class="breadcrumb-item active"><?php echo $isEdit ? 'Bearbeiten' : 'Neu'; ?></li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-table"></i> <?php echo $isEdit ? 'Schicht bearbeiten' : 'Schicht eintragen'; ?></h1>
    <a href="fest_dienstplan.php?fest_id=<?php echo $festId; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (empty($stationen)): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Bitte zuerst Stationen anlegen.</div>
<?php elseif (empty($mitarbeiter)): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Bitte zuerst Mitarbeiter hinzufügen.</div>
<?php else: ?>
<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Schicht</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="station_id" class="form-label">Station <span class="text-danger">*</span></label>
                        <select class="form-select" id="station_id" name="station_id" required>
                            <option value="">-- Station wählen --</option>
                            <?php foreach ($stationen as $s): ?>
                            <option value="<?php echo $s['id']; ?>"
                                    <?php echo ($dp['station_id'] ?? $preStation) == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="mitarbeiter_id" class="form-label">Mitarbeiter <span class="text-danger">*</span></label>
                        <select class="form-select" id="mitarbeiter_id" name="mitarbeiter_id" required>
                            <option value="">-- Mitarbeiter wählen --</option>
                            <?php foreach ($mitarbeiter as $m): ?>
                            <option value="<?php echo $m['id']; ?>"
                                    <?php echo ($dp['mitarbeiter_id'] ?? '') == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['vollname']); ?>
                                <?php echo $m['funktion'] ? ' (' . htmlspecialchars($m['funktion']) . ')' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="datum" class="form-label">Datum <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="datum" name="datum" required
                                   value="<?php echo htmlspecialchars($dp['datum'] ?? $preDatum ?? ''); ?>"
                                   min="<?php echo $fest['datum_von']; ?>"
                                   max="<?php echo $fest['datum_bis'] ?: $fest['datum_von']; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="zeit_von" class="form-label">Von <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="zeit_von" name="zeit_von" required
                                   value="<?php echo htmlspecialchars($dp['zeit_von'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="zeit_bis" class="form-label">Bis <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="zeit_bis" name="zeit_bis" required
                                   value="<?php echo htmlspecialchars($dp['zeit_bis'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notizen" class="form-label">Notizen</label>
                        <input type="text" class="form-control" id="notizen" name="notizen"
                               value="<?php echo htmlspecialchars($dp['notizen'] ?? ''); ?>">
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="fest_dienstplan.php?fest_id=<?php echo $festId; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Abbrechen
                        </a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
