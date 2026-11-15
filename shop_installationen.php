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
                <td class="fw-semibold"><?php echo $i['name'] ? $h($i['name']) : '<span class="text-muted">unbenannt</span>'; ?>
                    <br><small class="text-muted" style="font-family:monospace"><?php echo $h(substr($i['installation_id'], 0, 12)); ?>…</small></td>
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
