<?php
// rollen.php - Rollenverwaltung
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (Session::getRole() !== 'admin') {
    Session::setFlashMessage('danger', 'Nur Administratoren haben Zugriff');
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

// Rolle löschen
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $rolle_id = $_GET['delete'];
    
    // Prüfen ob Admin-Rolle
    $rolle = $db->fetchOne("SELECT ist_admin FROM rollen WHERE id = ?", [$rolle_id]);
    if ($rolle && $rolle['ist_admin']) {
        Session::setFlashMessage('danger', 'Admin-Rolle kann nicht gelöscht werden');
    } else {
        // Prüfen ob noch Benutzer diese Rolle haben
        $benutzer_count = $db->fetchOne("SELECT COUNT(*) as anzahl FROM benutzer WHERE rolle_id = ?", [$rolle_id]);
        if ($benutzer_count['anzahl'] > 0) {
            Session::setFlashMessage('danger', 'Rolle wird noch von ' . $benutzer_count['anzahl'] . ' Benutzer(n) verwendet');
        } else {
            try {
                $db->execute("DELETE FROM rollen WHERE id = ?", [$rolle_id]);
                Session::setFlashMessage('success', 'Rolle gelöscht');
            } catch (Exception $e) {
                Session::setFlashMessage('danger', 'Fehler: ' . $e->getMessage());
            }
        }
    }
    header('Location: rollen.php');
    exit;
}

$rollen = $db->fetchAll("SELECT * FROM rollen ORDER BY sortierung, name");

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-shield-lock"></i> Rollenverwaltung</h1>
    <a href="rolle_bearbeiten.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Neue Rolle
    </a>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>Hinweis:</strong> Rollen definieren, welche Benutzer welche Berechtungen haben. 
    Jedem Benutzer kann genau eine Rolle zugewiesen werden.
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="rollenTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Beschreibung</th>
                    <th>Typ</th>
                    <th>Benutzer</th>
                    <th>Status</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rollen as $rolle): ?>
                <?php
                // Benutzer-Anzahl für diese Rolle
                $benutzer_count = $db->fetchOne("SELECT COUNT(*) as anzahl FROM benutzer WHERE rolle_id = ?", [$rolle['id']]);
                ?>
                <tr>
                    <td>
                        <span class="badge bg-<?php echo htmlspecialchars($rolle['farbe']); ?> me-2">
                            <?php echo htmlspecialchars($rolle['name']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($rolle['beschreibung'] ?? '-'); ?></td>
                    <td>
                        <?php if ($rolle['ist_admin']): ?>
                        <span class="badge bg-danger">Admin</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Standard</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-info"><?php echo $benutzer_count['anzahl']; ?> Benutzer</span>
                    </td>
                    <td>
                        <?php if ($rolle['aktiv']): ?>
                        <span class="badge bg-success">Aktiv</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Inaktiv</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="berechtigungen_bearbeiten.php?rolle_id=<?php echo $rolle['id']; ?>" 
                           class="btn btn-sm btn-warning" title="Berechtigungen">
                            <i class="bi bi-key"></i>
                        </a>
                        <a href="rolle_bearbeiten.php?id=<?php echo $rolle['id']; ?>" 
                           class="btn btn-sm btn-primary" title="Bearbeiten">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if (!$rolle['ist_admin'] && $benutzer_count['anzahl'] == 0): ?>
                        <a href="?delete=<?php echo $rolle['id']; ?>" 
                           class="btn btn-sm btn-danger" title="Löschen"
                           onclick="return confirm('Rolle wirklich löschen?')">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Standard-Rollen Übersicht</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Rolle</th>
                            <th>Zuständigkeit</th>
                            <th>Hauptberechtigungen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-danger">Admin</span></td>
                            <td>Systemverwaltung</td>
                            <td>Voller Zugriff auf alle Bereiche, Benutzerverwaltung, Einstellungen</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-primary">Obmann</span></td>
                            <td>Vereinsführung</td>
                            <td>Mitglieder, Ausrückungen, Noten verwalten; Finanzen einsehen</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-success">Kapellmeister</span></td>
                            <td>Musikalische Leitung</td>
                            <td>Ausrückungen, Noten, Proben planen; Mitglieder verwalten</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-info">Kassier</span></td>
                            <td>Finanzen</td>
                            <td>Finanzen komplett verwalten, Beiträge erfassen</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-warning">Schriftführer</span></td>
                            <td>Verwaltung & Protokolle</td>
                            <td>Mitglieder, Ausrückungen, Noten bearbeiten</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-purple">Trachtenwart</span></td>
                            <td>Uniformen</td>
                            <td>Uniformen verwalten, Ausgabe/Rücknahme, Inventur</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-dark">Instrumentenwart</span></td>
                            <td>Instrumente</td>
                            <td>Instrumente verwalten, Wartung, Verleih</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-secondary">Mitglied</span></td>
                            <td>Basis-Zugriff</td>
                            <td>Termine einsehen, eigene Daten einsehen</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.badge.bg-purple {
    background-color: #6f42c1 !important;
}
</style>

<script>
$(document).ready(function() {
    $('#rollenTable').DataTable({
        order: [[0, 'asc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/de-DE.json'
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
