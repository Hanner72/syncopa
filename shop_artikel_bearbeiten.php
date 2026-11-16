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
        if (($_POST['aktion'] ?? '') === 'bild_haupt') {
            Shop::bildAlsHauptbild((int)$_POST['bild_id']);
            Session::setFlashMessage('success', 'Hauptbild festgelegt.');
            header('Location: shop_artikel_bearbeiten.php?id=' . (int)$id);
            exit;
        }
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
$kategorien = Shop::kategorienBaum();
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
                                <option value="<?php echo (int)$k['id']; ?>" <?php echo (int)$neu('kategorie_id') === (int)$k['id'] ? 'selected' : ''; ?>><?php echo $k['ebene'] ? '&nbsp;&nbsp;&nbsp;↳ ' : ''; ?><?php echo $h($k['name']); ?></option>
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
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-md-4 d-flex align-items-center pt-2"><div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="a-aktion" name="aktion" value="1" <?php echo ($id && $artikel['aktion']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="a-aktion"><i class="bi bi-tag-fill text-danger"></i> Aktion / Angebot</label></div></div>
                        <div class="col-md-4"><label class="form-label" for="a-aktiontext">Aktionslabel (optional)</label>
                            <input class="form-control" id="a-aktiontext" name="aktion_text" maxlength="40" value="<?php echo $h($neu('aktion_text')); ?>" placeholder="Standard: „Aktion“"></div>
                        <div class="col-md-4 d-flex align-items-center pt-2"><div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="a-ausverkauft" name="ausverkauft" value="1" <?php echo ($id && $artikel['ausverkauft']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="a-ausverkauft"><i class="bi bi-slash-circle text-secondary"></i> Ausverkauft</label></div></div>
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
                        <?php foreach ($bilder as $bi => $b): ?>
                        <div style="position:relative">
                            <img src="<?php echo $h($bildBasis . '/' . rawurlencode($b['dateiname'])); ?>" alt="" style="width:92px;height:92px;object-fit:cover;border-radius:6px;border:<?php echo $bi === 0 ? '2px solid #3d5a40' : '1px solid var(--border)'; ?>">
                            <?php if ($bi === 0): ?>
                            <span class="badge bg-success" style="position:absolute;left:4px;bottom:4px;font-size:.65rem">Hauptbild</span>
                            <?php else: ?>
                            <button type="submit" form="bild-haupt-<?php echo (int)$b['id']; ?>" class="btn btn-sm btn-light" style="position:absolute;left:4px;bottom:4px;padding:0 5px;font-size:.7rem;line-height:1.6" title="Als Hauptbild verwenden"><i class="bi bi-star"></i></button>
                            <?php endif; ?>
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
                    <div class="form-text">JPG, PNG oder WEBP, bis 16 MB je Bild – Fotos werden beim Hochladen automatisch verkleinert (max. <?php echo Shop::BILD_MAX_KANTE; ?> px) und richtig gedreht. Mehrere auf einmal möglich. Das Hauptbild erscheint zuerst; alle Bilder sind im Shop direkt auf der Karte durchklickbar (<i class="bi bi-star"></i> = als Hauptbild verwenden).</div>
                    <?php else: ?>
                    <p class="text-muted small mb-0">Bilder lassen sich hinzufügen, sobald der Artikel einmal gespeichert ist.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="speicher-leiste">
        <span class="speicher-hinweis small text-muted" id="speicher-hinweis"></span>
        <a href="shop_verwaltung.php" class="btn btn-secondary">Abbrechen</a>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Speichern</button>
    </div>
</form>
<style>
/* Speichern-Leiste bleibt beim Scrollen unten sichtbar */
.speicher-leiste{position:sticky;bottom:0;z-index:20;display:flex;align-items:center;justify-content:flex-end;gap:8px;
  margin:0 -4px 16px;padding:10px 12px;background:var(--bg-card);border:1px solid var(--border);border-radius:10px;
  box-shadow:0 -6px 18px rgba(0,0,0,.08)}
.speicher-leiste .speicher-hinweis{margin-right:auto}
.speicher-leiste.geaendert .speicher-hinweis{color:var(--c-warning)!important;font-weight:600}
</style>
<script>
// Hinweis bei ungespeicherten Änderungen
(function () {
    var form = document.querySelector('form[enctype="multipart/form-data"]'), leiste = document.querySelector('.speicher-leiste');
    if (!form || !leiste) return;
    function geaendert() { leiste.classList.add('geaendert'); document.getElementById('speicher-hinweis').innerHTML = '<i class="bi bi-pencil-square"></i> Nicht gespeicherte Änderungen'; }
    form.addEventListener('input', geaendert);
    form.addEventListener('change', geaendert);
    form.addEventListener('click', function (e) { if (e.target.closest('#variante-neu, .variante-weg')) geaendert(); });
})();
</script>

<?php foreach ($bilder as $b): ?>
<form method="post" id="bild-haupt-<?php echo (int)$b['id']; ?>" class="d-none"><input type="hidden" name="aktion" value="bild_haupt"><input type="hidden" name="id" value="<?php echo (int)$id; ?>"><input type="hidden" name="bild_id" value="<?php echo (int)$b['id']; ?>"></form>
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
<script>
// Vor dem Absenden: Dateigrößen prüfen (je Bild max. 16 MB, zusammen höchstens so viel, wie der Server pro Speichern annimmt)
(function () {
    var GRENZE = <?php echo Shop::uploadGrenzeBytes(); ?>, EINZEL = <?php echo Shop::BILD_MAX_BYTES; ?>;
    var form = document.querySelector('form[enctype="multipart/form-data"]');
    if (!form) return;
    var mb = function (b) { return (b / 1048576).toFixed(1).replace('.', ',') + ' MB'; };
    form.addEventListener('submit', function (e) {
        var summe = 0, zuGross = [];
        form.querySelectorAll('input[type=file]').forEach(function (inp) {
            Array.prototype.forEach.call(inp.files || [], function (f) { summe += f.size; if (f.size > EINZEL) zuGross.push(f.name); });
        });
        var text = '';
        if (zuGross.length) text = 'Diese Bilder sind größer als ' + mb(EINZEL) + ': ' + zuGross.join(', ');
        else if (summe > GRENZE - 512 * 1024) text = 'Die gewählten Bilder sind zusammen ' + mb(summe) + ' groß – pro Speichern sind höchstens ' + mb(GRENZE) + ' möglich. Bitte in mehreren Durchgängen hochladen.';
        if (text) { e.preventDefault(); alert(text); }
    });
})();
</script>
