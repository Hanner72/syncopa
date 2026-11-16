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
                // Versandkosten als Zahl normalisieren (Komma erlaubt), leer/0 = kein Versand
                foreach (['versandkosten', 'versandkosten_frei_ab'] as $f) {
                    $wert = (float)str_replace(',', '.', trim((string)($_POST[$f] ?? '')));
                    Shop::einstellungSetzen($f, $wert > 0 ? number_format($wert, 2, '.', '') : '');
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
            case 'angebot_loeschen':
                Shop::angebotLoeschen((int)$_POST['id']);
                Session::setFlashMessage('success', 'Angebot gelöscht.');
                header('Location: shop_verwaltung.php#angebote');
                exit;
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

$kategorien = Shop::kategorienBaum();
$hauptKats  = array_values(array_filter($kategorien, fn($k) => (int)$k['ebene'] === 0));
$katNamen   = array_column($kategorien, 'name', 'id');
$angebote   = Shop::angebote();
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
                    <?php if (!empty($a['aktion'])): ?><span class="badge bg-danger ms-1"><?php echo $h($a['aktion_text'] ?: 'Aktion'); ?></span><?php endif; ?>
                    <?php if (!empty($a['ausverkauft'])): ?><span class="badge bg-dark ms-1">Ausverkauft</span><?php endif; ?>
                    <?php if ($a['artikelnummer']): ?><br><small class="text-muted"><?php echo $h($a['artikelnummer']); ?></small><?php endif; ?>
                </td>
                <td class="small"><?php if ($a['kategorie_name']): ?>
                    <?php if (!empty($a['oberkategorie_name'])): ?><span class="text-muted"><?php echo $h($a['oberkategorie_name']); ?></span> <i class="bi bi-chevron-right text-muted" style="font-size:.7em"></i> <?php endif; ?><?php echo $h($a['kategorie_name']); ?>
                <?php else: ?><span class="text-muted">–</span><?php endif; ?></td>
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
    <?php if (!$kategorien): ?>
    <div class="card-body text-center text-muted py-4">Noch keine Kategorien. Ohne Kategorie landen Artikel unter „Sonstiges“.</div>
    <?php else:
        $kinderVon = [];
        foreach ($kategorien as $k) if ($k['ebene']) $kinderVon[(int)$k['parent_id']][] = $k;
        $katZeile = function ($k) use ($h) { ob_start(); ?>
            <div class="kat-zeile <?php echo $k['aktiv'] ? '' : 'kat-inaktiv'; ?>">
                <span class="kat-griff" title="Ziehen zum Verschieben"><i class="bi bi-grip-vertical"></i></span>
                <span class="kat-name"><?php echo $h($k['name']); ?><?php if (!$k['aktiv']): ?> <span class="badge bg-secondary ms-1">inaktiv</span><?php endif; ?></span>
                <span class="kat-besch small text-muted d-none d-md-inline"><?php echo $h($k['beschreibung'] ?? ''); ?></span>
                <span class="kat-knoepfe text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" title="Bearbeiten" data-kat='<?php echo $h(json_encode($k, JSON_UNESCAPED_UNICODE)); ?>'><i class="bi bi-pencil"></i></button>
                    <button type="submit" form="kat-del-<?php echo (int)$k['id']; ?>" class="btn btn-sm btn-outline-danger" title="Löschen" onclick="return confirm('Kategorie „<?php echo $h(addslashes($k['name'])); ?>“ löschen?')"><i class="bi bi-trash"></i></button>
                </span>
            </div>
        <?php return ob_get_clean(); };
    ?>
    <div class="card-body pb-2">
        <div class="small text-muted mb-2"><i class="bi bi-arrows-move"></i> Am Griff <i class="bi bi-grip-vertical"></i> ziehen, um die Reihenfolge zu ändern. <strong>Eingerückt unter eine Kategorie ziehen</strong> macht sie zur Unterkategorie, <strong>nach links heraus ziehen</strong> wieder zur Hauptkategorie. Wird sofort gespeichert.</div>
        <ul class="kat-baum" id="kat-baum">
            <?php foreach ($kategorien as $k): if ($k['ebene']) continue; ?>
            <li class="kat-item" data-id="<?php echo (int)$k['id']; ?>">
                <?php echo $katZeile($k); ?>
                <ul class="kat-kinder">
                    <?php foreach ($kinderVon[(int)$k['id']] ?? [] as $c): ?>
                    <li class="kat-item" data-id="<?php echo (int)$c['id']; ?>"><?php echo $katZeile($c); ?><ul class="kat-kinder"></ul></li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="small mt-1" id="kat-status" style="min-height:1.4em"></div>
    </div>
    <?php endif; ?>
</div>
<style>
.kat-baum,.kat-kinder{list-style:none;margin:0;padding:0}
.kat-kinder{margin-left:34px;min-height:4px;border-radius:8px;transition:background .15s}
.kat-kinder .kat-kinder{display:none}                                   /* nur eine Ebene */
.kat-zeile{display:flex;align-items:center;gap:10px;background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:7px 10px;margin:4px 0}
.kat-baum > li > .kat-zeile .kat-name{font-weight:700}
.kat-kinder .kat-zeile{border-style:dashed}
.kat-zeile .kat-name{flex:0 1 auto;min-width:0}
.kat-zeile .kat-besch{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.kat-zeile .kat-knoepfe{margin-left:auto}
.kat-inaktiv{opacity:.6}
.kat-griff{cursor:grab;color:var(--text-muted);padding:2px 4px;font-size:1.1rem;touch-action:none}
.kat-griff:active{cursor:grabbing}
.kat-ziehen .kat-baum > li > .kat-kinder{min-height:30px;background:rgba(68,113,163,.06);outline:1px dashed rgba(68,113,163,.35)}
.kat-geist > .kat-zeile{background:rgba(68,113,163,.12);border-color:var(--c-primary)}
.kat-gewaehlt > .kat-zeile{box-shadow:0 6px 16px rgba(0,0,0,.15)}
</style>
<?php foreach ($kategorien as $k): ?>
<form method="post" id="kat-del-<?php echo (int)$k['id']; ?>" class="d-none"><input type="hidden" name="aktion" value="kategorie_loeschen"><input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>"></form>
<?php endforeach; ?>

<!-- Angebote -->
<div class="card mb-4" id="angebote">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-stars"></i> Angebote</h5>
        <a href="shop_angebot_bearbeiten.php" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Angebot</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th style="width:64px"></th><th>Titel</th><th>Verweist auf</th><th class="text-end">Reihenfolge</th><th class="text-center">Sichtbar</th><th></th></tr></thead>
            <tbody>
            <?php if (!$angebote): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">Noch keine Angebote. Angebote erscheinen als Karten mit Bild und Beschreibung oben im Shop (z. B. „Besticken &amp; Beflocken“, „Hutschmuck“).</td></tr>
            <?php endif; ?>
            <?php foreach ($angebote as $g): ?>
            <tr class="<?php echo $g['aktiv'] ? '' : 'text-muted'; ?>">
                <td><?php if ($g['bild']): ?><img src="<?php echo $h($bildBasis . '/' . rawurlencode($g['bild'])); ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:4px;border:1px solid var(--border)"><?php else: ?><span class="text-muted d-inline-flex align-items-center justify-content-center" style="width:48px;height:48px;border:1px dashed var(--border);border-radius:4px"><i class="bi bi-image"></i></span><?php endif; ?></td>
                <td><a href="shop_angebot_bearbeiten.php?id=<?php echo (int)$g['id']; ?>" class="fw-semibold"><?php echo $h($g['titel']); ?></a>
                    <?php if ($g['beschreibung']): ?><br><small class="text-muted"><?php echo $h(mb_strimwidth($g['beschreibung'], 0, 90, '…')); ?></small><?php endif; ?></td>
                <td class="small"><?php echo $g['kategorie_id'] && isset($katNamen[$g['kategorie_id']]) ? $h($katNamen[$g['kategorie_id']]) : '<span class="text-muted">–</span>'; ?></td>
                <td class="text-end"><?php echo (int)$g['sortierung']; ?></td>
                <td class="text-center"><?php echo $g['aktiv'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash-circle text-muted"></i>'; ?></td>
                <td class="text-end text-nowrap">
                    <a href="shop_angebot_bearbeiten.php?id=<?php echo (int)$g['id']; ?>" class="btn btn-sm btn-outline-primary" title="Bearbeiten"><i class="bi bi-pencil"></i></a>
                    <button type="submit" form="ang-del-<?php echo (int)$g['id']; ?>" class="btn btn-sm btn-outline-danger" title="Löschen" onclick="return confirm('Angebot „<?php echo $h(addslashes($g['titel'])); ?>“ löschen?')"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php foreach ($angebote as $g): ?>
<form method="post" id="ang-del-<?php echo (int)$g['id']; ?>" class="d-none"><input type="hidden" name="aktion" value="angebot_loeschen"><input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>"></form>
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
                <textarea class="form-control" id="s-versand" name="versand" rows="2" placeholder="z. B. Lieferung 2–3 Wochen"><?php echo $h(Shop::einstellung('versand')); ?></textarea></div>
            <div class="col-md-6"><label class="form-label" for="s-kontakt">Kontakt / Rückfragen</label>
                <textarea class="form-control" id="s-kontakt" name="kontakt" rows="2"><?php echo $h(Shop::einstellung('kontakt')); ?></textarea></div>
            <div class="col-md-3"><label class="form-label" for="s-vk">Versandkosten (€)</label>
                <input class="form-control" id="s-vk" name="versandkosten" inputmode="decimal" value="<?php echo $h(Shop::einstellung('versandkosten')); ?>" placeholder="z. B. 5,90">
                <div class="form-text">Pauschale je Bestellung. Leer oder 0 = kein Versand.</div></div>
            <div class="col-md-3"><label class="form-label" for="s-vkfrei">Versandfrei ab (€)</label>
                <input class="form-control" id="s-vkfrei" name="versandkosten_frei_ab" inputmode="decimal" value="<?php echo $h(Shop::einstellung('versandkosten_frei_ab')); ?>" placeholder="z. B. 150">
                <div class="form-text">Ab dieser Warensumme entfällt der Versand. Leer = nie.</div></div>
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
        <div class="mb-3"><label class="form-label" for="k-parent">Oberkategorie</label>
            <select class="form-select" id="k-parent" name="parent_id">
                <option value="">– keine (Hauptkategorie) –</option>
                <?php foreach ($hauptKats as $hk): ?><option value="<?php echo (int)$hk['id']; ?>"><?php echo $h($hk['name']); ?></option><?php endforeach; ?>
            </select>
            <div class="form-text">Mit Oberkategorie wird diese Kategorie zur Unterkategorie.</div></div>
        <div class="mb-3"><label class="form-label" for="k-besch">Beschreibung</label><input class="form-control" id="k-beschreibung" name="beschreibung"></div>
        <div class="row g-3">
            <div class="col-6"><label class="form-label" for="k-sort">Reihenfolge</label><input type="number" class="form-control" id="k-sortierung" name="sortierung" value="100"></div>
            <div class="col-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" id="k-aktiv" name="aktiv" value="1" checked><label class="form-check-label" for="k-aktiv">Aktiv</label></div></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-primary">Speichern</button></div>
</form></div></div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
// Kategorien per Drag & Drop anordnen – auch als Unter- bzw. Hauptkategorie (eine Ebene)
(function () {
    var baum = document.getElementById('kat-baum');
    if (!baum || !window.Sortable) return;
    var bereich = baum.parentElement, status = document.getElementById('kat-status');
    function struktur() {
        return Array.prototype.map.call(baum.children, function (li) {
            var ul = li.querySelector(':scope > .kat-kinder');
            return { id: +li.dataset.id, kinder: ul ? Array.prototype.map.call(ul.children, function (c) { return +c.dataset.id; }) : [] };
        });
    }
    function speichern() {
        status.innerHTML = '<span class="text-muted"><i class="bi bi-arrow-repeat"></i> Wird gespeichert …</span>';
        fetch('api/shop_kategorien_sortieren.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ struktur: struktur() })
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) { if (!r.ok || !d.success) throw new Error(d.error || 'Speichern fehlgeschlagen.'); });
        }).then(function () {
            status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Gespeichert</span>';
            setTimeout(function () { status.innerHTML = ''; }, 2500);
        }).catch(function (e) {
            status.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + e.message + ' Die Seite wird neu geladen …</span>';
            setTimeout(function () { location.reload(); }, 2000);
        });
    }
    var optionen = {
        group: 'shop-kategorien', handle: '.kat-griff', animation: 150, fallbackOnBody: true, swapThreshold: 0.6,
        ghostClass: 'kat-geist', chosenClass: 'kat-gewaehlt',
        onStart: function () { bereich.classList.add('kat-ziehen'); },
        onMove: function (evt) {
            if (!evt.to.classList.contains('kat-kinder')) return true;
            if (evt.to.parentElement.parentElement !== baum) return false;          // nur eine Ebene
            var eigene = evt.dragged.querySelector(':scope > .kat-kinder');
            if (eigene && eigene.children.length) return false;                      // hat selbst Unterkategorien
            return true;
        },
        onEnd: function (evt) {
            bereich.classList.remove('kat-ziehen');
            if (evt.from !== evt.to || evt.oldIndex !== evt.newIndex) speichern();
        }
    };
    new Sortable(baum, optionen);
    baum.querySelectorAll('.kat-kinder').forEach(function (ul) { new Sortable(ul, optionen); });
})();
</script>
<script>
document.querySelectorAll('[data-kat]').forEach(function (b) {
    b.addEventListener('click', function () {
        var d = JSON.parse(b.dataset.kat || '{}');
        var f = document.getElementById('katModal');
        f.querySelector('#k-id').value = d.id || '';
        f.querySelector('#k-name').value = d.name || '';
        var sel = f.querySelector('#k-parent');
        sel.value = d.parent_id || '';
        Array.prototype.forEach.call(sel.options, function (o) { o.disabled = !!d.id && o.value === String(d.id); });
        f.querySelector('#k-beschreibung').value = d.beschreibung || '';
        f.querySelector('#k-sortierung').value = d.sortierung || 100;
        f.querySelector('#k-aktiv').checked = d.id ? d.aktiv == 1 : true;
        bootstrap.Modal.getOrCreateInstance(f).show();
    });
});
</script>
