<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Kalender-URL generieren
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
$calendarUrl = $baseUrl . '/kalender_export.php';

// Webcal URL (für direktes Abonnieren) - nur Ausrückungen
$webcalUrl = str_replace(['http://', 'https://'], 'webcal://', $calendarUrl);

// URL für kombinierten Kalender (Ausrückungen + Termine)
$calendarUrlKombiniert = $calendarUrl . '?include=termine';
$webcalUrlKombiniert = str_replace(['http://', 'https://'], 'webcal://', $calendarUrlKombiniert);

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
    <!-- Option 1: Nur Ausrückungen -->
    <div class="col-md-6 mb-4">
        <div class="card border-primary h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-truck"></i> Nur Ausrückungen</h5>
            </div>
            <div class="card-body d-flex flex-column">
                <p>Enthält ausschließlich die <strong>Ausrückungen</strong> (Einsätze, Übungen etc.).</p>
                
                <div class="d-grid mb-3">
                    <a href="<?php echo htmlspecialchars($webcalUrl); ?>" class="btn btn-lg btn-primary">
                        <i class="bi bi-calendar-plus"></i> Ausrückungen abonnieren
                    </a>
                </div>
                
                <div class="input-group mt-auto">
                    <input type="text" class="form-control form-control-sm" id="calendarUrl" 
                           value="<?php echo htmlspecialchars($calendarUrl); ?>" readonly>
                    <button class="btn btn-sm btn-outline-primary" type="button" onclick="copyUrl('calendarUrl', this)">
                        <i class="bi bi-clipboard"></i> Kopieren
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Option 2: Ausrückungen + Termine -->
    <div class="col-md-6 mb-4">
        <div class="card border-success h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-calendar2-week"></i> Ausrückungen + Termine</h5>
            </div>
            <div class="card-body d-flex flex-column">
                <p>Enthält die <strong>Ausrückungen</strong> und zusätzlich alle <strong>Termine</strong> aus dem Terminkalender.</p>
                
                <div class="d-grid mb-3">
                    <a href="<?php echo htmlspecialchars($webcalUrlKombiniert); ?>" class="btn btn-lg btn-success">
                        <i class="bi bi-calendar-plus"></i> Ausrückungen + Termine abonnieren
                    </a>
                </div>
                
                <div class="input-group mt-auto">
                    <input type="text" class="form-control form-control-sm" id="calendarUrlKombiniert" 
                           value="<?php echo htmlspecialchars($calendarUrlKombiniert); ?>" readonly>
                    <button class="btn btn-sm btn-outline-success" type="button" onclick="copyUrl('calendarUrlKombiniert', this)">
                        <i class="bi bi-clipboard"></i> Kopieren
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hinweis -->
    <div class="col-12 mb-4">
        <div class="alert alert-info mb-0">
            <i class="bi bi-info-circle"></i>
            <strong>Hinweis:</strong> Beide Kalender werden automatisch aktualisiert, wenn Änderungen vorgenommen werden. Du musst nichts manuell aktualisieren!
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
                    <div class="col-md-4">
                        <h6><i class="bi bi-truck text-primary"></i> Ausrückungen (beide Varianten):</h6>
                        <ul>
                            <li><i class="bi bi-check text-success"></i> Titel der Ausrückung</li>
                            <li><i class="bi bi-check text-success"></i> Datum und Uhrzeit</li>
                            <li><i class="bi bi-check text-success"></i> Ort</li>
                            <li><i class="bi bi-check text-success"></i> Beschreibung</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6><i class="bi bi-calendar2-week text-success"></i> Zusätzlich bei "Ausrückungen + Termine":</h6>
                        <ul>
                            <li><i class="bi bi-check text-success"></i> Alle Termine aus dem Terminkalender</li>
                            <li><i class="bi bi-check text-success"></i> Titel & Beschreibung der Termine</li>
                            <li><i class="bi bi-check text-success"></i> Datum und Uhrzeit</li>
                            <li><i class="bi bi-check text-success"></i> Ort</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6><i class="bi bi-gear text-primary"></i> Features:</h6>
                        <ul>
                            <li><i class="bi bi-clock text-primary"></i> Automatische Aktualisierung</li>
                            <li><i class="bi bi-arrow-repeat text-primary"></i> Änderungen werden übernommen</li>
                            <li><i class="bi bi-trash text-primary"></i> Gelöschte Einträge verschwinden</li>
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
                <div class="d-flex gap-2 flex-wrap">
                    <a href="kalender_export.php?download=1" class="btn btn-outline-primary">
                        <i class="bi bi-download"></i> Nur Ausrückungen (ICS)
                    </a>
                    <a href="kalender_export.php?download=1&include=termine" class="btn btn-outline-success">
                        <i class="bi bi-download"></i> Ausrückungen + Termine (ICS)
                    </a>
                </div>
                <small class="text-muted d-block mt-2">
                    Diese Dateien kannst du in jeden Kalender importieren, werden aber nicht automatisch aktualisiert.
                </small>
            </div>
        </div>
    </div>
</div>

<script>
function copyUrl(fieldId, btn) {
    const urlField = document.getElementById(fieldId);
    urlField.select();
    urlField.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(urlField.value).then(() => {
        const originalHTML = btn.innerHTML;
        const wasOutlinePrimary = btn.classList.contains('btn-outline-primary');
        btn.innerHTML = '<i class="bi bi-check"></i> Kopiert!';
        btn.classList.remove('btn-outline-primary', 'btn-outline-success');
        btn.classList.add('btn-success');
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.classList.remove('btn-success');
            btn.classList.add(wasOutlinePrimary ? 'btn-outline-primary' : 'btn-outline-success');
        }, 2000);
    }).catch(() => {
        alert('Fehler beim Kopieren. Bitte manuell kopieren.');
    });
}
</script>

<?php include 'includes/footer.php'; ?>