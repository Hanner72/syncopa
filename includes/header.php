<?php
// includes/header.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';
Session::start();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

$pages = [
    'kalender' => ['kalender', 'ausrueckungen', 'ausrueckung_detail', 'ausrueckung_bearbeiten'],
    'mitglieder' => ['mitglieder', 'mitglied_detail', 'mitglied_bearbeiten'],
    'noten' => ['noten', 'noten_bearbeiten'],
    'instrumente' => ['instrumente', 'instrument_detail', 'instrument_bearbeiten'],
    'uniformen' => ['uniformen', 'uniform_detail', 'uniform_bearbeiten', 'uniform_mitglied', 'uniform_kleidungsstuecke', 'uniform_kategorien'],
    'finanzen' => ['finanzen', 'transaktion_bearbeiten', 'beitraege_verwalten'],
    'admin' => ['benutzer', 'benutzer_bearbeiten', 'rollen', 'rolle_bearbeiten', 'einstellungen', 'berechtigungen_bearbeiten']
];

function isActive($page, $pages, $current) {
    if (isset($pages[$page])) return in_array($current, $pages[$page]) ? 'active' : '';
    return $current === $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
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
        
        /* MAIN */
        .main-wrapper { margin-left: var(--sidebar-w); min-height: 100vh; transition: margin-left var(--transition); }
        .main-content { padding: calc(var(--topbar-h) + 16px) 16px 16px; }
        
        /* CARDS - Modern mit linkem Akzentrand */
        .card {
            background: var(--bg-card);
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 16px;
            border-left: 3px solid var(--border);
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
        .form-label { font-size: 12px; font-weight: 500; margin-bottom: 4px; color: var(--text-secondary); }
        .input-group-text {
            font-size: 13px; padding: 8px 12px;
            background: var(--bg-body); border-color: var(--border);
            color: var(--text-muted);
        }
        
        /* TABLES */
        .table { font-size: 13px; color: var(--text-primary); margin-bottom: 0; }
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
        
        /* BADGES */
        .badge { font-size: 10px; font-weight: 500; padding: 3px 8px; border-radius: 3px; }
        
        /* LIST GROUPS */
        .list-group-item { border-color: var(--border-light); padding: 12px 0; background: transparent; }
        .list-group-flush .list-group-item:first-child { padding-top: 0; }
        
        /* DATATABLES */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { font-size: 12px; }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input { font-size: 12px; padding: 4px 8px; }
        .page-link { font-size: 12px; padding: 4px 10px; color: var(--text-secondary); }
        .page-item.active .page-link { background: var(--c-primary); border-color: var(--c-primary); }
        
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
        </nav>
    </aside>
    
    <header class="topbar">
        <button class="topbar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        
        <div class="topbar-right">
            <button class="theme-toggle" id="themeToggle" title="Design wechseln">
                <i class="bi bi-moon"></i>
            </button>
            
            <div class="topbar-user">
                <div class="topbar-avatar"><?php echo strtoupper(substr(Session::getUsername(), 0, 1)); ?></div>
                <div class="topbar-info d-none d-sm-block">
                    <div class="topbar-name"><?php echo htmlspecialchars(Session::getUsername()); ?></div>
                    <div class="topbar-role"><?php echo ucfirst(Session::getRole()); ?></div>
                </div>
            </div>
            
            <a href="logout.php" class="topbar-logout" title="Abmelden">
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
    <?php endif; ?>
    
    <div class="main-wrapper">
        <main class="main-content">
            <?php if ($flash = Session::getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
