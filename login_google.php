<?php
/**
 * Google OAuth Login - Redirect zu Google
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

// CSRF-Token generieren
$state = bin2hex(random_bytes(16));
Session::set('oauth_state', $state);

// Google OAuth URL zusammenbauen
$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account'
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

header('Location: ' . $authUrl);
exit;
