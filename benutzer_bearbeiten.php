<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (Session::getRole() !== 'admin') {
    Session::setFlashMessage('danger', 'Keine Berechtigung');
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();
$id = $_GET['id'] ?? null;
$isEdit = !empty($id);

// Alle aktiven Rollen laden
$rollen = $db->fetchAll("SELECT * FROM rollen WHERE aktiv = 1 ORDER BY sortierung, name");

// Alle Mitglieder für die Verknüpfung laden
$mitglieder = $db->fetchAll("
    SELECT id, mitgliedsnummer, vorname, nachname, status 
    FROM mitglieder 
    WHERE status IN ('aktiv', 'passiv')
    ORDER BY nachname, vorname
");

if ($isEdit) {
    $benutzer = $db->fetchOne("SELECT * FROM benutzer WHERE id = ?", [$id]);
    if (!$benutzer) {
        Session::setFlashMessage('danger', 'Benutzer nicht gefunden');
        header('Location: benutzer.php');
        exit;
    }
    // Aktuelle Rollen des Benutzers laden
    $benutzerRollenIds = array_column(
        $db->fetchAll("SELECT rolle_id FROM benutzer_rollen WHERE benutzer_id = ?", [$id]),
        'rolle_id'
    );
} else {
    $benutzer = [];
    $benutzerRollenIds = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzername   = $_POST['benutzername'];
    $email          = $_POST['email'];
    $gewaehlteRollen = array_map('intval', $_POST['rollen'] ?? []);
    $mitglied_id    = !empty($_POST['mitglied_id']) ? $_POST['mitglied_id'] : null;
    $aktiv          = isset($_POST['aktiv']) ? 1 : 0;
    $neues_passwort = $_POST['passwort'] ?? '';

    try {
        if (empty($gewaehlteRollen)) {
            throw new Exception('Mindestens eine Rolle muss ausgewählt werden.');
        }

        // Primärrolle = erste ausgewählte Rolle
        $primaerRolleId = $gewaehlteRollen[0];
        $rolleData = $db->fetchOne("SELECT name FROM rollen WHERE id = ?", [$primaerRolleId]);
        $rolleName = $rolleData['name'] ?? 'mitglied';

        if ($isEdit) {
            $sql = "UPDATE benutzer SET benutzername=?, email=?, rolle=?, rolle_id=?, mitglied_id=?, aktiv=? WHERE id=?";
            $params = [$benutzername, $email, $rolleName, $primaerRolleId, $mitglied_id, $aktiv, $id];
            if (!empty($neues_passwort)) {
                $sql = "UPDATE benutzer SET benutzername=?, email=?, rolle=?, rolle_id=?, mitglied_id=?, aktiv=?, passwort_hash=? WHERE id=?";
                $params = [$benutzername, $email, $rolleName, $primaerRolleId, $mitglied_id, $aktiv, password_hash($neues_passwort, PASSWORD_DEFAULT), $id];
            }
            $db->execute($sql, $params);
            $benutzerId = (int)$id;
        } else {
            if (empty($neues_passwort)) throw new Exception('Passwort ist erforderlich');
            $db->execute(
                "INSERT INTO benutzer (benutzername, email, passwort_hash, rolle, rolle_id, mitglied_id, aktiv) VALUES (?,?,?,?,?,?,?)",
                [$benutzername, $email, password_hash($neues_passwort, PASSWORD_DEFAULT), $rolleName, $primaerRolleId, $mitglied_id, $aktiv]
            );
            $benutzerId = $db->lastInsertId();
        }

        // Pivot-Tabelle neu schreiben
        $db->execute("DELETE FROM benutzer_rollen WHERE benutzer_id = ?", [$benutzerId]);
        foreach ($gewaehlteRollen as $rId) {
            $db->execute("INSERT INTO benutzer_rollen (benutzer_id, rolle_id) VALUES (?,?)", [$benutzerId, $rId]);
        }

        Session::setFlashMessage('success', $isEdit ? 'Benutzer aktualisiert' : 'Benutzer erstellt');
        header('Location: benutzer.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-person-gear"></i> <?php echo $isEdit ? 'Benutzer bearbeiten' : 'Neuer Benutzer'; ?></h1>
    <a href="benutzer.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="benutzername" class="form-label">Benutzername *</label>
                    <input type="text" class="form-control" id="benutzername" name="benutzername" 
                           value="<?php echo htmlspecialchars($benutzer['benutzername'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">E-Mail *</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($benutzer['email'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="passwort" class="form-label">
                        Passwort <?php echo $isEdit ? '(leer lassen für keine Änderung)' : '*'; ?>
                    </label>
                    <input type="password" class="form-control" id="passwort" name="passwort" 
                           <?php echo $isEdit ? '' : 'required'; ?>>
                    <small class="text-muted">Mindestens 6 Zeichen</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Rollen * <small class="text-muted">(mind. eine, erste = primär)</small></label>
                    <div class="border rounded p-2" style="max-height:180px;overflow-y:auto">
                        <?php foreach ($rollen as $rolle): ?>
                        <?php $checked = in_array($rolle['id'], $benutzerRollenIds) ? 'checked' : ''; ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="rollen[]"
                                   value="<?php echo $rolle['id']; ?>"
                                   id="rolle_<?php echo $rolle['id']; ?>"
                                   <?php echo $checked; ?>>
                            <label class="form-check-label d-flex align-items-center gap-2" for="rolle_<?php echo $rolle['id']; ?>">
                                <span class="badge bg-<?php echo htmlspecialchars($rolle['farbe'] ?? 'secondary'); ?>" style="font-size:10px">
                                    <?php echo htmlspecialchars(ucfirst($rolle['name'])); ?>
                                </span>
                                <?php if ($rolle['ist_admin']): ?>
                                <small class="text-danger"><i class="bi bi-shield-fill"></i> Admin</small>
                                <?php endif; ?>
                                <?php if (!empty($rolle['beschreibung'])): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($rolle['beschreibung']); ?></small>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted">
                        <a href="rollen.php" target="_blank">Rollen verwalten</a>
                    </small>
                </div>
            </div>
            
            <!-- Mitglied-Verknüpfung -->
            <div class="mb-3">
                <label for="mitglied_id" class="form-label">
                    Zugeordnetes Mitglied (optional)
                    <i class="bi bi-info-circle text-muted" title="Ermöglicht dem Benutzer sich bei Ausrückungen anzumelden"></i>
                </label>
                <select class="form-select" id="mitglied_id" name="mitglied_id">
                    <option value="">-- Kein Mitglied zugeordnet --</option>
                    <?php foreach ($mitglieder as $mitglied): ?>
                    <option value="<?php echo $mitglied['id']; ?>" 
                            <?php echo ($benutzer['mitglied_id'] ?? '') == $mitglied['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($mitglied['nachname'] . ' ' . $mitglied['vorname']); ?>
                        (<?php echo htmlspecialchars($mitglied['mitgliedsnummer']); ?>)
                        - <?php echo ucfirst($mitglied['status']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">
                    Wenn zugeordnet, kann sich der Benutzer selbst bei Ausrückungen an/abmelden.
                    Admin-Benutzer müssen nicht zugeordnet werden.
                </small>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="aktiv" name="aktiv" 
                       <?php echo ($benutzer['aktiv'] ?? 1) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="aktiv">Benutzer ist aktiv</label>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="benutzer.php" class="btn btn-secondary"><i class="bi bi-x"></i> Abbrechen</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?php echo $isEdit ? 'Aktualisieren' : 'Erstellen'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
