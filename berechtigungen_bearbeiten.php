<?php
// berechtigungen_bearbeiten.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (Session::getRole() !== 'admin') {
    Session::setFlashMessage('danger', 'Keine Berechtigung');
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();
$rolle_id = $_GET['rolle_id'] ?? null;

if (!$rolle_id) {
    Session::setFlashMessage('danger', 'Rolle nicht angegeben');
    header('Location: rollen.php');
    exit;
}

$rolle = $db->fetchOne("SELECT * FROM rollen WHERE id = ?", [$rolle_id]);
if (!$rolle) {
    Session::setFlashMessage('danger', 'Rolle nicht gefunden');
    header('Location: rollen.php');
    exit;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Erst alle Berechtigungen für diese Rolle löschen
        $db->execute("DELETE FROM berechtigungen WHERE rolle = ?", [$rolle['name']]);
        
        // Neue Berechtigungen speichern
        foreach ($module as $modul_key => $modul_name) {
            $lesen = isset($_POST[$modul_key . '_lesen']) ? 1 : 0;
            $schreiben = isset($_POST[$modul_key . '_schreiben']) ? 1 : 0;
            $loeschen = isset($_POST[$modul_key . '_loeschen']) ? 1 : 0;
            
            // Nur speichern wenn mindestens eine Berechtigung gesetzt
            if ($lesen || $schreiben || $loeschen) {
                $db->execute(
                    "INSERT INTO berechtigungen (rolle, modul, lesen, schreiben, loeschen) VALUES (?, ?, ?, ?, ?)",
                    [$rolle['name'], $modul_key, $lesen, $schreiben, $loeschen]
                );
            }
        }
        
        Session::setFlashMessage('success', 'Berechtigungen gespeichert');
        header('Location: berechtigungen_bearbeiten.php?rolle_id=' . $rolle_id);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Aktuelle Berechtigungen laden
$berechtigungen = $db->fetchAll("SELECT * FROM berechtigungen WHERE rolle = ?", [$rolle['name']]);
$berechtigungen_map = [];
foreach ($berechtigungen as $b) {
    $berechtigungen_map[$b['modul']] = $b;
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">
        <i class="bi bi-key"></i> Berechtigungen: 
        <span class="badge bg-<?php echo htmlspecialchars($rolle['farbe']); ?>">
            <?php echo htmlspecialchars($rolle['name']); ?>
        </span>
    </h1>
    <a href="rollen.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($rolle['ist_admin']): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Administrator-Rolle:</strong> Hat automatisch volle Rechte auf alle Module.
    Berechtigungen können hier nicht geändert werden.
</div>
<?php else: ?>

<div class="card">
    <div class="card-body">
        <p class="text-muted">
            Legen Sie fest, welche Berechtigungen die Rolle "<strong><?php echo htmlspecialchars($rolle['name']); ?></strong>" hat.
        </p>
        
        <form method="POST">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Modul</th>
                            <th class="text-center" style="width: 120px;">
                                <i class="bi bi-eye"></i> Lesen
                            </th>
                            <th class="text-center" style="width: 120px;">
                                <i class="bi bi-pencil"></i> Schreiben
                            </th>
                            <th class="text-center" style="width: 120px;">
                                <i class="bi bi-trash"></i> Löschen
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($module as $modul_key => $modul_name): ?>
                        <?php
                        $berechtigung = $berechtigungen_map[$modul_key] ?? null;
                        $lesen = $berechtigung ? $berechtigung['lesen'] : 0;
                        $schreiben = $berechtigung ? $berechtigung['schreiben'] : 0;
                        $loeschen = $berechtigung ? $berechtigung['loeschen'] : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($modul_name); ?></strong></td>
                            <td class="text-center">
                                <div class="form-check d-inline-block">
                                    <input class="form-check-input modul-check" type="checkbox" 
                                           id="<?php echo $modul_key; ?>_lesen" 
                                           name="<?php echo $modul_key; ?>_lesen"
                                           data-modul="<?php echo $modul_key; ?>"
                                           <?php echo $lesen ? 'checked' : ''; ?>>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="form-check d-inline-block">
                                    <input class="form-check-input modul-check" type="checkbox" 
                                           id="<?php echo $modul_key; ?>_schreiben" 
                                           name="<?php echo $modul_key; ?>_schreiben"
                                           data-modul="<?php echo $modul_key; ?>"
                                           data-requires="lesen"
                                           <?php echo $schreiben ? 'checked' : ''; ?>>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="form-check d-inline-block">
                                    <input class="form-check-input modul-check" type="checkbox" 
                                           id="<?php echo $modul_key; ?>_loeschen" 
                                           name="<?php echo $modul_key; ?>_loeschen"
                                           data-modul="<?php echo $modul_key; ?>"
                                           data-requires="schreiben"
                                           <?php echo $loeschen ? 'checked' : ''; ?>>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <strong>Hinweis:</strong> 
                <ul class="mb-0">
                    <li><strong>Lesen:</strong> Daten ansehen</li>
                    <li><strong>Schreiben:</strong> Erstellen und Bearbeiten (erfordert Lesen)</li>
                    <li><strong>Löschen:</strong> Daten löschen (erfordert Schreiben)</li>
                </ul>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="rollen.php" class="btn btn-secondary"><i class="bi bi-x"></i> Abbrechen</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Berechtigungen speichern
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Automatische Abhängigkeiten
$(document).on('change', '.modul-check', function() {
    const modul = $(this).data('modul');
    const type = $(this).attr('name').split('_').pop();
    const isChecked = $(this).is(':checked');
    
    if (isChecked) {
        // Wenn Schreiben aktiviert wird, auch Lesen aktivieren
        if (type === 'schreiben') {
            $('#' + modul + '_lesen').prop('checked', true);
        }
        // Wenn Löschen aktiviert wird, auch Schreiben und Lesen aktivieren
        if (type === 'loeschen') {
            $('#' + modul + '_schreiben').prop('checked', true);
            $('#' + modul + '_lesen').prop('checked', true);
        }
    } else {
        // Wenn Lesen deaktiviert wird, auch Schreiben und Löschen deaktivieren
        if (type === 'lesen') {
            $('#' + modul + '_schreiben').prop('checked', false);
            $('#' + modul + '_loeschen').prop('checked', false);
        }
        // Wenn Schreiben deaktiviert wird, auch Löschen deaktivieren
        if (type === 'schreiben') {
            $('#' + modul + '_loeschen').prop('checked', false);
        }
    }
});
</script>

<?php endif; ?>

<style>
.badge.bg-purple {
    background-color: #6f42c1 !important;
}
</style>

<?php include 'includes/footer.php'; ?>
