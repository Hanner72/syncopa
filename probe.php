<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('probe', 'lesen');

$db          = Database::getInstance();
$benutzerId  = Session::getUserId();
$formationId = Session::getFormationId();
$isLeiter    = Session::checkPermission('probe', 'schreiben');

// Aktive Session laden
$sessionSql    = "SELECT ps.*, n.titel as noten_titel, n.id as noten_id FROM probe_session ps
                  JOIN noten n ON ps.noten_id = n.id WHERE ps.aktiv = 1";
$sessionParams = [];
if ($formationId) {
    $sessionSql    .= " AND (ps.formation_id = ? OR ps.formation_id IS NULL)";
    $sessionParams[] = $formationId;
}
$sessionSql .= " ORDER BY ps.gestartet_am DESC LIMIT 1";
$session = $db->fetchOne($sessionSql, $sessionParams);

$stimmen       = [];
$activeStimme  = null;
$favoriten     = [];
$pdfUrl        = null;
$seiteVon      = 1;
$seiteBis      = 1;

if ($session) {
    $stimmen = $db->fetchAll(
        "SELECT ns.*, nd.dateiname as dateiname FROM noten_stimmen ns
         LEFT JOIN noten_dateien nd ON ns.datei_id = nd.id
         WHERE ns.noten_id = ? ORDER BY ns.reihenfolge, ns.name",
        [$session['noten_id']]
    );

    $favoriten = $db->fetchAll(
        "SELECT nf.stimme_id, ns.name FROM noten_favoriten nf
         JOIN noten_stimmen ns ON nf.stimme_id = ns.id
         WHERE nf.benutzer_id = ? AND ns.noten_id = ?
         ORDER BY ns.name",
        [$benutzerId, $session['noten_id']]
    );

    // Aktuell gewählte Stimme aus session_spieler (neuester Eintrag)
    $spieler = $db->fetchOne(
        "SELECT stimme_id FROM probe_session_spieler
         WHERE session_id = ? AND benutzer_id = ?
         ORDER BY joined_am DESC, id DESC LIMIT 1",
        [$session['id'], $benutzerId]
    );
    $activeStimmeId = $spieler ? (int)$spieler['stimme_id'] : null;

    // Auto-Select: erster Favorit wenn Stimme zum aktuellen Stück passt
    if (!$activeStimmeId && !empty($favoriten)) {
        $notenIds = array_column($stimmen, 'id');
        foreach ($favoriten as $fav) {
            if (in_array($fav['stimme_id'], $notenIds)) {
                $activeStimmeId = (int)$fav['stimme_id'];
                $db->query(
                    "INSERT INTO probe_session_spieler (session_id, benutzer_id, stimme_id, joined_am)
                     VALUES (?,?,?,NOW())",
                    [$session['id'], $benutzerId, $activeStimmeId]
                );
                break;
            }
        }
    }

    if ($activeStimmeId) {
        foreach ($stimmen as $st) {
            if ($st['id'] == $activeStimmeId) { $activeStimme = $st; break; }
        }
    }
}

// Stimme wählen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_stimme']) && $session) {
    $stimmeId    = (int)($_POST['stimme_id'] ?? 0);
    $saveFav     = !empty($_POST['save_favorit']);
    if ($stimmeId) {
        $db->query(
            "INSERT INTO probe_session_spieler (session_id, benutzer_id, stimme_id, joined_am)
             VALUES (?,?,?,NOW())",
            [$session['id'], $benutzerId, $stimmeId]
        );
        if ($saveFav) {
            $db->query(
                "INSERT IGNORE INTO noten_favoriten (benutzer_id, stimme_id) VALUES (?,?)",
                [$benutzerId, $stimmeId]
            );
        }
    }
    header('Location: probe.php');
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
    header('Location: probe.php');
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
            [$session['noten_id']]
        );
        if ($ersteDatei) {
            $pdfUrl   = 'uploads/noten/' . rawurlencode($ersteDatei['dateiname']);
            $seiteVon = 1;
            $seiteBis = 9999;
        }
    }
} elseif ($session && $isLeiter) {
    // Dirigent: erste Datei, alle Seiten
    $ersteDatei = $db->fetchOne(
        "SELECT dateiname FROM noten_dateien WHERE noten_id=? ORDER BY sortierung LIMIT 1",
        [$session['noten_id']]
    );
    if ($ersteDatei) {
        $pdfUrl   = 'uploads/noten/' . rawurlencode($ersteDatei['dateiname']);
        $seiteVon = 1;
        $seiteBis = 9999;
    }
}
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Live – <?= $session ? htmlspecialchars($session['noten_titel']) : 'Kein Stück aktiv' ?></title>
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
<?php if ($session && $pdfUrl): ?>
<button id="btn-drawing-float" title="Notizen ein-/ausschalten"><i class="bi bi-pencil"></i></button>
<?php endif; ?>
<div id="pending-toast" style="display:none">
    <i class="bi bi-bell-fill" style="margin-right:5px"></i> Kapellmeister hat ein neues Stück gestartet
</div>
<?php if ($session && $pdfUrl): ?>
<button id="btn-accept-new"><i class="bi bi-arrow-right-circle"></i> Jetzt wechseln</button>
<?php endif; ?>

<div class="probe-layout">

    <!-- Sidebar -->
    <div class="probe-sidebar" id="probe-sidebar">
        <div class="probe-sidebar-inner">
            <div class="sidebar-header-row">
                <a href="<?= $isLeiter ? 'probe_leiter.php' : 'index.php' ?>" class="sidebar-back" title="Zurück">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="sidebar-title">🎵 Live</span>
                <button class="sidebar-back" id="btn-sidebar-hide" title="Ausblenden" style="background:none;border:none;cursor:pointer;">
                    <i class="bi bi-layout-sidebar-reverse"></i>
                </button>
            </div>

            <?php if (!$session): ?>
            <div style="color: var(--sidebar-text); font-size: 13px; padding: 16px 0; text-align: center;">
                <i class="bi bi-hourglass" style="font-size:32px;display:block;margin-bottom:8px;opacity:0.4"></i>
                Warte auf Kapellmeister…
            </div>

            <?php else: ?>

            <!-- Aktives Stück -->
            <div class="sidebar-label">Aktuelles Stück</div>
            <div class="active-stimme-badge mb-2"><?= htmlspecialchars($session['noten_titel']) ?></div>

            <!-- Stimme wählen -->
            <?php if (!empty($stimmen)): ?>
            <div class="sidebar-label">Stimme wählen</div>
            <form method="POST" class="stimme-form mb-3">
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
                    <label class="d-flex align-items-center gap-1" style="color:var(--sidebar-text);font-size:11px;cursor:pointer;">
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
                <form method="POST" style="flex:1">
                    <input type="hidden" name="stimme_id" value="<?= $fav['stimme_id'] ?>">
                    <button type="submit" name="select_stimme"
                            class="fav-btn <?= $activeStimme && $activeStimme['id'] == $fav['stimme_id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($fav['name']) ?>
                    </button>
                </form>
                <form method="POST">
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

            <?php endif; // session ?>

            <?php if ($isLeiter): ?>
            <div class="sidebar-label">Leitung</div>
            <a href="probe_leiter.php" class="fav-btn">
                <i class="bi bi-sliders me-1"></i> Live verwalten
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hauptinhalt -->
    <div class="probe-main">
        <!-- Canvas-Bereich -->
        <div class="probe-canvas-area pdf-viewer" id="canvas-area">
            <?php if (!$session): ?>
            <div class="wait-screen">
                <i class="bi bi-music-note-beamed" style="font-size:64px;opacity:0.15"></i>
                <div>Warte auf Kapellmeister…</div>
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
                        <a href="probe_stimmen.php?noten_id=<?= $session['noten_id'] ?>">Stimmen verwalten →</a>
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

        <?php if ($session && $pdfUrl): ?>
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

<?php if ($session && $pdfUrl): ?>
<script>
const userData = {
    musiker_id:  <?= (int)$benutzerId ?>,
    stimme_id:   <?= $activeStimme ? (int)$activeStimme['id'] : 'null' ?>,
    session_id:  <?= (int)$session['id'] ?>,
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
    document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');

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

    <?php if ($session && $pdfUrl): ?>
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

    // Reload-Flag: verhindert dass pagehide fälschlich als "verlassen" gewertet wird
    var _isReloading = false;
    function reloadPage() { _isReloading = true; location.reload(); }

    // Beim Verlassen der Seite: Abmeldung an den Server senden
    window.addEventListener('pagehide', function() {
        if (_isReloading) return;
        navigator.sendBeacon('api/probe_session.php', JSON.stringify({ leave: true }));
    });

    // Polling: Stückwechsel erkennen, aktuell angezeigte Note melden
    var lastNoten = <?= (int)$session['noten_id'] ?>;
    setInterval(function() {
        fetch('api/probe_session.php?viewing=' + lastNoten)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.noten_id && d.noten_id !== lastNoten) {
                    if (drawingLocked) {
                        pendingNotenId = d.noten_id;
                        showPendingNotification();
                    } else {
                        reloadPage();
                    }
                }
                if (!d.noten_id && lastNoten) reloadPage();
            }).catch(function() {});
    }, 5000);
    <?php else: ?>
    // Reload-Flag: verhindert dass pagehide fälschlich als "verlassen" gewertet wird
    var _isReloading = false;
    function reloadPage() { _isReloading = true; location.reload(); }

    // Beim Verlassen der Seite: Abmeldung an den Server senden
    window.addEventListener('pagehide', function() {
        if (_isReloading) return;
        navigator.sendBeacon('api/probe_session.php', JSON.stringify({ leave: true }));
    });

    // Polling ohne PDF: warte auf aktive Session
    var lastNoten = <?= $session ? (int)$session['noten_id'] : 0 ?>;
    setInterval(function() {
        fetch('api/probe_session.php')
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.noten_id !== lastNoten) reloadPage(); })
            .catch(function() {});
    }, 5000);
    <?php endif; ?>

    // Formulare (Stimme wählen, Favoriten) navigieren zur selben Seite zurück –
    // das ist kein echtes Verlassen. Ohne diese Markierung feuert pagehide beim
    // Absenden trotzdem und der "leave"-Beacon löscht die gerade erst gewählte
    // Stimme wieder, bevor der Reload sie anzeigen kann.
    document.addEventListener('submit', function() { _isReloading = true; }, true);
});
</script>

</body>
</html>
