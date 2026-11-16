<?php
// shop_angebot_bearbeiten.php – ein Angebot (Karte mit Bild und Beschreibung auf der Shop-Startseite) bearbeiten (Katalog-Server, Admin)
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
        $id = Shop::angebotSpeichern($_POST, $id, $_FILES['bild'] ?? null);
        Session::setFlashMessage('success', 'Angebot gespeichert.');
        header('Location: shop_angebot_bearbeiten.php?id=' . $id);
        exit;
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
        header('Location: shop_angebot_bearbeiten.php' . ($id ? '?id=' . $id : ''));
        exit;
    }
}

$angebot = $id ? Shop::angebot($id) : null;
if ($id && !$angebot) { Session::setFlashMessage('danger', 'Angebot nicht gefunden.'); header('Location: shop_verwaltung.php#angebote'); exit; }
$kategorien = Shop::kategorienBaum();
$w = fn($f, $std = '') => $angebot[$f] ?? $std;
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-stars"></i> <?php echo $id ? 'Angebot bearbeiten' : 'Neues Angebot'; ?></h1>
    <a href="shop_verwaltung.php#angebote" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zur Verwaltung</a>
</div>

<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Inhalt</h5></div><div class="card-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label" for="g-titel">Titel *</label>
                        <input class="form-control" id="g-titel" name="titel" required maxlength="150" value="<?php echo $h($w('titel')); ?>" placeholder="z. B. Besticken &amp; Beflocken"></div>
                    <div class="col-12"><label class="form-label" for="g-besch">Beschreibung</label>
                        <textarea class="form-control" id="g-besch" name="beschreibung" rows="3" placeholder="z. B. Vereinskleidung mit eurem Namen und Wappen."><?php echo $h($w('beschreibung')); ?></textarea></div>
                    <div class="col-12"><label class="form-label" for="g-punkte">Aufzählung (optional)</label>
                        <textarea class="form-control" id="g-punkte" name="punkte" rows="4" placeholder="Weste mit individuellem Schriftzug&#10;Janker mit Vereinswappen&#10;Mehr Infos | https://…"><?php echo $h($w('punkte')); ?></textarea>
                        <div class="form-text">Eine Zeile je Punkt. Soll ein Punkt verlinken, nach einem senkrechten Strich die Adresse anhängen: <code>Text | https://…</code></div></div>
                    <div class="col-md-6"><label class="form-label" for="g-kat">Verweist auf Kategorie (optional)</label>
                        <select class="form-select" id="g-kat" name="kategorie_id">
                            <option value="">– keine –</option>
                            <?php foreach ($kategorien as $k): ?>
                            <option value="<?php echo (int)$k['id']; ?>" <?php echo (int)$w('kategorie_id') === (int)$k['id'] ? 'selected' : ''; ?>><?php echo $k['ebene'] ? '&nbsp;&nbsp;&nbsp;↳ ' : ''; ?><?php echo $h($k['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Zeigt auf der Karte „Zu den Artikeln“, das die Kategorie filtert.</div></div>
                    <div class="col-md-3"><label class="form-label" for="g-sort">Reihenfolge</label>
                        <input type="number" class="form-control" id="g-sort" name="sortierung" value="<?php echo (int)$w('sortierung', 100); ?>"></div>
                    <div class="col-md-3 d-flex align-items-end pb-2"><div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="g-aktiv" name="aktiv" value="1" <?php echo ($id ? $angebot['aktiv'] : 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="g-aktiv">Sichtbar</label></div></div>
                </div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Bild</h5></div><div class="card-body">
                <?php if ($angebot && $angebot['bild']): ?>
                <img src="<?php echo $h('uploads/shop/' . rawurlencode($angebot['bild'])); ?>" alt="" class="w-100 mb-3" style="aspect-ratio:16/10;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
                <?php endif; ?>
                <label class="form-label" for="g-bild"><?php echo ($angebot && $angebot['bild']) ? 'Bild ersetzen' : 'Bild hochladen'; ?></label>
                <input class="form-control" type="file" id="g-bild" name="bild" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG oder WEBP, bis 16 MB – wird beim Hochladen automatisch passend verkleinert. Am besten im Querformat (etwa 16:10).</div>
            </div></div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="shop_verwaltung.php#angebote" class="btn btn-secondary">Abbrechen</a>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Speichern</button>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
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
