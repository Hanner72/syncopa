<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('noten', 'lesen');

$notenObj = new Noten();

$filter = [];
if (!empty($_GET['search'])) $filter['search'] = $_GET['search'];
if (!empty($_GET['genre'])) $filter['genre'] = $_GET['genre'];
if (!empty($_GET['schwierigkeitsgrad'])) $filter['schwierigkeitsgrad'] = $_GET['schwierigkeitsgrad'];

$noten = $notenObj->getAll($filter);
$genres = $notenObj->getGenres();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-music-note-list"></i> Noten</h1>
    <?php if (Session::checkPermission('noten', 'schreiben')): ?>
    <a href="noten_bearbeiten.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Neue Noten
    </a>
    <?php endif; ?>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Titel, Komponist, Arrangeur..." 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="genre">
                    <option value="">Alle Genres</option>
                    <?php foreach ($genres as $genre): ?>
                    <option value="<?php echo htmlspecialchars($genre); ?>" <?php echo ($_GET['genre'] ?? '') === $genre ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($genre); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="schwierigkeitsgrad">
                    <option value="">Alle Schwierigkeitsgrade</option>
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($_GET['schwierigkeitsgrad'] ?? '') == $i ? 'selected' : ''; ?>>
                        Grad <?php echo $i; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Filtern</button>
                <a href="noten.php" class="btn btn-secondary"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover" id="notenTable">
            <thead>
                <tr>
                    <th>Archiv-Nr.</th>
                    <th>Titel</th>
                    <th>Komponist</th>
                    <th>Genre</th>
                    <th>Schwierigkeit</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($noten as $note): ?>
                <tr>
                    <td><small><?php echo htmlspecialchars($note['archiv_nummer']); ?></small></td>
                    <td>
                        <strong><?php echo htmlspecialchars($note['titel']); ?></strong>
                        <?php if ($note['untertitel']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($note['untertitel']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($note['komponist'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($note['genre'] ?? '-'); ?></td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo ($note['schwierigkeitsgrad'] / 6) * 100; ?>%">
                                <?php echo $note['schwierigkeitsgrad']; ?>/6
                            </div>
                        </div>
                    </td>
                    <td class="text-end">
                        <?php if (Session::checkPermission('noten', 'schreiben')): ?>
                        <a href="noten_bearbeiten.php?id=<?php echo $note['id']; ?>" class="btn btn-sm btn-primary">
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
    $('#notenTable').DataTable({order: [[1, 'asc']]});
});
</script>

<?php include 'includes/footer.php'; ?>
