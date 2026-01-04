<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'schreiben');

$db = Database::getInstance();
$id = $_GET['id'] ?? null;
$isEdit = !empty($id);

if ($isEdit) {
    $transaktion = $db->fetchOne("SELECT * FROM finanzen WHERE id = ?", [$id]);
    if (!$transaktion) {
        Session::setFlashMessage('danger', 'Transaktion nicht gefunden');
        header('Location: finanzen.php');
        exit;
    }
} else {
    $transaktion = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'typ' => $_POST['typ'],
        'datum' => $_POST['datum'],
        'betrag' => $_POST['betrag'],
        'kategorie' => $_POST['kategorie'] ?? null,
        'beschreibung' => $_POST['beschreibung'] ?? null,
        'beleg_nummer' => $_POST['beleg_nummer'] ?? null,
        'zahlungsart' => $_POST['zahlungsart'] ?? null
    ];
    
    try {
        if ($isEdit) {
            $db->execute(
                "UPDATE finanzen SET typ=?, datum=?, betrag=?, kategorie=?, beschreibung=?, beleg_nummer=?, zahlungsart=? WHERE id=?",
                [$data['typ'], $data['datum'], $data['betrag'], $data['kategorie'], $data['beschreibung'], 
                 $data['beleg_nummer'], $data['zahlungsart'], $id]
            );
            Session::setFlashMessage('success', 'Transaktion aktualisiert');
        } else {
            $db->execute(
                "INSERT INTO finanzen (typ, datum, betrag, kategorie, beschreibung, beleg_nummer, zahlungsart, erstellt_von) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$data['typ'], $data['datum'], $data['betrag'], $data['kategorie'], $data['beschreibung'], 
                 $data['beleg_nummer'], $data['zahlungsart'], Session::getUserId()]
            );
            Session::setFlashMessage('success', 'Transaktion erstellt');
        }
        header('Location: finanzen.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-cash-coin"></i> <?php echo $isEdit ? 'Transaktion bearbeiten' : 'Neue Transaktion'; ?></h1>
    <a href="finanzen.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Zurück</a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="typ" class="form-label">Typ *</label>
                    <select class="form-select" id="typ" name="typ" required>
                        <option value="einnahme" <?php echo ($transaktion['typ'] ?? '') === 'einnahme' ? 'selected' : ''; ?>>Einnahme</option>
                        <option value="ausgabe" <?php echo ($transaktion['typ'] ?? '') === 'ausgabe' ? 'selected' : ''; ?>>Ausgabe</option>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="datum" class="form-label">Datum *</label>
                    <input type="date" class="form-control" id="datum" name="datum" 
                           value="<?php echo htmlspecialchars($transaktion['datum'] ?? date('Y-m-d')); ?>" required>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="betrag" class="form-label">Betrag (€) *</label>
                    <input type="number" class="form-control" id="betrag" name="betrag" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($transaktion['betrag'] ?? ''); ?>" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="kategorie" class="form-label">Kategorie</label>
                    <input type="text" class="form-control" id="kategorie" name="kategorie" 
                           value="<?php echo htmlspecialchars($transaktion['kategorie'] ?? ''); ?>"
                           placeholder="z.B. Noten, Instrumente, Fest">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="zahlungsart" class="form-label">Zahlungsart</label>
                    <select class="form-select" id="zahlungsart" name="zahlungsart">
                        <option value="">Bitte wählen</option>
                        <option value="bar" <?php echo ($transaktion['zahlungsart'] ?? '') === 'bar' ? 'selected' : ''; ?>>Bar</option>
                        <option value="überweisung" <?php echo ($transaktion['zahlungsart'] ?? '') === 'überweisung' ? 'selected' : ''; ?>>Überweisung</option>
                        <option value="lastschrift" <?php echo ($transaktion['zahlungsart'] ?? '') === 'lastschrift' ? 'selected' : ''; ?>>Lastschrift</option>
                        <option value="kreditkarte" <?php echo ($transaktion['zahlungsart'] ?? '') === 'kreditkarte' ? 'selected' : ''; ?>>Kreditkarte</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="beleg_nummer" class="form-label">Belegnummer</label>
                <input type="text" class="form-control" id="beleg_nummer" name="beleg_nummer" 
                       value="<?php echo htmlspecialchars($transaktion['beleg_nummer'] ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="beschreibung" class="form-label">Beschreibung</label>
                <textarea class="form-control" id="beschreibung" name="beschreibung" rows="3"><?php echo htmlspecialchars($transaktion['beschreibung'] ?? ''); ?></textarea>
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="finanzen.php" class="btn btn-secondary"><i class="bi bi-x"></i> Abbrechen</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> <?php echo $isEdit ? 'Aktualisieren' : 'Erstellen'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
