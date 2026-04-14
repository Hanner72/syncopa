<?php
/**
 * SYNCOPA – Konfigurationsvorlage
 * Kopiere diese Datei als config.php und trage deine Werte ein.
 * config.php wird bei Updates NICHT überschrieben.
 */

// ============================================================================
// DATENBANK
// ============================================================================
define('DB_HOST',    'localhost');
define('DB_NAME',    'syncopa');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================================
// ANWENDUNGS-URL & VERSION
// ============================================================================
define('APP_VERSION', '2.2.2');
define('BASE_URL',    'https://deine-domain.at'); // Ohne abschließenden Slash

// ============================================================================
// GOOGLE OAUTH LOGIN (optional – auf false setzen zum Deaktivieren)
// Anleitung: https://console.cloud.google.com/apis/credentials
// Redirect URI: https://deine-domain.at/login_google_callback.php
// ============================================================================
define('GOOGLE_OAUTH_ENABLED',  false);
define('GOOGLE_CLIENT_ID',      '');
define('GOOGLE_CLIENT_SECRET',  '');
define('GOOGLE_REDIRECT_URI',   BASE_URL . '/login_google_callback.php');

// ============================================================================
// GOOGLE CALENDAR API (optional)
// ============================================================================
define('GOOGLE_CALENDAR_ENABLED', false);
define('GOOGLE_CALENDAR_API_KEY', '');
define('GOOGLE_CALENDAR_ID',      '');

// ============================================================================
// OCR.SPACE API (optional)
// Kostenloser Key: https://ocr.space/ocrapi
// ============================================================================
define('OCR_SPACE_API_KEY', '');

// ============================================================================
// E-MAIL / SMTP (optional)
// ============================================================================
define('EMAIL_ENABLED',    false);
define('EMAIL_SMTP_HOST',  'smtp.example.com');
define('EMAIL_SMTP_PORT',  587);
define('EMAIL_SMTP_USER',  '');
define('EMAIL_SMTP_PASS',  '');
define('EMAIL_FROM',       'noreply@deine-domain.at');
define('EMAIL_FROM_NAME',  'Musikverein');

// ============================================================================
// App-Konfiguration laden (nicht editieren)
// ============================================================================
require_once __DIR__ . '/config.app.php';
