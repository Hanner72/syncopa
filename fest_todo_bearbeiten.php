<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'schreiben');

$todoObj = new FestTodo();
$festObj = new Fest();

$id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$festId  = isset($_GET['fest_id']) ? (int)$_GET['fest_id'] : null;
$isEdit  = $id !== null;

if ($isEdit) {
    $todo = $todoObj->getById($id);
    if (!$todo) { Session::setFlashMessage('danger', 'Todo nicht gefunden.'); header('Location: fest_todos.php'); exit; }
    $festId = $todo['fest_id'];
} else {
    $todo = [];
}

if ($festId) {
    $fest = $festObj->getById($festId);
    if (!$fest) { Session::setFlashMessage('danger', 'Fest nicht gefunden.'); header('Location: feste.php'); exit; }
}

// Alle Feste für Dropdown (falls kein Fest vorgewählt)
$alleFeste   = $festObj->getAll();
// Benutzer für Zuweisung
$db          = Database::getInstance();
$benutzerListe = $db->fetchAll("SELECT id, benutzername FROM benutzer WHERE aktiv = 1 ORDER BY benutzername");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'fest_id'       => (int)$_POST['fest_id'],
        'titel'         => trim($_POST['titel'] ?? ''),
        'beschreibung'  => trim($_POST['beschreibung'] ?? ''),
        'faellig_am'    => $_POST['faellig_am'] ?? '',
        'zustaendig_id' => !empty($_POST['zustaendig_id']) ? (int)$_POST['zustaendig_id'] : null,
        'status'        => $_POST['status'] ?? 'offen',
        'prioritaet'    => $_POST['prioritaet'] ?? 'normal',
        'erstellt_von'  => Session::getUserId(),
    ];

    if (empty($data['titel'])) {
        $error = 'Bitte einen Titel eingeben.';
    } elseif (empty($data['fest_id'])) {
        $error = 'Bitte ein Fest zuweisen.';
    } else {
        try {
            if ($isEdit) {
                $todoObj->update($id, $data);
                Session::setFlashMessage('success', 'Todo aktualisiert.');
            } else {
                $todoObj->create($data);
                Session::setFlashMessage('success', 'Todo angelegt.');
            }
            header('Location: fest_todos.php?fest_id=' . $data['fest_id']); exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="fest_todos.php">Todos</a></li>
        <?php if ($festId && isset($fest)): ?>
        <li class="breadcrumb-item"><a href="fest_todos.php?fest_id=<?php echo $festId; ?>"><?php echo htmlspecialchars($fest['name']); ?></a></li>
        <?php endif; ?>
        <li class="breadcrumb-item active"><?php echo $isEdit ? 'Bearbeiten' : 'Neu'; ?></li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-check2-square"></i> <?php echo $isEdit ? 'Todo bearbeiten' : 'Todo anlegen'; ?></h1>
    <a href="fest_todos.php<?php echo $festId ? '?fest_id='.$festId : ''; ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Zurück
    </a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Aufgabe</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="fest_id" class="form-label">Fest <span class="text-danger">*</span></label>
                        <select class="form-select" id="fest_id" name="fest_id" required>
                            <option value="">-- Fest wählen --</option>
                            <?php foreach ($alleFeste as $f): ?>
                            <option value="<?php echo $f['id']; ?>"
                                    <?php echo ($todo['fest_id'] ?? $festId) == $f['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($f['name'] . ' (' . $f['jahr'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="titel" class="form-label">Titel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="titel" name="titel" required
                               value="<?php echo htmlspecialchars($todo['titel'] ?? ''); ?>"
                               placeholder="Kurze, präzise Beschreibung der Aufgabe">
                    </div>
                    <div class="mb-3">
                        <label for="beschreibung" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="beschreibung" name="beschreibung" rows="4"
                                  placeholder="Detaillierte Beschreibung, Hinweise, Links..."><?php echo htmlspecialchars($todo['beschreibung'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="fest_todos.php<?php echo $festId ? '?fest_id='.$festId : ''; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Abbrechen
                        </a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Details</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="prioritaet" class="form-label">Priorität</label>
                        <select class="form-select" id="prioritaet" name="prioritaet">
                            <option value="niedrig"  <?php echo ($todo['prioritaet'] ?? 'normal') === 'niedrig'  ? 'selected' : ''; ?>>Niedrig</option>
                            <option value="normal"   <?php echo ($todo['prioritaet'] ?? 'normal') === 'normal'   ? 'selected' : ''; ?>>Normal</option>
                            <option value="hoch"     <?php echo ($todo['prioritaet'] ?? '') === 'hoch'     ? 'selected' : ''; ?>>Hoch</option>
                            <option value="kritisch" <?php echo ($todo['prioritaet'] ?? '') === 'kritisch' ? 'selected' : ''; ?>>Kritisch</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="offen"       <?php echo ($todo['status'] ?? 'offen') === 'offen'       ? 'selected' : ''; ?>>Offen</option>
                            <option value="in_arbeit"   <?php echo ($todo['status'] ?? '') === 'in_arbeit'   ? 'selected' : ''; ?>>In Arbeit</option>
                            <option value="erledigt"    <?php echo ($todo['status'] ?? '') === 'erledigt'    ? 'selected' : ''; ?>>Erledigt</option>
                            <option value="abgebrochen" <?php echo ($todo['status'] ?? '') === 'abgebrochen' ? 'selected' : ''; ?>>Abgebrochen</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="faellig_am" class="form-label">Fällig am</label>
                        <input type="date" class="form-control" id="faellig_am" name="faellig_am"
                               value="<?php echo htmlspecialchars($todo['faellig_am'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="zustaendig_id" class="form-label">Zuständig</label>
                        <select class="form-select" id="zustaendig_id" name="zustaendig_id">
                            <option value="">– Niemand –</option>
                            <?php foreach ($benutzerListe as $b): ?>
                            <option value="<?php echo $b['id']; ?>"
                                    <?php echo ($todo['zustaendig_id'] ?? '') == $b['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['benutzername']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
