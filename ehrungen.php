<?php
// ehrungen.php
// Anstehende und verliehene Ehrungen aller Mitglieder, Regeln für anstehende Ehrungen (nur Admins).
// Verliehene Ehrungen kommen aus dem BMV („BMV Daten holen“ → Mitglieder übernehmen).
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('mitglieder', 'lesen');

$istAdmin = Session::isAdmin();
$bereiche = ['anstehend' => 'Anstehend', 'verliehen' => 'Verliehen'];
if ($istAdmin) {
    $bereiche['regeln'] = 'Regeln';
}
$bereich = $_GET['bereich'] ?? 'anstehend';
if (!isset($bereiche[$bereich])) {
    $bereich = 'anstehend';
}

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $istAdmin && ($_POST['aktion'] ?? '') === 'regeln_speichern') {
    Ehrungen::regelnSpeichern($_POST['regel'] ?? []);
    Ehrungen::gezaehlteMitgliedsartenSpeichern(array_keys($_POST['mitgliedsart'] ?? []));
    Session::setFlashMessage('success', 'Regeln gespeichert.');
    header('Location: ehrungen.php?bereich=regeln');
    exit;
}

$diesesJahr = (int)date('Y');

if ($bereich === 'anstehend') {
    $bisJahr = (int)($_GET['bis'] ?? $diesesJahr + 1);
    if ($bisJahr < $diesesJahr || $bisJahr > $diesesJahr + 10) {
        $bisJahr = $diesesJahr + 1;
    }
    $regeln = Ehrungen::regeln();
    $liste = Ehrungen::anstehendBis($bisJahr);
} elseif ($bereich === 'verliehen') {
    $jahre = array_column($db->fetchAll(
        "SELECT DISTINCT YEAR(von) AS jahr FROM bmv_mitglied_daten WHERE art = 'ehrung' AND von IS NOT NULL ORDER BY jahr DESC"
    ), 'jahr');
    $jahr = isset($_GET['jahr']) && $_GET['jahr'] !== '' ? (int)$_GET['jahr'] : null;
    $sql = "SELECT d.*, m.vorname, m.nachname, m.status FROM bmv_mitglied_daten d
            JOIN mitglieder m ON m.id = d.mitglied_id WHERE d.art = 'ehrung'";
    $params = [];
    if ($jahr) {
        $sql .= " AND YEAR(d.von) = ?";
        $params[] = $jahr;
    }
    $verliehen = $db->fetchAll($sql . " ORDER BY d.von DESC, m.nachname", $params);
} else {
    $bezeichnungen = BmvZusatzdaten::bezeichnungen();
    $ehrungCodes = array_unique(array_merge(
        array_keys($bezeichnungen['ehrung'] ?? []),
        array_map('intval', array_column($db->fetchAll("SELECT DISTINCT code FROM bmv_mitglied_daten WHERE art = 'ehrung' AND code IS NOT NULL"), 'code'))
    ));
    sort($ehrungCodes);
    $regelNachCode = array_column(Ehrungen::regeln(false), null, 'ehrung_code');
    $anzahlVerliehen = [];
    foreach ($db->fetchAll("SELECT code, COUNT(*) AS anzahl FROM bmv_mitglied_daten WHERE art = 'ehrung' GROUP BY code") as $r) {
        $anzahlVerliehen[(int)$r['code']] = (int)$r['anzahl'];
    }
    $mitgliedsartCodes = array_unique(array_merge(
        array_keys($bezeichnungen['mitgliedsart'] ?? []),
        array_map('intval', array_column($db->fetchAll("SELECT DISTINCT code FROM bmv_mitglied_daten WHERE art = 'mitgliedsart' AND code IS NOT NULL"), 'code'))
    ));
    sort($mitgliedsartCodes);
    $gezaehlt = Ehrungen::gezaehlteMitgliedsarten();
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-award"></i> Ehrungen</h1>
</div>

<ul class="nav nav-tabs mb-4">
    <?php foreach ($bereiche as $schluessel => $label): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo $schluessel === $bereich ? 'active' : ''; ?>" href="ehrungen.php?bereich=<?php echo $schluessel; ?>"><?php echo $label; ?></a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if ($bereich === 'anstehend'): ?>

<form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <input type="hidden" name="bereich" value="anstehend">
    <label for="bis" class="form-label mb-0">Fällig bis Ende</label>
    <select name="bis" id="bis" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <?php for ($j = $diesesJahr; $j <= $diesesJahr + 5; $j++): ?>
        <option value="<?php echo $j; ?>" <?php echo $j === $bisJahr ? 'selected' : ''; ?>><?php echo $j; ?></option>
        <?php endfor; ?>
    </select>
    <span class="text-muted small">Bereits erreichte, aber noch nicht verliehene Ehrungen werden immer angezeigt.</span>
</form>

<?php if (!$regeln): ?>
<div class="alert alert-permanent alert-info">
    <i class="bi bi-info-circle"></i> Es sind noch keine Regeln eingetragen, ab wie vielen Mitgliedsjahren welche Ehrung ansteht.
    <?php if ($istAdmin): ?><a href="ehrungen.php?bereich=regeln">Regeln eintragen</a><?php else: ?>Bitte einen Administrator, die Regeln einzutragen.<?php endif; ?>
</div>
<?php elseif (!$liste): ?>
<div class="alert alert-permanent alert-success"><i class="bi bi-check-circle"></i> Bis Ende <?php echo $bisJahr; ?> stehen keine Ehrungen an.</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th class="ps-3">Erreicht am</th><th>Mitglied</th><th>Ehrung</th><th class="text-end">Nötig</th><th class="text-end pe-3">Jahre heute</th></tr></thead>
            <tbody>
            <?php foreach ($liste as $e): ?>
            <tr>
                <td class="ps-3">
                    <?php echo $e['datum']->format('d.m.Y'); ?>
                    <?php if ($e['faellig']): ?><span class="badge bg-danger ms-1">fällig</span><?php endif; ?>
                </td>
                <td><a href="mitglied_detail.php?id=<?php echo (int)$e['mitglied']['id']; ?>"><?php echo htmlspecialchars($e['mitglied']['nachname'] . ' ' . $e['mitglied']['vorname']); ?></a></td>
                <td><?php echo htmlspecialchars($e['name']); ?></td>
                <td class="text-end"><?php echo $e['jahre']; ?> J.</td>
                <td class="text-end pe-3"><?php echo number_format($e['mitgliedsjahre'], 1, ',', ''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<p class="text-muted small mt-2">
    Berechnet aus den Mitgliedsarten im BMV. Bei laufender Mitgliedschaft ist das Datum hochgerechnet.
    Ausgetretene Mitglieder werden nicht angezeigt.
</p>
<?php endif; ?>

<?php elseif ($bereich === 'verliehen'): ?>

<form method="GET" class="d-flex align-items-center gap-2 mb-3">
    <input type="hidden" name="bereich" value="verliehen">
    <label for="jahr" class="form-label mb-0">Jahr</label>
    <select name="jahr" id="jahr" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">Alle Jahre</option>
        <?php foreach ($jahre as $j): ?>
        <option value="<?php echo (int)$j; ?>" <?php echo (int)$j === $jahr ? 'selected' : ''; ?>><?php echo (int)$j; ?></option>
        <?php endforeach; ?>
    </select>
    <span class="text-muted small"><?php echo count($verliehen); ?> Ehrungen</span>
</form>

<?php if (!$verliehen): ?>
<div class="alert alert-permanent alert-info"><i class="bi bi-info-circle"></i> Keine Ehrungen vorhanden. Ehrungen kommen aus dem BMV über „BMV Daten holen“.</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th class="ps-3">Datum</th><th>Mitglied</th><th>Ehrung</th><th class="pe-3">Bemerkung</th></tr></thead>
            <tbody>
            <?php foreach ($verliehen as $e): ?>
            <tr>
                <td class="ps-3"><?php echo $e['von'] ? date('d.m.Y', strtotime($e['von'])) : '–'; ?></td>
                <td>
                    <a href="mitglied_detail.php?id=<?php echo (int)$e['mitglied_id']; ?>"><?php echo htmlspecialchars($e['nachname'] . ' ' . $e['vorname']); ?></a>
                    <?php if ($e['status'] === 'ausgetreten'): ?><span class="badge bg-secondary ms-1">ausgetreten</span><?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars(BmvZusatzdaten::name('ehrung', $e['code'])); ?></td>
                <td class="pe-3 text-muted small"><?php echo htmlspecialchars($e['bemerkung'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php else: /* Regeln (nur Admins) */ ?>

<form method="POST">
    <input type="hidden" name="aktion" value="regeln_speichern">

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Ab wie vielen Mitgliedsjahren?</h5></div>
        <div class="card-body">
            <p class="text-muted small">
                Trag bei jeder Ehrung ein, nach wie vielen Mitgliedsjahren sie ansteht. Leer lassen = wird nicht berechnet.
                Hat ein Mitglied schon eine Ehrung mit gleich vielen oder mehr Jahren, gelten die kleineren als erledigt.
                „Vorschlagen“ ausschalten bei alten Nummern, die nicht mehr vergeben werden: Sie zählen dann nur als erhalten.
                Ehrennadeln und Verdienstkreuze hängen von Funktionen ab und werden nicht berechnet.
                Fehlen Namen, kannst du sie unter <a href="bmv_daten.php?bereich=bezeichnungen">BMV Daten holen → Bezeichnungen</a> eintragen.
            </p>
            <?php if (!$ehrungCodes): ?>
            <div class="alert alert-permanent alert-info mb-0">Noch keine Ehrungen vorhanden. Bitte zuerst die Mitglieder aus dem BMV übernehmen.</div>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Ehrung</th><th class="text-end">Bisher verliehen</th><th style="width:140px">Nach Jahren</th><th class="text-center">Vorschlagen</th></tr></thead>
                <tbody>
                <?php foreach ($ehrungCodes as $code):
                    $r = $regelNachCode[$code] ?? null; ?>
                <tr>
                    <td><?php echo htmlspecialchars(BmvZusatzdaten::name('ehrung', $code)); ?></td>
                    <td class="text-end text-muted"><?php echo $anzahlVerliehen[$code] ?? 0; ?></td>
                    <td>
                        <input type="number" min="1" max="99" class="form-control form-control-sm"
                               name="regel[<?php echo $code; ?>][jahre]" value="<?php echo $r ? (int)$r['jahre'] : ''; ?>">
                    </td>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input" name="regel[<?php echo $code; ?>][aktiv]" value="1"
                               <?php echo (!$r || $r['aktiv']) ? 'checked' : ''; ?>>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Was zählt als Mitgliedsjahr?</h5></div>
        <div class="card-body">
            <p class="text-muted small">
                Mitgliedsjahre werden aus den Mitgliedsarten im BMV berechnet. Wähle, welche Mitgliedsarten zählen
                (z. B. nur „aktiv“). Ist nichts gewählt, zählen alle.
            </p>
            <?php if (!$mitgliedsartCodes): ?>
            <p class="text-muted mb-0">Noch keine Mitgliedsarten vorhanden.</p>
            <?php else: ?>
            <?php foreach ($mitgliedsartCodes as $code): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="ma<?php echo $code; ?>" name="mitgliedsart[<?php echo $code; ?>]" value="1"
                       <?php echo in_array($code, $gezaehlt, true) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="ma<?php echo $code; ?>"><?php echo htmlspecialchars(BmvZusatzdaten::name('mitgliedsart', $code)); ?></label>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex justify-content-end page-actions-sticky">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Regeln speichern</button>
    </div>
</form>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
