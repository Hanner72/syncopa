<?php
// shop_verwaltung.php – Katalog des Vereins-Shops pflegen (nur auf dem Katalog-Server, nur Administratoren)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin()) {
    Session::setFlashMessage('danger', 'Die Shop-Verwaltung ist nur für Administratoren.');
    header('Location: index.php');
    exit;
}
if (!SHOP_IST_ZENTRALE) {
    Session::setFlashMessage('danger', 'Diese Installation ist nicht der Katalog-Server. In config.php SHOP_IST_ZENTRALE auf true setzen.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['aktion'] ?? '') {
            case 'einstellungen':
                foreach (['titel', 'einleitung', 'versand', 'kontakt', 'bestell_email'] as $f) {
                    Shop::einstellungSetzen($f, trim((string)($_POST[$f] ?? '')));
                }
                Session::setFlashMessage('success', 'Shop-Einstellungen gespeichert.');
                break;
            case 'kategorie':
                Shop::kategorieSpeichern($_POST, (int)($_POST['id'] ?? 0) ?: null);
                Session::setFlashMessage('success', 'Kategorie gespeichert.');
                break;
            case 'kategorie_loeschen':
                Shop::kategorieLoeschen((int)$_POST['id']);
                Session::setFlashMessage('success', 'Kategorie gelöscht. Artikel darin bleiben erhalten (ohne Kategorie).');
                break;
            case 'artikel_loeschen':
                Shop::artikelLoeschen((int)$_POST['id']);
                Session::setFlashMessage('success', 'Artikel gelöscht.');
                break;
        }
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: shop_verwaltung.php' . (str_starts_with($_POST['aktion'] ?? '', 'kategorie') ? '#kategorien' : ''));
    exit;
}

$kategorien = Shop::kategorien();
$artikel    = Shop::artikelListe();
$bildBasis  = 'uploads/shop';
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-shop"></i> Shop-Verwaltung</h1>
    <div class="d-flex gap-2">
        <a href="shop_bestellungen.php" class="btn btn-outline-primary"><i class="bi bi-inbox"></i> Bestellungen</a>
        <a href="shop_artikel_bearbeiten.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> Neuer Artikel</a>
    </div>
</div>

<div class="alert alert-info alert-permanent d-flex align-items-center gap-2">
    <i class="bi bi-info-circle"></i>
    <div>Dieser Katalog wird an alle Vereine ausgeliefert, die in ihrer Syncopa den Shop aktiviert haben. Nur <strong>aktive</strong> Kategorien und Artikel sind für die Vereine sichtbar.</div>
</div>

<!-- Artikel -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-box-seam"></i> Artikel</h5></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th style="width:64px"></th><th>Artikel</th><th>Kategorie</th><th>Größen</th><th class="text-end">Preis</th><th class="text-center">Sichtbar</th><th></th></tr></thead>
            <tbody>
            <?php if (!$artikel): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Noch keine Artikel. Lege mit <strong>Neuer Artikel</strong> den ersten an.</td></tr>
            <?php endif; ?>
            <?php foreach ($artikel as $a): ?>
            <tr class="<?php echo $a['aktiv'] ? '' : 'text-muted'; ?>">
                <td>
                    <?php if ($a['hauptbild']): ?>
                    <img src="<?php echo $h($bildBasis . '/' . rawurlencode($a['hauptbild'])); ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:4px;border:1px solid var(--border)">
                    <?php else: ?>
                    <span class="text-muted d-inline-flex align-items-center justify-content-center" style="width:48px;height:48px;border:1px dashed var(--border);border-radius:4px"><i class="bi bi-image"></i></span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="shop_artikel_bearbeiten.php?id=<?php echo (int)$a['id']; ?>" class="fw-semibold"><?php echo $h($a['name']); ?></a>
                    <?php if (!$a['aktiv']): ?><span class="badge bg-secondary ms-1">verborgen</span><?php endif; ?>
                    <?php if ($a['artikelnummer']): ?><br><small class="text-muted"><?php echo $h($a['artikelnummer']); ?></small><?php endif; ?>
                </td>
                <td class="small"><?php echo $a['kategorie_name'] ? $h($a['kategorie_name']) : '<span class="text-muted">–</span>'; ?></td>
                <td class="small"><?php echo (int)$a['anzahl_varianten'] ? ((int)$a['anzahl_varianten'] . ' Größen') : '<span class="text-muted">eine</span>'; ?></td>
                <td class="text-end text-nowrap" style="font-variant-numeric:tabular-nums"><?php echo Shop::euro($a['preis']); ?></td>
                <td class="text-center"><?php echo $a['aktiv'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>'; ?></td>
                <td class="text-end text-nowrap">
                    <a href="shop_artikel_bearbeiten.php?id=<?php echo (int)$a['id']; ?>" class="btn btn-sm btn-outline-primary" title="Bearbeiten"><i class="bi bi-pencil"></i></a>
                    <button type="submit" form="art-del-<?php echo (int)$a['id']; ?>" class="btn btn-sm btn-outline-danger" title="Löschen" onclick="return confirm('Artikel „<?php echo $h(addslashes($a['name'])); ?>“ wirklich löschen?')"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php foreach ($artikel as $a): ?>
<form method="post" id="art-del-<?php echo (int)$a['id']; ?>" class="d-none"><input type="hidden" name="aktion" value="artikel_loeschen"><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>"></form>
<?php endforeach; ?>

<!-- Kategorien -->
<div class="card mb-4" id="kategorien">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-tags"></i> Kategorien</h5>
        <button class="btn btn-sm btn-success" data-kat='{}'><i class="bi bi-plus-lg"></i> Kategorie</button>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Beschreibung</th><th class="text-end">Reihenfolge</th><th class="text-center">Aktiv</th><th></th></tr></thead>
            <tbody>
            <?php if (!$kategorien): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">Noch keine Kategorien. Ohne Kategorie landen Artikel unter „Sonstiges“.</td></tr>
            <?php endif; ?>
            <?php foreach ($kategorien as $k): ?>
            <tr class="<?php echo $k['aktiv'] ? '' : 'text-muted'; ?>">
                <td class="fw-semibold"><?php echo $h($k['name']); ?></td>
                <td class="small text-muted"><?php echo $h($k['beschreibung'] ?? ''); ?></td>
                <td class="text-end"><?php echo (int)$k['sortierung']; ?></td>
                <td class="text-center"><?php echo $k['aktiv'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>'; ?></td>
                <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-outline-primary" title="Bearbeiten" data-kat='<?php echo $h(json_encode($k, JSON_UNESCAPED_UNICODE)); ?>'><i class="bi bi-pencil"></i></button>
                    <button type="submit" form="kat-del-<?php echo (int)$k['id']; ?>" class="btn btn-sm btn-outline-danger" title="Löschen" onclick="return confirm('Kategorie „<?php echo $h(addslashes($k['name'])); ?>“ löschen?')"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php foreach ($kategorien as $k): ?>
<form method="post" id="kat-del-<?php echo (int)$k['id']; ?>" class="d-none"><input type="hidden" name="aktion" value="kategorie_loeschen"><input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>"></form>
<?php endforeach; ?>

<!-- Einstellungen -->
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-gear"></i> Shop-Einstellungen</h5></div>
    <form method="post" class="card-body">
        <input type="hidden" name="aktion" value="einstellungen">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="s-titel">Titel des Shops</label>
                <input class="form-control" id="s-titel" name="titel" value="<?php echo $h(Shop::einstellung('titel', 'Vereins-Shop')); ?>"></div>
            <div class="col-md-6"><label class="form-label" for="s-email">Bestellungen melden an (E-Mail)</label>
                <input type="email" class="form-control" id="s-email" name="bestell_email" value="<?php echo $h(Shop::einstellung('bestell_email')); ?>" placeholder="z. B. shop@deine-domain.at"></div>
            <div class="col-12"><label class="form-label" for="s-einl">Einleitung (wird den Vereinen über dem Shop angezeigt)</label>
                <textarea class="form-control" id="s-einl" name="einleitung" rows="2"><?php echo $h(Shop::einstellung('einleitung')); ?></textarea></div>
            <div class="col-md-6"><label class="form-label" for="s-versand">Versand- und Lieferhinweis</label>
                <textarea class="form-control" id="s-versand" name="versand" rows="2" placeholder="z. B. Lieferung 2–3 Wochen, Versandkosten nach Aufwand"><?php echo $h(Shop::einstellung('versand')); ?></textarea></div>
            <div class="col-md-6"><label class="form-label" for="s-kontakt">Kontakt / Rückfragen</label>
                <textarea class="form-control" id="s-kontakt" name="kontakt" rows="2"><?php echo $h(Shop::einstellung('kontakt')); ?></textarea></div>
        </div>
        <div class="text-end mt-3"><button class="btn btn-primary"><i class="bi bi-save"></i> Einstellungen speichern</button></div>
    </form>
</div>

<!-- Kategorie-Dialog -->
<div class="modal fade" id="katModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content">
    <input type="hidden" name="aktion" value="kategorie"><input type="hidden" name="id" id="k-id">
    <div class="modal-header"><h5 class="modal-title">Kategorie</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label" for="k-name">Name</label><input class="form-control" id="k-name" name="name" required></div>
        <div class="mb-3"><label class="form-label" for="k-besch">Beschreibung</label><input class="form-control" id="k-beschreibung" name="beschreibung"></div>
        <div class="row g-3">
            <div class="col-6"><label class="form-label" for="k-sort">Reihenfolge</label><input type="number" class="form-control" id="k-sortierung" name="sortierung" value="100"></div>
            <div class="col-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" id="k-aktiv" name="aktiv" value="1" checked><label class="form-check-label" for="k-aktiv">Aktiv</label></div></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-primary">Speichern</button></div>
</form></div></div>

<?php include 'includes/footer.php'; ?>
<script>
document.querySelectorAll('[data-kat]').forEach(function (b) {
    b.addEventListener('click', function () {
        var d = JSON.parse(b.dataset.kat || '{}');
        var f = document.getElementById('katModal');
        f.querySelector('#k-id').value = d.id || '';
        f.querySelector('#k-name').value = d.name || '';
        f.querySelector('#k-beschreibung').value = d.beschreibung || '';
        f.querySelector('#k-sortierung').value = d.sortierung || 100;
        f.querySelector('#k-aktiv').checked = d.id ? d.aktiv == 1 : true;
        bootstrap.Modal.getOrCreateInstance(f).show();
    });
});
</script>
