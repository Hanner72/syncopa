<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'lesen');

$festObj = new Fest();
$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$fest    = $festObj->getById($id);

if (!$fest) {
    Session::setFlashMessage('danger', 'Fest nicht gefunden.');
    header('Location: feste.php'); exit;
}

$stats = $festObj->getDashboardStats($id);

$statusBadge = [
    'geplant'      => 'warning',
    'aktiv'        => 'success',
    'abgeschlossen'=> 'secondary',
    'abgesagt'     => 'danger',
][$fest['status']] ?? 'secondary';

$statusLabel = [
    'geplant'      => 'Geplant',
    'aktiv'        => 'Aktiv',
    'abgeschlossen'=> 'Abgeschlossen',
    'abgesagt'     => 'Abgesagt',
][$fest['status']] ?? $fest['status'];

include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="feste.php">Feste</a></li>
        <li class="breadcrumb-item active"><?php echo htmlspecialchars($fest['name']); ?></li>
    </ol>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="bi bi-stars"></i> <?php echo htmlspecialchars($fest['name']); ?>
            <span class="badge bg-<?php echo $statusBadge; ?> ms-2" style="font-size:13px"><?php echo $statusLabel; ?></span>
        </h1>
        <div class="text-muted small mt-1">
            <i class="bi bi-calendar3"></i> <?php echo date('d.m.Y', strtotime($fest['datum_von'])); ?>
            <?php if ($fest['datum_bis'] && $fest['datum_bis'] !== $fest['datum_von']): ?>
            – <?php echo date('d.m.Y', strtotime($fest['datum_bis'])); ?>
            <?php endif; ?>
            <?php if ($fest['ort']): ?>
            &nbsp;|&nbsp;<i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($fest['ort']); ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
        <a href="fest_bearbeiten.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil"></i> Bearbeiten
        </a>
        <?php endif; ?>
        <a href="feste.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Alle Feste</a>
    </div>
</div>

<!-- Stat-Karten -->
<div class="row mb-4">
    <div class="col-6 col-md-3 mb-2">
        <a href="fest_stationen.php?fest_id=<?php echo $id; ?>" class="text-decoration-none">
            <div class="card stat-card border-primary h-100">
                <div class="card-body">
                    <div><h6>Stationen</h6><h2><?php echo $stats['stationen']; ?></h2></div>
                    <i class="bi bi-shop stat-icon text-primary"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <a href="fest_mitarbeiter.php?fest_id=<?php echo $id; ?>" class="text-decoration-none">
            <div class="card stat-card border-success h-100">
                <div class="card-body">
                    <div><h6>Mitarbeiter</h6><h2><?php echo $stats['mitarbeiter']; ?></h2></div>
                    <i class="bi bi-people stat-icon text-success"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <a href="fest_todos.php?fest_id=<?php echo $id; ?>" class="text-decoration-none">
            <div class="card stat-card border-<?php echo $stats['todos_offen'] > 0 ? 'warning' : 'secondary'; ?> h-100">
                <div class="card-body">
                    <div>
                        <h6>Todos offen</h6>
                        <h2><?php echo $stats['todos_offen']; ?></h2>
                        <small>von <?php echo $stats['todos_gesamt']; ?> gesamt</small>
                    </div>
                    <i class="bi bi-check2-square stat-icon text-warning"></i>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <a href="fest_vertraege.php?fest_id=<?php echo $id; ?>" class="text-decoration-none">
            <div class="card stat-card border-info h-100">
                <div class="card-body">
                    <div>
                        <h6>Verträge</h6>
                        <h2><?php echo $stats['vertraege']; ?></h2>
                        <?php if ($stats['vertraege_offen'] > 0): ?>
                        <small class="text-danger"><?php echo $stats['vertraege_offen']; ?> offen</small>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-file-earmark-text stat-icon text-info"></i>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row">
    <!-- Linke Spalte: Submodul-Karten -->
    <div class="col-lg-8">
        <div class="row">
            <!-- Stationen -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-shop"></i> Stationen</h5>
                        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                        <a href="fest_station_bearbeiten.php?fest_id=<?php echo $id; ?>" class="btn btn-xs btn-outline-primary" style="font-size:11px;padding:2px 8px">
                            <i class="bi bi-plus"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Verkaufsstände, Bereiche und Einlassstellen verwalten.</p>
                        <a href="fest_stationen.php?fest_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary w-100">
                            <i class="bi bi-arrow-right-circle"></i> Stationen verwalten (<?php echo $stats['stationen']; ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mitarbeiter -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Mitarbeiter</h5>
                        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                        <a href="fest_mitarbeiter_bearbeiten.php?fest_id=<?php echo $id; ?>" class="btn btn-xs btn-outline-success" style="font-size:11px;padding:2px 8px">
                            <i class="bi bi-plus"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Helfer und Mitarbeiter (intern & extern) zuweisen.</p>
                        <a href="fest_mitarbeiter.php?fest_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-success w-100">
                            <i class="bi bi-arrow-right-circle"></i> Mitarbeiter verwalten (<?php echo $stats['mitarbeiter']; ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Dienstplan -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-table"></i> Dienstplan</h5>
                        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                        <a href="fest_dienstplan_bearbeiten.php?fest_id=<?php echo $id; ?>" class="btn btn-xs btn-outline-secondary" style="font-size:11px;padding:2px 8px">
                            <i class="bi bi-plus"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Schichtplan erstellen – wer wann wo arbeitet.</p>
                        <a href="fest_dienstplan.php?fest_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary w-100">
                            <i class="bi bi-arrow-right-circle"></i> Dienstplan öffnen (<?php echo $stats['dienstplaene']; ?> Schichten)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Einkäufe -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-cart3"></i> Einkäufe</h5>
                        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                        <a href="fest_einkauf_bearbeiten.php?fest_id=<?php echo $id; ?>" class="btn btn-xs btn-outline-warning" style="font-size:11px;padding:2px 8px">
                            <i class="bi bi-plus"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">Einkaufsliste verwalten, Lieferanten, Kosten erfassen.</p>
                        <p class="mb-3">
                            <strong><?php echo number_format($stats['einkauefe_summe'], 2, ',', '.'); ?> €</strong>
                            <span class="text-muted small">geplant</span>
                        </p>
                        <a href="fest_einkauefe.php?fest_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-warning w-100">
                            <i class="bi bi-arrow-right-circle"></i> Einkäufe verwalten (<?php echo $stats['einkauefe_gesamt']; ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Verträge -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Verträge</h5>
                        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                        <a href="fest_vertrag_bearbeiten.php?fest_id=<?php echo $id; ?>" class="btn btn-xs btn-outline-info" style="font-size:11px;padding:2px 8px">
                            <i class="bi bi-plus"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Verträge mit Musikgruppen und Bands, inkl. Dokument-Upload.</p>
                        <a href="fest_vertraege.php?fest_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-info w-100">
                            <i class="bi bi-arrow-right-circle"></i> Verträge verwalten (<?php echo $stats['vertraege']; ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Todos -->
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-check2-square"></i> Todos</h5>
                        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                        <a href="fest_todo_bearbeiten.php?fest_id=<?php echo $id; ?>" class="btn btn-xs btn-outline-danger" style="font-size:11px;padding:2px 8px">
                            <i class="bi bi-plus"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Aufgaben verwalten, Benutzer zuweisen, Fortschritt verfolgen.</p>
                        <a href="fest_todos.php?fest_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-arrow-right-circle"></i>
                            Todos verwalten
                            <?php if ($stats['todos_offen'] > 0): ?>
                            <span class="badge bg-danger ms-1"><?php echo $stats['todos_offen']; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rechte Spalte: Info + Aktionen -->
    <div class="col-lg-4">
        <?php if ($fest['beschreibung']): ?>
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-info-circle"></i> Beschreibung</h5></div>
            <div class="card-body">
                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($fest['beschreibung'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($fest['adresse']): ?>
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-geo-alt"></i> Adresse</h5></div>
            <div class="card-body">
                <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($fest['adresse'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-lightning"></i> Schnellaktionen</h5></div>
            <div class="card-body d-grid gap-2">
                <a href="fest_kopieren.php?ziel_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-copy"></i> Daten aus anderem Fest kopieren
                </a>
                <?php if (Session::checkPermission('fest', 'loeschen')): ?>
                <form method="POST" action="fest_loeschen.php"
                      onsubmit="return confirm('Fest «<?php echo htmlspecialchars(addslashes($fest['name'])); ?>» wirklich löschen?')">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-trash"></i> Fest löschen
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock-history"></i> Info</h5></div>
            <div class="card-body small text-muted">
                <div>Erstellt: <?php echo date('d.m.Y H:i', strtotime($fest['erstellt_am'])); ?></div>
                <?php if ($fest['erstellt_von_name']): ?>
                <div>Von: <?php echo htmlspecialchars($fest['erstellt_von_name']); ?></div>
                <?php endif; ?>
                <div class="mt-1">Zuletzt geändert: <?php echo date('d.m.Y H:i', strtotime($fest['aktualisiert_am'])); ?></div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
