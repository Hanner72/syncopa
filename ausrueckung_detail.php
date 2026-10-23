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

// Zugesagte Mitglieder nach Register
$db_top = Database::getInstance();
$zugesagteNachRegister = $db_top->fetchAll(
    "SELECT m.vorname, m.nachname, r.name as register_name, r.sortierung
     FROM anwesenheit a
     JOIN mitglieder m ON a.mitglied_id = m.id
     LEFT JOIN register r ON m.register_id = r.id
     INNER JOIN (
         SELECT mitglied_id, MAX(id) as max_id
         FROM anwesenheit WHERE ausrueckung_id = ?
         GROUP BY mitglied_id
     ) latest ON a.id = latest.max_id AND a.mitglied_id = latest.mitglied_id
     WHERE a.ausrueckung_id = ? AND a.status = 'zugesagt'
     ORDER BY r.sortierung, r.name, m.nachname, m.vorname",
    [$id, $id]
);
// Nach Register gruppieren
$zugesagteGruppiert = [];
foreach ($zugesagteNachRegister as $m) {
    $reg = $m['register_name'] ?? 'Ohne Register';
    $zugesagteGruppiert[$reg][] = $m['vorname'] . ' ' . $m['nachname'];
}

// Abgesagte Mitglieder nach Register
$abgesagteNachRegister = $db_top->fetchAll(
    "SELECT m.vorname, m.nachname, m.id as mitglied_id, r.name as register_name, r.sortierung, a.grund
     FROM anwesenheit a
     JOIN mitglieder m ON a.mitglied_id = m.id
     LEFT JOIN register r ON m.register_id = r.id
     INNER JOIN (
         SELECT mitglied_id, MAX(id) as max_id
         FROM anwesenheit WHERE ausrueckung_id = ?
         GROUP BY mitglied_id
     ) latest ON a.id = latest.max_id AND a.mitglied_id = latest.mitglied_id
     WHERE a.ausrueckung_id = ? AND a.status = 'abgesagt'
     ORDER BY r.sortierung, r.name, m.nachname, m.vorname",
    [$id, $id]
);
$abgesagteGruppiert = [];
foreach ($abgesagteNachRegister as $m) {
    $reg = $m['register_name'] ?? 'Ohne Register';
    $abgesagteGruppiert[$reg][] = ['name' => $m['vorname'] . ' ' . $m['nachname'], 'grund' => $m['grund']];
}

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
        
        <!-- Zugesagte Mitglieder nach Register -->
        <?php if (!empty($zugesagteGruppiert)): ?>
        <div class="card mb-3 border-success">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person-check text-success"></i> Zusagen</h5>
                <span class="badge bg-success"><?php echo count($zugesagteNachRegister); ?></span>
            </div>
            <div class="card-body p-0">
                <?php foreach ($zugesagteGruppiert as $registerName => $mitglieder): ?>
                <div class="px-3 pt-2 pb-1">
                    <small class="text-uppercase text-muted fw-semibold" style="font-size:0.7rem;letter-spacing:.05em;">
                        <?php echo htmlspecialchars($registerName); ?>
                    </small>
                    <ul class="list-unstyled mb-2 mt-1">
                        <?php foreach ($mitglieder as $name): ?>
                        <li class="py-1 border-bottom">
                            <i class="bi bi-check-circle-fill text-success me-1" style="font-size:.8rem;"></i>
                            <small><?php echo htmlspecialchars($name); ?></small>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Abgesagte Mitglieder nach Register -->
        <?php if (!empty($abgesagteGruppiert)): ?>
        <div class="card mb-3 border-danger">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-person-x text-danger"></i> Absagen</h5>
                <span class="badge bg-danger"><?php echo count($abgesagteNachRegister); ?></span>
            </div>
            <div class="card-body p-0">
                <?php foreach ($abgesagteGruppiert as $registerName => $mitglieder): ?>
                <div class="px-3 pt-2 pb-1">
                    <small class="text-uppercase text-muted fw-semibold" style="font-size:0.7rem;letter-spacing:.05em;">
                        <?php echo htmlspecialchars($registerName); ?>
                    </small>
                    <ul class="list-unstyled mb-2 mt-1">
                        <?php foreach ($mitglieder as $m): ?>
                        <li class="py-1 border-bottom">
                            <i class="bi bi-x-circle-fill text-danger me-1" style="font-size:.8rem;"></i>
                            <small><?php echo htmlspecialchars($m['name']); ?></small>
                            <?php if (!empty($m['grund'])): ?>
                            <br><small class="text-muted ms-3"><i class="bi bi-chat-left-text"></i> <?php echo htmlspecialchars($m['grund']); ?></small>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

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
                <?php
                    $msStatus = $meinStatus['status'] ?? null;
                    $abgestimmt = in_array($msStatus, ['zugesagt', 'abgesagt', 'ungewiss']);
                ?>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="btn-group anwesenheit-buttons" data-ausrueckung-id="<?php echo $id; ?>" data-mein-status="<?php echo htmlspecialchars($msStatus ?? ''); ?>">
                        <button type="button"
                            class="btn btn-anwesenheit btn-lg <?php echo $abgestimmt && $msStatus !== 'zugesagt' ? 'btn-outline-success dimmed' : 'btn-success'; ?>"
                            data-status="zugesagt">
                            <i class="bi bi-check-lg"></i> Ja
                        </button>
                        <button type="button"
                            class="btn btn-anwesenheit btn-lg <?php echo $abgestimmt && $msStatus !== 'ungewiss' ? 'btn-outline-warning dimmed' : 'btn-warning'; ?>"
                            data-status="ungewiss">
                            <i class="bi bi-question-lg"></i> Ungewiss
                        </button>
                        <button type="button"
                            class="btn btn-anwesenheit btn-lg <?php echo $abgestimmt && $msStatus !== 'abgesagt' ? 'btn-outline-danger dimmed' : 'btn-danger'; ?>"
                            data-status="abgesagt">
                            <i class="bi bi-x-lg"></i> Nein
                        </button>
                    </div>
                    <?php if (!empty($meinStatus['grund'])): ?>
                    <div class="text-muted">
                        <small><i class="bi bi-chat-left-text"></i> <?php echo htmlspecialchars($meinStatus['grund']); ?></small>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($meinStatus && $meinStatus['gemeldet_am']): ?>
                <div class="mt-2">
                    <small class="text-muted" id="anwesenheit-zeitstempel">
                        <i class="bi bi-clock"></i> Zuletzt geändert:
                        <?php echo date('d.m.Y H:i', strtotime($meinStatus['gemeldet_am'])); ?> Uhr
                    </small>
                </div>
                <?php else: ?>
                <div class="mt-2">
                    <small class="text-muted" id="anwesenheit-zeitstempel"></small>
                </div>
                <?php endif; ?>
                <div class="mt-2" id="anwesenheit-feedback"></div>
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
                    <span class="badge bg-success" title="Zugesagt" data-typ="zugesagt">✓ <?php echo $zugesagt; ?></span>
                    <span class="badge bg-warning text-dark" title="Ungewiss" data-typ="ungewiss">? <?php echo $ungewiss; ?></span>
                    <span class="badge bg-danger" title="Abgesagt" data-typ="abgesagt">✗ <?php echo $abgesagt; ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
                <form method="POST">
                    <div class="table-responsive">
                        <table class="table table-sm" id="anwesenheitAdminTable">
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
                    <table class="table table-sm" id="anwesenheitTable">
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

<style>
.btn-anwesenheit.dimmed {
    opacity: 0.35;
}
.btn-anwesenheit {
    transition: all 0.15s ease;
}
</style>

<script>
$(document).ready(function() {

    // Anwesenheitsliste sortierbar machen
    if ($'#anwesenheitAdminTable').length) {
        $('#anwesenheitAdminTable').DataTable({
            order: [[0, 'asc']],
            pageLength: 25,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json' },
            columnDefs: [{ orderable: false, targets: [1, 2] }]
        });
    }
    if ($('#anwesenheitTable').length) {
        $('#anwesenheitTable').DataTable({
            order: [[1, 'asc']],
            pageLength: 25,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json' }
        });
    }

    const statusMap = {
        'zugesagt': { solid: 'btn-success',  outline: 'btn-outline-success'  },
        'ungewiss': { solid: 'btn-warning',  outline: 'btn-outline-warning'  },
        'abgesagt': { solid: 'btn-danger',   outline: 'btn-outline-danger'   }
    };

    function updateButtons(gruppe, neuerStatus) {
        gruppe.find('.btn-anwesenheit').each(function() {
            const b = $(this);
            const s = b.data('status');
            const colors = statusMap[s];
            b.removeClass('btn-success btn-warning btn-danger btn-outline-success btn-outline-warning btn-outline-danger dimmed');
            if (s === neuerStatus) {
                b.addClass(colors.solid);
            } else {
                b.addClass(colors.outline + ' dimmed');
            }
        });
    }

    function updateZaehlerDetail(alterStatus, neuerStatus) {
        // Badges im Anwesenheit-Karten-Header aktualisieren
        const header = $('.card-header [data-typ]');
        if (!header.length) return;

        if (alterStatus && statusMap[alterStatus]) {
            const altEl = $('[data-typ="' + alterStatus + '"]');
            altEl.each(function() {
                const val = parseInt($(this).text().replace(/[^0-9]/g, '')) || 0;
                if (val > 0) $(this).text($(this).text().replace(/[0-9]+/, val - 1));
            });
        }
        const neuEl = $('[data-typ="' + neuerStatus + '"]');
        neuEl.each(function() {
            const val = parseInt($(this).text().replace(/[^0-9]/g, '')) || 0;
            $(this).text($(this).text().replace(/[0-9]+/, val + 1));
        });
    }

    $(document).on('click', '.btn-anwesenheit', function() {
        const btn = $(this);
        const gruppe = btn.closest('.anwesenheit-buttons');
        const ausrueckungId = gruppe.data('ausrueckung-id');
        const neuerStatus = btn.data('status');
        const alterStatus = gruppe.data('mein-status') || '';
        // Gleicher Status nochmal → nichts tun
        if (neuerStatus === alterStatus) return;

        updateButtons(gruppe, neuerStatus);
        updateZaehlerDetail(alterStatus, neuerStatus);
        gruppe.data('mein-status', neuerStatus);

        fetch('api/anwesenheit_setzen.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'ausrueckung_id=' + ausrueckungId + '&status=' + encodeURIComponent(neuerStatus)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const now = new Date();
                const ts = now.toLocaleDateString('de-AT') + ' ' + now.toLocaleTimeString('de-AT', {hour: '2-digit', minute: '2-digit'});
                $('#anwesenheit-zeitstempel').html('<i class="bi bi-clock"></i> Zuletzt geändert: ' + ts + ' Uhr');
                $('#anwesenheit-feedback').html('<small class="text-success"><i class="bi bi-check-circle"></i> Gespeichert</small>');
                setTimeout(() => $('#anwesenheit-feedback').html(''), 3000);
            } else {
                $('#anwesenheit-feedback').html('<small class="text-danger"><i class="bi bi-exclamation-circle"></i> Fehler: ' + data.message + '</small>');
            }
        })
        .catch(() => {
            $('#anwesenheit-feedback').html('<small class="text-danger"><i class="bi bi-exclamation-circle"></i> Netzwerkfehler</small>');
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
