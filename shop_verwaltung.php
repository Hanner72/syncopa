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
                $postfach = (int)($_POST['mail_konto'] ?? 0);
                if ($postfach && !Database::getInstance()->fetchOne("SELECT id FROM mail_konten WHERE id = ? AND benutzer_id IS NULL", [$postfach])) $postfach = 0;
                Shop::einstellungSetzen('mail_konto', $postfach ? (string)$postfach : '');
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
            case 'veredelung':
                Shop::veredelungenSpeichern((array)($_POST['ved_preis'] ?? []), (array)($_POST['ved_aktiv'] ?? []));
                Session::setFlashMessage('success', 'Veredelungs-Preise gespeichert.');
                header('Location: shop_verwaltung.php#veredelung');
                exit;
            case 'angebot_loeschen':
                Shop::angebotLoeschen((int)$_POST['id']);
                Session::setFlashMessage('success', 'Angebot gelöscht.');
                header('Location: shop_verwaltung.php#angebote');
                exit;
        }
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: shop_verwaltung.php' . (($_POST['aktion'] ?? '') === 'einstellungen' ? '#einstellungen' : ''));
    exit;
}

$katNamen   = array_column(Shop::kategorien(), 'name', 'id');
$angebote   = Shop::angebote();
$vedTabelle = [];
foreach (Shop::veredelungen() as $v) $vedTabelle[$v['position']][$v['art']] = $v;
$anzArtikel = (int)(Database::getInstance()->fetchOne("SELECT COUNT(*) n FROM shop_artikel")['n'] ?? 0);
$anzKat     = count($katNamen);
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

<!-- Schnellzugriff: Artikel und Kategorien haben eigene Seiten -->
<div class="row g-3 mb-4">
    <div class="col-md-6"><a href="shop_artikel.php" class="card h-100 text-decoration-none"><div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-box-seam fs-3 text-primary"></i>
        <div><div class="fw-bold">Artikel</div><div class="small text-muted"><?php echo $anzArtikel; ?> Artikel – Liste mit Suche, Filtern und Sortierung</div></div>
        <i class="bi bi-chevron-right ms-auto text-muted"></i></div></a></div>
    <div class="col-md-6"><a href="shop_kategorien.php" class="card h-100 text-decoration-none"><div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-tags fs-3 text-primary"></i>
        <div><div class="fw-bold">Kategorien</div><div class="small text-muted"><?php echo $anzKat; ?> Kategorien – per Drag &amp; Drop anordnen</div></div>
        <i class="bi bi-chevron-right ms-auto text-muted"></i></div></a></div>
</div>

<!-- Veredelung -->
<div class="card mb-4" id="veredelung">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-stars"></i> Veredelung</h5></div>
    <form method="post" class="card-body">
        <input type="hidden" name="aktion" value="veredelung">
        <p class="small text-muted mb-3">Preise je <strong>Stück</strong> und Position. Nur angehakte Positionen werden im Shop angeboten – und nur bei Artikeln mit dem Schalter <strong>„Veredelung möglich“</strong>. Der Besteller lädt je gewählter Position ein Logo bzw. einen Schriftzug hoch (JPG, PNG, WebP, PDF); die Dateien findest du bei der Bestellung.</p>
        <div class="table-responsive">
            <table class="table align-middle mb-0" style="max-width:640px">
                <thead class="table-light"><tr><th>Position</th>
                    <?php foreach (Shop::VEREDELUNG_ARTEN as $artName): ?><th style="width:210px"><?php echo $h($artName); ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach (Shop::VEREDELUNG_POSITIONEN as $pos => $posName): ?>
                <tr>
                    <td class="fw-semibold"><?php echo $h($posName); ?></td>
                    <?php foreach (array_keys(Shop::VEREDELUNG_ARTEN) as $art): $v = $vedTabelle[$pos][$art] ?? null; if (!$v) { echo '<td></td>'; continue; } ?>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="form-check form-switch m-0" title="Im Shop anbieten">
                                <input class="form-check-input" type="checkbox" name="ved_aktiv[<?php echo (int)$v['id']; ?>]" value="1" <?php echo $v['aktiv'] ? 'checked' : ''; ?> aria-label="<?php echo $h($v['bezeichnung']); ?> anbieten">
                            </div>
                            <div class="input-group input-group-sm" style="max-width:130px">
                                <input class="form-control text-end" name="ved_preis[<?php echo (int)$v['id']; ?>]" inputmode="decimal" value="<?php echo $h(number_format((float)$v['preis'], 2, ',', '')); ?>" aria-label="Preis <?php echo $h($v['bezeichnung']); ?>">
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-end mt-3"><button class="btn btn-primary"><i class="bi bi-save"></i> Preise speichern</button></div>
    </form>
</div>

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
<div class="card mb-4" id="einstellungen">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-gear"></i> Shop-Einstellungen</h5></div>
    <form method="post" class="card-body">
        <input type="hidden" name="aktion" value="einstellungen">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="s-titel">Titel des Shops</label>
                <input class="form-control" id="s-titel" name="titel" value="<?php echo $h(Shop::einstellung('titel', 'Vereins-Shop')); ?>"></div>
            <div class="col-md-6"><label class="form-label" for="s-email">Bestellungen melden an (E-Mail)</label>
                <input type="email" class="form-control" id="s-email" name="bestell_email" value="<?php echo $h(Shop::einstellung('bestell_email')); ?>" placeholder="z. B. shop@deine-domain.at">
                <div class="form-text">Bei jeder neuen Bestellung geht eine Mail mit allen Positionen an diese Adresse.</div></div>
            <div class="col-md-6"><label class="form-label" for="s-postfach">Versenden über Postfach</label>
                <?php $postfaecher = MailKonto::funktionsPostfaecher(); $gewaehlt = (int)Shop::einstellung('mail_konto', '0'); ?>
                <select class="form-select" id="s-postfach" name="mail_konto">
                    <option value="">– keine Mail senden –</option>
                    <?php foreach ($postfaecher as $pf): ?>
                    <option value="<?php echo (int)$pf['id']; ?>" <?php echo $gewaehlt === (int)$pf['id'] ? 'selected' : ''; ?>><?php echo $h($pf['bezeichnung'] . ' <' . $pf['email'] . '>'); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?php echo $postfaecher ? 'Funktions-Postfach aus dem E-Mail-Programm, über das gesendet wird.' : 'Noch kein Funktions-Postfach vorhanden – bitte im E-Mail-Programm anlegen.'; ?></div></div>
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

<?php include 'includes/footer.php'; ?>
