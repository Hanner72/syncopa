<?php
// cron/mail_spamfilter.php
// Prüft alle Postfächer mit eingeschaltetem Spamfilter auf neue Junk-E-Mail (auch wenn niemand das Postfach öffnet).
// Nur über die Kommandozeile, z. B. alle 5 Minuten als Webserver-Benutzer:
//   */5 * * * * www-data php /var/www/html/syncopa/cron/mail_spamfilter.php >/dev/null 2>&1
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Nur über die Kommandozeile.');
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

$ausgabe = in_array('-v', $argv, true);
foreach (Database::getInstance()->fetchAll("SELECT * FROM mail_konten WHERE spamfilter = 1 ORDER BY id") as $konto) {
    try {
        $imap = new ImapClient($konto['imap_host'], (int)$konto['imap_port'], $konto['imap_sicherheit']);
        $imap->anmelden($konto['benutzername'], MailKonto::passwort($konto));
        $n = MailSpamfilter::pruefen($konto, $imap);
        $imap->abmelden();
        if ($ausgabe) echo "{$konto['email']}: $n als Junk verschoben\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "{$konto['email']}: " . $e->getMessage() . "\n");
    }
}
