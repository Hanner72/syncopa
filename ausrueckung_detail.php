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

// Anwesenheitsliste (kommende Ausrückungen vorher mit den aktuellen Mitgliedern abgleichen)
$ausrueckungObj->anwesenheitErgaenzen((int)$id);
$anwesenheit = $ausrueckungObj->getAnwesenheit($id);

// Noten für diese Ausrückung
$noten = $ausrueckungObj->getNoten($id);

// Zu- und Absagen nach Register (Karten werden auch von der Live-Aktualisierung neu geladen)
require_once __DIR__ . '/includes/anwesenheit_register.php';
$zugesagteNachRegister = anwesenheitNachRegister((int)$id, 'zugesagt');
$abgesagteNachRegister = anwesenheitNachRegister((int)$id, 'abgesagt');

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
    Session::requirePermission('ausrueckungen', 'schreiben');
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
        
        <!-- Zu- und Absagen nach Register -->
        <?php echo anwesenheitRegisterKarte('zugesagt', $zugesagteNachRegister); ?>
        <?php echo anwesenheitRegisterKarte('abgesagt', $abgesagteNachRegister); ?>

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
                    $ungewiss = count(array_filter($anwesenheit, fn($a) => $a['status'] === 'ungewiss'));
                    $keineAntwort = count(array_filter($anwesenheit, fn($a) => $a['status'] === 'keine_antwort'));
                    ?>
                    <span class="badge bg-success" title="Zugesagt" data-typ="zugesagt">✓ <?php echo $zugesagt; ?></span>
                    <span class="badge bg-warning text-dark" title="Ungewiss" data-typ="ungewiss">? <?php echo $ungewiss; ?></span>
                    <span class="badge bg-danger" title="Abgesagt" data-typ="abgesagt">✗ <?php echo $abgesagt; ?></span>
                    <span class="badge bg-secondary" title="Keine Antwort" data-typ="keine_antwort">– <?php echo $keineAntwort; ?></span>
                </div>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2 d-flex flex-wrap gap-2 align-items-center">
                    <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
                    <span><i class="bi bi-lightning-charge"></i> Änderungen werden sofort gespeichert.</span>
                    <?php endif; ?>
                    <span id="anwLive" title="Die Liste aktualisiert sich automatisch"><span class="anw-live-punkt"></span> Live<span id="anwLiveZeit"></span></span>
                    <a href="ausrueckung_detail.php?id=<?php echo (int)$id; ?>" id="anwNeuLaden" class="d-none">Neue Meldungen – Seite neu laden</a>
                </p>
                <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
                <form method="POST" id="anwesenheitForm" onsubmit="return false;">
                    <div class="table-responsive">
                        <table class="table table-sm" id="anwesenheitAdminTable">
                            <thead>
                                <tr>
                                    <th>Mitglied</th>
                                    <th>Status / Grund</th>
                                    <th class="d-none d-md-table-cell">Gemeldet am</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($anwesenheit as $a): ?>
                                <tr data-mitglied-id="<?php echo (int)$a['mitglied_id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($a['nachname'] . ' ' . $a['vorname']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($a['mitgliedsnummer']); ?></small>

                                        <small class="text-muted d-md-none anw-zeit-mobil<?php echo $a['gemeldet_am'] ? '' : ' d-none'; ?>"><br><i class="bi bi-clock"></i> <span class="anw-zeit"><?php echo $a['gemeldet_am'] ? date('d.m.Y H:i', strtotime($a['gemeldet_am'])) : ''; ?></span></small>
                                    </td>
                                    <td>
                                        <div class="anw-eingabe">
                                        <select class="form-select form-select-sm anw-status anw-<?php echo htmlspecialchars($a['status']); ?>" name="anwesenheit[<?php echo $a['mitglied_id']; ?>]">
                                            <option value="keine_antwort" <?php echo $a['status'] === 'keine_antwort' ? 'selected' : ''; ?>>Keine Antwort</option>
                                            <option value="zugesagt" <?php echo $a['status'] === 'zugesagt' ? 'selected' : ''; ?>>✓ Zugesagt</option>
                                            <option value="ungewiss" <?php echo $a['status'] === 'ungewiss' ? 'selected' : ''; ?>>? Ungewiss</option>
                                            <option value="abgesagt" <?php echo $a['status'] === 'abgesagt' ? 'selected' : ''; ?>>✗ Abgesagt</option>
                                        </select>
                                        <?php $grundSichtbar = trim((string)($a['grund'] ?? '')) !== '' || $a['status'] === 'abgesagt'; ?>
                                        <input type="text" class="form-control form-control-sm anw-grund<?php echo $grundSichtbar ? '' : ' d-none'; ?>"
                                               name="grund[<?php echo $a['mitglied_id']; ?>]"
                                               value="<?php echo htmlspecialchars($a['grund'] ?? ''); ?>"
                                               placeholder="Grund (optional)">
                                        </div>
                                        <small class="anw-hinweis d-block" aria-live="polite"></small>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <small class="text-muted anw-zeit"><?php echo $a['gemeldet_am'] ? date('d.m.Y H:i', strtotime($a['gemeldet_am'])) : '-'; ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
                            <tr data-mitglied-id="<?php echo (int)$a['mitglied_id']; ?>">
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
                                    <span class="badge bg-<?php echo $badge; ?> anw-badge" data-status="<?php echo htmlspecialchars($a['status']); ?>"><?php echo $label; ?></span>
                                </td>
                                <td><small class="anw-grund-text"><?php echo htmlspecialchars(($a['grund'] ?? '') !== '' ? $a['grund'] : '-'); ?></small></td>
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
/* Anwesenheitsliste: Rand nach gewähltem Status */
/* Status und Grund: am Computer nebeneinander, am Handy untereinander */
.anw-eingabe { display: flex; gap: .5rem; align-items: center; }
.anw-eingabe .anw-status { flex: 0 0 9.5rem; }
.anw-eingabe input { flex: 1 1 auto; min-width: 0; }
@media (max-width: 767.98px) {
    .anw-eingabe { flex-direction: column; align-items: stretch; gap: .35rem; min-width: 10rem; }
    .anw-eingabe .anw-status { flex-basis: auto; }
}
/* Live-Anzeige */
.anw-live-punkt { display: inline-block; width: .5rem; height: .5rem; border-radius: 50%; background: var(--bs-success); margin-right: .15rem; animation: anwPuls 2s infinite; }
#anwLive.offline .anw-live-punkt { background: var(--bs-secondary); animation: none; }
@keyframes anwPuls { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }
tr.anw-neu > td, li.anw-neu { animation: anwNeu 2.5s ease-out; }
@keyframes anwNeu { from { background-color: rgba(var(--bs-info-rgb), .25); } to { background-color: transparent; } }
/* dezenter Farbstreifen links nach gewähltem Status */
.anw-status.anw-zugesagt,
.anw-status.anw-ungewiss,
.anw-status.anw-abgesagt { border-left-width: 4px; }
.anw-status.anw-zugesagt { border-left-color: rgba(var(--bs-success-rgb), .7); }
.anw-status.anw-ungewiss { border-left-color: rgba(var(--bs-warning-rgb), .8); }
.anw-status.anw-abgesagt { border-left-color: rgba(var(--bs-danger-rgb), .7); }
</style>

<script>
// jQuery wird erst im Footer geladen – deshalb auf DOMContentLoaded warten
document.addEventListener('DOMContentLoaded', function() {

    // Anwesenheitsliste: Status und Grund sofort speichern
    const ausrueckungIdListe = <?php echo (int)$id; ?>;

    function zaehlerNeu() {
        const z = { zugesagt: 0, ungewiss: 0, abgesagt: 0, keine_antwort: 0 };
        document.querySelectorAll('.anw-status').forEach(function(s) {
            if (z[s.value] !== undefined) z[s.value]++;
        });
        const zeichen = { zugesagt: '✓', ungewiss: '?', abgesagt: '✗', keine_antwort: '–' };
        Object.keys(z).forEach(function(k) {
            document.querySelectorAll('.card-header [data-typ="' + k + '"]').forEach(function(b) { b.textContent = zeichen[k] + ' ' + z[k]; });
        });
    }

    function hinweis(zeile, text, klasse) {
        const h = zeile.querySelector('.anw-hinweis');
        if (!h) return;
        clearTimeout(h._timer);
        h.className = 'anw-hinweis d-block ' + klasse;
        h.innerHTML = text;
        if (klasse === 'text-success') h._timer = setTimeout(function() { h.innerHTML = ''; }, 2000);
    }

    function zeileSpeichern(zeile) {
        const sel = zeile.querySelector('.anw-status');
        const grund = zeile.querySelector('.anw-grund');
        const daten = new URLSearchParams({
            ausrueckung_id: ausrueckungIdListe,
            mitglied_id: zeile.dataset.mitgliedId,
            status: sel.value,
            grund: grund ? grund.value : ''
        });
        const nr = (zeile._nr = (zeile._nr || 0) + 1);
        zeile._laeuft = true;
        hinweis(zeile, '<i class="bi bi-arrow-repeat"></i> Speichert …', 'text-muted');
        fetch('api/anwesenheit_verwalten.php', { method: 'POST', body: daten })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (nr !== zeile._nr) return; // inzwischen neuere Änderung unterwegs
                zeile._laeuft = false;
                if (d.success) {
                    hinweis(zeile, '<i class="bi bi-check-circle"></i> Gespeichert', 'text-success');
                    liveBald();
                    zeile.querySelectorAll('.anw-zeit').forEach(function(z) { z.textContent = d.gemeldet_am; });
                    zeile.querySelectorAll('.anw-zeit-mobil').forEach(function(z) { z.classList.remove('d-none'); });
                } else {
                    hinweis(zeile, '<i class="bi bi-exclamation-circle"></i> Nicht gespeichert: ' + (d.message || 'Fehler'), 'text-danger');
                }
            })
            .catch(function() {
                if (nr !== zeile._nr) return;
                zeile._laeuft = false;
                hinweis(zeile, '<i class="bi bi-exclamation-circle"></i> Nicht gespeichert (Verbindung)', 'text-danger');
            });
    }

    document.querySelectorAll('.anw-status').forEach(function(sel) {
        sel.addEventListener('change', function() {
            sel.classList.remove('anw-zugesagt', 'anw-ungewiss', 'anw-abgesagt', 'anw-keine_antwort');
            sel.classList.add('anw-' + sel.value);
            // Grund-Feld nur bei Absage oder wenn schon ein Grund eingetragen ist
            const grund = sel.closest('.anw-eingabe')?.querySelector('.anw-grund');
            if (grund) {
                const zeigen = sel.value === 'abgesagt' || grund.value.trim() !== '';
                grund.classList.toggle('d-none', !zeigen);
                if (zeigen && sel.value === 'abgesagt' && grund.value.trim() === '') grund.focus();
            }
            if (grund) { clearTimeout(grund._timer); grund.dataset.gespeichert = grund.value; }
            zaehlerNeu();
            zeileSpeichern(sel.closest('tr'));
        });
    });

    // Grund: kurz nach dem Tippen speichern, spätestens beim Verlassen des Feldes
    document.querySelectorAll('.anw-grund').forEach(function(g) {
        g.dataset.gespeichert = g.value;
        function jetzt() {
            clearTimeout(g._timer);
            if (g.value === g.dataset.gespeichert) return;
            g.dataset.gespeichert = g.value;
            zeileSpeichern(g.closest('tr'));
        }
        g.addEventListener('input', function() {
            clearTimeout(g._timer);
            g._timer = setTimeout(jetzt, 1000);
        });
        g.addEventListener('blur', jetzt);
        g.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); jetzt(); } });
    });

    // Live-Aktualisierung: Zu- und Absagen anderer alle 15 Sekunden nachladen
    const statusText  = { zugesagt: '✓ Zugesagt', ungewiss: '? Ungewiss', abgesagt: '✗ Abgesagt', keine_antwort: 'Keine Antwort', anwesend: 'Anwesend', abwesend: 'Abwesend' };
    const statusFarbe = { zugesagt: 'success', ungewiss: 'warning', abgesagt: 'danger', keine_antwort: 'secondary', anwesend: 'success', abwesend: 'danger' };

    // Zeilen merken, bevor DataTables sie bei Suche/Sortierung aus dem DOM nimmt
    const anwZeilen = new Map();
    document.querySelectorAll('#anwesenheitAdminTable tr[data-mitglied-id], #anwesenheitTable tr[data-mitglied-id]').forEach(function(tr) {
        anwZeilen.set(tr.dataset.mitgliedId, tr);
    });

    function zeileLiveSetzen(zeile, e) {
        const sel = zeile.querySelector('.anw-status');
        if (sel) {
            // Zeile wird gerade bearbeitet oder gespeichert → nicht überschreiben
            const grund = zeile.querySelector('.anw-grund');
            if (zeile._laeuft || zeile.contains(document.activeElement)) return false;
            if (grund && (grund.value !== grund.dataset.gespeichert)) return false;
            const grundNeu = e.grund || '';
            if (sel.value === e.status && (!grund || grund.value === grundNeu)) return false;
            if (sel.querySelector('option[value="' + e.status + '"]')) sel.value = e.status;
            sel.classList.remove('anw-zugesagt', 'anw-ungewiss', 'anw-abgesagt', 'anw-keine_antwort');
            sel.classList.add('anw-' + sel.value);
            if (grund) {
                grund.value = grundNeu;
                grund.dataset.gespeichert = grundNeu;
                grund.classList.toggle('d-none', !(sel.value === 'abgesagt' || grundNeu.trim() !== ''));
            }
        } else {
            const badge = zeile.querySelector('.anw-badge');
            if (!badge || badge.dataset.status === e.status && (zeile.querySelector('.anw-grund-text')?.textContent || '-') === (e.grund || '-')) return false;
            badge.dataset.status = e.status;
            badge.className = 'badge bg-' + (statusFarbe[e.status] || 'secondary') + ' anw-badge';
            badge.textContent = statusText[e.status] || e.status;
            const g = zeile.querySelector('.anw-grund-text');
            if (g) g.textContent = e.grund || '-';
        }
        if (e.gemeldet_am) {
            zeile.querySelectorAll('.anw-zeit').forEach(function(z) { z.textContent = e.gemeldet_am; });
            zeile.querySelectorAll('.anw-zeit-mobil').forEach(function(z) { z.classList.remove('d-none'); });
        }
        zeile.classList.remove('anw-neu'); void zeile.offsetWidth; zeile.classList.add('anw-neu');
        return true;
    }

    // Karte „Zusagen“/„Absagen“ nach Register austauschen; neu hinzugekommene Namen kurz hervorheben
    function karteErsetzen(art, html) {
        const alt = document.getElementById('anwKarte-' + art);
        if (!alt || alt._html === html) return;
        const vorher = new Set([...alt.querySelectorAll('li[data-mitglied-id]')].map(function(li) { return li.dataset.mitgliedId; }));
        const tmp = document.createElement('div');
        tmp.innerHTML = html.trim();
        const neu = tmp.firstElementChild;
        if (!neu) return;
        neu._html = html;
        neu.querySelectorAll('li[data-mitglied-id]').forEach(function(li) {
            if (!vorher.has(li.dataset.mitgliedId)) li.classList.add('anw-neu');
        });
        alt.replaceWith(neu);
    }

    let liveTimer = null;
    function liveBald() { clearTimeout(liveTimer); liveTimer = setTimeout(liveLaden, 800); }

    function liveLaden() {
        if (document.visibilityState !== 'visible') return;
        fetch('api/anwesenheit_liste.php?ausrueckung_id=' + ausrueckungIdListe, { cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                const live = document.getElementById('anwLive');
                if (!d.success) { live?.classList.add('offline'); return; }
                live?.classList.remove('offline');
                const z = { zugesagt: 0, ungewiss: 0, abgesagt: 0, keine_antwort: 0 };
                let unbekannt = false;
                d.liste.forEach(function(e) {
                    if (z[e.status] !== undefined) z[e.status]++;
                    const zeile = anwZeilen.get(String(e.mitglied_id));
                    if (zeile) zeileLiveSetzen(zeile, e); else unbekannt = true;
                });
                const zeichen = { zugesagt: '✓', ungewiss: '?', abgesagt: '✗', keine_antwort: '–' };
                Object.keys(z).forEach(function(k) {
                    document.querySelectorAll('.card-header [data-typ="' + k + '"]').forEach(function(b) { b.textContent = zeichen[k] + ' ' + z[k]; });
                });
                document.getElementById('anwNeuLaden')?.classList.toggle('d-none', !unbekannt);
                if (d.karten) Object.keys(d.karten).forEach(function(art) { karteErsetzen(art, d.karten[art]); });
                const t = document.getElementById('anwLiveZeit');
                if (t) t.textContent = ' · ' + d.zeit;
            })
            .catch(function() { document.getElementById('anwLive')?.classList.add('offline'); });
    }
    if (document.getElementById('anwLive')) {
        setInterval(liveLaden, 15000);
        document.addEventListener('visibilitychange', function() { if (document.visibilityState === 'visible') liveLaden(); });
    }

    // Anwesenheitsliste sortierbar machen
    if ($('#anwesenheitAdminTable').length) {
        // ohne Seitenaufteilung, damit beim Speichern alle Zeilen im Formular sind
        $('#anwesenheitAdminTable').DataTable({
            order: [[0, 'asc']],
            paging: false,
            columnDefs: [{ orderable: false, targets: [1] }]
        });
    }
    if ($('#anwesenheitTable').length) {
        $('#anwesenheitTable').DataTable({
            order: [[1, 'asc']],
            pageLength: 25,
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

        if (alterStatus && (statusMap[alterStatus] || alterStatus === 'keine_antwort')) {
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
                if (typeof liveBald === 'function') liveBald();
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
