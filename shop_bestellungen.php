<?php
// shop_bestellungen.php – Bestell-Eingang des Shops (Katalog-Server, Admin)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin() || !SHOP_IST_ZENTRALE) {
    Session::setFlashMessage('danger', 'Kein Zugriff auf die Shop-Verwaltung.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aktion'] ?? '') === 'status') {
    try {
        Shop::bestellStatusSetzen((int)$_POST['id'], (string)($_POST['status'] ?? ''));
        Session::setFlashMessage('success', 'Status geändert.');
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: shop_bestellungen.php');
    exit;
}

$filter = (string)($_GET['status'] ?? '');
$bestellungen = Shop::bestellungen($filter);
$statusFarbe = ['neu' => 'warning text-dark', 'bestaetigt' => 'info', 'geliefert' => 'primary', 'verrechnet' => 'success', 'storniert' => 'secondary'];
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-inbox"></i> Bestellungen</h1>
    <a href="shop_verwaltung.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zur Verwaltung</a>
</div>

<form method="get" class="d-flex align-items-center gap-2 mb-3">
    <label for="status" class="small text-muted">Status</label>
    <select name="status" id="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">Alle</option>
        <?php foreach (Shop::STATUS as $sv => $sl): ?>
        <option value="<?php echo $sv; ?>" <?php echo $filter === $sv ? 'selected' : ''; ?>><?php echo $sl; ?></option>
        <?php endforeach; ?>
    </select>
    <span class="text-muted small"><?php echo count($bestellungen); ?> Bestellungen</span>
</form>

<?php if (!$bestellungen): ?>
<div class="alert alert-info alert-permanent"><i class="bi bi-info-circle"></i> Noch keine Bestellungen. Sobald ein Verein im Shop bestellt, erscheint sie hier.</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
<?php foreach ($bestellungen as $b): $det = Shop::bestellung((int)$b['id']); ?>
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong><?php echo $h($b['bestellnummer']); ?></strong>
                · <?php echo $h($b['verein_name'] ?: 'Unbekannter Verein'); ?>
                <span class="text-muted small ms-1"><?php echo date('d.m.Y H:i', strtotime($b['erstellt_am'])); ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-<?php echo $statusFarbe[$b['status']] ?? 'secondary'; ?>"><?php echo Shop::STATUS[$b['status']] ?? $h($b['status']); ?></span>
                <form method="post" class="d-flex align-items-center gap-1">
                    <input type="hidden" name="aktion" value="status"><input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                    <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <?php foreach (Shop::STATUS as $sv => $sl): ?>
                        <option value="<?php echo $sv; ?>" <?php echo $b['status'] === $sv ? 'selected' : ''; ?>><?php echo $sl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6 small">
                    <div class="text-muted">Besteller</div>
                    <?php echo $h($b['besteller_name'] ?: '–'); ?>
                    <?php if ($b['besteller_email']): ?><br><a href="mailto:<?php echo $h($b['besteller_email']); ?>"><?php echo $h($b['besteller_email']); ?></a><?php endif; ?>
                    <?php if ($b['besteller_telefon']): ?><br><?php echo $h($b['besteller_telefon']); ?><?php endif; ?>
                </div>
                <div class="col-md-6 small">
                    <div class="text-muted">Lieferadresse</div>
                    <?php echo nl2br($h($b['lieferadresse'] ?: '–')); ?>
                </div>
                <?php if ($b['bemerkung']): ?>
                <div class="col-12 small"><div class="text-muted">Bemerkung</div><?php echo nl2br($h($b['bemerkung'])); ?></div>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Artikel</th><th>Größe</th><th class="text-end">Einzel</th><th class="text-end">Menge</th><th class="text-end">Summe</th></tr></thead>
                    <tbody>
                    <?php foreach ($det['positionen'] as $p): ?>
                    <tr>
                        <td><?php echo $h($p['artikel_name']); ?><?php if ($p['artikelnummer']): ?> <span class="text-muted small"><?php echo $h($p['artikelnummer']); ?></span><?php endif; ?></td>
                        <td><?php echo $h($p['variante'] ?: '–'); ?></td>
                        <td class="text-end" style="font-variant-numeric:tabular-nums"><?php echo Shop::euro($p['einzelpreis']); ?></td>
                        <td class="text-end"><?php echo (int)$p['menge']; ?></td>
                        <td class="text-end" style="font-variant-numeric:tabular-nums"><?php echo Shop::euro($p['einzelpreis'] * $p['menge']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <?php if ((float)($b['versandkosten'] ?? 0) > 0): ?>
                        <tr><td colspan="4" class="text-end border-0 py-0 text-muted small"><i class="bi bi-truck"></i> Versand</td><td class="text-end border-0 py-0" style="font-variant-numeric:tabular-nums"><?php echo Shop::euro($b['versandkosten']); ?></td></tr>
                    <?php endif; ?>
                        <tr><th colspan="4" class="text-end">Summe</th><th class="text-end" style="font-variant-numeric:tabular-nums"><?php echo Shop::euro($b['summe']); ?></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
