<?php
// shop.php – Vereins-Shop (Client): zeigt den vom Katalog-Server gelieferten Katalog, Warenkorb und Bestellung.
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin()) {
    Session::setFlashMessage('danger', 'Der Shop steht derzeit nur dem Vorstand (Administratoren) zur Verfügung.');
    header('Location: index.php');
    exit;
}
if (!ShopKatalog::aktiv()) {
    include 'includes/header.php';
    echo '<div class="alert alert-info alert-permanent mt-3"><i class="bi bi-info-circle"></i> Für diese Installation ist kein Shop eingerichtet. In <code>config.php</code> die Adresse des Katalog-Servers (<code>SHOP_ZENTRALE_URL</code>) und den Schlüssel (<code>SHOP_API_KEY</code>) eintragen.</div>';
    include 'includes/footer.php';
    exit;
}

$db = Database::getInstance();
if (!isset($_SESSION['shop_warenkorb']) || !is_array($_SESSION['shop_warenkorb'])) $_SESSION['shop_warenkorb'] = [];

// ---------------------------------------------------------------- Aktionen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zielAnsicht = 'liste';
    try {
        $katalog = ShopKatalog::katalog();
        switch ($_POST['aktion'] ?? '') {
            case 'hinzufuegen':
                $a = ShopKatalog::artikel($katalog, (int)($_POST['artikel_id'] ?? 0));
                if (!$a) throw new RuntimeException('Artikel nicht gefunden.');
                $menge = max(1, min(99, (int)($_POST['menge'] ?? 1)));
                $variante = null; $preis = (float)$a['preis']; $anr = $a['artikelnummer'];
                if (!empty($a['varianten'])) {
                    $vid = (int)($_POST['variante_id'] ?? 0);
                    foreach ($a['varianten'] as $v) if ((int)$v['id'] === $vid) { $variante = $v; break; }
                    if (!$variante) throw new RuntimeException('Bitte eine Größe wählen.');
                    $preis = (float)$variante['preis']; $anr = $variante['artikelnummer'] ?: $a['artikelnummer'];
                }
                $schluessel = $a['id'] . ':' . ($variante['id'] ?? 0);
                $wk = &$_SESSION['shop_warenkorb'];
                if (isset($wk[$schluessel])) $wk[$schluessel]['menge'] = min(99, $wk[$schluessel]['menge'] + $menge);
                else $wk[$schluessel] = [
                    'artikel_id' => (int)$a['id'], 'artikel_name' => $a['name'],
                    'variante' => $variante['bezeichnung'] ?? null, 'artikelnummer' => $anr,
                    'einzelpreis' => round($preis, 2), 'menge' => $menge,
                    'bild' => $a['bilder'][0] ?? null,
                ];
                unset($wk);
                Session::setFlashMessage('success', 'Zum Warenkorb hinzugefügt.');
                $zielAnsicht = 'liste';
                break;
            case 'menge':
                $k = (string)($_POST['schluessel'] ?? '');
                $m = (int)($_POST['menge'] ?? 1);
                if (isset($_SESSION['shop_warenkorb'][$k])) {
                    if ($m <= 0) unset($_SESSION['shop_warenkorb'][$k]);
                    else $_SESSION['shop_warenkorb'][$k]['menge'] = min(99, $m);
                }
                $zielAnsicht = 'warenkorb';
                break;
            case 'entfernen':
                unset($_SESSION['shop_warenkorb'][(string)($_POST['schluessel'] ?? '')]);
                $zielAnsicht = 'warenkorb';
                break;
            case 'leeren':
                $_SESSION['shop_warenkorb'] = [];
                $zielAnsicht = 'warenkorb';
                break;
            case 'bestellen':
                if (!$_SESSION['shop_warenkorb']) throw new RuntimeException('Der Warenkorb ist leer.');
                $positionen = array_map(fn($z) => [
                    'artikel_name' => $z['artikel_name'], 'variante' => $z['variante'],
                    'artikelnummer' => $z['artikelnummer'], 'einzelpreis' => $z['einzelpreis'], 'menge' => $z['menge'],
                ], array_values($_SESSION['shop_warenkorb']));
                $nr = ShopKatalog::bestellungSenden([
                    'verein_name'      => trim((string)($_POST['verein_name'] ?? '')),
                    'verein_url'       => BASE_URL,
                    'installation_id'  => (string)($db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'installations_id'")['wert'] ?? ''),
                    'besteller_name'   => trim((string)($_POST['besteller_name'] ?? '')),
                    'besteller_email'  => trim((string)($_POST['besteller_email'] ?? '')),
                    'besteller_telefon'=> trim((string)($_POST['besteller_telefon'] ?? '')),
                    'lieferadresse'    => trim((string)($_POST['lieferadresse'] ?? '')),
                    'bemerkung'        => trim((string)($_POST['bemerkung'] ?? '')),
                    'positionen'       => $positionen,
                ]);
                $_SESSION['shop_warenkorb'] = [];
                header('Location: shop.php?ansicht=fertig&nr=' . urlencode($nr));
                exit;
        }
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
        $zielAnsicht = $_POST['zuruck'] ?? $zielAnsicht;
    }
    header('Location: shop.php?ansicht=' . $zielAnsicht . (!empty($_POST['artikel_id']) && ($_POST['aktion'] ?? '') !== 'hinzufuegen' ? '&artikel=' . (int)$_POST['artikel_id'] : ''));
    exit;
}

// ---------------------------------------------------------------- Anzeige
$ansicht = $_GET['ansicht'] ?? 'liste';
$warenkorb = $_SESSION['shop_warenkorb'];
$wkAnzahl = array_sum(array_map(fn($z) => $z['menge'], $warenkorb));
$wkSumme  = array_sum(array_map(fn($z) => $z['einzelpreis'] * $z['menge'], $warenkorb));
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
$euro = fn($b) => number_format((float)$b, 2, ',', '.') . ' €';

$ladeFehler = null; $katalog = ['info' => ['titel' => 'Shop'], 'kategorien' => [], 'artikel' => []];
if ($ansicht !== 'fertig') {
    try { $katalog = ShopKatalog::katalog(); }
    catch (\Throwable $e) { $ladeFehler = $e->getMessage(); }
}
$titel = $katalog['info']['titel'] ?? 'Shop';

include 'includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0"><i class="bi bi-bag"></i> <?php echo $h($titel); ?></h1>
    <div class="d-flex gap-2">
        <?php if ($ansicht !== 'liste'): ?><a href="shop.php" class="btn btn-outline-secondary"><i class="bi bi-grid"></i> Zum Katalog</a><?php endif; ?>
        <a href="shop.php?ansicht=warenkorb" class="btn btn-primary position-relative">
            <i class="bi bi-cart"></i> Warenkorb
            <?php if ($wkAnzahl): ?><span class="badge bg-light text-dark ms-1"><?php echo (int)$wkAnzahl; ?></span><?php endif; ?>
        </a>
    </div>
</div>

<?php if ($ladeFehler): ?>
<div class="alert alert-warning alert-permanent"><i class="bi bi-exclamation-triangle"></i> <?php echo $h($ladeFehler); ?></div>
<?php endif; ?>

<?php if ($ansicht === 'fertig'): ?>
    <div class="card"><div class="card-body text-center py-5">
        <div class="mb-3"><i class="bi bi-check-circle-fill text-success" style="font-size:48px"></i></div>
        <h3>Bestellung eingegangen</h3>
        <p class="text-muted">Deine Bestellnummer: <strong><?php echo $h($_GET['nr'] ?? ''); ?></strong></p>
        <p class="text-muted" style="max-width:520px;margin:0 auto">Die Bestellung wurde an den Shop-Betreiber übermittelt. Die Abrechnung erfolgt per Rechnung. Bei Rückfragen die Bestellnummer angeben.</p>
        <a href="shop.php" class="btn btn-primary mt-3"><i class="bi bi-grid"></i> Zurück zum Katalog</a>
    </div></div>

<?php elseif ($ansicht === 'artikel' && ($a = ShopKatalog::artikel($katalog, (int)($_GET['artikel'] ?? 0)))): ?>
    <div class="card"><div class="card-body">
        <div class="row g-4">
            <div class="col-md-5">
                <?php if (!empty($a['bilder'])): ?>
                <img src="<?php echo $h($a['bilder'][0]); ?>" alt="" class="w-100" style="border-radius:8px;border:1px solid var(--border);object-fit:cover;max-height:360px">
                <?php if (count($a['bilder']) > 1): ?>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php foreach ($a['bilder'] as $b): ?><img src="<?php echo $h($b); ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:4px;border:1px solid var(--border)"><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="d-flex align-items-center justify-content-center text-muted" style="height:240px;border:1px dashed var(--border);border-radius:8px"><i class="bi bi-image" style="font-size:40px"></i></div>
                <?php endif; ?>
            </div>
            <div class="col-md-7">
                <div class="text-muted small"><?php echo $h(ShopKatalog::kategorieName($katalog, $a['kategorie_id'])); ?></div>
                <h3 class="mb-1"><?php echo $h($a['name']); ?></h3>
                <?php if ($a['artikelnummer']): ?><div class="text-muted small mb-2"><?php echo $h($a['artikelnummer']); ?></div><?php endif; ?>
                <?php if ($a['beschreibung']): ?><p style="white-space:pre-line"><?php echo $h($a['beschreibung']); ?></p><?php endif; ?>
                <?php if ($a['lieferzeit']): ?><p class="small text-muted"><i class="bi bi-truck"></i> Lieferzeit: <?php echo $h($a['lieferzeit']); ?></p><?php endif; ?>
                <form method="post" class="mt-3">
                    <input type="hidden" name="aktion" value="hinzufuegen">
                    <input type="hidden" name="artikel_id" value="<?php echo (int)$a['id']; ?>">
                    <?php if (!empty($a['varianten'])): ?>
                    <label class="form-label" for="variante_id">Größe</label>
                    <select class="form-select mb-3" id="variante_id" name="variante_id" required style="max-width:320px">
                        <option value="">– bitte wählen –</option>
                        <?php foreach ($a['varianten'] as $v): ?>
                        <option value="<?php echo (int)$v['id']; ?>"><?php echo $h($v['bezeichnung']); ?> – <?php echo $h($euro($v['preis'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <div class="h4 mb-3"><?php echo $h($euro($a['preis'])); ?></div>
                    <?php endif; ?>
                    <div class="d-flex align-items-end gap-2">
                        <div><label class="form-label" for="menge">Menge</label>
                            <input type="number" class="form-control" id="menge" name="menge" value="1" min="1" max="99" style="width:100px"></div>
                        <button class="btn btn-success"><i class="bi bi-cart-plus"></i> In den Warenkorb</button>
                    </div>
                </form>
            </div>
        </div>
    </div></div>

<?php elseif ($ansicht === 'warenkorb'): ?>
    <?php if (!$warenkorb): ?>
    <div class="alert alert-info alert-permanent"><i class="bi bi-cart"></i> Dein Warenkorb ist leer. <a href="shop.php" class="alert-link">Zum Katalog</a></div>
    <?php else: ?>
    <div class="card mb-3"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Artikel</th><th>Größe</th><th class="text-end">Einzel</th><th style="width:120px">Menge</th><th class="text-end">Summe</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($warenkorb as $k => $z): ?>
        <tr>
            <td><?php echo $h($z['artikel_name']); ?><?php if ($z['artikelnummer']): ?><br><small class="text-muted"><?php echo $h($z['artikelnummer']); ?></small><?php endif; ?></td>
            <td><?php echo $h($z['variante'] ?: '–'); ?></td>
            <td class="text-end" style="font-variant-numeric:tabular-nums"><?php echo $h($euro($z['einzelpreis'])); ?></td>
            <td>
                <form method="post" class="d-flex gap-1">
                    <input type="hidden" name="aktion" value="menge"><input type="hidden" name="schluessel" value="<?php echo $h($k); ?>">
                    <input type="number" name="menge" value="<?php echo (int)$z['menge']; ?>" min="0" max="99" class="form-control form-control-sm" style="width:72px" onchange="this.form.submit()">
                </form>
            </td>
            <td class="text-end" style="font-variant-numeric:tabular-nums"><?php echo $h($euro($z['einzelpreis'] * $z['menge'])); ?></td>
            <td class="text-end">
                <form method="post"><input type="hidden" name="aktion" value="entfernen"><input type="hidden" name="schluessel" value="<?php echo $h($k); ?>">
                    <button class="btn btn-sm btn-link text-danger p-0" title="Entfernen"><i class="bi bi-trash"></i></button></form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="4" class="text-end">Gesamt</th><th class="text-end" style="font-variant-numeric:tabular-nums"><?php echo $h($euro($wkSumme)); ?></th><th></th></tr></tfoot>
    </table></div></div>
    <div class="d-flex justify-content-between">
        <form method="post" onsubmit="return confirm('Warenkorb leeren?')"><input type="hidden" name="aktion" value="leeren"><button class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i> Leeren</button></form>
        <a href="shop.php?ansicht=kasse" class="btn btn-primary">Zur Bestellung <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php endif; ?>

<?php elseif ($ansicht === 'kasse'): ?>
    <?php
    if (!$warenkorb) { echo '<div class="alert alert-info alert-permanent">Der Warenkorb ist leer.</div>'; }
    else {
        $vereinsname = (string)($db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'vereinsname'")['wert'] ?? '');
        $meinBenutzer = $db->fetchOne("SELECT email FROM benutzer WHERE id = ?", [Session::getUserId()]);
    ?>
    <form method="post" class="row g-4">
        <input type="hidden" name="aktion" value="bestellen"><input type="hidden" name="zuruck" value="kasse">
        <div class="col-lg-7">
            <div class="card"><div class="card-header"><h5 class="mb-0">Bestelldaten</h5></div><div class="card-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label" for="verein_name">Verein</label>
                        <input class="form-control" id="verein_name" name="verein_name" required value="<?php echo $h($vereinsname); ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="besteller_name">Ansprechpartner</label>
                        <input class="form-control" id="besteller_name" name="besteller_name" required value="<?php echo $h(Session::getUsername()); ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="besteller_email">E-Mail</label>
                        <input type="email" class="form-control" id="besteller_email" name="besteller_email" value="<?php echo $h($meinBenutzer['email'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="besteller_telefon">Telefon</label>
                        <input class="form-control" id="besteller_telefon" name="besteller_telefon"></div>
                    <div class="col-12"><label class="form-label" for="lieferadresse">Lieferadresse</label>
                        <textarea class="form-control" id="lieferadresse" name="lieferadresse" rows="3" required placeholder="Verein, Straße, PLZ Ort"></textarea></div>
                    <div class="col-12"><label class="form-label" for="bemerkung">Bemerkung (optional)</label>
                        <textarea class="form-control" id="bemerkung" name="bemerkung" rows="2"></textarea></div>
                </div>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card"><div class="card-header"><h5 class="mb-0">Zusammenfassung</h5></div><div class="card-body">
                <?php foreach ($warenkorb as $z): ?>
                <div class="d-flex justify-content-between small mb-1">
                    <span><?php echo (int)$z['menge']; ?>× <?php echo $h($z['artikel_name']); ?><?php echo $z['variante'] ? ' (' . $h($z['variante']) . ')' : ''; ?></span>
                    <span style="font-variant-numeric:tabular-nums"><?php echo $h($euro($z['einzelpreis'] * $z['menge'])); ?></span>
                </div>
                <?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-between fw-semibold"><span>Gesamt</span><span style="font-variant-numeric:tabular-nums"><?php echo $h($euro($wkSumme)); ?></span></div>
                <p class="text-muted small mt-3 mb-0">Die Abrechnung erfolgt per Rechnung. Versandkosten laut Hinweis des Betreibers.</p>
                <?php if (!empty($katalog['info']['versand'])): ?><p class="text-muted small mb-0"><i class="bi bi-truck"></i> <?php echo $h($katalog['info']['versand']); ?></p><?php endif; ?>
                <button class="btn btn-success w-100 mt-3"><i class="bi bi-bag-check"></i> Verbindlich bestellen</button>
            </div></div>
        </div>
    </form>
    <?php } ?>

<?php else: /* Katalog-Liste */ ?>
    <?php if (!empty($katalog['info']['einleitung'])): ?>
    <p class="text-muted" style="max-width:720px"><?php echo $h($katalog['info']['einleitung']); ?></p>
    <?php endif; ?>
    <?php if (empty($katalog['artikel']) && !$ladeFehler): ?>
    <div class="alert alert-info alert-permanent"><i class="bi bi-info-circle"></i> Im Shop sind derzeit keine Artikel verfügbar.</div>
    <?php endif; ?>
    <?php
    // nach Kategorie gruppieren
    $nachKat = [];
    foreach ($katalog['artikel'] as $a) $nachKat[(int)($a['kategorie_id'] ?? 0)][] = $a;
    $katReihenfolge = array_merge(array_map(fn($k) => (int)$k['id'], $katalog['kategorien']), [0]);
    foreach ($katReihenfolge as $kid):
        if (empty($nachKat[$kid])) continue; ?>
    <h5 class="mt-4 mb-3"><?php echo $h($kid ? ShopKatalog::kategorieName($katalog, $kid) : 'Sonstiges'); ?></h5>
    <div class="row g-3">
        <?php foreach ($nachKat[$kid] as $a):
            $preisAb = !empty($a['varianten']) ? min(array_map(fn($v) => (float)$v['preis'], $a['varianten'])) : (float)$a['preis']; ?>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="shop.php?ansicht=artikel&artikel=<?php echo (int)$a['id']; ?>" class="card h-100 text-decoration-none" style="color:inherit">
                <?php if (!empty($a['bilder'])): ?>
                <img src="<?php echo $h($a['bilder'][0]); ?>" alt="" style="height:160px;object-fit:cover;border-radius:6px 6px 0 0">
                <?php else: ?>
                <div class="d-flex align-items-center justify-content-center text-muted" style="height:160px;border-bottom:1px solid var(--border-light)"><i class="bi bi-image" style="font-size:32px"></i></div>
                <?php endif; ?>
                <div class="card-body py-2 px-3">
                    <div class="fw-semibold" style="font-size:14px"><?php echo $h($a['name']); ?></div>
                    <div class="text-muted small"><?php echo !empty($a['varianten']) ? 'ab ' : ''; ?><?php echo $h($euro($preisAb)); ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
