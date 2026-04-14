<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('fest', 'lesen');

$todoObj    = new FestTodo();
$isAdmin    = Session::getRole() === 'admin';
$benutzerId = $isAdmin ? null : Session::getUserId();
$nurOffene  = !isset($_GET['alle']) || $_GET['alle'] !== '1';
$todos      = $todoObj->getAllOffene($benutzerId, $nurOffene);

$prioritaetLabels = [
    'kritisch' => ['label' => 'Kritisch', 'badge' => 'danger'],
    'hoch'     => ['label' => 'Hoch',     'badge' => 'warning'],
    'normal'   => ['label' => 'Normal',   'badge' => 'primary'],
    'niedrig'  => ['label' => 'Niedrig',  'badge' => 'secondary'],
];
$statusLabels = [
    'offen'       => ['label' => 'Offen',      'badge' => 'warning'],
    'in_arbeit'   => ['label' => 'In Arbeit',  'badge' => 'info'],
    'erledigt'    => ['label' => 'Erledigt',   'badge' => 'success'],
    'abgebrochen' => ['label' => 'Abgebrochen','badge' => 'secondary'],
];
$toggleIcon  = ['offen' => 'bi-circle', 'in_arbeit' => 'bi-circle-half', 'erledigt' => 'bi-check-circle-fill', 'abgebrochen' => 'bi-x-circle'];
$toggleColor = ['offen' => '#adb5bd',   'in_arbeit' => '#0dcaf0',        'erledigt' => '#198754',              'abgebrochen' => '#adb5bd'];

$heute = date('Y-m-d');
include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="bi bi-check2-square"></i>
        <?php echo $isAdmin ? 'Alle Todos' : 'Meine Todos'; ?>
    </h1>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($nurOffene): ?>
        <a href="fest_todos_alle.php?alle=1" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-eye"></i> Erledigte anzeigen
        </a>
        <?php else: ?>
        <a href="fest_todos_alle.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-eye-slash"></i> Erledigte ausblenden
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($todos)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-check2-all fs-1 d-block mb-2 opacity-25"></i>
    Keine Todos vorhanden.
</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0" id="todosTable">
            <thead>
                <tr>
                    <th style="width:36px"></th>
                    <th>Titel</th>
                    <th>Fest</th>
                    <th>Priorität</th>
                    <th>Fällig</th>
                    <th>Zuständig</th>
                    <th>Status</th>
                    <th class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todos as $t):
                    $pl  = $prioritaetLabels[$t['prioritaet']] ?? ['label' => $t['prioritaet'], 'badge' => 'secondary'];
                    $sl  = $statusLabels[$t['status']]         ?? ['label' => $t['status'],      'badge' => 'secondary'];
                    $faellig = $t['faellig_am'] ?? null;
                    $ueberfaellig = $faellig && $faellig < $heute && !in_array($t['status'], ['erledigt','abgebrochen']);
                    $erledigt = in_array($t['status'], ['erledigt','abgebrochen']);
                    $s = isset($toggleIcon[$t['status']]) ? $t['status'] : 'offen';
                ?>
                <tr class="<?php echo $ueberfaellig ? 'table-danger' : ($erledigt ? 'opacity-50' : ''); ?>">
                    <td>
                        <button class="btn-toggle-status"
                                data-id="<?php echo $t['id']; ?>"
                                data-status="<?php echo $s; ?>"
                                style="font-size:22px;padding:0;border:none;background:none;cursor:pointer;line-height:1;color:<?php echo $toggleColor[$s]; ?>"
                                title="Klick: Status wechseln">
                            <i class="bi <?php echo $toggleIcon[$s]; ?>"></i>
                        </button>
                    </td>
                    <td>
                        <span class="<?php echo $erledigt ? 'text-decoration-line-through text-muted' : ''; ?>">
                            <?php echo htmlspecialchars($t['titel']); ?>
                        </span>
                        <?php if ($t['beschreibung']): ?>
                        <div class="small text-muted"><?php echo htmlspecialchars(mb_substr($t['beschreibung'], 0, 80)); ?><?php echo mb_strlen($t['beschreibung']) > 80 ? '…' : ''; ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <a href="fest_todos.php?fest_id=<?php echo $t['fest_id']; ?>"><?php echo htmlspecialchars($t['fest_name']); ?></a>
                    </td>
                    <td><span class="badge bg-<?php echo $pl['badge']; ?>"><?php echo $pl['label']; ?></span></td>
                    <td class="small <?php echo $ueberfaellig ? 'text-danger fw-bold' : ''; ?>">
                        <?php echo $faellig ? date('d.m.Y', strtotime($faellig)) : '–'; ?>
                        <?php if ($ueberfaellig): ?><i class="bi bi-exclamation-triangle-fill"></i><?php endif; ?>
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
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
<script>
var nurOffene = <?php echo $nurOffene ? 'true' : 'false'; ?>;

if (typeof $ !== 'undefined' && $.fn.DataTable) {
    $('#todosTable').DataTable({ order: [[4, 'asc']] });
}

var toggleNext  = { 'offen': 'in_arbeit', 'in_arbeit': 'erledigt', 'erledigt': 'offen', 'abgebrochen': 'offen' };
var toggleIcon  = { 'offen': 'bi-circle', 'in_arbeit': 'bi-circle-half', 'erledigt': 'bi-check-circle-fill', 'abgebrochen': 'bi-x-circle' };
var toggleColor = { 'offen': '#adb5bd',   'in_arbeit': '#0dcaf0',        'erledigt': '#198754',              'abgebrochen': '#adb5bd' };
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
            if (!data.success) return;
            var row = btnEl.closest('tr');
            var erledigt = newStatus === 'erledigt' || newStatus === 'abgebrochen';

            if (nurOffene && erledigt) {
                // Im Nur-Offene-Modus: erledigte Zeile ausblenden
                row.style.transition = 'opacity 0.4s';
                row.style.opacity = '0';
                setTimeout(function() { row.remove(); }, 400);
                return;
            }

            btnEl.dataset.status = newStatus;
            btnEl.style.color = toggleColor[newStatus] || '#adb5bd';
            btnEl.querySelector('i').className = 'bi ' + (toggleIcon[newStatus] || 'bi-circle');

            var badge = row.querySelector('.badge-status');
            if (badge && statusLabels[newStatus]) {
                badge.className   = 'badge bg-' + statusLabels[newStatus][1] + ' badge-status';
                badge.textContent = statusLabels[newStatus][0];
            }
            var titleSpan = row.querySelector('td:nth-child(2) span');
            if (titleSpan) {
                if (erledigt) {
                    titleSpan.classList.add('text-decoration-line-through', 'text-muted');
                    row.classList.add('opacity-50');
                    row.classList.remove('table-danger');
                } else {
                    titleSpan.classList.remove('text-decoration-line-through', 'text-muted');
                    row.classList.remove('opacity-50');
                }
            }
        });
    });
});
</script>
