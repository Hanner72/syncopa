<?php
// shop_bestellungen.php – Bestell-Eingang des Shops (Katalog-Server, Admin)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin() || !SHOP_IST_ZENTRALE) {
    Session::setFlashMessage('danger', 'Kein Zugriff auf die Shop-Verwaltung.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        if (($_POST['aktion'] ?? '') === 'speichern') {
            Shop::bestellungAktualisieren($id, [
                'status' => (string)($_POST['status'] ?? ''), 'sendungsnummer' => (string)($_POST['sendungsnummer'] ?? ''),
                'tracking_url' => (string)($_POST['tracking_url'] ?? ''), 'nachricht' => (string)($_POST['nachricht'] ?? ''),
            ]);
            Session::setFlashMessage('success', 'Bestellung gespeichert – der Verein sieht den neuen Stand.');
        } elseif (($_POST['aktion'] ?? '') === 'mail') {
            Session::setFlashMessage(Shop::bestellungMelden($id) ? 'success' : 'danger',
                Shop::versandPostfach() && Shop::einstellung('bestell_email') !== '' ? 'Benachrichtigung gesendet an ' . Shop::einstellung('bestell_email') . '.'
                : 'Keine Benachrichtigung möglich: Bitte unter Shop-Einstellungen die E-Mail-Adresse und das Versand-Postfach hinterlegen.');
        }
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: shop_bestellungen.php' . (($_POST['filter'] ?? '') !== '' ? '?status=' . urlencode((string)$_POST['filter']) : '') . '#b-' . $id);
    exit;
}

$filter = (string)($_GET['status'] ?? '');
$bestellungen = Shop::bestellungen($filter);
$statusFarbe = Shop::STATUS_FARBE;
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
$mailBereit = Shop::versandPostfach() && Shop::einstellung('bestell_email') !== '';

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-inbox"></i> Bestellungen</h1>
    <a href="shop_verwaltung.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zur Verwaltung</a>
</div>

<form method="get" class="d-flex align-items-center gap-2 mb-3">
    <label for="status" class="small text-muted">Status</label>
    <select name="status" id="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <option value="">Alle</option>
        <?php foreach (Shop::STATUS as $sv => $sl): ?>
        <option value="<?php echo $sv; ?>" <?php echo $filter === $sv ? 'selected' : ''; ?>><?php echo $sl; ?></option>
        <?php endforeach; ?>
    </select>
    <span class="text-muted small"><?php echo count($bestellungen); ?> Bestellungen</span>
</form>
<?php if (!$mailBereit): ?>
<div class="alert alert-warning alert-permanent small"><i class="bi bi-envelope-exclamation"></i> Neue Bestellungen werden noch <strong>nicht per E-Mail gemeldet</strong>. Bitte in der <a href="shop_verwaltung.php#einstellungen">Shop-Verwaltung → Einstellungen</a> die Adresse für Bestell-Meldungen und das Versand-Postfach hinterlegen.</div>
<?php endif; ?>

<?php if (!$bestellungen): ?>
<div class="alert alert-info alert-permanent"><i class="bi bi-info-circle"></i> Noch keine Bestellungen. Sobald ein Verein im Shop bestellt, erscheint sie hier.</div>
<?php else: ?>
<div class="d-flex justify-content-end gap-2 mb-2">
    <button type="button" class="btn btn-sm btn-outline-secondary" data-alle="auf"><i class="bi bi-arrows-expand"></i> Alle aufklappen</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-alle="zu"><i class="bi bi-arrows-collapse"></i> Alle zuklappen</button>
</div>
<div class="d-flex flex-column gap-2">
<?php foreach ($bestellungen as $b): $det = Shop::bestellung((int)$b['id']); $verlauf = Shop::bestellVerlauf((int)$b['id']);
    $stueck = array_sum(array_map(fn($p) => (int)$p['menge'], $det['positionen'])); ?>
    <details class="card best-karte" id="b-<?php echo (int)$b['id']; ?>">
        <summary class="card-header best-kopf">
            <i class="bi bi-chevron-right best-pfeil"></i>
            <span class="best-nr"><?php echo $h($b['bestellnummer']); ?></span>
            <span class="best-verein"><?php echo $h($b['verein_name'] ?: 'Unbekannter Verein'); ?></span>
            <span class="best-info text-muted small"><?php echo date('d.m.Y H:i', strtotime($b['erstellt_am'])); ?> · <?php echo (int)$stueck; ?> Stück</span>
            <span class="best-summe"><?php echo Shop::euro($b['summe']); ?></span>
            <?php if ($b['tracking_url']): ?><a href="<?php echo $h($b['tracking_url']); ?>" target="_blank" rel="noopener" class="small" title="Sendung verfolgen"><i class="bi bi-truck"></i></a><?php endif; ?>
            <span class="badge bg-<?php echo $statusFarbe[$b['status']] ?? 'secondary'; ?>"><?php echo Shop::STATUS[$b['status']] ?? $h($b['status']); ?></span>
        </summary>
        <div class="card-body">
            <!-- Bearbeitung: Status, Sendungsverfolgung, Nachricht an den Verein -->
            <form method="post" class="border rounded p-3 mb-3" style="background:var(--bg-body)">
                <input type="hidden" name="aktion" value="speichern"><input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>"><input type="hidden" name="filter" value="<?php echo $h($filter); ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="form-label small mb-1" for="st-<?php echo (int)$b['id']; ?>">Status</label>
                        <select name="status" id="st-<?php echo (int)$b['id']; ?>" class="form-select form-select-sm">
                            <?php foreach (Shop::STATUS as $sv => $sl): ?><option value="<?php echo $sv; ?>" <?php echo $b['status'] === $sv ? 'selected' : ''; ?>><?php echo $sl; ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-3"><label class="form-label small mb-1" for="sn-<?php echo (int)$b['id']; ?>">Sendungsnummer</label>
                        <input class="form-control form-control-sm" id="sn-<?php echo (int)$b['id']; ?>" name="sendungsnummer" maxlength="100" value="<?php echo $h($b['sendungsnummer'] ?? ''); ?>" placeholder="optional"></div>
                    <div class="col-md-6"><label class="form-label small mb-1" for="tu-<?php echo (int)$b['id']; ?>">Link zur Sendungsverfolgung</label>
                        <input type="url" class="form-control form-control-sm" id="tu-<?php echo (int)$b['id']; ?>" name="tracking_url" maxlength="500" value="<?php echo $h($b['tracking_url'] ?? ''); ?>" placeholder="https://… (z. B. Post, DPD, GLS)"></div>
                    <div class="col-md-9"><label class="form-label small mb-1" for="na-<?php echo (int)$b['id']; ?>">Nachricht an den Verein <span class="text-muted">(sieht der Verein bei der Bestellung)</span></label>
                        <textarea class="form-control form-control-sm" id="na-<?php echo (int)$b['id']; ?>" name="nachricht" rows="1" placeholder="z. B. Stickerei dauert ca. 2 Wochen länger"><?php echo $h($b['nachricht'] ?? ''); ?></textarea></div>
                    <div class="col-md-3 d-flex gap-1 justify-content-end">
                        <button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> Speichern</button>
                        <?php if ($mailBereit): ?><button class="btn btn-sm btn-outline-secondary" name="aktion" value="mail" title="Bestell-Meldung erneut an <?php echo $h(Shop::einstellung('bestell_email')); ?> senden"><i class="bi bi-envelope"></i></button><?php endif; ?>
                    </div>
                </div>
                <?php if ($verlauf): ?>
                <div class="small text-muted mt-2"><i class="bi bi-clock-history"></i>
                    <?php echo implode(' <i class="bi bi-chevron-right" style="font-size:.7em"></i> ', array_map(fn($v) => $h(Shop::STATUS[$v['status']] ?? $v['status']) . ' ' . date('d.m. H:i', strtotime($v['erstellt_am'])), $verlauf)); ?>
                </div>
                <?php endif; ?>
            </form>
            <div class="row g-3 mb-3">
                <div class="col-md-6 small">
                    <div class="text-muted">Besteller</div>
                    <?php echo $h($b['besteller_name'] ?: '–'); ?>
                    <?php if ($b['besteller_email']): ?><br><a href="mailto:<?php echo $h($b['besteller_email']); ?>"><?php echo $h($b['besteller_email']); ?></a><?php endif; ?>
                    <?php if ($b['besteller_telefon']): ?><br><?php echo $h($b['besteller_telefon']); ?><?php endif; ?>
                </div>
                <div class="col-md-6 small">
                    <div class="text-muted">Lieferadresse</div>
                    <?php echo nl2br($h($b['lieferadresse'] ?: '–')); ?>
                </div>
                <?php if ($b['bemerkung']): ?>
                <div class="col-12 small"><div class="text-muted">Bemerkung</div><?php echo nl2br($h($b['bemerkung'])); ?></div>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Artikel</th><th>Größe</th><th class="text-end">Einzel</th><th class="text-end">Menge</th><th class="text-end">Summe</th></tr></thead>
                    <tbody>
                    <?php foreach ($det['positionen'] as $p): ?>
                    <tr>
                        <td><?php echo $h($p['artikel_name']); ?><?php if ($p['artikelnummer']): ?> <span class="text-muted small"><?php echo $h($p['artikelnummer']); ?></span><?php endif; ?>
                            <?php $ved = $p['veredelung'] ? (array)json_decode((string)$p['veredelung'], true) : []; if ($ved): ?>
                            <div class="mt-1 d-flex flex-column gap-1">
                                <?php foreach ($ved as $v): $durl = $v['datei'] ? 'shop_datei.php?b=' . (int)$b['id'] . '&d=' . rawurlencode($v['datei']) : null; ?>
                                <div class="d-flex align-items-center gap-2 small">
                                    <?php if ($durl && preg_match('/\.(jpg|png|webp)$/', $v['datei'])): ?>
                                    <a href="<?php echo $h($durl); ?>" target="_blank" rel="noopener"><img src="<?php echo $h($durl); ?>" alt="" style="width:34px;height:34px;object-fit:contain;border:1px solid var(--border);border-radius:4px;background:#fff"></a>
                                    <?php elseif ($durl): ?>
                                    <a href="<?php echo $h($durl); ?>" target="_blank" rel="noopener" class="d-inline-flex align-items-center justify-content-center text-danger" style="width:34px;height:34px;border:1px solid var(--border);border-radius:4px"><i class="bi bi-file-earmark-pdf"></i></a>
                                    <?php endif; ?>
                                    <span><i class="bi bi-stars text-primary"></i> <strong><?php echo $h($v['bezeichnung']); ?></strong> (+<?php echo Shop::euro($v['preis']); ?> je Stück)
                                        <?php if ($durl): ?> · <a href="<?php echo $h($durl); ?>&download=1"><?php echo $h($v['original']); ?></a><?php endif; ?>
                                        <?php if (($v['hinweis'] ?? '') !== ''): ?><br><span class="text-muted">„<?php echo $h($v['hinweis']); ?>“</span><?php endif; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?></td>
                        <td><?php echo $h($p['variante'] ?: '–'); ?></td>
                        <td class="text-end" style="font-variant-numeric:tabular-nums"><?php echo Shop::euro($p['einzelpreis']); ?></td>
                        <td class="text-end"><?php echo (int)$p['menge']; ?></td>
                        <td class="text-end" style="font-variant-numeric:tabular-nums"><?php echo Shop::euro($p['einzelpreis'] * $p['menge']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <?php if ((float)($b['versandkosten'] ?? 0) > 0): ?>
                        <tr><td colspan="4" class="text-end border-0 py-0 text-muted small"><i class="bi bi-truck"></i> Versand</td><td class="text-end border-0 py-0" style="font-variant-numeric:tabular-nums"><?php echo Shop::euro($b['versandkosten']); ?></td></tr>
                    <?php endif; ?>
                        <tr><th colspan="4" class="text-end">Summe</th><th class="text-end" style="font-variant-numeric:tabular-nums"><?php echo Shop::euro($b['summe']); ?></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </details>
<?php endforeach; ?>
</div>
<?php endif; ?>
<style>
.best-karte{margin:0}
.best-kopf{display:flex;align-items:center;flex-wrap:wrap;gap:6px 12px;cursor:pointer;list-style:none;user-select:none}
.best-kopf::-webkit-details-marker{display:none}
.best-kopf:hover{background:var(--bg-body)}
.best-pfeil{color:var(--text-muted);transition:transform .15s}
.best-karte[open] > .best-kopf .best-pfeil{transform:rotate(90deg)}
.best-karte[open] > .best-kopf{border-bottom:1px solid var(--border)}
.best-karte:not([open]) > .best-kopf{border-bottom:none;border-radius:inherit}
.best-nr{font-weight:700}
.best-verein{font-weight:600}
.best-info{margin-right:auto}
.best-summe{font-weight:700;font-variant-numeric:tabular-nums}
</style>

<?php include 'includes/footer.php'; ?>
<script>
// Bestellungen auf-/zuklappen; die zuletzt bearbeitete (Sprungmarke #b-…) bleibt offen
(function () {
    var karten = document.querySelectorAll('.best-karte');
    if (location.hash) {
        var ziel = document.querySelector(location.hash.replace(/[^#\w-]/g, ''));
        if (ziel && ziel.classList.contains('best-karte')) { ziel.open = true; setTimeout(function () { ziel.scrollIntoView({ block: 'start' }); }, 50); }
    }
    document.querySelectorAll('[data-alle]').forEach(function (b) {
        b.addEventListener('click', function () { var auf = b.dataset.alle === 'auf'; karten.forEach(function (k) { k.open = auf; }); });
    });
    // Klick auf den Verfolgungslink im Kopf soll die Karte nicht auf-/zuklappen
    document.querySelectorAll('.best-kopf a').forEach(function (a) { a.addEventListener('click', function (e) { e.stopPropagation(); }); });
})();
</script>
