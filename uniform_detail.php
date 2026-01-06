<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'lesen');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: uniformen.php');
    exit;
}

$uniformObj = new Uniform();
$uniform = $uniformObj->getById($id);

if (!$uniform) {
    Session::setFlashMessage('danger', 'Uniformteil nicht gefunden');
    header('Location: uniformen.php');
    exit;
}

$historie = $uniformObj->getAusgabeHistorie($id);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-person-badge"></i> 
        <?php echo htmlspecialchars($uniform['inventar_nummer']); ?>
    </h1>
    <div>
        <?php if (Session::checkPermission('uniformen', 'schreiben')): ?>
        <a href="uniform_bearbeiten.php?id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Bearbeiten
        </a>
        <?php endif; ?>
        <a href="uniformen.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Zurück
        </a>
    </div>
</div>

<div class="row">
    <!-- Hauptinformationen -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Kategorie:</th>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($uniform['kategorie_name'] ?? 'Ohne Kategorie'); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Bezeichnung:</th>
                                <td><?php echo htmlspecialchars($uniform['bezeichnung'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Größe:</th>
                                <td><strong><?php echo htmlspecialchars($uniform['groesse'] ?? '-'); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Farbe:</th>
                                <td><?php echo htmlspecialchars($uniform['farbe'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Zustand:</th>
                                <td>
                                    <?php
                                    $zustandColors = ['sehr gut' => 'success', 'gut' => 'info', 'befriedigend' => 'warning', 'schlecht' => 'danger'];
                                    $color = $zustandColors[$uniform['zustand']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($uniform['zustand'] ?? 'gut'); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="50%">Anschaffung:</th>
                                <td><?php echo $uniform['anschaffungsdatum'] ? date('d.m.Y', strtotime($uniform['anschaffungsdatum'])) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Anschaffungspreis:</th>
                                <td><?php echo $uniform['anschaffungspreis'] ? number_format($uniform['anschaffungspreis'], 2, ',', '.') . ' €' : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Standort:</th>
                                <td><?php echo htmlspecialchars($uniform['standort'] ?? '-'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($uniform['notizen']): ?>
                <hr>
                <h6>Notizen</h6>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($uniform['notizen'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ausgabe-Historie -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Ausgabe-Historie</h5>
            </div>
            <div class="card-body">
                <?php if (empty($historie)): ?>
                <p class="text-muted text-center mb-0">Noch keine Ausgaben erfasst</p>
                <?php else: ?>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Mitglied</th>
                            <th>Ausgabe</th>
                            <th>Rückgabe</th>
                            <th>Zustand</th>
                            <th>Bemerkungen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historie as $h): ?>
                        <tr>
                            <td>
                                <a href="mitglied_detail.php?id=<?php echo $h['mitglied_id']; ?>">
                                    <?php echo htmlspecialchars($h['nachname'] . ' ' . $h['vorname']); ?>
                                </a>
                            </td>
                            <td><?php echo date('d.m.Y', strtotime($h['ausgabe_datum'])); ?></td>
                            <td>
                                <?php if ($h['rueckgabe_datum']): ?>
                                <?php echo date('d.m.Y', strtotime($h['rueckgabe_datum'])); ?>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">Aktuell</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?php echo $h['zustand_bei_ausgabe'] ?? '-'; ?>
                                    <?php if ($h['zustand_bei_rueckgabe']): ?>
                                    → <?php echo $h['zustand_bei_rueckgabe']; ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td><small><?php echo htmlspecialchars($h['bemerkungen'] ?? '-'); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Seitenleiste -->
    <div class="col-lg-4">
        <!-- Status -->
        <div class="card mb-3 <?php echo $uniform['mitglied_id'] ? 'border-warning' : 'border-success'; ?>">
            <div class="card-header <?php echo $uniform['mitglied_id'] ? 'bg-warning text-dark' : 'bg-success text-white'; ?>">
                <h5 class="mb-0">
                    <i class="bi bi-<?php echo $uniform['mitglied_id'] ? 'person-check' : 'box-seam'; ?>"></i>
                    Status
                </h5>
            </div>
            <div class="card-body text-center">
                <?php if ($uniform['mitglied_id']): ?>
                <h5>Ausgegeben an</h5>
                <p class="h4 mb-2">
                    <a href="mitglied_detail.php?id=<?php echo $uniform['mitglied_id']; ?>">
                        <?php echo htmlspecialchars($uniform['vorname'] . ' ' . $uniform['nachname']); ?>
                    </a>
                </p>
                <p class="text-muted">seit <?php echo date('d.m.Y', strtotime($uniform['ausgabe_datum'])); ?></p>
                
                <?php if (Session::checkPermission('uniformen', 'schreiben')): ?>
                <form method="POST" action="uniform_zuruecknehmen.php" class="mt-3">
                    <input type="hidden" name="uniform_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="redirect" value="detail">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-box-arrow-in-down"></i> Zurücknehmen
                    </button>
                </form>
                <?php endif; ?>
                
                <?php else: ?>
                <h5 class="text-success mb-3">
                    <i class="bi bi-check-circle fs-1"></i><br>
                    Verfügbar
                </h5>
                
                <?php if (Session::checkPermission('uniformen', 'schreiben')): ?>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#ausgebenModal">
                    <i class="bi bi-box-arrow-up"></i> Ausgeben
                </button>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (Session::checkPermission('uniformen', 'loeschen')): ?>
        <div class="card border-danger">
            <div class="card-body">
                <h6 class="text-danger"><i class="bi bi-exclamation-triangle"></i> Gefahrenzone</h6>
                <p class="small text-muted">Das Löschen kann nicht rückgängig gemacht werden.</p>
                <form method="POST" action="uniform_loeschen.php" onsubmit="return confirm('Uniformteil wirklich löschen?');">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash"></i> Uniformteil löschen
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Uniform ausgeben -->
<?php if (!$uniform['mitglied_id'] && Session::checkPermission('uniformen', 'schreiben')): ?>
<div class="modal fade" id="ausgebenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="uniform_ausgeben.php">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-arrow-up text-warning"></i> Uniform ausgeben</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="uniform_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="redirect" value="detail">
                    
                    <div class="mb-3">
                        <label for="mitglied_id" class="form-label">Ausgeben an <span class="text-danger">*</span></label>
                        <select class="form-select" name="mitglied_id" id="mitglied_id" required>
                            <option value="">-- Mitglied wählen --</option>
                            <?php
                            $db = Database::getInstance();
                            $mitglieder = $db->fetchAll("SELECT id, vorname, nachname, mitgliedsnummer FROM mitglieder WHERE status = 'aktiv' ORDER BY nachname, vorname");
                            foreach ($mitglieder as $m):
                            ?>
                            <option value="<?php echo $m['id']; ?>">
                                <?php echo htmlspecialchars($m['nachname'] . ' ' . $m['vorname'] . ' (' . $m['mitgliedsnummer'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bemerkungen" class="form-label">Bemerkungen</label>
                        <textarea class="form-control" name="bemerkungen" id="bemerkungen" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-box-arrow-up"></i> Ausgeben
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
