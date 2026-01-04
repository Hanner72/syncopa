<?php
// ausrueckung_detail.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('ausrueckungen', 'lesen');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: ausrueckungen.php');
    exit;
}

$ausrueckungObj = new Ausrueckung();
$ausrueckung = $ausrueckungObj->getById($id);

if (!$ausrueckung) {
    Session::setFlashMessage('danger', 'Ausrückung nicht gefunden');
    header('Location: ausrueckungen.php');
    exit;
}

// Anwesenheitsliste
$anwesenheit = $ausrueckungObj->getAnwesenheit($id);

// Noten für diese Ausrückung
$noten = $ausrueckungObj->getNoten($id);

// Selbst-Anmeldung für normale Mitglieder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['self_anmeldung'])) {
    $currentUserId = Session::getUserId();
    
    // Mitglied-ID des aktuellen Benutzers ermitteln
    $db = Database::getInstance();
    $benutzer = $db->fetchOne("SELECT mitglied_id FROM benutzer WHERE id = ?", [$currentUserId]);
    
    if ($benutzer && $benutzer['mitglied_id']) {
        $mitgliedId = $benutzer['mitglied_id'];
        $status = $_POST['status'] ?? 'ungewiss';
        $grund = $_POST['grund'] ?? null;
        
        $ausrueckungObj->setAnwesenheit($id, $mitgliedId, $status, $grund);
        Session::setFlashMessage('success', 'Deine Anmeldung wurde gespeichert');
        header('Location: ausrueckung_detail.php?id=' . $id);
        exit;
    } else {
        Session::setFlashMessage('danger', 'Kein Mitgliedsprofil zugeordnet');
    }
}

// Anwesenheit aktualisieren (für Admins/Schreibberechtigte)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_anwesenheit'])) {
    foreach ($_POST['anwesenheit'] as $mitgliedId => $status) {
        $grund = $_POST['grund'][$mitgliedId] ?? null;
        $ausrueckungObj->setAnwesenheit($id, $mitgliedId, $status, $grund);
    }
    Session::setFlashMessage('success', 'Anwesenheit aktualisiert');
    header('Location: ausrueckung_detail.php?id=' . $id);
    exit;
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-flag"></i> <?php echo htmlspecialchars($ausrueckung['titel']); ?>
    </h1>
    <div>
        <a href="ausrueckungen.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Zurück
        </a>
        <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
        <a href="ausrueckung_bearbeiten.php?id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Bearbeiten
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Details -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Details</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Typ:</dt>
                    <dd class="col-sm-8">
                        <?php
                        $typeColors = [
                            'Probe' => 'secondary',
                            'Konzert' => 'primary',
                            'Ausrückung' => 'success',
                            'Fest' => 'warning',
                            'Wertung' => 'danger',
                            'Sonstiges' => 'info'
                        ];
                        $color = $typeColors[$ausrueckung['typ']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $color; ?>">
                            <?php echo htmlspecialchars($ausrueckung['typ']); ?>
                        </span>
                    </dd>
                    
                    <dt class="col-sm-4">Status:</dt>
                    <dd class="col-sm-8">
                        <?php
                        $statusColors = ['geplant' => 'warning', 'bestaetigt' => 'success', 'abgesagt' => 'danger'];
                        $statusText = ['geplant' => 'Geplant', 'bestaetigt' => 'Bestätigt', 'abgesagt' => 'Abgesagt'];
                        ?>
                        <span class="badge bg-<?php echo $statusColors[$ausrueckung['status']]; ?>">
                            <?php echo $statusText[$ausrueckung['status']]; ?>
                        </span>
                    </dd>
                    
                    <dt class="col-sm-4">Datum:</dt>
                    <dd class="col-sm-8">
                        <?php echo date('d.m.Y', strtotime($ausrueckung['start_datum'])); ?>
                    </dd>
                    
                    <dt class="col-sm-4">Zeit:</dt>
                    <dd class="col-sm-8">
                        <?php echo date('H:i', strtotime($ausrueckung['start_datum'])); ?> Uhr
                        <?php if ($ausrueckung['ende_datum']): ?>
                        - <?php echo date('H:i', strtotime($ausrueckung['ende_datum'])); ?> Uhr
                        <?php endif; ?>
                    </dd>
                    
                    <?php if ($ausrueckung['ort']): ?>
                    <dt class="col-sm-4">Ort:</dt>
                    <dd class="col-sm-8">
                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($ausrueckung['ort']); ?>
                    </dd>
                    <?php endif; ?>
                    
                    <?php if ($ausrueckung['adresse']): ?>
                    <dt class="col-sm-4">Adresse:</dt>
                    <dd class="col-sm-8">
                        <?php echo nl2br(htmlspecialchars($ausrueckung['adresse'])); ?>
                    </dd>
                    <?php endif; ?>
                    
                    <?php if ($ausrueckung['treffpunkt']): ?>
                    <dt class="col-sm-4">Treffpunkt:</dt>
                    <dd class="col-sm-8">
                        <?php echo htmlspecialchars($ausrueckung['treffpunkt']); ?>
                        <?php if ($ausrueckung['treffpunkt_zeit']): ?>
                        <br><small class="text-muted"><?php echo date('H:i', strtotime($ausrueckung['treffpunkt_zeit'])); ?> Uhr</small>
                        <?php endif; ?>
                    </dd>
                    <?php endif; ?>
                    
                    <dt class="col-sm-4">Uniform:</dt>
                    <dd class="col-sm-8">
                        <?php if ($ausrueckung['uniform']): ?>
                        <span class="text-success"><i class="bi bi-check-circle"></i> Ja</span>
                        <?php else: ?>
                        <span class="text-muted"><i class="bi bi-x-circle"></i> Nein</span>
                        <?php endif; ?>
                    </dd>
                </dl>
                
                <?php if ($ausrueckung['beschreibung']): ?>
                <hr>
                <h6>Beschreibung:</h6>
                <p><?php echo nl2br(htmlspecialchars($ausrueckung['beschreibung'])); ?></p>
                <?php endif; ?>
                
                <?php if ($ausrueckung['notizen']): ?>
                <hr>
                <h6>Notizen (intern):</h6>
                <p class="text-muted"><small><?php echo nl2br(htmlspecialchars($ausrueckung['notizen'])); ?></small></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Noten/Programm -->
        <?php if (!empty($noten)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Programm</h5>
            </div>
            <div class="card-body">
                <ol class="list-group list-group-numbered">
                    <?php foreach ($noten as $note): ?>
                    <li class="list-group-item">
                        <strong><?php echo htmlspecialchars($note['titel']); ?></strong>
                        <?php if ($note['komponist']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($note['komponist']); ?></small>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Anwesenheitsliste -->
    <div class="col-lg-8">
        <?php
        // Prüfen ob aktueller Benutzer ein Mitglied ist
        $currentUserId = Session::getUserId();
        $db = Database::getInstance();
        $currentBenutzer = $db->fetchOne("SELECT mitglied_id FROM benutzer WHERE id = ?", [$currentUserId]);
        
        if ($currentBenutzer && $currentBenutzer['mitglied_id']) {
            // Aktuellen Status des Mitglieds finden
            $currentMitgliedId = $currentBenutzer['mitglied_id'];
            $meinStatus = array_filter($anwesenheit, fn($a) => $a['mitglied_id'] == $currentMitgliedId);
            $meinStatus = !empty($meinStatus) ? reset($meinStatus) : null;
        ?>
        
        <!-- Selbst-Anmeldung für das eingeloggte Mitglied -->
        <div class="card mb-3 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-person-check"></i> Meine Anmeldung</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Ich nehme teil:</label>
                            <select class="form-select" name="status" required>
                                <option value="zugesagt" <?php echo ($meinStatus['status'] ?? '') === 'zugesagt' ? 'selected' : ''; ?>>
                                    ✓ Ja, ich komme
                                </option>
                                <option value="abgesagt" <?php echo ($meinStatus['status'] ?? '') === 'abgesagt' ? 'selected' : ''; ?>>
                                    ✗ Nein, ich kann nicht
                                </option>
                                <option value="ungewiss" <?php echo ($meinStatus['status'] ?? 'ungewiss') === 'ungewiss' ? 'selected' : ''; ?>>
                                    ? Ungewiss
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-5">
                            <label class="form-label">Grund (optional):</label>
                            <input type="text" class="form-control" name="grund" 
                                   value="<?php echo htmlspecialchars($meinStatus['grund'] ?? ''); ?>"
                                   placeholder="z.B. Urlaub, Krankheit...">
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" name="self_anmeldung" class="btn btn-primary w-100">
                                <i class="bi bi-save"></i> Speichern
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($meinStatus && $meinStatus['gemeldet_am']): ?>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-clock"></i> Zuletzt geändert: 
                            <?php echo date('d.m.Y H:i', strtotime($meinStatus['gemeldet_am'])); ?> Uhr
                        </small>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php } ?>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Anwesenheit</h5>
                <div>
                    <?php
                    $zugesagt = count(array_filter($anwesenheit, fn($a) => $a['status'] === 'zugesagt'));
                    $abgesagt = count(array_filter($anwesenheit, fn($a) => $a['status'] === 'abgesagt'));
                    $ungewiss = count(array_filter($anwesenheit, fn($a) => in_array($a['status'], ['ungewiss', 'keine_antwort'])));
                    ?>
                    <span class="badge bg-success" title="Zugesagt">✓ <?php echo $zugesagt; ?></span>
                    <span class="badge bg-danger" title="Abgesagt">✗ <?php echo $abgesagt; ?></span>
                    <span class="badge bg-warning text-dark" title="Ungewiss / Keine Antwort">? <?php echo $ungewiss; ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
                <form method="POST">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Mitglied</th>
                                    <th>Status</th>
                                    <th>Grund</th>
                                    <th>Gemeldet am</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anwesenheit as $a): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($a['nachname'] . ' ' . $a['vorname']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($a['mitgliedsnummer']); ?></small>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="anwesenheit[<?php echo $a['mitglied_id']; ?>]">
                                            <option value="keine_antwort" <?php echo $a['status'] === 'keine_antwort' ? 'selected' : ''; ?>>Keine Antwort</option>
                                            <option value="zugesagt" <?php echo $a['status'] === 'zugesagt' ? 'selected' : ''; ?>>✓ Zugesagt</option>
                                            <option value="ungewiss" <?php echo $a['status'] === 'ungewiss' ? 'selected' : ''; ?>>? Ungewiss</option>
                                            <option value="abgesagt" <?php echo $a['status'] === 'abgesagt' ? 'selected' : ''; ?>>✗ Abgesagt</option>
                                            <option value="anwesend" <?php echo $a['status'] === 'anwesend' ? 'selected' : ''; ?>>Anwesend (nachträglich)</option>
                                            <option value="abwesend" <?php echo $a['status'] === 'abwesend' ? 'selected' : ''; ?>>Abwesend (nachträglich)</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" 
                                               name="grund[<?php echo $a['mitglied_id']; ?>]"
                                               value="<?php echo htmlspecialchars($a['grund'] ?? ''); ?>"
                                               placeholder="Optional">
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo $a['gemeldet_am'] ? date('d.m.Y H:i', strtotime($a['gemeldet_am'])) : '-'; ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" name="update_anwesenheit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Anwesenheit speichern
                    </button>
                </form>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Mitglied</th>
                                <th>Status</th>
                                <th>Grund</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($anwesenheit as $a): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['nachname'] . ' ' . $a['vorname']); ?></td>
                                <td>
                                    <?php
                                    $statusBadges = [
                                        'zugesagt' => 'success',
                                        'ungewiss' => 'warning',
                                        'abgesagt' => 'danger',
                                        'anwesend' => 'success',
                                        'abwesend' => 'danger',
                                        'keine_antwort' => 'secondary'
                                    ];
                                    $statusLabels = [
                                        'zugesagt' => '✓ Zugesagt',
                                        'ungewiss' => '? Ungewiss',
                                        'abgesagt' => '✗ Abgesagt',
                                        'anwesend' => 'Anwesend',
                                        'abwesend' => 'Abwesend',
                                        'keine_antwort' => 'Keine Antwort'
                                    ];
                                    $badge = $statusBadges[$a['status']] ?? 'secondary';
                                    $label = $statusLabels[$a['status']] ?? ucfirst(str_replace('_', ' ', $a['status']));
                                    ?>
                                    <span class="badge bg-<?php echo $badge; ?>"><?php echo $label; ?></span>
                                </td>
                                <td><small><?php echo htmlspecialchars($a['grund'] ?? '-'); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
