<?php
require_once 'config.php';
require_once 'includes.php';
Session::start();

if (Session::isLoggedIn()) { header('Location: index.php'); exit; }

$error = '';
$benutzername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzername = $_POST['benutzername'] ?? '';
    $passwort = $_POST['passwort'] ?? '';
    
    if ($benutzername && $passwort) {
        $db = Database::getInstance();
        $user = $db->fetchOne("SELECT * FROM benutzer WHERE benutzername = ? AND aktiv = 1", [$benutzername]);
        
        if ($user && password_verify($passwort, $user['passwort_hash'])) {
            Session::set('user_id', $user['id']);
            Session::set('username', $user['benutzername']);
            Session::set('rolle', $user['rolle']);
            $db->execute("UPDATE benutzer SET letzter_login = NOW() WHERE id = ?", [$user['id']]);
            $db->execute("INSERT INTO aktivitaetslog (benutzer_id, aktion, beschreibung, ip_adresse) VALUES (?, ?, ?, ?)",
                [$user['id'], 'login', 'Login', $_SERVER['REMOTE_ADDR']]);
            header('Location: index.php'); exit;
        } else { $error = 'Ungültige Anmeldedaten'; }
    } else { $error = 'Bitte alle Felder ausfüllen'; }
}
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{font-family:'Inter',system-ui,sans-serif}
        [data-theme="light"]{--bg:#0f172a;--card:#fff;--text:#1e293b;--muted:#64748b;--border:#e2e8f0;--input:#fff}
        [data-theme="dark"]{--bg:#020617;--card:#1e293b;--text:#f1f5f9;--muted:#94a3b8;--border:#334155;--input:#0f172a}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg);padding:12px}
        .card{max-width:340px;width:100%;background:var(--card);border-radius:10px;box-shadow:0 20px 40px rgba(0,0,0,.3);border:none}
        .card-header{padding:20px 16px 12px;text-align:center;border:none;background:none}
        .logo{width:180px;height:auto;margin-bottom:8px}
        h1{font-size:16px;font-weight:600;color:var(--text);margin:0}
        .sub{font-size:11px;color:var(--muted)}
        .card-body{padding:0 16px 20px}
        label{font-size:10px;font-weight:500;color:var(--muted);margin-bottom:3px}
        .form-control{font-size:11px;padding:7px 9px;border-radius:5px;border:1px solid var(--border);background:var(--input);color:var(--text)}
        .form-control:focus{border-color:#6366f1;box-shadow:0 0 0 2px rgba(99,102,241,.1);background:var(--input);color:var(--text)}
        .input-group-text{font-size:11px;padding:7px 9px;background:var(--input);border-color:var(--border);color:var(--muted)}
        .btn-primary{width:100%;padding:9px;background:linear-gradient(135deg,#6366f1,#4f46e5);border:none;border-radius:5px;font-size:11px;font-weight:600}
        .btn-primary:hover{background:linear-gradient(135deg,#4f46e5,#4338ca)}
        .btn-google{width:100%;padding:9px;background:var(--input);border:1px solid var(--border);border-radius:5px;font-size:11px;font-weight:500;color:var(--text)}
        .btn-google:hover{background:var(--border);color:var(--text)}
        .divider{display:flex;align-items:center;gap:8px;margin:12px 0;font-size:10px;color:var(--muted)}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}
        .alert{font-size:10px;padding:7px 9px;border-radius:5px;border:none;margin-bottom:10px}
        .footer{text-align:center;padding-top:10px;border-top:1px solid var(--border);font-size:9px;color:var(--muted)}
        .theme-btn{position:fixed;top:10px;right:10px;width:28px;height:28px;border-radius:5px;background:rgba(255,255,255,.1);border:none;color:#94a3b8;font-size:12px;cursor:pointer}
        .theme-btn:hover{background:rgba(255,255,255,.15);color:#fff}
    </style>
</head>
<body>
    <button class="theme-btn" id="themeToggle"><i class="bi bi-moon"></i></button>
    
    <div class="card">
        <div class="card-header">
            <img src="assets/logo_full.png" alt="Syncopa" class="logo">
            
            <p class="sub">Musikvereinsverwaltung</p>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-2">
                    <label>Benutzername</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" name="benutzername" required autofocus value="<?php echo htmlspecialchars($benutzername); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label>Passwort</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="passwort" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Anmelden</button>
                
                <?php if (defined('GOOGLE_OAUTH_ENABLED') && GOOGLE_OAUTH_ENABLED): ?>
                <div class="divider">oder</div>
                <a href="login_google.php" class="btn btn-google">
                    <svg width="12" height="12" viewBox="0 0 24 24" style="vertical-align:-1px;margin-right:4px">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>Google
                </a>
                <?php endif; ?>
            </form>
            <div class="footer"><strong>admin</strong> / <strong>admin123</strong></div>
        </div>
    </div>
    
    <script>
    const t=document.getElementById('themeToggle'),h=document.documentElement,s=localStorage.getItem('theme')||'light';
    h.setAttribute('data-theme',s);t.querySelector('i').className=s==='light'?'bi bi-moon':'bi bi-sun';
    t.onclick=()=>{const n=h.getAttribute('data-theme')==='light'?'dark':'light';h.setAttribute('data-theme',n);localStorage.setItem('theme',n);t.querySelector('i').className=n==='light'?'bi bi-moon':'bi bi-sun'};
    </script>
</body>
</html>
