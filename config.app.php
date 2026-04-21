<?php
/**
 * SYNCOPA – App-Konfiguration
 * Diese Datei wird bei Updates automatisch aktualisiert.
 * Umgebungsspezifische Einstellungen gehören in config.php.
 */

// ============================================================================
// ANWENDUNGS-KONSTANTEN
// ============================================================================
if (!defined('APP_VERSION')) define('APP_VERSION', '2.3.9');
if (!defined('APP_NAME'))    define('APP_NAME',    'Syncopa');
if (!defined('BASE_PATH'))   define('BASE_PATH', __DIR__);

// ============================================================================
// SESSION
// ============================================================================
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600); // 1 Stunde

// ============================================================================
// UPLOAD-VERZEICHNISSE
// ============================================================================
if (!defined('UPLOAD_DIR'))         define('UPLOAD_DIR',         BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');
if (!defined('NOTEN_DIR'))          define('NOTEN_DIR',          UPLOAD_DIR . DIRECTORY_SEPARATOR . 'noten');
if (!defined('FOTOS_DIR'))          define('FOTOS_DIR',          UPLOAD_DIR . DIRECTORY_SEPARATOR . 'fotos');
if (!defined('DOKUMENTE_DIR'))      define('DOKUMENTE_DIR',      UPLOAD_DIR . DIRECTORY_SEPARATOR . 'dokumente');
if (!defined('FEST_VERTRAEGE_DIR')) define('FEST_VERTRAEGE_DIR', UPLOAD_DIR . DIRECTORY_SEPARATOR . 'fest_vertraege');
if (!defined('MAX_UPLOAD_SIZE'))    define('MAX_UPLOAD_SIZE',    10485760); // 10 MB

// ============================================================================
// ZEITZONE
// ============================================================================
date_default_timezone_set('Europe/Vienna');

// ============================================================================
// DATUMSFORMAT-KONSTANTEN
// ============================================================================
if (!defined('DATE_FORMAT_FULL'))      define('DATE_FORMAT_FULL',      'full');
if (!defined('DATE_FORMAT_LONG'))      define('DATE_FORMAT_LONG',      'long');
if (!defined('DATE_FORMAT_MEDIUM'))    define('DATE_FORMAT_MEDIUM',    'medium');
if (!defined('DATE_FORMAT_MONTH'))     define('DATE_FORMAT_MONTH',     'month');
if (!defined('DATE_FORMAT_MONTHYEAR')) define('DATE_FORMAT_MONTHYEAR', 'monthyear');

/**
 * Deutsche Datumsformatierung (PHP 8.1+ kompatibel, ersetzt strftime())
 * @param DateTime|string|int $date
 * @param string $format 'full', 'long', 'medium', 'month', 'monthyear' oder IntlDateFormatter-Pattern
 */
if (!function_exists('format_date_german')) {
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
