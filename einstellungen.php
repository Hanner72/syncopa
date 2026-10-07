<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (Session::getRole() !== 'admin') {
    Session::setFlashMessage('danger', 'Nur Administratoren haben Zugriff');
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

// Einstellungen laden
$einstellungen = $db->fetchAll("SELECT * FROM einstellungen");
$settings = [];
foreach ($einstellungen as $e) {
    $settings[$e['schluessel']] = $e['wert'];
}

// Speichern
$bmvStandardUrl = 'https://datenservice.blasmusik.at';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bmvLand     = strtoupper(trim($_POST['bmv_land'] ?? ''));
    $bmvBezirk   = trim($_POST['bmv_bezirk'] ?? '');
    $bmvVerein   = trim($_POST['bmv_verein'] ?? '');
    $bmvEmail    = trim($_POST['bmv_email'] ?? '');
    $bmvUrl      = trim($_POST['bmv_api_url'] ?? '');
    $bmvPasswort = $_POST['bmv_passwort'] ?? '';

    $bmvFehler = null;
    if ($bmvLand !== '' && !preg_match('/^[A-Z]{2}$/', $bmvLand)) {
        $bmvFehler = 'BMV Land: bitte genau 2 Buchstaben eingeben (z.B. ST).';
    } elseif ($bmvBezirk !== '' && !preg_match('/^\d{1,2}$/', $bmvBezirk)) {
        $bmvFehler = 'BMV Bezirk: bitte eine 1- bis 2-stellige Zahl eingeben.';
    } elseif ($bmvVerein !== '' && !preg_match('/^\d{1,2}$/', $bmvVerein)) {
        $bmvFehler = 'BMV Verein: bitte eine 1- bis 2-stellige Zahl eingeben.';
    } elseif (($bmvLand !== '' || $bmvBezirk !== '' || $bmvVerein !== '') && ($bmvLand === '' || $bmvBezirk === '' || $bmvVerein === '')) {
        $bmvFehler = 'BMV Zugangsdaten: Land, Bezirk und Verein müssen vollständig ausgefüllt sein.';
    } elseif ($bmvEmail !== '' && !filter_var($bmvEmail, FILTER_VALIDATE_EMAIL)) {
        $bmvFehler = 'BMV E-Mail: die Adresse ist ungültig.';
    } elseif ($bmvUrl !== '' && !filter_var($bmvUrl, FILTER_VALIDATE_URL)) {
        $bmvFehler = 'BMV API-Link: die Adresse ist ungültig.';
    }

    if ($bmvFehler) {
        $error = $bmvFehler;
        $settings['bmv_land']    = $bmvLand;
        $settings['bmv_bezirk']  = $bmvBezirk;
        $settings['bmv_verein']  = $bmvVerein;
        $settings['bmv_email']   = $bmvEmail;
        $settings['bmv_api_url'] = $bmvUrl;
    } else {
        $_POST['bmv_land']    = $bmvLand;
        $_POST['bmv_bezirk']  = $bmvBezirk !== '' ? str_pad($bmvBezirk, 2, '0', STR_PAD_LEFT) : '';
        $_POST['bmv_verein']  = $bmvVerein !== '' ? str_pad($bmvVerein, 2, '0', STR_PAD_LEFT) : '';
        $_POST['bmv_api_url'] = $bmvUrl !== '' ? rtrim($bmvUrl, '/') : $bmvStandardUrl;
        if ($bmvPasswort === '') {
            unset($_POST['bmv_passwort']); // leer = gespeichertes Passwort behalten
        }
    }
}

// Geänderte Vereinsdaten zuerst in den BMV-Datenservice schreiben; schlägt das fehl, wird nichts gespeichert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    $vereinSchluessel = ['verein_name', 'verein_adresse', 'verein_plz', 'verein_ort', 'verein_gemeinde',
        'verein_email', 'verein_telefon', 'verein_website', 'verein_zvr_nr', 'verein_gruendungsjahr',
        'verein_kontaktperson', 'verein_kontoname', 'verein_iban', 'verein_bic'];
    $vereinGeaendert = [];
    foreach ($vereinSchluessel as $schluessel) {
        $neu = trim((string)($_POST[$schluessel] ?? ''));
        if ($neu !== trim((string)($settings[$schluessel] ?? ''))) {
            $vereinGeaendert[$schluessel] = $neu;
        }
    }

    $bmvApi = new BmvApi();
    if ($vereinGeaendert && $bmvApi->istKonfiguriert()) {
        $bmvNr = (int)($settings['bmv_verein_nr'] ?? 0);
        if ($bmvNr <= 0) {
            $error = 'Vereinsdaten können erst nach der ersten Synchronisation mit dem BMV geändert werden. Bitte zuerst „Mit BMV synchronisieren" ausführen.';
        } else {
            try {
                $bmvApi->vereinsdatenAktualisieren($bmvNr, BmvApi::einstellungenZuVereinsdaten($vereinGeaendert));
            } catch (RuntimeException $e) {
                $error = 'Nicht gespeichert, da der BMV-Datenservice die Änderung nicht übernommen hat: ' . $e->getMessage();
            }
        }
        if (isset($error)) {
            foreach ($vereinGeaendert as $schluessel => $wert) {
                $settings[$schluessel] = $wert;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    try {
        // Normale Einstellungen
        foreach ($_POST as $key => $value) {
            if ($key !== 'submit') {
                $db->execute(
                    "INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?) ON DUPLICATE KEY UPDATE wert = ?",
                    [$key, $value, $value]
                );
            }
        }

        // Checkboxen (die nicht in POST sind wenn nicht angeklickt)
        $checkboxen = ['beitrag_aktiv', 'beitrag_passiv', 'beitrag_ehrenmitglied', 'beitrag_ausgetreten', 'telemetry_enabled'];
        foreach ($checkboxen as $checkbox) {
            $wert = isset($_POST[$checkbox]) ? '1' : '0';
            $db->execute(
                "INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?) ON DUPLICATE KEY UPDATE wert = ?",
                [$checkbox, $wert, $wert]
            );
        }

        Session::setFlashMessage('success', 'Einstellungen gespeichert');
        header('Location: einstellungen.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-gear"></i> Einstellungen</h1>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST">
    <!-- Allgemeine Einstellungen -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Allgemeine Einstellungen</h5>
        </div>
        <div class="card-body">
            <?php
            $bmvVereinBereit = !empty($settings['bmv_land']) && !empty($settings['bmv_bezirk'])
                && !empty($settings['bmv_verein']) && !empty($settings['bmv_passwort']);
            ?>
            <div class="alert alert-info small mb-3">
                <i class="bi bi-info-circle"></i>
                Die Vereinsdaten werden aus dem BMV-Datenservice übernommen. Jede Änderung hier wird beim Speichern
                zusätzlich an den BMV-Datenservice übertragen. Schlägt die Übertragung fehl, wird nichts gespeichert.
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="text-muted small">
                    <?php if (!empty($settings['bmv_letzte_sync_verein'])): ?>
                    Zuletzt aus dem BMV-Datenservice synchronisiert: <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($settings['bmv_letzte_sync_verein']))); ?>
                    <?php else: ?>
                    Die Vereinsdaten können aus dem BMV-Datenservice übernommen werden.
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnBmvVereinSync"
                        <?php echo $bmvVereinBereit ? '' : 'disabled title="Zuerst die BMV-Zugangsdaten eintragen und speichern"'; ?>>
                    <i class="bi bi-arrow-repeat"></i> Mit BMV synchronisieren
                </button>
            </div>
            <div id="bmvVereinSyncMeldung" class="alert d-none mb-3"></div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="verein_name" class="form-label">Vereinsname</label>
                    <input type="text" class="form-control" id="verein_name" name="verein_name"
                           value="<?php echo htmlspecialchars($settings['verein_name'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="verein_kontaktperson" class="form-label">Kontaktperson</label>
                    <input type="text" class="form-control" id="verein_kontaktperson" name="verein_kontaktperson"
                           value="<?php echo htmlspecialchars($settings['verein_kontaktperson'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="verein_adresse" class="form-label">Straße</label>
                    <input type="text" class="form-control" id="verein_adresse" name="verein_adresse"
                           value="<?php echo htmlspecialchars($settings['verein_adresse'] ?? ''); ?>">
                </div>

                <div class="col-md-2 mb-3">
                    <label for="verein_plz" class="form-label">PLZ</label>
                    <input type="text" class="form-control" id="verein_plz" name="verein_plz"
                           value="<?php echo htmlspecialchars($settings['verein_plz'] ?? ''); ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="verein_ort" class="form-label">Ort</label>
                    <input type="text" class="form-control" id="verein_ort" name="verein_ort"
                           value="<?php echo htmlspecialchars($settings['verein_ort'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="verein_gemeinde" class="form-label">Gemeinde</label>
                    <input type="text" class="form-control" id="verein_gemeinde" name="verein_gemeinde"
                           value="<?php echo htmlspecialchars($settings['verein_gemeinde'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="verein_email" class="form-label">E-Mail</label>
                    <input type="email" class="form-control" id="verein_email" name="verein_email"
                           value="<?php echo htmlspecialchars($settings['verein_email'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="verein_telefon" class="form-label">Telefon</label>
                    <input type="text" class="form-control" id="verein_telefon" name="verein_telefon"
                           value="<?php echo htmlspecialchars($settings['verein_telefon'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="verein_website" class="form-label">Website</label>
                    <input type="text" class="form-control" id="verein_website" name="verein_website"
                           value="<?php echo htmlspecialchars($settings['verein_website'] ?? ''); ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="verein_zvr_nr" class="form-label">ZVR-Nummer</label>
                    <input type="text" class="form-control" id="verein_zvr_nr" name="verein_zvr_nr"
                           value="<?php echo htmlspecialchars($settings['verein_zvr_nr'] ?? ''); ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="verein_gruendungsjahr" class="form-label">Gründungsjahr</label>
                    <input type="text" class="form-control" id="verein_gruendungsjahr" name="verein_gruendungsjahr"
                           value="<?php echo htmlspecialchars($settings['verein_gruendungsjahr'] ?? ''); ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="verein_kontoname" class="form-label">Kontobezeichnung</label>
                    <input type="text" class="form-control" id="verein_kontoname" name="verein_kontoname"
                           value="<?php echo htmlspecialchars($settings['verein_kontoname'] ?? ''); ?>">
                </div>

                <div class="col-md-8 mb-3">
                    <label for="verein_iban" class="form-label">IBAN</label>
                    <input type="text" class="form-control" id="verein_iban" name="verein_iban"
                           value="<?php echo htmlspecialchars($settings['verein_iban'] ?? ''); ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="verein_bic" class="form-label">BIC</label>
                    <input type="text" class="form-control" id="verein_bic" name="verein_bic"
                           value="<?php echo htmlspecialchars($settings['verein_bic'] ?? ''); ?>">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mitgliedsbeiträge -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Mitgliedsbeiträge</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <strong>Hinweis:</strong> Hier konfigurierst du die Mitgliedsbeiträge nach Status.
                Unter <a href="beitraege_verwalten.php" class="alert-link">Beiträge verwalten</a> 
                kannst du dann automatisch Beiträge für alle Mitglieder generieren.
            </div>
            
            <div class="mb-3">
                <label for="mitgliedsbeitrag_jahr" class="form-label">
                    Beitrag für aktive Mitglieder pro Jahr (€)
                </label>
                <input type="number" class="form-control" id="mitgliedsbeitrag_jahr" name="mitgliedsbeitrag_jahr" 
                       step="0.01" value="<?php echo htmlspecialchars($settings['mitgliedsbeitrag_jahr'] ?? '120.00'); ?>">
                <small class="text-muted">Dieser Betrag gilt für aktive Mitglieder (Standard)</small>
            </div>
            
            <hr>
            <h6>Beitragspflicht nach Mitgliederstatus</h6>
            <p class="text-muted small">Wähle aus, welche Mitgliederkategorien Beiträge zahlen müssen:</p>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="beitrag_aktiv" name="beitrag_aktiv" 
                               <?php echo ($settings['beitrag_aktiv'] ?? '1') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="beitrag_aktiv">
                            <strong>Aktive Mitglieder</strong>
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="beitrag_passiv" name="beitrag_passiv" 
                               <?php echo ($settings['beitrag_passiv'] ?? '0') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="beitrag_passiv">
                            <strong>Passive Mitglieder</strong>
                        </label>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="beitrag_ehrenmitglied" name="beitrag_ehrenmitglied" 
                               <?php echo ($settings['beitrag_ehrenmitglied'] ?? '0') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="beitrag_ehrenmitglied">
                            <strong>Ehrenmitglieder</strong>
                        </label>
                    </div>
                    
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="beitrag_ausgetreten" name="beitrag_ausgetreten" 
                               <?php echo ($settings['beitrag_ausgetreten'] ?? '0') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="beitrag_ausgetreten">
                            <strong>Ausgetretene Mitglieder</strong>
                        </label>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="beitrag_passiv_betrag" class="form-label">Beitrag für Passive (€)</label>
                    <input type="number" class="form-control" id="beitrag_passiv_betrag" name="beitrag_passiv_betrag" 
                           step="0.01" value="<?php echo htmlspecialchars($settings['beitrag_passiv_betrag'] ?? '60.00'); ?>">
                    <small class="text-muted">Falls abweichend vom Standardbeitrag</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="beitrag_faelligkeit_monat" class="form-label">Fälligkeit im Monat</label>
                    <select class="form-select" id="beitrag_faelligkeit_monat" name="beitrag_faelligkeit_monat">
                        <?php
                        $monate = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
                        $ausgewaehlt = $settings['beitrag_faelligkeit_monat'] ?? '1';
                        for ($i = 1; $i <= 12; $i++):
                        ?>
                        <option value="<?php echo $i; ?>" <?php echo $ausgewaehlt == $i ? 'selected' : ''; ?>>
                            <?php echo $monate[$i-1]; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">E-Mail Einstellungen</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email_smtp_host" class="form-label">SMTP Server</label>
                    <input type="text" class="form-control" id="email_smtp_host" name="email_smtp_host" 
                           value="<?php echo htmlspecialchars($settings['email_smtp_host'] ?? ''); ?>"
                           placeholder="smtp.gmail.com">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="email_smtp_port" class="form-label">SMTP Port</label>
                    <input type="number" class="form-control" id="email_smtp_port" name="email_smtp_port" 
                           value="<?php echo htmlspecialchars($settings['email_smtp_port'] ?? '587'); ?>">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="email_from" class="form-label">Absender E-Mail</label>
                <input type="email" class="form-control" id="email_from" name="email_from" 
                       value="<?php echo htmlspecialchars($settings['email_from'] ?? ''); ?>"
                       placeholder="verein@beispiel.at">
            </div>
        </div>
    </div>
    
    <!-- Google Calendar -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Google Calendar Integration</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                Um Google Calendar zu nutzen, benötigen Sie einen API-Schlüssel von der Google Cloud Console.
            </div>
            
            <div class="mb-3">
                <label for="google_calendar_api_key" class="form-label">API-Schlüssel</label>
                <input type="text" class="form-control" id="google_calendar_api_key" name="google_calendar_api_key" 
                       value="<?php echo htmlspecialchars($settings['google_calendar_api_key'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="google_calendar_id" class="form-label">Kalender-ID</label>
                <input type="text" class="form-control" id="google_calendar_id" name="google_calendar_id" 
                       value="<?php echo htmlspecialchars($settings['google_calendar_id'] ?? ''); ?>"
                       placeholder="ihre-kalender-id@group.calendar.google.com">
            </div>
        </div>
    </div>
    
    <!-- BMV Synchronisation -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">BMV Synchronisation Zugangsdaten</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Zugangsdaten für den Datenservice des Blasmusikverbands. Ohne Eingabe funktioniert Syncopa wie bisher,
                es wird dann nichts synchronisiert.
            </p>

            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="bmv_api_url" class="form-label">API-Link</label>
                    <input type="url" class="form-control" id="bmv_api_url" name="bmv_api_url"
                           value="<?php echo htmlspecialchars(($settings['bmv_api_url'] ?? '') ?: $bmvStandardUrl); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="bmv_email" class="form-label">E-Mail</label>
                    <input type="email" class="form-control" id="bmv_email" name="bmv_email" autocomplete="off"
                           value="<?php echo htmlspecialchars($settings['bmv_email'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="bmv_passwort" class="form-label">Passwort</label>
                    <input type="password" class="form-control" id="bmv_passwort" name="bmv_passwort" autocomplete="new-password"
                           placeholder="<?php echo !empty($settings['bmv_passwort']) ? 'gespeichert – leer lassen zum Beibehalten' : ''; ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label for="bmv_land" class="form-label">Land</label>
                    <input type="text" class="form-control text-uppercase" id="bmv_land" name="bmv_land"
                           maxlength="2" placeholder="ST"
                           value="<?php echo htmlspecialchars($settings['bmv_land'] ?? ''); ?>">
                    <small class="text-muted">2 Buchstaben, z.B. ST</small>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="bmv_bezirk" class="form-label">Bezirk</label>
                    <input type="text" class="form-control" id="bmv_bezirk" name="bmv_bezirk"
                           inputmode="numeric" maxlength="2" placeholder="16"
                           value="<?php echo htmlspecialchars($settings['bmv_bezirk'] ?? ''); ?>">
                    <small class="text-muted">2-stellige Zahl, z.B. 16</small>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="bmv_verein" class="form-label">Verein</label>
                    <input type="text" class="form-control" id="bmv_verein" name="bmv_verein"
                           inputmode="numeric" maxlength="2" placeholder="13"
                           value="<?php echo htmlspecialchars($settings['bmv_verein'] ?? ''); ?>">
                    <small class="text-muted">2-stellige Zahl, z.B. 13</small>
                </div>
            </div>

            <div class="alert alert-secondary mb-3">
                <i class="bi bi-person-badge"></i>
                Vereinskennung (Teil des API-Logins): <strong id="bmv_login_vorschau">–</strong>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="bmv_mitglieder_vorschau.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-eye"></i> BMV-Mitglieder-Vorschau anzeigen
                </a>
                <span class="text-muted small">Nur lesend, es wird nichts geändert.</span>
            </div>
        </div>
    </div>
    <script>
    (function() {
        var land   = document.getElementById('bmv_land');
        var bezirk = document.getElementById('bmv_bezirk');
        var verein = document.getElementById('bmv_verein');
        var vorschau = document.getElementById('bmv_login_vorschau');
        function aktualisieren() {
            var l = land.value.trim().toUpperCase();
            var b = bezirk.value.trim();
            var v = verein.value.trim();
            if (!l || !b || !v) { vorschau.textContent = '–'; return; }
            vorschau.textContent = l + '-' + b.padStart(2, '0') + '-' + v.padStart(3, '0');
        }
        [land, bezirk, verein].forEach(function(el) { el.addEventListener('input', aktualisieren); });
        aktualisieren();
    })();
    </script>

    <!-- Telemetrie -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Nutzungsstatistik</h5>
        </div>
        <div class="card-body">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="telemetry_enabled" name="telemetry_enabled"
                       value="1" <?php echo ($settings['telemetry_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="telemetry_enabled">
                    Anonyme Nutzungsstatistik senden
                </label>
            </div>
            <div class="text-muted small mt-1">
                Sendet einmal täglich die Versionsnummer und den Vereinsnamen anonym an den Syncopa-Server.
                Keine personenbezogenen Daten. Kann jederzeit deaktiviert werden.
            </div>
        </div>
    </div>

    <!-- Noten / PDF-Aufteilung -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Noten-Aufteilung</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">Definiere, welche Instrumente beim automatischen PDF-Aufteilen erkannt werden sollen und in welcher Reihenfolge die Muster geprüft werden.</p>
            <a href="noten_instrumente.php" class="btn btn-outline-primary">
                <i class="bi bi-music-note"></i> Instrument-Pattern verwalten
            </a>
        </div>
    </div>

    <!-- Rollen & Berechtigungen -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Rollen &amp; Berechtigungen</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">Verwalte Rollen und lege fest, welche Rolle auf welche Module zugreifen darf.</p>
            <div class="d-flex gap-2 flex-wrap">
                <a href="rollen.php" class="btn btn-outline-secondary">
                    <i class="bi bi-shield-lock"></i> Rollen verwalten
                </a>
                <a href="berechtigungen_matrix.php" class="btn btn-outline-primary">
                    <i class="bi bi-grid-3x3-gap"></i> Berechtigungs-Matrix
                </a>
            </div>
        </div>
    </div>

    <!-- System-Informationen -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">System-Informationen</h5>
            <a href="update.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-cloud-download"></i> System-Update
            </a>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">PHP Version:</dt>
                <dd class="col-sm-9"><?php echo PHP_VERSION; ?></dd>

                <dt class="col-sm-3">MySQL Version:</dt>
                <dd class="col-sm-9">
                    <?php
                    $version = $db->fetchOne("SELECT VERSION() as version");
                    echo htmlspecialchars($version['version']);
                    ?>
                </dd>

                <dt class="col-sm-3">Anwendungs-Version:</dt>
                <dd class="col-sm-9">
                    <?php echo APP_VERSION; ?>
                    <a href="update.php" class="ms-2 badge bg-primary text-decoration-none">
                        <i class="bi bi-cloud-download"></i> Update prüfen
                    </a>
                </dd>

                <dt class="col-sm-3">Upload-Verzeichnis:</dt>
                <dd class="col-sm-9">
                    <?php echo UPLOAD_DIR; ?>
                    <?php if (is_writable(UPLOAD_DIR)): ?>
                    <span class="badge bg-success">Beschreibbar</span>
                    <?php else: ?>
                    <span class="badge bg-danger">Nicht beschreibbar</span>
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    </div>
    
    <div class="d-flex justify-content-end">
        <button type="submit" name="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-save"></i> Einstellungen speichern
        </button>
    </div>
</form>

<script>
(function() {
    var btn     = document.getElementById('btnBmvVereinSync');
    var meldung = document.getElementById('bmvVereinSyncMeldung');
    if (!btn) return;

    function zeige(typ, text) {
        meldung.className = 'alert alert-' + typ + ' mb-3';
        meldung.textContent = text;
    }

    btn.addEventListener('click', function() {
        btn.disabled = true;
        var originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Synchronisiere…';
        meldung.classList.add('d-none');

        fetch('api/bmv_verein_sync.php', { method: 'POST' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.success) {
                    zeige('danger', d.error || 'Synchronisation fehlgeschlagen.');
                    return;
                }
                Object.keys(d.werte).forEach(function(key) {
                    var el = document.getElementById(key);
                    if (el && key !== 'bmv_letzte_sync_verein') el.value = d.werte[key];
                });
                zeige('success', 'Vereinsdaten wurden aus dem BMV-Datenservice übernommen und gespeichert.');
            })
            .catch(function() {
                zeige('danger', 'Synchronisation fehlgeschlagen: keine Verbindung zum Server.');
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                meldung.classList.remove('d-none');
            });
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
