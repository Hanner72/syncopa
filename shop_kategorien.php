<?php
// shop_kategorien.php – Kategorien des Shops: Baum mit Drag & Drop, Suche, Filter, Sortierung (Katalog-Server, Admin)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin() || !SHOP_IST_ZENTRALE) {
    Session::setFlashMessage('danger', 'Kein Zugriff auf die Shop-Verwaltung.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['aktion'] ?? '') {
            case 'kategorie':
                Shop::kategorieSpeichern($_POST, (int)($_POST['id'] ?? 0) ?: null);
                Session::setFlashMessage('success', 'Kategorie gespeichert.');
                break;
            case 'kategorie_loeschen':
                Shop::kategorieLoeschen((int)$_POST['id']);
                Session::setFlashMessage('success', 'Kategorie gelöscht. Artikel darin bleiben erhalten (ohne Kategorie).');
                break;
        }
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: shop_kategorien.php');
    exit;
}

$kategorien = Shop::kategorienBaum();
$hauptKats  = array_values(array_filter($kategorien, fn($k) => (int)$k['ebene'] === 0));
$kinderVon  = [];
foreach ($kategorien as $k) if ($k['ebene']) $kinderVon[(int)$k['parent_id']][] = $k;
// Artikel je Kategorie (direkt) und für Hauptkategorien zusätzlich mit ihren Unterkategorien
$direkt = [];
foreach (Database::getInstance()->fetchAll("SELECT kategorie_id, COUNT(*) n FROM shop_artikel WHERE kategorie_id IS NOT NULL GROUP BY kategorie_id") as $r) $direkt[(int)$r['kategorie_id']] = (int)$r['n'];
$gesamt = fn($id) => ($direkt[$id] ?? 0) + array_sum(array_map(fn($c) => $direkt[(int)$c['id']] ?? 0, $kinderVon[$id] ?? []));
$ohneKat = (int)(Database::getInstance()->fetchOne("SELECT COUNT(*) n FROM shop_artikel WHERE kategorie_id IS NULL")['n'] ?? 0);
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

$zeile = function (array $k, int $anzahl) use ($h) { ob_start(); ?>
    <div class="kat-zeile <?php echo $k['aktiv'] ? '' : 'kat-inaktiv'; ?>">
        <span class="kat-griff" title="Ziehen zum Verschieben"><i class="bi bi-grip-vertical"></i></span>
        <span class="kat-name"><?php echo $h($k['name']); ?><?php if (!$k['aktiv']): ?> <span class="badge bg-secondary ms-1">inaktiv</span><?php endif; ?></span>
        <span class="kat-besch small text-muted d-none d-md-inline"><?php echo $h($k['beschreibung'] ?? ''); ?></span>
        <a class="kat-anzahl" href="shop_artikel.php?kat=<?php echo (int)$k['id']; ?>" title="Artikel dieser Kategorie anzeigen"><?php echo $anzahl; ?> Artikel</a>
        <span class="kat-knoepfe text-nowrap">
            <button type="button" class="btn btn-sm btn-outline-primary" title="Bearbeiten" data-kat='<?php echo $h(json_encode($k, JSON_UNESCAPED_UNICODE)); ?>'><i class="bi bi-pencil"></i></button>
            <button type="submit" form="kat-del-<?php echo (int)$k['id']; ?>" class="btn btn-sm btn-outline-danger" title="Löschen" onclick="return confirm('Kategorie „<?php echo $h(addslashes($k['name'])); ?>“ löschen?')"><i class="bi bi-trash"></i></button>
        </span>
    </div>
<?php return ob_get_clean(); };

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-tags"></i> Kategorien</h1>
    <div class="d-flex gap-2">
        <a href="shop_artikel.php" class="btn btn-outline-primary"><i class="bi bi-box-seam"></i> Artikel</a>
        <button class="btn btn-success" data-kat='{}'><i class="bi bi-plus-lg"></i> Neue Kategorie</button>
    </div>
</div>

<?php if (!$kategorien): ?>
<div class="card"><div class="card-body text-center text-muted py-5">Noch keine Kategorien. Ohne Kategorie landen Artikel im Shop unter „Sonstiges“.</div></div>
<?php else: ?>
<!-- Suche, Filter, Sortierung -->
<div class="card mb-3"><div class="card-body py-2">
    <div class="row g-2 align-items-center">
        <div class="col-md-4"><div class="input-group input-group-sm"><span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" id="k-suche" placeholder="Kategorie suchen …" autocomplete="off"></div></div>
        <div class="col-6 col-md-3"><select class="form-select form-select-sm" id="k-filter" aria-label="Filter">
            <option value="alle">Alle Kategorien</option>
            <option value="aktiv">Nur aktive</option>
            <option value="inaktiv">Nur inaktive</option>
            <option value="mit">Mit Artikeln</option>
            <option value="leer">Ohne Artikel</option>
        </select></div>
        <div class="col-6 col-md-3"><select class="form-select form-select-sm" id="k-sort" aria-label="Sortierung">
            <option value="eigen">Eigene Reihenfolge</option>
            <option value="name">Name A–Z</option>
            <option value="name_ab">Name Z–A</option>
            <option value="anzahl">Meiste Artikel zuerst</option>
        </select></div>
        <div class="col-md-2 text-md-end small text-muted" id="k-zaehler"></div>
    </div>
</div></div>

<div class="card">
    <div class="card-body pb-2">
        <div class="small text-muted mb-2" id="k-hinweis"><i class="bi bi-arrows-move"></i> Am Griff <i class="bi bi-grip-vertical"></i> ziehen, um die Reihenfolge zu ändern. <strong>Eingerückt unter eine Kategorie ziehen</strong> macht sie zur Unterkategorie, <strong>nach links heraus ziehen</strong> wieder zur Hauptkategorie. Wird sofort gespeichert.</div>
        <div class="small text-warning mb-2" id="k-gesperrt" style="display:none"><i class="bi bi-lock"></i> Verschieben ist nur in der <strong>eigenen Reihenfolge</strong> ohne Suche und Filter möglich.</div>
        <ul class="kat-baum" id="kat-baum">
            <?php foreach ($hauptKats as $pos => $k): $id = (int)$k['id']; ?>
            <li class="kat-item" data-id="<?php echo $id; ?>" data-pos="<?php echo $pos; ?>" data-name="<?php echo $h(mb_strtolower($k['name'] . ' ' . ($k['beschreibung'] ?? ''))); ?>" data-aktiv="<?php echo (int)$k['aktiv']; ?>" data-anzahl="<?php echo $gesamt($id); ?>">
                <?php echo $zeile($k, $gesamt($id)); ?>
                <ul class="kat-kinder">
                    <?php foreach ($kinderVon[$id] ?? [] as $cpos => $c): $cid = (int)$c['id']; ?>
                    <li class="kat-item" data-id="<?php echo $cid; ?>" data-pos="<?php echo $cpos; ?>" data-name="<?php echo $h(mb_strtolower($c['name'] . ' ' . ($c['beschreibung'] ?? ''))); ?>" data-aktiv="<?php echo (int)$c['aktiv']; ?>" data-anzahl="<?php echo $direkt[$cid] ?? 0; ?>"><?php echo $zeile($c, $direkt[$cid] ?? 0); ?><ul class="kat-kinder"></ul></li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="small text-muted mt-2 py-3 text-center" id="k-leer" style="display:none">Keine passende Kategorie gefunden.</div>
        <?php if ($ohneKat): ?><div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> <a href="shop_artikel.php?kat=0"><?php echo $ohneKat; ?> Artikel ohne Kategorie</a> – erscheinen im Shop unter „Sonstiges“.</div><?php endif; ?>
        <div class="small mt-1" id="kat-status" style="min-height:1.4em"></div>
    </div>
</div>
<?php foreach ($kategorien as $k): ?>
<form method="post" id="kat-del-<?php echo (int)$k['id']; ?>" class="d-none"><input type="hidden" name="aktion" value="kategorie_loeschen"><input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>"></form>
<?php endforeach; ?>
<?php endif; ?>

<style>
.kat-baum,.kat-kinder{list-style:none;margin:0;padding:0}
.kat-kinder{margin-left:34px;min-height:4px;border-radius:8px;transition:background .15s}
.kat-kinder .kat-kinder{display:none}                                   /* nur eine Ebene */
.kat-zeile{display:flex;align-items:center;gap:10px;background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:7px 10px;margin:4px 0}
.kat-baum > li > .kat-zeile .kat-name{font-weight:700}
.kat-kinder .kat-zeile{border-style:dashed}
.kat-zeile .kat-name{flex:0 1 auto;min-width:0}
.kat-zeile .kat-besch{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.kat-zeile .kat-anzahl{margin-left:auto;font-size:.75rem;white-space:nowrap;background:var(--bg-body);border:1px solid var(--border);border-radius:999px;padding:1px 9px;color:var(--text-secondary);text-decoration:none}
.kat-zeile .kat-anzahl:hover{border-color:var(--c-primary);color:var(--c-primary)}
.kat-inaktiv{opacity:.6}
.kat-griff{cursor:grab;color:var(--text-muted);padding:2px 4px;font-size:1.1rem;touch-action:none}
.kat-griff:active{cursor:grabbing}
.kat-gesperrt .kat-griff{cursor:not-allowed;opacity:.3}
.kat-ziehen .kat-baum > li > .kat-kinder{min-height:30px;background:rgba(68,113,163,.06);outline:1px dashed rgba(68,113,163,.35)}
.kat-geist > .kat-zeile{background:rgba(68,113,163,.12);border-color:var(--c-primary)}
.kat-gewaehlt > .kat-zeile{box-shadow:0 6px 16px rgba(0,0,0,.15)}
.kat-treffer > .kat-zeile .kat-name{background:rgba(209,154,62,.25);border-radius:3px}
</style>

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
        <div class="mb-3"><label class="form-label" for="k-beschreibung">Beschreibung</label><input class="form-control" id="k-beschreibung" name="beschreibung"></div>
        <div class="row g-3">
            <div class="col-6"><label class="form-label" for="k-sortierung">Reihenfolge</label><input type="number" class="form-control" id="k-sortierung" name="sortierung" value="100"></div>
            <div class="col-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" id="k-aktiv" name="aktiv" value="1" checked><label class="form-check-label" for="k-aktiv">Aktiv</label></div></div>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-primary">Speichern</button></div>
</form></div></div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    var baum = document.getElementById('kat-baum');
    var sortierbare = [];

    // ---- Drag & Drop (auch als Unter- bzw. Hauptkategorie, eine Ebene) – sofort gespeichert
    if (baum && window.Sortable) {
        var bereich = baum.parentElement, status = document.getElementById('kat-status');
        var struktur = function () {
            return Array.prototype.map.call(baum.children, function (li) {
                var ul = li.querySelector(':scope > .kat-kinder');
                return { id: +li.dataset.id, kinder: ul ? Array.prototype.map.call(ul.children, function (c) { return +c.dataset.id; }) : [] };
            });
        };
        var speichern = function () {
            status.innerHTML = '<span class="text-muted"><i class="bi bi-arrow-repeat"></i> Wird gespeichert …</span>';
            fetch('api/shop_kategorien_sortieren.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ struktur: struktur() })
            }).then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (d) { if (!r.ok || !d.success) throw new Error(d.error || 'Speichern fehlgeschlagen.'); });
            }).then(function () {
                // neue Reihenfolge ist jetzt die „eigene“
                Array.prototype.forEach.call(baum.children, function (li, i) {
                    li.dataset.pos = i;
                    Array.prototype.forEach.call(li.querySelector(':scope > .kat-kinder').children, function (c, j) { c.dataset.pos = j; });
                });
                status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Gespeichert</span>';
                setTimeout(function () { status.innerHTML = ''; }, 2500);
            }).catch(function (e) {
                status.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + e.message + ' Die Seite wird neu geladen …</span>';
                setTimeout(function () { location.reload(); }, 2000);
            });
        };
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
        sortierbare.push(new Sortable(baum, optionen));
        baum.querySelectorAll('.kat-kinder').forEach(function (ul) { sortierbare.push(new Sortable(ul, optionen)); });
    }

    // ---- Suche, Filter, Sortierung
    var suche = document.getElementById('k-suche'), filter = document.getElementById('k-filter'), sort = document.getElementById('k-sort');
    if (baum && suche) {
        var passt = function (li, q, f) {
            var okText = !q || li.dataset.name.indexOf(q) > -1;
            var a = +li.dataset.anzahl, aktiv = li.dataset.aktiv === '1';
            var okFilter = f === 'alle' || (f === 'aktiv' && aktiv) || (f === 'inaktiv' && !aktiv) || (f === 'mit' && a > 0) || (f === 'leer' && a === 0);
            return okText && okFilter;
        };
        var vergleich = function (art) {
            return function (x, y) {
                if (art === 'name') return x.dataset.name.localeCompare(y.dataset.name, 'de');
                if (art === 'name_ab') return y.dataset.name.localeCompare(x.dataset.name, 'de');
                if (art === 'anzahl') return (+y.dataset.anzahl - +x.dataset.anzahl) || x.dataset.name.localeCompare(y.dataset.name, 'de');
                return +x.dataset.pos - +y.dataset.pos;
            };
        };
        var anwenden = function () {
            var q = suche.value.trim().toLowerCase(), f = filter.value, art = sort.value, sichtbar = 0;
            // sortieren (Hauptebene und je Hauptkategorie die Unterkategorien)
            Array.prototype.slice.call(baum.children).sort(vergleich(art)).forEach(function (li) { baum.appendChild(li); });
            baum.querySelectorAll(':scope > li > .kat-kinder').forEach(function (ul) {
                Array.prototype.slice.call(ul.children).sort(vergleich(art)).forEach(function (c) { ul.appendChild(c); });
            });
            // filtern: Hauptkategorie bleibt sichtbar, wenn sie selbst oder eine Unterkategorie passt
            Array.prototype.forEach.call(baum.children, function (li) {
                var selbst = passt(li, q, f), kinderTreffer = 0;
                var elternTreffer = !!q && li.dataset.name.indexOf(q) > -1;
                li.querySelectorAll(':scope > .kat-kinder > li').forEach(function (c) {
                    // Unterkategorie zeigen, wenn sie selbst passt – oder wenn die Suche die Hauptkategorie trifft (dann zählt nur der Filter)
                    var ok = passt(c, q, f) || (elternTreffer && passt(c, '', f));
                    c.style.display = ok ? '' : 'none';
                    c.classList.toggle('kat-treffer', !!q && c.dataset.name.indexOf(q) > -1);
                    if (ok) { kinderTreffer++; sichtbar++; }
                });
                var zeigen = selbst || kinderTreffer > 0;
                li.style.display = zeigen ? '' : 'none';
                li.classList.toggle('kat-treffer', !!q && li.dataset.name.indexOf(q) > -1);
                if (selbst) sichtbar++;
            });
            document.getElementById('k-zaehler').textContent = sichtbar + ' von ' + baum.querySelectorAll('li.kat-item').length + ' Kategorien';
            document.getElementById('k-leer').style.display = sichtbar ? 'none' : '';
            // Ziehen nur in eigener Reihenfolge ohne Suche/Filter
            var frei = art === 'eigen' && !q && f === 'alle';
            sortierbare.forEach(function (s) { s.option('disabled', !frei); });
            baum.classList.toggle('kat-gesperrt', !frei);
            document.getElementById('k-hinweis').style.display = frei ? '' : 'none';
            document.getElementById('k-gesperrt').style.display = frei ? 'none' : '';
            try { sessionStorage.setItem('shop_kategorien_ansicht', JSON.stringify({ q: suche.value, f: f, s: art })); } catch (e) {}
        };
        try {
            var gemerkt = JSON.parse(sessionStorage.getItem('shop_kategorien_ansicht') || 'null');
            if (gemerkt) { suche.value = gemerkt.q || ''; filter.value = gemerkt.f || 'alle'; sort.value = gemerkt.s || 'eigen'; }
        } catch (e) {}
        suche.addEventListener('input', anwenden);
        suche.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
        filter.addEventListener('change', anwenden);
        sort.addEventListener('change', anwenden);
        anwenden();
    }

    // ---- Dialog Neu/Bearbeiten
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
})();
</script>
