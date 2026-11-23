<?php
// bmv_apitest.php – Test der Zugriffspunkte des BMV-Datenservice (nur Administratoren).
// Liest die Liste der Zugriffspunkte live aus der API-Beschreibung (swagger.json), prüft sie mit den
// Zugangsdaten aus den Einstellungen und zeigt die Antworten als Tabelle. Nur GET – es wird nichts geschrieben.
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin()) {
    Session::setFlashMessage('danger', 'Diese Seite ist nur für Administratoren.');
    header('Location: index.php');
    exit;
}

const APITEST_SCHLUESSEL = 'apitest_bmv_stand'; // letzter Prüfstand (bewusst nicht bmv_…, das liest BmvApi als Zugangsdaten)

$api = new BmvApi();
$db  = Database::getInstance();
$aktion = $_GET['aktion'] ?? ($_POST['aktion'] ?? '');

// ---- Abruf eines Zugriffspunkts (AJAX) ----
if ($aktion === 'abruf') {
    header('Content-Type: application/json; charset=utf-8');
    session_write_close(); // langsame Antworten blockieren keine anderen Seiten
    try {
        $r = $api->abrufRoh((string)($_GET['pfad'] ?? ''), trim((string)($_GET['query'] ?? '')));
        $daten = null; $anzahl = null; $roh = null;
        if (is_string($r['body']) && $r['body'] !== '') {
            $daten = json_decode($r['body'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $anzahl = is_array($daten) ? (array_is_list($daten) ? count($daten) : 1) : 1;
            } else {
                $daten = null;
                $roh = mb_substr(trim(strip_tags($r['body'])), 0, 2000);
            }
        }
        $antwort = ['success' => true, 'code' => $r['code'], 'ms' => $r['ms'], 'bytes' => $r['bytes'], 'anzahl' => $anzahl,
                    'fehler' => $r['body'] === false ? ($r['fehler'] ?: 'Keine Verbindung') : '', 'roh' => $roh];
        if (empty($_GET['kurz'])) $antwort['daten'] = $daten;
        echo json_encode($antwort, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ---- Prüfstand merken (nach „Alle prüfen“) ----
if ($aktion === 'merken' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $e = json_decode((string)file_get_contents('php://input'), true);
    $stand = ['zeit' => date('Y-m-d H:i'), 'pfade' => [], 'ergebnisse' => []];
    foreach (array_slice((array)($e['pfade'] ?? []), 0, 1000) as $p) if (is_string($p)) $stand['pfade'][] = mb_substr($p, 0, 300);
    foreach (array_slice((array)($e['ergebnisse'] ?? []), 0, 1000, true) as $p => $x) {
        if (is_array($x)) $stand['ergebnisse'][mb_substr((string)$p, 0, 300)] = ['code' => (int)($x['code'] ?? 0), 'anzahl' => isset($x['anzahl']) ? (int)$x['anzahl'] : null, 'ms' => (int)($x['ms'] ?? 0)];
    }
    $json = json_encode($stand, JSON_UNESCAPED_UNICODE);
    $db->execute("INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?) ON DUPLICATE KEY UPDATE wert = ?", [APITEST_SCHLUESSEL, $json, $json]);
    echo json_encode(['success' => true, 'zeit' => $stand['zeit']]);
    exit;
}

// ---- Seite ----
$zeile = $db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = ?", [APITEST_SCHLUESSEL]);
$stand = $zeile ? (json_decode((string)$zeile['wert'], true) ?: null) : null;
// Vergleichsstand für „neu“: letzte Prüfung, sonst die Liste der früheren Testseite (apitest.php, Oktober 2026)
const APITEST_FRUEHER = [
    '/api/Musiker', '/api/MusikerService/GetMusikerTest', '/api/MusikerService/GetEmailJugendreferentVerein',
    '/api/KAPELLE', '/api/KAPELLE/GetKapelleKurz', '/api/KapellenCamba', '/api/KapellenDokumente',
    '/api/KapellenEhrungen', '/api/PersonenCamba', '/api/Mitgliedergruppen', '/api/MitgliedergruppenMitglieder',
    '/api/Mskehre', '/api/MskFkt', '/api/MskInst', '/api/MskMitart', '/api/MskPrf',
    '/api/Noten', '/api/Proben', '/api/AusrueckungenGaeste', '/api/MusikstueckeCamba/GetLiteraturliste',
    '/api/Konzertwertungsart', '/api/LiteraturArt',
    '/api/SetupBereich', '/api/SetupBesetzung', '/api/SetupFunktionen', '/api/SetupInstrumente',
    '/api/SetupKapellenehrungen', '/api/SetupKategorien', '/api/SetupKleidung', '/api/SetupMitgliederart',
    '/api/SetupMusikart', '/api/SetupProbenart', '/api/SetupProbenort', '/api/SetupRegister',
    '/api/SetupVeranstaltungsart', '/api/SetupZusammenkuenfteart',
];
$vergleichFrueher = !$stand;
$bekannt = array_flip($stand ? ($stand['pfade'] ?? []) : APITEST_FRUEHER);

// Von Syncopa genutzte Zugriffspunkte: im Code nach '/api/…' suchen
$genutzt = [];
foreach (glob(__DIR__ . '/classes/Bmv*.php') ?: [] as $datei) {
    if (preg_match_all("#'(/api/[A-Za-z0-9_/]+)'#", (string)file_get_contents($datei), $m)) {
        foreach ($m[1] as $p) $genutzt[$p] = true;
    }
}

$fehler = null;
$swagger = null;
$endpunkte = [];
$andere = ['POST' => 0, 'PUT' => 0, 'DELETE' => 0, 'PATCH' => 0];
try {
    $swagger = $api->swagger();
    foreach ($swagger['paths'] as $pfad => $ops) {
        foreach ($ops as $methode => $op) {
            $methode = strtoupper($methode);
            if ($methode !== 'GET') { if (isset($andere[$methode])) $andere[$methode]++; continue; }
            $pfadP = []; $queryP = [];
            foreach ((array)($op['parameters'] ?? []) as $par) {
                if (($par['in'] ?? '') === 'path') $pfadP[] = (string)$par['name'];
                if (($par['in'] ?? '') === 'query') $queryP[] = ['name' => (string)$par['name'], 'pflicht' => !empty($par['required'])];
            }
            $endpunkte[] = [
                'pfad' => (string)$pfad, 'bereich' => (string)(($op['tags'] ?? [''])[0] ?: 'Sonstige'),
                'info' => trim((string)($op['summary'] ?? $op['description'] ?? '')),
                'pfadParameter' => $pfadP, 'queryParameter' => $queryP,
                'genutzt' => isset($genutzt[$pfad]), 'neu' => !isset($bekannt[$pfad]) && !($vergleichFrueher && $pfadP),
                'zuletzt' => $stand['ergebnisse'][$pfad] ?? null,
            ];
        }
    }
    usort($endpunkte, fn($a, $b) => [$a['bereich'], $a['pfad']] <=> [$b['bereich'], $b['pfad']]);
} catch (\Throwable $e) {
    $fehler = $e->getMessage();
}
$anzahlNeu = count(array_filter($endpunkte, fn($e) => $e['neu']));
$weg = array_values(array_diff(array_keys($bekannt), array_column($endpunkte, 'pfad')));
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

include 'includes/header.php';
?>
<style>
    .at-tabelle td { vertical-align: middle; }
    .at-tabelle code { font-size: 12.5px; }
    .at-status { white-space: nowrap; font-size: 12.5px; }
    .at-param input { width: 92px; display: inline-block; }
    .at-zuletzt { color: var(--bs-secondary-color); font-size: 11.5px; }
    #atViewer .modal-body { padding: 0; display: flex; flex-direction: column; min-height: 0; }
    #atViewer .werkzeug { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px 14px; border-bottom: 1px solid var(--bs-border-color); }
    #atViewer .huelle { overflow: auto; flex: 1; min-height: 0; }
    #atViewer table { font-size: 12.5px; margin: 0; }
    #atViewer th, #atViewer td { white-space: nowrap; max-width: 320px; overflow: hidden; text-overflow: ellipsis; }
    #atViewer thead th { position: sticky; top: 0; background: var(--bs-tertiary-bg); z-index: 1; cursor: pointer; user-select: none; }
    #atViewer thead tr.filter th { top: 31px; cursor: default; padding: 3px 4px; }
    #atViewer thead tr.filter input { min-width: 70px; font-size: 12px; padding: 2px 5px; }
    #atViewer tbody tr { cursor: pointer; }
    #atViewer td.leer { color: var(--bs-secondary-color); }
    #atViewer td.zahl { text-align: right; }
    #atViewer pre.roh { margin: 0; padding: 12px 14px; font-size: 12px; }
    #atSpalten { display: none; flex-wrap: wrap; gap: 4px 14px; padding: 8px 14px; border-bottom: 1px solid var(--bs-border-color); font-size: 12.5px; }
    #atSpalten.offen { display: flex; }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-plug"></i> BMV-API testen</h1>
    <?php if (!$fehler): ?>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" id="atAllePruefen"><i class="bi bi-play-circle"></i> Alle prüfen</button>
    </div>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-body small">
        <div class="row g-2">
            <div class="col-md-4"><strong>Adresse:</strong> <?php echo $h($api->getBaseUrl() ?: '– nicht eingetragen –'); ?></div>
            <div class="col-md-4"><strong>Vereinskennung:</strong> <?php echo $h($api->getVereinskennung()); ?>
                <?php echo $api->istKonfiguriert() ? '<span class="badge bg-success ms-1">Zugang eingerichtet</span>' : '<span class="badge bg-warning text-dark ms-1">Zugang unvollständig oder ausgeschaltet</span>'; ?></div>
            <div class="col-md-4"><strong>API:</strong> <?php echo $swagger ? $h(($swagger['info']['title'] ?? 'BMV-Datenservice') . ' ' . ($swagger['info']['version'] ?? '')) : '–'; ?></div>
            <div class="col-12 text-muted">
                <?php echo count($endpunkte); ?> Zugriffspunkte zum Lesen (GET), davon <?php echo count(array_filter($endpunkte, fn($e) => !$e['pfadParameter'])); ?> ohne Pfadangabe.
                Daneben gibt es <?php echo array_sum($andere); ?> schreibende (<?php echo $h(implode(', ', array_map(fn($m, $n) => "$n× $m", array_keys(array_filter($andere)), array_filter($andere)))); ?>) – diese ruft Syncopa nie auf.
                <?php if ($stand): ?>Letzte Prüfung: <?php echo $h(date('d.m.Y H:i', strtotime($stand['zeit']))); ?> – „neu“ heißt: seitdem dazugekommen.<?php else: ?>„Neu“ heißt: nicht in der früheren Testseite (Oktober 2026). Nach „Alle prüfen“ wird ab dann mit dieser Prüfung verglichen.<?php endif; ?>
            </div>
            <?php if ($anzahlNeu || $weg): ?>
            <div class="col-12">
                <?php if ($anzahlNeu): ?><span class="badge bg-info text-dark"><i class="bi bi-stars"></i> <?php echo $anzahlNeu; ?> neu<?php echo $stand ? ' seit der letzten Prüfung' : ''; ?></span><?php endif; ?>
                <?php if ($weg): ?><span class="badge bg-secondary" title="<?php echo $h(implode(', ', $weg)); ?>"><?php echo count($weg); ?> nicht mehr vorhanden: <?php echo $h(implode(', ', array_slice($weg, 0, 5))); ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($fehler): ?>
<div class="alert alert-danger alert-permanent"><i class="bi bi-exclamation-triangle"></i> <?php echo $h($fehler); ?></div>
<?php else: ?>
<div class="card">
    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
        <input type="search" class="form-control form-control-sm" id="atSuche" placeholder="Zugriffspunkt suchen …" style="max-width:260px">
        <select class="form-select form-select-sm" id="atFilter" style="max-width:230px">
            <option value="">Alle Zugriffspunkte</option>
            <option value="neu">Nur neue</option>
            <option value="genutzt">Nur von Syncopa genutzte</option>
            <option value="ok">Nur funktionierende</option>
            <option value="fehler">Nur fehlerhafte</option>
        </select>
        <span class="text-muted small ms-auto" id="atFortschritt"></span>
    </div>
    <div class="table-responsive">
    <table class="table table-sm table-hover mb-0 at-tabelle">
        <thead><tr><th>Bereich</th><th>Zugriffspunkt</th><th>Parameter</th><th>Ergebnis</th><th class="text-end">Datensätze</th><th class="text-end">Zeit</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($endpunkte as $i => $e):
            $pfadTeile = preg_split('#(\{[^}]+\})#', $e['pfad'], -1, PREG_SPLIT_DELIM_CAPTURE); ?>
            <tr data-i="<?php echo $i; ?>" data-pfad="<?php echo $h($e['pfad']); ?>" data-neu="<?php echo $e['neu'] ? 1 : 0; ?>" data-genutzt="<?php echo $e['genutzt'] ? 1 : 0; ?>"
                data-auto="<?php echo !$e['pfadParameter'] && !array_filter($e['queryParameter'], fn($q) => $q['pflicht']) ? 1 : 0; ?>">
                <td class="small text-muted"><?php echo $h($e['bereich']); ?></td>
                <td>
                    <code><?php foreach ($pfadTeile as $t) echo preg_match('#^\{(.+)\}$#', $t, $m) ? '<span class="at-param"><input class="form-control form-control-sm py-0" data-param="' . $h($m[1]) . '" placeholder="' . $h($m[1]) . '"></span>' : $h($t); ?></code>
                    <?php if ($e['neu']): ?><span class="badge bg-info text-dark ms-1">neu</span><?php endif; ?>
                    <?php if ($e['genutzt']): ?><span class="badge bg-primary-subtle text-primary-emphasis ms-1" title="Wird von Syncopa abgefragt">in Syncopa</span><?php endif; ?>
                    <?php if ($e['info']): ?><div class="small text-muted"><?php echo $h($e['info']); ?></div><?php endif; ?>
                </td>
                <td class="small"><?php if ($e['queryParameter']): ?>
                    <input class="form-control form-control-sm" data-query style="min-width:160px" placeholder="<?php echo $h(implode('&', array_map(fn($q) => $q['name'] . '=' . ($q['pflicht'] ? '…' : ''), $e['queryParameter']))); ?>"
                           title="<?php echo $h(implode(', ', array_map(fn($q) => $q['name'] . ($q['pflicht'] ? ' (Pflicht)' : ' (optional)'), $e['queryParameter']))); ?>">
                <?php else: ?><span class="text-muted">–</span><?php endif; ?></td>
                <td class="at-status" data-status><?php if ($e['zuletzt']): ?><span class="at-zuletzt" title="Ergebnis der letzten Prüfung">zuletzt HTTP <?php echo (int)$e['zuletzt']['code']; ?></span><?php endif; ?></td>
                <td class="text-end small" data-anzahl><?php echo $e['zuletzt'] && $e['zuletzt']['anzahl'] !== null ? '<span class="at-zuletzt">' . (int)$e['zuletzt']['anzahl'] . '</span>' : ''; ?></td>
                <td class="text-end small text-muted" data-zeit></td>
                <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-outline-secondary" data-pruefen title="Nur prüfen"><i class="bi bi-arrow-repeat"></i></button>
                    <button class="btn btn-sm btn-outline-primary" data-anzeigen title="Antwort als Tabelle anzeigen"><i class="bi bi-table"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="modal fade" id="atViewer" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title"><code id="atTitel"></code> <small class="text-muted ms-2" id="atMeta"></small></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="werkzeug">
                    <input type="search" class="form-control form-control-sm" id="atVSuche" placeholder="In allen Spalten suchen …" style="max-width:280px">
                    <span class="text-muted small" id="atVAnzahl"></span>
                    <span class="ms-auto"></span>
                    <button class="btn btn-sm btn-outline-secondary" id="atVSpalten">Spalten</button>
                    <button class="btn btn-sm btn-outline-secondary" id="atVReset">Filter zurücksetzen</button>
                    <button class="btn btn-sm btn-outline-secondary" id="atVCsv"><i class="bi bi-filetype-csv"></i> CSV</button>
                    <button class="btn btn-sm btn-outline-secondary" id="atVJson">JSON</button>
                </div>
                <div id="atSpalten"></div>
                <div class="huelle" id="atHuelle"><table class="table table-sm table-hover"><thead></thead><tbody></tbody></table></div>
                <div class="huelle" id="atRohHuelle" hidden><pre class="roh" id="atRoh"></pre></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="atDetail" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2"><h5 class="modal-title">Datensatz</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-0"><table class="table table-sm mb-0"><tbody></tbody></table></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
<?php if (!$fehler): ?>
<script>
(function () {
    const zeilen = [...document.querySelectorAll('.at-tabelle tbody tr')];
    const ergebnisse = {};
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const meldung = (t, typ = 'danger') => window.showToast ? showToast(t, typ) : alert(t);

    const BEDEUTUNG = { 0: 'keine Verbindung', 200: 'funktioniert', 204: 'funktioniert (leer)', 400: 'Anfrage abgelehnt', 401: 'Zugang abgelehnt',
        403: 'keine Berechtigung', 404: 'nicht gefunden', 405: 'nicht erlaubt', 500: 'Fehler beim BMV', 502: 'BMV nicht erreichbar', 503: 'BMV nicht erreichbar', 504: 'Zeitüberschreitung' };
    function statusHtml(d) {
        if (!d.success) return `<span class="badge bg-danger">Fehler</span> <span class="small">${esc(d.error)}</span>`;
        const ok = d.code >= 200 && d.code < 300;
        const farbe = ok ? 'success' : (d.code === 401 || d.code === 403) ? 'warning text-dark' : 'danger';
        const text = BEDEUTUNG[d.code] || (ok ? 'funktioniert' : 'Fehler');
        return `<span class="badge bg-${farbe}" title="${esc(d.fehler || d.roh || '')}">${d.code || '–'}</span> ${esc(text)}`;
    }

    /** Pfad mit eingesetzten Pfadparametern; null, wenn einer fehlt */
    function pfadVon(tr) {
        let pfad = tr.dataset.pfad, fehlt = null;
        tr.querySelectorAll('[data-param]').forEach(inp => {
            const v = inp.value.trim();
            if (!v) fehlt = fehlt || inp; else pfad = pfad.replace('{' + inp.dataset.param + '}', v);
        });
        if (fehlt) { fehlt.focus(); fehlt.classList.add('is-invalid'); setTimeout(() => fehlt.classList.remove('is-invalid'), 1500); return null; }
        return pfad;
    }
    const queryVon = tr => tr.querySelector('[data-query]')?.value.trim() || '';

    function abrufen(pfad, query, kurz) {
        return fetch('bmv_apitest.php?' + new URLSearchParams({ aktion: 'abruf', pfad, query, kurz: kurz ? 1 : '' }))
            .then(r => r.json()).catch(() => ({ success: false, error: 'Keine Antwort von Syncopa.' }));
    }
    function eintragen(tr, d) {
        tr.querySelector('[data-status]').innerHTML = statusHtml(d);
        tr.querySelector('[data-anzahl]').textContent = d.success && d.anzahl !== null && d.anzahl !== undefined ? d.anzahl.toLocaleString('de-AT') : '';
        tr.querySelector('[data-zeit]').textContent = d.success ? (d.ms >= 1000 ? (d.ms / 1000).toFixed(1).replace('.', ',') + ' s' : d.ms + ' ms') : '';
        tr.dataset.ergebnis = d.success && d.code >= 200 && d.code < 300 ? 'ok' : 'fehler';
        filtern();
    }
    function pruefen(tr) {
        const pfad = pfadVon(tr); if (!pfad) return Promise.resolve();
        tr.querySelector('[data-status]').innerHTML = '<span class="spinner-border spinner-border-sm text-secondary"></span>';
        return abrufen(pfad, queryVon(tr), true).then(d => { eintragen(tr, d); if (d.success && pfad === tr.dataset.pfad) ergebnisse[pfad] = { code: d.code, anzahl: d.anzahl, ms: d.ms }; return d; });
    }

    zeilen.forEach(tr => {
        tr.querySelector('[data-pruefen]').addEventListener('click', () => pruefen(tr));
        tr.querySelector('[data-anzeigen]').addEventListener('click', () => anzeigen(tr));
        tr.querySelectorAll('input').forEach(i => i.addEventListener('keydown', e => { if (e.key === 'Enter') anzeigen(tr); }));
    });

    // Alle ohne Pflichtangaben nacheinander prüfen, danach den Stand merken (für „neu“-Markierung)
    const knopf = document.getElementById('atAllePruefen'), fortschritt = document.getElementById('atFortschritt');
    knopf.addEventListener('click', async () => {
        knopf.disabled = true;
        const auto = zeilen.filter(tr => tr.dataset.auto === '1');
        let ok = 0, n = 0;
        for (const tr of auto) {
            fortschritt.textContent = `Prüfe ${++n} von ${auto.length} …`;
            const d = await pruefen(tr);
            if (d && d.success && d.code >= 200 && d.code < 300) ok++;
        }
        const r = await fetch('bmv_apitest.php?aktion=merken', { method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pfade: zeilen.map(tr => tr.dataset.pfad), ergebnisse }) }).then(r => r.json()).catch(() => ({}));
        fortschritt.textContent = `${ok} von ${auto.length} funktionieren` + (r.success ? ' · Stand gespeichert' : '') + '. Zugriffspunkte mit {Angaben} einzeln prüfen.';
        knopf.disabled = false;
    });

    // Suche und Filter in der Liste
    const suche = document.getElementById('atSuche'), filter = document.getElementById('atFilter');
    function filtern() {
        const q = suche.value.trim().toLowerCase(), f = filter.value;
        zeilen.forEach(tr => {
            const passt = (!q || tr.textContent.toLowerCase().includes(q) || tr.dataset.pfad.toLowerCase().includes(q))
                && (!f || (f === 'neu' && tr.dataset.neu === '1') || (f === 'genutzt' && tr.dataset.genutzt === '1') || tr.dataset.ergebnis === f);
            tr.hidden = !passt;
        });
    }
    suche.addEventListener('input', filtern); filter.addEventListener('change', filtern);

    // ---------------- Tabellen-Ansicht einer Antwort ----------------
    const viewer = document.getElementById('atViewer'), viewerModal = new bootstrap.Modal(viewer), detailModal = new bootstrap.Modal(document.getElementById('atDetail'));
    const thead = viewer.querySelector('#atHuelle thead'), tbody = viewer.querySelector('#atHuelle tbody'), vSuche = document.getElementById('atVSuche');
    const spaltenBox = document.getElementById('atSpalten');
    let nutzdaten = null, reihen = [], spalten = [], versteckt = new Set(), spaltenFilter = {}, sortSpalte = null, sortRichtung = 1, sichtbar = [], dateiname = 'bmv';
    const MAX = 2000;
    const leer = v => v === null || v === undefined || v === '';
    const isoDatum = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/;
    function text(v) {
        if (leer(v)) return '';
        if (typeof v === 'object') return JSON.stringify(v);
        const m = typeof v === 'string' && v.match(isoDatum);
        if (m) return m[3] + '.' + m[2] + '.' + m[1] + (m[4] + m[5] + m[6] === '000000' ? '' : ' ' + m[4] + ':' + m[5]);
        return String(v);
    }
    const el = (tag, t) => { const e = document.createElement(tag); if (t !== undefined) e.textContent = t; return e; };
    function vergleich(a, b) {
        if (leer(a)) return leer(b) ? 0 : 1;
        if (leer(b)) return -1;
        if (typeof a === 'number' && typeof b === 'number') return (a - b) * sortRichtung;
        return String(a).localeCompare(String(b), 'de', { numeric: true, sensitivity: 'base' }) * sortRichtung;
    }
    function kopf() {
        thead.innerHTML = '';
        const tr = el('tr'), fr = el('tr'); fr.className = 'filter';
        spalten.filter(c => !versteckt.has(c)).forEach(c => {
            const th = el('th', c + (sortSpalte === c ? (sortRichtung === 1 ? ' ▲' : ' ▼') : ''));
            th.title = 'Klicken zum Sortieren';
            th.onclick = () => { sortRichtung = sortSpalte === c ? -sortRichtung : 1; sortSpalte = c; kopf(); koerper(); };
            tr.appendChild(th);
            const fth = el('th'), inp = el('input'); inp.className = 'form-control form-control-sm'; inp.placeholder = 'Filter'; inp.value = spaltenFilter[c] || '';
            inp.oninput = () => { spaltenFilter[c] = inp.value; koerper(); };
            fth.appendChild(inp); fr.appendChild(fth);
        });
        thead.append(tr, fr);
    }
    function koerper() {
        const q = vSuche.value.trim().toLowerCase(), gezeigt = spalten.filter(c => !versteckt.has(c));
        const aktiv = Object.entries(spaltenFilter).filter(([c, v]) => v.trim() && !versteckt.has(c)).map(([c, v]) => [c, v.trim().toLowerCase()]);
        sichtbar = reihen.filter(r => aktiv.every(([c, v]) => text(r[c]).toLowerCase().includes(v)) && (!q || gezeigt.some(c => text(r[c]).toLowerCase().includes(q))));
        if (sortSpalte !== null) sichtbar = [...sichtbar].sort((a, b) => vergleich(a[sortSpalte], b[sortSpalte]));
        const frag = document.createDocumentFragment();
        sichtbar.slice(0, MAX).forEach(r => {
            const tr = el('tr');
            gezeigt.forEach(c => { const v = r[c], td = el('td', leer(v) ? '–' : text(v)); if (leer(v)) td.className = 'leer'; else if (typeof v === 'number') td.className = 'zahl'; td.title = text(v); tr.appendChild(td); });
            tr.onclick = () => detail(r); frag.appendChild(tr);
        });
        tbody.innerHTML = ''; tbody.appendChild(frag);
        let info = sichtbar.length + ' von ' + reihen.length + ' Datensätzen · ' + gezeigt.length + ' von ' + spalten.length + ' Spalten';
        if (sichtbar.length > MAX) info += ' · angezeigt werden die ersten ' + MAX;
        document.getElementById('atVAnzahl').textContent = info;
    }
    function detail(r) {
        const body = document.querySelector('#atDetail tbody'); body.innerHTML = '';
        Object.entries(r).forEach(([k, v]) => { const tr = el('tr'), a = el('td', k), b = el('td', v !== null && typeof v === 'object' ? JSON.stringify(v, null, 2) : text(v));
            a.className = 'text-muted text-nowrap'; b.style.whiteSpace = 'pre-wrap'; b.style.wordBreak = 'break-word'; tr.append(a, b); body.appendChild(tr); });
        detailModal.show();
    }
    function aufbauen(daten) {
        nutzdaten = daten;
        reihen = (Array.isArray(daten) ? daten : [daten]).map(r => (r !== null && typeof r === 'object' && !Array.isArray(r)) ? r : { wert: r });
        spalten = []; reihen.forEach(r => Object.keys(r).forEach(k => { if (!spalten.includes(k)) spalten.push(k); }));
        versteckt = new Set(spalten.filter(c => reihen.every(r => leer(r[c])))); // ganz leere Spalten anfangs ausblenden
        spaltenFilter = {}; sortSpalte = null; vSuche.value = '';
        spaltenBox.innerHTML = '';
        spalten.forEach(c => { const l = el('label'), cb = el('input'); cb.type = 'checkbox'; cb.checked = !versteckt.has(c);
            cb.onchange = () => { cb.checked ? versteckt.delete(c) : versteckt.add(c); kopf(); koerper(); }; l.append(cb, ' ' + c); spaltenBox.appendChild(l); });
        document.getElementById('atRoh').textContent = '';
        document.getElementById('atRohHuelle').hidden = true; document.getElementById('atHuelle').hidden = false;
        kopf(); koerper();
    }
    function anzeigen(tr) {
        const pfad = pfadVon(tr); if (!pfad) return;
        const query = queryVon(tr);
        document.getElementById('atTitel').textContent = pfad + (query ? '?' + query : '');
        document.getElementById('atMeta').innerHTML = '<span class="spinner-border spinner-border-sm"></span> wird geladen …';
        aufbauen([]); viewerModal.show();
        dateiname = pfad.replace(/^\/api\//, '').replace(/[^\p{L}\p{N}]+/gu, '_');
        abrufen(pfad, query, false).then(d => {
            eintragen(tr, d);
            if (!d.success) { document.getElementById('atMeta').textContent = d.error; return; }
            document.getElementById('atMeta').innerHTML = statusHtml(d) + ` · ${d.ms} ms · ${d.bytes.toLocaleString('de-AT')} Bytes`;
            if (d.daten !== null && d.daten !== undefined) aufbauen(d.daten);
            else { document.getElementById('atRoh').textContent = d.roh || d.fehler || '(leere Antwort)'; document.getElementById('atRohHuelle').hidden = false; document.getElementById('atHuelle').hidden = true; }
        });
    }
    vSuche.addEventListener('input', koerper);
    document.getElementById('atVSpalten').onclick = e => { spaltenBox.classList.toggle('offen'); e.currentTarget.classList.toggle('active'); };
    document.getElementById('atVReset').onclick = () => { vSuche.value = ''; spaltenFilter = {}; sortSpalte = null; kopf(); koerper(); };
    document.getElementById('atVJson').onclick = e => {
        const roh = document.getElementById('atRoh'), rh = document.getElementById('atRohHuelle'), th = document.getElementById('atHuelle');
        if (!roh.textContent && nutzdaten !== null) roh.textContent = JSON.stringify(nutzdaten, null, 2);
        rh.hidden = !rh.hidden; th.hidden = !rh.hidden; e.currentTarget.classList.toggle('active');
    };
    document.getElementById('atVCsv').onclick = () => {
        // gefilterte Ansicht; Semikolon + BOM, damit Excel die Datei direkt öffnet
        const gezeigt = spalten.filter(c => !versteckt.has(c)), zelle = v => '"' + text(v).replace(/"/g, '""') + '"';
        const z = [gezeigt.map(zelle).join(';')].concat(sichtbar.map(r => gezeigt.map(c => zelle(r[c])).join(';')));
        const a = el('a'); a.href = URL.createObjectURL(new Blob(['﻿' + z.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
        a.download = dateiname + '.csv'; a.click(); URL.revokeObjectURL(a.href);
    };
})();
</script>
<?php endif; ?>
