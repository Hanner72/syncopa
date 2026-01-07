<?php
/**
 * Google OAuth Callback
 * Verarbeitet die Antwort von Google und loggt den Benutzer ein
 */
require_once 'config.php';
require_once 'includes.php';

Session::start();

// Prüfen ob Google OAuth aktiviert ist
if (!defined('GOOGLE_OAUTH_ENABLED') || !GOOGLE_OAUTH_ENABLED) {
    Session::setFlashMessage('danger', 'Google Login ist nicht aktiviert.');
    header('Location: login.php');
    exit;
}

// Fehler von Google prüfen
if (isset($_GET['error'])) {
    Session::setFlashMessage('danger', 'Google Login abgebrochen: ' . htmlspecialchars($_GET['error']));
    header('Location: login.php');
    exit;
}

// CSRF-Token prüfen
if (!isset($_GET['state']) || $_GET['state'] !== Session::get('oauth_state')) {
    Session::setFlashMessage('danger', 'Ungültiger Sicherheitstoken. Bitte erneut versuchen.');
    header('Location: login.php');
    exit;
}
Session::remove('oauth_state');

// Authorization Code prüfen
if (!isset($_GET['code'])) {
    Session::setFlashMessage('danger', 'Kein Autorisierungscode erhalten.');
    header('Location: login.php');
    exit;
}

$code = $_GET['code'];

// Access Token von Google holen
$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    Session::setFlashMessage('danger', 'Fehler beim Abrufen des Tokens von Google.');
    header('Location: login.php');
    exit;
}

$tokenJson = json_decode($tokenResponse, true);
if (!isset($tokenJson['access_token'])) {
    Session::setFlashMessage('danger', 'Kein Access Token erhalten.');
    header('Location: login.php');
    exit;
}

$accessToken = $tokenJson['access_token'];

// Benutzerdaten von Google holen
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
$userResponse = curl_exec($ch);
curl_close($ch);

$googleUser = json_decode($userResponse, true);

if (!isset($googleUser['email'])) {
    Session::setFlashMessage('danger', 'Keine E-Mail-Adresse von Google erhalten.');
    header('Location: login.php');
    exit;
}

$email = $googleUser['email'];
$name = $googleUser['name'] ?? '';
$googleId = $googleUser['id'] ?? '';

$db = Database::getInstance();

// Prüfen ob Benutzer bereits existiert (per E-Mail oder Google-ID)
$benutzer = $db->fetchOne(
    "SELECT * FROM benutzer WHERE email = ? OR google_id = ?", 
    [$email, $googleId]
);

if ($benutzer) {
    // Benutzer existiert
    if (!$benutzer['aktiv']) {
        Session::setFlashMessage('danger', 'Ihr Benutzerkonto ist deaktiviert. Bitte kontaktieren Sie den Administrator.');
        header('Location: login.php');
        exit;
    }
    
    // Google-ID aktualisieren falls noch nicht gesetzt
    if (empty($benutzer['google_id'])) {
        $db->execute("UPDATE benutzer SET google_id = ? WHERE id = ?", [$googleId, $benutzer['id']]);
    }
    
    $userId = $benutzer['id'];
    $rolle = $benutzer['rolle'];
    $benutzername = $benutzer['benutzername'];
    
} else {
    // Neuen Benutzer anlegen mit Standard-Rolle "mitglied"
    $benutzername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    if (empty($benutzername)) {
        $benutzername = 'user_' . substr($googleId, 0, 8);
    }
    
    // Eindeutigen Benutzernamen sicherstellen
    $baseName = $benutzername;
    $counter = 1;
    while ($db->fetchOne("SELECT id FROM benutzer WHERE benutzername = ?", [$benutzername])) {
        $benutzername = $baseName . $counter;
        $counter++;
    }
    
    // Standard-Rolle "mitglied" (rolle_id = 9)
    $standardRolleId = 9;
    $standardRolle = 'mitglied';
    
    // Benutzer erstellen (ohne Passwort - nur Google Login möglich)
    $db->execute(
        "INSERT INTO benutzer (benutzername, email, passwort_hash, rolle, rolle_id, google_id, aktiv, erstellt_am) 
         VALUES (?, ?, '', ?, ?, ?, 1, NOW())",
        [$benutzername, $email, $standardRolle, $standardRolleId, $googleId]
    );
    
    $userId = $db->lastInsertId();
    $rolle = $standardRolle;
    
    // Info-Nachricht für neuen Benutzer
    Session::setFlashMessage('info', 'Willkommen! Ihr Konto wurde erstellt. Ein Administrator wird Ihnen die entsprechende Rolle zuweisen.');
}

// Login durchführen
Session::set('user_id', $userId);
Session::set('username', $benutzername);
Session::set('rolle', $rolle);

// Letzten Login aktualisieren
$db->execute("UPDATE benutzer SET letzter_login = NOW() WHERE id = ?", [$userId]);

// Aktivitätslog
$db->execute(
    "INSERT INTO aktivitaetslog (benutzer_id, aktion, beschreibung, ip_adresse) VALUES (?, ?, ?, ?)",
    [$userId, 'login_google', 'Benutzer hat sich via Google angemeldet', $_SERVER['REMOTE_ADDR']]
);

header('Location: index.php');
exit;
