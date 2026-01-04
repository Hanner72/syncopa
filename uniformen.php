<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'lesen');

$db = Database::getInstance();

$filter = [];
if (!empty($_GET['kategorie'])) $filter['kategorie'] = $_GET['kategorie'];
if (!empty($_GET['mitglied_id'])) $filter['mitglied_id'] = $_GET['mitglied_id'];
if (!empty($_GET['groesse'])) $filter['groesse'] = $_GET['groesse'];

$where = ["u.aktiv = 1"];
$params = [];

if (!empty($filter['kategorie'])) {
    $where[] = "u.kategorie = ?";
    $params[] = $filter['kategorie'];
}
if (!empty($filter['mitglied_id'])) {
    $where[] = "u.mitglied_id = ?";
    $params[] = $filter['mitglied_id'];
}
if (!empty($filter['groesse'])) {
    $where[] = "(u.groesse = ? OR u.groesse_numerisch = ? OR u.groesse_text = ?)";
    $params[] = $filter['groesse'];
    $params[] = $filter['groesse'];
    $params[] = $filter['groesse'];
}

$uniformen = $db->fetchAll("
    SELECT u.*, m.vorname, m.nachname, m.mitgliedsnummer
    FROM uniformen u
    LEFT JOIN mitglieder m ON u.mitglied_id = m.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.kategorie, u.groesse
", $params);

$kategorien = $db->fetchAll("SELECT DISTINCT kategorie FROM uniformen WHERE aktiv = 1 ORDER BY kategorie");

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-suit-spade"></i> Uniformenverwaltung</h1>
    <?php if (Session::checkPermission('uniformen', 'schreiben')): ?>
    <div>
        <a href="uniform_bearbeiten.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Neue Uniform
        </a>
        <a href="uniform_inventur.php" class="btn btn-info">
            <i class="bi bi-clipboard-check"></i> Inventur
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <select class="form-select" name="kategorie">
                    <option value="">Alle Kategorien</option>
                    <?php foreach ($kategorien as $kat): ?>
                    <option value="<?php echo htmlspecialchars($kat['kategorie']); ?>" 
                            <?php echo ($_GET['kategorie'] ?? '') === $kat['kategorie'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($kat['kategorie']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="groesse" placeholder="Größe..." 
                       value="<?php echo htmlspecialchars($_GET['groesse'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filtern</button>
                <a href="uniformen.php" class="btn btn-secondary"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="uniformenTable">
            <thead>
                <tr>
                    <th>Kategorie</th>
                    <th>Größe</th>
                    <th>Anzahl</th>
                    <th>Zustand</th>
                    <th>Ausgegeben an</th>
                    <th>Standort</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($uniformen as $u): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($u['kategorie']); ?></strong></td>
                    <td><?php echo htmlspecialchars($u['groesse'] ?? $u['groesse_numerisch'] ?? $u['groesse_text'] ?? '-'); ?></td>
                    <td><?php echo $u['anzahl_vorhanden'] ?? 1; ?>x</td>
                    <td>
                        <?php
                        $zustandColors = ['sehr gut' => 'success', 'gut' => 'info', 'befriedigend' => 'warning', 'schlecht' => 'danger'];
                        $color = $zustandColors[$u['zustand']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($u['zustand'] ?? 'gut'); ?></span>
                    </td>
                    <td>
                        <?php if ($u['mitglied_id']): ?>
                        <span class="badge bg-warning text-dark">
                            <?php echo htmlspecialchars($u['nachname'] . ' ' . $u['vorname']); ?>
                        </span>
                        <?php else: ?>
                        <span class="badge bg-success">Verfügbar</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($u['standort'] ?? '-'); ?></td>
                    <td class="text-end">
                        <?php if (Session::checkPermission('uniformen', 'schreiben')): ?>
                        <a href="uniform_bearbeiten.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary">
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

<script>
$(document).ready(function() {
    $('#uniformenTable').DataTable({order: [[0, 'asc'], [1, 'asc']]});
});
</script>

<?php include 'includes/footer.php'; ?>
