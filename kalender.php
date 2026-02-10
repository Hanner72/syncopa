<?php
// kalender.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('ausrueckungen', 'lesen');

include 'includes/header.php';
?>

<style>
    a.fc-event, a.fc-event:hover {
        text-decoration: none;
        cursor: pointer !important;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-calendar-event"></i> Kalender
    </h1>
    <?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newEventModal">
        <i class="bi bi-plus-circle"></i> Neuer Termin
    </button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

<!-- Event Details Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventModalBody">
                <!-- Wird dynamisch gefüllt -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                <a href="#" id="eventDetailsLink" class="btn btn-primary">Details anzeigen</a>
            </div>
        </div>
    </div>
</div>

<!-- New Event Modal -->
<?php if (Session::checkPermission('ausrueckungen', 'schreiben')): ?>
<div class="modal fade" id="newEventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="api/kalender_termine.php" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header">
                    <h5 class="modal-title">Neuer Kalender-Termin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Hinweis:</strong> Dies erstellt einen einfachen Kalender-Termin. 
                        Für offizielle Ausrückungen mit Anwesenheitsliste nutze bitte 
                        <a href="ausrueckung_bearbeiten.php">Ausrückungen erstellen</a>.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="titel" class="form-label">Titel *</label>
                            <input type="text" class="form-control" id="titel" name="titel" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="typ" class="form-label">Typ *</label>
                            <select class="form-select" id="typ" name="typ" required>
                                <option value="Termin">Termin</option>
                                <option value="Besprechung">Besprechung</option>
                                <option value="Geburtstag">Geburtstag</option>
                                <option value="Feiertag">Feiertag</option>
                                <option value="Reminder">Reminder</option>
                                <option value="Sonstiges">Sonstiges</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_datum" class="form-label">Start Datum/Zeit *</label>
                            <input type="datetime-local" class="form-control" id="start_datum" name="start_datum" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="ende_datum" class="form-label">
                                Ende Datum/Zeit 
                                <small class="text-muted">(optional, Standard: +2h)</small>
                            </label>
                            <input type="datetime-local" class="form-control" id="ende_datum" name="ende_datum">
                            <small class="text-muted">Leer lassen für automatisch +2 Stunden</small>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="ganztaegig" name="ganztaegig" value="1">
                        <label class="form-check-label" for="ganztaegig">Ganztägig</label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="ort" class="form-label">Ort</label>
                        <input type="text" class="form-control" id="ort" name="ort">
                    </div>
                    
                    <div class="mb-3">
                        <label for="beschreibung" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="beschreibung" name="beschreibung" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="farbe" class="form-label">Farbe</label>
                        <select class="form-select" id="farbe" name="farbe">
                            <option value="#6c757d" style="background: #6c757d; color: white;">Grau (Standard)</option>
                            <option value="#0d6efd" style="background: #0d6efd; color: white;">Blau</option>
                            <option value="#198754" style="background: #198754; color: white;">Grün</option>
                            <option value="#ffc107" style="background: #ffc107; color: black;">Gelb</option>
            <option value="#dc3545" style="background: #dc3545; color: white;">Rot</option>
                            <option value="#6f42c1" style="background: #6f42c1; color: white;">Lila</option>
                            <option value="#17a2b8" style="background: #17a2b8; color: white;">Cyan</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'de',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        buttonText: {
            today: 'Heute',
            month: 'Monat',
            week: 'Woche',
            day: 'Tag',
            list: 'Liste'
        },
        firstDay: 1, // Montag
        weekNumbers: true,
        weekText: 'KW',
        height: 'auto',
        events: function(info, successCallback, failureCallback) {
            const url = 'api/kalender.php?start=' + info.startStr + '&end=' + info.endStr;
            console.log('Kalender lädt Events von:', url);
            
            fetch(url)
                .then(response => {
                    console.log('API Response Status:', response.status);
                    if (!response.ok) {
                        throw new Error('HTTP Error: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('API liefert', data.length, 'Events:', data);
                    if (data.length === 0) {
                        console.warn('⚠️ API liefert leeres Array! Keine Events im Zeitraum:', info.startStr, 'bis', info.endStr);
                    }
                    successCallback(data);
                })
                .catch(error => {
                    console.error('❌ Fehler beim Laden der Events:', error);
                    failureCallback(error);
                    alert('Fehler beim Laden der Termine: ' + error.message);
                });
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault(); // Verhindere Standard-Navigation
            
            const event = info.event;
            
            // Modal Titel
            document.getElementById('eventModalTitle').textContent = event.title;
            
            // Prüfe ob Ausrückung oder Termin
            const isAusrueckung = event.extendedProps.isAusrueckung || false;
            const isTermin = event.extendedProps.isTermin || false;
            
            // Modal Body
            let body = '<dl class="row">';
            
            // Typ-Badge
            body += '<dt class="col-sm-3">Art:</dt><dd class="col-sm-9">';
            if (isAusrueckung) {
                body += '<span class="badge bg-success">Ausrückung</span> ';
            } else if (isTermin) {
                body += '<span class="badge bg-info">Kalender-Termin</span> ';
            }
            body += '<span class="badge bg-primary">' + event.extendedProps.typ + '</span>';
            body += '</dd>';
            
            // Datum
            body += '<dt class="col-sm-3">Datum:</dt><dd class="col-sm-9">' + formatDate(event.start);
            if (event.end) {
                body += ' bis ' + formatDate(event.end);
            }
            body += '</dd>';
            
            // Ort
            if (event.extendedProps.ort) {
                body += '<dt class="col-sm-3">Ort:</dt><dd class="col-sm-9">' + event.extendedProps.ort + '</dd>';
            }
            
            // Nur bei Ausrückungen:
            if (isAusrueckung) {
                if (event.extendedProps.treffpunkt) {
                    body += '<dt class="col-sm-3">Treffpunkt:</dt><dd class="col-sm-9">' + event.extendedProps.treffpunkt + '</dd>';
                }
                
                if (event.extendedProps.uniform) {
                    body += '<dt class="col-sm-3">Uniform:</dt><dd class="col-sm-9"><span class="badge bg-success">Erforderlich</span></dd>';
                }
                
                body += '<dt class="col-sm-3">Status:</dt><dd class="col-sm-9">';
                body += '<span class="badge bg-' + (event.extendedProps.status === 'bestaetigt' ? 'success' : 'warning') + '">';
                body += event.extendedProps.status === 'bestaetigt' ? 'Bestätigt' : event.extendedProps.status === 'abgesagt' ? 'Abgesagt' : 'Geplant';
                body += '</span></dd>';
            }
            
            // Beschreibung
            if (event.extendedProps.beschreibung) {
                body += '<dt class="col-sm-3">Beschreibung:</dt><dd class="col-sm-9">' + event.extendedProps.beschreibung + '</dd>';
            }
            
            // Erstellt von (nur bei Terminen)
            if (isTermin && event.extendedProps.erstellt_von) {
                body += '<dt class="col-sm-3">Erstellt von:</dt><dd class="col-sm-9">' + event.extendedProps.erstellt_von + '</dd>';
            }
            
            body += '</dl>';
            
            document.getElementById('eventModalBody').innerHTML = body;
            
            // Details-Link
            const detailsLink = document.getElementById('eventDetailsLink');
            if (isAusrueckung) {
                // ID ohne Prefix
                const realId = event.id.replace('ausrueckung_', '');
                detailsLink.href = 'ausrueckung_detail.php?id=' + realId;
                detailsLink.style.display = '';
                detailsLink.innerHTML = '<i class="bi bi-info-circle"></i> Zu Ausrückung';
            } else if (isTermin) {
                // Kein Details-Link für Termine
                detailsLink.style.display = 'none';
            }
            
            const modal = new bootstrap.Modal(document.getElementById('eventModal'));
            modal.show();
        },
        eventDidMount: function(info) {
            // Tooltip hinzufügen
            info.el.title = info.event.title + '\n' + formatDate(info.event.start);
        }
    });
    
    calendar.render();
    
    function formatDate(date) {
        if (!date) return '';
        const options = { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return new Date(date).toLocaleDateString('de-DE', options);
    }
    
    // Ganztägig Checkbox Toggle
    document.getElementById('ganztaegig')?.addEventListener('change', function() {
        const startInput = document.getElementById('start_datum');
        const endeInput = document.getElementById('ende_datum');
        
        if (this.checked) {
            startInput.type = 'date';
            endeInput.type = 'date';
        } else {
            startInput.type = 'datetime-local';
            endeInput.type = 'datetime-local';
        }
    });
    
    // Form Submit per AJAX (kein Page Reload)
    document.querySelector('#newEventModal form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validierung
        const titel = document.getElementById('titel').value.trim();
        const startDatum = document.getElementById('start_datum').value;
        const ganztaegig = document.getElementById('ganztaegig').checked;
        const endeDatum = document.getElementById('ende_datum').value;
        
        if (!titel) {
            alert('Bitte Titel eingeben!');
            return;
        }
        
        if (!startDatum) {
            alert('Bitte Start-Datum eingeben!');
            return;
        }
        
        // Hinweis wenn Ende-Datum fehlt
        if (!endeDatum && !ganztaegig) {
            const confirmMsg = 'Ende-Datum nicht angegeben.\n\n' +
                             'Der Termin wird automatisch auf 2 Stunden gesetzt.\n\n' +
                             'Fortfahren?';
            if (!confirm(confirmMsg)) {
                return;
            }
        }
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Speichere...';
        
        fetch('api/kalender_termine.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP Error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Modal schließen
                const modal = bootstrap.Modal.getInstance(document.getElementById('newEventModal'));
                modal.hide();
                
                // Kalender neu laden
                calendar.refetchEvents();
                
                // Success-Nachricht (Bootstrap Toast wäre schöner, aber Alert geht auch)
                alert('✅ Termin erfolgreich erstellt!');
                
                // Form zurücksetzen
                this.reset();
            } else {
                throw new Error(data.error || 'Unbekannter Fehler');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Fehler beim Speichern:\n\n' + error.message);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
