<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('noten', 'lesen');

$db          = Database::getInstance();
$benutzerId  = Session::getUserId();
$formationId = Session::getFormationId();
$buchId      = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Bestehendes Buch laden
$buch = null;
if ($buchId) {
    $buch = $db->fetchOne("SELECT * FROM notenbucher WHERE id = ?", [$buchId]);
    if (!$buch) {
        Session::setFlashMessage('danger', 'Notenbuch nicht gefunden');
        header('Location: notenbucher.php');
        exit;
    }
    // Zugriff prüfen: eigenes Buch oder geteilt + schreiben-Berechtigung
    $darfBearbeiten = $buch['benutzer_id'] == $benutzerId || Session::isAdmin()
        || ($buch['typ'] === 'geteilt' && Session::checkPermission('noten', 'schreiben'));
    if (!$darfBearbeiten) {
        Session::setFlashMessage('danger', 'Kein Zugriff');
        header('Location: notenbucher.php');
        exit;
    }
}

// Notiz aus Buch entfernen
if (isset($_GET['remove_note']) && $buchId) {
    $notenId = (int)$_GET['remove_note'];
    $db->execute("DELETE FROM notenbuch_noten WHERE notenbuch_id = ? AND noten_id = ?", [$buchId, $notenId]);
    header("Location: notenbuch_bearbeiten.php?id={$buchId}");
    exit;
}

// Note zum Buch hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note']) && $buchId) {
    $notenId = (int)($_POST['noten_id'] ?? 0);
    if ($notenId) {
        $maxRf = $db->fetchOne("SELECT COALESCE(MAX(reihenfolge),0)+1 as n FROM notenbuch_noten WHERE notenbuch_id = ?", [$buchId]);
        $db->execute(
            "INSERT IGNORE INTO notenbuch_noten (notenbuch_id, noten_id, reihenfolge) VALUES (?,?,?)",
            [$buchId, $notenId, (int)$maxRf['n']]
        );
    }
    header("Location: notenbuch_bearbeiten.php?id={$buchId}");
    exit;
}

// Buch speichern (Metadaten)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_buch'])) {
    $name        = trim($_POST['name'] ?? '');
    $beschreibung = trim($_POST['beschreibung'] ?? '');
    $typ    = in_array($_POST['typ'] ?? '', ['privat', 'geteilt']) ? $_POST['typ'] : 'privat';
    $formId = null;
    if ($typ === 'geteilt') {
        $formId = (int)($_POST['formation_id'] ?? 0) ?: null;
    }

    if (!$name) {
        $error = 'Name darf nicht leer sein';
    } elseif ($typ === 'geteilt' && !$formId) {
        $error = 'Bitte eine Formation für das geteilte Notenbuch auswählen';
    } else {
        try {
            if ($buchId && $buch) {
                $db->execute(
                    "UPDATE notenbucher SET name=?, beschreibung=?, typ=?, formation_id=? WHERE id=?",
                    [$name, $beschreibung ?: null, $typ, $formId, $buchId]
                );
                Session::setFlashMessage('success', 'Notenbuch gespeichert');
                header("Location: notenbuch_bearbeiten.php?id={$buchId}");
            } else {
                $db->execute(
                    "INSERT INTO notenbucher (name, beschreibung, benutzer_id, typ, formation_id) VALUES (?,?,?,?,?)",
                    [$name, $beschreibung ?: null, $benutzerId, $typ, $formId]
                );
                $newId = $db->lastInsertId();
                Session::setFlashMessage('success', 'Notenbuch angelegt');
                header("Location: notenbuch_bearbeiten.php?id={$newId}");
            }
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Noten im Buch laden (sortierbar)
$notenImBuch = [];
if ($buchId) {
    $notenImBuch = $db->fetchAll(
        "SELECT n.id, n.titel, n.komponist, nn.reihenfolge
         FROM notenbuch_noten nn
         JOIN noten n ON nn.noten_id = n.id
         WHERE nn.notenbuch_id = ?
         ORDER BY nn.reihenfolge, n.titel",
        [$buchId]
    );
}

// Alle anderen Noten (noch nicht im Buch) – gefiltert nach Formation
$notenImBuchIds = array_column($notenImBuch, 'id');
$formFilter     = Formation::getFilterCondition($formationId, 'n');
$formWhere      = $formFilter['condition'] ? "AND {$formFilter['condition']}" : "";
$alleNoten      = $db->fetchAll(
    "SELECT n.id, n.titel, n.komponist,
            (SELECT COUNT(*) FROM noten_stimmen WHERE noten_id = n.id) as anzahl_stimmen
     FROM noten n
     WHERE 1=1 {$formWhere}
     ORDER BY n.titel",
    $formFilter['params']
);
$verfuegbareNoten = array_filter($alleNoten, fn($n) => !in_array($n['id'], $notenImBuchIds));

// Formationen für den "Geteilt"-Selektor (Admins: alle; sonst nur eigene)
if (Session::isAdmin()) {
    $formationen = $db->fetchAll("SELECT * FROM formationen WHERE aktiv = 1 ORDER BY name");
} else {
    $meinFormIds = Session::getFormationIds();
    if ($meinFormIds) {
        $ph = implode(',', array_fill(0, count($meinFormIds), '?'));
        $formationen = $db->fetchAll("SELECT * FROM formationen WHERE aktiv = 1 AND id IN ({$ph}) ORDER BY name", $meinFormIds);
    } else {
        $formationen = [];
    }
}

include 'includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="notenbucher.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h1 class="h2 mb-0">
        <i class="bi bi-journal-plus"></i>
        <?= $buchId ? 'Notenbuch bearbeiten' : 'Neues Notenbuch' ?>
    </h1>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Metadaten -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Buchdetails</h5></div>
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= htmlspecialchars($buch['name'] ?? '') ?>" required autofocus>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sichtbarkeit</label>
                    <div class="d-flex gap-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="typ" id="typ_privat" value="privat"
                                   <?= (!$buch || $buch['typ'] === 'privat') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="typ_privat">
                                <i class="bi bi-person"></i> Privat (nur ich)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="typ" id="typ_geteilt" value="geteilt"
                                   <?= ($buch && $buch['typ'] === 'geteilt') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="typ_geteilt">
                                <i class="bi bi-people"></i> Geteilt (Formation)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6" id="formation-select-wrap"
                     style="<?= ($buch && $buch['typ'] === 'geteilt') ? '' : 'display:none' ?>">
                    <label class="form-label">Formation <span class="text-danger">*</span></label>
                    <?php if (empty($formationen)): ?>
                    <div class="alert alert-warning py-2 mb-0">Du bist keiner Formation zugewiesen.</div>
                    <?php else: ?>
                    <select name="formation_id" class="form-select">
                        <option value="">– Formation wählen –</option>
                        <?php foreach ($formationen as $f): ?>
                        <option value="<?= $f['id'] ?>"
                            <?= ($buch && $buch['formation_id'] == $f['id']) ? 'selected'
                                : (!$buch && $formationId == $f['id'] ? 'selected' : '') ?>>
                            <?= htmlspecialchars($f['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Nur Mitglieder dieser Formation sehen das Buch.</div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">Beschreibung</label>
                    <textarea name="beschreibung" class="form-control" rows="2"><?= htmlspecialchars($buch['beschreibung'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" name="save_buch" class="btn btn-primary">
                    <i class="bi bi-save"></i> Speichern
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($buchId): ?>

<!-- Noten im Buch -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Stücke im Buch</h5>
        <small class="text-muted"><i class="bi bi-grip-vertical"></i> Zum Umsortieren ziehen</small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($notenImBuch)): ?>
        <div class="p-4 text-center text-muted">
            <i class="bi bi-music-note-list" style="font-size:32px;opacity:0.2"></i>
            <p class="mt-2 mb-0">Noch keine Stücke im Buch. Unten hinzufügen.</p>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0" id="buchNotenTable">
            <tbody id="buch-noten-tbody">
                <?php foreach ($notenImBuch as $note): ?>
                <tr data-id="<?= $note['id'] ?>">
                    <td class="drag-handle text-muted ps-3" style="cursor:grab;width:32px;vertical-align:middle">
                        <i class="bi bi-grip-vertical"></i>
                    </td>
                    <td style="vertical-align:middle">
                        <span class="fw-semibold"><?= htmlspecialchars($note['titel']) ?></span>
                        <?php if ($note['komponist']): ?>
                        <span class="text-muted ms-2" style="font-size:12px"><?= htmlspecialchars($note['komponist']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-3" style="vertical-align:middle">
                        <a href="?id=<?= $buchId ?>&remove_note=<?= $note['id'] ?>"
                           class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Stück aus Buch entfernen?')">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="sort-status" class="text-muted small px-3 pb-2" style="min-height:20px"></div>
        <?php endif; ?>
    </div>
</div>

<!-- Verfügbare Noten -->
<?php if (!empty($verfuegbareNoten)): ?>
<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0 flex-grow-1">Stücke hinzufügen</h5>
            <input type="text" id="search-noten" class="form-control form-control-sm" placeholder="Suche…" style="max-width:220px">
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-sm mb-0" id="verfuegbareTable">
            <tbody>
                <?php foreach ($verfuegbareNoten as $note): ?>
                <tr>
                    <td class="ps-3" style="vertical-align:middle">
                        <span class="fw-semibold"><?= htmlspecialchars($note['titel']) ?></span>
                        <?php if ($note['komponist']): ?>
                        <span class="text-muted ms-2" style="font-size:12px"><?= htmlspecialchars($note['komponist']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="width:80px;vertical-align:middle">
                        <span class="badge bg-secondary"><?= (int)$note['anzahl_stimmen'] ?> Stimmen</span>
                    </td>
                    <td class="text-end pe-3" style="width:80px;vertical-align:middle">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="noten_id" value="<?= $note['id'] ?>">
                            <button type="submit" name="add_note" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; // buchId ?>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
<?php if ($buchId && !empty($notenImBuch)): ?>
var sortStatus = document.getElementById('sort-status');
Sortable.create(document.getElementById('buch-noten-tbody'), {
    handle: '.drag-handle',
    animation: 150,
    ghostClass: 'table-active',
    onEnd: function() {
        var rows = document.querySelectorAll('#buch-noten-tbody tr[data-id]');
        var order = Array.from(rows).map(function(tr, idx) {
            return { id: parseInt(tr.dataset.id), pos: idx };
        });
        sortStatus.innerHTML = '<i class="bi bi-arrow-repeat"></i> Speichern…';
        fetch('api/notenbuch_sortierung.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notenbuch_id: <?= $buchId ?>, order: order })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            sortStatus.innerHTML = d.success
                ? '<i class="bi bi-check-circle text-success"></i> Reihenfolge gespeichert'
                : '<i class="bi bi-exclamation-circle text-danger"></i> Fehler';
            setTimeout(function() { sortStatus.innerHTML = ''; }, 2000);
        })
        .catch(function() {
            sortStatus.innerHTML = '<i class="bi bi-exclamation-circle text-danger"></i> Verbindungsfehler';
        });
    }
});
<?php endif; ?>

// Formation-Dropdown ein-/ausblenden
document.querySelectorAll('input[name="typ"]').forEach(function(r) {
    r.addEventListener('change', function() {
        var wrap = document.getElementById('formation-select-wrap');
        if (wrap) wrap.style.display = this.value === 'geteilt' ? '' : 'none';
    });
});

// Live-Suche für verfügbare Noten
var searchInput = document.getElementById('search-noten');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        var q = this.value.toLowerCase();
        document.querySelectorAll('#verfuegbareTable tbody tr').forEach(function(tr) {
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}
</script>
