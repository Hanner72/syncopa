<?php
/**
 * SYNCOPA – Installations-Assistent
 * PHP 7.4+ kompatibel
 * install.lock sperrt diesen Installer dauerhaft nach erfolgreicher Installation.
 */

// ── Session ──────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['idata'])) {
    $_SESSION['idata'] = array();
}

// ── Lock ─────────────────────────────────────────────────────
if (file_exists(__DIR__ . '/install.lock')) {
    http_response_code(403);
    die(renderLocked());
}

// ── Schritt-Logik ────────────────────────────────────────────
$action = isset($_POST['action']) ? $_POST['action'] : '';
$step   = (int)(isset($_SESSION['istep']) ? $_SESSION['istep'] : 0);
$error  = '';

// Alle POST-Felder in Session speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $k => $v) {
        if ($k !== 'action') {
            $_SESSION['idata'][$k] = $v;
        }
    }
    // Checkboxen: wenn nicht im POST, auf 0 setzen
    $checkboxes = array('google_oauth_enabled','google_calendar_enabled','email_enabled');
    foreach ($checkboxes as $cb) {
        if (!isset($_POST[$cb])) {
            $_SESSION['idata'][$cb] = '';
        }
    }
}

// Zurück per GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['back'])) {
    $step = max(0, $step - 1);
    $_SESSION['istep'] = $step;
}

$post = $_SESSION['idata'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'back') {
        $step = max(0, $step - 1);

    } elseif ($action === 'next0') {
        $step = 1;

    } elseif ($action === 'next1') {
        $r = validateDb($post);
        if ($r !== true) { $error = $r; }
        else             { $step  = 2; }

    } elseif ($action === 'next2') {
        if (empty(trim($post['verein_name'] ?? ''))) {
            $error = 'Bitte den Vereinsnamen angeben.';
        } elseif (strlen($post['admin_passwort'] ?? '') < 8) {
            $error = 'Das Admin-Passwort muss mindestens 8 Zeichen lang sein.';
        } elseif (($post['admin_passwort'] ?? '') !== ($post['admin_passwort2'] ?? '')) {
            $error = 'Die Passwörter stimmen nicht überein.';
        } else {
            $step = 3;
        }

    } elseif ($action === 'next3') {
        // Integrationen – keine Pflichtfelder
        $step = 4;

    } elseif ($action === 'install') {
        $result = runInstallation($post);
        if ($result['success']) {
            $step = 5;
            $_SESSION['install_done_user']  = $post['admin_benutzername'] ?? 'admin';
            $_SESSION['install_done_email'] = $post['admin_email'] ?? '';
            $_SESSION['idata'] = array();
        } else {
            $error = $result['error'];
        }
    }

    $_SESSION['istep'] = $step;

} elseif (isset($_GET['back'])) {
    $step = max(0, $step - 1);
    $_SESSION['istep'] = $step;
}

$post = $_SESSION['idata'];

// ── Systemanforderungen ──────────────────────────────────────
function checkRequirements() {
    $checks = array();
    $checks[] = array('label' => 'PHP-Version >= 7.4',   'ok' => version_compare(PHP_VERSION, '7.4', '>='), 'val' => PHP_VERSION);
    $checks[] = array('label' => 'PDO-Erweiterung',      'ok' => extension_loaded('pdo'),       'val' => extension_loaded('pdo')       ? 'verfügbar' : 'fehlt');
    $checks[] = array('label' => 'PDO MySQL',             'ok' => extension_loaded('pdo_mysql'), 'val' => extension_loaded('pdo_mysql') ? 'verfügbar' : 'fehlt');
    $checks[] = array('label' => 'intl-Erweiterung',     'ok' => extension_loaded('intl'),      'val' => extension_loaded('intl')      ? 'verfügbar' : 'fehlt');
    $checks[] = array('label' => 'fileinfo-Erweiterung', 'ok' => extension_loaded('fileinfo'),  'val' => extension_loaded('fileinfo')  ? 'verfügbar' : 'fehlt');
    $checks[] = array('label' => 'database.sql vorhanden','ok' => file_exists(__DIR__ . '/database.sql'), 'val' => file_exists(__DIR__ . '/database.sql') ? 'gefunden' : 'fehlt!');
    $writable = is_writable(__DIR__) || (file_exists(__DIR__ . '/config.php') && is_writable(__DIR__ . '/config.php'));
    $checks[] = array('label' => 'Verzeichnis schreibbar','ok' => $writable, 'val' => $writable ? 'ja' : 'nein');
    return $checks;
}

function allChecksPassed($checks) {
    foreach ($checks as $c) { if (!$c['ok']) return false; }
    return true;
}

// ── DB-Verbindung testen ─────────────────────────────────────
function validateDb($p) {
    try {
        $pdo = dbConnect($p, false);
        $db  = preg_replace('/[^a-zA-Z0-9_]/', '', $p['db_name'] ?? '');
        if (empty($db)) return 'Ungültiger Datenbankname.';
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        return true;
    } catch (PDOException $e) {
        return 'Verbindung fehlgeschlagen: ' . htmlspecialchars($e->getMessage());
    }
}

function dbConnect($p, $withDb = true) {
    $host = $p['db_host'] ?? 'localhost';
    $port = (int)($p['db_port'] ?? 3306);
    $user = $p['db_user'] ?? '';
    $pass = $p['db_pass'] ?? '';
    $db   = $p['db_name'] ?? '';
    $dsn  = $withDb
        ? "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4"
        : "mysql:host=$host;port=$port;charset=utf8mb4";
    return new PDO($dsn, $user, $pass, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ));
}

// ── Installation ─────────────────────────────────────────────
function runInstallation($p) {
    try {
        $pdo = dbConnect($p);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        importSchema($pdo);
        insertBaseData($pdo);

        $vereinName = trim($p['verein_name'] ?? '');
        $vereinOrt  = trim($p['verein_ort']  ?? '');
        $pdo->prepare("UPDATE `einstellungen` SET `wert`=? WHERE `schluessel`='verein_name'")->execute(array($vereinName));
        $pdo->prepare("UPDATE `einstellungen` SET `wert`=? WHERE `schluessel`='verein_ort'")->execute(array($vereinOrt));

        // Admin-Benutzer ZUERST (FK-Referenz für Beispieldaten)
        $hash  = password_hash($p['admin_passwort'], PASSWORD_BCRYPT, array('cost' => 12));
        $bname = trim($p['admin_benutzername'] ?? 'admin');
        $email = trim($p['admin_email'] ?? '');
        $pdo->exec("DELETE FROM `benutzer` WHERE `benutzername` = 'admin'");
        $pdo->prepare("INSERT INTO `benutzer` (`benutzername`,`email`,`passwort_hash`,`rolle`,`rolle_id`,`aktiv`,`erstellt_am`) VALUES (?,?,?,'admin',1,1,NOW())")->execute(array($bname, $email, $hash));
        $adminId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO `benutzer_rollen` (`benutzer_id`, `rolle_id`) VALUES (?, 1)")->execute(array($adminId));

        // Installations-ID generieren & Telemetry speichern
        $installId = bin2hex(random_bytes(16));
        $telemetryEnabled = !empty($p['telemetry_enabled']) ? '1' : '0';
        $pdo->prepare("UPDATE `einstellungen` SET `wert`=? WHERE `schluessel`='installation_id'")->execute(array($installId));
        $pdo->prepare("UPDATE `einstellungen` SET `wert`=? WHERE `schluessel`='telemetry_enabled'")->execute(array($telemetryEnabled));

        if (!empty($p['beispieldaten']) && $p['beispieldaten'] === '1') {
            insertSampleData($pdo);
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        writeConfig($p);

        foreach (array('uploads','uploads/noten','uploads/fotos','uploads/dokumente','uploads/fest_vertraege') as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (!is_dir($path)) mkdir($path, 0755, true);
            if (!file_exists($path . '/index.php')) {
                file_put_contents($path . '/index.php', '<?php // Kein direkter Zugriff');
            }
        }

        file_put_contents(__DIR__ . '/install.lock', date('Y-m-d H:i:s') . ' – Syncopa Installation abgeschlossen');
        return array('success' => true);

    } catch (Exception $e) {
        return array('success' => false, 'error' => 'Fehler: ' . htmlspecialchars($e->getMessage()));
    }
}

// ── Schema importieren ───────────────────────────────────────
function importSchema($pdo) {
    $sql = file_get_contents(__DIR__ . '/database.sql');
    if ($sql === false) throw new Exception('database.sql konnte nicht gelesen werden.');
    $sql = str_replace("\r\n", "\n", $sql);
    $statements = explode(';', $sql);
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        $upper = strtoupper(ltrim($stmt));
        if (strpos($upper, 'CREATE TABLE') !== 0 &&
            strpos($upper, 'ALTER TABLE')  !== 0 &&
            strpos($upper, 'CREATE INDEX') !== 0) continue;
        try { $pdo->exec($stmt); }
        catch (PDOException $e) {
            if (strpos($e->getMessage(), '1050') === false) throw $e;
        }
    }
}

// ── Basisdaten ───────────────────────────────────────────────
function insertBaseData($pdo) {
    // Pivot-Tabelle für Mehrfachrollen sicherstellen
    $pdo->exec("CREATE TABLE IF NOT EXISTS `benutzer_rollen` (
        `benutzer_id` INT NOT NULL,
        `rolle_id` INT NOT NULL,
        PRIMARY KEY (`benutzer_id`, `rolle_id`),
        FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`rolle_id`) REFERENCES `rollen`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("DELETE FROM `rollen`");
    $pdo->exec("ALTER TABLE `rollen` AUTO_INCREMENT = 1");
    $pdo->exec("INSERT INTO `rollen` (`id`,`name`,`beschreibung`,`ist_admin`,`farbe`,`sortierung`,`aktiv`,`erstellt_am`) VALUES
        (1,'admin','Administrator mit vollen Rechten',1,'danger',1,1,NOW()),
        (2,'obmann','Obmann/Vorstand',0,'primary',2,1,NOW()),
        (3,'kapellmeister','Kapellmeister',0,'success',3,1,NOW()),
        (4,'kassier','Kassier',0,'info',4,1,NOW()),
        (5,'schriftfuehrer','Schriftführer',0,'warning',5,1,NOW()),
        (6,'trachtenwart','Trachtenwart',0,'purple',6,1,NOW()),
        (7,'instrumentenwart','Instrumentenwart',0,'dark',7,1,NOW()),
        (8,'jugendbeauftragter','Jugendbeauftragter',0,'info',8,1,NOW()),
        (9,'mitglied','Normales Mitglied',0,'secondary',999,1,NOW()),
        (10,'notenwart','Notenwart',0,'danger',9,1,NOW())");

    $pdo->exec("DELETE FROM `berechtigungen`");
    $pdo->exec("INSERT INTO `berechtigungen` (`id`,`rolle_id`,`rolle`,`modul`,`lesen`,`schreiben`,`loeschen`) VALUES
        (1,1,'admin','mitglieder',1,1,1),(2,1,'admin','instrumente',1,1,1),(3,1,'admin','noten',1,1,1),
        (4,1,'admin','ausrueckungen',1,1,1),(5,1,'admin','finanzen',1,1,1),(6,1,'admin','benutzer',1,1,1),
        (7,1,'admin','einstellungen',1,1,1),(8,1,'admin','uniformen',1,1,1),
        (9,2,'obmann','mitglieder',1,1,1),(10,2,'obmann','ausrueckungen',1,1,1),(11,2,'obmann','noten',1,0,0),
        (12,2,'obmann','instrumente',1,0,0),(13,2,'obmann','uniformen',1,0,0),(14,2,'obmann','finanzen',1,0,0),(15,2,'obmann','benutzer',1,0,0),
        (16,3,'kapellmeister','mitglieder',1,0,0),(17,3,'kapellmeister','ausrueckungen',1,1,1),(18,3,'kapellmeister','noten',1,1,1),
        (19,3,'kapellmeister','instrumente',1,0,0),(20,3,'kapellmeister','uniformen',1,0,0),(21,3,'kapellmeister','finanzen',1,0,0),(22,3,'kapellmeister','benutzer',1,0,0),
        (23,4,'kassier','mitglieder',1,0,0),(24,4,'kassier','ausrueckungen',1,0,0),(25,4,'kassier','noten',1,0,0),
        (26,4,'kassier','instrumente',1,0,0),(27,4,'kassier','uniformen',1,0,0),(28,4,'kassier','finanzen',1,1,1),(29,4,'kassier','benutzer',1,0,0),
        (30,5,'schriftfuehrer','mitglieder',1,1,0),(31,5,'schriftfuehrer','ausrueckungen',1,1,0),(32,5,'schriftfuehrer','noten',1,0,0),
        (33,5,'schriftfuehrer','instrumente',1,0,0),(34,5,'schriftfuehrer','uniformen',1,0,0),(35,5,'schriftfuehrer','finanzen',1,0,0),(36,5,'schriftfuehrer','benutzer',1,0,0),
        (37,6,'trachtenwart','mitglieder',1,1,0),(38,6,'trachtenwart','ausrueckungen',1,0,0),(39,6,'trachtenwart','noten',1,0,0),
        (40,6,'trachtenwart','instrumente',1,0,0),(41,6,'trachtenwart','uniformen',1,1,1),(42,6,'trachtenwart','finanzen',1,0,0),(43,6,'trachtenwart','benutzer',1,0,0),
        (44,7,'instrumentenwart','mitglieder',1,1,0),(45,7,'instrumentenwart','ausrueckungen',1,0,0),(46,7,'instrumentenwart','noten',1,0,0),
        (47,7,'instrumentenwart','instrumente',1,1,1),(48,7,'instrumentenwart','uniformen',1,0,0),(49,7,'instrumentenwart','finanzen',1,0,0),(50,7,'instrumentenwart','benutzer',1,0,0),
        (51,8,'jugendbeauftragter','mitglieder',1,1,0),(52,8,'jugendbeauftragter','ausrueckungen',1,1,1),(53,8,'jugendbeauftragter','noten',1,1,1),
        (54,8,'jugendbeauftragter','instrumente',1,0,0),(55,8,'jugendbeauftragter','uniformen',1,0,0),(56,8,'jugendbeauftragter','finanzen',1,0,0),(57,8,'jugendbeauftragter','benutzer',1,0,0),
        (58,9,'mitglied','mitglieder',1,0,0),(59,9,'mitglied','ausrueckungen',1,0,0),(60,9,'mitglied','noten',1,0,0),
        (61,9,'mitglied','instrumente',1,0,0),(62,9,'mitglied','uniformen',1,0,0),
        (77,10,'notenwart','mitglieder',1,0,0),(78,10,'notenwart','ausrueckungen',1,0,0),(79,10,'notenwart','noten',1,1,1),
        (80,10,'notenwart','instrumente',1,0,0),(81,10,'notenwart','uniformen',1,0,0),(82,10,'notenwart','finanzen',0,0,0),(83,10,'notenwart','benutzer',0,0,0),
        (84,1,'admin','fest',1,1,1)");

    $pdo->exec("DELETE FROM `register`");
    $pdo->exec("INSERT INTO `register` (`id`,`name`,`beschreibung`,`sortierung`) VALUES
        (1,'Flöten/Klarinetten','Holzbläser - hohe Register',1),(2,'Saxophone','Holzbläser - Saxophone',2),
        (3,'Flügelhörner','Blechbläser - hohe Lage',3),(4,'Trompeten','Blechbläser - hohe Lage',4),
        (5,'Tenorhörner','Blechbläser - mittlere Lage',5),(6,'Posaunen','Blechbläser - tiefe Lage',6),
        (7,'Tuben','Blechbläser - Bass',7),(8,'Schlagwerk','Perkussion',8)");

    $pdo->exec("DELETE FROM `instrument_typen`");
    $pdo->exec("INSERT INTO `instrument_typen` (`id`,`name`,`register_id`,`beschreibung`) VALUES
        (1,'Flöte',1,'Querflöte'),(2,'Klarinette in Bb',1,'Standard-Klarinette'),(3,'Bassklarinette',1,'Tiefe Klarinette'),
        (4,'Altsaxophon',2,'Alt-Saxophon in Es'),(5,'Tenorsaxophon',2,'Tenor-Saxophon in Bb'),(6,'Baritonsaxophon',2,'Bariton-Saxophon in Es'),
        (7,'Flügelhorn',3,'Flügelhorn in Bb'),(8,'Trompete',4,'Trompete in Bb'),(9,'Tenorhorn',5,'Tenorhorn in Bb'),
        (10,'Bariton',5,'Bariton/Euphonium'),(11,'Posaune',6,'Zugposaune'),(12,'Bassposaune',6,'Bass-Posaune'),
        (13,'Tuba',7,'B-Tuba'),(14,'Es-Bass',7,'Es-Tuba'),(15,'Schlagzeug',8,'Drum-Set'),
        (16,'Kleine Trommel',8,'Snare Drum'),(17,'Große Trommel',8,'Bass Drum'),(18,'Becken',8,'Marschbecken'),(19,'Lyra',8,'Glockenspiel/Lyra')");

    $pdo->exec("DELETE FROM `einstellungen`");
    $pdo->exec("INSERT INTO `einstellungen` (`id`,`schluessel`,`wert`,`beschreibung`,`aktualisiert_am`) VALUES
        (1,'verein_name','','Name des Musikvereins',NOW()),(2,'verein_ort','','Ort des Vereins',NOW()),
        (3,'verein_plz','','Postleitzahl',NOW()),(4,'verein_adresse','','Adresse des Vereins',NOW()),
        (5,'verein_email','','E-Mail-Adresse',NOW()),(6,'verein_telefon','','Telefonnummer',NOW()),
        (7,'verein_website','','Website',NOW()),(8,'google_calendar_api_key','','Google Calendar API Schlüssel',NOW()),
        (9,'google_calendar_id','','Google Calendar ID',NOW()),(10,'mitgliedsbeitrag_jahr','0','Jährlicher Mitgliedsbeitrag',NOW()),
        (11,'beitrag_aktiv','0','Beitrag aktive Mitglieder',NOW()),(12,'beitrag_passiv','0','Beitrag passive Mitglieder',NOW()),
        (13,'beitrag_ehrenmitglied','0','Beitrag Ehrenmitglieder',NOW()),(14,'beitrag_faelligkeit_monat','1','Monat der Fälligkeit',NOW()),
        (15,'email_smtp_host','','SMTP Server',NOW()),(16,'email_smtp_port','587','SMTP Port',NOW()),
        (17,'email_from','','Absender E-Mail',NOW()),(18,'uniform_groessen_system','international','Größensystem',NOW()),
        (19,'uniform_groessen_verfuegbar','XS,S,M,L,XL,XXL','Verfügbare Größen',NOW()),(20,'uniform_pfand_betrag','50.00','Standard-Pfandbetrag',NOW()),
        (21,'probentag','Freitag','Regulärer Probentag',NOW()),(22,'probenzeit_beginn','19:30','Beginn der Probe',NOW()),
        (23,'probenzeit_ende','22:00','Ende der Probe',NOW()),(24,'probelokal_adresse','','Adresse Probelokal',NOW()),
        (29,'beitrag_passiv_betrag','25','Betrag passive Mitglieder',NOW()),(39,'beitrag_ausgetreten','0','Beitrag ausgetretene Mitglieder',NOW()),
        (40,'nummernkreis_mitglieder_prefix','My','Nummernkreis Mitglieder – Präfix',NOW()),
        (41,'nummernkreis_mitglieder_stellen','3','Nummernkreis Mitglieder – Stellen',NOW()),
        (42,'nummernkreis_noten_prefix','Ny','Nummernkreis Noten – Präfix',NOW()),
        (43,'nummernkreis_noten_stellen','3','Nummernkreis Noten – Stellen',NOW()),
        (44,'nummernkreis_instrumente_prefix','Iy','Nummernkreis Instrumente – Präfix',NOW()),
        (45,'nummernkreis_instrumente_stellen','3','Nummernkreis Instrumente – Stellen',NOW()),
        (46,'installation_id','','Eindeutige Installations-ID',NOW()),
        (47,'telemetry_enabled','0','Anonyme Nutzungsstatistik senden',NOW())");
}

// ── Beispieldaten ─────────────────────────────────────────────
function insertSampleData($pdo) {
    $year = (int)date('Y');

    $pdo->exec("DELETE FROM `mitglieder`");
    $pdo->exec("ALTER TABLE `mitglieder` AUTO_INCREMENT = 1");
    $pdo->exec("INSERT INTO `mitglieder`
        (`id`,`mitgliedsnummer`,`vorname`,`nachname`,`geburtsdatum`,`geschlecht`,`strasse`,`plz`,`ort`,`land`,`telefon`,`mobil`,`email`,`register_id`,`eintritt_datum`,`status`,`erstellt_am`)
    VALUES
        (1,'2001','Thomas','Huber','1985-03-12','m','Hauptstrasse 4','8630','Mariazell','AT','+43 3882 2241','+43 664 1234567','t.huber@example.at',4,'2001-05-01','aktiv',NOW()),
        (2,'2003','Maria','Mayer','1990-07-24','w','Kirchengasse 8','8630','Mariazell','AT','','+43 664 2345678','m.mayer@example.at',3,'2003-09-01','aktiv',NOW()),
        (3,'2005','Andreas','Leitner','1978-11-03','m','Bergstrasse 12','8632','Gusswerk','AT','+43 3882 3312','+43 664 3456789','a.leitner@example.at',6,'2005-01-15','aktiv',NOW()),
        (4,'2007','Eva','Schmid','1995-05-19','w','Lindenweg 3','8630','Mariazell','AT','','+43 664 4567890','e.schmid@example.at',1,'2007-04-01','aktiv',NOW()),
        (5,'2009','Peter','Wagner','1982-09-07','m','Am Anger 6','8633','Siegharts','AT','+43 3882 4421','+43 664 5678901','p.wagner@example.at',7,'2009-06-01','aktiv',NOW()),
        (6,'2010','Sophie','Gruber','1998-01-30','w','Schulstrasse 2','8630','Mariazell','AT','','+43 664 6789012','s.gruber@example.at',2,'2010-09-01','aktiv',NOW()),
        (7,'2012','Michael','Bauer','1975-06-15','m','Wiesenweg 9','8630','Mariazell','AT','+43 3882 5543','+43 664 7890123','m.bauer@example.at',5,'2012-03-01','aktiv',NOW()),
        (8,'2013','Lisa','Hofer','1993-12-08','w','Gartenstrasse 14','8631','Etzen','AT','','+43 664 8901234','l.hofer@example.at',1,'2013-09-01','aktiv',NOW()),
        (9,'2015','Klaus','Steiner','1988-04-22','m','Dorfplatz 1','8630','Mariazell','AT','+43 3882 6654','+43 664 9012345','k.steiner@example.at',8,'2015-01-01','aktiv',NOW()),
        (10,'2016','Julia','Fischer','2000-08-14','w','Bachgasse 5','8630','Mariazell','AT','','+43 664 0123456','j.fischer@example.at',3,'2016-09-01','aktiv',NOW()),
        (11,'2018','Markus','Pichler','1970-02-28','m','Muehlgasse 7','8633','Siegharts','AT','+43 3882 7765','+43 699 1234567','m.pichler@example.at',7,'2018-05-01','aktiv',NOW()),
        (12,'2020','Anna','Reiter','2003-10-05','w','Blumenweg 11','8630','Mariazell','AT','','+43 699 2345678','a.reiter@example.at',4,'2020-09-01','aktiv',NOW())");

    $pdo->exec("DELETE FROM `mitglied_instrumente`");
    $pdo->exec("INSERT INTO `mitglied_instrumente` (`mitglied_id`,`instrument_typ_id`,`hauptinstrument`,`seit_datum`) VALUES
        (1,8,1,'2001-05-01'),(2,7,1,'2003-09-01'),(3,11,1,'2005-01-15'),
        (4,2,1,'2007-04-01'),(5,13,1,'2009-06-01'),(6,4,1,'2010-09-01'),
        (7,9,1,'2012-03-01'),(8,1,1,'2013-09-01'),(9,15,1,'2015-01-01'),
        (10,7,1,'2016-09-01'),(11,14,1,'2018-05-01'),(12,8,1,'2020-09-01')");

    $demoHash = password_hash('admin123', PASSWORD_BCRYPT, array('cost' => 10));
    $stmt = $pdo->prepare("INSERT INTO `benutzer` (`benutzername`,`email`,`passwort_hash`,`rolle`,`rolle_id`,`aktiv`,`mitglied_id`,`erstellt_am`) VALUES (?,?,?,'mitglied',9,1,?,NOW())");
    $demoUsers = array(
        array('t.huber',   't.huber@example.at',   1),
        array('m.mayer',   'm.mayer@example.at',   2),
        array('a.leitner', 'a.leitner@example.at', 3),
        array('e.schmid',  'e.schmid@example.at',  4),
        array('p.wagner',  'p.wagner@example.at',  5),
    );
    $stmtBr = $pdo->prepare("INSERT IGNORE INTO `benutzer_rollen` (`benutzer_id`, `rolle_id`) VALUES (?, 9)");
    foreach ($demoUsers as $u) {
        try {
            $stmt->execute(array($u[0], $u[1], $demoHash, $u[2]));
            $stmtBr->execute(array((int)$pdo->lastInsertId()));
        }
        catch (Exception $ex) { /* ignorieren */ }
    }

    $pdo->exec("DELETE FROM `instrumente`");
    $pdo->exec("ALTER TABLE `instrumente` AUTO_INCREMENT = 1");
    $pdo->exec("INSERT INTO `instrumente` (`id`,`inventar_nummer`,`instrument_typ_id`,`hersteller`,`modell`,`seriennummer`,`baujahr`,`anschaffungsdatum`,`anschaffungspreis`,`zustand`,`standort`,`erstellt_am`) VALUES
        (1,'INV-001',8,'Bach','Stradivarius','BA-2019-001',2019,'2019-03-15',1890.00,'gut','Probelokal Schrank 1',NOW()),
        (2,'INV-002',11,'Yamaha','YSL-456G','YA-2015-002',2015,'2015-09-01',1250.00,'gut','Probelokal Schrank 2',NOW()),
        (3,'INV-003',13,'Meinl Weston','45','MW-2010-003',2010,'2010-06-20',3200.00,'gut','Probelokal Schrank 3',NOW()),
        (4,'INV-004',15,'Pearl','Export','PE-2018-004',2018,'2018-01-10',1650.00,'sehr gut','Probelokal Schlagwerk',NOW()),
        (5,'INV-005',2,'Buffet Crampon','E11','BC-2020-005',2020,'2020-05-05',780.00,'sehr gut','Probelokal Schrank 1',NOW()),
        (6,'INV-006',14,'Melton','195','ME-2008-006',2008,'2008-03-01',2800.00,'befriedigend','Probelokal Schrank 3',NOW()),
        (7,'INV-007',9,'Wessex','TH-900','WE-2017-007',2017,'2017-07-12',950.00,'gut','Probelokal Schrank 2',NOW()),
        (8,'INV-008',17,'Pearl','Championship','PE-2016-008',2016,'2016-02-20',420.00,'gut','Probelokal Schlagwerk',NOW())");

    $pdo->exec("DELETE FROM `noten`");
    $pdo->exec("ALTER TABLE `noten` AUTO_INCREMENT = 1");
    $pdo->exec("INSERT INTO `noten` (`id`,`titel`,`untertitel`,`komponist`,`arrangeur`,`verlag`,`besetzung`,`schwierigkeitsgrad`,`genre`,`archiv_nummer`,`anzahl_stimmen`,`zustand`,`standort`,`erstellt_am`) VALUES
        (1,'Radetzky-Marsch','Op. 228','Johann Strauss Vater','Kurt Gaeble','Musikverlag Doblinger','Blasorchester','3','Marsch','N26001',18,'gut','Regal A',NOW()),
        (2,'Boehmischer Wind','','Karel Vacek','Herbert Ferstl','Edition Melodie','Blasorchester','2','Polka','N26002',18,'sehr gut','Regal A',NOW()),
        (3,'Jupiter aus den Planeten','Op. 32','Gustav Holst','Alfred Reed','Hal Leonard','Blasorchester','5','Konzert','N26003',22,'gut','Regal B',NOW()),
        (4,'Ein Heller und ein Batzen','','Volksweise','Franz Bummerl','Eigendruck','Blasorchester','1','Volksmusik','N26004',18,'befriedigend','Regal A',NOW()),
        (5,'Festmusik der Stadt Wien','','Richard Strauss','Mark Hindsley','Boosey and Hawkes','Blasorchester','6','Konzert','N26005',24,'gut','Regal B',NOW()),
        (6,'Amazing Grace','','Traditional','Johann de Meij','Molenaar','Blasorchester','3','Spiritual','N26006',18,'sehr gut','Regal A',NOW()),
        (7,'Florentiner Marsch','Op. 214','Julius Fucik','','Peters','Blasorchester','3','Marsch','N26007',20,'gut','Regal A',NOW()),
        (8,'Das Beste kommt noch','','Roy Black','Thomas Asanger','Eigendruck','Blasorchester','2','Schlager','N26008',18,'gut','Regal C',NOW())");

    $pdo->exec("DELETE FROM `ausrueckungen`");
    $pdo->exec("ALTER TABLE `ausrueckungen` AUTO_INCREMENT = 1");
    $events = array(
        array('Fruehjahrskonzert',  'Konzert',     '-04-26 19:30:00', '-04-26 22:00:00', 'Kulturhaus Mariazell',  'Grosse Uniform'),
        array('Maiaufmarsch',       'Ausrückung', '-05-01 09:00:00', '-05-01 12:00:00', 'Stadtplatz Mariazell',  'Grosse Uniform'),
        array('Fronleichnam',       'Ausrückung', '-06-19 09:00:00', '-06-19 11:00:00', 'Mariazell Basilika',    'Grosse Uniform'),
        array('Bezirksmusikfest',   'Fest',        '-07-05 10:00:00', '-07-05 18:00:00', 'Bruck an der Mur',     'Grosse Uniform'),
        array('Platzkonzert Juli',  'Konzert',     '-07-19 19:00:00', '-07-19 21:30:00', 'Hauptplatz Mariazell', 'Tracht'),
        array('Erntedankfest',      'Ausrückung', '-09-28 09:30:00', '-09-28 12:00:00', 'Pfarrkirche Mariazell','Grosse Uniform'),
        array('Herbstkonzert',      'Konzert',     '-11-08 19:30:00', '-11-08 22:30:00', 'Kulturhaus Mariazell', 'Frack'),
        array('Weihnachtsmarkt',    'Ausrückung', '-12-06 16:00:00', '-12-06 19:00:00', 'Hauptplatz Mariazell', 'Tracht'),
    );
    $stA = $pdo->prepare("INSERT INTO `ausrueckungen` (`titel`,`typ`,`start_datum`,`ende_datum`,`ganztaegig`,`ort`,`uniform`,`status`,`erstellt_von`,`erstellt_am`) VALUES (?,?,?,?,0,?,?,'geplant',1,NOW())");
    foreach ($events as $e) {
        $stA->execute(array($e[0] . ' ' . $year, $e[1], $year . $e[2], $year . $e[3], $e[4], $e[5]));
    }

    $pdo->exec("DELETE FROM `kalender_termine`");
    $pdo->exec("ALTER TABLE `kalender_termine` AUTO_INCREMENT = 1");
    $friday = strtotime('next Friday');
    for ($i = 0; $i < 8; $i++) {
        $d = date('Y-m-d', $friday + $i * 7 * 86400);
        $pdo->prepare("INSERT INTO `kalender_termine` (`titel`,`beschreibung`,`typ`,`start_datum`,`ende_datum`,`ganztaegig`,`ort`,`farbe`,`erstellt_von`,`erstellt_am`,`aktualisiert_am`) VALUES (?,?,'Probe',?,?,0,'Probelokal','#4471A3',1,NOW(),NOW())"
        )->execute(array(($i + 1) . '. Probe', 'Regulaere Wochenprobe', $d . ' 19:30:00', $d . ' 22:00:00'));
    }

    $pdo->exec("DELETE FROM `finanzen`");
    $pdo->exec("ALTER TABLE `finanzen` AUTO_INCREMENT = 1");
    $fin = array(
        array('einnahme', '-01-15', 1200.00, 'Mitgliedsbeitraege', 'Mitgliedsbeitraege ' . $year, 'BE-001', 'Ueberweisung'),
        array('einnahme', '-01-20',  850.00, 'Subventionen',       'Gemeindesubvention '  . $year, 'BE-002', 'Ueberweisung'),
        array('ausgabe',  '-02-10',  320.00, 'Noten und Material', 'Notenankauf Fruehjahrskonzert', 'AU-001', 'Ueberweisung'),
        array('ausgabe',  '-02-18',  180.00, 'Instandhaltung',     'Reparatur Bassposaune INV-006', 'AU-002', 'Bar'),
        array('einnahme', '-04-27', 1650.00, 'Konzerteinnahmen',   'Kartenerloes Fruehjahrskonzert','BE-003', 'Bar'),
        array('ausgabe',  '-04-27',  240.00, 'Bewirtung',          'Buffet Fruehjahrskonzert',     'AU-003', 'Bar'),
        array('ausgabe',  '-05-03',   95.00, 'Verwaltung',         'Druckkosten Programme',        'AU-004', 'Bar'),
        array('einnahme', '-07-19',  420.00, 'Spenden',            'Hutspende Platzkonzert',       'BE-004', 'Bar'),
        array('ausgabe',  '-08-15',  560.00, 'Uniformen',          'Reinigung Uniformen',          'AU-005', 'Ueberweisung'),
        array('ausgabe',  '-09-01',  750.00, 'Instrumente',        'Ankauf Lyra NEU',              'AU-006', 'Ueberweisung'),
    );
    $stF = $pdo->prepare("INSERT INTO `finanzen` (`typ`,`datum`,`betrag`,`kategorie`,`beschreibung`,`beleg_nummer`,`zahlungsart`,`erstellt_von`,`erstellt_am`) VALUES (?,?,?,?,?,?,?,1,NOW())");
    foreach ($fin as $f) { $stF->execute(array($f[0], $year . $f[1], $f[2], $f[3], $f[4], $f[5], $f[6])); }

    $pdo->exec("DELETE FROM `beitraege`");
    $pdo->exec("ALTER TABLE `beitraege` AUTO_INCREMENT = 1");
    $stB = $pdo->prepare("INSERT INTO `beitraege` (`mitglied_id`,`jahr`,`betrag`,`bezahlt_am`,`bezahlt`,`zahlungsart`,`erstellt_am`) VALUES (?,?,100.00,?,1,'Ueberweisung',NOW())");
    for ($mid = 1; $mid <= 10; $mid++) { $stB->execute(array($mid, $year, $year . '-01-31')); }
    $stBo = $pdo->prepare("INSERT INTO `beitraege` (`mitglied_id`,`jahr`,`betrag`,`bezahlt`,`erstellt_am`) VALUES (?,?,100.00,0,NOW())");
    $stBo->execute(array(11, $year));
    $stBo->execute(array(12, $year));
}

// ── config.php schreiben ─────────────────────────────────────
function writeConfig($p) {
    $host      = addslashes($p['db_host']    ?? 'localhost');
    $dbname    = addslashes($p['db_name']    ?? '');
    $user      = addslashes($p['db_user']    ?? '');
    $pass      = addslashes($p['db_pass']    ?? '');
    $baseUrl   = rtrim(addslashes($p['base_url'] ?? ''), '/');
    $ts        = date('Y-m-d H:i:s');

    $googleOauthEnabled = !empty($p['google_oauth_enabled'])      ? 'true'  : 'false';
    $googleClientId     = addslashes($p['google_client_id']       ?? '');
    $googleClientSecret = addslashes($p['google_client_secret']   ?? '');
    $googleCalEnabled   = !empty($p['google_calendar_enabled'])   ? 'true'  : 'false';
    $googleCalApiKey    = addslashes($p['google_calendar_api_key'] ?? '');
    $googleCalId        = addslashes($p['google_calendar_id']      ?? '');
    $ocrApiKey          = addslashes($p['ocr_api_key']            ?? '');
    $emailEnabled       = !empty($p['email_enabled'])             ? 'true'  : 'false';
    $emailSmtpHost      = addslashes($p['email_smtp_host']        ?? '');
    $emailSmtpPort      = (int)($p['email_smtp_port']             ?? 587);
    $emailSmtpUser      = addslashes($p['email_smtp_user']        ?? '');
    $emailSmtpPass      = addslashes($p['email_smtp_pass']        ?? '');
    $emailFrom          = addslashes($p['email_from']             ?? '');
    $emailFromName      = addslashes($p['email_from_name']        ?? 'Musikverein');

    $lines = array(
        "<?php",
        "/**",
        " * SYNCOPA – Konfigurationsdatei",
        " * Automatisch erstellt am: {$ts}",
        " * Enthält nur umgebungsspezifische Einstellungen (DB, URLs, API-Keys).",
        " * Diese Datei wird bei Updates NICHT überschrieben.",
        " */",
        "",
        "// ============================================================================",
        "// DATENBANK",
        "// ============================================================================",
        "define('DB_HOST',    '{$host}');",
        "define('DB_NAME',    '{$dbname}');",
        "define('DB_USER',    '{$user}');",
        "define('DB_PASS',    '{$pass}');",
        "define('DB_CHARSET', 'utf8mb4');",
        "",
        "// ============================================================================",
        "// ANWENDUNGS-URL",
        "// ============================================================================",
        "define('BASE_URL', '{$baseUrl}');",
        "",
        "// ============================================================================",
        "// GOOGLE OAUTH LOGIN (optional – auf false setzen zum Deaktivieren)",
        "// Anleitung: https://console.cloud.google.com/apis/credentials",
        "// Redirect URI: {$baseUrl}/login_google_callback.php",
        "// ============================================================================",
        "define('GOOGLE_OAUTH_ENABLED',  {$googleOauthEnabled});",
        "define('GOOGLE_CLIENT_ID',      '{$googleClientId}');",
        "define('GOOGLE_CLIENT_SECRET',  '{$googleClientSecret}');",
        "define('GOOGLE_REDIRECT_URI',   BASE_URL . '/login_google_callback.php');",
        "",
        "// ============================================================================",
        "// GOOGLE CALENDAR API (optional)",
        "// ============================================================================",
        "define('GOOGLE_CALENDAR_ENABLED', {$googleCalEnabled});",
        "define('GOOGLE_CALENDAR_API_KEY', '{$googleCalApiKey}');",
        "define('GOOGLE_CALENDAR_ID',      '{$googleCalId}');",
        "",
        "// ============================================================================",
        "// OCR.SPACE API (optional)",
        "// Kostenloser Key: https://ocr.space/ocrapi",
        "// ============================================================================",
        "define('OCR_SPACE_API_KEY', '{$ocrApiKey}');",
        "",
        "// ============================================================================",
        "// E-MAIL / SMTP (optional)",
        "// ============================================================================",
        "define('EMAIL_ENABLED',    {$emailEnabled});",
        "define('EMAIL_SMTP_HOST',  '{$emailSmtpHost}');",
        "define('EMAIL_SMTP_PORT',  {$emailSmtpPort});",
        "define('EMAIL_SMTP_USER',  '{$emailSmtpUser}');",
        "define('EMAIL_SMTP_PASS',  '{$emailSmtpPass}');",
        "define('EMAIL_FROM',       '{$emailFrom}');",
        "define('EMAIL_FROM_NAME',  '{$emailFromName}');",
        "",
        "// ============================================================================",
        "// App-Konfiguration laden (nicht editieren)",
        "// ============================================================================",
        "require_once __DIR__ . '/config.app.php';",
    );

    if (file_put_contents(__DIR__ . '/config.php', implode("\n", $lines)) === false) {
        throw new Exception('config.php konnte nicht geschrieben werden. Bitte Schreibrechte prüfen.');
    }
}

// ── Gesperrt-Seite ────────────────────────────────────────────
function renderLocked() {
    return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8">'
         . '<meta name="viewport" content="width=device-width,initial-scale=1">'
         . '<title>Syncopa Installer – Gesperrt</title>'
         . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
         . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">'
         . '<style>body{background:#f5f7fa;display:flex;align-items:center;justify-content:center;min-height:100vh}</style>'
         . '</head><body><div class="text-center p-5">'
         . '<i class="bi bi-shield-lock-fill text-danger" style="font-size:4rem"></i>'
         . '<h3 class="mt-3">Installer gesperrt</h3>'
         . '<p class="text-muted">Syncopa wurde bereits installiert.<br>'
         . 'Löschen Sie <code>install.lock</code>, um eine Neuinstallation zu starten.</p>'
         . '<a href="index.php" class="btn btn-primary mt-2"><i class="bi bi-house me-2"></i>Zur Anwendung</a>'
         . '</div></body></html>';
}

// ── HTML-Helfer ───────────────────────────────────────────────
function h($v)                         { return htmlspecialchars((string)$v, ENT_QUOTES); }
function val($key, $post, $default='') { return h(isset($post[$key]) ? $post[$key] : $default); }
function baseUrl() {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $path  = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    return "$proto://$host$path";
}

$checks    = checkRequirements();
$allOk     = allChecksPassed($checks);
$stepNames = array('Willkommen', 'Datenbank', 'Anwendung', 'Integrationen', 'Installation', 'Fertig');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Syncopa – Installation</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --c-primary:#4471A3; --c-primary-light:#5496cb;
            --c-success:#5b8a72; --c-bg:#f5f7fa;
            --c-border:#e2e8f0;  --c-muted:#64748b;
        }
        body { background:var(--c-bg); font-family:'Segoe UI',system-ui,sans-serif; min-height:100vh; display:flex; flex-direction:column; color:#1e293b; }
        .inst-header { background:var(--c-primary); color:#fff; padding:1.25rem 2rem; display:flex; align-items:center; gap:1rem; box-shadow:0 2px 8px rgba(0,0,0,.15); }
        .inst-header img { height:40px; }
        .inst-header h1  { font-size:1.3rem; margin:0; font-weight:600; }
        .inst-header small { opacity:.75; font-size:.8rem; }
        .inst-body { flex:1; display:flex; align-items:flex-start; justify-content:center; padding:2.5rem 1rem; }
        .inst-container { width:100%; max-width:680px; }
        .step-progress { display:flex; margin-bottom:2rem; }
        .step-item     { display:flex; flex-direction:column; align-items:center; position:relative; flex:1; }
        .step-item:not(:last-child)::after { content:''; position:absolute; top:16px; left:50%; width:100%; height:2px; background:var(--c-border); z-index:0; }
        .step-item.done::after, .step-item.active::after { background:var(--c-primary); }
        .step-circle { width:32px; height:32px; border-radius:50%; border:2px solid var(--c-border); background:#fff; display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; color:var(--c-muted); position:relative; z-index:1; transition:all .3s; }
        .step-item.done .step-circle   { background:var(--c-primary); border-color:var(--c-primary); color:#fff; }
        .step-item.active .step-circle { background:var(--c-primary); border-color:var(--c-primary); color:#fff; box-shadow:0 0 0 4px rgba(68,113,163,.2); }
        .step-label { font-size:.65rem; color:var(--c-muted); margin-top:.4rem; white-space:nowrap; }
        .step-item.active .step-label { color:var(--c-primary); font-weight:600; }
        .inst-card { background:#fff; border:1px solid var(--c-border); border-radius:8px; box-shadow:0 2px 12px rgba(0,0,0,.06); overflow:hidden; }
        .inst-card-header { padding:1.5rem 1.75rem 1.25rem; border-bottom:1px solid var(--c-border); background:#fafbfc; }
        .inst-card-header h2 { font-size:1.15rem; margin:0 0 .2rem; font-weight:600; }
        .inst-card-header p  { margin:0; color:var(--c-muted); font-size:.9rem; }
        .inst-card-body   { padding:1.75rem; }
        .inst-card-footer { padding:1.1rem 1.75rem; border-top:1px solid var(--c-border); background:#fafbfc; display:flex; justify-content:space-between; align-items:center; }
        .form-label { font-size:.875rem; font-weight:500; margin-bottom:.35rem; }
        .form-control:focus,.form-select:focus { border-color:var(--c-primary); box-shadow:0 0 0 3px rgba(68,113,163,.15); }
        .form-text { font-size:.8rem; color:var(--c-muted); }
        .btn-primary { background:var(--c-primary); border-color:var(--c-primary); font-weight:500; }
        .btn-primary:hover { background:var(--c-primary-light); border-color:var(--c-primary-light); }
        .check-list { list-style:none; padding:0; margin:0; }
        .check-list li { display:flex; align-items:center; gap:.75rem; padding:.6rem .75rem; border-radius:6px; font-size:.875rem; margin-bottom:.3rem; }
        .check-list li.ok   { background:#f0faf5; }
        .check-list li.fail { background:#fef2f2; }
        .check-val { margin-left:auto; font-family:monospace; font-size:.8rem; color:var(--c-muted); }
        .inst-alert { display:flex; gap:.75rem; align-items:flex-start; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:6px; padding:.9rem 1rem; font-size:.875rem; margin-bottom:1.25rem; }
        .inst-alert i { font-size:1.1rem; flex-shrink:0; }
        .info-box { background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:.9rem 1rem; font-size:.85rem; color:#1e40af; display:flex; gap:.6rem; align-items:flex-start; }
        .info-box i { flex-shrink:0; }
        .section-divider { font-size:.7rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--c-muted); border-bottom:1px solid var(--c-border); padding-bottom:.4rem; margin:1.5rem 0 1rem; }
        .summary-table td:first-child { color:var(--c-muted); width:40%; padding-right:1rem; }
        .summary-table td { padding:.35rem 0; vertical-align:top; font-size:.875rem; }
        .success-icon { width:72px; height:72px; background:#f0faf5; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem; color:var(--c-success); font-size:2.2rem; }
        .beispiel-toggle { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.1rem; border:1px solid var(--c-border); border-radius:8px; cursor:pointer; transition:all .2s; background:#fafbfc; user-select:none; }
        .beispiel-toggle:hover, .beispiel-toggle.active { border-color:var(--c-primary); background:#eff6ff; }
        .beispiel-toggle-left { display:flex; align-items:center; gap:.9rem; }
        .beispiel-toggle-icon { width:40px; height:40px; border-radius:8px; background:#e8f0f8; color:var(--c-primary); display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; transition:all .2s; }
        .beispiel-toggle.active .beispiel-toggle-icon { background:var(--c-primary); color:#fff; }
        .beispiel-toggle-title { font-weight:600; font-size:.9rem; }
        .beispiel-toggle-desc  { font-size:.78rem; color:var(--c-muted); margin-top:.1rem; }
        .beispiel-switch { width:44px; height:24px; border-radius:12px; background:#cbd5e1; position:relative; flex-shrink:0; transition:background .2s; }
        .beispiel-toggle.active .beispiel-switch { background:var(--c-primary); }
        .beispiel-knob { width:18px; height:18px; border-radius:50%; background:#fff; position:absolute; top:3px; left:3px; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
        .beispiel-toggle.active .beispiel-knob { transform:translateX(20px); }
        .inst-page-footer { text-align:center; padding:1.25rem; font-size:.8rem; color:var(--c-muted); }
    </style>
</head>
<body>

<div class="inst-header">
    <?php if (file_exists(__DIR__ . '/assets/logo_full_white.png')): ?>
        <img src="assets/logo_full_white.png" alt="Syncopa">
    <?php else: ?>
        <i class="bi bi-music-note-beamed fs-3"></i>
    <?php endif; ?>
    <div>
        <h1>Installations-Assistent</h1>
        <small>Syncopa – Musikvereinsverwaltung</small>
    </div>
</div>

<div class="inst-body">
<div class="inst-container">

    <!-- Progress -->
    <div class="step-progress">
        <?php foreach ($stepNames as $i => $name): ?>
        <div class="step-item <?php if ($i < $step) echo 'done'; elseif ($i === $step) echo 'active'; ?>">
            <div class="step-circle">
                <?php if ($i < $step): ?><i class="bi bi-check"></i><?php else: echo $i + 1; endif; ?>
            </div>
            <div class="step-label"><?= h($name) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="inst-card">

    <?php if ($error): ?>
    <div style="padding:1.25rem 1.75rem 0">
        <div class="inst-alert"><i class="bi bi-exclamation-triangle-fill"></i><div><?= $error ?></div></div>
    </div>
    <?php endif; ?>

    <?php if ($step === 0): ?>
    <!-- ── SCHRITT 0: Willkommen ── -->
    <div class="inst-card-header">
        <h2><i class="bi bi-rocket-takeoff me-2" style="color:var(--c-primary)"></i>Willkommen bei Syncopa</h2>
        <p>Dieser Assistent führt Sie in wenigen Schritten durch die Erstinstallation.</p>
    </div>
    <div class="inst-card-body">
        <p class="section-divider">Systemvoraussetzungen</p>
        <ul class="check-list">
            <?php foreach ($checks as $c): ?>
            <li class="<?= $c['ok'] ? 'ok' : 'fail' ?>">
                <?php if ($c['ok']): ?><i class="bi bi-check-circle-fill text-success"></i><?php else: ?><i class="bi bi-x-circle-fill text-danger"></i><?php endif; ?>
                <span><?= h($c['label']) ?></span>
                <span class="check-val"><?= h($c['val']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$allOk): ?>
        <div class="inst-alert mt-3"><i class="bi bi-exclamation-triangle-fill"></i><div>Bitte beheben Sie die markierten Probleme, bevor Sie fortfahren.</div></div>
        <?php else: ?>
        <div class="info-box mt-3"><i class="bi bi-info-circle-fill"></i><div>Alle Voraussetzungen erfüllt. Sie können mit der Installation beginnen.</div></div>
        <?php endif; ?>
    </div>
    <div class="inst-card-footer">
        <span class="text-muted" style="font-size:.8rem"><i class="bi bi-file-earmark-lock me-1"></i>Nach der Installation wird <code>install.lock</code> erstellt</span>
        <form method="post">
            <input type="hidden" name="action" value="next0">
            <button class="btn btn-primary" <?= !$allOk ? 'disabled' : '' ?>>Weiter <i class="bi bi-arrow-right ms-1"></i></button>
        </form>
    </div>

    <?php elseif ($step === 1): ?>
    <!-- ── SCHRITT 1: Datenbank ── -->
    <div class="inst-card-header">
        <h2><i class="bi bi-database me-2" style="color:var(--c-primary)"></i>Datenbank-Verbindung</h2>
        <p>Geben Sie die MySQL/MariaDB-Zugangsdaten ein. Die Datenbank wird automatisch erstellt, falls sie nicht existiert.</p>
    </div>
    <form method="post">
    <input type="hidden" name="action" value="next1">
    <div class="inst-card-body">
        <div class="row g-3">
            <div class="col-sm-8">
                <label class="form-label">Hostname <span class="text-danger">*</span></label>
                <input type="text" name="db_host" class="form-control" value="<?= val('db_host',$post,'localhost') ?>" required>
            </div>
            <div class="col-sm-4">
                <label class="form-label">Port</label>
                <input type="number" name="db_port" class="form-control" value="<?= val('db_port',$post,'3306') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Datenbankname <span class="text-danger">*</span></label>
                <input type="text" name="db_name" class="form-control" value="<?= val('db_name',$post) ?>" required placeholder="z.B. syncopa_db">
                <div class="form-text">Wird automatisch angelegt, falls noch nicht vorhanden.</div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Benutzername <span class="text-danger">*</span></label>
                <input type="text" name="db_user" class="form-control" value="<?= val('db_user',$post) ?>" required autocomplete="username">
            </div>
            <div class="col-sm-6">
                <label class="form-label">Passwort</label>
                <div class="input-group">
                    <input type="password" name="db_pass" id="dbPass" class="form-control" value="<?= val('db_pass',$post) ?>" autocomplete="current-password">
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePw('dbPass',this)"><i class="bi bi-eye"></i></button>
                </div>
            </div>
        </div>
    </div>
    <div class="inst-card-footer">
        <a href="?back=1" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
        <button class="btn btn-primary">Verbindung testen &amp; weiter <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
    </form>

    <?php elseif ($step === 2): ?>
    <!-- ── SCHRITT 2: Anwendung ── -->
    <div class="inst-card-header">
        <h2><i class="bi bi-sliders me-2" style="color:var(--c-primary)"></i>Anwendungsdaten</h2>
        <p>Vereinsdaten und Administrator-Zugang einrichten.</p>
    </div>
    <form method="post">
    <input type="hidden" name="action" value="next2">
    <div class="inst-card-body">
        <p class="section-divider">Verein</p>
        <div class="row g-3">
            <div class="col-sm-8">
                <label class="form-label">Vereinsname <span class="text-danger">*</span></label>
                <input type="text" name="verein_name" class="form-control" value="<?= val('verein_name',$post) ?>" required placeholder="z.B. Musikverein Musterstadt">
            </div>
            <div class="col-sm-4">
                <label class="form-label">Ort</label>
                <input type="text" name="verein_ort" class="form-control" value="<?= val('verein_ort',$post) ?>" placeholder="z.B. Musterstadt">
            </div>
            <div class="col-12">
                <label class="form-label">URL der Anwendung <span class="text-danger">*</span></label>
                <input type="url" name="base_url" class="form-control" value="<?= val('base_url',$post,baseUrl()) ?>" required>
                <div class="form-text">Vollständige URL ohne abschließenden Schrägstrich.</div>
            </div>
        </div>
        <p class="section-divider">Administrator-Konto</p>
        <div class="row g-3">
            <div class="col-sm-6">
                <label class="form-label">Benutzername <span class="text-danger">*</span></label>
                <input type="text" name="admin_benutzername" class="form-control" value="<?= val('admin_benutzername',$post,'admin') ?>" required autocomplete="off">
            </div>
            <div class="col-sm-6">
                <label class="form-label">E-Mail-Adresse</label>
                <input type="email" name="admin_email" class="form-control" value="<?= val('admin_email',$post) ?>" autocomplete="off">
            </div>
            <div class="col-sm-6">
                <label class="form-label">Passwort <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" name="admin_passwort" id="pw1" class="form-control" required minlength="8" placeholder="Min. 8 Zeichen" autocomplete="new-password">
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePw('pw1',this)"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Passwort wiederholen <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" name="admin_passwort2" id="pw2" class="form-control" required minlength="8" autocomplete="new-password">
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePw('pw2',this)"><i class="bi bi-eye"></i></button>
                </div>
            </div>
        </div>
    </div>
    <div class="inst-card-footer">
        <a href="?back=1" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
        <button class="btn btn-primary">Weiter <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
    </form>

    <?php elseif ($step === 3): ?>
    <!-- ── SCHRITT 3: Integrationen ── -->
    <div class="inst-card-header">
        <h2><i class="bi bi-puzzle me-2" style="color:var(--c-primary)"></i>Optionale Integrationen</h2>
        <p>Diese Einstellungen können jederzeit in <code>config.php</code> nachgetragen oder geändert werden.</p>
    </div>
    <form method="post">
    <input type="hidden" name="action" value="next3">
    <div class="inst-card-body">

        <p class="section-divider">Google OAuth Login</p>
        <div class="row g-3">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="google_oauth_enabled" id="googleOauth" value="1" <?= !empty($post['google_oauth_enabled']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="googleOauth">Google OAuth Login aktivieren</label>
                </div>
                <div class="form-text">Client-ID erstellen: <a href="https://console.cloud.google.com/apis/credentials" target="_blank">console.cloud.google.com</a></div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Google Client-ID</label>
                <input type="text" name="google_client_id" class="form-control" value="<?= val('google_client_id',$post) ?>" placeholder="xxx.apps.googleusercontent.com">
            </div>
            <div class="col-sm-6">
                <label class="form-label">Google Client-Secret</label>
                <input type="text" name="google_client_secret" class="form-control" value="<?= val('google_client_secret',$post) ?>" placeholder="GOCSPX-...">
            </div>
        </div>

        <p class="section-divider">Google Calendar API</p>
        <div class="row g-3">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="google_calendar_enabled" id="googleCal" value="1" <?= !empty($post['google_calendar_enabled']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="googleCal">Google Calendar Integration aktivieren</label>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">API Key</label>
                <input type="text" name="google_calendar_api_key" class="form-control" value="<?= val('google_calendar_api_key',$post) ?>" placeholder="AIzaSy...">
            </div>
            <div class="col-sm-6">
                <label class="form-label">Calendar ID</label>
                <input type="text" name="google_calendar_id" class="form-control" value="<?= val('google_calendar_id',$post) ?>" placeholder="xyz@group.calendar.google.com">
            </div>
        </div>

        <p class="section-divider">OCR Space API <span class="badge bg-warning text-dark ms-1" style="font-size:.7rem">Für PDF-Splittung erforderlich</span></p>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">OCR Space API Key</label>
                <input type="text" name="ocr_api_key" class="form-control" value="<?= val('ocr_api_key',$post) ?>" placeholder="K12345...">
                <div class="form-text">Kostenlosen Key: <a href="https://ocr.space/ocrapi" target="_blank">ocr.space/ocrapi</a> → „Get API Key FREE"</div>
            </div>
        </div>

        <p class="section-divider">Nutzungsstatistik</p>
        <div class="row g-3">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="telemetry_enabled" id="telemetryEnabled" value="1" <?= !empty($post['telemetry_enabled']) ? 'checked' : 'checked' ?>>
                    <label class="form-check-label fw-semibold" for="telemetryEnabled">Anonyme Nutzungsstatistik senden (empfohlen)</label>
                </div>
                <div class="form-text mt-1">
                    Hilft dem Entwickler, die App zu verbessern. Es werden ausschließlich übertragen:
                    eine zufällige Installations-ID, die Versionsnummer und der Vereinsname.
                    Kein Tracking von Personen oder Inhalten. Kann jederzeit in den Einstellungen deaktiviert werden.
                </div>
            </div>
        </div>

        <p class="section-divider">E-Mail (SMTP)</p>
        <div class="row g-3">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="email_enabled" id="emailEnabled" value="1" <?= !empty($post['email_enabled']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="emailEnabled">E-Mail-Versand aktivieren</label>
                </div>
            </div>
            <div class="col-sm-8">
                <label class="form-label">SMTP Host</label>
                <input type="text" name="email_smtp_host" class="form-control" value="<?= val('email_smtp_host',$post,'smtp.example.com') ?>" placeholder="smtp.example.com">
            </div>
            <div class="col-sm-4">
                <label class="form-label">Port</label>
                <input type="number" name="email_smtp_port" class="form-control" value="<?= val('email_smtp_port',$post,'587') ?>">
            </div>
            <div class="col-sm-6">
                <label class="form-label">SMTP Benutzer</label>
                <input type="text" name="email_smtp_user" class="form-control" value="<?= val('email_smtp_user',$post) ?>" placeholder="user@example.com">
            </div>
            <div class="col-sm-6">
                <label class="form-label">SMTP Passwort</label>
                <div class="input-group">
                    <input type="password" id="smtpPw" name="email_smtp_pass" class="form-control" value="<?= val('email_smtp_pass',$post) ?>">
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePw('smtpPw',this)"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label">Absender-Adresse</label>
                <input type="email" name="email_from" class="form-control" value="<?= val('email_from',$post,'noreply@musikverein.at') ?>">
            </div>
            <div class="col-sm-6">
                <label class="form-label">Absender-Name</label>
                <input type="text" name="email_from_name" class="form-control" value="<?= val('email_from_name',$post,'Musikverein Verwaltung') ?>">
            </div>
        </div>

    </div>
    <div class="inst-card-footer">
        <a href="?back=1" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
        <button class="btn btn-primary">Weiter <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
    </form>

    <?php elseif ($step === 4): ?>
    <!-- ── SCHRITT 4: Bestätigung ── -->
    <div class="inst-card-header">
        <h2><i class="bi bi-list-check me-2" style="color:var(--c-primary)"></i>Zusammenfassung</h2>
        <p>Prüfen Sie die Einstellungen und starten Sie die Installation.</p>
    </div>
    <form method="post">
    <input type="hidden" name="action" value="install">
    <input type="hidden" name="beispieldaten" id="beispieldatenHidden" value="<?= val('beispieldaten',$post,'0') ?>">
    <div class="inst-card-body">
        <p class="section-divider">Datenbank</p>
        <table class="summary-table w-100">
            <tr><td>Host</td><td><strong><?= val('db_host',$post) ?></strong></td></tr>
            <tr><td>Datenbankname</td><td><strong><?= val('db_name',$post) ?></strong></td></tr>
            <tr><td>Benutzer</td><td><strong><?= val('db_user',$post) ?></strong></td></tr>
        </table>
        <p class="section-divider">Anwendung</p>
        <table class="summary-table w-100">
            <tr><td>Vereinsname</td><td><strong><?= val('verein_name',$post) ?></strong></td></tr>
            <tr><td>URL</td><td><strong><?= val('base_url',$post) ?></strong></td></tr>
            <tr><td>Admin</td><td><strong><?= val('admin_benutzername',$post) ?></strong></td></tr>
        </table>
        <?php if (!empty($post['google_oauth_enabled']) || !empty($post['google_calendar_enabled']) || !empty($post['ocr_api_key']) || !empty($post['email_enabled'])): ?>
        <p class="section-divider">Integrationen</p>
        <table class="summary-table w-100">
            <?php if (!empty($post['google_oauth_enabled'])): ?><tr><td>Google OAuth</td><td><strong>aktiviert</strong></td></tr><?php endif; ?>
            <?php if (!empty($post['google_calendar_enabled'])): ?><tr><td>Google Calendar</td><td><strong>aktiviert</strong></td></tr><?php endif; ?>
            <?php if (!empty($post['ocr_api_key'])): ?><tr><td>OCR Space</td><td><strong>konfiguriert</strong></td></tr><?php endif; ?>
            <?php if (!empty($post['email_enabled'])): ?><tr><td>E-Mail (SMTP)</td><td><strong>aktiviert</strong></td></tr><?php endif; ?>
        </table>
        <?php endif; ?>

        <p class="section-divider">Optionen</p>
        <div class="beispiel-toggle" id="beispielToggle" onclick="toggleBeispieldaten()">
            <div class="beispiel-toggle-left">
                <div class="beispiel-toggle-icon"><i class="bi bi-database-add"></i></div>
                <div>
                    <div class="beispiel-toggle-title">Beispieldaten laden</div>
                    <div class="beispiel-toggle-desc">12 Mitglieder, Instrumente, Ausrückungen, Noten und Finanzbuchungen als Orientierungshilfe</div>
                </div>
            </div>
            <div class="beispiel-switch"><div class="beispiel-knob"></div></div>
        </div>

        <div class="info-box mt-3">
            <i class="bi bi-info-circle-fill"></i>
            <div>Datenbanktabellen werden angelegt, Grunddaten eingespielt, <code>config.php</code> wird geschrieben und <code>install.lock</code> sperrt den Installer danach permanent.</div>
        </div>
    </div>
    <div class="inst-card-footer">
        <a href="?back=1" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
        <button class="btn btn-success" id="installBtn"
            onclick="this.disabled=true;this.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Wird installiert\u2026';this.form.submit()">
            <i class="bi bi-download me-1"></i>Installation starten
        </button>
    </div>
    </form>

    <?php elseif ($step === 5): ?>
    <!-- ── SCHRITT 5: Fertig ── -->
    <div class="inst-card-body text-center py-5">
        <div class="success-icon"><i class="bi bi-check-lg"></i></div>
        <h2 class="mb-2">Installation abgeschlossen!</h2>
        <p class="text-muted mb-4">Syncopa wurde erfolgreich eingerichtet.</p>
        <div class="info-box text-start mb-4" style="max-width:380px;margin:0 auto 1.5rem">
            <i class="bi bi-person-circle"></i>
            <div><strong>Ihre Admin-Zugangsdaten</strong><br>
            Benutzername: <code><?= h($_SESSION['install_done_user'] ?? '') ?></code><br>
            <?php if (!empty($_SESSION['install_done_email'])): ?>
            E-Mail: <code><?= h($_SESSION['install_done_email']) ?></code>
            <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="index.php" class="btn btn-primary btn-lg px-4"><i class="bi bi-house me-2"></i>Zur Anwendung</a>
            <a href="login.php" class="btn btn-outline-primary btn-lg px-4"><i class="bi bi-box-arrow-in-right me-2"></i>Anmelden</a>
        </div>
        <p class="text-muted mt-4" style="font-size:.8rem">
            <i class="bi bi-shield-check me-1"></i><code>install.lock</code> wurde erstellt – dieser Installer ist jetzt gesperrt.
        </p>
    </div>

    <?php endif; ?>

    </div><!-- /.inst-card -->
</div><!-- /.inst-container -->
</div><!-- /.inst-body -->

<div class="inst-page-footer">
    Syncopa &copy; <?= date('Y') ?> Johann Danner &nbsp;&middot;&nbsp; Installations-Assistent v1.2
</div>

<script>
function togglePw(id, btn) {
    var inp = document.getElementById(id);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.innerHTML = '<i class="bi bi-eye-slash"></i>';
    } else {
        inp.type = 'password';
        btn.innerHTML = '<i class="bi bi-eye"></i>';
    }
}
function toggleBeispieldaten() {
    var hidden = document.getElementById('beispieldatenHidden');
    var toggle = document.getElementById('beispielToggle');
    if (toggle.classList.contains('active')) {
        toggle.classList.remove('active');
        hidden.value = '0';
    } else {
        toggle.classList.add('active');
        hidden.value = '1';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    var hidden = document.getElementById('beispieldatenHidden');
    if (hidden && hidden.value === '1') {
        var t = document.getElementById('beispielToggle');
        if (t) t.classList.add('active');
    }
});
</script>
</body>
</html>
