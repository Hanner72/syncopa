<?php
// update.php – System-Update aus GitHub
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin()) {
    Session::setFlashMessage('danger', 'Nur Administratoren haben Zugriff');
    header('Location: index.php');
    exit;
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-cloud-download"></i> System-Update</h1>
    <a href="einstellungen.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Einstellungen</a>
</div>

<!-- Versionsstatus -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Versionsstatus</h5>
        <button class="btn btn-sm btn-outline-primary" id="btn-check" onclick="checkVersion()">
            <i class="bi bi-arrow-clockwise"></i> Prüfen
        </button>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="border rounded p-3 text-center">
                    <div class="text-muted small mb-1">Installierte Version</div>
                    <div class="fs-4 fw-bold"><?php echo APP_VERSION; ?></div>
                    <div class="text-muted small" id="local-hash">–</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 text-center">
                    <div class="text-muted small mb-1">Verfügbare Version</div>
                    <div class="fs-4 fw-bold" id="remote-version">
                        <span class="text-muted">–</span>
                    </div>
                    <div class="text-muted small" id="remote-hash">–</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 text-center">
                    <div class="text-muted small mb-1">Status</div>
                    <div id="status-badge" class="fs-5 mt-1">
                        <span class="text-muted"><i class="bi bi-dash-circle"></i> Nicht geprüft</span>
                    </div>
                </div>
            </div>
        </div>

        <div id="check-spinner" class="text-center py-3" style="display:none">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <span class="ms-2 text-muted">Verbinde mit GitHub…</span>
        </div>
        <div id="check-error" class="alert alert-danger" style="display:none"></div>
    </div>
</div>

<!-- Neuigkeiten -->
<div class="card mb-3" id="card-commits" style="display:none">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-journal-text"></i> Änderungen in neuer Version</h5>
    </div>
    <div class="card-body">
        <div id="commit-list" style="font-size:13px;white-space:pre-wrap;font-family:inherit;line-height:1.6"></div>
    </div>
</div>

<!-- Update durchführen -->
<div class="card mb-3" id="card-update" style="display:none">
    <div class="card-header" style="border-left:3px solid var(--c-warning)">
        <h5 class="mb-0"><i class="bi bi-cloud-download"></i> Update durchführen</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>Hinweis:</strong> Das Update überschreibt alle PHP-Dateien mit der aktuellen Version auf GitHub.
            <ul class="mb-0 mt-1">
                <li><strong>config.php</strong> wird automatisch gesichert und nach dem Update wiederhergestellt.</li>
                <li>Uploads und Benutzerdaten bleiben unberührt.</li>
                <li>Empfehlung: Erstelle vorher ein vollständiges Datenbank-Backup.</li>
            </ul>
        </div>
        <div id="up-to-date-msg" class="alert alert-success" style="display:none">
            <i class="bi bi-check-circle-fill"></i> Das System ist bereits auf dem neuesten Stand.
        </div>
        <button class="btn btn-success" id="btn-update" onclick="doUpdate()" style="display:none">
            <i class="bi bi-cloud-download"></i> Jetzt auf neueste Version aktualisieren
        </button>
    </div>
</div>

<!-- Update-Log -->
<div class="card" id="card-log" style="display:none">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-terminal"></i> Update-Protokoll</h5>
    </div>
    <div class="card-body p-0">
        <pre id="update-log" class="m-0 p-3" style="background:#1e1e1e;color:#d4d4d4;font-size:13px;border-radius:0 0 .375rem .375rem;min-height:80px;white-space:pre-wrap"></pre>
    </div>
    <div id="update-result" class="card-footer" style="display:none"></div>
</div>

<script>
var versionData = null;

function checkVersion() {
    document.getElementById('check-spinner').style.display = '';
    document.getElementById('check-error').style.display = 'none';
    document.getElementById('btn-check').disabled = true;
    document.getElementById('card-commits').style.display = 'none';
    document.getElementById('card-update').style.display = 'none';
    document.getElementById('card-log').style.display = 'none';

    fetch('api/system_update.php?action=check')
        .then(r => r.json())
        .then(data => {
            document.getElementById('check-spinner').style.display = 'none';
            document.getElementById('btn-check').disabled = false;

            if (!data.success) {
                document.getElementById('check-error').textContent = 'Fehler: ' + (data.error || 'Unbekannt');
                document.getElementById('check-error').style.display = '';
                return;
            }

            if (data.serverError) {
                document.getElementById('check-error').innerHTML =
                    '<i class="bi bi-exclamation-triangle-fill"></i> <strong>Automatische Updates nicht verfügbar</strong><br>' + data.serverError;
                document.getElementById('check-error').className = 'alert alert-warning';
                document.getElementById('check-error').style.display = '';
                document.getElementById('status-badge').innerHTML =
                    '<span class="badge bg-secondary"><i class="bi bi-slash-circle"></i> Nicht verfügbar</span>';
                return;
            }

            versionData = data;

            // Versionen anzeigen
            document.getElementById('remote-version').textContent = data.remoteVersion || '–';
            document.getElementById('local-hash').textContent  = 'Stand: ' + (data.localHash  || '–');
            document.getElementById('remote-hash').textContent = 'Stand: ' + (data.remoteHash || '–');

            // Status-Badge
            var statusEl = document.getElementById('status-badge');
            if (data.upToDate) {
                statusEl.innerHTML = '<span class="badge bg-success fs-6"><i class="bi bi-check-circle"></i> Aktuell</span>';
            } else {
                statusEl.innerHTML = '<span class="badge bg-warning text-dark fs-6"><i class="bi bi-exclamation-circle"></i> Update verfügbar</span>';
            }

            // Changelog-Karte
            if (data.newChanges && data.newChanges.length > 0) {
                document.getElementById('card-commits').style.display = '';
                var el = document.getElementById('commit-list');
                el.innerHTML = '';
                data.newChanges.forEach(function(line) {
                    var div = document.createElement('div');
                    // Überschriften fett
                    if (/^#{1,3}\s/.test(line)) {
                        div.style.fontWeight = 'bold';
                        div.style.marginTop  = '8px';
                        div.textContent = line.replace(/^#+\s*/, '');
                    } else if (/^[-*]\s/.test(line)) {
                        div.style.paddingLeft = '12px';
                        div.textContent = line;
                    } else {
                        div.textContent = line;
                    }
                    el.appendChild(div);
                });
            }

            // Update-Karte
            document.getElementById('card-update').style.display = '';
            if (data.upToDate) {
                document.getElementById('up-to-date-msg').style.display = '';
                document.getElementById('btn-update').style.display = 'none';
            } else {
                document.getElementById('up-to-date-msg').style.display = 'none';
                document.getElementById('btn-update').style.display = '';
            }
        })
        .catch(function(e) {
            document.getElementById('check-spinner').style.display = 'none';
            document.getElementById('btn-check').disabled = false;
            document.getElementById('check-error').textContent = 'Verbindungsfehler: ' + e.message;
            document.getElementById('check-error').style.display = '';
        });
}

function doUpdate() {
    if (!confirm('Jetzt wirklich aktualisieren?\n\nDas System wird kurz nicht erreichbar sein.')) return;

    var btn = document.getElementById('btn-update');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Aktualisierung läuft…';

    var logEl = document.getElementById('update-log');
    logEl.textContent = '';
    document.getElementById('card-log').style.display = '';
    document.getElementById('update-result').style.display = 'none';

    var fd = new FormData();
    fd.append('action', 'update');

    fetch('api/system_update.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-download"></i> Jetzt auf neueste Version aktualisieren';

            if (data.log) {
                logEl.textContent = data.log.join('\n');
            }

            var resultEl = document.getElementById('update-result');
            resultEl.style.display = '';
            if (data.success) {
                resultEl.className = 'card-footer bg-success bg-opacity-10 text-success';
                resultEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> <strong>Update erfolgreich!</strong>'
                    + (data.newVersion ? ' Neue Version: <strong>' + escHtml(data.newVersion) + '</strong>' : '')
                    + ' – <a href="update.php" class="alert-link">Seite neu laden</a>';
                btn.style.display = 'none';
            } else {
                resultEl.className = 'card-footer bg-danger bg-opacity-10 text-danger';
                resultEl.innerHTML = '<i class="bi bi-x-circle-fill"></i> <strong>Fehler beim Update.</strong> Siehe Protokoll oben.';
            }
        })
        .catch(function(e) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-download"></i> Jetzt auf neueste Version aktualisieren';
            logEl.textContent += '\nVerbindungsfehler: ' + e.message;
        });
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Direkt beim Laden prüfen
checkVersion();
</script>

<?php include 'includes/footer.php'; ?>
