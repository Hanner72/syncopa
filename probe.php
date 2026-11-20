<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('probe', 'lesen');

$db          = Database::getInstance();
$benutzerId  = Session::getUserId();
$formationId = Session::getFormationId();
$isLeiter    = Session::checkPermission('probe', 'schreiben');

// Aktive Session des Kapellmeisters laden
$sessionSql    = "SELECT ps.*, n.titel as noten_titel, n.id as noten_id FROM probe_session ps
                  JOIN noten n ON ps.noten_id = n.id WHERE ps.aktiv = 1";
$sessionParams = [];
if ($formationId) {
    $sessionSql    .= " AND (ps.formation_id = ? OR ps.formation_id IS NULL)";
    $sessionParams[] = $formationId;
}
$sessionSql .= " ORDER BY ps.gestartet_am DESC LIMIT 1";
$session = $db->fetchOne($sessionSql, $sessionParams);

// Modus: 'live' = mit der Kapelle, 'warten' = auf den Kapellmeister warten, 'ueben' = selbst üben, 'auswahl' = Stück zum Üben wählen
$uebenParam = $_GET['ueben'] ?? null;
$buchId     = (int)($_GET['buch'] ?? 0);
if ($uebenParam !== null) {
    $modus = (int)$uebenParam > 0 ? 'ueben' : 'auswahl';
} else {
    $modus = $session ? 'live' : (isset($_GET['kapelle']) ? 'warten' : 'auswahl');
}

// Notenbücher, die der Benutzer sehen darf (eigene + geteilte der Formation)
$nbCond   = Session::isAdmin() ? "OR nb.typ = 'geteilt'" : "OR (nb.typ = 'geteilt' AND nb.formation_id = ?)";
$nbParams = Session::isAdmin() ? [$benutzerId] : [$benutzerId, $formationId ?: 0];

// Nur Stücke mit eingerichteten Stimmen, eingeschränkt auf die aktive Formation
$formFilter = Formation::getFilterCondition($formationId, 'n');
$stueckCond = "EXISTS (SELECT 1 FROM noten_stimmen s WHERE s.noten_id = n.id)"
            . ($formFilter['condition'] ? " AND " . $formFilter['condition'] : "");

$notenbuecher = $db->fetchAll(
    "SELECT nb.id, nb.name, nb.typ,
            (SELECT COUNT(*) FROM notenbuch_noten nbn JOIN noten n ON n.id = nbn.noten_id
             WHERE nbn.notenbuch_id = nb.id AND {$stueckCond}) AS anzahl
     FROM notenbucher nb
     WHERE nb.benutzer_id = ? {$nbCond}
     ORDER BY nb.typ ASC, nb.name",
    array_merge($formFilter['params'], $nbParams)
);
$buch = null;
foreach ($notenbuecher as $nb) {
    if ((int)$nb['id'] === $buchId) { $buch = $nb; }
}
if (!$buch) { $buchId = 0; }

// Stückliste: ganzes Notenbuch oder alle Stücke
if ($buch) {
    $stueckListe = $db->fetchAll(
        "SELECT n.id, n.titel, n.komponist FROM notenbuch_noten nbn JOIN noten n ON n.id = nbn.noten_id
         WHERE nbn.notenbuch_id = ? AND {$stueckCond} ORDER BY nbn.reihenfolge, n.titel",
        array_merge([$buchId], $formFilter['params'])
    );
} elseif ($modus === 'auswahl') {
    $stueckListe = $db->fetchAll(
        "SELECT n.id, n.titel, n.komponist FROM noten n WHERE {$stueckCond} ORDER BY n.titel",
        $formFilter['params']
    );
} else {
    $stueckListe = [];
}

// Stück zum Anzeigen bestimmen
$stueck = null;
if ($modus === 'live') {
    $stueck = ['id' => (int)$session['noten_id'], 'titel' => $session['noten_titel']];
} elseif ($modus === 'ueben') {
    $stueck = $db->fetchOne(
        "SELECT n.id, n.titel, n.komponist FROM noten n WHERE n.id = ? AND {$stueckCond}",
        array_merge([(int)$uebenParam], $formFilter['params'])
    );
    if (!$stueck) {
        Session::setFlashMessage('danger', 'Dieses Stück ist nicht verfügbar.');
        header('Location: probe.php?ueben=0');
        exit;
    }
}

// Vorheriges / nächstes Stück im Notenbuch
$vorher = $nachher = null;
if ($modus === 'ueben' && $buch) {
    $ids = array_map('intval', array_column($stueckListe, 'id'));
    $pos = array_search((int)$stueck['id'], $ids, true);
    if ($pos !== false) {
        $vorher  = $stueckListe[$pos - 1] ?? null;
        $nachher = $stueckListe[$pos + 1] ?? null;
    }
}

$stimmen       = [];
$activeStimme  = null;
$favoriten     = [];
$pdfUrl        = null;
$seiteVon      = 1;
$seiteBis      = 1;

if ($stueck) {
    $stimmen = $db->fetchAll(
        "SELECT ns.*, nd.dateiname as dateiname FROM noten_stimmen ns
         LEFT JOIN noten_dateien nd ON ns.datei_id = nd.id
         WHERE ns.noten_id = ? ORDER BY ns.reihenfolge, ns.name",
        [$stueck['id']]
    );

    $favoriten = $db->fetchAll(
        "SELECT nf.stimme_id, ns.name FROM noten_favoriten nf
         JOIN noten_stimmen ns ON nf.stimme_id = ns.id
         WHERE nf.benutzer_id = ? AND ns.noten_id = ?
         ORDER BY ns.name",
        [$benutzerId, $stueck['id']]
    );
    $stimmenIds = array_map('intval', array_column($stimmen, 'id'));

    $activeStimmeId = null;
    if ($modus === 'live') {
        // Aktuell gewählte Stimme aus session_spieler (neuester Eintrag)
        $spieler = $db->fetchOne(
            "SELECT stimme_id FROM probe_session_spieler
             WHERE session_id = ? AND benutzer_id = ?
             ORDER BY joined_am DESC, id DESC LIMIT 1",
            [$session['id'], $benutzerId]
        );
        $activeStimmeId = $spieler ? (int)$spieler['stimme_id'] : null;
    } else {
        // Üben: gewählte Stimme, sonst gleichnamige Stimme vom vorigen Stück
        $gewaehlt = (int)($_GET['stimme'] ?? 0);
        if (in_array($gewaehlt, $stimmenIds, true)) {
            $activeStimmeId = $gewaehlt;
        } elseif (!empty($_GET['sn'])) {
            foreach ($stimmen as $st) {
                if (mb_strtolower($st['name']) === mb_strtolower($_GET['sn'])) { $activeStimmeId = (int)$st['id']; break; }
            }
        }
    }

    // Auto-Auswahl: erster Favorit dieses Stücks
    if (!$activeStimmeId && !empty($favoriten)) {
        $activeStimmeId = (int)$favoriten[0]['stimme_id'];
        if ($modus === 'live') {
            $db->query(
                "INSERT INTO probe_session_spieler (session_id, benutzer_id, stimme_id, joined_am)
                 VALUES (?,?,?,NOW())",
                [$session['id'], $benutzerId, $activeStimmeId]
            );
        }
    }

    foreach ($stimmen as $st) {
        if ((int)$st['id'] === $activeStimmeId) { $activeStimme = $st; break; }
    }
}

// Adresse dieser Ansicht (für Formulare und Links)
function pultUrl(array $param): string {
    $param = array_filter($param, fn($v) => $v !== null && $v !== '' && $v !== 0);
    return 'probe.php' . ($param ? '?' . http_build_query($param) : '');
}
$hierUrl = $modus === 'live'
    ? 'probe.php'
    : pultUrl(['ueben' => $stueck ? (int)$stueck['id'] : '0', 'buch' => $buchId, 'stimme' => $activeStimme ? (int)$activeStimme['id'] : 0]);
$sn = $activeStimme ? $activeStimme['name'] : null;

// Stimme wählen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_stimme']) && $stueck) {
    $stimmeId = (int)($_POST['stimme_id'] ?? 0);
    $saveFav  = !empty($_POST['save_favorit']);
    if ($stimmeId && in_array($stimmeId, $stimmenIds, true)) {
        if ($modus === 'live') {
            $db->query(
                "INSERT INTO probe_session_spieler (session_id, benutzer_id, stimme_id, joined_am)
                 VALUES (?,?,?,NOW())",
                [$session['id'], $benutzerId, $stimmeId]
            );
        }
        if ($saveFav) {
            $db->query(
                "INSERT IGNORE INTO noten_favoriten (benutzer_id, stimme_id) VALUES (?,?)",
                [$benutzerId, $stimmeId]
            );
        }
    }
    header('Location: ' . ($modus === 'live' ? 'probe.php'
        : pultUrl(['ueben' => (int)$stueck['id'], 'buch' => $buchId, 'stimme' => $stimmeId])));
    exit;
}

// Favorit löschen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_favorit'])) {
    $stimmeId = (int)($_POST['stimme_id'] ?? 0);
    if ($stimmeId) {
        $db->query(
            "DELETE FROM noten_favoriten WHERE benutzer_id=? AND stimme_id=?",
            [$benutzerId, $stimmeId]
        );
    }
    header('Location: ' . $hierUrl);
    exit;
}

// PDF-URL bestimmen
if ($activeStimme) {
    $seiteVon = (int)$activeStimme['seite_von'];
    $seiteBis = (int)$activeStimme['seite_bis'];
    if ($activeStimme['dateiname']) {
        $pdfUrl = 'uploads/noten/' . rawurlencode($activeStimme['dateiname']);
    } else {
        // Fallback: erste Datei des Notenwerks
        $ersteDatei = $db->fetchOne(
            "SELECT dateiname FROM noten_dateien WHERE noten_id=? ORDER BY sortierung LIMIT 1",
            [$stueck['id']]
        );
        if ($ersteDatei) {
            $pdfUrl   = 'uploads/noten/' . rawurlencode($ersteDatei['dateiname']);
            $seiteVon = 1;
            $seiteBis = 9999;
        }
    }
} elseif ($modus === 'live' && $isLeiter) {
    // Dirigent: erste Datei, alle Seiten
    $ersteDatei = $db->fetchOne(
        "SELECT dateiname FROM noten_dateien WHERE noten_id=? ORDER BY sortierung LIMIT 1",
        [$stueck['id']]
    );
    if ($ersteDatei) {
        $pdfUrl   = 'uploads/noten/' . rawurlencode($ersteDatei['dateiname']);
        $seiteVon = 1;
        $seiteBis = 9999;
    }
}
$flash = Session::getFlashMessage();
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light') ?>" data-bs-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(Sicherheit::pruefzeichen(), ENT_QUOTES); ?>">
    <script src="assets/js/csrf.js?v=<?php echo urlencode(APP_VERSION); ?>"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Notenpult – <?= $stueck ? htmlspecialchars($stueck['titel']) : ($modus === 'auswahl' ? 'Selbst üben' : 'Kein Stück aktiv') ?></title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="assets/js/libs/pdf.min.js"></script>
    <style>
        :root {
            --c-primary: #4471A3;
            --bg-sidebar: #2d4a6a;
            --sidebar-text: #a8b8c8;
            --radius: 4px;
            --sidebar-w: 280px;
        }
        [data-theme="light"] {
            --bg-body: #f5f6f8; --bg-card: #fff; --border: #e0e4e8;
            --text-primary: #2c3e50; --text-muted: #8699ac;
        }
        [data-theme="dark"] {
            --bg-body: #1a2332; --bg-card: #243042; --border: #3a4a5d;
            --text-primary: #e8eef4; --text-muted: #708090;
        }

        * { box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }
        body { margin: 0; background: var(--bg-body); color: var(--text-primary); overflow: hidden; }

        .probe-layout { display: flex; height: 100vh; }

        /* Sidebar */
        .probe-sidebar {
            width: var(--sidebar-w); flex-shrink: 0;
            background: var(--bg-sidebar);
            display: flex; flex-direction: column;
            overflow-y: auto; transition: width 0.25s;
        }
        .probe-sidebar.collapsed { width: 0; overflow: hidden; }
        .probe-sidebar-inner { padding: 12px; min-width: var(--sidebar-w); }
        .sidebar-header-row {
            display: flex; align-items: center; gap: 8px;
            padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 10px;
        }
        .sidebar-title { color: #fff; font-weight: 600; font-size: 14px; flex: 1; }
        .sidebar-back { color: var(--sidebar-text); font-size: 20px; text-decoration: none; }
        .sidebar-back:hover { color: #fff; }
        .sidebar-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.8px;
                          color: #5a6a7a; margin: 10px 0 4px; }

        .stimme-form select, .stimme-form button { font-size: 12px; }
        .stimme-form .form-select {
            background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.15);
            color: #fff; padding: 6px 10px;
        }
        .stimme-form .form-select option { background: #2d4a6a; }

        .fav-btn {
            display: flex; align-items: center; justify-content: space-between;
            width: 100%; padding: 5px 8px; margin-bottom: 3px;
            background: rgba(255,255,255,0.07); border: 1px solid transparent;
            border-radius: var(--radius); color: var(--sidebar-text);
            font-size: 12px; cursor: pointer; text-align: left;
        }
        .fav-btn:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .fav-btn.active { background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.2); }
        .fav-del { color: #c06060; font-size: 14px; padding: 0 2px; line-height: 1; background: none; border: none; cursor: pointer; }
        .fav-del:hover { color: #ff8080; }

        .active-stimme-badge {
            background: rgba(255,255,255,0.15); border-radius: var(--radius);
            padding: 6px 10px; color: #fff; font-size: 12px; font-weight: 500;
        }

        a.fav-btn { text-decoration: none; }
        a.fav-btn.justify-content-center { justify-content: center; }

        /* Umschalter Kapelle / Üben */
        .modus-switch { display: flex; background: rgba(0,0,0,0.18); border-radius: 6px; padding: 3px; gap: 3px; }
        .modus-switch a {
            flex: 1; text-align: center; padding: 6px 4px; border-radius: 4px; font-size: 12px;
            color: var(--sidebar-text); text-decoration: none; position: relative; white-space: nowrap;
        }
        .modus-switch a:hover { color: #fff; }
        .modus-switch a.active { background: var(--c-primary); color: #fff; }
        .live-dot {
            position: absolute; top: 4px; right: 5px; width: 8px; height: 8px; border-radius: 50%;
            background: #e8590c; animation: pulse-orange 1.2s ease-in-out infinite;
        }

        /* Stückauswahl zum Üben */
        .probe-canvas-area.auswahl { display: block; overflow-y: auto; }
        .auswahl-inner { max-width: 760px; margin: 0 auto; padding: 24px 16px 40px; }
        .auswahl-inner .list-group-item { background: var(--bg-card); color: var(--text-primary); border-color: var(--border); }
        .auswahl-inner .list-group-item-action:hover { background: rgba(68,113,163,0.08); }
        .auswahl-inner .text-muted { color: var(--text-muted) !important; }

        @media (max-width: 600px) {
            .modus-auswahl.probe-layout { flex-direction: column; overflow-y: auto; }
            .modus-auswahl .probe-sidebar { width: 100%; flex-shrink: 0; overflow: visible; }
            .modus-auswahl .probe-sidebar-inner { min-width: 0; }
            .modus-auswahl .probe-main, .modus-auswahl .probe-canvas-area { overflow: visible; flex: none; }
        }

        /* Hauptinhalt */
        .probe-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }

        /* Canvas-Bereich: kein Scroll, kein Padding – eine Seite füllt alles */
        .probe-canvas-area {
            flex: 1; overflow: hidden; position: relative;
            display: flex; justify-content: center; align-items: center;
            background: var(--bg-body);
        }
        #canvas-inner { position: relative; display: inline-block; line-height: 0; }
        #pdf-canvas   { display: block; }

        /* Floating Menü-Button (sichtbar wenn Sidebar collapsed) */
        #btn-menu-float {
            display: none; position: fixed; top: 12px; left: 12px; z-index: 9000;
            background: rgba(0,0,0,0.55); color: #fff; border: none;
            border-radius: var(--radius); padding: 8px 13px; font-size: 18px; cursor: pointer;
        }

        /* Floating Notizen-Button (immer sichtbar wenn PDF aktiv) */
        #btn-drawing-float {
            position: fixed; top: 12px; right: 12px; z-index: 9000;
            background: rgba(0,0,0,0.55); color: #fff; border: none;
            border-radius: var(--radius); padding: 8px 13px; font-size: 18px; cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }
        #btn-drawing-float.active {
            background: var(--c-primary);
        }

        /* Pending-Benachrichtigung: kleiner Toast + blinkender Wechsel-Button */
        @keyframes pulse-orange {
            0%, 100% { background: rgba(200,90,0,0.92); box-shadow: 0 0 0 0 rgba(200,90,0,0.5); }
            50%       { background: rgba(230,120,0,1);   box-shadow: 0 0 0 7px rgba(200,90,0,0); }
        }
        #btn-accept-new {
            display: none; position: fixed; top: 58px; right: 12px; z-index: 9000;
            background: rgba(200,90,0,0.92); color: #fff; border: none;
            border-radius: var(--radius); padding: 7px 12px; font-size: 12px;
            cursor: pointer; font-weight: 600; white-space: nowrap;
            animation: pulse-orange 1.2s ease-in-out infinite;
            -webkit-tap-highlight-color: transparent;
        }
        #pending-toast {
            display: none; position: fixed; top: 12px; left: 50%; transform: translateX(-50%);
            z-index: 9100; background: rgba(30,30,30,0.88); color: #fff;
            border-radius: 20px; padding: 6px 16px; font-size: 12px;
            pointer-events: none; white-space: nowrap;
            transition: opacity 0.5s;
        }

        /* Tap-Zonen für Seitennavigation (links/rechts) */
        .tap-zone {
            position: absolute; top: 0; height: 100%; width: 18%; z-index: 50;
            cursor: pointer; -webkit-tap-highlight-color: transparent; user-select: none;
        }
        .tap-prev { left: 0; }
        .tap-next { right: 0; }

        /* Warten */
        .wait-screen {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            color: var(--text-muted); gap: 16px;
        }

        /* --vh fix für iOS */
        .probe-layout { height: calc(var(--vh, 1vh) * 100); }

        @media (max-width: 600px) {
            :root { --sidebar-w: 100vw; }
        }

        /* Bottom-Toolbar */
        .probe-bottombar {
            flex-shrink: 0;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
            background: var(--bg-card); border-top: 1px solid var(--border);
            padding: 4px 8px; gap: 4px; min-height: 48px;
        }
        .pbt-group { display: flex; align-items: center; gap: 3px; }
        .pbt-btn {
            background: none; border: 1px solid transparent; border-radius: 4px;
            color: var(--text-primary); font-size: 16px;
            padding: 5px 9px; cursor: pointer; min-width: 36px; min-height: 36px;
            display: flex; align-items: center; justify-content: center;
            -webkit-tap-highlight-color: transparent;
        }
        .pbt-btn:active, .pbt-btn:hover { background: rgba(128,128,128,0.12); }
        .pbt-btn.active { background: rgba(68,113,163,0.15); border-color: var(--c-primary); color: var(--c-primary); }
        .pbt-text { font-size: 12px; min-width: 36px; text-align: center; color: var(--text-primary); }
        .pbt-color { width: 28px; height: 28px; border: 1px solid var(--border); padding: 1px; border-radius: 4px; cursor: pointer; vertical-align: middle; }
        .pbt-slider { width: 64px; cursor: pointer; vertical-align: middle; accent-color: var(--c-primary); }
        .pbt-status { font-size: 11px; min-width: 24px; text-align: center; opacity: 0; transition: opacity 0.3s; color: var(--text-muted); }

        /* Stempel-Palette */
        .stamp-palette {
            flex-shrink: 0;
            display: flex; align-items: center; flex-wrap: wrap; gap: 4px;
            background: var(--bg-card); border-top: 1px solid var(--border);
            padding: 6px 8px;
        }
        .stamp-sep {
            width: 1px; height: 22px; background: var(--border); flex-shrink: 0; margin: 0 2px;
        }
        .stamp-btn {
            background: none; border: 1px solid var(--border); border-radius: 3px;
            padding: 2px 7px; cursor: pointer; min-width: 30px; min-height: 28px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 13px; color: var(--text-primary);
            -webkit-tap-highlight-color: transparent;
        }
        .stamp-btn:hover, .stamp-btn:active { background: rgba(128,128,128,0.15); }
        .stamp-btn.active { background: rgba(68,113,163,0.15); border-color: var(--c-primary); color: var(--c-primary); }
        /* Dynamikzeichen kursiv-serif (wie echte Notenschrift) */
        .stamp-btn.dyn { font-style: italic; font-family: 'Times New Roman', serif; font-weight: bold; font-size: 14px; }
    </style>
</head>
<body>

<button id="btn-menu-float" title="Menü einblenden">☰</button>
<?php if ($stueck && $pdfUrl): ?>
<button id="btn-drawing-float" title="Notizen ein-/ausschalten"><i class="bi bi-pencil"></i></button>
<?php endif; ?>
<div id="pending-toast" style="display:none">
    <i class="bi bi-bell-fill" style="margin-right:5px"></i> Kapellmeister hat ein neues Stück gestartet
</div>
<?php if ($modus === 'live' && $pdfUrl): ?>
<button id="btn-accept-new"><i class="bi bi-arrow-right-circle"></i> Jetzt wechseln</button>
<?php endif; ?>

<div class="probe-layout modus-<?= $modus ?>">

    <!-- Sidebar -->
    <div class="probe-sidebar" id="probe-sidebar">
        <div class="probe-sidebar-inner">
            <div class="sidebar-header-row">
                <a href="index.php" class="sidebar-back" title="Zurück zu Syncopa">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="sidebar-title"><i class="bi bi-music-note-list"></i> Notenpult</span>
                <button class="sidebar-back" id="btn-sidebar-hide" title="Ausblenden" style="background:none;border:none;cursor:pointer;">
                    <i class="bi bi-layout-sidebar-reverse"></i>
                </button>
            </div>

            <!-- Umschalter: mit der Kapelle / selbst üben -->
            <div class="modus-switch mb-2">
                <a href="probe.php?kapelle=1" class="<?= in_array($modus, ['live', 'warten'], true) ? 'active' : '' ?>">
                    <i class="bi bi-people"></i> Mit der Kapelle
                    <?php if ($session && $modus !== 'live'): ?><span class="live-dot" title="Der Kapellmeister hat ein Stück gestartet"></span><?php endif; ?>
                </a>
                <a href="probe.php?ueben=0" class="<?= in_array($modus, ['ueben', 'auswahl'], true) ? 'active' : '' ?>">
                    <i class="bi bi-person"></i> Selbst üben
                </a>
            </div>

            <?php if ($modus === 'live' || $modus === 'ueben'): ?>

            <div class="sidebar-label"><?= $modus === 'live' ? 'Kapellmeister spielt' : 'Du übst' ?></div>
            <div class="active-stimme-badge mb-2"><?= htmlspecialchars($stueck['titel']) ?></div>

            <?php if ($modus === 'ueben' && $buch): ?>
            <div class="d-flex gap-1 mb-2">
                <?php if ($vorher): ?>
                <a href="<?= htmlspecialchars(pultUrl(['ueben' => (int)$vorher['id'], 'buch' => $buchId, 'sn' => $sn])) ?>" class="fav-btn justify-content-center" title="<?= htmlspecialchars($vorher['titel']) ?>"><i class="bi bi-skip-backward"></i>&nbsp;Voriges</a>
                <?php endif; ?>
                <?php if ($nachher): ?>
                <a href="<?= htmlspecialchars(pultUrl(['ueben' => (int)$nachher['id'], 'buch' => $buchId, 'sn' => $sn])) ?>" class="fav-btn justify-content-center" title="<?= htmlspecialchars($nachher['titel']) ?>">Nächstes&nbsp;<i class="bi bi-skip-forward"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Stimme wählen -->
            <?php if (!empty($stimmen)): ?>
            <div class="sidebar-label">Stimme wählen</div>
            <form method="POST" action="<?= htmlspecialchars($hierUrl) ?>" class="stimme-form mb-3">
                <select name="stimme_id" class="form-select mb-2">
                    <option value="">– Stimme wählen –</option>
                    <?php foreach ($stimmen as $st): ?>
                    <option value="<?= $st['id'] ?>" <?= $activeStimme && $activeStimme['id'] == $st['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($st['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex gap-2">
                    <button type="submit" name="select_stimme" class="btn btn-sm btn-primary flex-grow-1">
                        <i class="bi bi-check-lg"></i> Auswählen
                    </button>
                    <label class="d-flex align-items-center gap-1" style="color:var(--sidebar-text);font-size:11px;cursor:pointer;" title="Als Favorit merken">
                        <input type="checkbox" name="save_favorit" style="cursor:pointer"> ★
                    </label>
                </div>
            </form>
            <?php endif; ?>

            <!-- Aktive Stimme anzeigen -->
            <?php if ($activeStimme): ?>
            <div class="sidebar-label">Du spielst</div>
            <div class="active-stimme-badge mb-3"><?= htmlspecialchars($activeStimme['name']) ?></div>
            <?php endif; ?>

            <!-- Favoriten -->
            <?php if (!empty($favoriten)): ?>
            <div class="sidebar-label">Favoriten</div>
            <?php foreach ($favoriten as $fav): ?>
            <div style="display:flex;align-items:center;gap:4px;margin-bottom:3px">
                <form method="POST" action="<?= htmlspecialchars($hierUrl) ?>" style="flex:1">
                    <input type="hidden" name="stimme_id" value="<?= $fav['stimme_id'] ?>">
                    <button type="submit" name="select_stimme"
                            class="fav-btn <?= $activeStimme && $activeStimme['id'] == $fav['stimme_id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($fav['name']) ?>
                    </button>
                </form>
                <form method="POST" action="<?= htmlspecialchars($hierUrl) ?>">
                    <input type="hidden" name="stimme_id" value="<?= $fav['stimme_id'] ?>">
                    <button type="submit" name="delete_favorit" class="fav-del" title="Favorit entfernen">✕</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($pdfUrl): ?>
            <div class="sidebar-label">Ansicht</div>
            <button id="btn-drawing-toggle" class="fav-btn" style="justify-content:space-between">
                <span><i class="bi bi-pencil"></i> Notizen</span>
                <span id="drawing-toggle-label" style="font-size:10px;opacity:0.5">Aus</span>
            </button>
            <button id="btn-halfpage" class="fav-btn" style="justify-content:space-between">
                <span>½ Seite</span>
                <span style="font-size:10px;opacity:0.5">Split-Modus</span>
            </button>
            <?php endif; ?>

            <?php if ($modus === 'ueben' && $buch): ?>
            <div class="sidebar-label"><i class="bi bi-journal"></i> <?= htmlspecialchars($buch['name']) ?></div>
            <?php foreach ($stueckListe as $i => $st): ?>
            <a href="<?= htmlspecialchars(pultUrl(['ueben' => (int)$st['id'], 'buch' => $buchId, 'sn' => $sn])) ?>"
               class="fav-btn <?= (int)$st['id'] === (int)$stueck['id'] ? 'active' : '' ?>">
                <span class="text-truncate"><?= ($i + 1) ?>. <?= htmlspecialchars($st['titel']) ?></span>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($modus === 'ueben'): ?>
            <a href="<?= htmlspecialchars(pultUrl(['ueben' => '0', 'buch' => $buchId])) ?>" class="fav-btn mt-2">
                <i class="bi bi-collection me-1"></i> Anderes Stück wählen
            </a>
            <?php endif; ?>

            <?php elseif ($modus === 'warten'): ?>

            <div style="color: var(--sidebar-text); font-size: 13px; padding: 16px 0; text-align: center;">
                <i class="bi bi-hourglass" style="font-size:32px;display:block;margin-bottom:8px;opacity:0.4"></i>
                Warte auf Kapellmeister…
            </div>

            <?php else: // Auswahl ?>

            <div style="color: var(--sidebar-text); font-size: 12px; line-height: 1.5; padding: 4px 2px 8px;">
                <?php if ($session): ?>
                Der Kapellmeister hat gerade <strong style="color:#fff"><?= htmlspecialchars($session['noten_titel']) ?></strong> gestartet.
                <a href="probe.php?kapelle=1" class="fav-btn mt-2 justify-content-center"><i class="bi bi-people me-1"></i> Mitspielen</a>
                <?php else: ?>
                Wähle rechts ein Stück oder Notenbuch zum Üben.
                Startet der Kapellmeister ein Stück, wechselt das Notenpult von selbst.
                <?php endif; ?>
            </div>

            <?php if (!empty($notenbuecher)): ?>
            <div class="sidebar-label">Notenbücher</div>
            <a href="probe.php?ueben=0" class="fav-btn <?= !$buch ? 'active' : '' ?>"><span><i class="bi bi-collection me-1"></i> Alle Stücke</span></a>
            <?php foreach ($notenbuecher as $nb): ?>
            <a href="<?= htmlspecialchars(pultUrl(['ueben' => '0', 'buch' => (int)$nb['id']])) ?>" class="fav-btn <?= $buch && (int)$buch['id'] === (int)$nb['id'] ? 'active' : '' ?>">
                <span class="text-truncate"><i class="bi bi-journal<?= $nb['typ'] === 'geteilt' ? 's' : '' ?> me-1"></i> <?= htmlspecialchars($nb['name']) ?></span>
                <span style="font-size:10px;opacity:0.6"><?= (int)$nb['anzahl'] ?></span>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php endif; ?>

            <?php if ($isLeiter): ?>
            <div class="sidebar-label">Leitung</div>
            <a href="probe_leiter.php" class="fav-btn">
                <span><i class="bi bi-sliders me-1"></i> Notenpult leiten</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hauptinhalt -->
    <div class="probe-main">
        <!-- Canvas-Bereich -->
        <div class="probe-canvas-area pdf-viewer <?= $modus === 'auswahl' ? 'auswahl' : '' ?>" id="canvas-area">
            <?php if ($modus === 'warten'): ?>
            <div class="wait-screen">
                <i class="bi bi-music-note-beamed" style="font-size:64px;opacity:0.15"></i>
                <div>Warte auf Kapellmeister…</div>
                <div class="small">Sobald ein Stück gestartet wird, erscheinen hier deine Noten.</div>
            </div>

            <?php elseif ($modus === 'auswahl'): ?>
            <div class="auswahl-inner">
                <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-permanent py-2"><?= htmlspecialchars($flash['message']) ?></div>
                <?php endif; ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <h1 class="h4 mb-0 flex-grow-1">
                        <?php if ($buch): ?><i class="bi bi-journal"></i> <?= htmlspecialchars($buch['name']) ?>
                        <?php else: ?><i class="bi bi-collection"></i> Selbst üben<?php endif; ?>
                    </h1>
                    <input type="search" id="stueck-suche" class="form-control form-control-sm" style="max-width:260px" placeholder="Stück suchen…" autocomplete="off">
                </div>
                <?php if ($buch && $stueckListe): ?>
                <a href="<?= htmlspecialchars(pultUrl(['ueben' => (int)$stueckListe[0]['id'], 'buch' => $buchId])) ?>" class="btn btn-primary btn-sm mb-3">
                    <i class="bi bi-play-fill"></i> Notenbuch von vorne spielen
                </a>
                <?php endif; ?>
                <?php if (empty($stueckListe)): ?>
                <div class="text-muted py-5 text-center">
                    <i class="bi bi-music-note-beamed" style="font-size:48px;opacity:0.2"></i>
                    <p class="mt-2 mb-0"><?= $buch ? 'In diesem Notenbuch sind keine Stücke mit eingerichteten Stimmen.' : 'Es sind noch keine Stücke mit eingerichteten Stimmen vorhanden.' ?></p>
                </div>
                <?php else: ?>
                <div class="list-group" id="stueck-liste">
                    <?php foreach ($stueckListe as $i => $st): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 px-3 py-2"
                       data-suche="<?= htmlspecialchars(mb_strtolower($st['titel'] . ' ' . $st['komponist'])) ?>"
                       href="<?= htmlspecialchars(pultUrl(['ueben' => (int)$st['id'], 'buch' => $buchId])) ?>">
                        <?php if ($buch): ?><span class="text-muted small" style="min-width:22px"><?= $i + 1 ?>.</span><?php endif; ?>
                        <span class="flex-grow-1">
                            <span class="fw-medium"><?= htmlspecialchars($st['titel']) ?></span>
                            <?php if ($st['komponist']): ?><span class="small text-muted d-block"><?= htmlspecialchars($st['komponist']) ?></span><?php endif; ?>
                        </span>
                        <i class="bi bi-play-circle text-primary fs-5"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php elseif (!$pdfUrl): ?>
            <div class="wait-screen">
                <i class="bi bi-file-earmark-pdf" style="font-size:64px;opacity:0.15"></i>
                <div>
                    <?php if (!$activeStimme): ?>
                        Bitte links eine Stimme auswählen.
                    <?php else: ?>
                        Kein PDF für diese Stimme hinterlegt.
                        <?php if ($isLeiter): ?>
                        <a href="probe_stimmen.php?noten_id=<?= (int)$stueck['id'] ?>">Stimmen verwalten →</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            <div id="canvas-inner">
                <canvas id="pdf-canvas"></canvas>
                <canvas id="drawing-canvas" style="position:absolute;top:0;left:0;pointer-events:none"></canvas>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($stueck && $pdfUrl): ?>
        <!-- Stempel-Palette (nur im Stempel-Modus sichtbar) -->
        <div class="stamp-palette" id="stamp-panel" style="display:none">
            <!-- Dynamik -->
            <button class="stamp-btn dyn active" data-sym="pp"  title="Pianissimo">pp</button>
            <button class="stamp-btn dyn"        data-sym="p"   title="Piano">p</button>
            <button class="stamp-btn dyn"        data-sym="mp"  title="Mezzopiano">mp</button>
            <button class="stamp-btn dyn"        data-sym="mf"  title="Mezzoforte">mf</button>
            <button class="stamp-btn dyn"        data-sym="f"   title="Forte">f</button>
            <button class="stamp-btn dyn"        data-sym="ff"  title="Fortissimo">ff</button>
            <button class="stamp-btn dyn"        data-sym="sf"  title="Sforzando">sf</button>
            <button class="stamp-btn dyn"        data-sym="fp"  title="Fortepiano">fp</button>
            <div class="stamp-sep"></div>
            <!-- Artikulation -->
            <button class="stamp-btn" data-sym=">"  title="Akzent">&#62;</button>
            <button class="stamp-btn" data-sym="^"  title="Marcato">^</button>
            <button class="stamp-btn" data-sym="•"  title="Stakkato">•</button>
            <button class="stamp-btn" data-sym="—"  title="Tenuto">—</button>
            <button class="stamp-btn" data-sym="~"  title="Triller">~</button>
            <div class="stamp-sep"></div>
            <!-- Spieltechnik / Sonstiges -->
            <button class="stamp-btn" data-sym="↑"  title="Aufstrich / nach oben">↑</button>
            <button class="stamp-btn" data-sym="↓"  title="Abstrich / nach unten">↓</button>
            <button class="stamp-btn" data-sym="○"  title="Offen / Flageolett">○</button>
            <button class="stamp-btn" data-sym="+"  title="Gedämpft / gestopft">+</button>
            <button class="stamp-btn" data-sym="♯"  title="Erhöhungszeichen (Kreuz)">♯</button>
            <button class="stamp-btn" data-sym="♭"  title="Vertiefungszeichen (B)">♭</button>
            <button class="stamp-btn" data-sym="♮"  title="Auflösungszeichen">♮</button>
            <button class="stamp-btn" data-sym="♩"  title="Viertel">♩</button>
            <button class="stamp-btn" data-sym="♪"  title="Achtel">♪</button>
            <button class="stamp-btn" data-sym="✓"  title="Richtig / OK">✓</button>
        </div>

        <!-- Bearbeitungs-Toolbar (standardmäßig ausgeblendet) -->
        <div class="probe-bottombar" id="probe-bottombar" style="display:none">
            <div class="pbt-group">
                <button id="btn-prev" class="pbt-btn" title="Vorige Seite"><i class="bi bi-chevron-left"></i></button>
                <span class="pbt-text"><span id="page-num">–</span> / <span id="page-total">–</span></span>
                <button id="btn-next" class="pbt-btn" title="Nächste Seite"><i class="bi bi-chevron-right"></i></button>
            </div>
            <div class="pbt-group">
                <button id="btn-pen"         class="pbt-btn active" title="Stift"><i class="bi bi-pencil"></i></button>
                <button id="btn-highlighter" class="pbt-btn"        title="Textmarker"><i class="bi bi-brush"></i></button>
                <button id="btn-eraser"      class="pbt-btn"        title="Radierer"><i class="bi bi-eraser"></i></button>
                <button id="btn-stamp"       class="pbt-btn"        title="Stempel"><i class="bi bi-alphabet"></i></button>
                <input type="color" id="color-picker" value="#000000" class="pbt-color" title="Farbe">
                <input type="range" id="width-slider" min="1" max="10" value="2" class="pbt-slider">
                <span id="width-display" class="pbt-text">2px</span>
            </div>
            <div class="pbt-group">
                <button id="btn-undo"  class="pbt-btn" title="Rückgängig"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button id="btn-clear" class="pbt-btn" title="Seite löschen"><i class="bi bi-trash"></i></button>
                <button id="btn-save"  class="pbt-btn" title="Speichern"><i class="bi bi-floppy2"></i></button>
                <span id="save-status" class="pbt-status"></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($stueck && $pdfUrl): ?>
<script>
const userData = {
    musiker_id:  <?= (int)$benutzerId ?>,
    stimme_id:   <?= $activeStimme ? (int)$activeStimme['id'] : 'null' ?>,
    session_id:  <?= $modus === 'live' ? (int)$session['id'] : 'null' ?>,
    pdf_file:    <?= json_encode($pdfUrl) ?>,
    seite_von:   <?= (int)$seiteVon ?>,
    seite_bis:   <?= (int)$seiteBis ?>,
    initial_page: 1
};
</script>
<script src="assets/js/pdf-viewer.js"></script>
<script src="assets/js/drawing.js"></script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dark Mode
    var _theme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', _theme);
    document.documentElement.setAttribute('data-bs-theme', _theme);

    // iOS vh-Fix
    function setVH() { document.documentElement.style.setProperty('--vh', (window.innerHeight * 0.01) + 'px'); }
    setVH();
    window.addEventListener('resize', setVH);
    window.addEventListener('orientationchange', setVH);

    // Fullscreen API (webkit-Prefix für Safari/Android)
    function enterFullscreen() {
        var el = document.documentElement;
        var fn = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen;
        if (fn) { try { fn.call(el); } catch(e) {} }
        localStorage.setItem('probe_fullscreen', '1');
    }
    function exitFullscreen() {
        var fn = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen;
        if (fn) { try { fn.call(document); } catch(e) {} }
        localStorage.removeItem('probe_fullscreen');
    }

    var sidebar  = document.getElementById('probe-sidebar');
    var btnFloat = document.getElementById('btn-menu-float');

    function hideSidebar() {
        sidebar.classList.add('collapsed');
        btnFloat.style.display = 'block';
        enterFullscreen();
    }
    function showSidebar() {
        sidebar.classList.remove('collapsed');
        btnFloat.style.display = 'none';
        exitFullscreen();
    }

    document.getElementById('btn-sidebar-hide').addEventListener('click', hideSidebar);
    btnFloat.addEventListener('click', showSidebar);

    // Vollbild-Zustand über Stückwechsel (location.reload) hinweg wiederherstellen
    if (localStorage.getItem('probe_fullscreen') === '1') {
        sidebar.classList.add('collapsed');
        btnFloat.style.display = 'block';
        setTimeout(enterFullscreen, 150);
    }

    <?php if ($stueck && $pdfUrl): ?>
    // PDF Viewer starten
    window.pdfViewer = new PDFViewer('pdf-canvas', userData.pdf_file, userData.seite_von, userData.seite_bis);

    // Halbseiten-Modus: standardmäßig aktiv, außer explizit ausgeschaltet
    if (localStorage.getItem('probe_halfpage') !== '0') {
        window.pdfViewer.splitPageMode = true;
    }

    window.pdfViewer.init().then(function() {
        // Nochmals rendern nach Abschluss aller Übergänge (Sidebar, Fullscreen)
        setTimeout(function() {
            if (!window.pdfViewer || !window.pdfViewer.pdfDoc) return;
            window.pdfViewer._pageCache.clear();
            if (window.pdfViewer.splitPageMode) window.pdfViewer.renderSplit();
            else window.pdfViewer.renderPage(window.pdfViewer.currentRelPage);
        }, 350);
    });

    // DrawingApp initialisieren
    window.drawingApp = new DrawingApp('drawing-canvas');
    window.drawingApp.selectTool('pen');
    var colorPicker = document.getElementById('color-picker');
    if (colorPicker) colorPicker.addEventListener('input', function() { window.drawingApp.color = this.value; });

    // Stempel-Panel: Symbol auswählen und Stempel-Modus aktivieren
    var stampPanel = document.getElementById('stamp-panel');
    var btnStamp   = document.getElementById('btn-stamp');

    if (btnStamp) {
        btnStamp.addEventListener('click', function() {
            // Palette ein-/ausblenden
            var open = stampPanel && stampPanel.style.display !== 'none';
            if (stampPanel) stampPanel.style.display = open ? 'none' : 'flex';
            if (!open && window.drawingApp) window.drawingApp.selectTool('stamp');
        });
    }

    document.querySelectorAll('.stamp-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            // Aktives Symbol hervorheben
            document.querySelectorAll('.stamp-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            // Stempel-Symbol im DrawingApp setzen und Stempel-Modus aktivieren
            if (window.drawingApp) window.drawingApp.selectStamp(this.dataset.sym);
        });
    });

    // Notizen-Modus: Toolbar ein-/ausblenden + Stückwechsel blockieren
    var drawingLocked  = false;
    var pendingNotenId = null;
    var bottombar      = document.getElementById('probe-bottombar');
    var pendingToast   = document.getElementById('pending-toast');
    var acceptBtn      = document.getElementById('btn-accept-new');
    var drawingToggle  = document.getElementById('btn-drawing-toggle');
    var toggleLabel    = document.getElementById('drawing-toggle-label');
    var drawingFloat   = document.getElementById('btn-drawing-float');
    var drawingCanvas  = document.getElementById('drawing-canvas');
    var _toastTimer    = null;

    function showPendingNotification() {
        if (pendingToast) {
            pendingToast.style.display = 'block';
            pendingToast.style.opacity = '1';
            clearTimeout(_toastTimer);
            _toastTimer = setTimeout(function() {
                pendingToast.style.opacity = '0';
                setTimeout(function() { pendingToast.style.display = 'none'; }, 500);
            }, 4000);
        }
        if (acceptBtn) acceptBtn.style.display = 'block';
    }
    function hidePendingNotification() {
        if (pendingToast) { pendingToast.style.display = 'none'; }
        if (acceptBtn)    { acceptBtn.style.display = 'none'; }
    }

    function enableDrawingMode() {
        drawingLocked = true;
        if (drawingCanvas) { drawingCanvas.style.pointerEvents = 'auto'; }
        if (bottombar)     { bottombar.style.display = 'flex'; }
        if (drawingToggle) { drawingToggle.classList.add('active'); }
        if (toggleLabel)   { toggleLabel.textContent = 'An'; }
        if (drawingFloat)  { drawingFloat.classList.add('active'); }
    }
    function disableDrawingMode() {
        // Aktuellen Zeichenstand sofort in den Cache schreiben,
        // bevor das Ausblenden der Toolbar den ResizeObserver und damit loadAnnotations auslöst
        if (window.drawingApp) window.drawingApp.saveNow();

        // Stempel-Panel schließen und zurück zum Stift wechseln
        if (stampPanel) stampPanel.style.display = 'none';
        if (window.drawingApp) window.drawingApp.selectTool('pen');

        drawingLocked = false;
        if (drawingCanvas) { drawingCanvas.style.pointerEvents = 'none'; }
        if (bottombar)     { bottombar.style.display = 'none'; }
        if (drawingToggle) { drawingToggle.classList.remove('active'); }
        if (toggleLabel)   { toggleLabel.textContent = 'Aus'; }
        if (drawingFloat)  { drawingFloat.classList.remove('active'); }
        hidePendingNotification();
        if (pendingNotenId !== null) { reloadPage(); }
    }

    if (drawingToggle) {
        drawingToggle.addEventListener('click', function() {
            if (drawingLocked) disableDrawingMode();
            else enableDrawingMode();
        });
    }
    if (drawingFloat) {
        drawingFloat.addEventListener('click', function() {
            if (drawingLocked) disableDrawingMode();
            else enableDrawingMode();
        });
    }
    if (acceptBtn) acceptBtn.addEventListener('click', disableDrawingMode);

    // Halbseiten-Toggle
    var halfBtn = document.getElementById('btn-halfpage');
    if (halfBtn) {
        if (window.pdfViewer.splitPageMode) halfBtn.classList.add('active');
        halfBtn.addEventListener('click', function() {
            var isHalf = window.pdfViewer.toggleSplitPageMode();
            this.classList.toggle('active', isHalf);
            localStorage.setItem('probe_halfpage', isHalf ? '1' : '0');
        });
    }

    // Re-render bei Größenänderung des Canvas-Bereichs (z.B. Sidebar ein-/ausblenden)
    if (window.ResizeObserver) {
        var _roTimer;
        new ResizeObserver(function() {
            if (!window.pdfViewer || !window.pdfViewer.pdfDoc) return;
            window.pdfViewer._pageCache.clear();
            clearTimeout(_roTimer);
            _roTimer = setTimeout(function() {
                if (window.pdfViewer.splitPageMode) window.pdfViewer.renderSplit();
                else window.pdfViewer.renderPage(window.pdfViewer.currentRelPage);
            }, 150);
        }).observe(document.getElementById('canvas-area'));
    }

    // Swipe-Navigation (Events bubblen vom drawing-canvas hoch)
    var touchStartX = 0, touchStartY = 0;
    var canvasArea = document.getElementById('canvas-area');
    canvasArea.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
    }, { passive: true });
    canvasArea.addEventListener('touchend', function(e) {
        var dx = e.changedTouches[0].clientX - touchStartX;
        var dy = e.changedTouches[0].clientY - touchStartY;
        if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy)) {
            if (window.drawingApp) window.drawingApp.saveNow();
            if (dx < 0) window.pdfViewer.nextPage();
            else        window.pdfViewer.prevPage();
        }
    }, { passive: true });

    <?php endif; ?>

    // Suche in der Stückauswahl
    var suche = document.getElementById('stueck-suche');
    if (suche) {
        suche.addEventListener('input', function() {
            var q = this.value.trim().toLowerCase();
            document.querySelectorAll('#stueck-liste [data-suche]').forEach(function(el) {
                el.classList.toggle('d-none', q !== '' && el.dataset.suche.indexOf(q) === -1);
            });
        });
    }

    // Reload-Flag: verhindert dass pagehide fälschlich als "verlassen" gewertet wird
    var _isReloading = false;
    function reloadPage() { _isReloading = true; location.reload(); }

    var MODUS     = <?= json_encode($modus) ?>;
    // Ohne ausdrückliche Wahl „Selbst üben“ wechselt die Auswahl zur Kapelle, sobald der Kapellmeister startet
    var AUTO_LIVE = <?= $uebenParam === null ? 'true' : 'false' ?>;
    var lastNoten = <?= $session ? (int)$session['noten_id'] : 0 ?>;

    // Beim Verlassen des gemeinsamen Spielens: Abmeldung an den Server senden
    if (MODUS === 'live') {
        window.addEventListener('pagehide', function() {
            if (_isReloading) return;
            navigator.sendBeacon('api/probe_session.php', JSON.stringify({ leave: true }));
        });
    }

    // Hinweis beim Üben, wenn der Kapellmeister ein Stück startet
    function liveHinweis(aktiv) {
        var link = document.querySelector('.modus-switch a');
        if (!link) return;
        var dot = link.querySelector('.live-dot');
        if (aktiv && !dot) {
            dot = document.createElement('span');
            dot.className = 'live-dot';
            dot.title = 'Der Kapellmeister hat ein Stück gestartet';
            link.appendChild(dot);
            var toast = document.getElementById('pending-toast');
            if (toast) {
                toast.innerHTML = '<i class="bi bi-bell-fill" style="margin-right:5px"></i> Kapellmeister hat ein Stück gestartet – „Mit der Kapelle“ wählen';
                toast.style.display = 'block'; toast.style.opacity = '1';
                setTimeout(function() { toast.style.opacity = '0'; setTimeout(function() { toast.style.display = 'none'; }, 500); }, 5000);
            }
        } else if (!aktiv && dot) {
            dot.remove();
        }
    }

    // Polling: Stückwechsel erkennen, aktuell angezeigte Note melden
    setInterval(function() {
        fetch('api/probe_session.php' + (MODUS === 'live' ? '?viewing=' + lastNoten : ''))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var neu = d.noten_id || 0;
                if (MODUS === 'live') {
                    if (neu && neu !== lastNoten) {
                        if (typeof drawingLocked !== 'undefined' && drawingLocked) {
                            pendingNotenId = neu;
                            showPendingNotification();
                        } else {
                            reloadPage();
                        }
                    }
                    if (!neu) reloadPage();
                } else if (MODUS === 'warten' || (MODUS === 'auswahl' && AUTO_LIVE)) {
                    if (neu) reloadPage();
                } else {
                    liveHinweis(!!neu);
                }
            }).catch(function() {});
    }, 5000);

    // Formulare (Stimme wählen, Favoriten) navigieren zur selben Seite zurück –
    // das ist kein echtes Verlassen. Ohne diese Markierung feuert pagehide beim
    // Absenden trotzdem und der "leave"-Beacon löscht die gerade erst gewählte
    // Stimme wieder, bevor der Reload sie anzeigen kann.
    document.addEventListener('submit', function() { _isReloading = true; }, true);
});
</script>

</body>
</html>
