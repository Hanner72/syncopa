<?php
// shop_artikel.php – Artikelliste des Shops mit Suche, Filtern und Sortierung (Katalog-Server, Admin)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin() || !SHOP_IST_ZENTRALE) {
    Session::setFlashMessage('danger', 'Kein Zugriff auf die Shop-Verwaltung.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aktion'] ?? '') === 'artikel_loeschen') {
    try {
        Shop::artikelLoeschen((int)$_POST['id']);
        Session::setFlashMessage('success', 'Artikel gelöscht.');
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: shop_artikel.php?zurueck=1');
    exit;
}

$artikel    = Shop::artikelListe();          // bereits nach Kategorienbaum sortiert
$kategorien = Shop::kategorienBaum();
$elternVon  = [];
foreach ($kategorien as $k) if ($k['ebene']) $elternVon[(int)$k['id']] = (int)$k['parent_id'];
$vorKat     = isset($_GET['kat']) ? (string)(int)$_GET['kat'] : '';   // aus der Kategorienseite: ?kat=ID (0 = ohne Kategorie)
$bildBasis  = 'uploads/shop';
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-box-seam"></i> Artikel</h1>
    <div class="d-flex gap-2">
        <a href="shop_kategorien.php" class="btn btn-outline-primary"><i class="bi bi-tags"></i> Kategorien</a>
        <a href="shop_artikel_bearbeiten.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> Neuer Artikel</a>
    </div>
</div>

<!-- Suche, Filter, Sortierung -->
<div class="card mb-3"><div class="card-body py-2">
    <div class="row g-2 align-items-center">
        <div class="col-lg-3 col-md-6"><div class="input-group input-group-sm"><span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" id="a-suche" placeholder="Name, Artikelnummer, Kategorie …" autocomplete="off"></div></div>
        <div class="col-lg-3 col-md-6"><select class="form-select form-select-sm" id="a-kat" aria-label="Kategorie">
            <option value="">Alle Kategorien</option>
            <?php foreach ($kategorien as $k): ?>
            <option value="<?php echo (int)$k['id']; ?>"><?php echo $k['ebene'] ? '&nbsp;&nbsp;&nbsp;↳ ' : ''; ?><?php echo $h($k['name']); ?></option>
            <?php endforeach; ?>
            <option value="0">– ohne Kategorie –</option>
        </select></div>
        <div class="col-lg-2 col-6"><select class="form-select form-select-sm" id="a-sichtbar" aria-label="Sichtbarkeit">
            <option value="">Sichtbar &amp; verborgen</option>
            <option value="1">Nur sichtbare</option>
            <option value="0">Nur verborgene</option>
        </select></div>
        <div class="col-lg-2 col-6"><select class="form-select form-select-sm" id="a-merkmal" aria-label="Kennzeichen">
            <option value="">Alle Kennzeichen</option>
            <option value="aktion">Aktion</option>
            <option value="ausverkauft">Ausverkauft</option>
            <option value="veredelung">Veredelung möglich</option>
            <option value="ohnebild">Ohne Bild</option>
            <option value="groessen">Mit Größen</option>
        </select></div>
        <div class="col-lg-2 col-md-12 d-flex gap-1">
            <select class="form-select form-select-sm" id="a-sort" aria-label="Sortierung">
                <option value="baum">Nach Kategorie</option>
                <option value="name">Name A–Z</option>
                <option value="name_ab">Name Z–A</option>
                <option value="preis">Preis aufsteigend</option>
                <option value="preis_ab">Preis absteigend</option>
                <option value="neu">Zuletzt geändert</option>
                <option value="eigen" hidden>Eigene Sortierung</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="a-reset" title="Suche und Filter zurücksetzen"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
</div></div>

<div class="alert alert-warning alert-permanent d-none d-flex flex-wrap align-items-center gap-2" id="a-leer">
    <i class="bi bi-funnel"></i> <span>Keine Artikel passen zu Suche und Filter.</span>
    <button type="button" class="btn btn-sm btn-warning ms-auto" id="a-leer-reset"><i class="bi bi-x-lg"></i> Filter zurücksetzen und alle anzeigen</button>
</div>
<style>.filter-aktiv{border-color:var(--c-warning)!important;box-shadow:0 0 0 .15rem rgba(209,154,62,.25)!important}</style>

<div class="card">
    <div class="card-body">
        <?php if (!$artikel): ?>
        <div class="text-center text-muted py-4">Noch keine Artikel. Lege mit <strong>Neuer Artikel</strong> den ersten an.</div>
        <?php else: ?>
        <table class="table align-middle mb-0" id="artikelTabelle" style="width:100%">
            <thead class="table-light"><tr>
                <th style="width:60px"></th><th>Artikel</th><th>Kategorie</th><th>Größen</th><th class="text-end">Preis</th><th>Aktion</th>
                <th class="text-center">Sichtbar</th><th class="text-center">Ausverkauft</th><th>Geändert</th><th></th><th>Pos</th>
            </tr></thead>
            <tbody>
            <?php foreach ($artikel as $pos => $a):
                $kid = (int)($a['kategorie_id'] ?? 0);
                $geaendert = $a['aktualisiert_am'] ?: $a['erstellt_am'];
                $merkmale = trim(implode(' ', array_filter([
                    !empty($a['aktion']) ? 'aktion' : '', !empty($a['ausverkauft']) ? 'ausverkauft' : '', !empty($a['veredelung']) ? 'veredelung' : '',
                    $a['hauptbild'] ? '' : 'ohnebild', (int)$a['anzahl_varianten'] > 0 ? 'groessen' : '',
                ]))); ?>
            <tr class="<?php echo $a['aktiv'] ? '' : 'text-muted'; ?>" data-id="<?php echo (int)$a['id']; ?>" data-kat="<?php echo $kid; ?>" data-eltern="<?php echo (int)($elternVon[$kid] ?? 0); ?>"
                data-aktiv="<?php echo (int)$a['aktiv']; ?>" data-aktion="<?php echo (int)!empty($a['aktion']); ?>" data-preis="<?php echo (float)$a['preis']; ?>" data-aktionspreis="<?php echo (float)($a['aktionspreis'] ?? 0); ?>" data-merkmale="<?php echo $h($merkmale); ?>">
                <td>
                    <?php if ($a['hauptbild']): ?>
                    <img src="<?php echo $h($bildBasis . '/' . rawurlencode($a['hauptbild'])); ?>" alt="" loading="lazy" style="width:48px;height:48px;object-fit:cover;border-radius:4px;border:1px solid var(--border)">
                    <?php else: ?>
                    <span class="text-muted d-inline-flex align-items-center justify-content-center" style="width:48px;height:48px;border:1px dashed var(--border);border-radius:4px"><i class="bi bi-image"></i></span>
                    <?php endif; ?>
                </td>
                <td data-order="<?php echo $h(mb_strtolower($a['name'])); ?>">
                    <a href="shop_artikel_bearbeiten.php?id=<?php echo (int)$a['id']; ?>" class="fw-semibold"><?php echo $h($a['name']); ?></a>
                    <span class="badge bg-secondary ms-1 b-aktiv <?php echo $a['aktiv'] ? 'd-none' : ''; ?>">verborgen</span>
                    <span class="badge bg-danger ms-1 b-aktion <?php echo !empty($a['aktion']) ? '' : 'd-none'; ?>"><?php echo $h($a['aktion_text'] ?: 'Aktion'); ?></span>
                    <span class="badge bg-dark ms-1 b-ausverkauft <?php echo !empty($a['ausverkauft']) ? '' : 'd-none'; ?>">Ausverkauft</span>
                    <?php if (!empty($a['veredelung'])): ?><span class="badge bg-primary-subtle text-primary ms-1" title="Veredelung möglich"><i class="bi bi-stars"></i></span><?php endif; ?>
                    <?php if ($a['artikelnummer']): ?><br><small class="text-muted"><?php echo $h($a['artikelnummer']); ?></small><?php endif; ?>
                </td>
                <td class="small" data-order="<?php echo (int)$pos; ?>"><?php if ($a['kategorie_name']): ?>
                    <?php if (!empty($a['oberkategorie_name'])): ?><span class="text-muted"><?php echo $h($a['oberkategorie_name']); ?></span> <i class="bi bi-chevron-right text-muted" style="font-size:.7em"></i> <?php endif; ?><?php echo $h($a['kategorie_name']); ?>
                <?php else: ?><span class="text-muted">–</span><?php endif; ?></td>
                <td class="small" data-order="<?php echo (int)$a['anzahl_varianten']; ?>"><?php echo (int)$a['anzahl_varianten'] ? ((int)$a['anzahl_varianten'] . ' Größen') : '<span class="text-muted">eine</span>'; ?></td>
                <td class="text-end text-nowrap zelle-preis" data-order="<?php echo (int)round(Shop::effektivPreis($a) * 100); ?>" style="font-variant-numeric:tabular-nums"><?php if (Shop::effektivPreis($a) < (float)$a['preis']): ?><s class="text-muted small me-1"><?php echo Shop::euro($a['preis']); ?></s><span class="text-danger fw-semibold"><?php echo Shop::euro(Shop::effektivPreis($a)); ?></span><?php else: ?><?php echo Shop::euro($a['preis']); ?><?php endif; ?></td>
                <td data-order="<?php echo (int)!empty($a['aktion']); ?>"><div class="d-flex align-items-center gap-2">
                    <div class="form-check form-switch m-0"><input class="form-check-input schalter" type="checkbox" role="switch" data-feld="aktion" <?php echo !empty($a['aktion']) ? 'checked' : ''; ?> title="Aktion" aria-label="Aktion für „<?php echo $h($a['name']); ?>“"></div>
                    <div class="input-group input-group-sm" style="width:112px"><input class="form-control text-end aktionspreis-feld" inputmode="decimal" value="<?php echo !empty($a['aktionspreis']) ? $h(number_format((float)$a['aktionspreis'], 2, ',', '')) : ''; ?>" placeholder="Preis" title="Aktionspreis – leer = nur Label" aria-label="Aktionspreis für „<?php echo $h($a['name']); ?>“"><span class="input-group-text">€</span></div>
                </div></td>
                <td class="text-center" data-order="<?php echo (int)$a['aktiv']; ?>"><div class="form-check form-switch d-inline-block m-0">
                    <input class="form-check-input schalter" type="checkbox" role="switch" data-feld="aktiv" <?php echo $a['aktiv'] ? 'checked' : ''; ?> title="Für Vereine sichtbar" aria-label="„<?php echo $h($a['name']); ?>“ sichtbar"></div></td>
                <td class="text-center" data-order="<?php echo (int)!empty($a['ausverkauft']); ?>"><div class="form-check form-switch d-inline-block m-0">
                    <input class="form-check-input schalter schalter-ausverkauft" type="checkbox" role="switch" data-feld="ausverkauft" <?php echo !empty($a['ausverkauft']) ? 'checked' : ''; ?> title="Ausverkauft" aria-label="„<?php echo $h($a['name']); ?>“ ausverkauft"></div></td>
                <td class="small text-muted text-nowrap zelle-geaendert" data-order="<?php echo $geaendert ? strtotime($geaendert) : 0; ?>"><?php echo $geaendert ? date('d.m.Y', strtotime($geaendert)) : '–'; ?></td>
                <td class="text-end text-nowrap">
                    <a href="shop_artikel_bearbeiten.php?id=<?php echo (int)$a['id']; ?>" class="btn btn-sm btn-outline-primary" title="Bearbeiten"><i class="bi bi-pencil"></i></a>
                    <button type="submit" form="art-del-<?php echo (int)$a['id']; ?>" class="btn btn-sm btn-outline-danger" title="Löschen" onclick="return confirm('Artikel „<?php echo $h(addslashes($a['name'])); ?>“ wirklich löschen?')"><i class="bi bi-trash"></i></button>
                </td>
                <td><?php echo (int)$pos; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php foreach ($artikel as $a): ?>
<form method="post" id="art-del-<?php echo (int)$a['id']; ?>" class="d-none"><input type="hidden" name="aktion" value="artikel_loeschen"><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>"></form>
<?php endforeach; ?>

<?php include 'includes/footer.php'; ?>
<?php if ($artikel): ?>
<script>
$(document).ready(function () {
    var SPEICHER = 'shop_artikel_filter';
    var feld = function (id) { return document.getElementById(id); };
    var kat = feld('a-kat'), sichtbar = feld('a-sichtbar'), merkmal = feld('a-merkmal'), sort = feld('a-sort'), suche = feld('a-suche');
    var SORTIERUNG = { baum: [[10, 'asc']], name: [[1, 'asc']], name_ab: [[1, 'desc']], preis: [[4, 'asc']], preis_ab: [[4, 'desc']], neu: [[8, 'desc']] };

    // eigene Filter (Kategorie inkl. Unterkategorien, Sichtbarkeit, Kennzeichen) – nur für diese Tabelle
    $.fn.dataTable.ext.search.push(function (settings, daten, index) {
        if (settings.nTable.id !== 'artikelTabelle') return true;
        var tr = settings.aoData[index].nTr;
        if (kat.value !== '' && tr.dataset.kat !== kat.value && tr.dataset.eltern !== kat.value) return false;
        if (sichtbar.value !== '' && tr.dataset.aktiv !== sichtbar.value) return false;
        if (merkmal.value !== '' && (' ' + tr.dataset.merkmale + ' ').indexOf(' ' + merkmal.value + ' ') < 0) return false;
        return true;
    });

    // Gemerkte Filter, Suche, Sortierung und Seite gelten nur beim Zurückkommen (aus „Artikel bearbeiten“, nach dem Löschen: ?zurueck=1).
    // Über das Menü geöffnet zeigt die Liste immer alle Artikel.
    var ZURUECK = <?php echo isset($_GET['zurueck']) ? 'true' : 'false'; ?>;
    if (ZURUECK) {
        try {
            var g = JSON.parse(sessionStorage.getItem(SPEICHER) || 'null');
            if (g) { kat.value = g.kat || ''; sichtbar.value = g.sichtbar || ''; merkmal.value = g.merkmal || ''; sort.value = g.sort || 'baum'; }
        } catch (e) {}
    } else {
        try { sessionStorage.removeItem(SPEICHER); } catch (e) {}
    }
    // ein Wert, den die Auswahl nicht (mehr) kennt – z. B. gelöschte Kategorie – zählt als „alle“
    [kat, sichtbar, merkmal].forEach(function (s) { if (s.selectedIndex < 0) s.value = ''; });
    <?php if ($vorKat !== ''): ?>kat.value = <?php echo json_encode($vorKat); ?>;<?php endif; ?>
    if (location.search) history.replaceState(null, '', 'shop_artikel.php');   // Neuladen = frische Liste

    function sortAbgleichen(api) {
        var o = JSON.stringify(api.order().map(function (x) { return [x[0], x[1]]; }));
        var treffer = Object.keys(SORTIERUNG).filter(function (k) { return JSON.stringify(SORTIERUNG[k]) === o; })[0];
        sort.value = treffer || 'eigen';
    }

    var tabelle = $('#artikelTabelle').DataTable({
        stateSave: true,
        stateDuration: -1,
        stateLoadParams: function () { if (!ZURUECK) return false; },   // über das Menü: gemerkten Zustand nicht laden
        order: SORTIERUNG[sort.value] || SORTIERUNG.baum,
        columnDefs: [
            { targets: [0, 9], orderable: false, searchable: false },
            { targets: 10, visible: false, searchable: false }
        ],
        dom: '<"row align-items-center mb-2"<"col-auto"l>><"table-responsive"t><"row align-items-center mt-2"<"col-sm-5"i><"col-sm-7"p>>',
        // Erst wenn die Tabelle fertig ist (Sprachdatei geladen, gemerkter Zustand wiederhergestellt), die Bedienelemente abgleichen –
        // sonst bliebe das Suchfeld leer, während die Tabelle nach einem gemerkten Suchbegriff filtert.
        initComplete: function () {
            var api = this.api();
            suche.value = api.search();
            sortAbgleichen(api);
            api.draw();
            // gemerkte Seite leer (z. B. weniger Treffer als zuvor)? → auf Seite 1
            var info = api.page.info();
            if (info.recordsDisplay > 0 && info.start >= info.recordsDisplay) api.page('first').draw(false);
        }
    });

    var merken = function () {
        try { sessionStorage.setItem(SPEICHER, JSON.stringify({ kat: kat.value, sichtbar: sichtbar.value, merkmal: merkmal.value, sort: sort.value })); } catch (e) {}
    };
    suche.addEventListener('input', function () { tabelle.search(suche.value).draw(); });
    suche.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    [kat, sichtbar, merkmal].forEach(function (s) { s.addEventListener('change', function () { tabelle.draw(); merken(); }); });
    sort.addEventListener('change', function () { if (SORTIERUNG[sort.value]) { tabelle.order(SORTIERUNG[sort.value]).draw(); merken(); } });
    // Klick auf einen Spaltenkopf: Auswahl zeigt „Eigene Sortierung“, sofern sie keiner Vorgabe entspricht
    tabelle.on('order.dt', function () { sortAbgleichen(tabelle); });
    var zuruecksetzen = function () {
        suche.value = ''; kat.value = ''; sichtbar.value = ''; merkmal.value = ''; sort.value = 'baum';
        tabelle.search('').order(SORTIERUNG.baum).page('first').draw(); merken();
    };
    feld('a-reset').addEventListener('click', zuruecksetzen);
    feld('a-leer-reset').addEventListener('click', zuruecksetzen);
    // Aktive Filter deutlich zeigen; leeres Ergebnis erklären
    tabelle.on('draw.dt', function () {
        var aktiv = suche.value.trim() !== '' || kat.value !== '' || sichtbar.value !== '' || merkmal.value !== '';
        [kat, sichtbar, merkmal].forEach(function (s) { s.classList.toggle('filter-aktiv', s.value !== ''); });
        suche.classList.toggle('filter-aktiv', suche.value.trim() !== '');
        var r = feld('a-reset');
        r.classList.toggle('btn-warning', aktiv); r.classList.toggle('btn-outline-secondary', !aktiv);
        r.innerHTML = aktiv ? '<i class="bi bi-funnel-fill"></i> Filter löschen' : '<i class="bi bi-x-lg"></i>';
        var info = tabelle.page.info();
        feld('a-leer').classList.toggle('d-none', !(info.recordsTotal > 0 && info.recordsDisplay === 0));
    });

    // ---- Direkt bearbeiten: Sichtbar, Ausverkauft, Aktion (Schalter) und Aktionspreis – sofort gespeichert, ohne Neuladen
    var euro = function (b) { return b.toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'; };
    var senden = function (tr, feld, wert) {
        return fetch('api/shop_artikel_schalten.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: +tr.dataset.id, feld: feld, wert: wert })
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) { if (!r.ok || !d.success) throw new Error(d.error || 'Speichern fehlgeschlagen.'); return d; });
        });
    };
    var merkmalSetzen = function (tr, name, an) {   // eigener Name: „merkmal“ ist das Auswahlfeld „Kennzeichen“
        var m = (' ' + tr.dataset.merkmale + ' ').replace(' ' + name + ' ', ' ');
        tr.dataset.merkmale = (an ? m + name : m).trim().replace(/\s+/g, ' ');
    };
    // Preiszelle: bei Aktion mit Aktionspreis den normalen Preis durchstreichen
    var preisZeigen = function (tr) {
        var p = +tr.dataset.preis, ap = +tr.dataset.aktionspreis, aktion = tr.dataset.aktion === '1';
        var gilt = aktion && ap > 0 ? ap : p, z = tr.querySelector('.zelle-preis');
        z.innerHTML = gilt < p ? '<s class="text-muted small me-1">' + euro(p) + '</s><span class="text-danger fw-semibold">' + euro(gilt) + '</span>' : euro(p);
        z.setAttribute('data-order', Math.round(gilt * 100));   // in Cent: die deutsche Tabellen-Einstellung liest den Punkt als Tausendertrennzeichen
    };
    var nachSpeichern = function (tr, d, text) {
        var zg = tr.querySelector('.zelle-geaendert');
        zg.textContent = d.datum; zg.setAttribute('data-order', Math.floor(Date.now() / 1000));
        tabelle.row(tr).invalidate('dom');   // Sortierung/Filter kennen den neuen Stand (ohne neu zu zeichnen)
        var name = (tr.querySelector('td a.fw-semibold') || {}).textContent || 'Artikel';
        if (typeof showToast === 'function') showToast('„' + name + '“ ' + text + '.', 'success');
    };

    $('#artikelTabelle').on('change', 'input.schalter', function () {
        var sch = this, tr = sch.closest('tr'), feld = sch.dataset.feld, wert = sch.checked;
        sch.disabled = true;
        senden(tr, feld, wert).then(function (d) {
            var text;
            if (feld === 'aktiv') {
                tr.dataset.aktiv = wert ? '1' : '0';
                tr.classList.toggle('text-muted', !wert);
                tr.querySelector('.b-aktiv').classList.toggle('d-none', wert);
                text = wert ? 'ist jetzt für die Vereine sichtbar' : 'ist jetzt verborgen';
            } else if (feld === 'ausverkauft') {
                merkmalSetzen(tr, 'ausverkauft', wert);
                tr.querySelector('.b-ausverkauft').classList.toggle('d-none', !wert);
                text = wert ? 'ist jetzt als ausverkauft markiert' : 'ist wieder bestellbar';
            } else {
                tr.dataset.aktion = wert ? '1' : '0';
                merkmalSetzen(tr, 'aktion', wert);
                tr.querySelector('.b-aktion').classList.toggle('d-none', !wert);
                preisZeigen(tr);
                text = wert ? 'ist jetzt in Aktion' + (+tr.dataset.aktionspreis > 0 ? ' um ' + euro(+tr.dataset.aktionspreis) : '') : 'ist nicht mehr in Aktion';
            }
            sch.closest('td').setAttribute('data-order', wert ? '1' : '0');
            nachSpeichern(tr, d, text);
        }).catch(function (e) {
            sch.checked = !wert;   // zurück auf den alten Stand
            alert(e.message);
        }).then(function () { sch.disabled = false; });
    });

    // Aktionspreis: speichern beim Verlassen des Feldes bzw. mit Enter
    $('#artikelTabelle').on('keydown', 'input.aktionspreis-feld', function (e) { if (e.key === 'Enter') { e.preventDefault(); this.blur(); } });
    $('#artikelTabelle').on('change', 'input.aktionspreis-feld', function () {
        var f = this, tr = f.closest('tr'), alt = +tr.dataset.aktionspreis;
        f.disabled = true;
        senden(tr, 'aktionspreis', f.value.trim()).then(function (d) {
            var p = d.aktionspreis ? +d.aktionspreis : 0;
            tr.dataset.aktionspreis = p;
            f.value = p ? p.toFixed(2).replace('.', ',') : '';
            preisZeigen(tr);
            nachSpeichern(tr, d, p ? 'hat jetzt den Aktionspreis ' + euro(p) + (tr.dataset.aktion === '1' ? '' : ' (gilt, sobald „Aktion“ an ist)') : 'hat keinen Aktionspreis mehr');
        }).catch(function (e) {
            f.value = alt ? alt.toFixed(2).replace('.', ',') : '';
            alert(e.message);
        }).then(function () { f.disabled = false; });
    });
});
</script>
<?php endif; ?>
