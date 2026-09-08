<?php
// includes/header.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';
Session::start();

// Telemetry-Ping (einmal pro Tag, nur wenn eingeloggt und aktiviert)
(function() {
    if (!Session::isLoggedIn()) return;
    $lastPing = $_SESSION['_telemetry_ping_ts'] ?? 0;
    if (time() - $lastPing < 86400) return;
    $_SESSION['_telemetry_ping_ts'] = time();
    try {
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT schluessel, wert FROM einstellungen WHERE schluessel IN ('telemetry_enabled','installation_id','verein_name')"
        );
        $cfg = array_column($rows, 'wert', 'schluessel');
        if (($cfg['telemetry_enabled'] ?? '0') !== '1') return;
        if (empty($cfg['installation_id'])) return;
        $payload = json_encode([
            'id'      => $cfg['installation_id'],
            'version' => defined('APP_VERSION') ? APP_VERSION : '?',
            'verein'  => $cfg['verein_name'] ?? '',
            'url'     => defined('BASE_URL') ? BASE_URL : '',
        ]);
        $url = 'https://syncopa.dannerbam.eu/telemetry/ping.php';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 3, CURLOPT_SSL_VERIFYPEER => true]);
            @curl_exec($ch); curl_close($ch);
        } elseif (ini_get('allow_url_fopen')) {
            @file_get_contents($url, false, stream_context_create(['http' => [
                'method' => 'POST', 'header' => "Content-Type: application/json\r\n",
                'content' => $payload, 'timeout' => 3]]));
        }
    } catch (\Throwable $e) {}
})();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$pages = [
    'kalender' => ['kalender', 'ausrueckungen', 'ausrueckung_detail', 'ausrueckung_bearbeiten'],
    'mitglieder' => ['mitglieder', 'mitglied_detail', 'mitglied_bearbeiten'],
    'noten' => ['noten', 'noten_bearbeiten'],
    'probe' => ['probe', 'probe_leiter', 'probe_stimmen'],
    'instrumente' => ['instrumente', 'instrument_detail', 'instrument_bearbeiten'],
    'uniformen' => ['uniformen', 'uniform_detail', 'uniform_bearbeiten', 'uniform_mitglied', 'uniform_kleidungsstuecke', 'uniform_kategorien'],
    'finanzen' => ['finanzen', 'transaktion_bearbeiten', 'beitraege_verwalten'],
    'admin' => ['benutzer', 'benutzer_bearbeiten', 'rollen', 'rolle_bearbeiten', 'einstellungen', 'berechtigungen_bearbeiten'],
    'formationen' => ['formationen', 'formation_bearbeiten', 'formation_mitglieder'],
    'fest'  => [
        'feste', 'fest_bearbeiten', 'fest_detail',
        'fest_stationen', 'fest_station_bearbeiten',
        'fest_mitarbeiter', 'fest_mitarbeiter_bearbeiten',
        'fest_dienstplan', 'fest_dienstplan_bearbeiten',
        'fest_einkauefe', 'fest_einkauf_bearbeiten',
        'fest_vertraege', 'fest_vertrag_bearbeiten',
        'fest_todos', 'fest_todo_bearbeiten',
        'fest_kopieren'
    ]
];

function isActive($page, $pages, $current) {
    if (isset($pages[$page])) return in_array($current, $pages[$page]) ? 'active' : '';
    return $current === $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="de" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Chart.js früh laden -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        :root {
            /* Einheitliche, ruhige Farbpalette für Verwaltung */
            --c-primary: #4471A3;      /* Gedämpftes Petrol */
            --c-primary-light: #5496cb;
            --c-success: #5b8a72;       /* Gedämpftes Grün */
            --c-warning: #D19A3E;       /* Gedämpftes Gold */
            --c-danger: #F44336;        /* Gedämpftes Rot */
            --c-info: #77bad7;          /* Gedämpftes Blau */
            
            --sidebar-w: 210px;
            --topbar-h: 60px;
            --radius: 4px;
            --radius-lg: 6px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --transition: 0.2s ease;
        }
        
        [data-theme="light"] {
            --bg-body: #f5f6f8;
            --bg-card: #ffffff;
            --bg-sidebar: #2d4a6a;
            --bg-input: #ffffff;
            --border: #e0e4e8;
            --border-light: #eef0f2;
            --text-primary: #2c3e50;
            --text-secondary: #5a6c7d;
            --text-muted: #8699ac;
            --sidebar-text: #a8b8c8;
            --sidebar-hover: rgba(255,255,255,0.06);
            --sidebar-active: rgba(255,255,255,0.12);
        }
        
        [data-theme="dark"] {
            --bg-body: #1a2332;
            --bg-card: #243042;
            --bg-sidebar: #151d2b;
            --bg-input: #2a3a4d;
            --border: #3a4a5d;
            --border-light: #2d3d50;
            --text-primary: #e8eef4;
            --text-secondary: #a0b0c0;
            --text-muted: #708090;
            --sidebar-text: #8090a0;
            --sidebar-hover: rgba(255,255,255,0.05);
            --sidebar-active: rgba(255,255,255,0.10);

            /* FullCalendar (index.global.min.css) verwendet eigene Variablen, standardmäßig helles Theme */
            --fc-page-bg-color: var(--bg-card);
            --fc-border-color: var(--border);
            --fc-neutral-bg-color: var(--bg-body);
            --fc-neutral-text-color: var(--text-secondary);
            --fc-today-bg-color: rgba(68, 113, 163, 0.18);
            --fc-button-bg-color: var(--c-primary);
            --fc-button-border-color: var(--c-primary);
            --fc-button-hover-bg-color: var(--c-primary-light);
            --fc-button-hover-border-color: var(--c-primary-light);
            --fc-button-active-bg-color: var(--c-primary-light);
            --fc-button-active-border-color: var(--c-primary-light);
            --fc-list-event-hover-bg-color: var(--bg-body);
            --fc-more-link-bg-color: var(--bg-body);
        }
        
        * { font-family: 'Inter', system-ui, sans-serif; -webkit-tap-highlight-color: transparent; box-sizing: border-box; }
        body { background: var(--bg-body); color: var(--text-primary); font-size: 13px; line-height: 1.5; overflow-x: hidden; margin: 0; }
        a { text-decoration: none !important; color: inherit; }
        
        /* SIDEBAR */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-w); height: 100vh;
            background: var(--bg-sidebar);
            z-index: 1050;
            display: flex; flex-direction: column;
            transition: transform var(--transition);
        }
        .sidebar-header {
            height: var(--topbar-h);
            padding: 0 12px;
            display: flex; align-items: center; justify-content: center;
            position: relative;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-logo { width: 70px; height: auto; }
        .sidebar-brand { font-size: 15px; font-weight: 600; color: #fff; }
        .sidebar-close {
            display: none; margin-left: auto;
            /* position: absolute; right: 8px; */
            background: none; border: none;
            color: #708090; font-size: 18px; padding: 4px; cursor: pointer;
            line-height: 1;
        }
        .sidebar-close:hover { color: #fff; }
        .sidebar-nav { flex: 1; padding: 8px; overflow-y: auto; }
        .sidebar-nav::-webkit-scrollbar { width: 3px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 3px; }
        .nav-group { margin-bottom: 16px; }
        .nav-label {
            font-size: 10px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.8px;
            color: #5a6a7a; padding: 6px 10px 4px; margin-bottom: 2px;
        }
        .sidebar .nav-link {
            display: flex; align-items: center;
            padding: 8px 10px; margin-bottom: 2px;
            border-radius: var(--radius);
            color: var(--sidebar-text);
            font-size: 13px; font-weight: 500;
            transition: all var(--transition);
        }
        .sidebar .nav-link i { font-size: 15px; width: 20px; margin-right: 8px; opacity: 0.75; }
        .sidebar .nav-link:hover { background: var(--sidebar-hover); color: #d0daea; }
        .sidebar .nav-link.active { background: var(--sidebar-active); color: #fff; }
        .sidebar .nav-link.active i { opacity: 1; }
        
        /* TOPBAR */
        .topbar {
            position: fixed; top: 0; left: var(--sidebar-w); right: 0;
            height: var(--topbar-h);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center;
            padding: 0 16px; z-index: 1040; gap: 8px;
            transition: left var(--transition);
        }
        .topbar-toggle {
            display: block; background: none; border: none;
            font-size: 20px; color: var(--text-primary);
            padding: 4px; border-radius: var(--radius); cursor: pointer;
        }
        .topbar-toggle:hover { background: var(--bg-body); }
        .topbar-right { display: flex; align-items: center; gap: 8px; margin-left: auto; }
        .theme-toggle {
            width: 34px; height: 34px;
            border-radius: var(--radius);
            background: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 15px;
            transition: all var(--transition);
        }
        .theme-toggle:hover { color: var(--c-primary); border-color: var(--c-primary); }
        .topbar-user {
            display: flex; align-items: center; gap: 8px;
            padding: 4px 10px 4px 4px;
            border-radius: 20px;
            background: var(--bg-body);
            border: 1px solid var(--border);
        }
        .topbar-avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: var(--c-primary);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 600; font-size: 11px;
        }
        .topbar-name { font-size: 13px; font-weight: 500; color: var(--text-primary); }
        .topbar-role { font-size: 10px; color: var(--text-muted); }
        .topbar-logout {
            width: 34px; height: 34px;
            border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary); font-size: 16px;
            transition: all var(--transition);
        }
        .topbar-logout:hover { background: #fde8e8; color: var(--c-danger); }

        /* Formation-Switcher */
        .formation-switcher {
            display: flex; align-items: center; gap: 6px;
            padding: 4px 10px 4px 8px;
            border-radius: 20px;
            background: var(--bg-body);
            border: 1px solid var(--border);
            cursor: pointer; position: relative;
            font-size: 12px; font-weight: 500; color: var(--text-primary);
            transition: border-color var(--transition);
        }
        .formation-switcher:hover { border-color: var(--c-primary); }
        .formation-dot {
            width: 10px; height: 10px; border-radius: 50%;
            flex-shrink: 0;
        }
        .formation-dropdown {
            position: absolute; top: calc(100% + 6px); right: 0;
            min-width: 180px;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-lg); box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            z-index: 1100; display: none; overflow: hidden;
        }
        .formation-dropdown.show { display: block; }
        .formation-dropdown-item {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 12px; font-size: 12px;
            cursor: pointer; color: var(--text-primary);
            transition: background var(--transition);
        }
        .formation-dropdown-item:hover { background: var(--bg-body); }
        .formation-dropdown-item.active { font-weight: 600; color: var(--c-primary); }
        .formation-dropdown-sep { border-top: 1px solid var(--border-light); margin: 4px 0; }
        
        /* MAIN */
        .main-wrapper { margin-left: var(--sidebar-w); min-height: 100vh; transition: margin-left var(--transition); }
        .main-content { padding: calc(var(--topbar-h) + 16px) 16px 16px; }
        
        /* CARDS - Modern mit linkem Akzentrand */
        .card {
            /* Bootstrap setzt hier selbst color:var(--bs-body-color) (unthemed) – überschreibt sonst
               die geerbte Theme-Farbe für allen Text ohne eigene color-Regel (Listen, Absätze, ...) */
            color: var(--text-primary);
            background: var(--bg-card);
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 16px;
            border-left: 3px solid var(--border);
        }
        /* bg-light/bg-white erzwingen (per !important) eine immer helle Fläche – Text muss dann
           immer dunkel bleiben, unabhängig vom Theme (z.B. kleine Badges/Chips, die bewusst hell bleiben) */
        .bg-light, .bg-white { color: #212529 !important; }
        /* Größere Flächen (Karten, Trennbalken, Code-Blöcke) mit bg-light/bg-white sollen sich im
           Dark Mode wie der Rest der Oberfläche verhalten (dunkel), statt als heller Fremdkörper
           stehen zu bleiben. Kleine Badges/Chips (.badge) bleiben bewusst ausgenommen. */
        [data-theme="dark"] .bg-light:not(.badge), [data-theme="dark"] .bg-white:not(.badge) {
            background: var(--bg-card) !important;
            color: var(--text-primary) !important;
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border-light);
            padding: 12px 16px;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-primary);
        }
        .card-body { padding: 16px; }
        
        /* STAT CARDS - Modernes Design mit linkem Farbrand */
        .stat-card {
            border-left-width: 3px;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }
        .stat-card.border-primary { border-left-color: var(--c-primary) !important; }
        .stat-card.border-success { border-left-color: var(--c-success) !important; }
        .stat-card.border-warning { border-left-color: var(--c-warning) !important; }
        .stat-card.border-info { border-left-color: var(--c-info) !important; }
        .stat-card.border-danger { border-left-color: var(--c-danger) !important; }
        
        .stat-card .card-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
        }
        .stat-card h6 { font-size: 11px; color: var(--text-muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card h2 { font-size: 24px; font-weight: 700; margin: 0; color: var(--text-primary); }
        .stat-card small { font-size: 11px; color: var(--text-muted); }
        .stat-card .stat-icon { font-size: 32px; opacity: 0.15; }
        
        /* Einheitliche Farben für Icons und Text */
        .text-primary { color: var(--c-primary) !important; }
        .text-success { color: var(--c-success) !important; }
        .text-warning { color: var(--c-warning) !important; }
        .text-info { color: var(--c-info) !important; }
        .text-danger { color: var(--c-danger) !important; }
        
        .bg-primary { background-color: var(--c-primary) !important; }
        .bg-success { background-color: var(--c-success) !important; }
        .bg-warning { background-color: var(--c-warning) !important; color: #fff !important; }
        .bg-info { background-color: var(--c-info) !important; }
        .bg-danger { background-color: var(--c-danger) !important; }
        .bg-secondary { background-color: var(--text-muted) !important; }
        
        /* BUTTONS */
        .btn {
            font-size: 12px; font-weight: 500;
            padding: 6px 12px;
            border-radius: var(--radius);
            transition: all var(--transition);
        }
        .btn-sm { padding: 4px 8px; font-size: 11px; }
        .btn-primary { background: var(--c-primary); border-color: var(--c-primary); }
        .btn-primary:hover { background: var(--c-primary-light); border-color: var(--c-primary-light); }
        .btn-success { background: var(--c-success); border-color: var(--c-success); }
        .btn-warning { background: var(--c-warning); border-color: var(--c-warning); color: #fff; }
        .btn-info { background: var(--c-info); border-color: var(--c-info); }
        .btn-danger { background: var(--c-danger); border-color: var(--c-danger); }
        .btn-secondary { background: var(--text-muted); border-color: var(--text-muted); }
        .btn-outline-primary { color: var(--c-primary); border-color: var(--c-primary); }
        .btn-outline-primary:hover { background: var(--c-primary); color: #fff; }
        
        /* FORMS */
        .form-control, .form-select {
            font-size: 13px; padding: 8px 12px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            background: var(--bg-input);
            color: var(--text-primary);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--c-primary);
            box-shadow: 0 0 0 2px rgba(79,109,122,0.15);
            background: var(--bg-input);
            color: var(--text-primary);
        }
        .form-select option { background: var(--bg-input); color: var(--text-primary); }
        .form-control::placeholder { color: var(--text-muted); opacity: 1; }
        .form-label { font-size: 12px; font-weight: 500; margin-bottom: 4px; color: var(--text-secondary); }
        .form-check-input { background-color: var(--bg-input); border-color: var(--border); }
        .form-check-input:checked { background-color: var(--c-primary); border-color: var(--c-primary); }
        .input-group-text {
            font-size: 13px; padding: 8px 12px;
            background: var(--bg-body); border-color: var(--border);
            color: var(--text-muted);
        }
        
        /* TABLES */
        .table { font-size: 13px; color: var(--text-primary); background: var(--bg-card); margin-bottom: 0; }
        /* "color: inherit" reicht nicht: Zeilen mit Bootstrap-Varianten wie .table-danger/.table-warning
           setzen --bs-table-color selbst fest (z.B. #000) – das würde sich sonst in die Zelle vererben.
           Deshalb hier direkt unsere Theme-Farbe erzwingen statt zu vererben. */
        .table > :not(caption) > * > * { background: transparent; color: var(--text-primary); }
        .table thead th {
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.3px;
            color: var(--text-muted);
            background: var(--bg-body);
            border-bottom: 1px solid var(--border);
            padding: 10px 12px; white-space: nowrap;
        }
        .table td { padding: 10px 12px; vertical-align: middle; border-bottom: 1px solid var(--border-light); }
        .table-hover tbody tr:hover { background: var(--bg-body); }
        
        /* ALERTS */
        .alert { font-size: 13px; padding: 12px 16px; border-radius: var(--radius); border: none; margin-bottom: 16px; }
        .alert-warning { background: #fef6e6; color: #8a6d1b; }
        .alert-danger { background: #fde8e8; color: #8a3a3a; }
        .alert-success { background: #e8f5ed; color: #3a6b4a; }
        .alert-info { background: #e8f0f8; color: #3a5a7a; }
        [data-theme="dark"] .alert-warning   { background: rgba(209,154,62,0.15); color: #e0b563; }
        [data-theme="dark"] .alert-danger    { background: rgba(244,67,54,0.15); color: #f18b84; }
        [data-theme="dark"] .alert-success   { background: rgba(91,138,114,0.18); color: #8fc3a6; }
        [data-theme="dark"] .alert-info      { background: rgba(119,186,215,0.15); color: #9ed3ea; }
        [data-theme="dark"] .alert-secondary { background: var(--bg-input); color: var(--text-secondary); border: 1px solid var(--border); }

        /* MODAL */
        .modal-content { background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border); }
        .modal-header, .modal-footer { border-color: var(--border-light); }
        [data-theme="dark"] .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }

        /* ACCORDION */
        .accordion-item { background: var(--bg-card); border-color: var(--border); color: var(--text-primary); }
        .accordion-button { background: var(--bg-card); color: var(--text-primary); }
        .accordion-button:not(.collapsed) { background: var(--bg-body); color: var(--c-primary); box-shadow: inset 0 -1px 0 var(--border); }
        [data-theme="dark"] .accordion-button::after { filter: invert(1) grayscale(100%) brightness(1.8); }

        /* BADGES */
        .badge { font-size: 10px; font-weight: 500; padding: 3px 8px; border-radius: 3px; }
        
        /* LIST GROUPS */
        .list-group-item { border-color: var(--border-light); padding: 12px 0; background: transparent; color: var(--text-primary); }
        .list-group-flush .list-group-item:first-child { padding-top: 0; }
        .text-muted { color: var(--text-muted) !important; }
        
        /* DATATABLES */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { font-size: 12px; color: var(--text-primary) !important; }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input { font-size: 12px; padding: 4px 8px; }
        .page-link { font-size: 12px; padding: 4px 10px; color: var(--text-secondary); background: var(--bg-card); border-color: var(--border); }
        .page-link:hover { background: var(--bg-body); color: var(--text-primary); border-color: var(--border); }
        .page-item.active .page-link { background: var(--c-primary); border-color: var(--c-primary); color: #fff; }
        .page-item.disabled .page-link { background: var(--bg-card); color: var(--text-muted); border-color: var(--border); opacity: 0.6; }

        /* FULLCALENDAR */
        .fc, .fc .fc-toolbar-title, .fc-daygrid-day-number, .fc-col-header-cell-cushion,
        .fc-list-day-text, .fc-list-day-side-text, .fc-list-event-title, .fc-list-event-time {
            color: var(--text-primary);
        }
        .fc .fc-button:not(.fc-button-active):not(:hover) { color: var(--fc-button-text-color); }

        /* PAGE HEADER */
        .page-header {
            display: flex; flex-wrap: wrap;
            align-items: center; justify-content: space-between;
            gap: 12px; margin-bottom: 16px;
        }
        .page-title { font-size: 18px; font-weight: 600; margin: 0; color: var(--text-primary); }
        .page-title i { margin-right: 8px; opacity: 0.6; }
        
        h1.h2, .h1, .h2 { font-size: 18px; font-weight: 600; color: var(--text-primary); }
        h5 { font-size: 14px; font-weight: 600; }
        h6 { font-size: 12px; }
        
        /* OVERLAY */
        .sidebar-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.4); z-index: 1045;
            backdrop-filter: blur(2px);
        }
        
        /* SIDEBAR COLLAPSED (Desktop) */
        body.sidebar-collapsed .sidebar { transform: translateX(-100%); }
        body.sidebar-collapsed .topbar { left: 0; }
        body.sidebar-collapsed .main-wrapper { margin-left: 0; }
        
        /* CHART CONTAINER */
        .chart-container { position: relative; min-height: 200px; }
        
        /* RESPONSIVE */
        @media (max-width: 991.98px) {
            :root { --sidebar-w: 260px; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .sidebar-overlay.show { display: block; }
            .topbar { left: 0; }
            .main-wrapper { margin-left: 0; }
            .topbar-user .topbar-info { display: none; }
        }
        
        @media (max-width: 767.98px) {
            .main-content { padding: calc(var(--topbar-h) + 12px) 12px 12px; }
            .card-body { padding: 12px; }
            .stat-card h2 { font-size: 20px; }
            .stat-card .stat-icon { font-size: 24px; }
            .col-md-3 { margin-bottom: 8px; }
        }
        
        @media (max-width: 575.98px) {
            .topbar-name { display: none; }
            .main-content { padding: calc(var(--topbar-h) + 8px) 8px 8px; }
            .row { margin-left: -4px; margin-right: -4px; }
            .row > [class*="col-"] { padding-left: 4px; padding-right: 4px; }
        }
        
        /* PRINT */
        @media print {
            .sidebar, .topbar, .no-print { display: none !important; }
            .main-wrapper { margin: 0; }
            .main-content { padding: 0; }
            body { background: #fff; }
        }
    .tooltip-wide .tooltip-inner { max-width: 450px; text-align: left; white-space: pre-wrap; }
    </style>
</head>
<body>
    <?php if (Session::isLoggedIn()): ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="assets/logo_full_white.png" alt="" class="sidebar-logo">
            <!-- <span class="sidebar-brand"><?php echo APP_NAME; ?></span> -->
            <button class="sidebar-close" id="sidebarClose"><i class="bi bi-x-lg"></i></button>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-group">
                <ul class="nav flex-column">
                    <li><a class="nav-link <?php echo isActive('index', $pages, $currentPage); ?>" href="index.php">
                        <i class="bi bi-grid"></i> Dashboard
                    </a></li>
                </ul>
            </div>
            
            <?php if (Session::checkPermission('mitglieder', 'lesen') || Session::checkPermission('ausrueckungen', 'lesen')): ?>
            <div class="nav-group">
                <div class="nav-label">Organisation</div>
                <ul class="nav flex-column">
                    <?php if (Session::checkPermission('mitglieder', 'lesen')): ?>
                    <li><a class="nav-link <?php echo isActive('mitglieder', $pages, $currentPage); ?>" href="mitglieder.php">
                        <i class="bi bi-people"></i> Mitglieder
                    </a></li>
                    <?php endif; ?>
                    <?php if (Session::checkPermission('ausrueckungen', 'lesen')): ?>
                    <li><a class="nav-link <?php echo $currentPage === 'kalender' ? 'active' : ''; ?>" href="kalender.php">
                        <i class="bi bi-calendar3"></i> Kalender
                    </a></li>
                    <li><a class="nav-link <?php echo isActive('kalender', $pages, $currentPage) && $currentPage !== 'kalender' ? 'active' : ''; ?>" href="ausrueckungen.php">
                        <i class="bi bi-flag"></i> Ausrückungen
                    </a></li>
                    <?php endif; ?>
                    <?php if (Session::checkPermission('probe', 'lesen')): ?>
                    <li><a class="nav-link <?php echo isActive('probe', $pages, $currentPage); ?>" href="probe.php">
                        <i class="bi bi-broadcast"></i> Live
                    </a></li>
                    <?php if (Session::checkPermission('probe', 'schreiben')): ?>
                    <li><a class="nav-link <?php echo isActive('probe_leiter', $pages, $currentPage); ?>" href="probe_leiter.php" style="padding-left:28px;font-size:12px">
                        <i class="bi bi-sliders"></i> Live Leitung
                    </a></li>
                    <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (Session::checkPermission('noten', 'lesen') || Session::checkPermission('instrumente', 'lesen') || Session::checkPermission('uniformen', 'lesen')): ?>
            <div class="nav-group">
                <div class="nav-label">Inventar</div>
                <ul class="nav flex-column">
                    <?php if (Session::checkPermission('noten', 'lesen')): ?>
                    <li><a class="nav-link <?php echo isActive('noten', $pages, $currentPage); ?>" href="noten.php">
                        <i class="bi bi-music-note-list"></i> Noten
                    </a></li>
                    <li><a class="nav-link <?php echo isActive('notenbucher', $pages, $currentPage); ?>" href="notenbucher.php">
                        <i class="bi bi-journals"></i> Notenbücher
                    </a></li>
                    <?php endif; ?>
                    <?php if (Session::checkPermission('instrumente', 'lesen')): ?>
                    <li><a class="nav-link <?php echo isActive('instrumente', $pages, $currentPage); ?>" href="instrumente.php">
                        <i class="bi bi-disc"></i> Instrumente
                    </a></li>
                    <?php endif; ?>
                    <?php if (Session::checkPermission('uniformen', 'lesen')): ?>
                    <li><a class="nav-link <?php echo isActive('uniformen', $pages, $currentPage); ?>" href="uniformen.php">
                        <i class="bi bi-person-badge"></i> Uniformen
                    </a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (Session::checkPermission('finanzen', 'lesen')): ?>
            <div class="nav-group">
                <div class="nav-label">Finanzen</div>
                <ul class="nav flex-column">
                    <li><a class="nav-link <?php echo isActive('finanzen', $pages, $currentPage); ?>" href="finanzen.php">
                        <i class="bi bi-wallet2"></i> Kassenbuch
                    </a></li>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (Session::checkPermission('fest', 'lesen')): ?>
            <div class="nav-group">
                <div class="nav-label">Festverwaltung</div>
                <ul class="nav flex-column">
                    <li><a class="nav-link <?php echo isActive('fest', $pages, $currentPage); ?>" href="feste.php">
                        <i class="bi bi-stars"></i> Feste
                    </a></li>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (Session::checkPermission('formationen', 'schreiben')): ?>
            <div class="nav-group">
                <div class="nav-label">Formationen</div>
                <ul class="nav flex-column">
                    <li><a class="nav-link <?php echo isActive('formationen', $pages, $currentPage); ?>" href="formationen.php">
                        <i class="bi bi-collection"></i> Formationen
                    </a></li>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (Session::getRole() === 'admin'): ?>
            <div class="nav-group">
                <div class="nav-label">System</div>
                <ul class="nav flex-column">
                    <li><a class="nav-link <?php echo in_array($currentPage, ['benutzer', 'benutzer_bearbeiten']) ? 'active' : ''; ?>" href="benutzer.php">
                        <i class="bi bi-person-gear"></i> Benutzer
                    </a></li>
                    <li><a class="nav-link <?php echo in_array($currentPage, ['rollen', 'rolle_bearbeiten', 'berechtigungen_bearbeiten']) ? 'active' : ''; ?>" href="rollen.php">
                        <i class="bi bi-shield-check"></i> Rollen
                    </a></li>
                    <li><a class="nav-link <?php echo $currentPage === 'stammdaten' ? 'active' : ''; ?>" href="stammdaten.php">
                        <i class="bi bi-database-gear"></i> Stammdaten
                    </a></li>
                    <li><a class="nav-link <?php echo $currentPage === 'einstellungen' ? 'active' : ''; ?>" href="einstellungen.php">
                        <i class="bi bi-gear"></i> Einstellungen
                    </a></li>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="nav-group">
                <div class="nav-label">Hilfe</div>
                <ul class="nav flex-column">
                    <li><a class="nav-link" href="/docs">
                        <i class="bi bi-person-gear"></i> Dokumentation
                    </a></li>
                </ul>
            </div>
        </nav>
    </aside>
    
    <header class="topbar">
        <button class="topbar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>

        <div class="topbar-right">
            <button class="theme-toggle" id="themeToggle" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Design wechseln">
                <i class="bi bi-moon"></i>
            </button>

            <?php if (Session::isAdmin()):
                // Update-Check: max. einmal pro Stunde in der Session cachen
                $updateAvailable = false;
                $cacheKey = '_update_check';
                $cacheTs  = '_update_check_ts';
                $ttl      = 3600; // 1 Stunde
                $needsCheck = !Session::has($cacheKey) || (time() - (int)Session::get($cacheTs, 0)) > $ttl;
                if ($needsCheck) {
                    $rawUrl = "https://raw.githubusercontent.com/Hanner72/syncopa/main/docs/changelog.md";
                    $body = false;
                    if (function_exists('curl_init')) {
                        $ch = curl_init($rawUrl);
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT        => 5,
                            CURLOPT_USERAGENT      => 'syncopa-updater/1.0',
                            CURLOPT_SSL_VERIFYPEER => true,
                            CURLOPT_FOLLOWLOCATION => true,
                        ]);
                        $body = curl_exec($ch);
                        $ok   = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
                        curl_close($ch);
                        if (!$ok) $body = false;
                    } elseif (ini_get('allow_url_fopen')) {
                        $ctx  = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'syncopa-updater/1.0']]);
                        $body = @file_get_contents($rawUrl, false, $ctx);
                    }
                    if ($body) {
                        preg_match('/##\s*\[([0-9]+\.[0-9]+\.[0-9]+)\]/', $body, $m);
                        $remoteVersion = $m[1] ?? null;
                        $updateAvailable = $remoteVersion && version_compare(APP_VERSION, $remoteVersion, '<');
                        Session::set($cacheKey, $updateAvailable);
                        Session::set($cacheTs,  time());
                    }
                    // Bei Fehler nicht cachen → nächste Seite neu versuchen
                } else {
                    $updateAvailable = (bool)Session::get($cacheKey, false);
                }
            ?>
            <?php if ($updateAvailable): ?>
            <a href="update.php" class="theme-toggle position-relative text-decoration-none" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Update verfügbar!">
                <i class="bi bi-arrow-up-circle text-warning"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning" style="font-size:9px;padding:2px 5px;">!</span>
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <?php if (Session::checkPermission('fest', 'lesen')):
                $todoObj   = new FestTodo();
                $benutzerId = Session::isAdmin() ? null : Session::getUserId();
                $todoCounts = $todoObj->getOffeneCount($benutzerId);
            ?>
            <div class="dropdown">
                <a href="fest_todos_alle.php" class="theme-toggle position-relative text-decoration-none" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Todos">
                    <i class="bi bi-check2-square"></i>
                    <?php if ($todoCounts['ueberfaellig'] > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px;padding:2px 5px;">
                        <?php echo $todoCounts['ueberfaellig']; ?>
                    </span>
                    <?php elseif ($todoCounts['offen'] > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning" style="font-size:9px;padding:2px 5px;">
                        <?php echo $todoCounts['offen']; ?>
                    </span>
                    <?php endif; ?>
                </a>
            </div>
            <?php endif; ?>

            <?php
            // Formation-Switcher: alle verfügbaren Formationen laden
            $activeFormationId = Session::getFormationId();
            if (Session::isAdmin()) {
                $_allFormationen = (new Formation())->getAll(true);
            } else {
                $_formIds = Session::getFormationIds();
                $_allFormationen = [];
                if (!empty($_formIds)) {
                    $_formObj = new Formation();
                    foreach ($_formIds as $_fid) {
                        $_fdata = $_formObj->getById($_fid);
                        if ($_fdata && $_fdata['aktiv']) $_allFormationen[] = $_fdata;
                    }
                }
            }
            if (count($_allFormationen) > 0):
                $activeFormation = $activeFormationId
                    ? array_values(array_filter($_allFormationen, fn($f) => $f['id'] == $activeFormationId))[0] ?? null
                    : null;
            ?>
            <div class="formation-switcher" id="formationSwitcher">
                <span class="formation-dot" style="background:<?php echo htmlspecialchars($activeFormation['farbe'] ?? '#8699ac'); ?>"></span>
                <span id="formationSwitcherLabel">
                    <?php echo $activeFormation ? htmlspecialchars($activeFormation['kuerzel'] ?: $activeFormation['name']) : 'Alle'; ?>
                </span>
                <i class="bi bi-chevron-down" style="font-size:10px;opacity:0.6"></i>
                <div class="formation-dropdown" id="formationDropdown">
                    <?php if (Session::isAdmin()): ?>
                    <div class="formation-dropdown-item <?php echo $activeFormationId === null ? 'active' : ''; ?>"
                         onclick="switchFormation(0)">
                        <span class="formation-dot" style="background:#8699ac"></span>
                        Alle Formationen
                    </div>
                    <div class="formation-dropdown-sep"></div>
                    <?php endif; ?>
                    <?php foreach ($_allFormationen as $_f): ?>
                    <div class="formation-dropdown-item <?php echo $activeFormationId == $_f['id'] ? 'active' : ''; ?>"
                         onclick="switchFormation(<?php echo $_f['id']; ?>)">
                        <span class="formation-dot" style="background:<?php echo htmlspecialchars($_f['farbe']); ?>"></span>
                        <?php echo htmlspecialchars($_f['name']); ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (Session::isAdmin()): ?>
                    <div class="formation-dropdown-sep"></div>
                    <div class="formation-dropdown-item" onclick="location.href='formationen.php'">
                        <i class="bi bi-gear" style="width:10px;height:10px"></i>
                        Formationen verwalten
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="topbar-user">
                <div class="topbar-avatar"><?php echo strtoupper(substr(Session::getUsername(), 0, 1)); ?></div>
                <div class="topbar-info d-none d-sm-block">
                    <div class="topbar-name"><?php echo htmlspecialchars(Session::getUsername()); ?></div>
                    <div class="topbar-role"><?php echo ucfirst(Session::getRole()); ?></div>
                </div>
            </div>
            
            <a href="logout.php" class="topbar-logout" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Abmelden">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </header>
    <script>
    (function() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var toggleBtn = document.getElementById('sidebarToggle');
        var closeBtn = document.getElementById('sidebarClose');
        var body = document.body;
        var isMobile = function() { return window.innerWidth < 992; };

        function openSidebar() {
            if (isMobile()) {
                sidebar.classList.add('show');
                overlay.classList.add('show');
            } else {
                body.classList.remove('sidebar-collapsed');
            }
        }
        function closeSidebar() {
            if (isMobile()) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            } else {
                body.classList.add('sidebar-collapsed');
            }
        }

        if (toggleBtn) toggleBtn.addEventListener('click', function() {
            if (isMobile()) {
                sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
            } else {
                body.classList.contains('sidebar-collapsed') ? openSidebar() : closeSidebar();
            }
        });
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);
    })();
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            new bootstrap.Tooltip(el);
        });
    });
    </script>
    <script>
    // Formation-Switcher
    (function() {
        var switcher = document.getElementById('formationSwitcher');
        var dropdown = document.getElementById('formationDropdown');
        if (!switcher || !dropdown) return;
        switcher.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        document.addEventListener('click', function() {
            dropdown.classList.remove('show');
        });
    })();

    function switchFormation(id) {
        var body = 'formation_id=' + id;
        fetch('api/formation_switch.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success) location.reload();
            else alert('Fehler: ' + (d.error || 'Unbekannt'));
        });
    }
    </script>
    <?php endif; ?>

    <div class="main-wrapper">
        <main class="main-content">
            <?php if ($flash = Session::getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
