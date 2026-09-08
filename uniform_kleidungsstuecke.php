<?php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('uniformen', 'schreiben');

$uniformObj = new Uniform();

// -------------------------------------------------------
// AJAX: Fehlende Mitglieder für ein Kleidungsstück laden
// -------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fehlende_mitglieder' && isset($_GET['id'])) {
    $ksId = (int)$_GET['id'];
    header('Content-Type: application/json; charset=utf-8');
    try {
        $db = Database::getInstance();
        $mitglieder = $db->fetchAll(
            "SELECT m.id, m.vorname, m.nachname
             FROM mitglieder m
             WHERE m.status = 'aktiv'
               AND m.id NOT IN (
                   SELECT uz.mitglied_id
                   FROM uniform_zuweisungen uz
                   WHERE uz.kleidungsstueck_id = ?
               )
             ORDER BY m.nachname, m.vorname",
            [$ksId]
        );
        $ks = $db->fetchOne(
            "SELECT groessen_verfuegbar FROM uniform_kleidungsstuecke WHERE id = ?",
            [$ksId]
        );
        $groessen = [];
        if (!empty($ks['groessen_verfuegbar'])) {
            $groessen = array_map('trim', explode(',', $ks['groessen_verfuegbar']));
        }
        echo json_encode(['success' => true, 'mitglieder' => $mitglieder, 'groessen' => $groessen]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------
// AJAX: Kleidungsstück einem Mitglied zuweisen
// -------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'zuweisen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $mitgliedId        = (int)($_POST['mitglied_id'] ?? 0);
        $kleidungsstueckId = (int)($_POST['kleidungsstueck_id'] ?? 0);
        if (!$mitgliedId || !$kleidungsstueckId) throw new Exception('Ungültige Parameter.');
        $data = [
            'groesse'       => trim($_POST['groesse'] ?? '') ?: null,
            'zustand'       => $_POST['zustand'] ?? 'gut',
            'ausgabe_datum' => $_POST['ausgabe_datum'] ?: date('Y-m-d'),
            'bemerkungen'   => trim($_POST['bemerkungen'] ?? '') ?: null,
        ];
        $uniformObj->zuweisen($mitgliedId, $kleidungsstueckId, $data);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// -------------------------------------------------------
// AJAX: Kleidungsstück als "nicht benötigt" markieren
// -------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'nicht_benoetigt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $mitgliedId        = (int)($_POST['mitglied_id'] ?? 0);
        $kleidungsstueckId = (int)($_POST['kleidungsstueck_id'] ?? 0);
        if (!$mitgliedId || !$kleidungsstueckId) throw new Exception('Ungültige Parameter.');
        $uniformObj->nichtBenoetigt($mitgliedId, $kleidungsstueckId);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Aktionen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            // KATEGORIEN
            case 'kategorie_erstellen':
                $data = [
                    'name' => trim($_POST['name'] ?? ''),
                    'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                    'sortierung' => (int)($_POST['sortierung'] ?? 100)
                ];
                if (empty($data['name'])) throw new Exception('Bitte einen Namen eingeben.');
                $uniformObj->createKategorie($data);
                Session::setFlashMessage('success', 'Kategorie erfolgreich angelegt.');
                break;
                
            case 'kategorie_aktualisieren':
                $data = [
                    'name' => trim($_POST['name'] ?? ''),
                    'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                    'sortierung' => (int)($_POST['sortierung'] ?? 100)
                ];
                if (empty($data['name'])) throw new Exception('Bitte einen Namen eingeben.');
                $uniformObj->updateKategorie($_POST['id'], $data);
                Session::setFlashMessage('success', 'Kategorie erfolgreich aktualisiert.');
                break;
                
            case 'kategorie_loeschen':
                Session::requirePermission('uniformen', 'loeschen');
                $uniformObj->deleteKategorie($_POST['id']);
                Session::setFlashMessage('success', 'Kategorie erfolgreich gelöscht.');
                break;
                
            // KLEIDUNGSSTÜCKE
            case 'kleidungsstueck_erstellen':
                $data = [
                    'kategorie_id' => $_POST['kategorie_id'] ?: null,
                    'name' => trim($_POST['name'] ?? ''),
                    'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                    'groessen_verfuegbar' => trim($_POST['groessen_verfuegbar'] ?? '') ?: null,
                    'sortierung' => (int)($_POST['sortierung'] ?? 100)
                ];
                if (empty($data['name'])) throw new Exception('Bitte einen Namen eingeben.');
                $uniformObj->createKleidungsstueck($data);
                Session::setFlashMessage('success', 'Kleidungsstück erfolgreich angelegt.');
                break;
                
            case 'kleidungsstueck_aktualisieren':
                $data = [
                    'kategorie_id' => $_POST['kategorie_id'] ?: null,
                    'name' => trim($_POST['name'] ?? ''),
                    'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                    'groessen_verfuegbar' => trim($_POST['groessen_verfuegbar'] ?? '') ?: null,
                    'sortierung' => (int)($_POST['sortierung'] ?? 100)
                ];
                if (empty($data['name'])) throw new Exception('Bitte einen Namen eingeben.');
                $uniformObj->updateKleidungsstueck($_POST['id'], $data);
                Session::setFlashMessage('success', 'Kleidungsstück erfolgreich aktualisiert.');
                break;
                
            case 'kleidungsstueck_loeschen':
                Session::requirePermission('uniformen', 'loeschen');
                $uniformObj->deleteKleidungsstueck($_POST['id']);
                Session::setFlashMessage('success', 'Kleidungsstück erfolgreich gelöscht.');
                break;
        }
    } catch (Exception $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    
    header('Location: uniform_kleidungsstuecke.php');
    exit;
}

$kategorien      = $uniformObj->getKategorien();
$kleidungsstuecke = $uniformObj->getKleidungsstuecke();

// -------------------------------------------------------
// Bedarf nach Größe: wie viele Zuweisungen je Größe
// pro Kleidungsstück existieren
// -------------------------------------------------------
$bedarfNachGroesse = [];
try {
    $db = Database::getInstance();
    $rows = $db->fetchAll(
        "SELECT kleidungsstueck_id,
                COALESCE(NULLIF(TRIM(groesse), ''), 'o.A.') AS groesse,
                COUNT(*) AS anzahl
         FROM uniform_zuweisungen
         GROUP BY kleidungsstueck_id, groesse
         ORDER BY kleidungsstueck_id, groesse"
    );
    foreach ($rows as $row) {
        $bedarfNachGroesse[$row['kleidungsstueck_id']][$row['groesse']] = $row['anzahl'];
    }
} catch (Exception $e) {
    $bedarfNachGroesse = [];
}

// Anzahl fehlender Mitglieder je Kleidungsstück
$fehlendAnzahl = [];
try {
    $aktiveMitglieder = (int)$db->fetchOne("SELECT COUNT(*) as c FROM mitglieder WHERE status = 'aktiv'")['c'];
    $rows = $db->fetchAll(
        "SELECT kleidungsstueck_id, COUNT(DISTINCT mitglied_id) AS zugeteilt
         FROM uniform_zuweisungen
         GROUP BY kleidungsstueck_id"
    );
    foreach ($rows as $row) {
        $fehlendAnzahl[$row['kleidungsstueck_id']] = max(0, $aktiveMitglieder - (int)$row['zugeteilt']);
    }
    // Kleidungsstücke ohne jeden Eintrag → alle fehlen
    foreach ($kleidungsstuecke as $k) {
        if (!isset($fehlendAnzahl[$k['id']])) {
            $fehlendAnzahl[$k['id']] = $aktiveMitglieder;
        }
    }
} catch (Exception $e) {
    $fehlendAnzahl = [];
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="bi bi-tags"></i> Kleidungsstücke verwalten</h1>
    <a href="uniformen.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Zurück zur Übersicht
    </a>
</div>

<div class="row">
    <!-- Kategorien -->
    <div class="col-lg-3">
        <div class="card mb-4 p-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-folder"></i> Kategorien</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#neuKategorieModal">
                    <i class="bi bi-plus"></i>
                </button>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (empty($kategorien)): ?>
                <li class="list-group-item text-muted text-center">Keine Kategorien</li>
                <?php else: ?>
                <?php foreach ($kategorien as $kat): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?php echo htmlspecialchars($kat['name']); ?></strong>
                        <?php if ($kat['beschreibung']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($kat['beschreibung']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary btn-edit-kat"
                                data-id="<?php echo $kat['id']; ?>"
                                data-name="<?php echo htmlspecialchars($kat['name']); ?>"
                                data-beschreibung="<?php echo htmlspecialchars($kat['beschreibung'] ?? ''); ?>"
                                data-sortierung="<?php echo $kat['sortierung']; ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Kategorie löschen?');">
                            <input type="hidden" name="action" value="kategorie_loeschen">
                            <input type="hidden" name="id" value="<?php echo $kat['id']; ?>">
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <!-- Kleidungsstücke -->
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-tag"></i> Kleidungsstücke</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#neuKleidungModal">
                    <i class="bi bi-plus"></i> Neu
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($kleidungsstuecke)): ?>
                <p class="text-muted text-center mb-0">Keine Kleidungsstücke definiert</p>
                <?php else: ?>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Kategorie</th>
                            <th>Name</th>
                            <th>Größen</th>
                            <th>Zuweisungen nach Größe</th>
                            <th class="text-end">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kleidungsstuecke as $k): ?>
                        <tr>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($k['kategorie_name'] ?? 'Ohne'); ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($k['name']); ?></strong>
                                <?php if ($k['beschreibung']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($k['beschreibung']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo htmlspecialchars($k['groessen_verfuegbar'] ?? '-'); ?></small></td>
                            <td>
                                <?php
                                $bedarf = $bedarfNachGroesse[$k['id']] ?? [];
                                if (!empty($bedarf)):
                                    foreach ($bedarf as $groesse => $anzahl):
                                ?>
                                <span class="badge bg-info text-dark me-1 mb-1">
                                    <?php echo htmlspecialchars($groesse); ?>: <?php echo (int)$anzahl; ?>×
                                </span>
                                <?php
                                    endforeach;
                                else:
                                ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                                <br>
                                <?php if (($fehlendAnzahl[$k['id']] ?? 0) > 0): ?>
                                <button type="button"
                                        class="btn btn-sm btn-outline-warning mt-1 btn-fehlende-mitglieder"
                                        data-id="<?php echo $k['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($k['name']); ?>"
                                        title="Aktive Mitglieder ohne dieses Kleidungsstück anzeigen">
                                    <i class="bi bi-people"></i> Fehlend
                                    <span class="badge bg-warning text-dark ms-1"><?php echo $fehlendAnzahl[$k['id']]; ?></span>
                                </button>
                                <?php else: ?>
                                <span class="badge bg-success mt-1"><i class="bi bi-check-circle"></i> Alle versorgt</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-edit-ks"
                                            data-id="<?php echo $k['id']; ?>"
                                            data-kategorie="<?php echo $k['kategorie_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($k['name']); ?>"
                                            data-beschreibung="<?php echo htmlspecialchars($k['beschreibung'] ?? ''); ?>"
                                            data-groessen="<?php echo htmlspecialchars($k['groessen_verfuegbar'] ?? ''); ?>"
                                            data-sortierung="<?php echo $k['sortierung']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Kleidungsstück löschen?');">
                                        <input type="hidden" name="action" value="kleidungsstueck_loeschen">
                                        <input type="hidden" name="id" value="<?php echo $k['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Neue Kategorie -->
<div class="modal fade" id="neuKategorieModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="kategorie_erstellen">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Neue Kategorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="z.B. Festtracht, Sommertracht">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sortierung</label>
                        <input type="number" class="form-control" name="sortierung" value="100" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Kategorie bearbeiten -->
<div class="modal fade" id="editKategorieModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="kategorie_aktualisieren">
                <input type="hidden" name="id" id="edit_kat_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Kategorie bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_kat_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" id="edit_kat_beschreibung" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sortierung</label>
                        <input type="number" class="form-control" name="sortierung" id="edit_kat_sortierung" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Neues Kleidungsstück -->
<div class="modal fade" id="neuKleidungModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="kleidungsstueck_erstellen">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Neues Kleidungsstück</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategorie</label>
                        <select class="form-select" name="kategorie_id">
                            <option value="">-- Ohne Kategorie --</option>
                            <?php foreach ($kategorien as $kat): ?>
                            <option value="<?php echo $kat['id']; ?>"><?php echo htmlspecialchars($kat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required placeholder="z.B. Jacke, Hose, Hut">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Verfügbare Größen</label>
                        <input type="text" class="form-control" name="groessen_verfuegbar" placeholder="z.B. S, M, L, XL">
                        <small class="text-muted">Komma-getrennt, optional</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sortierung</label>
                        <input type="number" class="form-control" name="sortierung" value="100" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Kleidungsstück bearbeiten -->
<div class="modal fade" id="editKleidungModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="kleidungsstueck_aktualisieren">
                <input type="hidden" name="id" id="edit_ks_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Kleidungsstück bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategorie</label>
                        <select class="form-select" name="kategorie_id" id="edit_ks_kategorie">
                            <option value="">-- Ohne Kategorie --</option>
                            <?php foreach ($kategorien as $kat): ?>
                            <option value="<?php echo $kat['id']; ?>"><?php echo htmlspecialchars($kat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="edit_ks_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" id="edit_ks_beschreibung" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Verfügbare Größen</label>
                        <input type="text" class="form-control" name="groessen_verfuegbar" id="edit_ks_groessen">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sortierung</label>
                        <input type="number" class="form-control" name="sortierung" id="edit_ks_sortierung" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Eigenes Overlay: Fehlende Mitglieder (kein Bootstrap-Modal) -->
<style>
#fehlendeOverlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 1055;
    overflow-y: auto;
    padding: 30px 15px;
}
#fehlendeOverlay .overlay-dialog {
    background: #fff;
    border-radius: 8px;
    max-width: 1100px;
    margin: 0 auto;
    box-shadow: 0 5px 30px rgba(0,0,0,.3);
}
[data-bs-theme="dark"] #fehlendeOverlay .overlay-dialog { background: #2b2d42; color: #eee; }
</style>

<div id="fehlendeOverlay">
    <div class="overlay-dialog">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-warning-subtle rounded-top">
            <h5 class="mb-0"><i class="bi bi-people"></i> Mitglieder ohne: <span id="fehlende_ks_name" class="fw-bold"></span></h5>
            <button type="button" class="btn-close" id="fehlendeClose"></button>
        </div>
        <div class="p-3">
            <div id="fehlende_loading" class="text-center py-4">
                <div class="spinner-border text-warning" role="status"></div>
                <p class="mt-2 text-muted">Wird geladen…</p>
            </div>
            <div id="fehlende_content" style="display:none">
                <p class="text-muted mb-3">Folgende aktive Mitglieder haben dieses Kleidungsstück noch <strong>nicht zugeteilt</strong> bekommen. Direkt zuweisen über die Felder in der jeweiligen Zeile:</p>
                <div id="fehlende_leer" class="alert alert-success alert-permanent" style="display:none">
                    <i class="bi bi-check-circle"></i> Alle aktiven Mitglieder haben dieses Kleidungsstück bereits erhalten.
                </div>
                <div id="fehlende_liste_wrapper">
                    <p class="fw-semibold mb-2">Noch fehlend: <span id="fehlende_anzahl" class="badge bg-warning text-dark fs-6"></span></p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th><th>Nachname</th><th>Vorname</th>
                                    <th>Größe</th><th>Zustand</th><th>Ausgabedatum</th>
                                    <th>Zuweisen</th><th>Nicht benötigt</th>
                                </tr>
                            </thead>
                            <tbody id="fehlende_tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div id="fehlende_fehler" class="alert alert-danger alert-permanent" style="display:none">
                <i class="bi bi-exclamation-triangle"></i> <span id="fehlende_fehler_text"></span>
            </div>
        </div>
        <div class="p-3 border-top text-end">
            <button type="button" class="btn btn-secondary" id="fehlendeClose2">Schließen</button>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function() {

var overlay      = document.getElementById('fehlendeOverlay');
var aktuelleKsId = null;

function overlayShow() { overlay.style.display = 'block'; document.body.style.overflow = 'hidden'; }
function overlayHide() { overlay.style.display = 'none'; document.body.style.overflow = ''; window.location.href = window.location.pathname + '?t=' + Date.now(); }

document.getElementById('fehlendeClose').addEventListener('click', overlayHide);
document.getElementById('fehlendeClose2').addEventListener('click', overlayHide);
overlay.addEventListener('click', function(e) { if (e.target === overlay) overlayHide(); });

// -------------------------------------------------------
// Fehlende Mitglieder laden
// -------------------------------------------------------
document.querySelectorAll('.btn-fehlende-mitglieder').forEach(function(btn) {
    btn.addEventListener('click', function() {
        aktuelleKsId = this.dataset.id;

        document.getElementById('fehlende_ks_name').textContent = this.dataset.name;
        document.getElementById('fehlende_loading').style.display = 'block';
        document.getElementById('fehlende_content').style.display = 'none';
        document.getElementById('fehlende_fehler').style.display  = 'none';
        document.getElementById('fehlende_leer').style.display    = 'none';
        document.getElementById('fehlende_liste_wrapper').style.display = 'block';
        document.getElementById('fehlende_tbody').innerHTML = '';

        overlayShow();

        fetch('uniform_kleidungsstuecke.php?ajax=fehlende_mitglieder&id=' + encodeURIComponent(aktuelleKsId), {
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('fehlende_loading').style.display = 'none';

            if (!data.success) {
                document.getElementById('fehlende_fehler').style.display = 'block';
                document.getElementById('fehlende_fehler_text').textContent = data.error || 'Unbekannter Fehler';
                return;
            }

            document.getElementById('fehlende_content').style.display = 'block';
            var mitglieder = data.mitglieder;
            var groessen   = data.groessen || [];

            if (mitglieder.length === 0) {
                document.getElementById('fehlende_leer').style.display = 'block';
                document.getElementById('fehlende_liste_wrapper').style.display = 'none';
                return;
            }

            document.getElementById('fehlende_anzahl').textContent = mitglieder.length;
            var tbody = document.getElementById('fehlende_tbody');

            mitglieder.forEach(function(m, idx) {
                var today = new Date().toISOString().split('T')[0];

                var groesseHtml;
                if (groessen.length > 0) {
                    groesseHtml = '<select class="form-select form-select-sm" name="groesse"><option value="">—</option>';
                    groessen.forEach(function(g) {
                        groesseHtml += '<option value="' + escHtml(g) + '">' + escHtml(g) + '</option>';
                    });
                    groesseHtml += '</select>';
                } else {
                    groesseHtml = '<input type="text" class="form-control form-control-sm" name="groesse" placeholder="z.B. M">';
                }

                var tr = document.createElement('tr');
                tr.dataset.mitgliedId = m.id;
                tr.innerHTML =
                    '<td class="text-muted small">' + (idx + 1) + '</td>'
                  + '<td><strong>' + escHtml(m.nachname) + '</strong></td>'
                  + '<td>' + escHtml(m.vorname) + '</td>'
                  + '<td style="min-width:100px">' + groesseHtml + '</td>'
                  + '<td style="min-width:130px">'
                  +   '<select class="form-select form-select-sm" name="zustand">'
                  +     '<option value="sehr gut">Sehr gut</option>'
                  +     '<option value="gut" selected>Gut</option>'
                  +     '<option value="befriedigend">Befriedigend</option>'
                  +     '<option value="schlecht">Schlecht</option>'
                  +   '</select>'
                  + '</td>'
                  + '<td style="min-width:140px"><input type="date" class="form-control form-control-sm" name="ausgabe_datum" value="' + today + '"></td>'
                  + '<td><button type="button" class="btn btn-sm btn-success btn-zuweisen"><i class="bi bi-check-lg"></i> Zuweisen</button></td>'
                  + '<td><button type="button" class="btn btn-sm btn-outline-secondary btn-nicht-benoetigt" title="Dieses Kleidungsstück wird von dieser Person nicht benötigt"><i class="bi bi-slash-circle"></i> Nicht benötigt</button></td>';

                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('.btn-nicht-benoetigt').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var tr         = this.closest('tr');
                    var mitgliedId = tr.dataset.mitgliedId;

                    if (!confirm('Dieses Kleidungsstück für ' + tr.querySelector('td:nth-child(2)').textContent + ' ' + tr.querySelector('td:nth-child(3)').textContent + ' als "nicht benötigt" markieren?')) return;

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    var formData = new FormData();
                    formData.append('mitglied_id',        mitgliedId);
                    formData.append('kleidungsstueck_id', aktuelleKsId);

                    fetch('uniform_kleidungsstuecke.php?ajax=nicht_benoetigt', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            tr.classList.add('table-secondary');
                            tr.querySelectorAll('input, select, button').forEach(function(el) { el.disabled = true; });
                            btn.innerHTML = '<i class="bi bi-slash-circle"></i> Nicht benötigt';
                            tr.querySelector('.btn-zuweisen').style.display = 'none';
                            var badge = document.getElementById('fehlende_anzahl');
                            var neu = parseInt(badge.textContent) - 1;
                            badge.textContent = neu;
                            if (neu === 0) {
                                document.getElementById('fehlende_leer').style.display = 'block';
                                document.getElementById('fehlende_liste_wrapper').style.display = 'none';
                            }
                        } else {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-slash-circle"></i> Nicht benötigt';
                            alert('Fehler: ' + (res.error || 'Unbekannter Fehler'));
                        }
                    })
                    .catch(function(err) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-slash-circle"></i> Nicht benötigt';
                        alert('Verbindungsfehler: ' + err.message);
                    });
                });
            });

            tbody.querySelectorAll('.btn-zuweisen').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var tr         = this.closest('tr');
                    var mitgliedId = tr.dataset.mitgliedId;
                    var groesse    = tr.querySelector('[name=groesse]').value;
                    var zustand    = tr.querySelector('[name=zustand]').value;
                    var datum      = tr.querySelector('[name=ausgabe_datum]').value;

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    var formData = new FormData();
                    formData.append('mitglied_id',        mitgliedId);
                    formData.append('kleidungsstueck_id', aktuelleKsId);
                    formData.append('groesse',            groesse);
                    formData.append('zustand',            zustand);
                    formData.append('ausgabe_datum',      datum);

                    fetch('uniform_kleidungsstuecke.php?ajax=zuweisen', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            tr.classList.add('table-success');
                            tr.querySelectorAll('input, select, button').forEach(function(el) { el.disabled = true; });
                            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Zugewiesen';
                            var badge = document.getElementById('fehlende_anzahl');
                            var neu = parseInt(badge.textContent) - 1;
                            badge.textContent = neu;
                            if (neu === 0) {
                                document.getElementById('fehlende_leer').style.display = 'block';
                                document.getElementById('fehlende_liste_wrapper').style.display = 'none';
                            }
                        } else {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-check-lg"></i> Zuweisen';
                            alert('Fehler: ' + (res.error || 'Unbekannter Fehler'));
                        }
                    })
                    .catch(function(err) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-check-lg"></i> Zuweisen';
                        alert('Verbindungsfehler: ' + err.message);
                    });
                });
            });
        })
        .catch(function(err) {
            document.getElementById('fehlende_loading').style.display = 'none';
            document.getElementById('fehlende_fehler').style.display  = 'block';
            document.getElementById('fehlende_fehler_text').textContent = 'Verbindungsfehler: ' + err.message;
        });
    });
});

function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
}

// Kategorie bearbeiten
document.querySelectorAll('.btn-edit-kat').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_kat_id').value = this.dataset.id;
        document.getElementById('edit_kat_name').value = this.dataset.name;
        document.getElementById('edit_kat_beschreibung').value = this.dataset.beschreibung;
        document.getElementById('edit_kat_sortierung').value = this.dataset.sortierung;
        new bootstrap.Modal(document.getElementById('editKategorieModal')).show();
    });
});

// Kleidungsstück bearbeiten
document.querySelectorAll('.btn-edit-ks').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_ks_id').value = this.dataset.id;
        document.getElementById('edit_ks_kategorie').value = this.dataset.kategorie || '';
        document.getElementById('edit_ks_name').value = this.dataset.name;
        document.getElementById('edit_ks_beschreibung').value = this.dataset.beschreibung;
        document.getElementById('edit_ks_groessen').value = this.dataset.groessen;
        document.getElementById('edit_ks_sortierung').value = this.dataset.sortierung;
        new bootstrap.Modal(document.getElementById('editKleidungModal')).show();
    });
});

}); // end window.addEventListener('load')
</script>

<?php include 'includes/footer.php'; ?>