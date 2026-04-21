<?php
/**
 * SYNCOPA – App-Konfiguration
 * Diese Datei wird bei Updates automatisch aktualisiert.
 * Umgebungsspezifische Einstellungen gehören in config.php.
 */

// ============================================================================
// ANWENDUNGS-KONSTANTEN
// ============================================================================
if (!defined('APP_VERSION')) define('APP_VERSION', '2.3.7');
define('APP_NAME',    'Syncopa');
define('BASE_PATH', __DIR__);

// ============================================================================
// SESSION
// ============================================================================
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600); // 1 Stunde

// ============================================================================
// UPLOAD-VERZEICHNISSE
// ============================================================================
define('UPLOAD_DIR',         BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('NOTEN_DIR',          UPLOAD_DIR . DIRECTORY_SEPARATOR . 'noten');
define('FOTOS_DIR',          UPLOAD_DIR . DIRECTORY_SEPARATOR . 'fotos');
define('DOKUMENTE_DIR',      UPLOAD_DIR . DIRECTORY_SEPARATOR . 'dokumente');
define('FEST_VERTRAEGE_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'fest_vertraege');
define('MAX_UPLOAD_SIZE',    10485760); // 10 MB

// ============================================================================
// ZEITZONE
// ============================================================================
date_default_timezone_set('Europe/Vienna');

// ============================================================================
// DATUMSFORMAT-KONSTANTEN
// ============================================================================
define('DATE_FORMAT_FULL',      'full');       // Mittwoch, 24. Dezember 2025
define('DATE_FORMAT_LONG',      'long');       // 24. Dezember 2025
define('DATE_FORMAT_MEDIUM',    'medium');     // 24.12.2025
define('DATE_FORMAT_MONTH',     'month');      // Dezember
define('DATE_FORMAT_MONTHYEAR', 'monthyear'); // Dezember 2025

/**
 * Deutsche Datumsformatierung (PHP 8.1+ kompatibel, ersetzt strftime())
 * @param DateTime|string|int $date
 * @param string $format 'full', 'long', 'medium', 'month', 'monthyear' oder IntlDateFormatter-Pattern
 */
function format_date_german($date, string $format = DATE_FORMAT_FULL): string {
    if (is_string($date))   $date = new DateTime($date);
    elseif (is_int($date))  $date = (new DateTime())->setTimestamp($date);

    $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
    $patterns = [
        DATE_FORMAT_FULL      => 'EEEE, dd. MMMM yyyy',
        DATE_FORMAT_LONG      => 'dd. MMMM yyyy',
        DATE_FORMAT_MEDIUM    => 'dd.MM.yyyy',
        DATE_FORMAT_MONTH     => 'MMMM',
        DATE_FORMAT_MONTHYEAR => 'MMMM yyyy',
    ];
    $formatter->setPattern($patterns[$format] ?? $format);
    return $formatter->format($date);
}

// ============================================================================
// AUTOLOADER
// ============================================================================
spl_autoload_register(function (string $class): void {
    $file = BASE_PATH . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR
          . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    if (file_exists($file)) require_once $file;
});

// ============================================================================
// UPLOAD-VERZEICHNISSE ERSTELLEN (beim ersten Aufruf)
// ============================================================================
foreach ([UPLOAD_DIR, NOTEN_DIR, FOTOS_DIR, DOKUMENTE_DIR, FEST_VERTRAEGE_DIR] as $dir) {
    if (!file_exists($dir)) @mkdir($dir, 0755, true);
}
