<?php
// includes/header.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';
Session::start();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- FullCalendar CSS (für Kalender) -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            min-height: calc(100vh - 56px);
            background: linear-gradient(180deg, #1a2332 0%, #2c3e50 100%);
            box-shadow: 2px 0 5px rgba(0,0,0,.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,.8);
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            margin: 0.2rem 0.5rem;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 0.5rem;
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 2px solid #f8f9fa;
            font-weight: 600;
            padding: 1rem 1.5rem;
        }
        
        .stat-card {
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .badge-status {
            padding: 0.35em 0.65em;
            font-size: 0.875rem;
        }
        
        .table-actions a {
            margin: 0 0.25rem;
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .user-menu {
            border-left: 1px solid rgba(255,255,255,.1);
            padding-left: 1rem;
        }
        
        @media print {
            .sidebar, .navbar, .no-print {
                display: none !important;
            }
            .main-content {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-music-note-beamed"></i> <?php echo APP_NAME; ?>
            </a>
            
            <?php if (Session::isLoggedIn()): ?>
            <div class="d-flex align-items-center ms-auto user-menu">
                <span class="text-white me-3">
                    <i class="bi bi-person-circle"></i> 
                    <?php echo htmlspecialchars(Session::getUsername()); ?>
                    <small class="text-muted">(<?php echo ucfirst(Session::getRole()); ?>)</small>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Abmelden
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <?php if (Session::isLoggedIn()): ?>
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse px-0">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'index' ? 'active' : ''; ?>" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        
                        <?php if (Session::checkPermission('mitglieder', 'lesen')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'mitglieder' ? 'active' : ''; ?>" href="mitglieder.php">
                                <i class="bi bi-people"></i> Mitglieder
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Session::checkPermission('ausrueckungen', 'lesen')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'kalender' ? 'active' : ''; ?>" href="kalender.php">
                                <i class="bi bi-calendar-event"></i> Kalender
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'ausrueckungen' ? 'active' : ''; ?>" href="ausrueckungen.php">
                                <i class="bi bi-flag"></i> Ausrückungen
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Session::checkPermission('noten', 'lesen')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'noten' ? 'active' : ''; ?>" href="noten.php">
                                <i class="bi bi-music-note-list"></i> Noten
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Session::checkPermission('instrumente', 'lesen')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'instrumente' ? 'active' : ''; ?>" href="instrumente.php">
                                <i class="bi bi-diagram-3"></i> Instrumente
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Session::checkPermission('uniformen', 'lesen')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'uniformen' ? 'active' : ''; ?>" href="uniformen.php">
                                <i class="bi bi-diagram-3"></i> Uniformen
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Session::checkPermission('finanzen', 'lesen')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'finanzen' ? 'active' : ''; ?>" href="finanzen.php">
                                <i class="bi bi-cash-coin"></i> Finanzen
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (Session::getRole() === 'admin'): ?>
                        <li class="nav-item mt-3">
                            <h6 class="px-3 text-white-50 text-uppercase small">Administration</h6>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'benutzer' ? 'active' : ''; ?>" href="benutzer.php">
                                <i class="bi bi-person-gear"></i> Benutzer
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'rollen' ? 'active' : ''; ?>" href="rollen.php">
                                <i class="bi bi-person-gear"></i> Rollen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'einstellungen' ? 'active' : ''; ?>" href="einstellungen.php">
                                <i class="bi bi-gear"></i> Einstellungen
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
            <?php endif; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <?php
                // Flash Messages anzeigen
                $flash = Session::getFlashMessage();
                if ($flash):
                ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
