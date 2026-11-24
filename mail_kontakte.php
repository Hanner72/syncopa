<?php
// mail_kontakte.php – Kontakte von SyncoMail (wie „Personen“ in Outlook): Filter | Kontaktliste | Visitenkarte.
// Kontakte sind privat (nur ich) oder gemeinsam (alle mit E-Mail-Zugang).
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && MailKontakt::darfNutzen()) {
    $anzeigen = 0;   // nach dem Speichern diesen Kontakt wieder anzeigen
    try {
        if (($_POST['aktion'] ?? '') === 'loeschen') {
            MailKontakt::loeschen((int)$_POST['id']);
            Session::setFlashMessage('success', 'Kontakt gelöscht.');
        } else {
            $anzeigen = MailKontakt::speichern($_POST, (int)($_POST['id'] ?? 0) ?: null);
            Session::setFlashMessage('success', 'Kontakt gespeichert.');
        }
    } catch (RuntimeException $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: mail_kontakte.php' . (!empty($_POST['filter']) ? '?filter=' . urlencode($_POST['filter']) : '') . ($anzeigen ? '#k' . $anzeigen : ''));
    exit;
}

$filter = in_array($_GET['filter'] ?? '', ['gemeinsam', 'privat'], true) ? $_GET['filter'] : '';
$kontakte = MailKontakt::darfNutzen() ? MailKontakt::sichtbare($filter) : [];
$anzahl = MailKontakt::darfNutzen() ? ['' => count(MailKontakt::sichtbare()), 'gemeinsam' => count(MailKontakt::sichtbare('gemeinsam')), 'privat' => count(MailKontakt::sichtbare('privat'))] : [];
$filterNamen = ['' => 'Alle Kontakte', 'gemeinsam' => 'Gemeinsame', 'privat' => 'Meine privaten'];
$filterSymbole = ['' => 'bi-person-lines-fill', 'gemeinsam' => 'bi-people', 'privat' => 'bi-lock'];

// Vorbelegung aus dem Lesebereich („+ Kontakt“)
$vorgabe = !empty($_GET['neu']) ? ['email' => (string)($_GET['email'] ?? ''), 'name' => (string)($_GET['name'] ?? '')] : null;

// Daten für Liste und Visitenkarte aufbereiten
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
$daten = [];
foreach ($kontakte as $k) {
    $name = MailKontakt::name($k);
    $sortiert = (string)($k['nachname'] ?: ($k['organisation'] ?: $k['email']));
    $buchstabe = mb_strtoupper(mb_substr($sortiert, 0, 1));
    $buchstabe = strtr($buchstabe, ['Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U']);
    $daten[] = $k + [
        'name'        => $name,
        'buchstabe'   => preg_match('/^\p{Lu}$/u', $buchstabe) ? $buchstabe : '#',
        'initialen'   => mb_strtoupper(mb_substr($k['vorname'] ?: $name, 0, 1) . mb_substr($k['nachname'] ?? '', 0, 1)),
        'farbe'       => 'hsl(' . (crc32($k['email']) % 360) . ',45%,45%)',
        'bearbeitbar' => MailKontakt::darfBearbeiten($k),
        'suche'       => mb_strtolower(implode(' ', [$name, $k['organisation'], $k['funktion'], $k['email'], $k['telefon']])),
        'schreiben'   => 'mail.php?an=' . urlencode($k['email']) . '&name=' . urlencode($name),
    ];
}

include 'includes/header.php';
?>
<link rel="stylesheet" href="assets/css/syncomail.css?v=<?php echo APP_VERSION; ?>">
<style>
    .k-haupt { display: grid; grid-template-columns: 210px 340px 1fr; min-height: 0; }
    .k-nav, .k-liste { border-right: 1px solid var(--border); overflow-y: auto; min-height: 0; }
    /* Filter (links) */
    .k-nav { padding: 8px 0; font-size: 13.5px; display: flex; flex-direction: column; }
    .k-nav-kopf { padding: 8px 14px 6px; font-weight: 600; font-size: 13px; }
    .k-nav a { display: flex; align-items: center; gap: 9px; padding: 6px 14px 6px 22px; color: var(--text-primary); text-decoration: none; border-left: 3px solid transparent; }
    .k-nav a:hover { background: var(--m-akzent-hell); }
    .k-nav a.aktiv { background: var(--m-auswahl); border-left-color: var(--m-akzent); font-weight: 600; }
    .k-nav a i { color: var(--m-akzent); }
    .k-nav .zahl { margin-left: auto; color: var(--text-secondary); font-size: 12px; font-weight: 400; }
    .k-nav-info { margin-top: auto; padding: 12px 14px; font-size: 11.5px; color: var(--text-muted); line-height: 1.45; }
    /* Liste (Mitte) */
    .k-liste-kopf { position: sticky; top: 0; z-index: 2; background: var(--bg-card); display: flex; align-items: center; gap: 8px;
        padding: 10px 14px 8px; border-bottom: 1px solid var(--border-light); }
    .k-liste-kopf h6 { margin: 0; font-weight: 600; flex: 1; }
    .k-liste-kopf .anzahl { font-size: 12px; color: var(--text-secondary); }
    .k-buchstabe { padding: 10px 14px 3px; font-size: 12px; font-weight: 700; color: var(--m-akzent); }
    .k-eintrag { display: flex; align-items: center; gap: 10px; padding: 7px 14px; cursor: pointer; border-left: 3px solid transparent; outline: none; }
    .k-eintrag:hover, .k-eintrag:focus-visible { background: var(--m-akzent-hell); }
    .k-eintrag.aktiv { background: var(--m-auswahl); border-left-color: var(--m-akzent); }
    .k-eintrag .txt { min-width: 0; flex: 1; }
    .k-eintrag .nm { font-size: 13.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .k-eintrag .unter { font-size: 12px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .k-eintrag .sicht { color: var(--text-muted); font-size: 12px; }
    .k-avatar { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 600; font-size: 13px; flex-shrink: 0; }
    .k-avatar.gross { width: 72px; height: 72px; font-size: 26px; }
    .k-keine { padding: 30px 16px; text-align: center; color: var(--text-muted); font-size: 13px; }
    /* Visitenkarte (rechts) */
    .k-karte { overflow-y: auto; min-height: 0; }
    .k-leer { height: 100%; min-height: 240px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted); }
    .k-leer i { font-size: 46px; color: var(--m-akzent); opacity: .45; }
    .k-karte-kopf { display: flex; align-items: center; gap: 18px; padding: 24px 28px 18px; border-bottom: 1px solid var(--border-light); }
    .k-karte-kopf h4 { margin: 0 0 2px; font-size: 22px; font-weight: 600; word-break: break-word; }
    .k-karte-kopf .rolle { color: var(--text-secondary); font-size: 13.5px; }
    .k-aktionen { display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap; }
    .k-aktionen button, .k-aktionen a { border: 1px solid var(--border); background: var(--bg-card); color: var(--text-primary); border-radius: 4px;
        padding: 4px 11px; font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
    .k-aktionen button:hover, .k-aktionen a:hover { background: var(--m-akzent-hell); }
    .k-aktionen .primaer { background: var(--m-akzent); border-color: var(--m-akzent); color: #fff; }
    .k-aktionen .primaer:hover { background: var(--m-akzent); filter: brightness(1.1); }
    .k-aktionen i { color: var(--m-akzent); } .k-aktionen .primaer i { color: #fff; }
    .k-details { padding: 18px 28px 28px; display: grid; grid-template-columns: 130px 1fr; gap: 12px 16px; font-size: 13.5px; max-width: 760px; margin: 0; }
    .k-details dt { color: var(--text-secondary); font-weight: 500; display: flex; gap: 7px; align-items: baseline; }
    .k-details dt i { color: var(--m-akzent); }
    .k-details dd { margin: 0; word-break: break-word; white-space: pre-line; }
    /* Suche in der Befehlsleiste */
    .k-suche { position: relative; margin-left: auto; }
    .k-suche i { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 12px; }
    .k-suche input { border: 1px solid var(--border); background: var(--bg-input, #fff); color: var(--text-primary); border-radius: 4px;
        padding: 3px 10px 3px 28px; font-size: 12.5px; width: 240px; max-width: 46vw; }
    .k-suche input:focus { outline: none; border-color: var(--m-akzent); box-shadow: 0 0 0 2px var(--m-akzent-hell); }
    .k-zurueck, .k-filterknopf { display: none; }
    @media (max-width: 991px) {
        .mail-app { height: calc(100vh - var(--topbar-h, 56px) - 24px); }
        .k-haupt { grid-template-columns: 1fr; }
        .k-nav, .k-liste, .k-karte { display: none; border-right: 0; }
        .kontakte-app[data-ansicht="nav"] .k-nav { display: flex; }
        .kontakte-app[data-ansicht="liste"] .k-liste, .kontakte-app[data-ansicht="karte"] .k-karte { display: block; }
        .k-zurueck, .k-filterknopf { display: inline-flex; }
        .mail-befehle .btn-cmd:not(.btn-neu) span { display: none; }
    }
</style>

<?php if (!MailKontakt::darfNutzen()): ?>
<div class="page-header"><h1 class="page-title"><i class="bi bi-people"></i> <?php echo $h(MailKonto::APP_NAME); ?> · Kontakte</h1></div>
<div class="alert alert-info alert-permanent">Kontakte gibt es für alle, die ein E-Mail-Postfach in Syncopa nutzen. Unter <a href="konto.php#postfaecher">Mein Konto</a> kannst du dein Postfach einrichten.</div>
<?php else: ?>

<div class="mail-app kontakte-app" id="kontakteApp" data-ansicht="liste">
    <?php $syncoAktiv = 'kontakte'; include 'includes/syncomail_titel.php'; ?>
    <div class="mail-befehle">
        <button class="btn-cmd btn-neu" data-kontakt='{}' title="Neuer Kontakt"><i class="bi bi-person-plus"></i><span>Neuer Kontakt</span></button>
        <div class="trenner"></div>
        <button class="btn-cmd k-zurueck" id="kZurueck" title="Zurück"><i class="bi bi-arrow-left"></i></button>
        <button class="btn-cmd" id="kSchreiben" data-wahl disabled title="E-Mail schreiben"><i class="bi bi-envelope-plus"></i><span>E-Mail schreiben</span></button>
        <button class="btn-cmd" id="kBearbeiten" data-wahl disabled title="Bearbeiten"><i class="bi bi-pencil"></i><span>Bearbeiten</span></button>
        <button class="btn-cmd" id="kLoeschen" data-wahl disabled title="Löschen"><i class="bi bi-trash"></i><span>Löschen</span></button>
        <div class="trenner"></div>
        <a class="btn-cmd nebensache text-decoration-none" href="mail.php" title="Zur E-Mail"><i class="bi bi-envelope"></i><span>Zur E-Mail</span></a>
        <div class="k-suche"><i class="bi bi-search"></i><input type="search" id="kontaktSuche" placeholder="Kontakte durchsuchen" aria-label="Kontakte durchsuchen" autocomplete="off"></div>
    </div>
    <div class="k-haupt">
        <nav class="k-nav" aria-label="Kontakte filtern">
            <div class="k-nav-kopf">Kontakte</div>
            <?php foreach ($filterNamen as $fv => $fl): ?>
            <a href="mail_kontakte.php<?php echo $fv ? '?filter=' . $fv : ''; ?>" class="<?php echo $filter === $fv ? 'aktiv' : ''; ?>"><i class="bi <?php echo $filterSymbole[$fv]; ?>"></i> <?php echo $fl; ?> <span class="zahl"><?php echo (int)$anzahl[$fv]; ?></span></a>
            <?php endforeach; ?>
            <div class="k-nav-info"><i class="bi bi-info-circle"></i> Kontakte erscheinen beim Schreiben einer E-Mail als Vorschlag. Gemeinsame sehen alle mit Postfach in Syncopa; ändern kann sie, wer sie angelegt hat, oder ein Administrator. Mails von Kontakten kommen nie in den Junk-Ordner.</div>
        </nav>
        <section class="k-liste" id="kListe">
            <div class="k-liste-kopf">
                <button class="btn btn-sm btn-link p-0 k-filterknopf" id="kFilterKnopf" title="Filter"><i class="bi bi-funnel"></i></button>
                <h6><?php echo $filterNamen[$filter]; ?></h6><span class="anzahl" id="kAnzahl"><?php echo count($daten); ?></span>
            </div>
            <?php $letzter = null; foreach ($daten as $i => $k): if ($k['buchstabe'] !== $letzter): $letzter = $k['buchstabe']; ?>
            <div class="k-buchstabe" data-buchstabe="<?php echo $h($letzter); ?>"><?php echo $h($letzter); ?></div>
            <?php endif; ?>
            <div class="k-eintrag" tabindex="0" data-index="<?php echo $i; ?>" data-buchstabe="<?php echo $h($k['buchstabe']); ?>" data-suche="<?php echo $h($k['suche']); ?>">
                <span class="k-avatar" style="background:<?php echo $k['farbe']; ?>"><?php echo $h($k['initialen']); ?></span>
                <div class="txt">
                    <div class="nm"><?php echo $h($k['name']); ?></div>
                    <div class="unter"><?php echo $h(implode(' · ', array_filter([$k['funktion'], ($k['vorname'] || $k['nachname']) ? $k['organisation'] : '', $k['email']]))); ?></div>
                </div>
                <i class="bi <?php echo $k['oeffentlich'] ? 'bi-people' : 'bi-lock'; ?> sicht" title="<?php echo $k['oeffentlich'] ? 'Gemeinsam (alle mit Postfach)' : 'Privat (nur du)'; ?>"></i>
            </div>
            <?php endforeach; ?>
            <div class="k-keine" id="kKeine" <?php echo $daten ? 'hidden' : ''; ?>><?php echo $daten ? 'Keine Treffer.' : 'Noch keine Kontakte. Lege z. B. Gemeinde, Pfarre, Musikhaus oder Presse als Kontakt an.'; ?></div>
        </section>
        <section class="k-karte" id="kKarte">
            <div class="k-leer"><i class="bi bi-person-vcard"></i><p class="mt-2 mb-0">Wählen Sie einen Kontakt aus, um ihn anzuzeigen.</p></div>
        </section>
    </div>
</div>
<?php foreach ($daten as $k): if (!$k['bearbeitbar']) continue; ?>
<form method="POST" id="k-del-<?php echo (int)$k['id']; ?>" class="d-none"><input type="hidden" name="aktion" value="loeschen"><input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>"><input type="hidden" name="filter" value="<?php echo $h($filter); ?>"></form>
<?php endforeach; ?>

<!-- Dialog Neu/Bearbeiten -->
<div class="modal fade" id="kontaktModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" class="modal-content">
        <input type="hidden" name="id"><input type="hidden" name="filter" value="<?php echo $h($filter); ?>">
        <div class="modal-header"><h5 class="modal-title">Kontakt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <fieldset class="modal-body row g-3 m-0">
            <div class="col-md-6"><label class="form-label" for="k-vorname">Vorname</label><input class="form-control" id="k-vorname" name="vorname" maxlength="100"></div>
            <div class="col-md-6"><label class="form-label" for="k-nachname">Nachname</label><input class="form-control" id="k-nachname" name="nachname" maxlength="100"></div>
            <div class="col-md-6"><label class="form-label" for="k-organisation">Organisation</label><input class="form-control" id="k-organisation" name="organisation" maxlength="150" placeholder="z. B. Gemeinde Musterdorf"></div>
            <div class="col-md-6"><label class="form-label" for="k-funktion">Funktion</label><input class="form-control" id="k-funktion" name="funktion" maxlength="150" placeholder="z. B. Bürgermeister"></div>
            <div class="col-md-7"><label class="form-label" for="k-email">E-Mail *</label><input type="email" class="form-control" id="k-email" name="email" required maxlength="190"></div>
            <div class="col-md-5"><label class="form-label" for="k-telefon">Telefon</label><input class="form-control" id="k-telefon" name="telefon" maxlength="50"></div>
            <div class="col-12"><label class="form-label" for="k-adresse">Adresse</label><input class="form-control" id="k-adresse" name="adresse" maxlength="250"></div>
            <div class="col-12"><label class="form-label" for="k-notizen">Notizen</label><textarea class="form-control" id="k-notizen" name="notizen" rows="2"></textarea></div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="oeffentlich" value="1" id="k-oeffentlich">
                    <label class="form-check-label" for="k-oeffentlich"><strong>Für alle mit E-Mail-Zugang sichtbar</strong></label>
                </div>
                <div class="form-text" id="k-sichtbar-text">Ohne Haken bleibt der Kontakt privat: nur du siehst ihn.</div>
            </div>
        </fieldset>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button><button class="btn btn-primary" id="k-speichern">Speichern</button></div>
    </form></div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
<?php if (MailKontakt::darfNutzen()): ?>
<script>
(function () {
    document.title = <?php echo json_encode(MailKonto::APP_NAME . ' · Kontakte'); ?> + ' – ' + document.title;
    var KONTAKTE = <?php echo json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var app = document.getElementById('kontakteApp'), karte = document.getElementById('kKarte'), liste = document.getElementById('kListe');
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var gewaehlt = null;

    // ---- Dialog Neu/Bearbeiten
    var modal = document.getElementById('kontaktModal');
    var form = modal.querySelector('form'), schalter = document.getElementById('k-oeffentlich'), hinweis = document.getElementById('k-sichtbar-text');
    function hinweisSetzen() { hinweis.textContent = schalter.checked ? 'Alle, die ein Postfach in Syncopa nutzen, sehen diesen Kontakt.' : 'Ohne Haken bleibt der Kontakt privat: nur du siehst ihn.'; }
    schalter.addEventListener('change', hinweisSetzen);
    function oeffnen(d) {
        form.reset();
        form.elements.namedItem('id').value = d.id || '';
        ['vorname', 'nachname', 'organisation', 'funktion', 'email', 'telefon', 'adresse', 'notizen'].forEach(function (n) { form.elements.namedItem(n).value = d[n] || ''; });
        schalter.checked = d.oeffentlich == 1;
        var nurLesen = d.id && !d.bearbeitbar;
        form.querySelector('fieldset').disabled = !!nurLesen;
        document.getElementById('k-speichern').hidden = !!nurLesen;
        modal.querySelector('.modal-title').textContent = d.id ? (nurLesen ? 'Kontakt' : 'Kontakt bearbeiten') : 'Neuer Kontakt';
        hinweisSetzen();
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    document.querySelectorAll('[data-kontakt]').forEach(function (b) { b.addEventListener('click', function () { oeffnen(JSON.parse(b.dataset.kontakt || '{}')); }); });

    // ---- Visitenkarte
    function zeile(symbol, titel, wert) { return wert ? '<dt><i class="bi ' + symbol + '"></i>' + titel + '</dt><dd>' + wert + '</dd>' : ''; }
    function anzeigen(i) {
        var k = KONTAKTE[i]; if (!k) return;
        gewaehlt = k;
        document.querySelectorAll('.k-eintrag').forEach(function (e) { e.classList.toggle('aktiv', +e.dataset.index === i); });
        var rolle = [k.funktion, k.organisation].filter(Boolean).join(' · ');
        var sicht = k.oeffentlich == 1 ? '<span class="badge bg-success"><i class="bi bi-people"></i> Alle mit Postfach</span>' + (k.ersteller ? ' <span class="text-muted small">angelegt von ' + esc(k.ersteller) + '</span>' : '')
                                         : '<span class="badge bg-secondary"><i class="bi bi-lock"></i> Privat – nur du</span>';
        karte.innerHTML =
            '<div class="k-karte-kopf"><span class="k-avatar gross" style="background:' + esc(k.farbe) + '">' + esc(k.initialen) + '</span><div>'
            + '<h4>' + esc(k.name) + '</h4>' + (rolle ? '<div class="rolle">' + esc(rolle) + '</div>' : '')
            + '<div class="k-aktionen"><a class="primaer" href="' + esc(k.schreiben) + '"><i class="bi bi-envelope-plus"></i> E-Mail senden</a>'
            + (k.telefon ? '<a href="tel:' + esc(String(k.telefon).replace(/[^\d+]/g, '')) + '"><i class="bi bi-telephone"></i> Anrufen</a>' : '')
            + '<button type="button" data-aktion="bearbeiten"><i class="bi ' + (k.bearbeitbar ? 'bi-pencil' : 'bi-eye') + '"></i> ' + (k.bearbeitbar ? 'Bearbeiten' : 'Details') + '</button></div>'
            + '</div></div>'
            + '<dl class="k-details">'
            + zeile('bi-envelope', 'E-Mail', '<a href="' + esc(k.schreiben) + '">' + esc(k.email) + '</a>')
            + zeile('bi-telephone', 'Telefon', k.telefon ? '<a href="tel:' + esc(String(k.telefon).replace(/[^\d+]/g, '')) + '">' + esc(k.telefon) + '</a>' : '')
            + zeile('bi-building', 'Organisation', esc(k.organisation))
            + zeile('bi-briefcase', 'Funktion', esc(k.funktion))
            + zeile('bi-geo-alt', 'Adresse', esc(k.adresse))
            + zeile('bi-journal-text', 'Notizen', esc(k.notizen))
            + zeile('bi-eye', 'Sichtbar für', sicht)
            + '</dl>';
        karte.querySelector('[data-aktion="bearbeiten"]').addEventListener('click', function () { oeffnen(k); });
        document.getElementById('kSchreiben').disabled = false;
        document.getElementById('kBearbeiten').disabled = false;
        document.getElementById('kBearbeiten').querySelector('span').textContent = k.bearbeitbar ? 'Bearbeiten' : 'Details';
        document.getElementById('kLoeschen').disabled = !k.bearbeitbar;
        app.dataset.ansicht = 'karte';
        try { history.replaceState(null, '', '#k' + k.id); } catch (e) {}
    }
    liste.addEventListener('click', function (e) { var el = e.target.closest('.k-eintrag'); if (el) anzeigen(+el.dataset.index); });
    liste.addEventListener('keydown', function (e) {
        var el = e.target.closest('.k-eintrag'); if (!el) return;
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); anzeigen(+el.dataset.index); }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            var alle = Array.prototype.filter.call(liste.querySelectorAll('.k-eintrag'), function (x) { return !x.hidden; });
            var n = alle[alle.indexOf(el) + (e.key === 'ArrowDown' ? 1 : -1)];
            if (n) { n.focus(); anzeigen(+n.dataset.index); }
        }
    });

    // ---- Befehle
    document.getElementById('kSchreiben').addEventListener('click', function () { if (gewaehlt) location.href = gewaehlt.schreiben; });
    document.getElementById('kBearbeiten').addEventListener('click', function () { if (gewaehlt) oeffnen(gewaehlt); });
    document.getElementById('kLoeschen').addEventListener('click', function () {
        if (!gewaehlt || !gewaehlt.bearbeitbar) return;
        if (!confirm('Kontakt „' + gewaehlt.name + '“ löschen?' + (gewaehlt.oeffentlich == 1 ? ' Er verschwindet für alle.' : ''))) return;
        document.getElementById('k-del-' + gewaehlt.id).submit();
    });
    document.getElementById('kZurueck').addEventListener('click', function () { app.dataset.ansicht = 'liste'; });   // von Visitenkarte oder Filter zurück zur Liste
    document.getElementById('kFilterKnopf').addEventListener('click', function () { app.dataset.ansicht = 'nav'; });

    // ---- Suche (blendet Kontakte und leere Buchstaben aus)
    var suche = document.getElementById('kontaktSuche');
    suche.addEventListener('input', function () {
        var q = suche.value.trim().toLowerCase(), sichtbar = 0, jeBuchstabe = {};
        liste.querySelectorAll('.k-eintrag').forEach(function (e) {
            var an = !q || e.dataset.suche.indexOf(q) >= 0; e.hidden = !an;
            if (an) { sichtbar++; jeBuchstabe[e.dataset.buchstabe] = true; }
        });
        liste.querySelectorAll('.k-buchstabe').forEach(function (b) { b.hidden = !jeBuchstabe[b.dataset.buchstabe]; });
        document.getElementById('kAnzahl').textContent = sichtbar;
        document.getElementById('kKeine').hidden = sichtbar > 0 || !KONTAKTE.length;
        if (q) app.dataset.ansicht = 'liste';
    });
    suche.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

    // ---- Start: Sprungmarke #k<ID> (z. B. nach dem Speichern) oder auf großen Bildschirmen den ersten Kontakt zeigen
    var start = (location.hash.match(/^#k(\d+)$/) || [])[1];
    var idx = start ? KONTAKTE.findIndex(function (k) { return String(k.id) === start; }) : -1;
    if (idx >= 0) anzeigen(idx);
    else if (KONTAKTE.length && window.matchMedia('(min-width: 992px)').matches) { anzeigen(0); }
    else app.dataset.ansicht = 'liste';

    <?php if ($vorgabe): ?>
    // Aus dem Lesebereich: Name in Vor- und Nachname aufteilen
    var vg = <?php echo json_encode($vorgabe, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, teile = vg.name.trim().split(/\s+/);
    oeffnen({ email: vg.email, vorname: teile.length > 1 ? teile.slice(0, -1).join(' ') : '', nachname: teile.length > 1 ? teile[teile.length - 1] : '', organisation: teile.length === 1 ? teile[0] : '' });
    <?php endif; ?>
})();
</script>
<?php endif; ?>
