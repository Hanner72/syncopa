<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'lesen');

$uniformObj = new Uniform();
$mitglieder = $uniformObj->getMitgliederMitUniformen();
$stats = $uniformObj->getStatistik();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-person-badge"></i> Uniformenverwaltung</h1>
    <?php if (Session::checkPermission('uniformen', 'schreiben')): ?>
    <div>
        <a href="uniform_kleidungsstuecke.php" class="btn btn-outline-primary">
            <i class="bi bi-tags"></i> Kleidungsstücke verwalten
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Statistik-Karten -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Aktive Mitglieder</h6>
                        <h2 class="mb-0"><?php echo $stats['mitglieder_aktiv']; ?></h2>
                    </div>
                    <i class="bi bi-people fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Mit Uniform</h6>
                        <h2 class="mb-0"><?php echo $stats['mitglieder_mit_uniform']; ?></h2>
                    </div>
                    <i class="bi bi-person-check fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Kleidungsstücke</h6>
                        <h2 class="mb-0"><?php echo $stats['kleidungsstuecke']; ?></h2>
                    </div>
                    <i class="bi bi-tag fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title mb-0">Zuweisungen</h6>
                        <h2 class="mb-0"><?php echo $stats['zuweisungen']; ?></h2>
                    </div>
                    <i class="bi bi-arrow-left-right fs-1 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mitglieder-Tabelle -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-people"></i> Mitglieder & ihre Uniformen</h5>
    </div>
    <div class="card-body">
        <table class="table table-hover" id="mitgliederTable">
            <thead>
                <tr>
                    <th>Nr.</th>
                    <th>Name</th>
                    <th class="text-center">Zugewiesene Teile</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mitglieder as $m): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($m['mitgliedsnummer']); ?></code></td>
                    <td>
                        <strong><?php echo htmlspecialchars($m['nachname'] . ' ' . $m['vorname']); ?></strong>
                    </td>
                    <td class="text-center">
                        <?php if ($m['anzahl_teile'] > 0): ?>
                        <button type="button" class="btn btn-sm btn-outline-success btn-show-uniform"
                                data-id="<?php echo $m['id']; ?>"
                                data-name="<?php echo htmlspecialchars($m['vorname'] . ' ' . $m['nachname']); ?>">
                            <i class="bi bi-eye"></i>
                            <span class="badge bg-success"><?php echo $m['anzahl_teile']; ?></span>
                        </button>
                        <?php else: ?>
                        <span class="badge bg-secondary">Keine</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="uniform_mitglied.php?id=<?php echo $m['id']; ?>" 
                               class="btn btn-primary" title="Uniform bearbeiten">
                                <i class="bi bi-pencil"></i> Bearbeiten
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Uniform-Details anzeigen -->
<div class="modal fade" id="uniformModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-badge text-primary"></i> 
                    Uniform von <span id="modalMitgliedName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="uniformModalContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="modalEditLink" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Bearbeiten
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
(function() {
    // DataTable
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        $('#mitgliederTable').DataTable({
            order: [[1, 'asc']],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/de-DE.json'
            }
        });
    }
    
    // Modal für Uniform-Anzeige
    document.querySelectorAll('.btn-show-uniform').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var mitgliedId = this.dataset.id;
            var mitgliedName = this.dataset.name;
            
            document.getElementById('modalMitgliedName').textContent = mitgliedName;
            document.getElementById('modalEditLink').href = 'uniform_mitglied.php?id=' + mitgliedId;
            document.getElementById('uniformModalContent').innerHTML = 
                '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
            
            var modal = new bootstrap.Modal(document.getElementById('uniformModal'));
            modal.show();
            
            // Daten laden
            fetch('api/uniform_mitglied.php?id=' + mitgliedId)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success && data.zuweisungen.length > 0) {
                        var html = '<table class="table table-sm">';
                        html += '<thead><tr><th>Kategorie</th><th>Kleidungsstück</th><th>Größe</th><th>Zustand</th></tr></thead>';
                        html += '<tbody>';
                        
                        data.zuweisungen.forEach(function(z) {
                            var zustandColors = {'sehr gut': 'success', 'gut': 'info', 'befriedigend': 'warning', 'schlecht': 'danger'};
                            var color = zustandColors[z.zustand] || 'secondary';
                            
                            html += '<tr>';
                            html += '<td><span class="badge bg-secondary">' + (z.kategorie_name || '-') + '</span></td>';
                            html += '<td><strong>' + z.kleidungsstueck_name + '</strong></td>';
                            html += '<td>' + (z.groesse || '-') + '</td>';
                            html += '<td><span class="badge bg-' + color + '">' + (z.zustand || 'gut') + '</span></td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                        document.getElementById('uniformModalContent').innerHTML = html;
                    } else {
                        document.getElementById('uniformModalContent').innerHTML = 
                            '<p class="text-muted text-center mb-0">Keine Kleidungsstücke zugewiesen</p>';
                    }
                })
                .catch(function(error) {
                    console.error('Fehler:', error);
                    document.getElementById('uniformModalContent').innerHTML = 
                        '<p class="text-danger text-center mb-0">Fehler beim Laden</p>';
                });
        });
    });
})();
</script>
