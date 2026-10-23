<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('probe', 'schreiben');

$db          = Database::getInstance();
$formationId = Session::getFormationId();
$benutzerId  = Session::getUserId();

$aktiveFormationName = $formationId
    ? ($db->fetchOne("SELECT name FROM formationen WHERE id = ?", [$formationId])['name'] ?? null)
    : null;

/**
 * Belegungs-Statistik für eine laufende Live-Session ermitteln.
 * Ist eine Formation aktiv, werden ALLE aktiven Mitglieder dieser Formation
 * berücksichtigt (nicht nur die gerade online Verbundenen).
 */
function probeLiveStats(Database $db, int $sessionId, int $notenId, ?int $formationId): array {
    $spielerMap = [];
    $total      = 0;

    $spieler = $db->fetchAll(
        "SELECT pss.stimme_id, pss.benutzer_id, m.id as mitglied_id,
                COALESCE(CONCAT(m.vorname, ' ', m.nachname), b.benutzername) as anzeigename,
                ROW_NUMBER() OVER (PARTITION BY pss.benutzer_id ORDER BY pss.joined_am DESC, pss.id DESC) as rn
         FROM probe_session_spieler pss
         JOIN benutzer b ON pss.benutzer_id = b.id
         LEFT JOIN mitglieder m ON (m.id = b.mitglied_id OR (b.mitglied_id IS NULL AND m.benutzer_id = b.id))
         WHERE pss.session_id = ?
           AND (pss.last_seen IS NULL OR pss.last_seen >= NOW() - INTERVAL 20 SECOND)",
        [$sessionId]
    );
    $viewers = $db->fetchAll(
        "SELECT benutzer_id FROM probe_session_viewer WHERE noten_id = ?",
        [$notenId]
    );
    $viewerSet = array_flip(array_column($viewers, 'benutzer_id'));

    $connectedByMitglied = [];
    foreach ($spieler as $s) {
        if ((int)$s['rn'] !== 1) continue;
        $total++;
        $sid = $s['stimme_id'] ?? 0;
        $spielerMap[$sid][] = [
            'name'   => $s['anzeigename'],
            'is_new' => isset($viewerSet[$s['benutzer_id']]),
        ];
        if ($s['mitglied_id']) {
            $connectedByMitglied[$s['mitglied_id']] = [
                'stimme_id' => $s['stimme_id'],
                'is_new'    => isset($viewerSet[$s['benutzer_id']]),
            ];
        }
    }

    $mitStimme = 0;
    $ohneStimme = 0;
    $ohneStimmeListe = [];

    if ($formationId) {
        // Vollständiger Formations-Abgleich: alle aktiven Mitglieder der Formation,
        // unabhängig davon, ob sie gerade online sind.
        $mitglieder = $db->fetchAll(
            "SELECT m.id as mitglied_id, m.vorname, m.nachname
             FROM mitglied_formationen mf
             JOIN mitglieder m ON mf.mitglied_id = m.id
             WHERE mf.formation_id = ? AND m.status = 'aktiv'
             ORDER BY m.nachname, m.vorname",
            [$formationId]
        );
        foreach ($mitglieder as $mg) {
            $conn = $connectedByMitglied[$mg['mitglied_id']] ?? null;
            if ($conn && !empty($conn['stimme_id'])) {
                $mitStimme++;
                continue;
            }
            $ohneStimme++;
            $ohneStimmeListe[] = [
                'name'   => trim($mg['vorname'] . ' ' . $mg['nachname']),
                'status' => $conn ? ($conn['is_new'] ? 'viewing' : 'connected') : 'offline',
            ];
        }
    } else {
        // Keine Formation gewählt: wie bisher nur die aktuell Verbundenen zählen
        foreach ($spieler as $s) {
            if ((int)$s['rn'] !== 1) continue;
            if (!empty($s['stimme_id'])) {
                $mitStimme++;
            } else {
                $ohneStimme++;
                $ohneStimmeListe[] = [
                    'name'   => $s['anzeigename'],
                    'status' => isset($viewerSet[$s['benutzer_id']]) ? 'viewing' : 'connected',
                ];
            }
        }
    }

    return [
        'spielerMap'      => $spielerMap,
        'total'           => $total,
        'mitStimme'       => $mitStimme,
        'ohneStimme'      => $ohneStimme,
        'ohneStimmeListe' => $ohneStimmeListe,
    ];
}

// Session starten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_session'])) {
    $notenId = (int)($_POST['noten_id'] ?? 0);
    if ($notenId) {
        $db->query("UPDATE probe_session SET aktiv = 0");
        $db->query(
            "INSERT INTO probe_session (noten_id, formation_id, kapellmeister_id, aktiv, gestartet_am)
             VALUES (?, ?, ?, 1, NOW())",
            [$notenId, $formationId ?: null, $benutzerId]
        );
    }
    Session::setFlashMessage('success', 'Live-Session gestartet.');
    header('Location: probe_leiter.php');
    exit;
}

// Session beenden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stop_session'])) {
    $db->query("UPDATE probe_session SET aktiv = 0");
    Session::setFlashMessage('success', 'Live-Session beendet.');
    header('Location: probe_leiter.php');
    exit;
}

// AJAX: Spieler-Liste aktualisieren
if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    session_write_close();
    $session = $db->fetchOne(
        "SELECT ps.id, ps.noten_id FROM probe_session ps WHERE ps.aktiv = 1 LIMIT 1"
    );
    $spielerMap      = [];
    $total           = 0;
    $mitStimme       = 0;
    $ohneStimme      = 0;
    $ohneStimmeListe = [];
    if ($session) {
        $stats           = probeLiveStats($db, (int)$session['id'], (int)$session['noten_id'], $formationId);
        $spielerMap      = $stats['spielerMap'];
        $total           = $stats['total'];
        $mitStimme       = $stats['mitStimme'];
        $ohneStimme      = $stats['ohneStimme'];
        $ohneStimmeListe = $stats['ohneStimmeListe'];
    }
    echo json_encode([
        'total'             => $total,
        'spieler_map'       => $spielerMap,
        'mit_stimme'        => $mitStimme,
        'ohne_stimme'       => $ohneStimme,
        'ohne_stimme_liste' => $ohneStimmeListe,
    ]);
    exit;
}

// Aktive Session laden
$activeSession = $db->fetchOne(
    "SELECT ps.*, n.titel as noten_titel
     FROM probe_session ps
     JOIN noten n ON ps.noten_id = n.id
     WHERE ps.aktiv = 1 LIMIT 1"
);

// Stimmen der aktiven Session
$stimmen         = [];
$spielerMap      = [];
$mitStimme       = 0;
$ohneStimme      = 0;
$ohneStimmeListe = [];
if ($activeSession) {
    $stimmen = $db->fetchAll(
        "SELECT * FROM noten_stimmen WHERE noten_id = ? ORDER BY reihenfolge, name",
        [$activeSession['noten_id']]
    );
    $stats           = probeLiveStats($db, (int)$activeSession['id'], (int)$activeSession['noten_id'], $formationId);
    $spielerMapRaw   = $stats['spielerMap'];
    $mitStimme       = $stats['mitStimme'];
    $ohneStimme      = $stats['ohneStimme'];
    $ohneStimmeListe = $stats['ohneStimmeListe'];

    // Für die Server-gerenderte Ausgabe HTML-escapen (JSON/JS-Refresh nutzt die rohen Werte)
    foreach ($spielerMapRaw as $sid => $names) {
        foreach ($names as $n) {
            $spielerMap[$sid][] = ['name' => htmlspecialchars($n['name']), 'is_new' => $n['is_new']];
        }
    }
    $ohneStimmeListe = array_map(function ($n) {
        $n['name'] = htmlspecialchars($n['name']);
        return $n;
    }, $ohneStimmeListe);
}

// Notenbücher für Filter laden
$nbGeteiltCond   = Session::isAdmin() ? "OR typ = 'geteilt'" : "OR (typ = 'geteilt' AND formation_id = ?)";
$nbGeteiltParams = Session::isAdmin() ? [$benutzerId] : [$benutzerId, $formationId ?: 0];
$alleNotenbucher = $db->fetchAll("
    SELECT id, name, typ FROM notenbucher
    WHERE benutzer_id = ? {$nbGeteiltCond}
    ORDER BY typ ASC, name
", $nbGeteiltParams);

$selectedBuch = isset($_GET['buch']) ? (int)$_GET['buch'] : 0;

// Noten laden – nur Noten mit gesplitteten Stimmen-PDFs
$formationCond = $formationId
    ? "AND (n.formation_id = {$formationId} OR n.formation_id IS NULL)"
    : "";
$buchJoin  = $selectedBuch ? "JOIN notenbuch_noten nbn ON nbn.noten_id = n.id AND nbn.notenbuch_id = {$selectedBuch}" : "";
$buchOrder = $selectedBuch ? "nbn.reihenfolge, " : "";
$alleNoten = $db->fetchAll(
    "SELECT n.id, n.titel, n.komponist,
            (SELECT COUNT(*) FROM noten_stimmen WHERE noten_id = n.id) as anzahl_stimmen
     FROM noten n
     {$buchJoin}
     WHERE EXISTS (
         SELECT 1 FROM noten_dateien nd
         WHERE nd.noten_id = n.id AND nd.beschreibung LIKE '[stimme]%'
     )
     {$formationCond}
     ORDER BY {$buchOrder}n.titel"
);

include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-broadcast"></i> Live – Leitung</h1>
    <a href="probe.php" class="btn btn-primary"><i class="bi bi-eye"></i> Notenansicht öffnen</a>
</div>

<?php if ($activeSession): ?>
<div class="card border-success mb-4" style="border-left-color:var(--c-success)!important">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <span class="badge bg-success me-2">● Läuft</span>
            <strong><?= htmlspecialchars($activeSession['noten_titel']) ?></strong>
            <small class="text-muted ms-2">seit <?= date('H:i', strtotime($activeSession['gestartet_am'])) ?> Uhr</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span id="spieler-count" class="badge bg-secondary">
                <?= array_sum(array_map('count', $spielerMap)) ?> Musiker
            </span>
            <a href="probe_stimmen.php?noten_id=<?= $activeSession['noten_id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-sliders"></i> Stimmen
            </a>
            <form method="POST" class="d-inline">
                <button type="submit" name="stop_session" class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Live-Session beenden?')">
                    <i class="bi bi-stop-circle"></i> Beenden
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Stimmen-Raster -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            Stimmen-Belegung
            <?php if ($aktiveFormationName): ?>
            <small class="text-muted fw-normal">– <?= htmlspecialchars($aktiveFormationName) ?></small>
            <?php endif; ?>
        </span>
        <span class="d-flex gap-2">
            <span id="stat-mit-stimme" class="badge bg-success-subtle text-success-emphasis" title="Musiker mit zugewiesener Stimme">
                <i class="bi bi-person-check-fill"></i> <?= $mitStimme ?> mit Stimme
            </span>
            <span id="stat-ohne-stimme" class="badge bg-secondary-subtle text-secondary-emphasis" title="Musiker ohne zugewiesene Stimme">
                <i class="bi bi-person-dash-fill"></i> <?= $ohneStimme ?> ohne Stimme
            </span>
        </span>
    </div>
    <div class="card-body">
        <?php if (empty($stimmen)): ?>
            <p class="text-muted mb-0">
                Noch keine Stimmen definiert.
                <a href="probe_stimmen.php?noten_id=<?= $activeSession['noten_id'] ?>">Stimmen anlegen →</a>
            </p>
        <?php else: ?>
        <div class="row g-2" id="stimmen-grid">
            <?php foreach ($stimmen as $st): ?>
            <?php $names = $spielerMap[$st['id']] ?? []; ?>
            <div class="col-6 col-md-4 col-lg-3" data-stimme-id="<?= $st['id'] ?>">
                <div class="card h-100" style="border-left-color:<?= empty($names) ? 'var(--border)' : 'var(--c-success)' ?>!important">
                    <div class="card-body py-2 px-3">
                        <div class="fw-semibold" style="font-size:12px"><?= htmlspecialchars($st['name']) ?></div>
                        <?php if ($names): ?>
                            <?php foreach ($names as $n): ?>
                            <div class="<?= $n['is_new'] ? 'text-success' : 'text-warning' ?>" style="font-size:11px">
                                <i class="bi <?= $n['is_new'] ? 'bi-check-circle-fill' : 'bi-hourglass-split' ?>"></i>
                                <?= $n['name'] ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted" style="font-size:11px">frei</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php
        $statusBadge = ['viewing' => 'bg-success', 'connected' => 'bg-warning text-dark', 'offline' => 'bg-secondary'];
        $statusTitle = ['viewing' => 'Online, sieht das Stück gerade', 'connected' => 'Online, aber (noch) nicht bei diesem Stück', 'offline' => 'Nicht mit der Live-Session verbunden'];
        ?>
        <div id="ohne-stimme-liste" class="mt-3 p-2 rounded d-flex flex-wrap align-items-center gap-2" style="background:var(--bg-body)<?= empty($ohneStimmeListe) ? ';display:none' : '' ?>">
            <small class="text-muted">Ohne Stimme:</small>
            <?php foreach ($ohneStimmeListe as $n): ?>
            <span class="badge <?= $statusBadge[$n['status']] ?? 'bg-secondary' ?>" title="<?= $statusTitle[$n['status']] ?? '' ?>"><?= $n['name'] ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stück auswählen -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <span>Stück für Live-Session auswählen</span>
        <?php if (!empty($alleNotenbucher)): ?>
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-journals text-muted"></i>
            <select class="form-select form-select-sm" style="width:auto;min-width:180px"
                    onchange="location.href='probe_leiter.php?buch='+this.value">
                <option value="0" <?= !$selectedBuch ? 'selected' : '' ?>>Alle Stücke</option>
                <?php foreach ($alleNotenbucher as $nb): ?>
                <option value="<?= $nb['id'] ?>" <?= $selectedBuch == $nb['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($nb['name']) ?>
                    <?= $nb['typ'] === 'geteilt' ? '(geteilt)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
            <a href="notenbucher.php" class="btn btn-sm btn-outline-secondary" title="Notenbücher verwalten">
                <i class="bi bi-gear"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($alleNoten)): ?>
        <p class="text-muted">
            <?= $selectedBuch ? 'Keine Stücke in diesem Notenbuch.' : 'Keine Noten vorhanden.' ?>
            <?php if (!$selectedBuch): ?><a href="noten.php">Noten verwalten →</a><?php endif; ?>
        </p>
        <?php else: ?>
        <div class="row g-2">
            <?php foreach ($alleNoten as $note): ?>
            <?php $isActive = $activeSession && $activeSession['noten_id'] == $note['id']; ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                <div class="card h-100<?= $isActive ? ' border-primary' : '' ?>">
                    <div class="card-body py-2 px-3">
                        <div class="fw-semibold lh-sm" style="font-size:13px"><?= htmlspecialchars($note['titel']) ?></div>
                        <?php if ($note['komponist']): ?>
                        <div class="text-muted text-truncate" style="font-size:10px"><?= htmlspecialchars($note['komponist']) ?></div>
                        <?php endif; ?>
                        <div class="mt-1 d-flex gap-1 flex-wrap">
                            <span class="badge bg-secondary" style="font-size:10px"><?= (int)$note['anzahl_stimmen'] ?> Stimmen</span>
                            <?php if ($isActive): ?>
                            <span class="badge bg-success" style="font-size:10px">Aktiv</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer py-1 px-2 d-flex gap-1">
                        <form method="POST" class="flex-fill">
                            <input type="hidden" name="noten_id" value="<?= $note['id'] ?>">
                            <button type="submit" name="start_session"
                                    class="btn btn-xs w-100 <?= $isActive ? 'btn-primary' : 'btn-outline-primary' ?>"
                                    style="font-size:11px;padding:2px 6px">
                                <i class="bi bi-play-fill"></i> <?= $isActive ? 'Neu' : 'Start' ?>
                            </button>
                        </form>
                        <a href="probe_stimmen.php?noten_id=<?= $note['id'] ?>" class="btn btn-outline-secondary"
                           title="Stimmen" style="font-size:11px;padding:2px 6px">
                            <i class="bi bi-sliders"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
<?php if ($activeSession): ?>
// Spieler-Liste alle 4 Sekunden aktualisieren
(function() {
    var stimmenData = <?= json_encode($stimmen) ?>;

    function refresh() {
        fetch('probe_leiter.php', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                document.getElementById('spieler-count').textContent = d.total + ' Musiker';
                var statMit  = document.getElementById('stat-mit-stimme');
                var statOhne = document.getElementById('stat-ohne-stimme');
                if (statMit)  statMit.innerHTML  = '<i class="bi bi-person-check-fill"></i> ' + d.mit_stimme + ' mit Stimme';
                if (statOhne) statOhne.innerHTML = '<i class="bi bi-person-dash-fill"></i> ' + d.ohne_stimme + ' ohne Stimme';

                var ohneListe = document.getElementById('ohne-stimme-liste');
                if (ohneListe) {
                    var liste = d.ohne_stimme_liste || [];
                    if (liste.length) {
                        var badgeCls = { viewing: 'bg-success', connected: 'bg-warning text-dark', offline: 'bg-secondary' };
                        var html = '<small class="text-muted">Ohne Stimme: </small>';
                        liste.forEach(function(n) {
                            html += '<span class="badge ' + (badgeCls[n.status] || 'bg-secondary') + '">' + n.name + '</span>';
                        });
                        ohneListe.innerHTML = html;
                        ohneListe.style.display = '';
                    } else {
                        ohneListe.style.display = 'none';
                    }
                }

                var grid = document.getElementById('stimmen-grid');
                if (!grid) return;
                stimmenData.forEach(function(st) {
                    var cell = grid.querySelector('[data-stimme-id="' + st.id + '"]');
                    if (!cell) return;
                    var names = d.spieler_map[st.id] || [];
                    var inner = cell.querySelector('.card');
                    inner.style.borderLeftColor = names.length ? 'var(--c-success)' : 'var(--border)';
                    var body = cell.querySelector('.card-body');
                    var html = '<div class="fw-semibold" style="font-size:12px">' + st.name + '</div>';
                    if (names.length) {
                        names.forEach(function(n) {
                            var cls  = n.is_new ? 'text-success' : 'text-warning';
                            var icon = n.is_new ? 'bi-check-circle-fill' : 'bi-hourglass-split';
                            html += '<div class="' + cls + '" style="font-size:11px"><i class="bi ' + icon + '"></i> ' + n.name + '</div>';
                        });
                    } else {
                        html += '<div class="text-muted" style="font-size:11px">frei</div>';
                    }
                    body.innerHTML = html;
                });
            })
            .catch(function() {});
    }

    setInterval(refresh, 4000);
})();
<?php endif; ?>
</script>
