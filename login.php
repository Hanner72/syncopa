<?php
// login.php
require_once 'config.php';
require_once 'includes.php';

Session::start();

// Wenn bereits eingeloggt, zum Dashboard weiterleiten
if (Session::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzername = $_POST['benutzername'] ?? '';
    $passwort = $_POST['passwort'] ?? '';
    
    if (!empty($benutzername) && !empty($passwort)) {
        $db = Database::getInstance();
        $sql = "SELECT * FROM benutzer WHERE benutzername = ? AND aktiv = 1";
        $benutzer = $db->fetchOne($sql, [$benutzername]);
        
        if ($benutzer && password_verify($passwort, $benutzer['passwort_hash'])) {
            // Login erfolgreich
            Session::set('user_id', $benutzer['id']);
            Session::set('username', $benutzer['benutzername']);
            Session::set('rolle', $benutzer['rolle']);
            
            // Letzten Login aktualisieren
            $db->execute("UPDATE benutzer SET letzter_login = NOW() WHERE id = ?", [$benutzer['id']]);
            
            // Aktivitätslog
            $db->execute(
                "INSERT INTO aktivitaetslog (benutzer_id, aktion, beschreibung, ip_adresse) VALUES (?, ?, ?, ?)",
                [$benutzer['id'], 'login', 'Benutzer hat sich angemeldet', $_SERVER['REMOTE_ADDR']]
            );
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Ungültiger Benutzername oder Passwort';
        }
    } else {
        $error = 'Bitte alle Felder ausfüllen';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            width: 100%;
        }
        
        .btn-login:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="bi bi-music-note-beamed"></i>
            <h4 class="mb-0"><?php echo APP_NAME; ?></h4>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="benutzername" class="form-label">Benutzername</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="benutzername" name="benutzername" 
                               required autofocus value="<?php echo htmlspecialchars($benutzername ?? ''); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="passwort" class="form-label">Passwort</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="passwort" name="passwort" required>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Angemeldet bleiben</label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Anmelden
                </button>
                
                <!-- Debug: Google OAuth Status -->
                <!-- GOOGLE_OAUTH_ENABLED: <?php echo defined('GOOGLE_OAUTH_ENABLED') ? (GOOGLE_OAUTH_ENABLED ? 'true' : 'false') : 'nicht definiert'; ?> -->
                
                <?php if (defined('GOOGLE_OAUTH_ENABLED') && GOOGLE_OAUTH_ENABLED === true): ?>
                <div class="mt-3">
                    <div class="d-flex align-items-center mb-3">
                        <hr class="flex-grow-1">
                        <span class="px-2 text-muted small">oder</span>
                        <hr class="flex-grow-1">
                    </div>
                    <a href="login_google.php" class="btn btn-outline-dark w-100">
                        <svg width="18" height="18" viewBox="0 0 24 24" class="me-2" style="vertical-align: text-bottom;">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Mit Google anmelden
                    </a>
                </div>
                <?php endif; ?>
            </form>
            
            <div class="mt-3 text-center">
                <small class="text-muted">
                    Standard-Login: admin / admin123<br>
                    <strong>Bitte nach dem ersten Login ändern!</strong>
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
