<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'lesen');

$festId  = isset($_GET['fest_id']) ? (int)$_GET['fest_id'] : 0;
$festObj = new Fest();
$fest    = $festObj->getById($festId);

if (!$fest) {
    Session::setFlashMessage('danger', 'Fest nicht gefunden.');
    header('Location: feste.php'); exit;
}

$dpObj      = new FestDienstplan();
$stationObj = new FestStation();
$maObj      = new FestMitarbeiter();

$mitarbeiter = $maObj->getByFest($festId);
$daten       = $dpObj->getDaten($festId);

// Alle Fest-Tage ermitteln
$alleDaten = [];
if (!empty($fest['datum_von'])) {
    $d   = new DateTime($fest['datum_von']);
    $end = new DateTime($fest['datum_bis'] ?: $fest['datum_von']);
    while ($d <= $end) {
        $alleDaten[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }
}
foreach ($daten as $d) {
    if (!in_array($d, $alleDaten)) $alleDaten[] = $d;
}
sort($alleDaten);
if (empty($alleDaten)) $alleDaten = [date('Y-m-d')];

$filterDatum = isset($_GET['datum']) ? $_GET['datum'] : ($alleDaten[0] ?? date('Y-m-d'));

$stationen   = $stationObj->getByFestUndDatum($festId, $filterDatum);

// Schichten: $gridData[$stationId][$maId] = [shifts]
$gridData = [];
if ($filterDatum) {
    foreach ($dpObj->getByFest($festId, $filterDatum) as $sch) {
        $gridData[$sch['station_id']][$sch['mitarbeiter_id']][] = $sch;
    }
}

// ── Zeitraster ─────────────────────────────────────────────────────────────
function dpTimeToMin(string $t): int {
    $p = explode(':', substr($t, 0, 5));
    return (int)$p[0] * 60 + (int)$p[1];
}
function dpMinToTime(int $m): string {
    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

$SLOT_W   = 64;   // px pro 30 Min
$LABEL_W  = 200;  // px linke Spalte
$ROW_H    = 50;   // px Mitarbeiter-Zeile
$ST_HDR_H = 38;   // px Stations-Header-Zeile
$HEAD_H   = 34;   // px Zeitkopf

// Früheste Öffnung / späteste Schließung aus Stationen ermitteln
$stStartMin = null;
$stEndMin   = null;
foreach ($stationen as $s) {
    if (!empty($s['oeffnung_von'])) $stStartMin = $stStartMin === null ? dpTimeToMin($s['oeffnung_von']) : min($stStartMin, dpTimeToMin($s['oeffnung_von']));
    if (!empty($s['oeffnung_bis'])) $stEndMin   = $stEndMin   === null ? dpTimeToMin($s['oeffnung_bis']) : max($stEndMin,   dpTimeToMin($s['oeffnung_bis']));
}

// 2 Stunden Puffer, auf 30 Min gerundet, begrenzt auf 00:00–24:00
if ($stStartMin !== null) {
    $startMin = (int)(floor(max(0,         $stStartMin - 60) / 30) * 30);
    $endMin   = (int)(ceil( min(24 * 60,   $stEndMin   + 60) / 30) * 30);
} else {
    $startMin = 8 * 60;
    $endMin   = 22 * 60;
}

// Schichten dürfen nicht außerhalb des Rasters liegen
foreach ($gridData as $stSchichten) {
    foreach ($stSchichten as $maSchichten) {
        foreach ($maSchichten as $sch) {
            if (!empty($sch['zeit_von'])) $startMin = min($startMin, (int)(floor(dpTimeToMin($sch['zeit_von']) / 30) * 30));
            if (!empty($sch['zeit_bis'])) $endMin   = max($endMin,   (int)(ceil( dpTimeToMin($sch['zeit_bis']) / 30) * 30));
        }
    }
}
$slotCount = ($endMin - $startMin) / 30;
$gridW     = $slotCount * $SLOT_W;

// Station-Farben
$stPalette = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16','#f97316','#6366f1'];
$stColors  = [];
foreach ($stationen as $i => $s) {
    $stColors[$s['id']] = $stPalette[$i % count($stPalette)];
}

$stationenJson = array_map(fn($s) => [
    'id'    => $s['id'],
    'name'  => $s['name'],
    'color' => $stColors[$s['id']],
    'von'   => !empty($s['oeffnung_von']) ? substr($s['oeffnung_von'],0,5) : null,
    'bis'   => !empty($s['oeffnung_bis']) ? substr($s['oeffnung_bis'],0,5) : null,
], $stationen);

$mitarbeiterJson = array_map(fn($m) => [
    'id'       => $m['id'],
    'vollname' => $m['vollname'],
    'funktion' => $m['funktion'] ?? '',
], $mitarbeiter);

$canWrite  = Session::checkPermission('fest', 'schreiben');
$canDelete = Session::checkPermission('fest', 'loeschen');

// MA-Lookup
$maById = [];
foreach ($mitarbeiter as $ma) $maById[$ma['id']] = $ma;

include 'includes/header.php';
?>

<?php include 'includes/fest_tabs.php'; ?>

<div class="dp-print-title">
    Dienstplan – <?= htmlspecialchars($fest['name']) ?> – <?= date('D d.m.Y', strtotime($filterDatum)) ?>
</div>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-calendar3"></i> Dienstplan</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary" id="btnPDF">
            <i class="bi bi-file-pdf"></i> PDF erstellen
        </button>
        <?php if ($canWrite): ?>
        <button class="btn btn-primary" id="btnAddSchicht">
            <i class="bi bi-plus-lg"></i> Schicht hinzufügen
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($stationen)): ?>
<div class="alert alert-info"><i class="bi bi-info-circle"></i>
    Bitte zuerst <a href="fest_stationen.php?fest_id=<?= $festId ?>">Stationen anlegen</a>.
</div>
<?php elseif (empty($mitarbeiter)): ?>
<div class="alert alert-info"><i class="bi bi-info-circle"></i>
    Bitte zuerst <a href="fest_mitarbeiter.php?fest_id=<?= $festId ?>">Mitarbeiter hinzufügen</a>.
</div>
<?php else: ?>

<!-- Tages-Auswahl -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="text-muted small"><i class="bi bi-calendar3"></i></span>
            <?php foreach ($alleDaten as $d): ?>
            <a href="fest_dienstplan.php?fest_id=<?= $festId ?>&datum=<?= $d ?>"
               class="btn btn-sm <?= $filterDatum === $d ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= date('D d.m.', strtotime($d)) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Grid -->
<div class="card mb-3" id="dp-grid-card">
    <div class="card-body p-0" style="overflow:hidden;border-radius:inherit">
        <div style="display:flex">

            <!-- ── Linke Spalte (fix) ───────────────────────────────────── -->
            <div id="dp-labels" style="width:<?= $LABEL_W ?>px;flex-shrink:0;border-right:2px solid #dee2e6;z-index:5">

                <!-- Zeitkopf-Spacer -->
                <div style="height:<?= $HEAD_H ?>px;background:#fff;border-bottom:2px solid #dee2e6"></div>

                <?php foreach ($stationen as $s):
                    $stId       = $s['id'];
                    $color      = $stColors[$stId];
                    $schichtAnz = 0;
                    foreach ($mitarbeiter as $ma) $schichtAnz += count($gridData[$stId][$ma['id']] ?? []);
                    $filled = $schichtAnz >= $s['benoetigte_helfer'];
                ?>

                <!-- Station-Header -->
                <div style="height:<?= $ST_HDR_H ?>px;
                            background:<?= $color ?>;color:#fff;
                            padding:0 10px;
                            display:flex;align-items:center;justify-content:space-between;
                            border-bottom:1px solid rgba(0,0,0,0.12)">
                    <div style="font-weight:700;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                        <i class="bi bi-geo-alt-fill" style="font-size:11px;opacity:0.8"></i>
                        <?= htmlspecialchars($s['name']) ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:5px;flex-shrink:0">
                        <?php if (!empty($s['oeffnung_von'])): ?>
                        <span style="font-size:10px;opacity:0.85"><?= substr($s['oeffnung_von'],0,5) ?>–<?= substr($s['oeffnung_bis'],0,5) ?></span>
                        <?php endif; ?>
                        <span style="font-size:10px;background:rgba(255,255,255,0.25);
                                     border-radius:10px;padding:1px 6px;font-weight:600">
                            <?= $schichtAnz ?>/<?= $s['benoetigte_helfer'] ?>
                        </span>
                    </div>
                </div>

                <!-- Mitarbeiter-Labels unter dieser Station (nur mit Schicht) -->
                <?php foreach ($mitarbeiter as $ma):
                    if (empty($gridData[$stId][$ma['id']])) continue;
                ?>
                <div class="dp-label-row"
                     draggable="true"
                     data-station-id="<?= $stId ?>"
                     data-ma-id="<?= $ma['id'] ?>"
                     style="height:<?= $ROW_H ?>px;
                            padding:4px 6px 4px 4px;
                            border-bottom:1px solid #dee2e6;
                            background:#fff;
                            display:flex;align-items:center;gap:4px;
                            border-left:3px solid <?= $color ?>55;
                            cursor:grab">
                    <i class="bi bi-grip-vertical" style="font-size:14px;color:#adb5bd;flex-shrink:0"></i>
                    <div style="overflow:hidden">
                        <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            <?= htmlspecialchars($ma['vollname']) ?>
                        </div>
                        <?php if ($ma['funktion']): ?>
                        <div style="font-size:10px;color:#6c757d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            <?= htmlspecialchars($ma['funktion']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <!-- Drop-Zone-Spacer -->
                <div class="dp-label-dropzone"
                     data-station-id="<?= $stId ?>"
                     style="height:<?= $ROW_H ?>px;
                            border-bottom:1px solid #dee2e6;
                            border-left:3px solid <?= $color ?>55;
                            display:flex;align-items:center;justify-content:center;
                            background:#fafafa">
                    <span style="font-size:11px;color:#adb5bd">
                        <i class="bi bi-plus-circle"></i> Einplanen
                    </span>
                </div>

                <?php endforeach; ?>
            </div>

            <!-- ── Rechte Spalte (scrollbar) ──────────────────────────── -->
            <div id="dp-scroll" style="overflow-x:auto;flex:1">
                <div id="dp-inner" style="min-width:<?= $gridW ?>px">

                    <!-- Zeitkopf -->
                    <div style="height:<?= $HEAD_H ?>px;position:relative;border-bottom:2px solid #dee2e6;background:#fff">
                        <?php for ($i = 0; $i <= $slotCount; $i++):
                            $m = $startMin + $i * 30;
                            $isHour = ($m % 60 === 0);
                        ?>
                        <?php if ($isHour): ?>
                        <div class="dp-time-label" data-slot-idx="<?= $i ?>"
                             style="position:absolute;left:<?= $i * $SLOT_W ?>px;top:0;height:100%;
                                    border-left:2px solid #ced4da;padding:8px 4px 0;
                                    font-size:11px;font-weight:700;color:#495057;
                                    white-space:nowrap;box-sizing:border-box">
                            <?= dpMinToTime($m) ?>
                        </div>
                        <?php else: ?>
                        <div class="dp-time-label" data-slot-idx="<?= $i ?>"
                             style="position:absolute;left:<?= $i * $SLOT_W ?>px;top:18px;
                                    border-left:1px dashed #dee2e6;height:50%;
                                    font-size:9px;color:#adb5bd;padding:0 3px;box-sizing:border-box">:30</div>
                        <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <!-- Stations + Mitarbeiter-Zeilen -->
                    <?php foreach ($stationen as $s):
                        $stId  = $s['id'];
                        $color = $stColors[$stId];
                        $hasOef = !empty($s['oeffnung_von']) && !empty($s['oeffnung_bis']);
                        $stVon  = $hasOef ? dpTimeToMin($s['oeffnung_von']) : $startMin;
                        $stBis  = $hasOef ? dpTimeToMin($s['oeffnung_bis']) : $endMin;
                        $stL    = ($stVon - $startMin) / 30 * $SLOT_W;
                        $stW    = ($stBis - $stVon)    / 30 * $SLOT_W;
                    ?>

                    <!-- Station-Header-Zeile -->
                    <div class="dp-bgrid-st" style="height:<?= $ST_HDR_H ?>px;position:relative;
                                background:<?= $color ?>18;
                                border-bottom:1px solid <?= $color ?>55">
                        <?php if ($hasOef): ?>
                        <!-- Öffnungszeit-Markierung in Header -->
                        <div class="dp-oef-marker"
                             data-von-min="<?= $stVon ?>" data-bis-min="<?= $stBis ?>"
                             style="position:absolute;left:<?= $stL ?>px;width:<?= $stW ?>px;
                                    top:6px;bottom:6px;background:<?= $color ?>33;
                                    border:1px solid <?= $color ?>66;border-radius:4px;
                                    pointer-events:none"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Mitarbeiter-Timelines dieser Station (nur mit Schicht) -->
                    <?php foreach ($mitarbeiter as $ma):
                        $maSchichten = $gridData[$stId][$ma['id']] ?? [];
                        if (empty($maSchichten)) continue;
                    ?>
                    <div class="dp-timeline dp-bgrid-tl"
                         data-station-id="<?= $stId ?>"
                         data-station-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                         data-station-color="<?= $color ?>"
                         data-ma-id="<?= $ma['id'] ?>"
                         data-ma-name="<?= htmlspecialchars($ma['vollname'], ENT_QUOTES) ?>"
                         style="height:<?= $ROW_H ?>px;position:relative;
                                border-bottom:1px solid #dee2e6;
                                background-color:#fff;
                                cursor:<?= $canWrite ? 'crosshair' : 'default' ?>">

                        <!-- Öffnungszeit-Hintergrund -->
                        <?php if ($hasOef): ?>
                        <?php if ($stL > 0): ?>
                        <div class="dp-oef-bg-before" data-von-min="<?= $stVon ?>"
                             style="position:absolute;left:0;width:<?= $stL ?>px;top:0;bottom:0;
                                    background:rgba(0,0,0,0.04);pointer-events:none;z-index:1"></div>
                        <?php endif; ?>
                        <div class="dp-oef-bg-after" data-bis-min="<?= $stBis ?>"
                             style="position:absolute;left:<?= $stL + $stW ?>px;right:0;top:0;bottom:0;
                                    background:rgba(0,0,0,0.04);pointer-events:none;z-index:1"></div>
                        <?php endif; ?>

                        <!-- Schicht-Blöcke -->
                        <?php foreach ($maSchichten as $sch):
                            $vonMin = dpTimeToMin($sch['zeit_von']);
                            $bisMin = dpTimeToMin($sch['zeit_bis']);
                            $left   = ($vonMin - $startMin) / 30 * $SLOT_W;
                            $width  = max(($bisMin - $vonMin) / 30 * $SLOT_W, $SLOT_W);
                            $ttText = htmlspecialchars($ma['vollname'], ENT_QUOTES)
                                    . ' @ ' . htmlspecialchars($s['name'], ENT_QUOTES)
                                    . ' | ' . substr($sch['zeit_von'],0,5) . '–' . substr($sch['zeit_bis'],0,5)
                                    . ($sch['notizen'] ? ' | ' . htmlspecialchars($sch['notizen'], ENT_QUOTES) : '');
                        ?>
                        <div class="dp-shift"
                             data-id="<?= $sch['id'] ?>"
                             data-von-min="<?= $vonMin ?>"
                             data-bis-min="<?= $bisMin ?>"
                             data-duration="<?= $bisMin - $vonMin ?>"
                             data-station-id="<?= $stId ?>"
                             data-ma-id="<?= $ma['id'] ?>"
                             data-bs-toggle="tooltip"
                             data-bs-placement="top"
                             data-bs-title="<?= $ttText ?>"
                             style="position:absolute;left:<?= $left ?>px;width:<?= $width ?>px;
                                    top:5px;height:<?= $ROW_H-10 ?>px;
                                    background:<?= $color ?>;color:#fff;border-radius:6px;
                                    padding:3px 20px 3px 20px;
                                    font-size:11px;overflow:hidden;
                                    box-shadow:0 2px 6px rgba(0,0,0,0.2);
                                    user-select:none;z-index:3;box-sizing:border-box;
                                    cursor:<?= $canWrite ? 'grab' : 'default' ?>">
                            <?php if ($canWrite): ?>
                            <div class="dp-resize-left" style="position:absolute;left:0;top:0;bottom:0;width:10px;
                                     cursor:ew-resize;z-index:6;border-radius:6px 0 0 6px;
                                     background:rgba(0,0,0,0.18)"></div>
                            <?php endif; ?>
                            <div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.5">
                                <?= substr($sch['zeit_von'],0,5) ?> – <?= substr($sch['zeit_bis'],0,5) ?>
                            </div>
                            <div class="dp-shift-time" style="font-size:10px;opacity:0.85">
                                <?= dpTimeToMin($sch['zeit_bis']) - dpTimeToMin($sch['zeit_von']) ?> Min
                            </div>
                            <?php if ($canWrite): ?>
                            <div class="dp-resize-right" style="position:absolute;right:0;top:0;bottom:0;width:10px;
                                     cursor:ew-resize;z-index:6;border-radius:0 6px 6px 0;
                                     background:rgba(0,0,0,0.18)"></div>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button class="dp-del" data-id="<?= $sch['id'] ?>"
                                    style="position:absolute;top:3px;right:12px;border:none;
                                           background:rgba(0,0,0,0.28);color:#fff;border-radius:50%;
                                           width:14px;height:14px;font-size:12px;line-height:13px;
                                           cursor:pointer;padding:0;display:flex;align-items:center;
                                           justify-content:center;z-index:7">×</button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <!-- Drop-Ghost -->
                        <div class="dp-ghost" style="display:none;position:absolute;top:5px;
                                height:<?= $ROW_H-10 ?>px;background:rgba(13,110,253,0.15);
                                border:2px dashed #0d6efd;border-radius:6px;
                                pointer-events:none;z-index:6;box-sizing:border-box"></div>
                    </div>
                    <?php endforeach; // mitarbeiter ?>

                    <!-- Drop-Zone: neuer MA via Bank-Drag -->
                    <div class="dp-dropzone"
                         data-station-id="<?= $stId ?>"
                         data-station-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                         data-station-color="<?= $color ?>"
                         style="height:<?= $ROW_H ?>px;position:relative;
                                border-bottom:1px solid #dee2e6;
                                background:#fafafa;
                                display:flex;align-items:center;justify-content:center;
                                border:2px dashed transparent;
                                box-sizing:border-box;
                                transition:border-color 0.15s,background 0.15s">
                        <span class="dp-dropzone-hint" style="font-size:11px;color:#adb5bd;pointer-events:none">
                            <i class="bi bi-plus-circle"></i> Mitarbeiter aus Bank hierher ziehen
                        </span>
                        <!-- Drop-Ghost -->
                        <div class="dp-ghost" style="display:none;position:absolute;top:5px;
                                height:<?= $ROW_H-10 ?>px;background:rgba(13,110,253,0.15);
                                border:2px dashed #0d6efd;border-radius:6px;
                                pointer-events:none;z-index:6;box-sizing:border-box"></div>
                    </div>

                    <?php endforeach; // stationen ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Welche Mitarbeiter sind bereits eingeplant (für diesen Tag)?
$maEingeplant = [];
foreach ($gridData as $stId => $maSchichten) {
    foreach ($maSchichten as $maId => $schichten) {
        if (!empty($schichten)) $maEingeplant[$maId] = true;
    }
}
?>

<!-- Mitarbeiter-Bank -->
<?php if ($canWrite && !empty($mitarbeiter)): ?>
<div class="card mb-3">
    <div class="card-header py-2">
        <span class="fw-semibold"><i class="bi bi-people"></i> Mitarbeiter</span>
        <span class="text-muted small ms-2">auf eine Zeile ziehen zum Einplanen</span>
    </div>
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($mitarbeiter as $ma):
                $eingeplant = !empty($maEingeplant[$ma['id']]);
            ?>
            <div class="dp-bench"
                 draggable="true"
                 data-ma-id="<?= $ma['id'] ?>"
                 data-ma-name="<?= htmlspecialchars($ma['vollname'], ENT_QUOTES) ?>"
                 style="border-radius:8px;padding:7px 12px;font-size:12px;
                        user-select:none;cursor:grab;min-width:110px;
                        <?php if ($eingeplant): ?>
                        background:#e8f5e9;border:2px solid #4caf50;color:#2e7d32;
                        <?php else: ?>
                        background:#fff;border:2px solid #dee2e6;color:#212529;
                        <?php endif; ?>">
                <div class="bench-name" style="font-weight:700">
                    <?= htmlspecialchars($ma['vollname']) ?>
                    <?php if ($eingeplant): ?>
                    <i class="bi bi-check-circle-fill text-success ms-1 bench-check" style="font-size:11px"></i>
                    <?php endif; ?>
                </div>
                <?php if ($ma['funktion']): ?>
                <div style="font-size:10px;opacity:0.7"><?= htmlspecialchars($ma['funktion']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Modal -->
<div class="modal fade" id="schichtModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Schicht eintragen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mitarbeiter <span class="text-danger">*</span></label>
                    <select id="sf-mitarbeiter" class="form-select">
                        <option value="">– Mitarbeiter wählen –</option>
                        <?php foreach ($mitarbeiter as $ma): ?>
                        <option value="<?= $ma['id'] ?>"><?= htmlspecialchars($ma['vollname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Station <span class="text-danger">*</span></label>
                    <select id="sf-station" class="form-select">
                        <option value="">– Station wählen –</option>
                        <?php foreach ($stationen as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label fw-semibold">Von <span class="text-danger">*</span></label>
                        <input type="time" id="sf-zeit-von" class="form-control" step="1800">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label fw-semibold">Bis <span class="text-danger">*</span></label>
                        <input type="time" id="sf-zeit-bis" class="form-control" step="1800">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Notizen</label>
                    <input type="text" id="sf-notizen" class="form-control" placeholder="Optional">
                </div>
                <div id="sf-error" class="alert alert-danger d-none mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" id="sf-save">
                    <i class="bi bi-check-lg"></i> Speichern
                </button>
            </div>
        </div>
    </div>
</div>

<style>
:root { --dp-sw: <?= $SLOT_W ?>px; }

/* Raster-Hintergrund via CSS-Variable (skalierbar) */
.dp-bgrid-st {
    background-image: repeating-linear-gradient(90deg,
        transparent 0px, transparent calc(var(--dp-sw)/2 - 1px),
        rgba(0,0,0,0.03) calc(var(--dp-sw)/2 - 1px), rgba(0,0,0,0.03) calc(var(--dp-sw)/2),
        transparent calc(var(--dp-sw)/2), transparent calc(var(--dp-sw) - 1px),
        rgba(0,0,0,0.06) calc(var(--dp-sw) - 1px), rgba(0,0,0,0.06) var(--dp-sw));
}
.dp-bgrid-tl {
    background-image: repeating-linear-gradient(90deg,
        transparent 0px, transparent calc(var(--dp-sw)/2 - 1px),
        #f1f3f5 calc(var(--dp-sw)/2 - 1px), #f1f3f5 calc(var(--dp-sw)/2),
        transparent calc(var(--dp-sw)/2), transparent calc(var(--dp-sw) - 1px),
        #dee2e6 calc(var(--dp-sw) - 1px), #dee2e6 var(--dp-sw));
}

.dp-shift              { transition: box-shadow 0.1s; }
.dp-shift:hover        { box-shadow: 0 4px 16px rgba(0,0,0,0.32) !important; z-index:10 !important; }
.dp-shift.dp-active    { opacity: 0.5; cursor: grabbing !important; z-index:20 !important; }
.dp-timeline.dp-over   { outline: 2px dashed #0d6efd; outline-offset: -2px; }
.dp-resize-left:hover,
.dp-resize-right:hover { background: rgba(0,0,0,0.38) !important; }
.dp-dropzone           { transition: border-color 0.15s, background 0.15s; }
.dp-label-dropzone     { opacity: 0.6; }

@media screen { .dp-print-title { display: none; } }
</style>

<?php include 'includes/footer.php'; ?>
<script>
let   SLOT_W     = <?= $SLOT_W ?>;
const ROW_H      = <?= $ROW_H ?>;
const GRID_START = <?= $startMin ?>;
const GRID_END   = <?= $endMin ?>;
const FEST_ID    = <?= $festId ?>;
const DATUM      = '<?= $filterDatum ?>';
const CAN_WRITE  = <?= $canWrite ? 'true' : 'false' ?>;
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;
const STATIONEN  = <?= json_encode($stationenJson) ?>;
const MITARBEITER = <?= json_encode($mitarbeiterJson) ?>;

function timeToMin(t) {
    var p = t.split(':'); return parseInt(p[0])*60 + parseInt(p[1]);
}

// ── Tooltips ───────────────────────────────────────────────────────────────
function initTooltip(el) {
    new bootstrap.Tooltip(el, { trigger: 'hover', customClass: 'tooltip-wide' });
}
document.querySelectorAll('.dp-shift[data-bs-toggle="tooltip"]').forEach(initTooltip);

// ── Helpers ────────────────────────────────────────────────────────────────
function minToTime(m) {
    return String(Math.floor(m/60)).padStart(2,'0') + ':' + String(m%60).padStart(2,'0');
}
function pxToSlot(px) { return Math.round(px / SLOT_W); }
function slotToMin(s)  { return GRID_START + s * 30; }
function clampMin(m, dur) {
    return Math.max(GRID_START, Math.min(m, GRID_END - dur));
}
function getScrollX(clientX) {
    var sc = document.getElementById('dp-scroll');
    return clientX - sc.getBoundingClientRect().left + sc.scrollLeft;
}
function updateTimeLabel(el, vonMin, bisMin) {
    var t = el.querySelector('.dp-shift-time');
    if (t) t.textContent = (bisMin - vonMin) + ' Min';
    var h = el.querySelector('div:not(.dp-resize-left):not(.dp-resize-right):not(.dp-shift-time)');
    if (h) h.textContent = minToTime(vonMin) + ' – ' + minToTime(bisMin);
}
function snapToSlot(min) { return Math.round(min / 30) * 30; }

// ── Interaktion ────────────────────────────────────────────────────────────
var iact           = null;
var dragJustEnded  = false;

document.addEventListener('mousedown', function(e) {
    if (!CAN_WRITE || e.target.closest('.dp-del')) return;

    var leftH  = e.target.closest('.dp-resize-left');
    var rightH = e.target.closest('.dp-resize-right');
    var shiftEl = e.target.closest('.dp-shift');
    if (!leftH && !rightH && !shiftEl) return;

    e.preventDefault();
    var el = shiftEl || (leftH || rightH).closest('.dp-shift');

    var tt = bootstrap.Tooltip.getInstance(el);
    if (tt) tt.hide();

    iact = {
        type:      leftH ? 'resize-left' : (rightH ? 'resize-right' : 'drag'),
        el:        el,
        id:        parseInt(el.dataset.id),
        vonMin:    parseInt(el.dataset.vonMin),
        bisMin:    parseInt(el.dataset.bisMin),
        duration:  parseInt(el.dataset.bisMin) - parseInt(el.dataset.vonMin),
        stationId: parseInt(el.dataset.stationId),
        maId:      parseInt(el.dataset.maId),
        origLeft:  parseFloat(el.style.left),
        origWidth: parseFloat(el.style.width),
        startX:    e.clientX,
        grabOff:   getScrollX(e.clientX) - parseFloat(el.style.left),
        timeline:  el.closest('.dp-timeline'),
    };
    el.classList.add('dp-active');
    document.body.style.userSelect = 'none';
    document.body.style.cursor = (leftH || rightH) ? 'ew-resize' : 'grabbing';
});

document.addEventListener('mousemove', function(e) {
    if (!iact) return;
    var el   = iact.el;
    var maxW = (GRID_END - GRID_START) / 30 * SLOT_W;

    if (iact.type === 'drag') {
        var rawLeft  = getScrollX(e.clientX) - iact.grabOff;
        var newVon   = snapToSlot(clampMin(slotToMin(rawLeft / SLOT_W * 1), iact.duration));
        var newLeft  = (newVon - GRID_START) / 30 * SLOT_W;
        el.style.left = newLeft + 'px';
    }

    if (iact.type === 'resize-left') {
        var dx     = e.clientX - iact.startX;
        var dSlots = Math.round(dx / SLOT_W);
        var newW   = iact.origWidth - dSlots * SLOT_W;
        if (newW >= SLOT_W) {
            var newLeft = iact.origLeft + dSlots * SLOT_W;
            if (newLeft >= 0) {
                el.style.left  = newLeft + 'px';
                el.style.width = newW + 'px';
                updateTimeLabel(el, slotToMin(pxToSlot(newLeft)), iact.bisMin);
            }
        }
    }

    if (iact.type === 'resize-right') {
        var dx     = e.clientX - iact.startX;
        var dSlots = Math.round(dx / SLOT_W);
        var newW   = Math.max(SLOT_W, Math.min(iact.origWidth + dSlots * SLOT_W, maxW - iact.origLeft));
        el.style.width = newW + 'px';
        updateTimeLabel(el, iact.vonMin, slotToMin(pxToSlot(iact.origLeft + newW)));
    }
});

document.addEventListener('mouseup', function(e) {
    if (!iact) return;
    var el = iact.el;
    el.classList.remove('dp-active');
    document.body.style.userSelect = '';
    document.body.style.cursor     = '';

    var newLeft  = parseFloat(el.style.left);
    var newWidth = parseFloat(el.style.width);
    var newVon   = slotToMin(pxToSlot(newLeft));
    var newBis   = slotToMin(pxToSlot(newLeft + newWidth));

    // Revert bei ungültigen Werten
    if (newVon >= newBis || newVon < GRID_START || newBis > GRID_END) {
        el.style.left  = iact.origLeft + 'px';
        el.style.width = iact.origWidth + 'px';
        updateTimeLabel(el, iact.vonMin, iact.bisMin);
        dragJustEnded = true; iact = null; return;
    }

    var changed = (newVon !== iact.vonMin || newBis !== iact.bisMin);
    if (!changed) { dragJustEnded = true; iact = null; return; }

    el.dataset.vonMin   = newVon;
    el.dataset.bisMin   = newBis;
    el.dataset.duration = newBis - newVon;
    el.style.left  = ((newVon - GRID_START) / 30 * SLOT_W) + 'px';
    el.style.width = Math.max((newBis - newVon) / 30 * SLOT_W, SLOT_W) + 'px';
    updateTimeLabel(el, newVon, newBis);

    var oldTt = bootstrap.Tooltip.getInstance(el);
    if (oldTt) oldTt.dispose();
    initTooltip(el);

    doSaveMove(iact.id, iact.stationId, iact.maId, minToTime(newVon), minToTime(newBis));
    dragJustEnded = true;
    iact = null;
});

function doSaveMove(id, stationId, maId, zeitVon, zeitBis) {
    fetch('api/fest_dienstplan_move.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&station_id=' + stationId + '&mitarbeiter_id=' + maId
            + '&zeit_von=' + encodeURIComponent(zeitVon) + '&zeit_bis=' + encodeURIComponent(zeitBis)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (!d.success) alert('Fehler: ' + (d.error||'Unbekannt')); });
}

// ── Klick auf leere Timeline → Modal ──────────────────────────────────────
function tlClickHandler(e) {
    if (dragJustEnded) { dragJustEnded = false; return; }
    if (!CAN_WRITE || e.target.closest('.dp-shift')) return;
    var slot   = Math.floor(getScrollX(e.clientX) / SLOT_W);
    var vonMin = slotToMin(slot);
    var bisMin = Math.min(vonMin + 120, GRID_END);
    openModal(parseInt(this.dataset.maId), parseInt(this.dataset.stationId),
              minToTime(vonMin), minToTime(bisMin));
}
document.querySelectorAll('.dp-timeline').forEach(function(tl) {
    tl.addEventListener('click', tlClickHandler);
});

// ── Löschen ────────────────────────────────────────────────────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.dp-del');
    if (!btn) return;
    e.stopPropagation();
    if (!confirm('Schicht löschen?')) return;
    var id = parseInt(btn.dataset.id);
    fetch('api/fest_dienstplan_eintrag.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_method=DELETE&id=' + id
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            var el = document.querySelector('.dp-shift[data-id="' + id + '"]');
            if (!el) return;
            var maId    = parseInt(el.dataset.maId);
            var stId    = parseInt(el.dataset.stationId);
            var tl      = el.closest('.dp-timeline');
            el.remove();

            // Row löschen wenn keine Schichten mehr
            if (tl && !tl.querySelector('.dp-shift')) {
                tl.remove();
                var lr = document.querySelector('.dp-label-row[data-station-id="'+stId+'"][data-ma-id="'+maId+'"]');
                if (lr) lr.remove();
            }
            // Bench-Card aktualisieren
            var anyShift = document.querySelector('.dp-shift[data-ma-id="'+maId+'"]');
            updateBenchCard(maId, !!anyShift);
        }
    });
});

// ── Modal ──────────────────────────────────────────────────────────────────
var schichtModal = new bootstrap.Modal(document.getElementById('schichtModal'));
var sfMa    = document.getElementById('sf-mitarbeiter');
var sfSt    = document.getElementById('sf-station');
var sfVon   = document.getElementById('sf-zeit-von');
var sfBis   = document.getElementById('sf-zeit-bis');
var sfNot   = document.getElementById('sf-notizen');
var sfError = document.getElementById('sf-error');

function openModal(maId, stationId, zeitVon, zeitBis) {
    sfMa.value  = maId || '';
    sfSt.value  = stationId || '';
    sfVon.value = zeitVon || '';
    sfBis.value = zeitBis || '';
    sfNot.value = '';
    sfError.classList.add('d-none');
    schichtModal.show();
}

document.getElementById('btnAddSchicht') &&
document.getElementById('btnAddSchicht').addEventListener('click', function() {
    openModal(null, null, minToTime(GRID_START + 60), minToTime(GRID_START + 180));
});

document.getElementById('sf-save').addEventListener('click', function() {
    var maId = sfMa.value, stId = sfSt.value, von = sfVon.value, bis = sfBis.value;
    if (!maId || !stId || !von || !bis) {
        sfError.textContent = 'Bitte alle Pflichtfelder ausfüllen.';
        sfError.classList.remove('d-none'); return;
    }
    if (von >= bis) {
        sfError.textContent = 'Von-Zeit muss vor Bis-Zeit liegen.';
        sfError.classList.remove('d-none'); return;
    }
    sfError.classList.add('d-none');
    var btn = this; btn.disabled = true;

    fetch('api/fest_dienstplan_eintrag.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'fest_id=' + FEST_ID + '&station_id=' + stId + '&mitarbeiter_id=' + maId
            + '&datum=' + DATUM + '&zeit_von=' + von + '&zeit_bis=' + bis
            + '&notizen=' + encodeURIComponent(sfNot.value)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.disabled = false;
        if (d.success) {
            schichtModal.hide();
            var maId   = parseInt(sfMa.value);
            var stId   = parseInt(sfSt.value);
            var vonStr = sfVon.value;
            var bisStr = sfBis.value;
            var vonMin = timeToMin(vonStr);
            var bisMin = timeToMin(bisStr);

            // Timeline-Zeile finden oder erstellen
            var tl = findOrCreateTimeline(stId, maId);
            if (tl) {
                var shiftEl = createShiftBlock(d.id, vonMin, bisMin, stId, maId, sfNot.value);
                tl.appendChild(shiftEl);
                initTooltip(shiftEl);
            }
            updateBenchCard(maId, true);
        } else {
            sfError.textContent = 'Fehler: ' + (d.error||'');
            sfError.classList.remove('d-none');
        }
    })
    .catch(function() { btn.disabled = false; });
});

// ── Hilfsfunktionen: dynamisches DOM ──────────────────────────────────────

function updateBenchCard(maId, eingeplant) {
    var card = document.querySelector('.dp-bench[data-ma-id="'+maId+'"]');
    if (!card) return;
    var nameDiv   = card.querySelector('.bench-name');
    var checkIcon = card.querySelector('.bench-check');
    if (eingeplant) {
        card.style.background = '#e8f5e9';
        card.style.border     = '2px solid #4caf50';
        card.style.color      = '#2e7d32';
        if (!checkIcon && nameDiv) {
            var ic = document.createElement('i');
            ic.className = 'bi bi-check-circle-fill text-success ms-1 bench-check';
            ic.style.fontSize = '11px';
            nameDiv.appendChild(ic);
        }
    } else {
        card.style.background = '#fff';
        card.style.border     = '2px solid #dee2e6';
        card.style.color      = '#212529';
        if (checkIcon) checkIcon.remove();
    }
}

function createShiftBlock(id, vonMin, bisMin, stId, maId, notizen) {
    var left  = (vonMin - GRID_START) / 30 * SLOT_W;
    var width = Math.max((bisMin - vonMin) / 30 * SLOT_W, SLOT_W);
    var st    = STATIONEN.find(function(s) { return s.id == stId; });
    var color = st ? st.color : '#3b82f6';
    var stName = st ? st.name : '';
    var ma     = MITARBEITER.find(function(m) { return m.id == maId; });
    var maName = ma ? ma.vollname : '';

    var el = document.createElement('div');
    el.className = 'dp-shift';
    el.dataset.id        = id;
    el.dataset.vonMin    = vonMin;
    el.dataset.bisMin    = bisMin;
    el.dataset.duration  = bisMin - vonMin;
    el.dataset.stationId = stId;
    el.dataset.maId      = maId;
    el.setAttribute('data-bs-toggle', 'tooltip');
    el.setAttribute('data-bs-placement', 'top');
    el.setAttribute('data-bs-title', maName + ' @ ' + stName + ' | ' + minToTime(vonMin) + '–' + minToTime(bisMin) + (notizen ? ' | ' + notizen : ''));
    el.style.cssText = 'position:absolute;left:'+left+'px;width:'+width+'px;top:5px;height:'+(ROW_H-10)+'px;'
        + 'background:'+color+';color:#fff;border-radius:6px;'
        + 'padding:3px 20px 3px 20px;font-size:11px;overflow:hidden;'
        + 'box-shadow:0 2px 6px rgba(0,0,0,0.2);user-select:none;z-index:3;box-sizing:border-box;cursor:grab';

    var resL = CAN_WRITE ? '<div class="dp-resize-left" style="position:absolute;left:0;top:0;bottom:0;width:10px;cursor:ew-resize;z-index:6;border-radius:6px 0 0 6px;background:rgba(0,0,0,0.18)"></div>' : '';
    var resR = CAN_WRITE ? '<div class="dp-resize-right" style="position:absolute;right:0;top:0;bottom:0;width:10px;cursor:ew-resize;z-index:6;border-radius:0 6px 6px 0;background:rgba(0,0,0,0.18)"></div>' : '';
    var delBtn = CAN_DELETE ? '<button class="dp-del" data-id="'+id+'" style="position:absolute;top:3px;right:12px;border:none;background:rgba(0,0,0,0.28);color:#fff;border-radius:50%;width:14px;height:14px;font-size:12px;line-height:13px;cursor:pointer;padding:0;display:flex;align-items:center;justify-content:center;z-index:7">×</button>' : '';

    el.innerHTML = resL
        + '<div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.5">'+minToTime(vonMin)+' – '+minToTime(bisMin)+'</div>'
        + '<div class="dp-shift-time" style="font-size:10px;opacity:0.85">'+(bisMin-vonMin)+' Min</div>'
        + resR + delBtn;
    return el;
}

function createTimelineRow(stId, maId, color, stName, ma) {
    var tl = document.createElement('div');
    tl.className = 'dp-timeline dp-bgrid-tl';
    tl.dataset.stationId    = stId;
    tl.dataset.stationName  = stName;
    tl.dataset.stationColor = color;
    tl.dataset.maId         = maId;
    tl.dataset.maName       = ma.vollname;
    tl.style.cssText = 'height:'+ROW_H+'px;position:relative;border-bottom:1px solid #dee2e6;'
        + 'background-color:#fff;cursor:crosshair';
    // Ghost
    var ghost = document.createElement('div');
    ghost.className = 'dp-ghost';
    ghost.style.cssText = 'display:none;position:absolute;top:5px;height:'+(ROW_H-10)+'px;'
        + 'background:rgba(13,110,253,0.15);border:2px dashed #0d6efd;border-radius:6px;'
        + 'pointer-events:none;z-index:6;box-sizing:border-box';
    tl.appendChild(ghost);
    tl.addEventListener('click', tlClickHandler);
    return tl;
}

function createLabelRow(stId, maId, color, ma) {
    var lr = document.createElement('div');
    lr.className = 'dp-label-row';
    lr.draggable = true;
    lr.dataset.stationId = stId;
    lr.dataset.maId      = maId;
    lr.style.cssText = 'height:'+ROW_H+'px;padding:4px 6px 4px 4px;border-bottom:1px solid #dee2e6;'
        + 'background:#fff;display:flex;align-items:center;gap:4px;'
        + 'border-left:3px solid '+color+'55;cursor:grab';
    var grip = document.createElement('i');
    grip.className = 'bi bi-grip-vertical';
    grip.style.cssText = 'font-size:14px;color:#adb5bd;flex-shrink:0';
    lr.appendChild(grip);
    var inner = document.createElement('div');
    inner.style.cssText = 'overflow:hidden';
    var nameDiv = document.createElement('div');
    nameDiv.className = 'bench-name';
    nameDiv.style.cssText = 'font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis';
    nameDiv.textContent = ma.vollname;
    inner.appendChild(nameDiv);
    if (ma.funktion) {
        var fnDiv = document.createElement('div');
        fnDiv.style.cssText = 'font-size:10px;color:#6c757d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis';
        fnDiv.textContent = ma.funktion;
        inner.appendChild(fnDiv);
    }
    lr.appendChild(inner);
    initLabelRowDrag(lr);
    return lr;
}

function findOrCreateTimeline(stId, maId) {
    var tl = document.querySelector('.dp-timeline[data-station-id="'+stId+'"][data-ma-id="'+maId+'"]');
    if (tl) return tl;

    var dropzone = document.querySelector('.dp-dropzone[data-station-id="'+stId+'"]');
    if (!dropzone) return null;
    var st    = STATIONEN.find(function(s) { return s.id == stId; });
    var color = st ? st.color : '#3b82f6';
    var stName = st ? st.name : '';
    var ma    = MITARBEITER.find(function(m) { return m.id == maId; });
    if (!ma) return null;

    // Rechte Zeile vor Drop-Zone einfügen
    tl = createTimelineRow(stId, maId, color, stName, ma);
    dropzone.parentNode.insertBefore(tl, dropzone);

    // Linke Label-Zeile vor Drop-Zone-Spacer einfügen
    var lDropzone = document.querySelector('.dp-label-dropzone[data-station-id="'+stId+'"]');
    if (lDropzone) {
        var lr = createLabelRow(stId, maId, color, ma);
        lDropzone.parentNode.insertBefore(lr, lDropzone);
    }
    return tl;
}

// ── Mitarbeiter-Bank: Drag & Drop ──────────────────────────────────────────
var benchDrag = null;

document.querySelectorAll('.dp-bench').forEach(function(card) {
    card.addEventListener('dragstart', function(e) {
        benchDrag = { maId: card.dataset.maId, maName: card.dataset.maName };
        e.dataTransfer.effectAllowed = 'copy';
        e.dataTransfer.setData('text/plain', card.dataset.maId);
        card.style.opacity = '0.5';
    });
    card.addEventListener('dragend', function() {
        card.style.opacity = '';
        benchDrag = null;
        document.querySelectorAll('.dp-dropzone.dp-over').forEach(function(dz) {
            dz.classList.remove('dp-over');
            dz.style.borderColor = 'transparent';
            dz.style.background  = '#fafafa';
            var g = dz.querySelector('.dp-ghost');
            if (g) g.style.display = 'none';
        });
    });
});

function initDropZone(dz) {
    dz.addEventListener('dragover', function(e) {
        if (!benchDrag) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        dz.classList.add('dp-over');
        dz.style.borderColor = '#0d6efd';
        dz.style.background  = '#eff6ff';

        var sc         = document.getElementById('dp-scroll');
        var scrollRect = sc.getBoundingClientRect();
        var relX  = e.clientX - scrollRect.left + sc.scrollLeft;
        var slot  = Math.floor(relX / SLOT_W);
        var vonMin = slotToMin(slot);
        var bisMin = Math.min(vonMin + 120, GRID_END);
        var ghost = dz.querySelector('.dp-ghost');
        if (ghost) {
            ghost.style.display = 'block';
            ghost.style.left    = ((vonMin - GRID_START) / 30 * SLOT_W) + 'px';
            ghost.style.width   = ((bisMin - vonMin) / 30 * SLOT_W) + 'px';
        }
    });
    dz.addEventListener('dragleave', function(e) {
        if (!dz.contains(e.relatedTarget)) {
            dz.classList.remove('dp-over');
            dz.style.borderColor = 'transparent';
            dz.style.background  = '#fafafa';
            var g = dz.querySelector('.dp-ghost');
            if (g) g.style.display = 'none';
        }
    });
    dz.addEventListener('drop', function(e) {
        if (!benchDrag) return;
        e.preventDefault();
        dz.classList.remove('dp-over');
        dz.style.borderColor = 'transparent';
        dz.style.background  = '#fafafa';
        var g = dz.querySelector('.dp-ghost');
        if (g) g.style.display = 'none';

        var sc         = document.getElementById('dp-scroll');
        var scrollRect = sc.getBoundingClientRect();
        var relX  = e.clientX - scrollRect.left + sc.scrollLeft;
        var slot  = Math.floor(relX / SLOT_W);
        var vonMin = slotToMin(slot);
        var bisMin = Math.min(vonMin + 120, GRID_END);

        openModal(parseInt(benchDrag.maId), parseInt(dz.dataset.stationId),
                  minToTime(vonMin), minToTime(bisMin));
    });
}

document.querySelectorAll('.dp-dropzone').forEach(initDropZone);

// ── Zeilen-Sortierung (Mitarbeiter-Reihenfolge) ────────────────────────────
var rowDrag = null; // { labelEl, timelineEl, stationId }

function initLabelRowDrag(lr) {
    lr.addEventListener('dragstart', function(e) {
        if (benchDrag) return; // Bench-Drag hat Vorrang
        rowDrag = {
            labelEl:    lr,
            timelineEl: document.querySelector('.dp-timeline[data-station-id="'+lr.dataset.stationId+'"][data-ma-id="'+lr.dataset.maId+'"]'),
            stationId:  lr.dataset.stationId,
        };
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', lr.dataset.maId);
        setTimeout(function() { lr.style.opacity = '0.4'; }, 0);
    });
    lr.addEventListener('dragend', function() {
        lr.style.opacity = '';
        document.querySelectorAll('.dp-label-row.row-over, .dp-label-dropzone.row-over').forEach(function(el) {
            el.classList.remove('row-over');
            el.style.borderTop = '';
        });
        rowDrag = null;
    });
    lr.addEventListener('dragover', function(e) {
        if (!rowDrag || rowDrag.labelEl === lr) return;
        if (lr.dataset.stationId !== rowDrag.stationId) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        document.querySelectorAll('.dp-label-row.row-over, .dp-label-dropzone.row-over').forEach(function(el) {
            el.classList.remove('row-over');
            el.style.borderTop = '';
        });
        lr.classList.add('row-over');
        lr.style.borderTop = '2px solid #0d6efd';
    });
    lr.addEventListener('dragleave', function(e) {
        if (!lr.contains(e.relatedTarget)) {
            lr.classList.remove('row-over');
            lr.style.borderTop = '';
        }
    });
    lr.addEventListener('drop', function(e) {
        if (!rowDrag || rowDrag.labelEl === lr) return;
        if (lr.dataset.stationId !== rowDrag.stationId) return;
        e.preventDefault();
        lr.classList.remove('row-over');
        lr.style.borderTop = '';

        // Linke Spalte: Label vor Ziel einfügen
        lr.parentNode.insertBefore(rowDrag.labelEl, lr);

        // Rechte Spalte: Timeline vor Ziel-Timeline einfügen
        var targetTl = document.querySelector('.dp-timeline[data-station-id="'+lr.dataset.stationId+'"][data-ma-id="'+lr.dataset.maId+'"]');
        if (targetTl && rowDrag.timelineEl) {
            targetTl.parentNode.insertBefore(rowDrag.timelineEl, targetTl);
        }
    });
}

// Drop auf Drop-Zone-Spacer (ans Ende sortieren)
document.querySelectorAll('.dp-label-dropzone').forEach(function(dz) {
    dz.addEventListener('dragover', function(e) {
        if (!rowDrag || dz.dataset.stationId !== rowDrag.stationId) return;
        e.preventDefault();
        dz.classList.add('row-over');
        dz.style.borderTop = '2px solid #0d6efd';
    });
    dz.addEventListener('dragleave', function(e) {
        if (!dz.contains(e.relatedTarget)) {
            dz.classList.remove('row-over');
            dz.style.borderTop = '';
        }
    });
    dz.addEventListener('drop', function(e) {
        if (!rowDrag || dz.dataset.stationId !== rowDrag.stationId) return;
        e.preventDefault();
        dz.classList.remove('row-over');
        dz.style.borderTop = '';
        // Ans Ende (vor Drop-Zone) verschieben
        dz.parentNode.insertBefore(rowDrag.labelEl, dz);
        var rightDz = document.querySelector('.dp-dropzone[data-station-id="'+dz.dataset.stationId+'"]');
        if (rightDz && rowDrag.timelineEl) {
            rightDz.parentNode.insertBefore(rowDrag.timelineEl, rightDz);
        }
    });
});

document.querySelectorAll('.dp-label-row').forEach(initLabelRowDrag);

// ── Dynamische Skalierung (volle Breite) ───────────────────────────────────
var SLOT_COUNT  = (GRID_END - GRID_START) / 30;
var LABEL_W_JS  = <?= $LABEL_W ?>;

function applySlotWidth(newSW) {
    document.documentElement.style.setProperty('--dp-sw', newSW + 'px');

    var inner = document.getElementById('dp-inner');
    if (inner) inner.style.minWidth = (SLOT_COUNT * newSW) + 'px';

    document.querySelectorAll('.dp-time-label').forEach(function(el) {
        el.style.left = (parseInt(el.dataset.slotIdx) * newSW) + 'px';
    });
    document.querySelectorAll('.dp-oef-marker').forEach(function(el) {
        el.style.left  = ((parseInt(el.dataset.vonMin) - GRID_START) / 30 * newSW) + 'px';
        el.style.width = ((parseInt(el.dataset.bisMin) - parseInt(el.dataset.vonMin)) / 30 * newSW) + 'px';
    });
    document.querySelectorAll('.dp-oef-bg-before').forEach(function(el) {
        el.style.width = ((parseInt(el.dataset.vonMin) - GRID_START) / 30 * newSW) + 'px';
    });
    document.querySelectorAll('.dp-oef-bg-after').forEach(function(el) {
        el.style.left = ((parseInt(el.dataset.bisMin) - GRID_START) / 30 * newSW) + 'px';
    });
    document.querySelectorAll('.dp-shift').forEach(function(el) {
        el.style.left  = ((parseInt(el.dataset.vonMin) - GRID_START) / 30 * newSW) + 'px';
        el.style.width = Math.max((parseInt(el.dataset.bisMin) - parseInt(el.dataset.vonMin)) / 30 * newSW, newSW) + 'px';
    });
    SLOT_W = newSW;
}

function rescaleGrid() {
    var sc = document.getElementById('dp-scroll');
    if (!sc) return;
    var available = sc.clientWidth - 4;
    if (available < 200) return; // Mobile: nicht skalieren
    applySlotWidth(Math.max(36, Math.floor(available / SLOT_COUNT)));
}

// Beim Laden und bei Fenster-Größenänderung
window.addEventListener('load', rescaleGrid);
var _dpResizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(_dpResizeTimer);
    _dpResizeTimer = setTimeout(rescaleGrid, 60);
});

// ── PDF im Browser erstellen ───────────────────────────────────────────────
document.getElementById('btnPDF') && document.getElementById('btnPDF').addEventListener('click', function() {
    generatePDF();
});

function loadScript(src) {
    if (document.querySelector('script[src="' + src + '"]')) return Promise.resolve();
    return new Promise(function(resolve) {
        var s = document.createElement('script');
        s.src = src; s.onload = resolve; document.head.appendChild(s);
    });
}

function generatePDF() {
    var btn = document.getElementById('btnPDF');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>PDF…';

    var h2cSrc  = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
    var jPdfSrc = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';

    Promise.all([loadScript(h2cSrc), loadScript(jPdfSrc)]).then(function() {
        doPDF(btn);
    });
}

function doPDF(btn) {
    var card     = document.getElementById('dp-grid-card');
    var sc       = document.getElementById('dp-scroll');
    var inner    = document.getElementById('dp-inner');
    var lbls     = document.getElementById('dp-labels');
    var cardBody = card.querySelector('.card-body');

    // Für die Aufnahme: feste Slot-Breite → gesamte Zeitachse sichtbar
    var PDF_SW = 56;
    applySlotWidth(PDF_SW);

    // Alle Overflow-Ebenen öffnen damit html2canvas nichts clippt
    var saved = [
        { el: card,     ov: card.style.overflow,     w: card.style.width     },
        { el: cardBody, ov: cardBody.style.overflow,  w: cardBody.style.width  },
        { el: sc,       ov: sc.style.overflow,        w: sc.style.width        },
    ];
    var captureW = lbls.offsetWidth + (SLOT_COUNT + 1) * PDF_SW + 20;
    card.style.overflow     = 'visible'; card.style.width     = captureW + 'px';
    cardBody.style.overflow = 'visible'; cardBody.style.width = captureW + 'px';
    sc.style.overflow       = 'visible'; sc.style.width       = (captureW - lbls.offsetWidth) + 'px';
    if (inner) inner.style.minWidth = ((SLOT_COUNT + 1) * PDF_SW) + 'px';

    requestAnimationFrame(function() {
        html2canvas(card, {
            scale:       1.5,
            useCORS:     true,
            logging:     false,
            windowWidth: captureW + 100,
        }).then(function(canvas) {
            // Alle Styles zurücksetzen
            saved.forEach(function(s) { s.el.style.overflow = s.ov; s.el.style.width = s.w; });
            rescaleGrid();

            // PDF erstellen – A4 Querformat, 8mm Rand
            var mmPerPx = 25.4 / 96;
            var scale   = 1.5;
            var imgWmm  = canvas.width  / scale * mmPerPx;
            var imgHmm  = canvas.height / scale * mmPerPx;
            var pageWmm = 297 - 16;   // A4 landscape minus 2×8mm
            var pageHmm = 210 - 24;   // minus 8mm oben + 16mm für Titel unten
            var ratio   = Math.min(pageWmm / imgWmm, pageHmm / imgHmm);

            var doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
            doc.setFontSize(9);
            doc.setTextColor(80);
            doc.text(
                'Dienstplan – <?= addslashes($fest['name']) ?> – <?= date('d.m.Y', strtotime($filterDatum)) ?>',
                8, 7
            );
            doc.addImage(canvas, 'PNG', 8, 10, imgWmm * ratio, imgHmm * ratio);

            // Im Browser öffnen
            var blob = doc.output('blob');
            var url  = URL.createObjectURL(blob);
            window.open(url, '_blank');

            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-file-pdf"></i> PDF erstellen';
        }).catch(function(err) {
            saved.forEach(function(s) { s.el.style.overflow = s.ov; s.el.style.width = s.w; });
            rescaleGrid();
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-file-pdf"></i> PDF erstellen';
            alert('PDF-Fehler: ' + err.message);
        });
    });
}
</script>
