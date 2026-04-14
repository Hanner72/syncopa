<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'schreiben');

$eObj    = new FestEinkauf();
$festObj = new Fest();

$id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$festId  = isset($_GET['fest_id']) ? (int)$_GET['fest_id'] : null;
$isEdit  = $id !== null;

if ($isEdit) {
    $einkauf = $eObj->getById($id);
    if (!$einkauf) { Session::setFlashMessage('danger', 'Einkauf nicht gefunden.'); header('Location: feste.php'); exit; }
    $festId  = $einkauf['fest_id'];
} else {
    $einkauf = [];
}

$fest = $festObj->getById($festId);
if (!$fest) { Session::setFlashMessage('danger', 'Fest nicht gefunden.'); header('Location: feste.php'); exit; }

$kategorien  = $eObj->getKategorien();
$lieferanten = $eObj->getLieferanten($festId);
$stationObj  = new FestStation();
$stationen   = $stationObj->getByFest($festId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'fest_id'      => $festId,
        'kategorie_id' => $_POST['kategorie_id'] ?: null,
        'station_id'   => $_POST['station_id']   ?: null,
        'bezeichnung'  => trim($_POST['bezeichnung'] ?? ''),
        'menge'        => $_POST['menge'] !== '' ? (int)$_POST['menge'] : null,
        'einheit'      => trim($_POST['einheit'] ?? ''),
        'preis_gesamt' => $_POST['preis_gesamt'] !== '' ? (float)$_POST['preis_gesamt'] : null,
        'lieferant'    => trim($_POST['lieferant'] ?? ''),
        'status'       => $_POST['status'] ?? 'geplant',
        'ist_vorlage'  => isset($_POST['ist_vorlage']) ? 1 : 0,
        'notizen'      => trim($_POST['notizen'] ?? ''),
        'erstellt_von' => Session::getUserId(),
    ];

    if (empty($data['bezeichnung'])) {
        $error = 'Bitte eine Bezeichnung eingeben.';
    } else {
        try {
            if ($isEdit) {
                $eObj->update($id, $data);
                Session::setFlashMessage('success', 'Einkauf aktualisiert.');
            } else {
                $eObj->create($data);
                Session::setFlashMessage('success', 'Einkauf hinzugefügt.');
            }
            header('Location: fest_einkauefe.php?fest_id=' . $festId); exit;
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
        <li class="breadcrumb-item"><a href="fest_einkauefe.php?fest_id=<?php echo $festId; ?>">Einkäufe</a></li>
        <li class="breadcrumb-item active"><?php echo $isEdit ? 'Bearbeiten' : 'Neu'; ?></li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-cart3"></i> <?php echo $isEdit ? 'Einkauf bearbeiten' : 'Einkauf hinzufügen'; ?></h1>
    <a href="fest_einkauefe.php?fest_id=<?php echo $festId; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Einkauf</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="bezeichnung" class="form-label">Bezeichnung <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="bezeichnung" name="bezeichnung" required
                                   value="<?php echo htmlspecialchars($einkauf['bezeichnung'] ?? ''); ?>"
                                   placeholder="z.B. Bier 0,5l, Einwegbecher, Tischdecken">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="kategorie_id" class="form-label">Kategorie</label>
                            <select class="form-select" id="kategorie_id" name="kategorie_id">
                                <option value="">– Keine –</option>
                                <?php foreach ($kategorien as $k): ?>
                                <option value="<?php echo $k['id']; ?>"
                                        <?php echo ($einkauf['kategorie_id'] ?? '') == $k['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($k['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="station_id" class="form-label">Station</label>
                            <select class="form-select" id="station_id" name="station_id">
                                <option value="">– Keine Station –</option>
                                <?php foreach ($stationen as $st): ?>
                                <option value="<?php echo $st['id']; ?>"
                                        <?php echo ($einkauf['station_id'] ?? '') == $st['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($st['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="menge" class="form-label">Menge</label>
                            <input type="number" class="form-control" id="menge" name="menge"
                                   value="<?php echo htmlspecialchars($einkauf['menge'] !== null ? (int)$einkauf['menge'] : ''); ?>"
                                   step="1" min="0" placeholder="z.B. 50">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="einheit" class="form-label">Einheit</label>
                            <input type="text" class="form-control" id="einheit" name="einheit"
                                   value="<?php echo htmlspecialchars($einkauf['einheit'] ?? ''); ?>"
                                   placeholder="Stück, kg, L, Kiste"
                                   list="einheitList">
                            <datalist id="einheitList">
                                <option value="Stück">
                                <option value="kg">
                                <option value="g">
                                <option value="Liter">
                                <option value="ml">
                                <option value="Kiste">
                                <option value="Palette">
                                <option value="Karton">
                                <option value="Packung">
                            </datalist>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="preis_gesamt" class="form-label">Preis gesamt (€)</label>
                            <input type="number" class="form-control" id="preis_gesamt" name="preis_gesamt"
                                   value="<?php echo htmlspecialchars($einkauf['preis_gesamt'] ?? ''); ?>"
                                   step="0.01" min="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="lieferant" class="form-label">Lieferant</label>
                            <input type="text" class="form-control" id="lieferant" name="lieferant"
                                   value="<?php echo htmlspecialchars($einkauf['lieferant'] ?? ''); ?>"
                                   placeholder="z.B. METRO, Getränkehändler"
                                   list="lieferanten-list" autocomplete="off">
                            <datalist id="lieferanten-list">
                                <?php foreach ($lieferanten as $l): ?>
                                <option value="<?php echo htmlspecialchars($l); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notizen" class="form-label">Notizen</label>
                        <textarea class="form-control" id="notizen" name="notizen" rows="2"><?php echo htmlspecialchars($einkauf['notizen'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="fest_einkauefe.php?fest_id=<?php echo $festId; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Abbrechen
                        </a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Status & Optionen</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="geplant"   <?php echo ($einkauf['status'] ?? 'geplant') === 'geplant'   ? 'selected' : ''; ?>>Geplant</option>
                            <option value="bestellt"  <?php echo ($einkauf['status'] ?? '') === 'bestellt'  ? 'selected' : ''; ?>>Bestellt</option>
                            <option value="erhalten"  <?php echo ($einkauf['status'] ?? '') === 'erhalten'  ? 'selected' : ''; ?>>Erhalten</option>
                            <option value="storniert" <?php echo ($einkauf['status'] ?? '') === 'storniert' ? 'selected' : ''; ?>>Storniert</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ist_vorlage" name="ist_vorlage" value="1"
                               <?php echo !empty($einkauf['ist_vorlage']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ist_vorlage">
                            <i class="bi bi-copy text-success"></i> Als Vorlage markieren
                        </label>
                        <div class="form-text">Wird beim Kopieren in ein neues Fest übernommen.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
