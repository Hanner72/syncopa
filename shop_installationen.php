<?php
// shop_installationen.php – angebundene Vereins-Installationen: Shop je Verein ein-/ausschalten (Katalog-Server, Admin)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin() || !SHOP_IST_ZENTRALE) {
    Session::setFlashMessage('danger', 'Kein Zugriff auf die Shop-Verwaltung.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aktion'] ?? '') === 'name') {
    Shop::installationNameSetzen((int)$_POST['id'], (string)($_POST['anzeigename'] ?? ''));
    Session::setFlashMessage('success', trim((string)($_POST['anzeigename'] ?? '')) !== '' ? 'Name gespeichert.' : 'Eigener Name entfernt – es wird wieder der gemeldete Name angezeigt.');
    header('Location: shop_installationen.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aktion'] ?? '') === 'schalten') {
    Shop::installationAktivSetzen((int)$_POST['id'], !empty($_POST['aktiv']));
    Session::setFlashMessage('success', !empty($_POST['aktiv']) ? 'Shop für diesen Verein freigeschaltet.' : 'Shop für diesen Verein abgeschaltet.');
    header('Location: shop_installationen.php');
    exit;
}

$liste = Shop::installationen();
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-hdd-stack"></i> Installationen</h1>
    <a href="shop_verwaltung.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zur Verwaltung</a>
</div>

<div class="alert alert-info alert-permanent d-flex align-items-center gap-2">
    <i class="bi bi-info-circle"></i>
    <div>Jede Syncopa, die den Shop anzeigt, meldet sich hier. Neue Vereine sind <strong>standardmäßig freigeschaltet</strong>. Mit dem Schalter kannst du den Shop für einen Verein ab- oder wieder einschalten – die Änderung greift beim Verein innerhalb einer Stunde.</div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Verein</th><th>Adresse</th><th>Version</th><th>Zuletzt gemeldet</th><th class="text-center">Shop</th></tr></thead>
            <tbody>
            <?php if (!$liste): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Noch keine Installation hat sich gemeldet. Sobald ein Verein den Shop öffnet, erscheint er hier.</td></tr>
            <?php endif; ?>
            <?php foreach ($liste as $i): ?>
            <tr class="<?php echo $i['shop_aktiv'] ? '' : 'text-muted'; ?>">
                <?php $anzeige = Shop::installationAnzeige($i); $eigen = trim((string)($i['anzeigename'] ?? '')) !== ''; $gemeldet = trim((string)($i['name'] ?? '')); ?>
                <td>
                    <div id="n-<?php echo (int)$i['id']; ?>">
                        <span class="fw-semibold"><?php echo $anzeige !== null ? $h($anzeige) : '<span class="text-muted">unbenannt</span>'; ?></span>
                        <?php if (!$eigen && $gemeldet === '' && $anzeige !== null): ?><span class="badge bg-light text-muted border ms-1" title="Der Verein hat keinen Namen gemeldet">aus Adresse</span><?php endif; ?>
                        <button type="button" class="btn btn-sm btn-link p-0 ms-1 align-baseline" data-umbenennen="<?php echo (int)$i['id']; ?>" title="Namen ändern"><i class="bi bi-pencil"></i></button>
                        <br><small class="text-muted"><?php if (!empty($i['ort'])): ?><i class="bi bi-geo-alt"></i> <?php echo $h($i['ort']); ?> · <?php endif; ?>
                            <?php if ($eigen && $gemeldet !== '' && $gemeldet !== $anzeige): ?>gemeldet: <?php echo $h($gemeldet); ?> · <?php endif; ?>
                            <span style="font-family:monospace"><?php echo $h(substr($i['installation_id'], 0, 12)); ?>…</span></small>
                    </div>
                    <form method="post" id="e-<?php echo (int)$i['id']; ?>" style="display:none" class="align-items-center gap-1">
                        <input type="hidden" name="aktion" value="name"><input type="hidden" name="id" value="<?php echo (int)$i['id']; ?>">
                        <input class="form-control form-control-sm" name="anzeigename" maxlength="150" value="<?php echo $h($i['anzeigename'] ?? ''); ?>"
                               placeholder="<?php echo $h($gemeldet !== '' ? $gemeldet : ($anzeige ?? 'Name des Vereins')); ?>" style="min-width:220px">
                        <button class="btn btn-sm btn-primary" title="Speichern"><i class="bi bi-check-lg"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-abbrechen="<?php echo (int)$i['id']; ?>" title="Abbrechen"><i class="bi bi-x-lg"></i></button>
                    </form>
                </td>
                <td class="small"><?php echo $i['url'] ? '<a href="' . $h($i['url']) . '" target="_blank" rel="noopener">' . $h($i['url']) . '</a>' : '–'; ?></td>
                <td class="small"><?php echo $h($i['version'] ?? '–'); ?></td>
                <td class="small"><?php echo $i['letzte_meldung'] ? date('d.m.Y H:i', strtotime($i['letzte_meldung'])) : '–'; ?></td>
                <td class="text-center">
                    <form method="post">
                        <input type="hidden" name="aktion" value="schalten">
                        <input type="hidden" name="id" value="<?php echo (int)$i['id']; ?>">
                        <input type="hidden" name="aktiv" value="<?php echo $i['shop_aktiv'] ? '0' : '1'; ?>">
                        <div class="form-check form-switch d-inline-block">
                            <input class="form-check-input" type="checkbox" role="switch" <?php echo $i['shop_aktiv'] ? 'checked' : ''; ?> onchange="this.form.submit()" style="cursor:pointer">
                        </div>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
// Namen direkt in der Liste ändern
document.querySelectorAll('[data-umbenennen]').forEach(function (b) {
    b.addEventListener('click', function () {
        var id = b.dataset.umbenennen, f = document.getElementById('e-' + id);
        document.getElementById('n-' + id).style.display = 'none';
        f.style.display = 'flex';
        f.querySelector('input[name="anzeigename"]').focus();
    });
});
document.querySelectorAll('[data-abbrechen]').forEach(function (b) {
    b.addEventListener('click', function () {
        var id = b.dataset.abbrechen;
        document.getElementById('e-' + id).style.display = 'none';
        document.getElementById('n-' + id).style.display = '';
    });
});
</script>
