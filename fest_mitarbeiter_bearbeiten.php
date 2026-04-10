<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'schreiben');

$maObj   = new FestMitarbeiter();
$festObj = new Fest();

$id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$festId  = isset($_GET['fest_id']) ? (int)$_GET['fest_id'] : null;
$isEdit  = $id !== null;

if ($isEdit) {
    $ma = $maObj->getById($id);
    if (!$ma) { Session::setFlashMessage('danger', 'Mitarbeiter nicht gefunden.'); header('Location: feste.php'); exit; }
    $festId = $ma['fest_id'];
} else {
    $ma = [];
}

$fest = $festObj->getById($festId);
if (!$fest) { Session::setFlashMessage('danger', 'Fest nicht gefunden.'); header('Location: feste.php'); exit; }

$freie = $maObj->getFreieMitglieder($festId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $istExtern = isset($_POST['ist_extern']) && $_POST['ist_extern'] === '1';
    $data = [
        'fest_id'    => $festId,
        'mitglied_id'=> $istExtern ? null : ($_POST['mitglied_id'] ?? null),
        'vorname'    => trim($_POST['vorname'] ?? ''),
        'nachname'   => trim($_POST['nachname'] ?? ''),
        'telefon'    => trim($_POST['telefon'] ?? ''),
        'email'      => trim($_POST['email'] ?? ''),
        'funktion'   => trim($_POST['funktion'] ?? ''),
        'ist_extern' => $istExtern ? 1 : 0,
        'notizen'    => trim($_POST['notizen'] ?? ''),
    ];

    $fehler = false;
    if (!$istExtern && empty($data['mitglied_id'])) {
        $error = 'Bitte ein Vereinsmitglied auswählen.'; $fehler = true;
    }
    if ($istExtern && (empty($data['vorname']) || empty($data['nachname']))) {
        $error = 'Bitte Vor- und Nachname eingeben.'; $fehler = true;
    }

    if (!$fehler) {
        try {
            if ($isEdit) {
                $maObj->update($id, $data);
                Session::setFlashMessage('success', 'Mitarbeiter aktualisiert.');
            } else {
                $maObj->create($data);
                Session::setFlashMessage('success', 'Mitarbeiter hinzugefügt.');
            }
            header('Location: fest_mitarbeiter.php?fest_id=' . $festId); exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$istExternForm = ($ma['ist_extern'] ?? false) ? '1' : '0';
if (isset($_POST['ist_extern'])) $istExternForm = $_POST['ist_extern'];

include 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="feste.php">Feste</a></li>
        <li class="breadcrumb-item"><a href="fest_detail.php?id=<?php echo $festId; ?>"><?php echo htmlspecialchars($fest['name']); ?></a></li>
        <li class="breadcrumb-item"><a href="fest_mitarbeiter.php?fest_id=<?php echo $festId; ?>">Mitarbeiter</a></li>
        <li class="breadcrumb-item active"><?php echo $isEdit ? 'Bearbeiten' : 'Neu'; ?></li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-person-plus"></i> <?php echo $isEdit ? 'Mitarbeiter bearbeiten' : 'Mitarbeiter hinzufügen'; ?></h1>
    <a href="fest_mitarbeiter.php?fest_id=<?php echo $festId; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Mitarbeiter</h5></div>
                <div class="card-body">
                    <!-- Typ-Toggle -->
                    <div class="mb-4">
                        <label class="form-label">Typ <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ist_extern" id="typ_intern" value="0"
                                       <?php echo $istExternForm === '0' ? 'checked' : ''; ?> onchange="toggleTyp()">
                                <label class="form-check-label" for="typ_intern">
                                    <i class="bi bi-person-check text-success"></i> Vereinsmitglied
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="ist_extern" id="typ_extern" value="1"
                                       <?php echo $istExternForm === '1' ? 'checked' : ''; ?> onchange="toggleTyp()">
                                <label class="form-check-label" for="typ_extern">
                                    <i class="bi bi-person-plus text-warning"></i> Externe Person
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Intern: Mitglied-Auswahl -->
                    <div id="block_intern" <?php echo $istExternForm === '1' ? 'style="display:none"' : ''; ?>>
                        <div class="mb-3">
                            <label for="mitglied_id" class="form-label">Vereinsmitglied</label>
                            <select class="form-select" id="mitglied_id" name="mitglied_id">
                                <option value="">-- Mitglied auswählen --</option>
                                <?php
                                // Bei Edit: aktuelles Mitglied in die Liste einfügen
                                $currentMitglied = null;
                                if ($isEdit && !empty($ma['mitglied_id'])) {
                                    $db = Database::getInstance();
                                    $currentMitglied = $db->fetchOne("SELECT id, vorname, nachname, mitgliedsnummer FROM mitglieder WHERE id = ?", [$ma['mitglied_id']]);
                                }
                                if ($currentMitglied): ?>
                                <option value="<?php echo $currentMitglied['id']; ?>" selected>
                                    <?php echo htmlspecialchars($currentMitglied['nachname'] . ' ' . $currentMitglied['vorname'] . ' (' . $currentMitglied['mitgliedsnummer'] . ')'); ?>
                                </option>
                                <?php endif; ?>
                                <?php foreach ($freie as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo ($_POST['mitglied_id'] ?? '') == $m['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['nachname'] . ' ' . $m['vorname'] . ' (' . $m['mitgliedsnummer'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Extern: Name-Felder -->
                    <div id="block_extern" <?php echo $istExternForm === '0' ? 'style="display:none"' : ''; ?>>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vorname" class="form-label">Vorname</label>
                                <input type="text" class="form-control" id="vorname" name="vorname"
                                       value="<?php echo htmlspecialchars($_POST['vorname'] ?? $ma['vorname'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nachname" class="form-label">Nachname</label>
                                <input type="text" class="form-control" id="nachname" name="nachname"
                                       value="<?php echo htmlspecialchars($_POST['nachname'] ?? $ma['nachname'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="telefon" name="telefon"
                                       value="<?php echo htmlspecialchars($_POST['telefon'] ?? $ma['telefon'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">E-Mail</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? $ma['email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Gemeinsame Felder -->
                    <div class="mb-3">
                        <label for="funktion" class="form-label">Funktion / Rolle beim Fest</label>
                        <input type="text" class="form-control" id="funktion" name="funktion"
                               value="<?php echo htmlspecialchars($_POST['funktion'] ?? $ma['funktion'] ?? ''); ?>"
                               placeholder="z.B. Stationsleiter, Helfer, Kassierer, Springer"
                               list="funktionList">
                        <datalist id="funktionList">
                            <option value="Stationsleiter">
                            <option value="Helfer">
                            <option value="Kassierer">
                            <option value="Springer">
                            <option value="Auf-/Abbau">
                            <option value="Sicherheit">
                            <option value="Küche">
                            <option value="Einlass">
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label for="notizen" class="form-label">Notizen</label>
                        <textarea class="form-control" id="notizen" name="notizen" rows="2"><?php echo htmlspecialchars($_POST['notizen'] ?? $ma['notizen'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="fest_mitarbeiter.php?fest_id=<?php echo $festId; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Abbrechen
                        </a>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>
<script>
function toggleTyp() {
    var extern = document.querySelector('input[name="ist_extern"]:checked').value === '1';
    document.getElementById('block_intern').style.display = extern ? 'none' : '';
    document.getElementById('block_extern').style.display = extern ? '' : 'none';
}
</script>
