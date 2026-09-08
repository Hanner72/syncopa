<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (Session::getRole() !== 'admin') {
    Session::setFlashMessage('danger', 'Nur Administratoren haben Zugriff');
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

$module = [
    'mitglieder'    => 'Mitglieder',
    'ausrueckungen' => 'Ausrückungen',
    'noten'         => 'Noten',
    'probe'         => 'Live (Notenansicht)',
    'instrumente'   => 'Instrumente',
    'uniformen'     => 'Uniformen',
    'finanzen'      => 'Finanzen',
    'formationen'   => 'Formationen',
    'fest'          => 'Festverwaltung',
    'benutzer'      => 'Benutzer',
    'einstellungen' => 'Einstellungen',
];

$rollen = $db->fetchAll("SELECT * FROM rollen WHERE aktiv = 1 ORDER BY sortierung, name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($rollen as $rolle) {
            if ($rolle['ist_admin']) continue;
            $db->execute("DELETE FROM berechtigungen WHERE rolle = ?", [$rolle['name']]);
            foreach ($module as $modul_key => $modul_name) {
                $lesen     = isset($_POST[$rolle['name'] . '_' . $modul_key . '_lesen'])     ? 1 : 0;
                $schreiben = isset($_POST[$rolle['name'] . '_' . $modul_key . '_schreiben']) ? 1 : 0;
                $loeschen  = isset($_POST[$rolle['name'] . '_' . $modul_key . '_loeschen'])  ? 1 : 0;
                if ($lesen || $schreiben || $loeschen) {
                    $db->execute(
                        "INSERT INTO berechtigungen (rolle, modul, lesen, schreiben, loeschen) VALUES (?,?,?,?,?)",
                        [$rolle['name'], $modul_key, $lesen, $schreiben, $loeschen]
                    );
                }
            }
        }
        Session::setFlashMessage('success', 'Berechtigungen gespeichert.');
        header('Location: berechtigungen_matrix.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Alle Berechtigungen laden
$rows = $db->fetchAll("SELECT * FROM berechtigungen");
$bMap = [];
foreach ($rows as $b) {
    $bMap[$b['rolle']][$b['modul']] = $b;
}

include 'includes/header.php';

$flash = Session::getFlashMessage();
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($error)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-0"><i class="bi bi-grid-3x3-gap"></i> Berechtigungs-Matrix</h1>
        <small class="text-muted">Alle Rollen und Module auf einen Blick</small>
    </div>
    <a href="rollen.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Rollen</a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <span class="me-auto">
            <i class="bi bi-eye text-primary me-1"></i><strong>L</strong> = Lesen &nbsp;
            <i class="bi bi-pencil text-warning me-1"></i><strong>S</strong> = Schreiben &nbsp;
            <i class="bi bi-trash text-danger me-1"></i><strong>D</strong> = Löschen
        </span>
        <small class="text-muted">Admin-Rollen haben automatisch vollen Zugriff</small>
    </div>
    <div class="card-body p-0">
        <form method="POST" id="matrixForm">
        <div class="table-responsive">
        <table class="table table-bordered table-sm mb-0 align-middle" id="matrixTable">
            <thead class="table-light">
                <tr>
                    <th class="modul-col">Modul</th>
                    <?php foreach ($rollen as $rolle): ?>
                    <th class="text-center rolle-header">
                        <span class="badge bg-<?= htmlspecialchars($rolle['farbe']) ?>" style="font-size:11px">
                            <?= htmlspecialchars($rolle['name']) ?>
                        </span>
                        <?php if (!$rolle['ist_admin']): ?>
                        <div class="d-flex justify-content-center gap-1 mt-1">
                            <span class="perm-label text-primary" title="Lesen">L</span>
                            <span class="perm-label text-warning" title="Schreiben">S</span>
                            <span class="perm-label text-danger" title="Löschen">D</span>
                        </div>
                        <?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($module as $modul_key => $modul_name): ?>
                <tr>
                    <td class="modul-col fw-semibold"><?= htmlspecialchars($modul_name) ?></td>
                    <?php foreach ($rollen as $rolle): ?>
                    <?php
                        $b         = $bMap[$rolle['name']][$modul_key] ?? null;
                        $lesen     = (int)($b['lesen']     ?? 0);
                        $schreiben = (int)($b['schreiben'] ?? 0);
                        $loeschen  = (int)($b['loeschen']  ?? 0);
                        $rn        = htmlspecialchars($rolle['name']);
                    ?>
                    <td class="text-center px-2">
                        <?php if ($rolle['ist_admin']): ?>
                        <span class="text-muted" style="font-size:11px">Auto</span>
                        <?php else: ?>
                        <div class="d-flex justify-content-center gap-2">
                            <input type="checkbox" class="form-check-input cb-lesen"
                                   name="<?= $rn ?>_<?= $modul_key ?>_lesen"
                                   data-rolle="<?= $rn ?>" data-modul="<?= $modul_key ?>"
                                   title="<?= $rn ?>: <?= $modul_name ?> lesen"
                                   <?= $lesen ? 'checked' : '' ?>>
                            <input type="checkbox" class="form-check-input cb-schreiben"
                                   name="<?= $rn ?>_<?= $modul_key ?>_schreiben"
                                   data-rolle="<?= $rn ?>" data-modul="<?= $modul_key ?>"
                                   title="<?= $rn ?>: <?= $modul_name ?> schreiben"
                                   <?= $schreiben ? 'checked' : '' ?>>
                            <input type="checkbox" class="form-check-input cb-loeschen"
                                   name="<?= $rn ?>_<?= $modul_key ?>_loeschen"
                                   data-rolle="<?= $rn ?>" data-modul="<?= $modul_key ?>"
                                   title="<?= $rn ?>: <?= $modul_name ?> löschen"
                                   <?= $loeschen ? 'checked' : '' ?>>
                        </div>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="p-3 border-top d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Berechtigungen speichern
            </button>
        </div>
        </form>
    </div>
</div>

<style>
.modul-col { min-width: 160px; white-space: nowrap; }
.rolle-header { min-width: 90px; }
.perm-label { font-size: 10px; font-weight: 700; min-width: 16px; display: inline-block; text-align: center; }
#matrixTable .form-check-input { width: 16px; height: 16px; cursor: pointer; }
#matrixTable td, #matrixTable th { vertical-align: middle; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('matrixTable');

    table.addEventListener('change', function (e) {
        var cb = e.target;
        if (!cb.classList.contains('form-check-input')) return;

        var rolle = cb.dataset.rolle;
        var modul = cb.dataset.modul;
        var checked = cb.checked;

        var lesen     = table.querySelector('input[name="' + rolle + '_' + modul + '_lesen"]');
        var schreiben = table.querySelector('input[name="' + rolle + '_' + modul + '_schreiben"]');
        var loeschen  = table.querySelector('input[name="' + rolle + '_' + modul + '_loeschen"]');

        if (cb.classList.contains('cb-schreiben') && checked && lesen)  lesen.checked  = true;
        if (cb.classList.contains('cb-loeschen')  && checked && lesen)  lesen.checked  = true;
        if (cb.classList.contains('cb-loeschen')  && checked && schreiben) schreiben.checked = true;

        if (cb.classList.contains('cb-lesen') && !checked) {
            if (schreiben) schreiben.checked = false;
            if (loeschen)  loeschen.checked  = false;
        }
        if (cb.classList.contains('cb-schreiben') && !checked) {
            if (loeschen) loeschen.checked = false;
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
