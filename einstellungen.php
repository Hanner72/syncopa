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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $checkboxen = ['beitrag_aktiv', 'beitrag_passiv', 'beitrag_ehrenmitglied', 'beitrag_ausgetreten'];
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
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="verein_name" class="form-label">Vereinsname</label>
                    <input type="text" class="form-control" id="verein_name" name="verein_name" 
                           value="<?php echo htmlspecialchars($settings['verein_name'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="verein_ort" class="form-label">Ort</label>
                    <input type="text" class="form-control" id="verein_ort" name="verein_ort" 
                           value="<?php echo htmlspecialchars($settings['verein_ort'] ?? ''); ?>">
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
    
    <!-- System-Informationen -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">System-Informationen</h5>
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
                <dd class="col-sm-9"><?php echo APP_VERSION; ?></dd>
                
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

<?php include 'includes/footer.php'; ?>
