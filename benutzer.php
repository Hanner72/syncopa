<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (Session::getRole() !== 'admin') {
    Session::setFlashMessage('danger', 'Nur Administratoren haben Zugriff auf diese Seite');
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

// Alle Benutzer mit ihren Rollen laden
$benutzer = $db->fetchAll("
    SELECT b.*, r.name as rolle_name, r.farbe as rolle_farbe, r.beschreibung as rolle_beschreibung
    FROM benutzer b
    LEFT JOIN rollen r ON b.rolle_id = r.id
    ORDER BY b.benutzername
");

// Alle Rollen für die Berechtigungstabelle laden
$rollen = $db->fetchAll("SELECT * FROM rollen WHERE aktiv = 1 ORDER BY sortierung, name");

// Module definieren
$module = [
    'mitglieder' => 'Mitglieder',
    'ausrueckungen' => 'Ausrückungen', 
    'noten' => 'Noten',
    'instrumente' => 'Instrumente',
    'uniformen' => 'Uniformen',
    'finanzen' => 'Finanzen',
    'benutzer' => 'Benutzer',
    'einstellungen' => 'Einstellungen'
];

// Berechtigungen pro Rolle laden
$berechtigungen_map = [];
foreach ($rollen as $rolle) {
    $perms = $db->fetchAll("SELECT * FROM berechtigungen WHERE rolle = ?", [$rolle['name']]);
    foreach ($perms as $perm) {
        $berechtigungen_map[$rolle['name']][$perm['modul']] = $perm;
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-person-gear"></i> Benutzerverwaltung</h1>
    <a href="benutzer_bearbeiten.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Neuer Benutzer
    </a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="benutzerTable">
            <thead>
                <tr>
                    <th>Benutzername</th>
                    <th>E-Mail</th>
                    <th>Rolle</th>
                    <th>Mitglied</th>
                    <th>Status</th>
                    <th>Letzter Login</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($benutzer as $user): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($user['benutzername']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <?php if ($user['rolle_name']): ?>
                        <span class="badge bg-<?php echo htmlspecialchars($user['rolle_farbe'] ?? 'secondary'); ?>">
                            <?php echo htmlspecialchars(ucfirst($user['rolle_name'])); ?>
                        </span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Keine Rolle</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        // Mitglied-Info laden
                        $mitglied = null;
                        if ($user['mitglied_id']) {
                            $mitglied = $db->fetchOne("SELECT vorname, nachname, mitgliedsnummer FROM mitglieder WHERE id = ?", [$user['mitglied_id']]);
                        }
                        
                        if ($mitglied): ?>
                        <small class="text-success">
                            <i class="bi bi-person-check"></i>
                            <?php echo htmlspecialchars($mitglied['nachname'] . ' ' . $mitglied['vorname']); ?>
                            <br><span class="text-muted"><?php echo htmlspecialchars($mitglied['mitgliedsnummer']); ?></span>
                        </small>
                        <?php else: ?>
                        <small class="text-muted">
                            <i class="bi bi-dash-circle"></i> Nicht verknüpft
                        </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['aktiv']): ?>
                        <span class="badge bg-success">Aktiv</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Inaktiv</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['letzter_login']): ?>
                        <small><?php echo date('d.m.Y H:i', strtotime($user['letzter_login'])); ?></small>
                        <?php else: ?>
                        <small class="text-muted">Noch nie</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="benutzer_bearbeiten.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ($user['id'] != Session::getUserId()): ?>
                        <a href="benutzer_loeschen.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger"
                           onclick="return confirmDelete('Benutzer wirklich löschen?')">
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
                <h5 class="mb-0">Rollenberechtigungen</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Übersicht der Berechtigungen nach Rolle. 
                    <a href="rollen.php">Rollen verwalten</a> | 
                    Zum Ändern der Berechtigungen: Rolle auswählen → Berechtigungen bearbeiten
                </p>
                
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Rolle</th>
                                <?php foreach ($module as $modul_key => $modul_name): ?>
                                <th class="text-center"><?php echo $modul_name; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rollen as $rolle): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo htmlspecialchars($rolle['farbe']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($rolle['name'])); ?>
                                    </span>
                                    <?php if ($rolle['ist_admin']): ?>
                                    <small class="text-muted">(Admin)</small>
                                    <?php endif; ?>
                                </td>
                                <?php foreach ($module as $modul_key => $modul_name): ?>
                                <td class="text-center">
                                    <?php 
                                    if ($rolle['ist_admin']) {
                                        // Admin hat immer alle Rechte
                                        echo '<i class="bi bi-check-circle-fill text-success"></i> <small>Voll</small>';
                                    } elseif (isset($berechtigungen_map[$rolle['name']][$modul_key])) {
                                        $perm = $berechtigungen_map[$rolle['name']][$modul_key];
                                        
                                        if ($perm['loeschen']) {
                                            echo '<i class="bi bi-check-circle-fill text-success"></i> <small>Voll</small>';
                                        } elseif ($perm['schreiben']) {
                                            echo '<i class="bi bi-pencil-fill text-primary"></i> <small>Schreiben</small>';
                                        } elseif ($perm['lesen']) {
                                            echo '<i class="bi bi-eye-fill text-info"></i> <small>Lesen</small>';
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Legende:</strong>
                        <i class="bi bi-check-circle-fill text-success"></i> Voll = Lesen, Schreiben, Löschen | 
                        <i class="bi bi-pencil-fill text-primary"></i> Schreiben = Lesen, Erstellen, Bearbeiten | 
                        <i class="bi bi-eye-fill text-info"></i> Lesen = Nur ansehen
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.badge.bg-purple {
    background-color: #6f42c1 !important;
}

.table-sm td {
    vertical-align: middle;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}
</style>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#benutzerTable').DataTable({
        order: [[0, 'asc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/de-DE.json'
        }
    });
});
</script>
