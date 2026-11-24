<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin()) {
    Session::setFlashMessage('danger', 'Nur Administratoren haben Zugriff');
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id      = (int)($_POST['id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $pattern = trim($_POST['pattern'] ?? '');
        $sort    = max(1, (int)($_POST['sortierung'] ?? 100));
        $aktiv   = isset($_POST['aktiv']) ? 1 : 0;
        $beschr  = trim($_POST['beschreibung'] ?? '') ?: null;

        if (!$name || !$pattern) {
            Session::setFlashMessage('danger', 'Name und Pattern sind Pflichtfelder.');
        } elseif (@preg_match('/' . $pattern . '/iu', '') === false) {
            Session::setFlashMessage('danger', 'Ungültige Regex-Syntax im Pattern.');
        } else {
            if ($id > 0) {
                $db->execute(
                    "UPDATE noten_instrumente_pattern SET name=?, pattern=?, sortierung=?, aktiv=?, beschreibung=? WHERE id=?",
                    [$name, $pattern, $sort, $aktiv, $beschr, $id]
                );
                Session::setFlashMessage('success', 'Instrument aktualisiert.');
            } else {
                $db->execute(
                    "INSERT INTO noten_instrumente_pattern (name, pattern, sortierung, aktiv, beschreibung) VALUES (?,?,?,?,?)",
                    [$name, $pattern, $sort, $aktiv, $beschr]
                );
                Session::setFlashMessage('success', 'Instrument hinzugefügt.');
            }
        }
        header('Location: noten_instrumente.php');
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $db->execute("DELETE FROM noten_instrumente_pattern WHERE id=?", [$id]);
        Session::setFlashMessage('success', 'Eintrag gelöscht.');
        header('Location: noten_instrumente.php');
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $db->execute("UPDATE noten_instrumente_pattern SET aktiv = 1 - aktiv WHERE id=?", [$id]);
        header('Location: noten_instrumente.php');
        exit;
    }

    if ($action === 'move') {
        $id  = (int)($_POST['id'] ?? 0);
        $dir = ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down';
        if ($id > 0) {
            $cur = $db->fetchOne("SELECT sortierung FROM noten_instrumente_pattern WHERE id=?", [$id]);
            if ($cur) {
                $s = (int)$cur['sortierung'];
                if ($dir === 'up') {
                    $other = $db->fetchOne("SELECT id, sortierung FROM noten_instrumente_pattern WHERE sortierung < ? ORDER BY sortierung DESC LIMIT 1", [$s]);
                } else {
                    $other = $db->fetchOne("SELECT id, sortierung FROM noten_instrumente_pattern WHERE sortierung > ? ORDER BY sortierung ASC LIMIT 1", [$s]);
                }
                if ($other) {
                    $db->execute("UPDATE noten_instrumente_pattern SET sortierung=? WHERE id=?", [$other['sortierung'], $id]);
                    $db->execute("UPDATE noten_instrumente_pattern SET sortierung=? WHERE id=?", [$s, $other['id']]);
                }
            }
        }
        header('Location: noten_instrumente.php');
        exit;
    }
}

$patterns = $db->fetchAll("SELECT * FROM noten_instrumente_pattern ORDER BY sortierung ASC, id ASC");
$maxSort  = empty($patterns) ? 10 : (max(array_column($patterns, 'sortierung')) + 10);

include 'includes/header.php';

$flash = Session::getFlashMessage();
?>

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
    <?php echo htmlspecialchars($flash['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-0"><i class="bi bi-music-note"></i> Instrument-Pattern</h1>
        <small class="text-muted">Steuert die automatische Stimmen-Erkennung beim PDF-Aufteilen</small>
    </div>
    <div class="d-flex gap-2">
        <a href="einstellungen.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Einstellungen
        </a>
        <button type="button" class="btn btn-primary" onclick="openModal(0)">
            <i class="bi bi-plus-circle"></i> Neues Instrument
        </button>
    </div>
</div>

<!-- Hilfe -->
<div class="card mb-3 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#hilfeBox">
        <h6 class="mb-0">
            <i class="bi bi-info-circle text-info me-1"></i> Pattern-Hilfe
            <small class="text-muted ms-2">(zum Auf-/Zuklappen)</small>
        </h6>
    </div>
    <div class="collapse" id="hilfeBox">
        <div class="card-body">
            <p class="mb-2">Jeder Eintrag definiert ein <strong>Teil-Muster</strong> (PCRE-Regex), das in den Seitentexten der PDF gesucht wird. Das vollständige Suchmuster lautet:</p>
            <code class="d-block bg-light p-2 mb-3 rounded small">(?:(\d+)\s*[.\-]\s*)?(<strong>PATTERN</strong>)(?:\s*in\s+[A-Za-z]+)?(?:\s*(?:[IVX]+|(\d+)))?\b</code>
            <p class="mb-1">Der Rahmen erkennt automatisch:</p>
            <ul class="mb-3 small">
                <li><code>1.Trompete</code>, <code>2. Trompete</code> → Nummern-Präfix</li>
                <li><code>Trompete in B</code>, <code>Klarinette in Es</code> → Tonart-Suffix</li>
                <li><code>Trompete I</code>, <code>Trompete 2</code> → Stimmennummer-Suffix</li>
            </ul>
            <p class="mb-2">Häufige Regex-Elemente im <strong>Pattern</strong>-Feld:</p>
            <table class="table table-sm table-bordered w-auto small mb-3">
                <tr><td><code>[öu]</code></td><td>ö <em>oder</em> u (z.B. <code>Fl[öu]te</code> → Flöte und Flute)</td></tr>
                <tr><td><code>[n]?</code></td><td>optionales n (z.B. <code>Trompete[n]?</code> → Trompete/Trompeten)</td></tr>
                <tr><td><code>(?:...)</code></td><td>Gruppe ohne Capture</td></tr>
                <tr><td><code>\s*</code></td><td>beliebig viele Leerzeichen (auch keins)</td></tr>
                <tr><td><code>[A-H]</code></td><td>ein Buchstabe A–H</td></tr>
                <tr><td><code>a|b</code></td><td>a <em>oder</em> b</td></tr>
            </table>
            <div class="alert alert-warning mb-0 py-2 small">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>Reihenfolge ist entscheidend!</strong> Spezifischere Patterns müssen <em>vor</em> allgemeineren stehen
                (z.B. "Baritonsaxophon" vor "Saxophon", "Tenorhorn" vor "Horn", "Basstuba" vor "Tuba").
            </div>
        </div>
    </div>
</div>

<!-- Tabelle -->
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:90px">Reihenf.</th>
                    <th style="width:60px" class="text-center">Aktiv</th>
                    <th>Instrument</th>
                    <th>Pattern</th>
                    <th>Beschreibung</th>
                    <th style="width:90px" class="text-end">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patterns as $p): ?>
                <tr class="<?php echo $p['aktiv'] ? '' : 'table-secondary opacity-60'; ?>">
                    <td>
                        <div class="d-flex align-items-center gap-1">
                            <small class="text-muted me-1" style="min-width:26px"><?php echo (int)$p['sortierung']; ?></small>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="dir" value="up">
                                <button class="btn btn-sm btn-link p-0 text-muted" title="Nach oben">
                                    <i class="bi bi-chevron-up"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="dir" value="down">
                                <button class="btn btn-sm btn-link p-0 text-muted" title="Nach unten">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                    <td class="text-center">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-link p-0"
                                    title="<?php echo $p['aktiv'] ? 'Deaktivieren' : 'Aktivieren'; ?>">
                                <i class="bi <?php echo $p['aktiv'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted'; ?> fs-5"></i>
                            </button>
                        </form>
                    </td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><code class="small"><?php echo htmlspecialchars($p['pattern']); ?></code></td>
                    <td><small class="text-muted"><?php echo htmlspecialchars($p['beschreibung'] ?? ''); ?></small></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-primary"
                                onclick='openModal(<?php echo $p["id"]; ?>, <?php echo json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                title="Bearbeiten">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Pattern für &quot;<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>&quot; wirklich löschen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Löschen">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($patterns)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Keine Pattern vorhanden.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="patternModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="modalId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Neues Instrument</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modalName" class="form-label">Instrument <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modalName" name="name" required
                               placeholder="z.B. Trompete">
                    </div>
                    <div class="mb-3">
                        <label for="modalPattern" class="form-label">Pattern <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace" id="modalPattern" name="pattern" required
                               placeholder="z.B. Trompete[n]?">
                        <div class="form-text">PCRE-Regex, nur den Instrument-Teil. Kein <code>/</code> am Anfang/Ende nötig.</div>
                    </div>
                    <div class="row">
                        <div class="col-7 mb-3">
                            <label for="modalSort" class="form-label">Reihenfolge</label>
                            <input type="number" class="form-control" id="modalSort" name="sortierung" min="1">
                            <div class="form-text">Niedrigerer Wert = höhere Priorität</div>
                        </div>
                        <div class="col-5 mb-3 d-flex align-items-center pt-2">
                            <div class="form-check mt-3">
                                <input type="checkbox" class="form-check-input" id="modalAktiv" name="aktiv">
                                <label class="form-check-label" for="modalAktiv">Aktiv</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modalBeschr" class="form-label">
                            Beschreibung <small class="text-muted">(optional – z.B. Hinweis zur Reihenfolge)</small>
                        </label>
                        <textarea class="form-control" id="modalBeschr" name="beschreibung" rows="2"
                                  placeholder="z.B. Muss vor Horn eingeordnet werden"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
var defaultSort = <?php echo (int)$maxSort; ?>;

function openModal(id, data) {
    document.getElementById('modalId').value = id || 0;
    document.getElementById('modalTitle').textContent = id ? 'Instrument bearbeiten' : 'Neues Instrument';

    if (data) {
        document.getElementById('modalName').value    = data.name        || '';
        document.getElementById('modalPattern').value = data.pattern     || '';
        document.getElementById('modalSort').value    = data.sortierung  || 100;
        document.getElementById('modalAktiv').checked = data.aktiv == 1;
        document.getElementById('modalBeschr').value  = data.beschreibung || '';
    } else {
        document.getElementById('modalName').value    = '';
        document.getElementById('modalPattern').value = '';
        document.getElementById('modalSort').value    = defaultSort;
        document.getElementById('modalAktiv').checked = true;
        document.getElementById('modalBeschr').value  = '';
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('patternModal')).show();
}
</script>
