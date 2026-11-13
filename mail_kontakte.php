<?php
// mail_kontakte.php – Kontakte für das E-Mail-Programm: privat (nur ich) oder gemeinsam (alle mit E-Mail-Zugang)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && MailKontakt::darfNutzen()) {
    try {
        if (($_POST['aktion'] ?? '') === 'loeschen') {
            MailKontakt::loeschen((int)$_POST['id']);
            Session::setFlashMessage('success', 'Kontakt gelöscht.');
        } else {
            MailKontakt::speichern($_POST, (int)($_POST['id'] ?? 0) ?: null);
            Session::setFlashMessage('success', 'Kontakt gespeichert.');
        }
    } catch (RuntimeException $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: mail_kontakte.php' . (!empty($_POST['filter']) ? '?filter=' . urlencode($_POST['filter']) : ''));
    exit;
}

$filter = in_array($_GET['filter'] ?? '', ['gemeinsam', 'privat'], true) ? $_GET['filter'] : '';
$kontakte = MailKontakt::darfNutzen() ? MailKontakt::sichtbare($filter) : [];
$anzahl = MailKontakt::darfNutzen() ? ['' => count(MailKontakt::sichtbare()), 'gemeinsam' => count(MailKontakt::sichtbare('gemeinsam')), 'privat' => count(MailKontakt::sichtbare('privat'))] : [];

// Vorbelegung aus dem Lesebereich („+ Kontakt“)
$vorgabe = !empty($_GET['neu']) ? ['email' => (string)($_GET['email'] ?? ''), 'name' => (string)($_GET['name'] ?? '')] : null;

include 'includes/header.php';
?>
<style>
.kontakt-avatar { width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 14px; flex-shrink: 0; }
.kontakte td { vertical-align: middle; }
.kontakte .unterzeile { font-size: .8rem; color: var(--text-muted, #6c757d); }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0"><i class="bi bi-person-lines-fill"></i> Kontakte</h1>
    <div class="d-flex gap-2">
        <a href="mail.php" class="btn btn-outline-secondary"><i class="bi bi-envelope"></i> Zur E-Mail</a>
        <?php if (MailKontakt::darfNutzen()): ?><button class="btn btn-success" data-kontakt='{}'><i class="bi bi-person-plus"></i> Neuer Kontakt</button><?php endif; ?>
    </div>
</div>

<?php if (!MailKontakt::darfNutzen()): ?>
<div class="alert alert-info alert-permanent">Kontakte gibt es für alle, die ein E-Mail-Postfach in Syncopa nutzen. Unter <a href="konto.php#postfaecher">Mein Konto</a> kannst du dein Postfach einrichten.</div>
<?php else: ?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <ul class="nav nav-pills">
        <?php foreach (['' => 'Alle', 'gemeinsam' => 'Gemeinsame', 'privat' => 'Meine privaten'] as $fv => $fl): ?>
        <li class="nav-item"><a class="nav-link py-1 <?php echo $filter === $fv ? 'active' : ''; ?>" href="mail_kontakte.php<?php echo $fv ? '?filter=' . $fv : ''; ?>"><?php echo $fl; ?> <span class="badge bg-secondary"><?php echo $anzahl[$fv]; ?></span></a></li>
        <?php endforeach; ?>
    </ul>
    <input type="search" class="form-control form-control-sm ms-auto" style="max-width:280px" id="kontaktSuche" placeholder="Suchen (Name, Organisation, E-Mail)" aria-label="Kontakte suchen">
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 kontakte">
            <thead class="table-light"><tr><th>Name</th><th>E-Mail</th><th>Telefon</th><th>Sichtbar für</th><th class="text-end"></th></tr></thead>
            <tbody>
            <?php foreach ($kontakte as $k):
                $name = MailKontakt::name($k);
                $farbe = 'hsl(' . (crc32($k['email']) % 360) . ',45%,45%)';
                $initialen = mb_strtoupper(mb_substr($k['vorname'] ?: $name, 0, 1) . mb_substr($k['nachname'] ?? '', 0, 1));
                $suchtext = mb_strtolower(implode(' ', [$name, $k['organisation'], $k['funktion'], $k['email'], $k['telefon']]));
                $bearbeitbar = MailKontakt::darfBearbeiten($k);
            ?>
            <tr data-suche="<?php echo htmlspecialchars($suchtext); ?>">
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="kontakt-avatar" style="background:<?php echo $farbe; ?>"><?php echo htmlspecialchars($initialen); ?></span>
                        <div>
                            <strong><?php echo htmlspecialchars($name); ?></strong>
                            <?php $zusatz = array_filter([$k['funktion'], ($k['vorname'] || $k['nachname']) ? $k['organisation'] : '']); if ($zusatz): ?>
                            <div class="unterzeile"><?php echo htmlspecialchars(implode(' · ', $zusatz)); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><a href="mail.php?an=<?php echo urlencode($k['email']); ?>&name=<?php echo urlencode($name); ?>"><?php echo htmlspecialchars($k['email']); ?></a></td>
                <td class="small"><?php echo htmlspecialchars($k['telefon'] ?? ''); ?></td>
                <td>
                    <?php if ($k['oeffentlich']): ?>
                    <span class="badge bg-success" title="Alle mit E-Mail-Zugang sehen diesen Kontakt"><i class="bi bi-people"></i> Alle</span>
                    <div class="unterzeile">von <?php echo htmlspecialchars($k['ersteller'] ?? '–'); ?></div>
                    <?php else: ?>
                    <span class="badge bg-secondary" title="Nur du siehst diesen Kontakt"><i class="bi bi-lock"></i> Privat</span>
                    <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                    <a href="mail.php?an=<?php echo urlencode($k['email']); ?>&name=<?php echo urlencode($name); ?>" class="btn btn-sm btn-outline-primary" title="E-Mail schreiben"><i class="bi bi-envelope-plus"></i></a>
                    <button class="btn btn-sm btn-outline-secondary" title="<?php echo $bearbeitbar ? 'Bearbeiten' : 'Ansehen'; ?>"
                            data-kontakt='<?php echo htmlspecialchars(json_encode($k + ['nur_lesen' => !$bearbeitbar]), ENT_QUOTES); ?>'><i class="bi <?php echo $bearbeitbar ? 'bi-pencil' : 'bi-eye'; ?>"></i></button>
                    <?php if ($bearbeitbar): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Kontakt „<?php echo htmlspecialchars(addslashes($name)); ?>“ löschen?<?php echo $k['oeffentlich'] ? ' Er verschwindet für alle.' : ''; ?>')">
                        <input type="hidden" name="aktion" value="loeschen"><input type="hidden" name="id" value="<?php echo $k['id']; ?>"><input type="hidden" name="filter" value="<?php echo $filter; ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Löschen"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="keineTreffer" <?php echo $kontakte ? 'hidden' : ''; ?>><td colspan="5" class="text-center text-muted py-4">
                <?php echo $kontakte ? 'Keine Treffer.' : 'Noch keine Kontakte. Lege z. B. Gemeinde, Pfarre, Musikhaus oder Presse als Kontakt an.'; ?></td></tr>
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Kontakte erscheinen beim Schreiben einer E-Mail als Vorschlag. Gemeinsame Kontakte sehen alle, die ein Postfach in Syncopa nutzen. Ändern oder löschen kann sie nur, wer sie angelegt hat, oder ein Administrator. Mails von Kontakten kommen nie in den Junk-Ordner.</p>

<!-- Dialog -->
<div class="modal fade" id="kontaktModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" class="modal-content">
        <input type="hidden" name="id"><input type="hidden" name="filter" value="<?php echo $filter; ?>">
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
<script>
(function () {
    var suche = document.getElementById('kontaktSuche');
    if (suche) suche.addEventListener('input', function () {
        var q = suche.value.trim().toLowerCase(), sichtbar = 0;
        document.querySelectorAll('tr[data-suche]').forEach(function (tr) { var an = !q || tr.dataset.suche.indexOf(q) >= 0; tr.hidden = !an; if (an) sichtbar++; });
        var leer = document.getElementById('keineTreffer'); if (leer) leer.hidden = sichtbar > 0 || !document.querySelector('tr[data-suche]');
    });

    var modal = document.getElementById('kontaktModal');
    if (!modal) return;
    var form = modal.querySelector('form'), schalter = document.getElementById('k-oeffentlich'), hinweis = document.getElementById('k-sichtbar-text');
    function hinweisSetzen() { hinweis.textContent = schalter.checked ? 'Alle, die ein Postfach in Syncopa nutzen, sehen diesen Kontakt.' : 'Ohne Haken bleibt der Kontakt privat: nur du siehst ihn.'; }
    schalter.addEventListener('change', hinweisSetzen);
    function oeffnen(d) {
        form.reset();
        form.elements.namedItem('id').value = d.id || '';
        ['vorname', 'nachname', 'organisation', 'funktion', 'email', 'telefon', 'adresse', 'notizen'].forEach(function (n) { form.elements.namedItem(n).value = d[n] || ''; });
        schalter.checked = d.oeffentlich == 1;
        var nurLesen = !!d.nur_lesen;
        form.querySelector('fieldset').disabled = nurLesen;
        document.getElementById('k-speichern').hidden = nurLesen;
        modal.querySelector('.modal-title').textContent = d.id ? (nurLesen ? 'Kontakt' : 'Kontakt bearbeiten') : 'Neuer Kontakt';
        hinweisSetzen();
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    document.querySelectorAll('[data-kontakt]').forEach(function (b) { b.addEventListener('click', function () { oeffnen(JSON.parse(b.dataset.kontakt || '{}')); }); });
    <?php if ($vorgabe): ?>
    // Aus dem Lesebereich: Name in Vor- und Nachname aufteilen
    var vg = <?php echo json_encode($vorgabe, JSON_UNESCAPED_UNICODE); ?>, teile = vg.name.trim().split(/\s+/);
    oeffnen({ email: vg.email, vorname: teile.length > 1 ? teile.slice(0, -1).join(' ') : '', nachname: teile.length > 1 ? teile[teile.length - 1] : '', organisation: teile.length === 1 ? teile[0] : '' });
    <?php endif; ?>
})();
</script>
