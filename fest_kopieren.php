<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'schreiben');

$festObj = new Fest();
$kopObj  = new FestKopieren();

$zielId = isset($_GET['ziel_id']) ? (int)$_GET['ziel_id'] : null;
$ziel   = $zielId ? $festObj->getById($zielId) : null;

$alleFeste = $festObj->getAll();

$step      = 1;
$ergebnis  = null;
$quellFest = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quellId   = (int)($_POST['quell_id'] ?? 0);
    $zielId    = (int)($_POST['ziel_id'] ?? 0);
    $quellFest = $festObj->getById($quellId);
    $ziel      = $festObj->getById($zielId);

    if (!$quellFest || !$ziel) {
        $error = 'Ungültige Fest-Auswahl.';
    } elseif ($quellId === $zielId) {
        $error = 'Quell- und Ziel-Fest dürfen nicht identisch sein.';
    } elseif (isset($_POST['bestaetigt'])) {
        // Schritt 3: Ausführen
        $optionen = [
            'stationen'  => !empty($_POST['opt_stationen']),
            'mitarbeiter'=> !empty($_POST['opt_mitarbeiter']),
            'einkauefe'  => !empty($_POST['opt_einkauefe']),
        ];

        try {
            $ergebnis = $kopObj->kopieren($quellId, $zielId, $optionen);
            $step = 3;
            Session::setFlashMessage('success', 'Daten erfolgreich kopiert.');
        } catch (\Throwable $e) {
            $error = 'Fehler beim Kopieren: ' . $e->getMessage();
            $step = 2;
        }
    } else {
        // Schritt 2: Bestätigungsseite
        $step = 2;
        $optionen = [
            'stationen'  => !empty($_POST['opt_stationen']),
            'mitarbeiter'=> !empty($_POST['opt_mitarbeiter']),
            'einkauefe'  => !empty($_POST['opt_einkauefe']),
        ];
    }
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-copy"></i> Daten aus Vorjahr kopieren</h1>
    <a href="<?php echo $ziel ? 'fest_detail.php?id='.$ziel['id'] : 'feste.php'; ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Zurück
    </a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Schritte-Anzeige -->
<div class="d-flex gap-3 mb-4 align-items-center">
    <span class="badge <?php echo $step >= 1 ? 'bg-primary' : 'bg-secondary'; ?> rounded-pill px-3 py-2">1 Auswahl</span>
    <i class="bi bi-arrow-right text-muted"></i>
    <span class="badge <?php echo $step >= 2 ? 'bg-primary' : 'bg-secondary'; ?> rounded-pill px-3 py-2">2 Bestätigung</span>
    <i class="bi bi-arrow-right text-muted"></i>
    <span class="badge <?php echo $step >= 3 ? 'bg-success' : 'bg-secondary'; ?> rounded-pill px-3 py-2">3 Ergebnis</span>
</div>

<?php if ($step === 1): ?>
<!-- SCHRITT 1: Auswahl -->
<div class="card">
    <div class="card-header"><h5 class="mb-0">Schritt 1: Quell- und Ziel-Fest wählen</h5></div>
    <form method="POST">
        <div class="card-body">
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label for="quell_id" class="form-label">Quell-Fest (Daten werden kopiert von)</label>
                    <select class="form-select" id="quell_id" name="quell_id" required>
                        <option value="">-- Fest wählen --</option>
                        <?php foreach ($alleFeste as $f): ?>
                        <?php if ($f['id'] !== $zielId): ?>
                        <option value="<?php echo $f['id']; ?>">
                            <?php echo htmlspecialchars($f['name'] . ' (' . $f['jahr'] . ')'); ?>
                        </option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-center justify-content-center pt-3">
                    <i class="bi bi-arrow-right fs-3 text-muted"></i>
                </div>
                <div class="col-md-5 mb-3">
                    <label for="ziel_id" class="form-label">Ziel-Fest (Daten werden eingefügt in)</label>
                    <select class="form-select" id="ziel_id" name="ziel_id" required>
                        <option value="">-- Fest wählen --</option>
                        <?php foreach ($alleFeste as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php echo $f['id'] === $zielId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['name'] . ' (' . $f['jahr'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr>
            <h6 class="mb-3">Was soll kopiert werden?</h6>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="opt_stationen" name="opt_stationen" value="1" checked>
                        <label class="form-check-label" for="opt_stationen">
                            <i class="bi bi-shop text-primary"></i> <strong>Stationen</strong>
                            <div class="form-text">Alle Stationen (ohne Dienstpläne)</div>
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="opt_mitarbeiter" name="opt_mitarbeiter" value="1" checked>
                        <label class="form-check-label" for="opt_mitarbeiter">
                            <i class="bi bi-people text-success"></i> <strong>Mitarbeiter</strong>
                            <div class="form-text">Alle Mitarbeiter-Einträge (ohne Schichtpläne)</div>
                        </label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="opt_einkauefe" name="opt_einkauefe" value="1" checked>
                        <label class="form-check-label" for="opt_einkauefe">
                            <i class="bi bi-cart3 text-warning"></i> <strong>Einkäufe (Vorlagen)</strong>
                            <div class="form-text">Nur Einträge mit <i class="bi bi-copy text-success"></i> Vorlage-Markierung, Status wird auf «Geplant» zurückgesetzt</div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-3 mb-0">
                <i class="bi bi-info-circle"></i>
                <strong>Hinweis:</strong> Dienstpläne und Verträge werden nicht kopiert – diese müssen für jedes Fest neu erstellt werden.
                Bereits vorhandene Daten im Ziel-Fest werden <strong>nicht überschrieben</strong>, sondern ergänzt.
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-arrow-right-circle"></i> Weiter zur Bestätigung
            </button>
        </div>
    </form>
</div>

<?php elseif ($step === 2 && $quellFest && $ziel): ?>
<!-- SCHRITT 2: Bestätigung -->
<div class="card">
    <div class="card-header"><h5 class="mb-0">Schritt 2: Bestätigung</h5></div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-5">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Kopiere VON</div>
                        <h5><?php echo htmlspecialchars($quellFest['name']); ?></h5>
                        <div class="text-muted"><?php echo $quellFest['jahr']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-center justify-content-center">
                <i class="bi bi-arrow-right fs-2 text-primary"></i>
            </div>
            <div class="col-md-5">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <div class="text-muted small mb-1">Kopiere IN</div>
                        <h5><?php echo htmlspecialchars($ziel['name']); ?></h5>
                        <div class="text-muted"><?php echo $ziel['jahr']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <h6>Folgende Daten werden kopiert:</h6>
        <ul>
            <?php if (!empty($optionen['stationen'])): ?><li><i class="bi bi-shop text-primary"></i> Stationen</li><?php endif; ?>
            <?php if (!empty($optionen['mitarbeiter'])): ?><li><i class="bi bi-people text-success"></i> Mitarbeiter</li><?php endif; ?>
            <?php if (!empty($optionen['einkauefe'])): ?><li><i class="bi bi-cart3 text-warning"></i> Einkäufe (nur Vorlagen, Status = Geplant)</li><?php endif; ?>
        </ul>

        <div class="alert alert-warning mb-0">
            <i class="bi bi-exclamation-triangle"></i>
            Diese Aktion kann nicht automatisch rückgängig gemacht werden. Bitte bestätigen.
        </div>
    </div>
    <div class="card-footer">
        <form method="POST">
            <input type="hidden" name="quell_id" value="<?php echo $quellFest['id']; ?>">
            <input type="hidden" name="ziel_id" value="<?php echo $ziel['id']; ?>">
            <input type="hidden" name="opt_stationen" value="<?php echo !empty($optionen['stationen']) ? '1' : ''; ?>">
            <input type="hidden" name="opt_mitarbeiter" value="<?php echo !empty($optionen['mitarbeiter']) ? '1' : ''; ?>">
            <input type="hidden" name="opt_einkauefe" value="<?php echo !empty($optionen['einkauefe']) ? '1' : ''; ?>">
            <input type="hidden" name="bestaetigt" value="1">
            <div class="d-flex justify-content-between">
                <a href="fest_kopieren.php<?php echo $zielId ? '?ziel_id='.$zielId : ''; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Zurück
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg"></i> Jetzt kopieren
                </button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($step === 3): ?>
<!-- SCHRITT 3: Ergebnis -->
<div class="card">
    <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="bi bi-check-circle"></i> Kopieren erfolgreich!</h5></div>
    <div class="card-body">
        <p>Folgende Datensätze wurden kopiert:</p>
        <ul>
            <?php if (isset($ergebnis['stationen'])): ?>
            <li><i class="bi bi-shop text-primary"></i> <strong><?php echo $ergebnis['stationen']; ?></strong> Stationen</li>
            <?php endif; ?>
            <?php if (isset($ergebnis['mitarbeiter'])): ?>
            <li><i class="bi bi-people text-success"></i> <strong><?php echo $ergebnis['mitarbeiter']; ?></strong> Mitarbeiter</li>
            <?php endif; ?>
            <?php if (isset($ergebnis['einkauefe'])): ?>
            <li><i class="bi bi-cart3 text-warning"></i> <strong><?php echo $ergebnis['einkauefe']; ?></strong> Einkaufs-Vorlagen</li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="card-footer">
        <?php if ($ziel): ?>
        <a href="fest_detail.php?id=<?php echo $ziel['id']; ?>" class="btn btn-primary">
            <i class="bi bi-stars"></i> Zum Fest-Dashboard
        </a>
        <?php endif; ?>
        <a href="feste.php" class="btn btn-outline-secondary ms-2">Alle Feste</a>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
