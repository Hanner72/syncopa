<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Kalender-URL generieren
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
$calendarUrl = $baseUrl . '/kalender_export.php';

// Webcal URL (für direktes Abonnieren)
$webcalUrl = str_replace(['http://', 'https://'], 'webcal://', $calendarUrl);

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2"><i class="bi bi-calendar-check"></i> Kalender-Abonnement</h1>
            <div>
                <a href="kalender_vorschau.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-eye"></i> Vorschau
                </a>
                <a href="ausrueckungen.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Zurück
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Schnell-Abonnement -->
    <div class="col-md-12 mb-4">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-lightning-fill"></i> Schnell-Abonnement</h5>
            </div>
            <div class="card-body">
                <p class="lead">Klicke auf den Button um den Kalender zu deinem Kalender hinzuzufügen:</p>
                
                <div class="d-grid gap-2">
                    <a href="<?php echo htmlspecialchars($webcalUrl); ?>" class="btn btn-lg btn-primary">
                        <i class="bi bi-calendar-plus"></i> Kalender abonnieren
                    </a>
                </div>
                
                <div class="alert alert-info mt-3 mb-0">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Hinweis:</strong> Der Kalender wird automatisch aktualisiert, wenn neue Ausrückungen 
                    hinzugefügt oder geändert werden. Du musst nichts manuell aktualisieren!
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kalender-URL kopieren -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-link-45deg"></i> Kalender-URL</h5>
            </div>
            <div class="card-body">
                <p>Kopiere diese URL für manuelle Einrichtung:</p>
                
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="calendarUrl" 
                           value="<?php echo htmlspecialchars($calendarUrl); ?>" readonly>
                    <button class="btn btn-outline-primary" type="button" onclick="copyCalendarUrl()">
                        <i class="bi bi-clipboard"></i> Kopieren
                    </button>
                </div>
                
                <small class="text-muted">
                    Diese URL kannst du in deinem Kalender-Programm als "Kalender-Abonnement" hinzufügen.
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Anleitungen -->
<div class="row">
    <div class="col-12">
        <h3 class="mb-3">Anleitungen für verschiedene Kalender</h3>
    </div>
    
    <!-- Google Kalender -->
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-google text-danger"></i> Google Kalender
                </h5>
            </div>
            <div class="card-body">
                <ol>
                    <li>Öffne <strong>Google Kalender</strong></li>
                    <li>Klicke auf das <strong>+</strong> neben "Weitere Kalender"</li>
                    <li>Wähle <strong>"Über URL"</strong></li>
                    <li>Füge die Kalender-URL ein</li>
                    <li>Klicke auf <strong>"Kalender hinzufügen"</strong></li>
                </ol>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> Aktualisierung: Automatisch alle 24 Stunden
                </div>
            </div>
        </div>
    </div>
    
    <!-- Apple Kalender -->
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-apple"></i> Apple Kalender (iPhone/Mac)
                </h5>
            </div>
            <div class="card-body">
                <ol>
                    <li>Öffne die <strong>Kalender-App</strong></li>
                    <li>Gehe zu <strong>Kalender → Abonnements</strong></li>
                    <li>Klicke auf <strong>"Kalender hinzufügen"</strong></li>
                    <li>Füge die Kalender-URL ein</li>
                    <li>Bestätige mit <strong>"Abonnieren"</strong></li>
                </ol>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> Aktualisierung: Automatisch stündlich
                </div>
            </div>
        </div>
    </div>
    
    <!-- Outlook -->
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-microsoft text-primary"></i> Outlook
                </h5>
            </div>
            <div class="card-body">
                <ol>
                    <li>Öffne <strong>Outlook</strong></li>
                    <li>Klicke auf <strong>"Kalender hinzufügen"</strong></li>
                    <li>Wähle <strong>"Aus dem Internet"</strong></li>
                    <li>Füge die Kalender-URL ein</li>
                    <li>Klicke auf <strong>"OK"</strong></li>
                </ol>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> Aktualisierung: Automatisch alle 3 Stunden
                </div>
            </div>
        </div>
    </div>
    
    <!-- Thunderbird -->
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-envelope"></i> Thunderbird
                </h5>
            </div>
            <div class="card-body">
                <ol>
                    <li>Öffne <strong>Thunderbird</strong></li>
                    <li>Rechtsklick auf <strong>"Kalender"</strong></li>
                    <li>Wähle <strong>"Neuer Kalender"</strong></li>
                    <li>Wähle <strong>"Im Netzwerk"</strong></li>
                    <li>Format: <strong>iCalendar (ICS)</strong></li>
                    <li>Füge die Kalender-URL ein</li>
                </ol>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> Aktualisierung: Konfigurierbar (Standard: stündlich)
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Funktionen -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Was wird synchronisiert?</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Enthalten:</h6>
                        <ul>
                            <li><i class="bi bi-check text-success"></i> Titel der Ausrückung</li>
                            <li><i class="bi bi-check text-success"></i> Datum und Uhrzeit</li>
                            <li><i class="bi bi-check text-success"></i> Ort</li>
                            <li><i class="bi bi-check text-success"></i> Beschreibung</li>
                            <li><i class="bi bi-check text-success"></i> Automatische Updates</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Features:</h6>
                        <ul>
                            <li><i class="bi bi-clock text-primary"></i> Automatische Aktualisierung (stündlich)</li>
                            <li><i class="bi bi-arrow-repeat text-primary"></i> Änderungen werden übernommen</li>
                            <li><i class="bi bi-trash text-primary"></i> Gelöschte Ausrückungen verschwinden</li>
                            <li><i class="bi bi-calendar-event text-primary"></i> Nur zukünftige Termine</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Download -->
<div class="row mt-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-download"></i> Einmalig herunterladen</h5>
            </div>
            <div class="card-body">
                <p>Wenn du den Kalender nur einmalig (ohne automatische Updates) importieren möchtest:</p>
                <a href="kalender_export.php?download=1" class="btn btn-outline-primary">
                    <i class="bi bi-download"></i> ICS-Datei herunterladen
                </a>
                <small class="text-muted d-block mt-2">
                    Diese Datei kannst du in jeden Kalender importieren, wird aber nicht automatisch aktualisiert.
                </small>
            </div>
        </div>
    </div>
</div>

<script>
function copyCalendarUrl() {
    const urlField = document.getElementById('calendarUrl');
    urlField.select();
    urlField.setSelectionRange(0, 99999); // Für Mobile
    
    navigator.clipboard.writeText(urlField.value).then(() => {
        // Success feedback
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check"></i> Kopiert!';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }, 2000);
    }).catch(err => {
        alert('Fehler beim Kopieren. Bitte manuell kopieren.');
    });
}
</script>

<?php include 'includes/footer.php'; ?>
