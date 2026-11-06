<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

// Kalender-Basis-URL generieren
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);
$calendarBaseUrl = $baseUrl . '/kalender_export.php';

// Formationen laden
$formationenListe = [];
try {
    $formObj = new Formation();
    if (Session::isAdmin()) {
        $formationenListe = $formObj->getAll(true);
    } else {
        $formationenListe = $formObj->getFormationenFuerBenutzer(Session::getUserId());
    }
} catch (\Throwable $e) {}

// Aktive Formation aus Session
$activeFormationId = Session::getFormationId();
$activeFormation   = null;
foreach ($formationenListe as $f) {
    if ((int)$f['id'] === $activeFormationId) {
        $activeFormation = $f;
        break;
    }
}

// Haupt-URLs: mit aktiver Formation gefiltert (oder alle wenn kein Filter)
$fParam        = $activeFormationId ? 'formation=' . $activeFormationId : '';
$calendarUrl   = $calendarBaseUrl . ($fParam ? '?' . $fParam : '');
$calendarUrlKombiniert = $calendarBaseUrl . '?include=termine' . ($fParam ? '&' . $fParam : '');
$webcalUrl             = str_replace(['http://', 'https://'], 'webcal://', $calendarUrl);
$webcalUrlKombiniert   = str_replace(['http://', 'https://'], 'webcal://', $calendarUrlKombiniert);

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

<?php if ($activeFormation): ?>
<div class="alert alert-info mb-3 py-2">
    <i class="bi bi-filter"></i>
    Die folgenden Links sind gefiltert auf Formation:
    <strong>
        <span class="badge ms-1" style="background-color:<?php echo htmlspecialchars($activeFormation['farbe']); ?>">
            <?php echo htmlspecialchars($activeFormation['kuerzel'] ?: $activeFormation['name']); ?>
        </span>
        <?php echo htmlspecialchars($activeFormation['name']); ?>
    </strong>
    <?php if (Session::isAdmin()): ?>
    – <a href="api/formation_switch.php" onclick="event.preventDefault(); fetch('api/formation_switch.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({formation_id:null})}).then(()=>location.reload());" class="alert-link">Alle Formationen anzeigen</a>
    <?php endif; ?>
</div>
<?php elseif (empty($formationenListe)): ?>
<!-- keine Formationen: globale Links (wie bisher) -->
<?php else: ?>
<div class="alert alert-secondary mb-3 py-2">
    <i class="bi bi-info-circle"></i>
    Kein Formationsfilter aktiv – Links enthalten <strong>alle Formationen</strong>.
    Unten findest du formationsspezifische Abonnements.
</div>
<?php endif; ?>

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

<?php if (!empty($formationenListe)): ?>
<!-- Formationsspezifische Kalender -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Formationsspezifische Abonnements</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Abonniere den Kalender einer bestimmten Formation – enthält nur deren Ausrückungen sowie formationsübergreifende Einträge.</p>
                <div class="accordion" id="formationAccordion">
                    <?php foreach ($formationenListe as $idx => $form): ?>
                    <?php
                        $fId      = (int)$form['id'];
                        $fUrl     = $calendarBaseUrl . '?formation=' . $fId;
                        $fUrlT    = $calendarBaseUrl . '?include=termine&formation=' . $fId;
                        $fWebcal  = str_replace(['http://', 'https://'], 'webcal://', $fUrl);
                        $fWebcalT = str_replace(['http://', 'https://'], 'webcal://', $fUrlT);
                        $isActive = ($fId === $activeFormationId);
                        // Aktive Formation immer aufklappen, sonst erste wenn kein Filter gesetzt
                        $isOpen = $isActive || (!$activeFormationId && $idx === 0);
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="formHead<?php echo $fId; ?>">
                            <button class="accordion-button<?php echo ($isOpen ? '' : ' collapsed'); ?>" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#formBody<?php echo $fId; ?>"
                                    aria-expanded="<?php echo $isOpen ? 'true' : 'false'; ?>">
                                <span class="rounded-circle me-2" style="width:14px;height:14px;background:<?php echo htmlspecialchars($form['farbe']); ?>;display:inline-block;flex-shrink:0"></span>
                                <?php echo htmlspecialchars($form['name']); ?>
                                <?php if ($form['kuerzel']): ?>
                                <span class="badge ms-2" style="background-color:<?php echo htmlspecialchars($form['farbe']); ?>;color:#fff">
                                    <?php echo htmlspecialchars($form['kuerzel']); ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($isActive): ?>
                                <span class="badge bg-secondary ms-2">aktiv</span>
                                <?php endif; ?>
                            </button>
                        </h2>
                        <div id="formBody<?php echo $fId; ?>"
                             class="accordion-collapse collapse<?php echo $isOpen ? ' show' : ''; ?>"
                             data-bs-parent="#formationAccordion">
                            <div class="accordion-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card border-primary h-100">
                                            <div class="card-body">
                                                <h6 class="card-title text-primary"><i class="bi bi-truck"></i> Nur Ausrückungen</h6>
                                                <a href="<?php echo htmlspecialchars($fWebcal); ?>" class="btn btn-sm btn-primary mb-2 w-100">
                                                    <i class="bi bi-calendar-plus"></i> Abonnieren
                                                </a>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($fUrl); ?>" readonly
                                                           id="fUrl<?php echo $fId; ?>">
                                                    <button class="btn btn-outline-primary" type="button"
                                                            onclick="copyUrl('fUrl<?php echo $fId; ?>', this)">
                                                        <i class="bi bi-clipboard"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-success h-100">
                                            <div class="card-body">
                                                <h6 class="card-title text-success"><i class="bi bi-calendar2-week"></i> + Termine</h6>
                                                <a href="<?php echo htmlspecialchars($fWebcalT); ?>" class="btn btn-sm btn-success mb-2 w-100">
                                                    <i class="bi bi-calendar-plus"></i> Abonnieren
                                                </a>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($fUrlT); ?>" readonly
                                                           id="fUrlT<?php echo $fId; ?>">
                                                    <button class="btn btn-outline-success" type="button"
                                                            onclick="copyUrl('fUrlT<?php echo $fId; ?>', this)">
                                                        <i class="bi bi-clipboard"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2 d-flex gap-2">
                                    <a href="<?php echo htmlspecialchars($fUrl . '&download=1'); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download"></i> Nur Ausrückungen (ICS)
                                    </a>
                                    <a href="<?php echo htmlspecialchars($fUrlT . '&download=1'); ?>" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-download"></i> + Termine (ICS)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
                    <a href="<?php echo htmlspecialchars($calendarUrl . ($fParam ? '&download=1' : '?download=1')); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-download"></i> Nur Ausrückungen (ICS)
                    </a>
                    <a href="<?php echo htmlspecialchars($calendarUrlKombiniert . '&download=1'); ?>" class="btn btn-outline-success">
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
