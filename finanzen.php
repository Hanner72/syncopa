<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'lesen');

$db = Database::getInstance();

// Filter
$jahr = $_GET['jahr'] ?? date('Y');
$typ = $_GET['typ'] ?? '';

// Statistiken
$einnahmen = $db->fetchOne("SELECT SUM(betrag) as total FROM finanzen WHERE typ = 'einnahme' AND YEAR(datum) = ?", [$jahr]);
$ausgaben = $db->fetchOne("SELECT SUM(betrag) as total FROM finanzen WHERE typ = 'ausgabe' AND YEAR(datum) = ?", [$jahr]);
$saldo = ($einnahmen['total'] ?? 0) - ($ausgaben['total'] ?? 0);

// Mitgliedsbeiträge
$beitraege = $db->fetchAll("
    SELECT b.*, m.vorname, m.nachname, m.mitgliedsnummer 
    FROM beitraege b
    JOIN mitglieder m ON b.mitglied_id = m.id
    WHERE b.jahr = ?
    ORDER BY b.bezahlt, m.nachname
", [$jahr]);

$beitraege_offen = count(array_filter($beitraege, fn($b) => !$b['bezahlt']));
$beitraege_summe = array_sum(array_column($beitraege, 'betrag'));
$beitraege_bezahlt_summe = array_sum(array_map(fn($b) => $b['bezahlt'] ? $b['betrag'] : 0, $beitraege));

// Transaktionen
$where = ["YEAR(datum) = ?"];
$params = [$jahr];

if ($typ) {
    $where[] = "typ = ?";
    $params[] = $typ;
}

$transaktionen = $db->fetchAll("
    SELECT f.*, b.benutzername as erstellt_von_name
    FROM finanzen f
    LEFT JOIN benutzer b ON f.erstellt_von = b.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY f.datum DESC
", $params);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-cash-coin"></i> Finanzen</h1>
    <?php if (Session::checkPermission('finanzen', 'schreiben')): ?>
    <div>
        <a href="transaktion_bearbeiten.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Neue Transaktion
        </a>
        <a href="beitraege_verwalten.php" class="btn btn-primary">
            <i class="bi bi-currency-euro"></i> Beiträge verwalten
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Statistik-Karten -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <h6 class="text-muted">Einnahmen <?php echo $jahr; ?></h6>
                <h2 class="text-success">€ <?php echo number_format($einnahmen['total'] ?? 0, 2, ',', '.'); ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-danger border-4">
            <div class="card-body">
                <h6 class="text-muted">Ausgaben <?php echo $jahr; ?></h6>
                <h2 class="text-danger">€ <?php echo number_format($ausgaben['total'] ?? 0, 2, ',', '.'); ?></h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <h6 class="text-muted">Saldo <?php echo $jahr; ?></h6>
                <h2 class="<?php echo $saldo >= 0 ? 'text-success' : 'text-danger'; ?>">
                    € <?php echo number_format($saldo, 2, ',', '.'); ?>
                </h2>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <h6 class="text-muted">Beiträge offen</h6>
                <h2 class="text-warning"><?php echo $beitraege_offen; ?></h2>
                <small>von <?php echo count($beitraege); ?> Mitgliedern</small>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="jahr" class="form-label">Jahr</label>
                <select class="form-select" id="jahr" name="jahr">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $jahr == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="typ" class="form-label">Typ</label>
                <select class="form-select" id="typ" name="typ">
                    <option value="">Alle</option>
                    <option value="einnahme" <?php echo $typ === 'einnahme' ? 'selected' : ''; ?>>Einnahmen</option>
                    <option value="ausgabe" <?php echo $typ === 'ausgabe' ? 'selected' : ''; ?>>Ausgaben</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Filtern</button>
                <a href="finanzen.php" class="btn btn-secondary"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#transaktionen">Transaktionen</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#beitraege">Mitgliedsbeiträge</a>
    </li>
</ul>

<div class="tab-content">
    <!-- Transaktionen Tab -->
    <div class="tab-pane fade show active" id="transaktionen">
        <div class="card">
            <div class="card-body">
                <table class="table table-hover" id="transaktionenTable">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Typ</th>
                            <th>Beschreibung</th>
                            <th>Kategorie</th>
                            <th>Betrag</th>
                            <th class="text-end">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transaktionen as $t): ?>
                        <tr>
                            <td><?php echo date('d.m.Y', strtotime($t['datum'])); ?></td>
                            <td>
                                <?php if ($t['typ'] === 'einnahme'): ?>
                                <span class="badge bg-success">Einnahme</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Ausgabe</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($t['beschreibung'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($t['kategorie'] ?? '-'); ?></td>
                            <td class="<?php echo $t['typ'] === 'einnahme' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $t['typ'] === 'einnahme' ? '+' : '-'; ?>
                                € <?php echo number_format($t['betrag'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-end">
                                <?php if (Session::checkPermission('finanzen', 'schreiben')): ?>
                                <a href="transaktion_bearbeiten.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-primary" title="Bearbeiten">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (Session::checkPermission('finanzen', 'loeschen')): ?>
                                <a href="transaktion_loeschen.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-danger" title="Löschen"
                                   onclick="return confirm('Transaktion wirklich löschen?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Beiträge Tab -->
    <div class="tab-pane fade" id="beitraege">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="mb-0">Mitgliedsbeiträge <?php echo $jahr; ?></h5>
                    </div>
                    <div>
                        <strong>Bezahlt:</strong> € <?php echo number_format($beitraege_bezahlt_summe, 2, ',', '.'); ?> / 
                        € <?php echo number_format($beitraege_summe, 2, ',', '.'); ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-hover" id="beitraegeTable">
                    <thead>
                        <tr>
                            <th>Mitglied</th>
                            <th>Mitgliedsnr.</th>
                            <th>Betrag</th>
                            <th>Status</th>
                            <th>Bezahlt am</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($beitraege as $b): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($b['nachname'] . ' ' . $b['vorname']); ?></td>
                            <td><?php echo htmlspecialchars($b['mitgliedsnummer']); ?></td>
                            <td>€ <?php echo number_format($b['betrag'], 2, ',', '.'); ?></td>
                            <td>
                                <?php if ($b['bezahlt']): ?>
                                <span class="badge bg-success">Bezahlt</span>
                                <?php else: ?>
                                <span class="badge bg-warning">Offen</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($b['bezahlt_am']): ?>
                                <?php echo date('d.m.Y', strtotime($b['bezahlt_am'])); ?>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#transaktionenTable').DataTable({order: [[0, 'desc']]});
    $('#beitraegeTable').DataTable({order: [[3, 'asc'], [0, 'asc']]});
});
</script>
