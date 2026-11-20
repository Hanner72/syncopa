<?php
// rolle_bearbeiten.php
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

if ($isEdit) {
    $rolle = $db->fetchOne("SELECT * FROM rollen WHERE id = ?", [$id]);
    if (!$rolle) {
        Session::setFlashMessage('danger', 'Rolle nicht gefunden');
        header('Location: rollen.php');
        exit;
    }
} else {
    $rolle = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name']),
        'beschreibung' => $_POST['beschreibung'] ?? null,
        'ist_admin' => isset($_POST['ist_admin']) ? 1 : 0,
        'farbe' => RollenFarbe::pruefen($_POST['farbe'] ?? '') ?? RollenFarbe::hex($rolle['farbe'] ?? 'secondary'),
        'sortierung' => $_POST['sortierung'] ?? 100,
        'aktiv' => isset($_POST['aktiv']) ? 1 : 0
    ];
    
    // Name validieren
    if (empty($data['name'])) {
        $error = 'Name ist erforderlich';
    } elseif (!preg_match('/^[a-z0-9_-]+$/i', $data['name'])) {
        $error = 'Name darf nur Buchstaben, Zahlen, - und _ enthalten';
    } else {
        try {
            if ($isEdit) {
                // Admin-Flag nicht änderbar für bestehende Admin-Rolle
                if ($rolle['ist_admin']) {
                    unset($data['ist_admin']);
                }
                
                $sql = "UPDATE rollen SET name=?, beschreibung=?, farbe=?, sortierung=?, aktiv=? WHERE id=?";
                $params = [$data['name'], $data['beschreibung'], $data['farbe'], $data['sortierung'], $data['aktiv'], $id];
                
                if (!$rolle['ist_admin']) {
                    $sql = "UPDATE rollen SET name=?, beschreibung=?, ist_admin=?, farbe=?, sortierung=?, aktiv=? WHERE id=?";
                    $params = [$data['name'], $data['beschreibung'], $data['ist_admin'], $data['farbe'], $data['sortierung'], $data['aktiv'], $id];
                }
                
                $db->execute($sql, $params);
                Session::setFlashMessage('success', 'Rolle aktualisiert');
            } else {
                $sql = "INSERT INTO rollen (name, beschreibung, ist_admin, farbe, sortierung, aktiv) VALUES (?, ?, ?, ?, ?, ?)";
                $db->execute($sql, [$data['name'], $data['beschreibung'], $data['ist_admin'], $data['farbe'], $data['sortierung'], $data['aktiv']]);
                Session::setFlashMessage('success', 'Rolle erstellt');
            }
            header('Location: rollen.php');
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Farben: aktuelle, die der anderen Rollen und Vorschläge
$farbeAktuell = RollenFarbe::hex($rolle['farbe'] ?? null);
if (!$isEdit) $farbeAktuell = null;
$andereFarben = $db->fetchAll("SELECT name, farbe FROM rollen WHERE aktiv = 1 AND id <> ? ORDER BY sortierung, name", [(int)($id ?? 0)]);
$farbVorschlaege = RollenFarbe::vorschlaege(array_column($andereFarben, 'farbe'));
if ($farbeAktuell === null) $farbeAktuell = $farbVorschlaege[0] ?? RollenFarbe::ALT['secondary'];

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-shield-lock"></i> <?php echo $isEdit ? 'Rolle bearbeiten' : 'Neue Rolle'; ?></h1>
    <a href="rollen.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Name *</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?php echo htmlspecialchars($rolle['name'] ?? ''); ?>" 
                           pattern="[a-zA-Z0-9_-]+" required>
                    <small class="text-muted">Nur Buchstaben, Zahlen, - und _ (z.B. "zeugwart")</small>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="farbe" class="form-label">Farbe</label>
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" class="form-control form-control-color" id="farbe" name="farbe" value="<?php echo htmlspecialchars($farbeAktuell); ?>" title="Farbe wählen">
                        <span class="badge" id="farbeVorschau" style="<?php echo RollenFarbe::stil($farbeAktuell); ?>;font-size:12px"><?php echo htmlspecialchars($rolle['name'] ?? 'rolle'); ?></span>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="sortierung" class="form-label">Sortierung</label>
                    <input type="number" class="form-control" id="sortierung" name="sortierung" 
                           value="<?php echo htmlspecialchars($rolle['sortierung'] ?? '100'); ?>">
                    <small class="text-muted">Kleinere Zahl = weiter oben</small>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="small text-muted mb-1">Vorschläge – Farben, die sich von den anderen Rollen gut unterscheiden:</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($farbVorschlaege as $vf): ?>
                    <button type="button" class="rf-vorschlag" data-farbe="<?php echo $vf; ?>" style="background:<?php echo $vf; ?>" title="<?php echo $vf; ?>"></button>
                    <?php endforeach; ?>
                </div>
                <?php if ($andereFarben): ?>
                <div class="small text-muted mt-2 mb-1">Schon vergeben:</div>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($andereFarben as $af): ?>
                    <span class="badge" style="<?php echo RollenFarbe::stil($af['farbe']); ?>"><?php echo htmlspecialchars($af['name']); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="beschreibung" class="form-label">Beschreibung</label>
                <textarea class="form-control" id="beschreibung" name="beschreibung" rows="2"><?php echo htmlspecialchars($rolle['beschreibung'] ?? ''); ?></textarea>
            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="aktiv" name="aktiv" 
                           <?php echo ($rolle['aktiv'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="aktiv">
                        Rolle ist aktiv
                    </label>
                </div>
                
                <?php if (!$isEdit || !$rolle['ist_admin']): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="ist_admin" name="ist_admin" 
                           <?php echo ($rolle['ist_admin'] ?? 0) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="ist_admin">
                        <strong class="text-danger">Administrator-Rolle</strong> (volle Rechte)
                    </label>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Die Admin-Rolle kann nicht zu einer normalen Rolle geändert werden.
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($isEdit): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                Nach dem Speichern können Sie die <a href="berechtigungen_bearbeiten.php?rolle_id=<?php echo $id; ?>">Berechtigungen</a> für diese Rolle festlegen.
            </div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between page-actions-sticky">
                <a href="rollen.php" class="btn btn-secondary"><i class="bi bi-x"></i> Abbrechen</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?php echo $isEdit ? 'Aktualisieren' : 'Erstellen'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.badge.bg-purple {
    background-color: #6f42c1 !important;
}
</style>

<style>
.rf-vorschlag { width: 30px; height: 30px; border-radius: 6px; border: 2px solid rgba(0,0,0,.08); cursor: pointer; padding: 0; }
.rf-vorschlag:hover { transform: scale(1.12); }
.rf-vorschlag.aktiv { outline: 2px solid var(--c-primary); outline-offset: 2px; }
</style>
<script>
(function () {
    var feld = document.getElementById('farbe'), vorschau = document.getElementById('farbeVorschau'), name = document.getElementById('name');
    if (!feld) return;
    function textfarbe(hex) {
        var c = [1, 3, 5].map(function (i) { var v = parseInt(hex.substr(i, 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
        var l = 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
        return 1.05 / (l + 0.05) >= 3 ? '#ffffff' : '#1f2937';
    }
    function zeigen() {
        vorschau.style.backgroundColor = feld.value;
        vorschau.style.color = textfarbe(feld.value);
        document.querySelectorAll('.rf-vorschlag').forEach(function (b) { b.classList.toggle('aktiv', b.dataset.farbe === feld.value.toLowerCase()); });
    }
    feld.addEventListener('input', zeigen);
    if (name) name.addEventListener('input', function () { vorschau.textContent = name.value || 'rolle'; });
    document.querySelectorAll('.rf-vorschlag').forEach(function (b) {
        b.addEventListener('click', function () { feld.value = b.dataset.farbe; zeigen(); });
    });
    zeigen();
})();
</script>

<?php include 'includes/footer.php'; ?>
