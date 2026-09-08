<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('noten', 'lesen');

$db          = Database::getInstance();
$benutzerId  = Session::getUserId();
$formationId = Session::getFormationId();

// Notenbuch löschen
if (isset($_GET['delete']) && (int)$_GET['delete'] > 0) {
    $buchId = (int)$_GET['delete'];
    $buch   = $db->fetchOne("SELECT * FROM notenbucher WHERE id = ?", [$buchId]);
    if ($buch && ($buch['benutzer_id'] == $benutzerId || Session::isAdmin())) {
        $db->execute("DELETE FROM notenbucher WHERE id = ?", [$buchId]);
        Session::setFlashMessage('success', 'Notenbuch gelöscht');
    } else {
        Session::setFlashMessage('danger', 'Kein Zugriff');
    }
    header('Location: notenbucher.php');
    exit;
}

$geteiltCond   = Session::isAdmin()
    ? "OR nb.typ = 'geteilt'"
    : "OR (nb.typ = 'geteilt' AND nb.formation_id = ?)";
$geteiltParams = Session::isAdmin()
    ? [$benutzerId]
    : [$benutzerId, $formationId ?: 0];

$notenbucher = $db->fetchAll("
    SELECT nb.*,
           COALESCE(CONCAT(m.vorname, ' ', m.nachname), b.benutzername) as ersteller_name,
           (SELECT COUNT(*) FROM notenbuch_noten WHERE notenbuch_id = nb.id) as anzahl_noten,
           f.name as formation_name
    FROM notenbucher nb
    JOIN benutzer b ON nb.benutzer_id = b.id
    LEFT JOIN mitglieder m ON (m.id = b.mitglied_id OR (b.mitglied_id IS NULL AND m.benutzer_id = b.id))
    LEFT JOIN formationen f ON nb.formation_id = f.id
    WHERE nb.benutzer_id = ? {$geteiltCond}
    ORDER BY nb.typ ASC, nb.name
", $geteiltParams);

$eigene   = array_filter($notenbucher, fn($b) => $b['benutzer_id'] == $benutzerId);
$geteilte = array_filter($notenbucher, fn($b) => $b['benutzer_id'] != $benutzerId && $b['typ'] === 'geteilt');

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-journals"></i> Notenbücher</h1>
    <a href="notenbuch_bearbeiten.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Neues Notenbuch
    </a>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>Notenbücher</strong> fassen Noten zu Mappen zusammen – z.B. „Hauptmappe", „Weihnachtskonzert" oder eine persönliche Übungsmappe.
    Geteilte Bücher sind für alle Mitglieder der Formation sichtbar.
</div>

<?php if (empty($notenbucher)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-journals" style="font-size:48px;opacity:0.2"></i>
        <p class="mt-3">Noch keine Notenbücher vorhanden.</p>
        <a href="notenbuch_bearbeiten.php" class="btn btn-primary">Erstes Notenbuch anlegen</a>
    </div>
</div>
<?php else: ?>

<?php if (!empty($eigene)): ?>
<h5 class="text-muted mb-2"><i class="bi bi-person"></i> Meine Bücher</h5>
<div class="row g-3 mb-4">
    <?php foreach ($eigene as $buch): ?>
    <div class="col-md-4 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="fw-semibold"><?= htmlspecialchars($buch['name']) ?></div>
                    <?php if ($buch['typ'] === 'geteilt'): ?>
                    <span class="badge bg-primary flex-shrink-0">Geteilt</span>
                    <?php else: ?>
                    <span class="badge bg-secondary flex-shrink-0">Privat</span>
                    <?php endif; ?>
                </div>
                <?php if ($buch['beschreibung']): ?>
                <div class="text-muted mt-1" style="font-size:12px"><?= htmlspecialchars($buch['beschreibung']) ?></div>
                <?php endif; ?>
                <div class="mt-2 d-flex gap-1 flex-wrap">
                    <span class="badge bg-light text-dark border"><?= (int)$buch['anzahl_noten'] ?> Stück(e)</span>
                    <?php if ($buch['typ'] === 'geteilt' && $buch['formation_name']): ?>
                    <span class="badge bg-light text-dark border"><i class="bi bi-people"></i> <?= htmlspecialchars($buch['formation_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="notenbuch_bearbeiten.php?id=<?= $buch['id'] ?>" class="btn btn-sm btn-primary flex-fill">
                    <i class="bi bi-pencil"></i> Bearbeiten
                </a>
                <a href="?delete=<?= $buch['id'] ?>" class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Notenbuch wirklich löschen?')">
                    <i class="bi bi-trash"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($geteilte)): ?>
<h5 class="text-muted mb-2"><i class="bi bi-people"></i> Geteilte Bücher</h5>
<div class="row g-3">
    <?php foreach ($geteilte as $buch): ?>
    <div class="col-md-4 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="fw-semibold"><?= htmlspecialchars($buch['name']) ?></div>
                    <span class="badge bg-primary flex-shrink-0">Geteilt</span>
                </div>
                <?php if ($buch['beschreibung']): ?>
                <div class="text-muted mt-1" style="font-size:12px"><?= htmlspecialchars($buch['beschreibung']) ?></div>
                <?php endif; ?>
                <div class="mt-2 d-flex gap-1 flex-wrap">
                    <span class="badge bg-light text-dark border"><?= (int)$buch['anzahl_noten'] ?> Stück(e)</span>
                    <span class="badge bg-light text-dark border"><i class="bi bi-person"></i> <?= htmlspecialchars($buch['ersteller_name']) ?></span>
                </div>
            </div>
            <?php if (Session::checkPermission('noten', 'schreiben')): ?>
            <div class="card-footer d-flex gap-2">
                <a href="notenbuch_bearbeiten.php?id=<?= $buch['id'] ?>" class="btn btn-sm btn-primary flex-fill">
                    <i class="bi bi-pencil"></i> Bearbeiten
                </a>
                <?php if (Session::isAdmin()): ?>
                <a href="?delete=<?= $buch['id'] ?>" class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('Notenbuch wirklich löschen?')">
                    <i class="bi bi-trash"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
