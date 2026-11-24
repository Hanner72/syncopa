<?php
require_once 'config.php';
require_once 'includes.php';
Session::requireLogin();
Session::requirePermission('formationen', 'lesen');

$formation = new Formation();
$formationen = $formation->getAll();

include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-collection"></i> Formationen</h1>
    <?php if (Session::checkPermission('formationen', 'schreiben')): ?>
    <a href="formation_bearbeiten.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Neue Formation
    </a>
    <?php endif; ?>
</div>

<?php if (empty($formationen)): ?>
<div class="alert alert-info">
    Noch keine Formationen angelegt. <a href="formation_bearbeiten.php">Erste Formation erstellen</a>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Kürzel</th>
                    <th>Mitglieder</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($formationen as $f): ?>
                <tr>
                    <td>
                        <span class="d-inline-block rounded-circle me-2" style="width:12px;height:12px;background:<?php echo htmlspecialchars($f['farbe']); ?>;vertical-align:middle"></span>
                        <strong><?php echo htmlspecialchars($f['name']); ?></strong>
                        <?php if ($f['beschreibung']): ?>
                        <div class="text-muted" style="font-size:11px"><?php echo htmlspecialchars(mb_strimwidth($f['beschreibung'], 0, 80, '…')); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($f['kuerzel'] ?? '–'); ?></td>
                    <td>
                        <a href="formation_mitglieder.php?id=<?php echo $f['id']; ?>" class="badge bg-primary text-decoration-none">
                            <?php echo $f['mitglieder_anzahl']; ?> Mitglieder
                        </a>
                    </td>
                    <td>
                        <?php if ($f['aktiv']): ?>
                        <span class="badge bg-success">Aktiv</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Inaktiv</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (Session::checkPermission('formationen', 'schreiben')): ?>
                        <a href="formation_mitglieder.php?id=<?php echo $f['id']; ?>" class="btn btn-sm btn-outline-secondary me-1" title="Mitglieder zuordnen">
                            <i class="bi bi-people"></i>
                        </a>
                        <a href="formation_bearbeiten.php?id=<?php echo $f['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php endif; ?>
                        <?php if (Session::checkPermission('formationen', 'loeschen')): ?>
                        <button class="btn btn-sm btn-outline-danger ms-1" onclick="deleteFormation(<?php echo $f['id']; ?>, '<?php echo htmlspecialchars(addslashes($f['name'])); ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
function deleteFormation(id, name) {
    if (!confirm('Formation "' + name + '" wirklich löschen?\nAlle Mitglieder-Zuordnungen werden entfernt.')) return;
    fetch('api/formation_delete.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
        else alert('Fehler: ' + (data.error || 'Unbekannt'));
    });
}
</script>

<?php include 'includes/footer.php'; ?>
