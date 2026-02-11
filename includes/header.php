<?php
// includes/header.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';
Session::start();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Seiten-Gruppen für aktive Menüpunkte
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
    if (isset($pages[$page])) {
        return in_array($current, $pages[$page]) ? 'active' : '';
    }
    return $current === $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --sidebar-w: 200px;
            --topbar-h: 60px;
            --radius: 6px;
            --radius-lg: 8px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --transition: 0.2s ease;
        }
        
        [data-theme="light"] {
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --bg-sidebar: #0f172a;
            --bg-input: #ffffff;
            --border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --sidebar-text: #94a3b8;
            --sidebar-hover: rgba(255,255,255,0.05);
            --sidebar-active: rgba(99,102,241,0.15);
        }
        
        [data-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-sidebar: #020617;
            --bg-input: #1e293b;
            --border: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --sidebar-text: #94a3b8;
            --sidebar-hover: rgba(255,255,255,0.05);
            --sidebar-active: rgba(99,102,241,0.2);
        }
        
        * { font-family: 'Inter', system-ui, sans-serif; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg-body); color: var(--text-primary); font-size: 12px; line-height: 1.5; overflow-x: hidden; }
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
            padding: 0 10px;
            display: flex; align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .sidebar-logo { width: 24px; height: 24px; border-radius: 5px; margin-right: 8px; }
        .sidebar-brand { font-size: 26px; font-weight: 600; color: #fff; }
        .sidebar-close {
            display: none; margin-left: auto;
            background: none; border: none;
            color: #64748b; font-size: 16px; padding: 4px;
        }
        .sidebar-nav { flex: 1; padding: 6px; overflow-y: auto; }
        .sidebar-nav::-webkit-scrollbar { width: 2px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }
        .nav-group { margin-bottom: 12px; }
        .nav-label {
            font-size: 12px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: #596a84; padding: 4px 8px; margin-bottom: 2px;
        }
        .sidebar .nav-link {
            display: flex; align-items: center;
            padding: 6px 8px; margin-bottom: 1px;
            border-radius: var(--radius);
            color: var(--sidebar-text);
            font-size: 14px; font-weight: 500;
            transition: all var(--transition);
        }
        .sidebar .nav-link i { font-size: 14px; width: 16px; margin-right: 6px; opacity: 0.7; }
        .sidebar .nav-link:hover { background: var(--sidebar-hover); color: #e2e8f0; }
        .sidebar .nav-link.active { background: var(--sidebar-active); color: #a5b4fc; }
        .sidebar .nav-link.active i { opacity: 1; color: #818cf8; }

        /* TOPBAR */
        .topbar {
            position: fixed; top: 0; left: var(--sidebar-w); right: 0;
            height: var(--topbar-h);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center;
            padding: 0 10px; z-index: 1040; gap: 6px;
            transition: left var(--transition);
        }
        .topbar-toggle {
            display: none; background: none; border: none;
            font-size: 20px; color: var(--text-primary);
            padding: 4px; border-radius: var(--radius);
        }
        .topbar-toggle:hover { background: var(--bg-body); }
        .topbar-right { display: flex; align-items: center; gap: 4px; margin-left: auto; }
        .theme-toggle {
            width: 32px; height: 32px;
            border-radius: var(--radius);
            background: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 16px;
            transition: all var(--transition);
        }
        .theme-toggle:hover { color: var(--primary); border-color: var(--primary); }
        .topbar-user {
            display: flex; align-items: center; gap: 6px;
            padding: 3px 8px 3px 3px;
            border-radius: 50px;
            background: var(--bg-body);
            border: 1px solid var(--border);
        }
        .topbar-avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 600; font-size: 9px;
        }
        .topbar-name { font-size: 14px; font-weight: 500; color: var(--text-primary); }
        .topbar-role { font-size: 11px; color: var(--text-muted); }
        .topbar-logout {
            width: 32px; height: 32px;
            border-radius: var(--radius);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary); font-size: 18px;
            transition: all var(--transition);
        }
        .topbar-logout:hover { background: #fee2e2; color: #dc2626; }

        /* MAIN */
        .main-wrapper { margin-left: var(--sidebar-w); min-height: 100vh; transition: margin-left var(--transition); }
        .main-content { padding: calc(var(--topbar-h) + 10px) 10px 10px; }

        /* CARDS */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 10px;
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border);
            padding: 8px 10px; font-weight: 600; font-size: 11px;
        }
        .card-body { padding: 10px; }

        /* STAT CARDS */
        .stat-card .card-body { display: flex; align-items: center; gap: 10px; padding: 12px; }
        .stat-icon {
            width: 32px; height: 32px;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; flex-shrink: 0;
        }
        .stat-value { font-size: 16px; font-weight: 700; line-height: 1.2; }
        .stat-label { font-size: 10px; color: var(--text-secondary); }

        /* BUTTONS */
        .btn {
            font-size: 11px; font-weight: 500;
            padding: 5px 10px;
            border-radius: var(--radius);
            transition: all var(--transition);
        }
        .btn-sm { padding: 3px 6px; font-size: 10px; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }

        /* FORMS */
        .form-control, .form-select {
            font-size: 14px; padding: 5px 8px;
            border-radius: var(--radius);
            border-color: var(--border);
            background: var(--bg-input);
            color: var(--text-primary);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(99,102,241,0.1);
            background: var(--bg-input);
            color: var(--text-primary);
        }
        .form-label { font-size: 10px; font-weight: 500; margin-bottom: 3px; color: var(--text-secondary); }
        .input-group-text {
            font-size: 14px; padding: 5px 8px;
            background: var(--bg-body); border-color: var(--border);
            color: var(--text-secondary);
        }

        /* TABLES */
        .table { font-size: 14px; color: var(--text-primary); margin-bottom: 0; }
        .table thead th {
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.3px;
            color: var(--text-secondary);
            background: var(--bg-body);
            border-bottom: 1px solid var(--border);
            padding: 6px 8px; white-space: nowrap;
        }
        .table td { padding: 6px 8px; vertical-align: middle; border-bottom: 1px solid var(--border); }
        .table-responsive { margin: -1px; }

        /* ALERTS */
        .alert { font-size: 14px; padding: 8px 10px; border-radius: var(--radius); border: none; margin-bottom: 10px; }
        .alert .btn-close { padding: 10px; }

        /* BADGES */
        .badge { font-size: 11px; font-weight: 500; padding: 2px 5px; border-radius: 4px; }

        /* DATATABLES */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { font-size: 12px; }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input { font-size: 12px; padding: 3px 6px; }
        .page-link { font-size: 12px; padding: 3px 6px; }

        /* PAGE HEADER */
        .page-header {
            display: flex; flex-wrap: wrap;
            align-items: center; justify-content: space-between;
            gap: 6px; margin-bottom: 10px;
        }
        .page-title {
            font-size: 14px; font-weight: 600; margin: 0;
            display: flex; align-items: center; gap: 6px;
        }
        .page-title i { font-size: 16px; opacity: 0.7; }

        /* OVERLAY */
        .sidebar-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 1045;
            backdrop-filter: blur(2px);
        }

        /* RESPONSIVE */
        @media (max-width: 991.98px) {
            :root { --sidebar-w: 240px; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .sidebar-overlay.show { display: block; }
            .sidebar-close { display: block; }
            .topbar { left: 0; }
            .topbar-toggle { display: block; }
            .main-wrapper { margin-left: 0; }
            .topbar-user .topbar-info { display: none; }
        }
        
        @media (max-width: 767.98px) {
            .main-content { padding: calc(var(--topbar-h) + 6px) 6px 6px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .page-title { font-size: 13px; }
            .card-body { padding: 8px; }
            .stat-card .card-body { padding: 8px; }
            .stat-icon { width: 28px; height: 28px; font-size: 12px; }
            .stat-value { font-size: 14px; }
            .btn { padding: 5px 8px; }
            
            .table-stack thead { display: none; }
            .table-stack tbody tr { display: block; padding: 8px 0; border-bottom: 1px solid var(--border); }
            .table-stack tbody td { display: flex; justify-content: space-between; padding: 3px 0; border: none; }
            .table-stack tbody td::before { content: attr(data-label); font-weight: 500; color: var(--text-secondary); margin-right: 8px; }
        }
        
        @media (max-width: 575.98px) {
            .topbar-name { display: none; }
            .row > [class*="col-"] { padding-left: 3px; padding-right: 3px; }
            .row { margin-left: -3px; margin-right: -3px; }
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
            <img src="assets/logo.svg" alt="" class="sidebar-logo">
            <span class="sidebar-brand"><?php echo APP_NAME; ?></span>
            <button class="sidebar-close" id="sidebarClose"><i class="bi bi-x"></i></button>
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
    <?php endif; ?>
    
    <div class="main-wrapper">
        <main class="main-content">
            <?php if ($flash = Session::getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
