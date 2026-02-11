<?php
/**
 * SYNCOPA - Musikvereinsverwaltung
 * Konfigurationsdatei
 * 
 * @version 2.0.6
 */

// ============================================================================
// FEHLERBEHANDLUNG
// ============================================================================
// Für Produktion: error_reporting(0); ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================================================
// DATENBANK-KONFIGURATION
// ============================================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'mvpalfau_syncopa');
define('DB_USER', 'mvpalfau_syncopa');
define('DB_PASS', 'Da05nnerj12');
define('DB_CHARSET', 'utf8mb4');

// ============================================================================
// ANWENDUNGS-KONFIGURATION
// ============================================================================
define('APP_NAME', 'Syncopa');
define('APP_VERSION', '2.0.0');
define('BASE_URL', 'https://app.mv-palfau.at'); // Anpassen an Ihr Setup
define('BASE_PATH', __DIR__);

// ============================================================================
// SESSION-KONFIGURATION
// ============================================================================
define('SESSION_LIFETIME', 3600); // 1 Stunde

// ============================================================================
// UPLOAD-VERZEICHNISSE
// ============================================================================
define('UPLOAD_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('NOTEN_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'noten');
define('FOTOS_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'fotos');
define('DOKUMENTE_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'dokumente');

// Maximale Upload-Größe (in Bytes)
define('MAX_UPLOAD_SIZE', 10485760); // 10 MB

// ============================================================================
// ZEITZONE
// ============================================================================
date_default_timezone_set('Europe/Vienna');

// ============================================================================
// GOOGLE CALENDAR API (optional)
// ============================================================================
define('GOOGLE_CALENDAR_ENABLED', false);
define('GOOGLE_CALENDAR_API_KEY', '');
define('GOOGLE_CALENDAR_ID', '');

// ============================================================================
// GOOGLE OAUTH LOGIN (optional)
// ============================================================================
// Erstelle Client-ID unter: https://console.cloud.google.com/apis/credentials
// Redirect URI: https://deine-domain.at/syncopa/login_google_callback.php
define('GOOGLE_OAUTH_ENABLED', true);
define('GOOGLE_CLIENT_ID', '27789021745-q6933qnancq53lkkduc59d38sqmfasck.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', BASE_URL . '/login_google_callback.php');

// ============================================================================
// E-MAIL-KONFIGURATION (optional)
// ============================================================================
define('EMAIL_ENABLED', false);
define('EMAIL_SMTP_HOST', 'smtp.example.com');
define('EMAIL_SMTP_PORT', 587);
define('EMAIL_SMTP_USER', '');
define('EMAIL_SMTP_PASS', '');
define('EMAIL_FROM', 'noreply@musikverein.local');
define('EMAIL_FROM_NAME', 'Musikverein Verwaltung');

// ============================================================================
// DATUMSFORMAT-KONSTANTEN
// ============================================================================
define('DATE_FORMAT_FULL', 'full');        // Mittwoch, 24. Dezember 2025
define('DATE_FORMAT_LONG', 'long');        // 24. Dezember 2025
define('DATE_FORMAT_MEDIUM', 'medium');    // 24.12.2025
define('DATE_FORMAT_MONTH', 'month');      // Dezember
define('DATE_FORMAT_MONTHYEAR', 'monthyear'); // Dezember 2025

/**
 * Helper-Funktion für deutsche Datumsformatierung (PHP 8.1+ kompatibel)
 * Ersetzt das veraltete strftime()
 * 
 * @param mixed $date DateTime, string oder timestamp
 * @param string $format 'full', 'long', 'medium', 'month', 'monthyear' oder custom pattern
 * @return string Formatiertes Datum
 */
function format_date_german($date, $format = DATE_FORMAT_FULL) {
    if (is_string($date)) {
        $date = new DateTime($date);
    } elseif (is_int($date)) {
        $date = (new DateTime())->setTimestamp($date);
    }
    
    $formatter = new IntlDateFormatter(
        'de_DE',
        IntlDateFormatter::FULL,
        IntlDateFormatter::NONE
    );
    
    switch ($format) {
        case DATE_FORMAT_FULL:
            $formatter->setPattern('EEEE, dd. MMMM yyyy');
            break;
        case DATE_FORMAT_LONG:
            $formatter->setPattern('dd. MMMM yyyy');
            break;
        case DATE_FORMAT_MEDIUM:
            $formatter->setPattern('dd.MM.yyyy');
            break;
        case DATE_FORMAT_MONTH:
            $formatter->setPattern('MMMM');
            break;
        case DATE_FORMAT_MONTHYEAR:
            $formatter->setPattern('MMMM yyyy');
            break;
        default:
            $formatter->setPattern($format);
    }
    
    return $formatter->format($date);
}

// ============================================================================
// AUTOLOADER
// ============================================================================
spl_autoload_register(function ($class) {
    $file = __DIR__ . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ============================================================================
// UPLOAD-VERZEICHNISSE ERSTELLEN
// ============================================================================
$directories = [UPLOAD_DIR, NOTEN_DIR, FOTOS_DIR, DOKUMENTE_DIR];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}
