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
        'farbe'             => $_POST['farbe'] ?? '',
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

// Farbe: gespeicherte, bei Fehler die eingegebene, sonst die bisher im Dienstplan gezeigte bzw. die nächste freie Vorschlagsfarbe
$farben = $stationObj->farbenFuerFest((int)$festId);
if (isset($data['farbe']) && FestStation::farbeOderNull($data['farbe'])) {
    $farbe = FestStation::farbeOderNull($data['farbe']);
} elseif ($isEdit) {
    $farbe = $farben[(int)$id] ?? FestStation::PALETTE[0];
} else {
    $frei = array_values(array_diff(FestStation::PALETTE, $farben));
    $farbe = $frei[0] ?? FestStation::PALETTE[count($farben) % count(FestStation::PALETTE)];
}
$farbeVergeben = array_diff_key($farben, $isEdit ? [(int)$id => 1] : []); // Farben der anderen Stationen

include 'includes/header.php';
?>
<style>
    .farb-wahl { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .farb-wahl .muster { width: 32px; height: 32px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; padding: 0;
                         box-shadow: inset 0 0 0 2px rgba(255,255,255,.35); position: relative; }
    .farb-wahl .muster.aktiv { border-color: var(--bs-body-color); box-shadow: 0 0 0 2px var(--bs-body-bg) inset; }
    .farb-wahl .muster.vergeben::after { content: ''; position: absolute; right: -2px; bottom: -2px; width: 10px; height: 10px; border-radius: 50%;
                         background: var(--bs-body-bg); border: 2px solid currentColor; }
    .farb-wahl input[type=color] { width: 44px; height: 34px; padding: 2px; }
    .farb-vorschau { display: inline-flex; align-items: center; gap: 8px; padding: 4px 10px 4px 4px; border-radius: 6px; font-size: 13px; }
    .farb-vorschau .balken { width: 70px; height: 22px; border-radius: 4px; color: #fff; font-size: 11px; font-weight: 600;
                             display: flex; align-items: center; justify-content: center; }
</style>

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
                            <div class="form-text">Liegt „Bis" vor „Von" (z.B. 16:00–02:00), wird automatisch der Folgetag angenommen.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="farbe">Farbe im Dienstplan</label>
                        <div class="farb-wahl">
                            <?php foreach (FestStation::PALETTE as $p):
                                $vergeben = array_search($p, $farbeVergeben, true) !== false; ?>
                            <button type="button" class="muster<?php echo $p === $farbe ? ' aktiv' : ''; ?><?php echo $vergeben ? ' vergeben' : ''; ?>"
                                    style="background:<?php echo $p; ?>;color:<?php echo $p; ?>" data-farbe="<?php echo $p; ?>"
                                    title="<?php echo $vergeben ? 'Diese Farbe hat schon eine andere Station' : 'Farbe wählen'; ?>" aria-label="Farbe <?php echo $p; ?>"></button>
                            <?php endforeach; ?>
                            <input type="color" class="form-control form-control-color" id="farbe" name="farbe" value="<?php echo htmlspecialchars($farbe); ?>" title="Eigene Farbe wählen">
                            <span class="farb-vorschau"><span class="balken" id="farbVorschau" style="background:<?php echo htmlspecialchars($farbe); ?>">08:00</span>
                                <span class="text-muted small">so erscheint die Schicht im Dienstplan</span></span>
                        </div>
                        <div class="form-text">Farben mit kleinem Kreis verwendet schon eine andere Station dieses Festes.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="sortierung" class="form-label">Sortierung</label>
                        <input type="number" class="form-control" id="sortierung" name="sortierung"
                               value="<?php echo (int)($station['sortierung'] ?? 100); ?>" min="1">
                        <div class="form-text">Kleinere Zahlen erscheinen zuerst.</div>
                    </div>
                </div>
                <div class="card-footer page-actions-sticky">
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
<script>
(function () {
    var feld = document.getElementById('farbe'), vorschau = document.getElementById('farbVorschau');
    var muster = document.querySelectorAll('.farb-wahl .muster');
    function setzen(farbe) {
        feld.value = farbe; vorschau.style.background = farbe;
        muster.forEach(function (m) { m.classList.toggle('aktiv', m.dataset.farbe === farbe.toLowerCase()); });
    }
    muster.forEach(function (m) { m.addEventListener('click', function () { setzen(m.dataset.farbe); }); });
    feld.addEventListener('input', function () { setzen(feld.value); });
})();
</script>
