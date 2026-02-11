<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('instrumente', 'lesen');

$instrumentObj = new Instrument();

$filter = [];
if (!empty($_GET['typ'])) $filter['typ'] = $_GET['typ'];
if (!empty($_GET['zustand'])) $filter['zustand'] = $_GET['zustand'];
if (!empty($_GET['ausgeliehen'])) $filter['ausgeliehen'] = $_GET['ausgeliehen'];
if (!empty($_GET['search'])) $filter['search'] = $_GET['search'];

$instrumente = $instrumentObj->getAll($filter);
$typen = $instrumentObj->getTypen();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-diagram-3"></i> Instrumente</h1>
    <?php if (Session::checkPermission('instrumente', 'schreiben')): ?>
    <a href="instrument_bearbeiten.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Neues Instrument
    </a>
    <?php endif; ?>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Inventarnummer, Hersteller..." 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="typ">
                    <option value="">Alle Typen</option>
                    <?php foreach ($typen as $typ): ?>
                    <option value="<?php echo $typ['id']; ?>" <?php echo ($_GET['typ'] ?? '') == $typ['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($typ['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="ausgeliehen">
                    <option value="">Alle</option>
                    <option value="ja" <?php echo ($_GET['ausgeliehen'] ?? '') === 'ja' ? 'selected' : ''; ?>>Ausgeliehen</option>
                    <option value="nein" <?php echo ($_GET['ausgeliehen'] ?? '') === 'nein' ? 'selected' : ''; ?>>Verfügbar</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i></button>
                <a href="instrumente.php" class="btn btn-secondary"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="instrumenteTable">
            <thead>
                <tr>
                    <th>Inventar-Nr.</th>
                    <th>Instrument</th>
                    <th>Hersteller/Modell</th>
                    <th>Zustand</th>
                    <th>Status</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($instrumente as $instr): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($instr['inventar_nummer']); ?></strong></td>
                    <td>
                        <?php echo htmlspecialchars($instr['instrument_name']); ?>
                        <?php if ($instr['register_name']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($instr['register_name']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($instr['hersteller'] ?? '-'); ?>
                        <?php if ($instr['modell']): ?>
                        <br><small><?php echo htmlspecialchars($instr['modell']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $zustandColors = ['sehr gut' => 'success', 'gut' => 'info', 'befriedigend' => 'warning', 'schlecht' => 'danger', 'defekt' => 'dark'];
                        $color = $zustandColors[$instr['zustand']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($instr['zustand']); ?></span>
                    </td>
                    <td>
                        <?php if ($instr['mitglied_id']): ?>
                        <span class="badge bg-warning">Ausgeliehen</span>
                        <br><small><?php echo htmlspecialchars($instr['vorname'] . ' ' . $instr['nachname']); ?></small>
                        <?php else: ?>
                        <span class="badge bg-success">Verfügbar</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (Session::checkPermission('instrumente', 'schreiben')): ?>
                        <a href="instrument_bearbeiten.php?id=<?php echo $instr['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#instrumenteTable').DataTable({order: [[0, 'asc']]});
});
</script>
