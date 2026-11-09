<?php
// freigabe.php – Öffentliche Downloadseite für große E-Mail-Anhänge (ohne Anmeldung, nur mit gültigem Schlüssel)
require_once 'config.php';
require_once 'includes.php';

$f = MailFreigabe::nachToken((string)($_GET['t'] ?? ''));
if ($f && !empty($_GET['laden'])) {
    MailFreigabe::ausliefern($f);
    exit;
}
$verein = Database::getInstance()->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'verein_name'")['wert'] ?? 'Syncopa';
if (!$f) http_response_code(404);
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo $f ? htmlspecialchars($f['dateiname']) : 'Link abgelaufen'; ?> – <?php echo htmlspecialchars($verein); ?></title>
<style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #eef2f7; font-family: "Segoe UI", Arial, sans-serif; color: #1d2733; padding: 16px; box-sizing: border-box; }
    .karte { background: #fff; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,.08); padding: 28px; max-width: 460px; width: 100%; text-align: center; }
    .verein { font-size: 13px; color: #5b6878; letter-spacing: .04em; text-transform: uppercase; }
    h1 { font-size: 20px; margin: 14px 0 6px; word-break: break-word; }
    .info { color: #5b6878; font-size: 14px; margin-bottom: 20px; }
    a.knopf { display: inline-block; background: #4471A3; color: #fff; text-decoration: none; padding: 12px 22px; border-radius: 8px; font-weight: 600; }
    a.knopf:hover { background: #355c87; }
    .symbol { font-size: 42px; }
</style>
</head>
<body>
<div class="karte">
    <div class="verein"><?php echo htmlspecialchars($verein); ?></div>
    <?php if ($f): ?>
    <div class="symbol">📄</div>
    <h1><?php echo htmlspecialchars($f['dateiname']); ?></h1>
    <div class="info"><?php echo MailFreigabe::groesseText((int)$f['groesse']); ?> · abrufbar bis <?php echo date('d.m.Y', strtotime($f['gueltig_bis'])); ?></div>
    <a class="knopf" href="freigabe.php?t=<?php echo htmlspecialchars($f['token']); ?>&amp;laden=1">Herunterladen</a>
    <?php else: ?>
    <div class="symbol">⌛</div>
    <h1>Dieser Link ist nicht mehr gültig</h1>
    <div class="info">Die Datei ist abgelaufen oder wurde entfernt. Bitte beim Absender nachfragen.</div>
    <?php endif; ?>
</div>
</body>
</html>
