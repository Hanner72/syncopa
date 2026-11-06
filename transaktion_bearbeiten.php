<?php
// transaktion_bearbeiten.php – Buchung im Kassabuch anlegen oder bearbeiten (Einnahme, Ausgabe, Umbuchung, Beleg)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'schreiben');

$id = (int)($_GET['id'] ?? 0);
$buchung = $id ? Kassabuch::buchung($id) : null;
if ($id && !$buchung) {
    Session::setFlashMessage('danger', 'Buchung nicht gefunden.');
    header('Location: finanzen.php');
    exit;
}
$jahrStart = (int)($_GET['jahr'] ?? date('Y'));
$vorschlagDatum = $jahrStart == date('Y') ? date('Y-m-d') : "$jahrStart-12-31";
$b = $buchung ?: ['typ' => $_GET['typ'] ?? 'ausgabe', 'datum' => $vorschlagDatum, 'betrag' => '', 'konto_id' => (int)($_GET['konto'] ?? 0) ?: Kassabuch::standardKonto(false),
                  'gegenkonto_id' => null, 'kategorie_id' => null, 'beschreibung' => '', 'beleg_nummer' => '', 'zahlungsart' => ''];
$gesperrt = $buchung && Kassabuch::istGesperrt((int)substr($buchung['datum'], 0, 4));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$gesperrt) {
    $b = array_merge($b, array_intersect_key($_POST, array_flip(['typ', 'datum', 'betrag', 'konto_id', 'gegenkonto_id', 'kategorie_id', 'beschreibung', 'beleg_nummer', 'zahlungsart'])));
    try {
        if (!empty($_FILES['beleg'])) Kassabuch::belegPruefen($_FILES['beleg']); // vor dem Speichern prüfen, damit keine halbe Buchung entsteht
        $neueId = Kassabuch::speichern($b, $id ?: null);
        if (!empty($_POST['beleg_entfernen']) && $buchung) Kassabuch::belegEntfernen($buchung);
        if (!empty($_FILES['beleg'])) Kassabuch::belegHochladen($neueId, $_FILES['beleg']);
        $gespeichert = Kassabuch::buchung($neueId);
        Session::setFlashMessage('success', 'Buchung ' . $gespeichert['beleg_nummer'] . ($id ? ' gespeichert.' : ' angelegt.'));
        $ziel = 'finanzen.php?jahr=' . substr($gespeichert['datum'], 0, 4);
        if (!empty($_POST['weitere'])) $ziel = 'transaktion_bearbeiten.php?jahr=' . substr($gespeichert['datum'], 0, 4) . '&typ=' . $gespeichert['typ'] . '&konto=' . $gespeichert['konto_id'];
        header('Location: ' . $ziel);
        exit;
    } catch (RuntimeException $e) {
        $fehler = $e->getMessage();
    }
}

$konten = Kassabuch::konten(false);
$kategorien = Kassabuch::kategorien(null, true);
$vorschauNummer = $buchung['beleg_nummer'] ?? Kassabuch::naechsteBelegnummer((int)substr((string)$b['datum'], 0, 4));

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0"><i class="bi bi-journal-plus"></i> <?php echo $id ? 'Buchung bearbeiten' : 'Neue Buchung'; ?></h1>
    <a href="finanzen.php?jahr=<?php echo substr((string)$b['datum'], 0, 4); ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (!empty($fehler)): ?><div class="alert alert-danger alert-permanent"><?php echo htmlspecialchars($fehler); ?></div><?php endif; ?>
<?php if ($gesperrt): ?><div class="alert alert-secondary alert-permanent"><i class="bi bi-lock-fill"></i> Diese Buchung gehört zu einem abgeschlossenen Jahr und kann nicht geändert werden.</div><?php endif; ?>
<?php if ($buchung && ($buchung['beitrag_id'] || $buchung['fest_id'])): ?>
<div class="alert alert-info alert-permanent small"><i class="bi bi-link-45deg"></i> Diese Buchung wurde automatisch erzeugt (<?php echo $buchung['beitrag_id'] ? 'Mitgliedsbeitrag' : 'Fest-Abrechnung'; ?>). Änderungen am Betrag besser dort vornehmen.</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<fieldset <?php echo $gesperrt ? 'disabled' : ''; ?>>
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-body">
                <div class="btn-group w-100 mb-3" role="group" aria-label="Art der Buchung">
                    <?php foreach (['einnahme' => ['Einnahme', 'success', 'bi-arrow-down-left-circle'], 'ausgabe' => ['Ausgabe', 'danger', 'bi-arrow-up-right-circle'], 'umbuchung' => ['Umbuchung', 'secondary', 'bi-arrow-left-right']] as $tv => [$tl, $tf, $ti]): ?>
                    <input type="radio" class="btn-check" name="typ" id="typ-<?php echo $tv; ?>" value="<?php echo $tv; ?>" <?php echo $b['typ'] === $tv ? 'checked' : ''; ?>>
                    <label class="btn btn-outline-<?php echo $tf; ?>" for="typ-<?php echo $tv; ?>"><i class="bi <?php echo $ti; ?>"></i> <?php echo $tl; ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="datum" class="form-label">Datum *</label>
                        <input type="date" class="form-control" id="datum" name="datum" value="<?php echo htmlspecialchars((string)$b['datum']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="betrag" class="form-label">Betrag (€) *</label>
                        <input type="number" class="form-control" id="betrag" name="betrag" step="0.01" min="0.01" value="<?php echo htmlspecialchars((string)$b['betrag']); ?>" required autofocus>
                    </div>
                    <div class="col-md-4">
                        <label for="beleg_nummer" class="form-label">Belegnummer</label>
                        <input type="text" class="form-control" id="beleg_nummer" name="beleg_nummer" maxlength="50" value="<?php echo htmlspecialchars((string)($b['beleg_nummer'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($vorschauNummer); ?>">
                        <div class="form-text">Leer lassen für die nächste Nummer.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="konto_id" class="form-label" id="konto-label"><?php echo $b['typ'] === 'umbuchung' ? 'Von Konto *' : 'Konto *'; ?></label>
                        <select class="form-select" id="konto_id" name="konto_id" required>
                            <?php foreach ($konten as $k): if (!$k['aktiv'] && (int)$k['id'] !== (int)$b['konto_id']) continue; ?>
                            <option value="<?php echo $k['id']; ?>" <?php echo (int)$k['id'] === (int)$b['konto_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($k['name']); ?> (<?php echo Kassabuch::ARTEN[$k['art']]; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6" id="feld-gegenkonto" <?php echo $b['typ'] === 'umbuchung' ? '' : 'hidden'; ?>>
                        <label for="gegenkonto_id" class="form-label">Nach Konto *</label>
                        <select class="form-select" id="gegenkonto_id" name="gegenkonto_id">
                            <?php foreach ($konten as $k): if (!$k['aktiv'] && (int)$k['id'] !== (int)$b['gegenkonto_id']) continue; ?>
                            <option value="<?php echo $k['id']; ?>" <?php echo (int)$k['id'] === (int)$b['gegenkonto_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($k['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">z. B. Bargeld aus der Handkassa aufs Girokonto einzahlen.</div>
                    </div>
                    <div class="col-md-6" id="feld-kategorie" <?php echo $b['typ'] === 'umbuchung' ? 'hidden' : ''; ?>>
                        <label for="kategorie_id" class="form-label">Kategorie</label>
                        <select class="form-select" id="kategorie_id" name="kategorie_id">
                            <option value="">– ohne Kategorie –</option>
                            <?php foreach ($kategorien as $c): ?>
                            <option value="<?php echo $c['id']; ?>" data-typ="<?php echo $c['typ']; ?>" <?php echo (int)$c['id'] === (int)$b['kategorie_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="beschreibung" class="form-label">Buchungstext</label>
                        <input type="text" class="form-control" id="beschreibung" name="beschreibung" maxlength="250" value="<?php echo htmlspecialchars((string)($b['beschreibung'] ?? '')); ?>" placeholder="z. B. Rechnung Musikhaus Pichler – Reparatur Tuba">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-paperclip"></i> Beleg</div>
            <div class="card-body">
                <?php if ($buchung && $buchung['beleg_datei']): ?>
                <div class="mb-2 d-flex align-items-center gap-2">
                    <a href="finanz_beleg.php?id=<?php echo $buchung['id']; ?>" target="_blank"><i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($buchung['beleg_name'] ?? 'Beleg'); ?></a>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="beleg_entfernen" value="1" id="beleg_entfernen">
                    <label class="form-check-label small" for="beleg_entfernen">Beleg entfernen</label>
                </div>
                <?php endif; ?>
                <input type="file" class="form-control" name="beleg" accept=".pdf,.jpg,.jpeg,.png,.webp,image/*,application/pdf" capture="environment">
                <div class="form-text">Rechnung oder Quittung als PDF oder Foto (max. 10 MB). Am Handy kann direkt fotografiert werden.</div>
            </div>
        </div>
        <div class="card">
            <div class="card-body small text-muted">
                <i class="bi bi-info-circle"></i> Bei einer <strong>Umbuchung</strong> wird Geld zwischen zwei eigenen Konten verschoben. Sie zählt nicht als Einnahme oder Ausgabe.
            </div>
        </div>
    </div>
</div>
<div class="d-flex justify-content-between page-actions-sticky">
    <a href="finanzen.php?jahr=<?php echo substr((string)$b['datum'], 0, 4); ?>" class="btn btn-secondary"><i class="bi bi-x"></i> Abbrechen</a>
    <div class="d-flex gap-2">
        <?php if (!$id): ?><button type="submit" name="weitere" value="1" class="btn btn-outline-primary"><i class="bi bi-plus-lg"></i> Speichern und nächste</button><?php endif; ?>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Speichern</button>
    </div>
</div>
</fieldset>
</form>

<?php include 'includes/footer.php'; ?>
<script>
(function () {
    var radios = document.querySelectorAll('input[name="typ"]'), kat = document.getElementById('kategorie_id');
    function anpassen() {
        var typ = document.querySelector('input[name="typ"]:checked').value, umb = typ === 'umbuchung';
        document.getElementById('feld-gegenkonto').hidden = !umb;
        document.getElementById('feld-kategorie').hidden = umb;
        document.getElementById('konto-label').textContent = umb ? 'Von Konto *' : 'Konto *';
        // Nur passende Kategorien anbieten
        [].forEach.call(kat.options, function (o) { if (o.dataset.typ) o.hidden = !(o.dataset.typ === 'beide' || o.dataset.typ === typ); });
        if (kat.selectedOptions[0] && kat.selectedOptions[0].hidden) kat.value = '';
    }
    [].forEach.call(radios, function (r) { r.addEventListener('change', anpassen); });
    anpassen();
})();
</script>
