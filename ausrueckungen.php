<?php
// ausrueckungen.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('ausrueckungen', 'lesen');

$ausrueckung = new Ausrueckung();

// Filter
$filter = [];
if (!empty($_GET['typ'])) {
    $filter['typ'] = $_GET['typ'];
}
if (!empty($_GET['von_datum'])) {
    $filter['von_datum'] = $_GET['von_datum'];
}
if (!empty($_GET['bis_datum'])) {
    $filter['bis_datum'] = $_GET['bis_datum'];
}
if (!empty($_GET['status'])) {
    $filter['status'] = $_GET['status'];
}

$ausrueckungen = $ausrueckung->getAll($filter);

// Eigenen Anwesenheitsstatus für alle Ausrückungen laden
$meineMitgliedId = null;
$meineAnwesenheiten = [];
$db = Database::getInstance();
$currentBenutzer = $db->fetchOne("SELECT mitglied_id FROM benutzer WHERE id = ?", [Session::getUserId()]);
if ($currentBenutzer && $currentBenutzer['mitglied_id']) {
    $meineMitgliedId = $currentBenutzer['mitglied_id'];
    $rows = $db->fetchAll(
        "SELECT ausrueckung_id, status FROM anwesenheit WHERE mitglied_id = ?",
        [$meineMitgliedId]
    );
    foreach ($rows as $row) {
        $meineAnwesenheiten[$row['ausrueckung_id']] = $row['status'];
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-flag"></i> Ausrückungen
    </h1>
    <div>
        <a href="kalender_abonnement.php" class="btn btn-outline-primary me-2">
            <i class="bi bi-calendar-check"></i> Kalender-Abo
        </a>
        <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
        <a href="ausrueckung_bearbeiten.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Neue Ausrückung
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="typ" class="form-label">Typ</label>
                <select class="form-select" id="typ" name="typ">
                    <option value="">Alle Typen</option>
                    <option value="Probe" <?php echo ($_GET['typ'] ?? '') === 'Probe' ? 'selected' : ''; ?>>Probe</option>
                    <option value="Konzert" <?php echo ($_GET['typ'] ?? '') === 'Konzert' ? 'selected' : ''; ?>>Konzert</option>
                    <option value="Ausrückung" <?php echo ($_GET['typ'] ?? '') === 'Ausrückung' ? 'selected' : ''; ?>>Ausrückung</option>
                    <option value="Fest" <?php echo ($_GET['typ'] ?? '') === 'Fest' ? 'selected' : ''; ?>>Fest</option>
                    <option value="Wertung" <?php echo ($_GET['typ'] ?? '') === 'Wertung' ? 'selected' : ''; ?>>Wertung</option>
                    <option value="Sonstiges" <?php echo ($_GET['typ'] ?? '') === 'Sonstiges' ? 'selected' : ''; ?>>Sonstiges</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Alle Status</option>
                    <option value="geplant" <?php echo ($_GET['status'] ?? '') === 'geplant' ? 'selected' : ''; ?>>Geplant</option>
                    <option value="bestaetigt" <?php echo ($_GET['status'] ?? '') === 'bestaetigt' ? 'selected' : ''; ?>>Bestätigt</option>
                    <option value="abgesagt" <?php echo ($_GET['status'] ?? '') === 'abgesagt' ? 'selected' : ''; ?>>Abgesagt</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="von_datum" class="form-label">Von</label>
                <input type="date" class="form-control" id="von_datum" name="von_datum" 
                       value="<?php echo htmlspecialchars($_GET['von_datum'] ?? ''); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="bis_datum" class="form-label">Bis</label>
                <input type="date" class="form-control" id="bis_datum" name="bis_datum" 
                       value="<?php echo htmlspecialchars($_GET['bis_datum'] ?? ''); ?>">
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Filtern
                </button>
                <a href="ausrueckungen.php" class="btn btn-secondary">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Liste -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive p-2">
            <table class="table table-hover" id="ausrueckungenTable">
                <thead>
                    <tr>
                        <th>Datum/Zeit</th>
                        <th>Titel</th>
                        <th class="d-none d-md-table-cell">Typ</th>
                        <th class="d-none d-md-table-cell">Ort</th>
                        <th class="d-none d-md-table-cell">Status</th>
                        <th>Anwesenheit</th>
                        <th class="text-end no-print d-none d-md-table-cell">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ausrueckungen as $a): ?>
                    <tr>
                        <td data-order="<?php echo date('Y-m-d H:i', strtotime($a['start_datum'])); ?>">
                            <strong><?php echo date('d.m.Y', strtotime($a['start_datum'])); ?></strong><br>
                            <small class="text-muted">
                                <?php echo date('H:i', strtotime($a['start_datum'])); ?> Uhr
                            </small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($a['titel']); ?></strong>
                            <?php if ($a['beschreibung']): ?>
                            <br><small class="text-muted d-none d-md-inline"><?php echo htmlspecialchars(substr($a['beschreibung'], 0, 50)); ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php
                            $typeColors = [
                                'Probe' => 'secondary',
                                'Konzert' => 'primary',
                                'Ausrückung' => 'success',
                                'Fest' => 'warning',
                                'Wertung' => 'danger',
                                'Sonstiges' => 'info'
                            ];
                            $color = $typeColors[$a['typ']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo htmlspecialchars($a['typ']); ?>
                            </span>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php if ($a['ort']): ?>
                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($a['ort']); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php
                            $statusColors = [
                                'geplant' => 'warning',
                                'bestaetigt' => 'success',
                                'abgesagt' => 'danger'
                            ];
                            $statusColor = $statusColors[$a['status']] ?? 'secondary';
                            $statusText = [
                                'geplant' => 'Geplant',
                                'bestaetigt' => 'Bestätigt',
                                'abgesagt' => 'Abgesagt'
                            ];
                            ?>
                            <span class="badge bg-<?php echo $statusColor; ?>">
                                <?php echo $statusText[$a['status']]; ?>
                            </span>
                        </td>
                        <td>
                            <?php
                                $meinStatus = $meineAnwesenheiten[$a['id']] ?? null;
                                $abgestimmt = in_array($meinStatus, ['zugesagt', 'abgesagt', 'ungewiss']);
                            ?>
                            <!-- Zähler mit data-Attributen für JS-Update -->
                            <div class="anwesenheit-zaehler mb-1 d-none d-md-block" data-ausrueckung-id="<?php echo $a['id']; ?>">
                                <small>
                                    <span class="text-success" data-typ="zugesagt">✓ <?php echo $a['zugesagt'] ?? 0; ?></span>
                                    <span class="text-warning mx-1" data-typ="ungewiss">? <?php echo $a['ungewiss'] ?? 0; ?></span>
                                    <span class="text-danger" data-typ="abgesagt">✗ <?php echo $a['abgesagt'] ?? 0; ?></span>
                                </small>
                            </div>
                            <?php if ($meineMitgliedId): ?>
                            <div class="btn-group btn-group-sm anwesenheit-buttons"
                                 data-ausrueckung-id="<?php echo $a['id']; ?>"
                                 data-mein-status="<?php echo htmlspecialchars($meinStatus ?? ''); ?>">
                                <button type="button"
                                    class="btn btn-anwesenheit <?php echo $abgestimmt && $meinStatus !== 'zugesagt' ? 'btn-outline-success dimmed' : 'btn-success'; ?>"
                                    data-status="zugesagt" title="Ja, ich komme">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <button type="button"
                                    class="btn btn-anwesenheit <?php echo $abgestimmt && $meinStatus !== 'ungewiss' ? 'btn-outline-warning dimmed' : 'btn-warning'; ?>"
                                    data-status="ungewiss" title="Ungewiss">
                                    <i class="bi bi-question-lg"></i>
                                </button>
                                <button type="button"
                                    class="btn btn-anwesenheit <?php echo $abgestimmt && $meinStatus !== 'abgesagt' ? 'btn-outline-danger dimmed' : 'btn-danger'; ?>"
                                    data-status="abgesagt" title="Nein, ich kann nicht">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end no-print d-none d-md-table-cell">
                            <div class="table-actions">
                                <a href="ausrueckung_detail.php?id=<?php echo $a['id']; ?>" 
                                   class="btn btn-sm btn-info" title="Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
                                <a href="ausrueckung_bearbeiten.php?id=<?php echo $a['id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (Session::checkPermission('ausrueckungen', 'loeschen')): ?>
                                <a href="ausrueckung_loeschen.php?id=<?php echo $a['id']; ?>" 
                                   class="btn btn-sm btn-danger" title="Löschen"
                                   onclick="return confirmDelete('Ausrückung wirklich löschen?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Grund für Absage -->
<div class="modal fade" id="absageModal" tabindex="-1" aria-labelledby="absageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="absageModalLabel">
                    <i class="bi bi-x-circle"></i> Absagegrund
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Möchtest du einen Grund für deine Absage angeben? (optional)</p>
                <input type="text" class="form-control" id="absage-grund-input"
                       placeholder="z.B. Urlaub, Krankheit, anderer Termin ...">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x"></i> Abbrechen
                </button>
                <button type="button" class="btn btn-danger" id="absage-bestaetigen">
                    <i class="bi bi-check-lg"></i> Absage speichern
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<style>
.btn-anwesenheit.dimmed {
    opacity: 0.35;
}
.btn-anwesenheit {
    transition: all 0.15s ease;
}

@media (max-width: 767.98px) {
    .btn-anwesenheit {
        padding: 0.5rem 0.75rem;
        font-size: 1.1rem;
    }
    /* Tabelle kompakter auf Mobile */
    #ausrueckungenTable td {
        vertical-align: middle;
    }
}
</style>

<script>
$(document).ready(function() {
    $('#ausrueckungenTable').DataTable({
        order: [[0, 'asc']]
    });

    const statusMap = {
        'zugesagt': { solid: 'btn-success',  outline: 'btn-outline-success'  },
        'ungewiss': { solid: 'btn-warning',  outline: 'btn-outline-warning'  },
        'abgesagt': { solid: 'btn-danger',   outline: 'btn-outline-danger'   }
    };

    // Pending-Absage zwischenspeichern bis Modal bestätigt wird
    let pendingAbsage = null;

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

    function updateZaehler(ausrueckungId, alterStatus, neuerStatus) {
        const zaehler = $('.anwesenheit-zaehler[data-ausrueckung-id="' + ausrueckungId + '"]');
        if (!zaehler.length) return;

        if (alterStatus && statusMap[alterStatus]) {
            const altSpan = zaehler.find('[data-typ="' + alterStatus + '"]');
            const altVal = parseInt(altSpan.text().replace(/[^0-9]/g, '')) || 0;
            if (altVal > 0) altSpan.text(altSpan.text().replace(/[0-9]+/, altVal - 1));
        }
        const neuSpan = zaehler.find('[data-typ="' + neuerStatus + '"]');
        const neuVal = parseInt(neuSpan.text().replace(/[^0-9]/g, '')) || 0;
        neuSpan.text(neuSpan.text().replace(/[0-9]+/, neuVal + 1));
    }

    function speichern(ausrueckungId, alterStatus, neuerStatus, grund) {
        updateButtons(
            $('.anwesenheit-buttons[data-ausrueckung-id="' + ausrueckungId + '"]'),
            neuerStatus
        );
        updateZaehler(ausrueckungId, alterStatus, neuerStatus);
        $('.anwesenheit-buttons[data-ausrueckung-id="' + ausrueckungId + '"]').data('mein-status', neuerStatus);

        fetch('api/anwesenheit_setzen.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'ausrueckung_id=' + ausrueckungId
                + '&status=' + encodeURIComponent(neuerStatus)
                + '&grund=' + encodeURIComponent(grund || '')
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) console.error('Fehler beim Speichern:', data.message);
        })
        .catch(err => console.error('Netzwerkfehler:', err));
    }

    $(document).on('click', '.btn-anwesenheit', function() {
        const btn = $(this);
        const gruppe = btn.closest('.anwesenheit-buttons');
        const ausrueckungId = gruppe.data('ausrueckung-id');
        const neuerStatus = btn.data('status');
        const alterStatus = gruppe.data('mein-status') || '';

        if (neuerStatus === alterStatus) return;

        if (neuerStatus === 'abgesagt') {
            // Modal öffnen, Absage erst nach Bestätigung speichern
            pendingAbsage = { ausrueckungId, alterStatus };
            $('#absage-grund-input').val('');
            $('#absageModal').modal('show');
            // Focus ins Textfeld setzen
            $('#absageModal').one('shown.bs.modal', function() {
                $('#absage-grund-input').trigger('focus');
            });
        } else {
            speichern(ausrueckungId, alterStatus, neuerStatus, '');
        }
    });

    // Modal: Absage bestätigen
    $('#absage-bestaetigen').on('click', function() {
        if (!pendingAbsage) return;
        const grund = $('#absage-grund-input').val().trim();
        speichern(pendingAbsage.ausrueckungId, pendingAbsage.alterStatus, 'abgesagt', grund);
        pendingAbsage = null;
        $('#absageModal').modal('hide');
    });

    // Enter im Grundfeld = Bestätigen
    $('#absage-grund-input').on('keydown', function(e) {
        if (e.key === 'Enter') $('#absage-bestaetigen').trigger('click');
    });

    // Modal abgebrochen → kein Status ändern
    $('#absageModal').on('hidden.bs.modal', function() {
        pendingAbsage = null;
    });
});
</script>
