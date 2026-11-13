<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('probe', 'schreiben');

$db      = Database::getInstance();
$notenId = (int)($_GET['noten_id'] ?? $_POST['noten_id'] ?? 0);

if (!$notenId) {
    header('Location: probe_leiter.php');
    exit;
}

$noten = $db->fetchOne("SELECT * FROM noten WHERE id = ?", [$notenId]);
if (!$noten) {
    Session::setFlashMessage('danger', 'Noten nicht gefunden.');
    header('Location: probe_leiter.php');
    exit;
}

// Stimme hinzufügen / aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stimme'])) {
    $name     = trim($_POST['name']    ?? '');
    $dateiId  = (int)($_POST['datei_id'] ?? 0) ?: null;
    $stimmeId = (int)($_POST['stimme_id'] ?? 0);

    if ($name) {
        if ($stimmeId) {
            $db->query(
                "UPDATE noten_stimmen SET name=?, datei_id=? WHERE id=? AND noten_id=?",
                [$name, $dateiId, $stimmeId, $notenId]
            );
        } else {
            $nextOrder = $db->fetchOne(
                "SELECT COALESCE(MAX(reihenfolge),0)+1 as n FROM noten_stimmen WHERE noten_id=?",
                [$notenId]
            )['n'] ?? 1;
            $db->query(
                "INSERT INTO noten_stimmen (noten_id, datei_id, name, seite_von, seite_bis, reihenfolge)
                 VALUES (?,?,?,1,9999,?)",
                [$notenId, $dateiId, $name, $nextOrder]
            );
        }
        Session::setFlashMessage('success', 'Stimme gespeichert.');
    }
    header("Location: probe_stimmen.php?noten_id={$notenId}");
    exit;
}

// Stimme löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_stimme'])) {
    $stimmeId = (int)($_POST['stimme_id'] ?? 0);
    if ($stimmeId) {
        $db->query("DELETE FROM noten_stimmen WHERE id=? AND noten_id=?", [$stimmeId, $notenId]);
        Session::setFlashMessage('success', 'Stimme gelöscht.');
    }
    header("Location: probe_stimmen.php?noten_id={$notenId}");
    exit;
}

$stimmen = $db->fetchAll(
    "SELECT * FROM noten_stimmen WHERE noten_id=? ORDER BY reihenfolge, name",
    [$notenId]
);

$dateien = $db->fetchAll(
    "SELECT id, original_name, dateigroesse FROM noten_dateien WHERE noten_id=? ORDER BY sortierung",
    [$notenId]
);

// Stimme zum Bearbeiten
$editStimme = null;
if (isset($_GET['edit'])) {
    $editStimme = $db->fetchOne(
        "SELECT * FROM noten_stimmen WHERE id=? AND noten_id=?",
        [(int)$_GET['edit'], $notenId]
    );
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="bi bi-sliders"></i>
        Stimmen — <span class="text-muted fw-normal"><?= htmlspecialchars($noten['titel']) ?></span>
    </h1>
    <a href="probe_leiter.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<div class="row g-4">
    <!-- Stimmen-Liste -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">Vorhandene Stimmen</div>
            <div class="card-body p-0">
                <?php if (empty($stimmen)): ?>
                <p class="text-muted p-3 mb-0">Noch keine Stimmen. Rechts anlegen.</p>
                <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Stimme</th>
                            <th>PDF-Datei</th>
                            <th class="text-end">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stimmen as $st): ?>
                        <?php
                            $datei = null;
                            foreach ($dateien as $d) {
                                if ($d['id'] == $st['datei_id']) { $datei = $d; break; }
                            }
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($st['name']) ?></strong></td>
                            <td>
                                <?php if ($datei): ?>
                                <small class="text-muted"><?= htmlspecialchars($datei['original_name']) ?></small>
                                <?php else: ?>
                                <small class="text-muted">–</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="probe_stimmen.php?noten_id=<?= $notenId ?>&edit=<?= $st['id'] ?>"
                                   class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="noten_id"  value="<?= $notenId ?>">
                                    <input type="hidden" name="stimme_id" value="<?= $st['id'] ?>">
                                    <button type="submit" name="delete_stimme" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Stimme und alle Zeichnungen löschen?')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stimme anlegen / bearbeiten -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <?= $editStimme ? 'Stimme bearbeiten' : 'Neue Stimme' ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="noten_id" value="<?= $notenId ?>">
                    <?php if ($editStimme): ?>
                    <input type="hidden" name="stimme_id" value="<?= $editStimme['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required
                               placeholder="z.B. 1. Klarinette"
                               value="<?= htmlspecialchars($editStimme['name'] ?? '') ?>">
                    </div>

                    <?php if (!empty($dateien)): ?>
                    <div class="mb-3">
                        <label class="form-label">PDF-Datei</label>
                        <select name="datei_id" class="form-select">
                            <option value="">– ohne Zuordnung –</option>
                            <?php foreach ($dateien as $d): ?>
                            <option value="<?= $d['id'] ?>"
                                <?= ($editStimme && $editStimme['datei_id'] == $d['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['original_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Welche PDF-Datei enthält diese Stimme?</div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        Keine PDF-Dateien hinterlegt.
                        <a href="noten_bearbeiten.php?id=<?= $notenId ?>">PDFs hochladen →</a>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <button type="submit" name="save_stimme" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i>
                            <?= $editStimme ? 'Speichern' : 'Anlegen' ?>
                        </button>
                        <?php if ($editStimme): ?>
                        <a href="probe_stimmen.php?noten_id=<?= $notenId ?>" class="btn btn-secondary">Abbrechen</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
