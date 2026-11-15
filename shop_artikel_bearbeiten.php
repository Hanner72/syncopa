<?php
// shop_artikel_bearbeiten.php – einen Shop-Artikel mit Größen und Bildern bearbeiten (Katalog-Server, Admin)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin() || !SHOP_IST_ZENTRALE) {
    Session::setFlashMessage('danger', 'Kein Zugriff auf die Shop-Verwaltung.');
    header('Location: index.php');
    exit;
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0) ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['aktion'] ?? '') === 'bild_loeschen') {
            Shop::bildLoeschen((int)$_POST['bild_id']);
            Session::setFlashMessage('success', 'Bild entfernt.');
            header('Location: shop_artikel_bearbeiten.php?id=' . (int)$id);
            exit;
        }
        // Artikel speichern
        $id = Shop::artikelSpeichern($_POST, $id);
        // Varianten (Größen) – parallele Felder
        $zeilen = [];
        foreach ((array)($_POST['variante_bezeichnung'] ?? []) as $i => $bez) {
            $zeilen[] = [
                'bezeichnung'   => $bez,
                'artikelnummer' => $_POST['variante_artikelnummer'][$i] ?? '',
                'preis'         => $_POST['variante_preis'][$i] ?? '',
            ];
        }
        Shop::variantenSetzen($id, $zeilen);
        // Bilder hochladen (mehrere möglich)
        if (!empty($_FILES['bilder']['name'][0])) {
            foreach (array_keys($_FILES['bilder']['name']) as $i) {
                if (($_FILES['bilder']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                Shop::bildHinzufuegen($id, [
                    'name' => $_FILES['bilder']['name'][$i], 'tmp_name' => $_FILES['bilder']['tmp_name'][$i],
                    'error' => $_FILES['bilder']['error'][$i], 'size' => $_FILES['bilder']['size'][$i],
                ]);
            }
        }
        Session::setFlashMessage('success', 'Artikel gespeichert.');
        header('Location: shop_artikel_bearbeiten.php?id=' . $id);
        exit;
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
        header('Location: shop_artikel_bearbeiten.php' . ($id ? '?id=' . $id : ''));
        exit;
    }
}

$artikel    = $id ? Shop::artikel($id) : null;
if ($id && !$artikel) { Session::setFlashMessage('danger', 'Artikel nicht gefunden.'); header('Location: shop_verwaltung.php'); exit; }
$kategorien = Shop::kategorien();
$varianten  = $id ? Shop::varianten($id) : [];
$bilder     = $id ? Shop::bilder($id) : [];
$bildBasis  = 'uploads/shop';
$neu        = fn($feld, $std = '') => $artikel[$feld] ?? $std;
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
$preisWert  = $artikel ? number_format((float)$artikel['preis'], 2, ',', '') : '';

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-box-seam"></i> <?php echo $id ? 'Artikel bearbeiten' : 'Neuer Artikel'; ?></h1>
    <a href="shop_verwaltung.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zur Verwaltung</a>
</div>

<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Artikeldaten</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label" for="a-name">Name *</label>
                            <input class="form-control" id="a-name" name="name" required value="<?php echo $h($neu('name')); ?>"></div>
                        <div class="col-md-4"><label class="form-label" for="a-nr">Artikelnummer</label>
                            <input class="form-control" id="a-nr" name="artikelnummer" value="<?php echo $h($neu('artikelnummer')); ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="a-kat">Kategorie</label>
                            <select class="form-select" id="a-kat" name="kategorie_id">
                                <option value="">– ohne Kategorie –</option>
                                <?php foreach ($kategorien as $k): ?>
                                <option value="<?php echo (int)$k['id']; ?>" <?php echo (int)$neu('kategorie_id') === (int)$k['id'] ? 'selected' : ''; ?>><?php echo $h($k['name']); ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="col-md-3"><label class="form-label" for="a-preis">Preis (€) *</label>
                            <input class="form-control" id="a-preis" name="preis" inputmode="decimal" required value="<?php echo $h($preisWert); ?>" placeholder="0,00"></div>
                        <div class="col-md-3"><label class="form-label" for="a-sort">Reihenfolge</label>
                            <input type="number" class="form-control" id="a-sort" name="sortierung" value="<?php echo (int)$neu('sortierung', 100); ?>"></div>
                        <div class="col-md-6"><label class="form-label" for="a-lief">Lieferzeit (optional)</label>
                            <input class="form-control" id="a-lief" name="lieferzeit" value="<?php echo $h($neu('lieferzeit')); ?>" placeholder="z. B. 3–4 Wochen"></div>
                        <div class="col-md-6 d-flex align-items-end pb-2"><div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="a-aktiv" name="aktiv" value="1" <?php echo ($id ? $artikel['aktiv'] : 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="a-aktiv">Für Vereine sichtbar</label></div></div>
                        <div class="col-12"><label class="form-label" for="a-besch">Beschreibung</label>
                            <textarea class="form-control" id="a-besch" name="beschreibung" rows="4"><?php echo $h($neu('beschreibung')); ?></textarea></div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Größen / Varianten</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="variante-neu"><i class="bi bi-plus-lg"></i> Größe</button>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Leer lassen, wenn der Artikel keine Größen hat (z. B. Zubehör). Ein eigener Preis je Größe ist optional – leer = Artikelpreis.</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" id="variante-tabelle">
                            <thead><tr><th>Bezeichnung</th><th>Artikelnummer</th><th style="width:140px">Preis (€)</th><th style="width:40px"></th></tr></thead>
                            <tbody id="variante-rows">
                            <?php foreach ($varianten as $v): ?>
                            <tr>
                                <td><input class="form-control form-control-sm" name="variante_bezeichnung[]" value="<?php echo $h($v['bezeichnung']); ?>" placeholder="z. B. Gr. 50"></td>
                                <td><input class="form-control form-control-sm" name="variante_artikelnummer[]" value="<?php echo $h($v['artikelnummer'] ?? ''); ?>"></td>
                                <td><input class="form-control form-control-sm" name="variante_preis[]" inputmode="decimal" value="<?php echo $v['preis'] !== null ? $h(number_format((float)$v['preis'], 2, ',', '')) : ''; ?>" placeholder="Artikelpreis"></td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger p-0 variante-weg" title="Entfernen"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Bilder</h5></div>
                <div class="card-body">
                    <?php if ($bilder): ?>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($bilder as $b): ?>
                        <div style="position:relative">
                            <img src="<?php echo $h($bildBasis . '/' . rawurlencode($b['dateiname'])); ?>" alt="" style="width:92px;height:92px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
                            <button type="submit" form="bild-del-<?php echo (int)$b['id']; ?>" class="btn btn-sm btn-danger" style="position:absolute;top:-8px;right:-8px;border-radius:50%;width:24px;height:24px;padding:0;line-height:1" title="Bild entfernen" onclick="return confirm('Bild entfernen?')"><i class="bi bi-x"></i></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php elseif ($id): ?>
                    <p class="text-muted small mb-3">Noch keine Bilder.</p>
                    <?php endif; ?>
                    <?php if ($id): ?>
                    <label class="form-label" for="a-bilder">Bilder hinzufügen</label>
                    <input class="form-control" type="file" id="a-bilder" name="bilder[]" accept="image/jpeg,image/png,image/webp" multiple>
                    <div class="form-text">JPG, PNG oder WEBP, bis 5 MB je Bild. Mehrere auf einmal möglich.</div>
                    <?php else: ?>
                    <p class="text-muted small mb-0">Bilder lassen sich hinzufügen, sobald der Artikel einmal gespeichert ist.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="shop_verwaltung.php" class="btn btn-secondary">Abbrechen</a>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Speichern</button>
    </div>
</form>

<?php foreach ($bilder as $b): ?>
<form method="post" id="bild-del-<?php echo (int)$b['id']; ?>" class="d-none"><input type="hidden" name="aktion" value="bild_loeschen"><input type="hidden" name="id" value="<?php echo (int)$id; ?>"><input type="hidden" name="bild_id" value="<?php echo (int)$b['id']; ?>"></form>
<?php endforeach; ?>

<?php include 'includes/footer.php'; ?>
<script>
(function () {
    var rows = document.getElementById('variante-rows');
    function zeile() {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><input class="form-control form-control-sm" name="variante_bezeichnung[]" placeholder="z. B. Gr. 50"></td>'
            + '<td><input class="form-control form-control-sm" name="variante_artikelnummer[]"></td>'
            + '<td><input class="form-control form-control-sm" name="variante_preis[]" inputmode="decimal" placeholder="Artikelpreis"></td>'
            + '<td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger p-0 variante-weg" title="Entfernen"><i class="bi bi-x-lg"></i></button></td>';
        rows.appendChild(tr);
    }
    document.getElementById('variante-neu').addEventListener('click', zeile);
    rows.addEventListener('click', function (e) {
        var b = e.target.closest('.variante-weg');
        if (b) b.closest('tr').remove();
    });
})();
</script>
