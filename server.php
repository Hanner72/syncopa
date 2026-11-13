<?php
// server.php – Sicherheits-Checkliste für den Server (nur Administratoren)
// Prüft, was sich prüfen lässt, automatisch: auf dem Server (PHP, Dateien, Zugänge) und im Browser des
// Administrators (ob gesperrte Dateien von außen wirklich gesperrt sind). Punkte, die sich nicht prüfen
// lassen (Datensicherung, zweiter Faktor …), bestätigt ein Administrator von Hand.
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin()) {
    Session::setFlashMessage('danger', 'Diese Seite ist nur für Administratoren.');
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

// Manuelle Punkte bestätigen / zurücksetzen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schluessel = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['punkt'] ?? ''));
    if ($schluessel !== '') {
        if (($_POST['aktion'] ?? '') === 'bestaetigen') {
            $wert = json_encode(['datum' => date('Y-m-d H:i'), 'benutzer' => Session::getUsername()]);
            $db->execute("INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?) ON DUPLICATE KEY UPDATE wert = VALUES(wert)", ['server_ok_' . $schluessel, $wert]);
            Session::setFlashMessage('success', 'Als erledigt bestätigt.');
        } else {
            $db->execute("DELETE FROM einstellungen WHERE schluessel = ?", ['server_ok_' . $schluessel]);
            Session::setFlashMessage('success', 'Bestätigung zurückgenommen.');
        }
    }
    header('Location: server.php#' . $schluessel);
    exit;
}

$bestaetigt = [];
foreach ($db->fetchAll("SELECT schluessel, wert FROM einstellungen WHERE schluessel LIKE 'server\\_ok\\_%'") as $r) {
    $bestaetigt[substr($r['schluessel'], 10)] = json_decode((string)$r['wert'], true) ?: [];
}

$host      = (string)($_SERVER['HTTP_HOST'] ?? '');
$hostOhnePort = preg_replace('/:\d+$/', '', $host);
$lokal     = (bool)preg_match('/^(localhost|127\.0\.0\.1|\[::1\]|\d+\.\d+\.\d+\.\d+)$/', $hostOhnePort) || preg_match('/\.(test|local|localhost)$/i', $hostOhnePort);
$https     = Sicherheit::istHttps();
$appPfad   = rtrim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/');

// --- Weiterleitung http → https (vom Server aus einmal aufrufen)
$umleitung = 'unbekannt';
if ($lokal) {
    $umleitung = 'lokal';
} elseif (function_exists('curl_init')) {
    $ch = curl_init('http://' . $host . $appPfad . '/login.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_USERAGENT => 'syncopa-servercheck']);
    $antwort = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($antwort !== false && $code > 0) {
        $umleitung = (in_array($code, [301, 302, 307, 308], true) && preg_match('/^location:\s*https:\/\//mi', (string)$antwort)) ? 'ja' : 'nein';
    }
}

// --- Syncopa-Version (gleiche Quelle wie die Update-Anzeige, höchstens alle 6 Stunden)
$neuesteVersion = null;
$vCache = $db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'server_neueste_version'");
$vDaten = $vCache ? (json_decode((string)$vCache['wert'], true) ?: []) : [];
if (!empty($vDaten['zeit']) && time() - (int)$vDaten['zeit'] < 21600) {
    $neuesteVersion = $vDaten['version'] ?? null;
} elseif (function_exists('curl_init')) {
    $ch = curl_init('https://raw.githubusercontent.com/Hanner72/syncopa/main/docs/changelog.md');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_USERAGENT => 'syncopa-updater/1.0', CURLOPT_FOLLOWLOCATION => true]);
    $body = curl_exec($ch);
    if ($body && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && preg_match('/##\s*\[([0-9]+\.[0-9]+\.[0-9]+)\]/', $body, $m)) {
        $neuesteVersion = $m[1];
        $db->execute("INSERT INTO einstellungen (schluessel, wert) VALUES ('server_neueste_version', ?) ON DUPLICATE KEY UPDATE wert = VALUES(wert)",
            [json_encode(['version' => $neuesteVersion, 'zeit' => time()])]);
    }
    curl_close($ch);
}

// --- Weitere Werte
$phpVersion   = PHP_VERSION;
$phpStatus    = version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : (version_compare(PHP_VERSION, '8.2.0', '>=') ? 'warnung' : 'fehler');
$fehlerAnzeige = filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN) || ini_get('display_errors') === 'stdout';
$rechte = function (string $datei): ?int { return is_file($datei) ? (fileperms($datei) & 0777) : null; };
$configRechte = $rechte(__DIR__ . '/config.php');
$keyRechte    = $rechte(__DIR__ . '/config.mailkey.php');
$standardKonten = Sicherheit::kontenMitStandardpasswort();
$admins = $db->fetchAll(
    "SELECT DISTINCT b.benutzername, b.letzter_login FROM benutzer b
     JOIN benutzer_rollen br ON br.benutzer_id = b.id JOIN rollen r ON r.id = br.rolle_id
     WHERE b.aktiv = 1 AND r.ist_admin = 1 ORDER BY b.benutzername"
);
$alteAdmins = array_filter($admins, fn($a) => !$a['letzter_login'] || strtotime($a['letzter_login']) < strtotime('-12 months'));
$fehlversuche = (int)($db->fetchOne("SELECT COUNT(*) AS n FROM login_versuche WHERE erfolg = 0 AND zeit > NOW() - INTERVAL 24 HOUR")['n'] ?? 0);
$bmvPw = (string)($db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'bmv_passwort'")['wert'] ?? '');
$installGesperrt = is_file(__DIR__ . '/install.lock');
$htaccessDa = is_file(__DIR__ . '/.htaccess');

$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
$oktal = fn(?int $r) => $r === null ? '–' : sprintf('%o', $r);

/*
 * Punkte: schluessel, gruppe, titel, status (ok|warnung|fehler|info|manuell|browser), befund, anleitung (HTML)
 * „browser“: wird im Browser geprüft (JavaScript unten), „manuell“: vom Administrator zu bestätigen.
 */
$anleitungHtaccess = '<p>Syncopa bringt im Hauptordner eine <code>.htaccess</code> mit, die diese Dateien sperrt. Sie wirkt nur, wenn der Webserver <code>.htaccess</code>-Dateien beachtet.</p>
<ul>
<li><strong>netcup / Plesk / LiteSpeed:</strong> beachtet <code>.htaccess</code> von sich aus. Prüfen, ob die Datei <code>.htaccess</code> im Syncopa-Ordner liegt (Plesk → Dateimanager; versteckte Dateien anzeigen). Fehlt sie, das letzte Update erneut einspielen.</li>
<li><strong>Eigener Apache-Server:</strong> Datei <code>/etc/apache2/conf-available/syncopa.conf</code> anlegen mit<br><code>&lt;Directory ' . $h(__DIR__) . '&gt; AllowOverride All &lt;/Directory&gt;</code><br>dann <code>sudo a2enconf syncopa</code>, <code>sudo a2enmod rewrite</code> und <code>sudo systemctl reload apache2</code>.</li>
<li><strong>Nginx:</strong> beachtet keine <code>.htaccess</code>. Die Sperren als <code>location</code>-Regeln in die Server-Konfiguration übernehmen (siehe Dokumentation, Installation 3.10).</li>
</ul>';

$punkte = [
    // ---------------------------------------------------------------- Verbindung
    ['https', 'Verbindung', 'Verschlüsselte Verbindung (https)',
        $https ? 'ok' : ($lokal ? 'info' : 'fehler'),
        $https ? 'Diese Seite ist über https aufgerufen.' : ($lokal ? 'Lokale Adresse ohne https – für den Betrieb im Vereinsnetz in Ordnung, nicht für das Internet.' : 'Diese Seite ist <strong>unverschlüsselt</strong> aufgerufen. Passwörter könnten mitgelesen werden.'),
        '<ul><li><strong>netcup / Plesk:</strong> Websites &amp; Domains → <em>SSL/TLS-Zertifikate</em> → kostenloses Zertifikat von <em>Let\'s Encrypt</em> installieren.</li>
         <li><strong>Cloudflare:</strong> SSL/TLS → Modus <em>Full (strict)</em>.</li>
         <li><strong>Eigener Server:</strong> <code>sudo certbot --apache -d ' . $h($hostOhnePort) . '</code></li></ul>'],
    ['umleitung', 'Verbindung', 'Unverschlüsselte Aufrufe werden auf https umgeleitet',
        $umleitung === 'ja' ? 'ok' : ($umleitung === 'lokal' ? 'info' : ($umleitung === 'nein' ? 'fehler' : 'browser')),
        $umleitung === 'ja' ? '<code>http://' . $h($host) . '</code> leitet auf https um.'
            : ($umleitung === 'lokal' ? 'Lokale Adresse – keine Umleitung nötig.'
            : ($umleitung === 'nein' ? '<code>http://' . $h($host) . '</code> ist ohne Umleitung erreichbar.' : 'Vom Server aus nicht prüfbar – bitte selbst <code>http://' . $h($host . $appPfad) . '/login.php</code> öffnen: Die Adresse muss auf https wechseln.')),
        '<p>Die mitgelieferte <code>.htaccess</code> leitet automatisch um. Zusätzlich möglich:</p><ul><li><strong>netcup / Plesk:</strong> Hosting-Einstellungen → <em>Permanente SEO-sichere 301-Weiterleitung von HTTP zu HTTPS</em>.</li><li><strong>Cloudflare:</strong> SSL/TLS → Edge-Zertifikate → <em>Always Use HTTPS</em>.</li></ul>'],
    ['kopfzeilen', 'Verbindung', 'Schutz-Kopfzeilen werden gesendet', 'browser',
        'Wird im Browser geprüft …',
        '<p>Syncopa sendet diese Kopfzeilen selbst (u. a. Schutz vor Einbetten in fremde Seiten). Fehlen sie, entfernt sie ein vorgeschalteter Dienst – in Cloudflare unter <em>Rules → Transform Rules</em> nachsehen.</p>'],
    ['cookie', 'Verbindung', 'Anmelde-Cookie nur über verschlüsselte Verbindung',
        $https ? (ini_get('session.cookie_secure') ? 'ok' : 'warnung') : ($lokal ? 'info' : 'fehler'),
        $https ? (ini_get('session.cookie_secure') ? 'Das Cookie ist als „nur verschlüsselt“ und „SameSite“ markiert.' : 'Das Cookie ist nicht als „nur verschlüsselt“ markiert.') : 'Ohne https kann das Cookie nicht geschützt werden.',
        '<p>Syncopa setzt das automatisch, sobald die Seite über https läuft. Zuerst die Punkte darüber erledigen.</p>'],

    // ---------------------------------------------------------------- Webserver
    ['dateien', 'Webserver', 'Interne Dateien von außen gesperrt', 'browser',
        'Wird im Browser geprüft …', $anleitungHtaccess],
    ['uploads', 'Webserver', 'Belege, Jahresberichte und Freigaben nur mit Anmeldung', 'browser',
        'Wird im Browser geprüft …', $anleitungHtaccess],
    ['ordnerliste', 'Webserver', 'Keine Ordnerlisten', 'browser',
        'Wird im Browser geprüft …', $anleitungHtaccess],
    ['htaccess', 'Webserver', 'Schutzdatei .htaccess vorhanden',
        $htaccessDa ? 'ok' : 'fehler',
        $htaccessDa ? 'Die Datei <code>.htaccess</code> liegt im Syncopa-Ordner.' : 'Die Datei <code>.htaccess</code> fehlt im Syncopa-Ordner.',
        '<p>Die Datei kommt mit jedem Update. Fehlt sie, das aktuelle Update erneut einspielen oder die Datei aus dem Download-Paket hochladen. Beim Hochladen per FTP darauf achten, dass versteckte Dateien (Punkt am Anfang) mitkopiert werden.</p>'],

    // ---------------------------------------------------------------- PHP & Dateien
    ['php', 'PHP & Dateien', 'Aktuelle PHP-Version', $phpStatus,
        'PHP ' . $h($phpVersion) . ($phpStatus === 'ok' ? ' wird noch mit Sicherheits-Updates versorgt.' : ($phpStatus === 'warnung' ? ' – die Versorgung mit Sicherheits-Updates endet bald.' : ' bekommt keine Sicherheits-Updates mehr.')),
        '<ul><li><strong>netcup / Plesk:</strong> Websites &amp; Domains → <em>PHP</em> → neueste angebotene Version (mind. 8.3) wählen.</li><li><strong>Eigener Server:</strong> Paket <code>php8.3</code> (oder neuer) installieren und im Webserver aktivieren.</li></ul>'],
    ['fehleranzeige', 'PHP & Dateien', 'Technische Fehlermeldungen ausgeblendet',
        $fehlerAnzeige ? 'warnung' : 'ok',
        $fehlerAnzeige ? 'PHP zeigt Fehlermeldungen im Browser an (<code>display_errors</code> ist an). Das verrät Angreifern Pfade und Aufbau.' : 'Fehlermeldungen werden nur ins Protokoll geschrieben.',
        '<ul><li><strong>netcup / Plesk:</strong> PHP-Einstellungen → <code>display_errors</code> auf <em>off</em>.</li><li><strong>Eigener Server:</strong> in der <code>php.ini</code> <code>display_errors = Off</code> setzen.</li></ul>'],
    ['uploadphp', 'PHP & Dateien', 'Kein Programmcode im Upload-Ordner ausführbar', 'browser',
        'Wird im Browser geprüft …', $anleitungHtaccess],
    ['konfig', 'PHP & Dateien', 'Zugangsdaten-Datei nur für Syncopa lesbar',
        $configRechte === null ? 'fehler' : (($configRechte & 0004) ? 'warnung' : 'ok'),
        'Rechte von <code>config.php</code>: <code>' . $oktal($configRechte) . '</code>' . ($keyRechte !== null ? ', <code>config.mailkey.php</code>: <code>' . $oktal($keyRechte) . '</code>' : '') . (($configRechte ?? 0) & 0004 ? ' – auch für andere Benutzer am Server lesbar.' : '.'),
        '<ul><li><strong>netcup / Plesk:</strong> Dateimanager → <code>config.php</code> → <em>Berechtigungen ändern</em>: Lesen/Schreiben nur für den Besitzer, Lesen für die Gruppe, nichts für andere (<code>640</code>). Gleiches für <code>config.mailkey.php</code>.</li><li><strong>Eigener Server:</strong> <code>chmod 640 config.php config.mailkey.php</code></li></ul>'],
    ['installer', 'PHP & Dateien', 'Installations-Assistent gesperrt',
        $installGesperrt ? 'ok' : 'fehler',
        $installGesperrt ? 'Die Sperrdatei <code>install.lock</code> ist vorhanden.' : 'Die Sperrdatei <code>install.lock</code> fehlt – jeder könnte den Installations-Assistenten erneut aufrufen.',
        '<p>Eine leere Datei <code>install.lock</code> im Syncopa-Ordner anlegen (Plesk → Dateimanager → Neue Datei).</p>'],

    // ---------------------------------------------------------------- Zugänge
    ['standardpw', 'Zugänge', 'Keine Zugänge mit Standardpasswort',
        $standardKonten ? 'fehler' : 'ok',
        $standardKonten ? 'Diese aktiven Zugänge haben ein bekanntes Standardpasswort: <strong>' . $h(implode(', ', $standardKonten)) . '</strong>' : 'Kein aktiver Zugang verwendet ein bekanntes Standardpasswort.',
        '<p>Unter <a href="benutzer.php">System → Benutzer</a> beim betroffenen Zugang ein neues, langes Passwort setzen oder den Zugang deaktivieren.</p>'],
    ['admins', 'Zugänge', 'Administratoren im Überblick',
        $alteAdmins ? 'warnung' : 'ok',
        count($admins) . ' aktive' . (count($admins) === 1 ? 'r Administrator' : ' Administratoren') . ': ' . $h(implode(', ', array_column($admins, 'benutzername'))) . '.'
            . ($alteAdmins ? ' Seit über einem Jahr nicht angemeldet: <strong>' . $h(implode(', ', array_column($alteAdmins, 'benutzername'))) . '</strong>.' : ''),
        '<p>So wenige Administratoren wie nötig. Zugänge von ausgeschiedenen Funktionären unter <a href="benutzer.php">System → Benutzer</a> deaktivieren.</p>'],
    ['loginsperre', 'Zugänge', 'Sperre nach Fehlversuchen aktiv', 'ok',
        'Nach ' . Sicherheit::MAX_FEHLVERSUCHE_BENUTZER . ' falschen Passwörtern wird ein Zugang für ' . Sicherheit::SPERRE_MINUTEN . ' Minuten gesperrt. Fehlversuche in den letzten 24 Stunden: <strong>' . $fehlversuche . '</strong>.',
        '<p>Läuft automatisch. Viele Fehlversuche deuten auf Passwort-Raten hin – dann die Passwörter der betroffenen Zugänge ändern.</p>'],
    ['bmvpw', 'Zugänge', 'BMV-Passwort verschlüsselt gespeichert',
        ($bmvPw === '' || str_starts_with($bmvPw, 'enc:')) ? 'ok' : 'warnung',
        $bmvPw === '' ? 'Kein BMV-Passwort hinterlegt.' : (str_starts_with($bmvPw, 'enc:') ? 'Das Passwort für den BMV-Zugang ist verschlüsselt.' : 'Das BMV-Passwort liegt noch im Klartext in der Datenbank.'),
        '<p>Unter <a href="einstellungen.php">Einstellungen → BMV</a> das Passwort einmal neu eingeben und speichern.</p>'],

    // ---------------------------------------------------------------- Syncopa
    ['version', 'Syncopa', 'Syncopa ist aktuell',
        $neuesteVersion === null ? 'info' : (version_compare(APP_VERSION, $neuesteVersion, '>=') ? 'ok' : 'warnung'),
        'Installiert: <strong>' . $h(APP_VERSION) . '</strong>' . ($neuesteVersion ? ', neueste: <strong>' . $h($neuesteVersion) . '</strong>.' : ' – die neueste Version konnte nicht abgefragt werden.'),
        '<p>Updates bringen auch Sicherheitskorrekturen. Unter <a href="update.php">System-Update</a> einspielen.</p>'],

    // ---------------------------------------------------------------- Organisatorisch (manuell)
    ['sicherung', 'Organisatorisch', 'Regelmäßige Datensicherung', 'manuell',
        'Lässt sich nicht automatisch prüfen.',
        '<ul><li><strong>netcup / Plesk:</strong> Websites &amp; Domains → <em>Sichern &amp; Wiederherstellen</em> → <em>Planungseinstellungen</em>: tägliche Sicherung von Dateien <em>und</em> Datenbank einschalten.</li>
         <li>Zusätzlich mindestens monatlich eine Sicherung herunterladen und außerhalb des Servers aufbewahren (z. B. verschlüsselter USB-Stick beim Kassier).</li>
         <li>Einmal im Jahr eine Wiederherstellung ausprobieren.</li>
         <li>Unbedingt mitsichern: <code>config.php</code> und <code>config.mailkey.php</code> – ohne den Schlüssel lassen sich die gespeicherten Postfach- und BMV-Passwörter nicht mehr lesen.</li></ul>'],
    ['zweifaktor', 'Organisatorisch', 'Zweiter Faktor für Hosting, Cloudflare und GitHub', 'manuell',
        'Lässt sich nicht automatisch prüfen.',
        '<p>Wer das Hosting-, Cloudflare- oder GitHub-Konto übernimmt, kann Syncopa verändern. Überall die Anmeldung mit zweitem Faktor (Code-App) einschalten:</p><ul><li><strong>netcup:</strong> Customer Control Panel → Stammdaten → <em>Zwei-Faktor-Authentifizierung</em>.</li><li><strong>Cloudflare:</strong> My Profile → Authentication → <em>Two-Factor Authentication</em>.</li><li><strong>GitHub:</strong> Settings → Password and authentication → <em>Two-factor authentication</em>.</li></ul>'],
    ['passwoerter', 'Organisatorisch', 'Starke, persönliche Passwörter', 'manuell',
        'Lässt sich nicht automatisch prüfen.',
        '<ul><li>Jeder Funktionär hat einen eigenen Zugang – keine geteilten Zugänge.</li><li>Passwörter mit mindestens 12 Zeichen, nirgends sonst verwendet; am besten mit einem Passwort-Manager.</li><li>Bei Funktionswechsel den Zugang sofort anpassen oder deaktivieren.</li></ul>'],
];

$farben = ['ok' => 'success', 'warnung' => 'warning', 'fehler' => 'danger', 'info' => 'info', 'manuell' => 'secondary', 'browser' => 'secondary'];
$texte  = ['ok' => 'Erledigt', 'warnung' => 'Prüfen', 'fehler' => 'Unsicher', 'info' => 'Hinweis', 'manuell' => 'Offen', 'browser' => 'Wird geprüft'];
foreach ($punkte as &$p) {
    if ($p[3] === 'manuell' && isset($bestaetigt[$p[0]])) $p[3] = 'ok';
}
unset($p);

include 'includes/header.php';
?>
<style>
.srv-zusammenfassung { display: flex; flex-wrap: wrap; gap: 10px; }
.srv-zahl { flex: 1 1 130px; border-radius: 6px; padding: 10px 14px; background: var(--bg-card); border-left: 4px solid var(--border); }
.srv-zahl b { display: block; font-size: 22px; line-height: 1.2; font-variant-numeric: tabular-nums; }
.srv-zahl span { font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); }
.srv-zahl.ok { border-left-color: var(--c-success); } .srv-zahl.fehler { border-left-color: var(--c-danger); }
.srv-zahl.warnung { border-left-color: var(--c-warning); } .srv-zahl.offen { border-left-color: var(--text-muted); }
.srv-punkt { border-left: 4px solid var(--border); padding: 12px 16px; border-bottom: 1px solid var(--border-light); }
.srv-punkt:last-child { border-bottom: 0; }
.srv-punkt[data-status="ok"] { border-left-color: var(--c-success); }
.srv-punkt[data-status="fehler"] { border-left-color: var(--c-danger); }
.srv-punkt[data-status="warnung"] { border-left-color: var(--c-warning); }
.srv-punkt[data-status="info"] { border-left-color: var(--c-info); }
.srv-kopf { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
.srv-kopf h6 { margin: 0; font-size: 14px; font-weight: 600; flex: 1 1 260px; }
.srv-befund { margin: 4px 0 0; font-size: 13px; color: var(--text-secondary); }
.srv-punkt details { margin-top: 6px; font-size: 13px; }
.srv-punkt summary { cursor: pointer; color: var(--c-primary); font-weight: 500; }
.srv-punkt details > div { margin-top: 8px; padding: 10px 12px; background: var(--bg-body); border-radius: 4px; }
.srv-punkt details ul { margin: 0; padding-left: 1.2em; } .srv-punkt details li + li { margin-top: 4px; }
.srv-punkt details p:last-child { margin-bottom: 0; }
.srv-punkt code { font-size: 12px; }
</style>

<div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="page-title mb-0"><i class="bi bi-hdd-network"></i> Server</h1>
    <a href="server.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> Erneut prüfen</a>
</div>

<p class="text-muted mb-3" style="max-width:820px">Was der Server braucht, damit Syncopa sicher läuft. Grüne Punkte sind erledigt, rote müssen behoben werden. Unter <em>So geht’s</em> steht jeweils, wie es bei netcup/Plesk und auf einem eigenen Server gemacht wird. Punkte, die sich nicht automatisch prüfen lassen, bestätigst du nach dem Erledigen von Hand.</p>

<div class="srv-zusammenfassung mb-4" id="srvZusammenfassung">
    <div class="srv-zahl ok"><b data-zaehler="ok">–</b><span>Erledigt</span></div>
    <div class="srv-zahl fehler"><b data-zaehler="fehler">–</b><span>Unsicher</span></div>
    <div class="srv-zahl warnung"><b data-zaehler="warnung">–</b><span>Prüfen</span></div>
    <div class="srv-zahl offen"><b data-zaehler="offen">–</b><span>Offen</span></div>
</div>

<?php
$gruppen = [];
foreach ($punkte as $p) $gruppen[$p[1]][] = $p;
foreach ($gruppen as $gruppe => $liste): ?>
<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0"><?php echo $h($gruppe); ?></h5></div>
    <div class="card-body p-0">
        <?php foreach ($liste as [$schl, , $titel, $status, $befund, $anleitung]):
            $manuell = in_array($schl, ['sicherung', 'zweifaktor', 'passwoerter'], true); ?>
        <div class="srv-punkt" id="<?php echo $h($schl); ?>" data-punkt="<?php echo $h($schl); ?>" data-status="<?php echo $h($status); ?>">
            <div class="srv-kopf">
                <h6><?php echo $h($titel); ?></h6>
                <span class="badge bg-<?php echo $farben[$status]; ?> srv-badge"><?php echo $texte[$status]; ?></span>
            </div>
            <p class="srv-befund"><?php
                echo $befund; // fest im Programm geschriebener Text, Werte darin sind maskiert
                if ($manuell && isset($bestaetigt[$schl])) {
                    echo ' Bestätigt am ' . $h(date('d.m.Y', strtotime($bestaetigt[$schl]['datum'] ?? 'now'))) . ' von ' . $h($bestaetigt[$schl]['benutzer'] ?? '?') . '.';
                }
            ?></p>
            <details<?php echo in_array($status, ['fehler', 'warnung'], true) ? ' open' : ''; ?>>
                <summary>So geht’s</summary>
                <div><?php echo $anleitung; ?>
                    <?php if ($manuell): ?>
                    <form method="post" class="mt-2">
                        <input type="hidden" name="punkt" value="<?php echo $h($schl); ?>">
                        <?php if (isset($bestaetigt[$schl])): ?>
                        <button class="btn btn-sm btn-outline-secondary" name="aktion" value="zuruecknehmen"><i class="bi bi-arrow-counterclockwise"></i> Bestätigung zurücknehmen</button>
                        <?php else: ?>
                        <button class="btn btn-sm btn-success" name="aktion" value="bestaetigen"><i class="bi bi-check2"></i> Als erledigt bestätigen</button>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </div>
            </details>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<p class="text-muted small">Die Punkte „im Browser geprüft“ ruft dein Browser genauso auf wie ein Außenstehender – das Ergebnis zeigt also, was von außen erreichbar ist.</p>

<?php include 'includes/footer.php'; ?>
<script>
(function () {
    var farben = { ok: 'success', warnung: 'warning', fehler: 'danger', info: 'info', manuell: 'secondary', browser: 'secondary' };
    var texte  = { ok: 'Erledigt', warnung: 'Prüfen', fehler: 'Unsicher', info: 'Hinweis', manuell: 'Offen', browser: 'Wird geprüft' };
    function setzen(schl, status, befund) {
        var el = document.querySelector('[data-punkt="' + schl + '"]');
        if (!el) return;
        el.dataset.status = status;
        var b = el.querySelector('.srv-badge');
        b.className = 'badge bg-' + farben[status] + ' srv-badge';
        b.textContent = texte[status];
        el.querySelector('.srv-befund').innerHTML = befund;
        if (status === 'fehler' || status === 'warnung') el.querySelector('details').open = true;
        zaehlen();
    }
    function zaehlen() {
        var z = { ok: 0, fehler: 0, warnung: 0, offen: 0 };
        document.querySelectorAll('.srv-punkt').forEach(function (p) {
            var s = p.dataset.status;
            if (s === 'ok' || s === 'info') z.ok++; else if (s === 'fehler') z.fehler++; else if (s === 'warnung') z.warnung++; else z.offen++;
        });
        Object.keys(z).forEach(function (k) { document.querySelector('[data-zaehler="' + k + '"]').textContent = z[k]; });
    }
    function abruf(pfad) {
        return fetch(pfad + (pfad.indexOf('?') < 0 ? '?' : '&') + 'pruefung=' + Date.now(), { cache: 'no-store', credentials: 'omit', redirect: 'manual' })
            .then(function (r) { return r.text().then(function (t) { return { status: r.status, typ: r.type, text: t, headers: r.headers }; }); })
            .catch(function () { return { status: 0, text: '' }; });
    }
    var offen = function (r) { return r.status === 200; };
    zaehlen();

    // Interne Dateien
    var interne = ['database.sql', 'version_sync.php', 'classes/Session.php', 'includes/header.php', 'config.example.php', '.githooks/pre-commit'];
    Promise.all(interne.map(abruf)).then(function (ergebnisse) {
        var frei = interne.filter(function (p, i) { return offen(ergebnisse[i]); });
        if (frei.length) setzen('dateien', 'fehler', 'Von außen abrufbar: <code>' + frei.join('</code>, <code>') + '</code>');
        else setzen('dateien', 'ok', 'Geprüft: ' + interne.length + ' interne Dateien sind von außen gesperrt.');
    });
    // Geschützte Upload-Ordner (Testpfad – die Datei muss nicht existieren, der Ordner muss gesperrt sein)
    var geschuetzt = ['uploads/belege/syncopa-pruefung.pdf', 'uploads/bmv_jahresberichte/syncopa-pruefung.pdf', 'uploads/mail_freigaben/syncopa-pruefung.bin'];
    Promise.all(geschuetzt.map(abruf)).then(function (ergebnisse) {
        var nichtGesperrt = geschuetzt.filter(function (p, i) { return ergebnisse[i].status !== 403; });
        if (nichtGesperrt.length) setzen('uploads', 'fehler', 'Nicht gesperrt: <code>' + nichtGesperrt.map(function (p) { return p.split('/').slice(0, 2).join('/') + '/'; }).join('</code>, <code>') + '</code> – Dateien darin wären ohne Anmeldung abrufbar.');
        else setzen('uploads', 'ok', 'Belege, BMV-Jahresberichte und Freigaben sind nur über Syncopa mit Anmeldung erreichbar.');
    });
    // Ordnerliste
    abruf('uploads/').then(function (r) {
        if (r.status === 200 && /Index of|<title>Index/i.test(r.text)) setzen('ordnerliste', 'fehler', 'Der Ordner <code>uploads/</code> zeigt eine Liste aller Dateien an.');
        else setzen('ordnerliste', 'ok', 'Ordner zeigen keine Dateilisten an.');
    });
    // Programmcode in uploads
    abruf('uploads/noten/index.php').then(function (r) {
        if (r.status === 200) setzen('uploadphp', 'warnung', 'Eine Programmdatei im Upload-Ordner lässt sich aufrufen. Syncopa prüft Uploads zwar streng, die zusätzliche Sperre fehlt aber.');
        else setzen('uploadphp', 'ok', 'Programmdateien im Upload-Ordner werden nicht ausgeführt.');
    });
    // Schutz-Kopfzeilen
    abruf('login.php').then(function (r) {
        if (!r.headers) { setzen('kopfzeilen', 'warnung', 'Konnte nicht geprüft werden.'); return; }
        var fehlt = [];
        if (!/sameorigin|deny/i.test(r.headers.get('x-frame-options') || '')) fehlt.push('X-Frame-Options');
        if (!/nosniff/i.test(r.headers.get('x-content-type-options') || '')) fehlt.push('X-Content-Type-Options');
        if (location.protocol === 'https:' && !r.headers.get('strict-transport-security')) fehlt.push('Strict-Transport-Security');
        if (fehlt.length) setzen('kopfzeilen', 'warnung', 'Es fehlen: ' + fehlt.join(', ') + '.');
        else setzen('kopfzeilen', 'ok', 'Schutz vor Einbetten in fremde Seiten, vor falsch erkannten Dateitypen' + (location.protocol === 'https:' ? ' und dauerhafte https-Pflicht' : '') + ' ist aktiv.');
    });
    // Umleitung, falls der Server sie nicht selbst prüfen konnte: Hinweis bleibt „Offen“
})();
</script>
