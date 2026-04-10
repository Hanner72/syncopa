<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'lesen');

$todoObj = new FestTodo();
$festObj = new Fest();

// Modus: Fest-spezifisch oder globale Todos-Übersicht
$festId      = isset($_GET['fest_id']) ? (int)$_GET['fest_id'] : null;
$nurMeineTodos = isset($_GET['meine']) && $_GET['meine'] === '1';
$fest        = null;

if ($festId) {
    $fest = $festObj->getById($festId);
    if (!$fest) {
        Session::setFlashMessage('danger', 'Fest nicht gefunden.');
        header('Location: feste.php'); exit;
    }
}

$filter = [];
if (!empty($_GET['status']))      $filter['status']      = $_GET['status'];
if (!empty($_GET['prioritaet']))  $filter['prioritaet']  = $_GET['prioritaet'];
if ($nurMeineTodos)               $filter['zustaendig_id'] = Session::getUserId();

if ($festId) {
    $todos = $todoObj->getByFest($festId, $filter);
} elseif ($nurMeineTodos) {
    $todos = $todoObj->getMeineTodos(Session::getUserId());
} else {
    // Alle Feste: zusammengefasste Ansicht
    $todos = $todoObj->getMeineTodos(Session::getUserId());
}

$prioritaetLabels = [
    'kritisch' => ['label' => 'Kritisch', 'badge' => 'danger'],
    'hoch'     => ['label' => 'Hoch',     'badge' => 'warning'],
    'normal'   => ['label' => 'Normal',   'badge' => 'primary'],
    'niedrig'  => ['label' => 'Niedrig',  'badge' => 'secondary'],
];
$statusLabels = [
    'offen'      => ['label' => 'Offen',      'badge' => 'warning'],
    'in_arbeit'  => ['label' => 'In Arbeit',  'badge' => 'info'],
    'erledigt'   => ['label' => 'Erledigt',   'badge' => 'success'],
    'abgebrochen'=> ['label' => 'Abgebrochen','badge' => 'secondary'],
];

$heute = date('Y-m-d');
include 'includes/header.php';
?>

<?php if ($fest): ?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="feste.php">Feste</a></li>
        <li class="breadcrumb-item"><a href="fest_detail.php?id=<?php echo $festId; ?>"><?php echo htmlspecialchars($fest['name']); ?></a></li>
        <li class="breadcrumb-item active">Todos</li>
    </ol>
</nav>
<?php endif; ?>

<div class="page-header">
    <h1 class="page-title">
        <i class="bi bi-check2-square"></i>
        <?php echo $fest ? 'Todos – ' . htmlspecialchars($fest['name']) : 'Meine Todos'; ?>
    </h1>
    <div class="d-flex gap-2">
        <?php if ($fest): ?>
        <a href="fest_todos.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-person-check"></i> Meine Todos
        </a>
        <?php endif; ?>
        <?php if (Session::checkPermission('fest', 'schreiben')): ?>
        <a href="fest_todo_bearbeiten.php<?php echo $festId ? '?fest_id='.$festId : ''; ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Todo hinzufügen
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter -->
<?php if ($festId): ?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="fest_id" value="<?php echo $festId; ?>">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Alle Status</option>
                    <?php foreach ($statusLabels as $sv => $sl): ?>
                    <option value="<?php echo $sv; ?>" <?php echo ($_GET['status'] ?? '') === $sv ? 'selected' : ''; ?>><?php echo $sl['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Priorität</label>
                <select name="prioritaet" class="form-select form-select-sm">
                    <option value="">Alle Prioritäten</option>
                    <?php foreach ($prioritaetLabels as $pv => $pl): ?>
                    <option value="<?php echo $pv; ?>" <?php echo ($_GET['prioritaet'] ?? '') === $pv ? 'selected' : ''; ?>><?php echo $pl['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filtern</button>
                <a href="fest_todos.php?fest_id=<?php echo $festId; ?>" class="btn btn-sm btn-outline-secondary">Zurücksetzen</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Todo-Liste -->
<?php if (empty($todos)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-check2-all fs-1 d-block mb-2 opacity-25"></i>
    <?php echo $nurMeineTodos || !$festId ? 'Keine offenen Todos zugewiesen.' : 'Keine Todos gefunden.'; ?>
    <?php if ($festId && Session::checkPermission('fest', 'schreiben')): ?>
    <div class="mt-2"><a href="fest_todo_bearbeiten.php?fest_id=<?php echo $festId; ?>" class="btn btn-sm btn-primary">Erstes Todo anlegen</a></div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0" id="todosTable">
            <thead>
                <tr>
                    <th style="width:36px"></th>
                    <th>Titel</th>
                    <?php if (!$festId): ?><th>Fest</th><?php endif; ?>
                    <th>Priorität</th>
                    <th>Fällig</th>
                    <th>Zuständig</th>
                    <th>Status</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todos as $t): ?>
                <?php
                    $pl  = $prioritaetLabels[$t['prioritaet']] ?? ['label' => $t['prioritaet'], 'badge' => 'secondary'];
                    $sl  = $statusLabels[$t['status']] ?? ['label' => $t['status'], 'badge' => 'secondary'];
                    $faellig = $t['faellig_am'] ?? null;
                    $istUeberfaellig = $faellig && $faellig < $heute && !in_array($t['status'], ['erledigt','abgebrochen']);
                ?>
                <tr class="<?php echo $istUeberfaellig ? 'table-danger' : ''; ?>">
                    <td>
                        <!-- AJAX Status-Toggle -->
                        <?php
                            $toggleIcon  = ['offen' => 'bi-circle',            'in_arbeit' => 'bi-circle-half',       'erledigt' => 'bi-check-circle-fill'];
                            $toggleColor = ['offen' => '#adb5bd',              'in_arbeit' => '#0dcaf0',              'erledigt' => '#198754'];
                            $toggleTitle = ['offen' => 'Klick: In Arbeit',     'in_arbeit' => 'Klick: Erledigt',      'erledigt' => 'Klick: Zurücksetzen'];
                            $s = $t['status']; if (!isset($toggleIcon[$s])) $s = 'offen';
                        ?>
                        <button class="btn-toggle-status"
                                data-id="<?php echo $t['id']; ?>"
                                data-status="<?php echo $s; ?>"
                                style="font-size:22px;padding:0;border:none;background:none;cursor:pointer;line-height:1;color:<?php echo $toggleColor[$s]; ?>"
                                title="<?php echo $toggleTitle[$s]; ?>">
                            <i class="bi <?php echo $toggleIcon[$s]; ?>"></i>
                        </button>
                    </td>
                    <td <?php if ($t['beschreibung']): ?>data-bs-toggle="tooltip" data-bs-placement="right" title="<?php echo htmlspecialchars($t['beschreibung']); ?>"<?php endif; ?>>
                        <span class="<?php echo $t['status'] === 'erledigt' ? 'text-decoration-line-through text-muted' : ''; ?>">
                            <?php echo htmlspecialchars($t['titel']); ?>
                        </span>
                        <?php if ($t['beschreibung']): ?>
                        <div class="small text-muted"><?php echo htmlspecialchars(mb_substr($t['beschreibung'], 0, 80)); ?><?php echo mb_strlen($t['beschreibung']) > 80 ? '…' : ''; ?></div>
                        <?php endif; ?>
                    </td>
                    <?php if (!$festId): ?>
                    <td class="small"><a href="fest_detail.php?id=<?php echo $t['fest_id']; ?>"><?php echo htmlspecialchars($t['fest_name']); ?></a></td>
                    <?php endif; ?>
                    <td><span class="badge bg-<?php echo $pl['badge']; ?>"><?php echo $pl['label']; ?></span></td>
                    <td class="small <?php echo $istUeberfaellig ? 'text-danger fw-bold' : ''; ?>">
                        <?php echo $faellig ? date('d.m.Y', strtotime($faellig)) : '–'; ?>
                        <?php if ($istUeberfaellig): ?><i class="bi bi-exclamation-triangle-fill"></i><?php endif; ?>
                    </td>
                    <td class="small"><?php echo htmlspecialchars($t['zustaendig_name'] ?? '–'); ?></td>
                    <td><span class="badge bg-<?php echo $sl['badge']; ?> badge-status"><?php echo $sl['label']; ?></span></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <?php if (Session::checkPermission('fest', 'schreiben')): ?>
                            <a href="fest_todo_bearbeiten.php?id=<?php echo $t['id']; ?>&fest_id=<?php echo $t['fest_id']; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (Session::checkPermission('fest', 'loeschen')): ?>
                            <form method="POST" action="fest_todo_loeschen.php" class="d-inline"
                                  onsubmit="return confirm('Todo löschen?')">
                                <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                <input type="hidden" name="fest_id" value="<?php echo $t['fest_id']; ?>">
                                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
.tooltip-wide .tooltip-inner { max-width: 400px; text-align: left; white-space: pre-wrap; }
</style>
<?php include 'includes/footer.php'; ?>
<script>
if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $('#todosTable').DataTable({ order: [[3, 'asc'], [4, 'asc']] });
}

// Bootstrap Tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
    new bootstrap.Tooltip(el, { trigger: 'hover', customClass: 'tooltip-wide' });
});

// AJAX Status-Toggle (3-Stufen: offen → in_arbeit → erledigt → offen)
var toggleNext  = { 'offen': 'in_arbeit', 'in_arbeit': 'erledigt', 'erledigt': 'offen' };
var toggleIcon  = { 'offen': 'bi-circle', 'in_arbeit': 'bi-circle-half', 'erledigt': 'bi-check-circle-fill' };
var toggleColor = { 'offen': '#adb5bd',   'in_arbeit': '#0dcaf0',        'erledigt': '#198754' };
var toggleTitle = { 'offen': 'Klick: In Arbeit', 'in_arbeit': 'Klick: Erledigt', 'erledigt': 'Klick: Zuruecksetzen' };
var statusLabels = {
    'offen':       ['Offen',      'warning'],
    'in_arbeit':   ['In Arbeit',  'info'],
    'erledigt':    ['Erledigt',   'success'],
    'abgebrochen': ['Abgebrochen','secondary']
};

document.querySelectorAll('.btn-toggle-status').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var btnEl     = this;
        var status    = btnEl.dataset.status;
        var newStatus = toggleNext[status] || 'offen';

        fetch('api/fest_todo_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id=' + btnEl.dataset.id + '&status=' + newStatus
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                btnEl.dataset.status  = newStatus;
                btnEl.title           = toggleTitle[newStatus] || '';
                btnEl.style.color     = toggleColor[newStatus];
                btnEl.querySelector('i').className = 'bi ' + toggleIcon[newStatus];

                var row        = btnEl.closest('tr');
                var titleCell  = row.querySelector('td:nth-child(2) span');
                var statusBadge = row.querySelector('.badge-status');

                if (titleCell) {
                    if (newStatus === 'erledigt') {
                        titleCell.classList.add('text-decoration-line-through', 'text-muted');
                    } else {
                        titleCell.classList.remove('text-decoration-line-through', 'text-muted');
                    }
                }
                if (statusBadge && statusLabels[newStatus]) {
                    var sl = statusLabels[newStatus];
                    statusBadge.className   = 'badge bg-' + sl[1] + ' badge-status';
                    statusBadge.textContent = sl[0];
                }
            }
        })
        .catch(function(e) { console.error(e); });
    });
});
</script>
