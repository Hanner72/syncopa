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
            "SELECT m.id, m.vorname, m.nachname,
                    uz.id AS zid, uz.nicht_benoetigt, uz.groesse, uz.anzahl, uz.zustand, uz.ausgabe_datum, st.nummer
             FROM mitglieder m
             LEFT JOIN uniform_zuweisungen uz ON uz.mitglied_id = m.id AND uz.kleidungsstueck_id = ?
             LEFT JOIN uniform_stuecke st ON st.id = uz.stueck_id
             WHERE m.mitgliedsart_code IN (SELECT code FROM mitgliedsarten WHERE aktiv = 1 AND gast = 0)
             ORDER BY m.nachname, m.vorname",
            [$ksId]
        );
        $ksDaten = $uniformObj->getKleidungsstueckById($ksId);
        $groessen = [];
        foreach ($uniformObj->bestandNachGroesse($ksId) as $z) {
            if ($z['groesse'] !== '') $groessen[] = ['g' => $z['groesse'], 'lager' => $z['lager']];
        }
        $einzeln = ($ksDaten['bestandsart'] ?? 'menge') === 'einzeln';
        echo json_encode(['success' => true, 'mitglieder' => $mitglieder, 'groessen' => $groessen,
            'art' => $einzeln ? 'einzeln' : 'menge', 'kuerzel' => $ksDaten['kuerzel'] ?? '', 'stuecke' => $einzeln ? $uniformObj->lagerStuecke($ksId) : []]);
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
            'stueck_id'     => (int)($_POST['stueck_id'] ?? 0) ?: null,
            'anzahl'        => (int)($_POST['anzahl'] ?? 1),
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
                    'kuerzel' => $_POST['kuerzel'] ?? '',
                    'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                    'groessen_verfuegbar' => trim($_POST['groessen_verfuegbar'] ?? '') ?: null,
                    'bestandsart' => ($_POST['bestandsart'] ?? 'menge') === 'einzeln' ? 'einzeln' : 'menge',
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
                    'kuerzel' => $_POST['kuerzel'] ?? '',
                    'beschreibung' => trim($_POST['beschreibung'] ?? '') ?: null,
                    'groessen_verfuegbar' => trim($_POST['groessen_verfuegbar'] ?? '') ?: null,
                    'bestandsart' => ($_POST['bestandsart'] ?? 'menge') === 'einzeln' ? 'einzeln' : 'menge',
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
                SUM(anzahl) AS anzahl
         FROM uniform_zuweisungen
         WHERE nicht_benoetigt = 0
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
    $aktiveMitglieder = (int)$db->fetchOne("SELECT COUNT(*) as c FROM mitglieder WHERE mitgliedsart_code IN (SELECT code FROM mitgliedsarten WHERE aktiv = 1 AND gast = 0)")['c'];
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
    <div class="d-flex gap-2">
        <a href="uniform_bestand.php" class="btn btn-outline-primary"><i class="bi bi-boxes"></i> Bestand</a>
        <a href="uniformen.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Zurück zur Übersicht
        </a>
    </div>
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
                            <th>Ausgegeben nach Größe</th>
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
                                <?php if (!empty($k['kuerzel'])): ?><span class="badge bg-dark ms-1 font-monospace" title="Kürzel – steht vor der Stücknummer"><?php echo htmlspecialchars($k['kuerzel']); ?></span><?php endif; ?>
                                <a href="uniform_bestand.php?id=<?php echo $k['id']; ?>" class="badge text-bg-light border text-decoration-none ms-1" title="Bestand ansehen">
                                    <?php echo ($k['bestandsart'] ?? 'menge') === 'einzeln' ? '<i class="bi bi-hash"></i> Einzelstücke' : '<i class="bi bi-stack"></i> Menge'; ?>
                                </a>
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
                                <?php $fehlt = $fehlendAnzahl[$k['id']] ?? 0; ?>
                                <button type="button"
                                        class="btn btn-sm <?php echo $fehlt ? 'btn-outline-warning' : 'btn-outline-success'; ?> mt-1 btn-fehlende-mitglieder"
                                        data-id="<?php echo $k['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($k['name']); ?>"
                                        data-filter="<?php echo $fehlt ? 'ohne' : 'alle'; ?>"
                                        title="Aktive Mitglieder und dieses Kleidungsstück anzeigen">
                                    <?php if ($fehlt): ?>
                                    <i class="bi bi-people"></i> Fehlend
                                    <span class="badge bg-warning text-dark ms-1"><?php echo $fehlt; ?></span>
                                    <?php else: ?>
                                    <i class="bi bi-check-circle"></i> Alle versorgt
                                    <?php endif; ?>
                                </button>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-edit-ks"
                                            data-id="<?php echo $k['id']; ?>"
                                            data-kategorie="<?php echo $k['kategorie_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($k['name']); ?>"
                                            data-kuerzel="<?php echo htmlspecialchars($k['kuerzel'] ?? ''); ?>"
                                            data-beschreibung="<?php echo htmlspecialchars($k['beschreibung'] ?? ''); ?>"
                                            data-groessen="<?php echo htmlspecialchars($k['groessen_verfuegbar'] ?? ''); ?>"
                                            data-bestandsart="<?php echo htmlspecialchars($k['bestandsart'] ?? 'menge'); ?>"
                                            data-wechselbar="<?php echo $uniformObj->bestandsartWechselbar((int)$k['id']) ? '1' : '0'; ?>"
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
                        <label class="form-label">Kürzel</label>
                        <input type="text" class="form-control font-monospace text-uppercase" name="kuerzel" maxlength="5" placeholder="z. B. L für Lederhose" style="max-width:160px">
                        <small class="text-muted">Steht vor der Stücknummer (L001, H001) – wichtig bei Einzelstücken. Ändern ist jederzeit möglich, die Nummern passen sich an.</small>
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
                        <label class="form-label">Bestand führen als</label>
                        <select class="form-select" name="bestandsart">
                            <option value="menge">Menge – nur Anzahl je Größe (z. B. Hemden, Polos)</option>
                            <option value="einzeln">Einzelstücke mit Nummer (z. B. Lederhosen, Jacken)</option>
                        </select>
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
                        <label class="form-label">Kürzel</label>
                        <input type="text" class="form-control font-monospace text-uppercase" name="kuerzel" id="edit_ks_kuerzel" maxlength="5" placeholder="z. B. L für Lederhose" style="max-width:160px">
                        <small class="text-muted">Steht vor der Stücknummer (L001, H001) – wichtig bei Einzelstücken. Ändern ist jederzeit möglich, die Nummern passen sich an.</small>
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
                        <label class="form-label">Bestand führen als</label>
                        <select class="form-select" name="bestandsart" id="edit_ks_bestandsart">
                            <option value="menge">Menge – nur Anzahl je Größe (z. B. Hemden, Polos)</option>
                            <option value="einzeln">Einzelstücke mit Nummer (z. B. Lederhosen, Jacken)</option>
                        </select>
                        <small class="text-muted d-none" id="edit_ks_art_gesperrt">Nicht mehr änderbar, weil schon Bestand erfasst ist.</small>
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

<!-- Eigenes Overlay: Mitglieder und dieses Kleidungsstück (kein Bootstrap-Modal) -->
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
    background: var(--bg-card, #fff);
    color: var(--text-primary, #212529);
    border-radius: 8px;
    max-width: 1150px;
    margin: 0 auto;
    box-shadow: 0 5px 30px rgba(0,0,0,.3);
}
#fehlende_tbody tr.ul-hat td { background: rgba(25, 135, 84, .06); }
#fehlende_tbody tr.ul-nb td { color: var(--text-muted, #6c757d); }
</style>

<div id="fehlendeOverlay">
    <div class="overlay-dialog">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-warning-subtle rounded-top">
            <h5 class="mb-0"><i class="bi bi-people"></i> Mitglieder und <span id="fehlende_ks_name" class="fw-bold"></span></h5>
            <button type="button" class="btn-close" id="fehlendeClose"></button>
        </div>
        <div class="p-3">
            <div id="fehlende_loading" class="text-center py-4">
                <div class="spinner-border text-warning" role="status"></div>
                <p class="mt-2 text-muted">Wird geladen…</p>
            </div>
            <div id="fehlende_content" style="display:none">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Anzeige">
                        <input type="radio" class="btn-check" name="ul_filter" id="ul_ohne" value="ohne" checked>
                        <label class="btn btn-outline-warning" for="ul_ohne">Nur ohne <span class="badge bg-warning text-dark ms-1" id="ul_n_ohne">0</span></label>
                        <input type="radio" class="btn-check" name="ul_filter" id="ul_alle" value="alle">
                        <label class="btn btn-outline-secondary" for="ul_alle">Alle aktiven <span class="badge bg-secondary ms-1" id="ul_n_alle">0</span></label>
                    </div>
                    <span class="small text-muted">
                        <i class="bi bi-check-circle text-success"></i> <span id="ul_n_hat">0</span> haben es ·
                        <i class="bi bi-slash-circle"></i> <span id="ul_n_nb">0</span> nicht benötigt
                    </span>
                </div>
                <div id="fehlende_leer" class="alert alert-success alert-permanent" style="display:none">
                    <i class="bi bi-check-circle"></i> Alle aktiven Mitglieder haben dieses Kleidungsstück bereits erhalten (oder brauchen es nicht).
                </div>
                <div class="table-responsive" id="fehlende_liste_wrapper">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th><th>Nachname</th><th>Vorname</th>
                                <th id="ul_th_groesse">Größe</th><th>Zustand</th><th>Ausgabedatum</th>
                                <th>Zuweisen</th><th>Nicht benötigt</th>
                            </tr>
                        </thead>
                        <tbody id="fehlende_tbody"></tbody>
                    </table>
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
var ZUSTAENDE    = {'sehr gut': 'Sehr gut', 'gut': 'Gut', 'befriedigend': 'Befriedigend', 'schlecht': 'Schlecht'};
var ZFARBE       = {'sehr gut': 'success', 'gut': 'info', 'befriedigend': 'warning', 'schlecht': 'danger'};

function overlayShow() { overlay.style.display = 'block'; document.body.style.overflow = 'hidden'; }
function overlayHide() { overlay.style.display = 'none'; document.body.style.overflow = ''; window.location.href = window.location.pathname + '?t=' + Date.now(); }

document.getElementById('fehlendeClose').addEventListener('click', overlayHide);
document.getElementById('fehlendeClose2').addEventListener('click', overlayHide);
overlay.addEventListener('click', function(e) { if (e.target === overlay) overlayHide(); });

function datumDe(d) { if (!d) return '–'; var p = d.split('-'); return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : d; }

// Filter „Nur ohne“ / „Alle aktiven“
function filtern() {
    var alle = document.getElementById('ul_alle').checked;
    var tbody = document.getElementById('fehlende_tbody');
    var sichtbar = 0, nr = 0;
    tbody.querySelectorAll('tr').forEach(function(tr) {
        var zeigen = alle || tr.dataset.status === 'ohne';
        tr.style.display = zeigen ? '' : 'none';
        if (zeigen) { sichtbar++; tr.querySelector('.ul-nr').textContent = ++nr; }
    });
    var n = { ohne: 0, hat: 0, nb: 0 };
    tbody.querySelectorAll('tr').forEach(function(tr) { n[tr.dataset.status]++; });
    document.getElementById('ul_n_ohne').textContent = n.ohne;
    document.getElementById('ul_n_alle').textContent = n.ohne + n.hat + n.nb;
    document.getElementById('ul_n_hat').textContent = n.hat;
    document.getElementById('ul_n_nb').textContent = n.nb;
    document.getElementById('fehlende_leer').style.display = (!alle && n.ohne === 0) ? 'block' : 'none';
    document.getElementById('fehlende_liste_wrapper').style.display = sichtbar ? 'block' : 'none';
}
['ul_ohne', 'ul_alle'].forEach(function(id) { document.getElementById(id).addEventListener('change', filtern); });

// -------------------------------------------------------
// Mitglieder laden
// -------------------------------------------------------
document.querySelectorAll('.btn-fehlende-mitglieder').forEach(function(btn) {
    btn.addEventListener('click', function() {
        aktuelleKsId = this.dataset.id;
        document.getElementById(this.dataset.filter === 'alle' ? 'ul_alle' : 'ul_ohne').checked = true;

        document.getElementById('fehlende_ks_name').textContent = this.dataset.name;
        document.getElementById('fehlende_loading').style.display = 'block';
        document.getElementById('fehlende_content').style.display = 'none';
        document.getElementById('fehlende_fehler').style.display  = 'none';
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
            var einzeln    = data.art === 'einzeln';
            var kuerzel    = data.kuerzel || '';
            var stuecke    = data.stuecke || [];
            var stueckInfo = {};
            stuecke.forEach(function(st) { stueckInfo[st.id] = st; });
            var lager      = {};
            groessen.forEach(function(z) { lager[z.g] = z.lager; });
            var tbody      = document.getElementById('fehlende_tbody');
            var today      = new Date().toISOString().split('T')[0];

            document.getElementById('ul_th_groesse').textContent = einzeln ? 'Stück (Nr.)' : 'Größe (im Lager)';

            function stueckOptionen() {
                var h = '';
                stuecke.forEach(function(st) { h += '<option value="' + st.id + '">' + escHtml(kuerzel + st.nummer) + (st.groesse ? ' · ' + escHtml(st.groesse) : '') + '</option>'; });
                return h + '<option value="">– ohne Nummer –</option>';
            }
            function zustandSelect(wert, gesperrt) {
                var h = '<select class="form-select form-select-sm" name="zustand"' + (gesperrt ? ' disabled title="Zustand kommt vom Stück"' : '') + '>';
                Object.keys(ZUSTAENDE).forEach(function(k) { h += '<option value="' + k + '"' + (k === wert ? ' selected' : '') + '>' + ZUSTAENDE[k] + '</option>'; });
                return h + '</select>';
            }
            // Bei Einzelstücken: Zustand des gewählten Stücks anzeigen
            function zustandVomStueck(tr) {
                var sel = tr.querySelector('[name=stueck_id]');
                if (!sel) return;
                var st = stueckInfo[sel.value];
                var z = tr.querySelector('[name=zustand]');
                if (st) { z.value = st.zustand; z.disabled = true; z.title = 'Zustand kommt vom Stück'; }
                else { z.disabled = false; z.title = ''; }
            }

            // Zeile, die das Kleidungsstück schon hat (nur Anzeige)
            function zeileHat(tr, m) {
                tr.dataset.status = 'hat';
                tr.className = 'ul-hat';
                var nr = m.nummer ? '<span class="font-monospace">' + escHtml(kuerzel + m.nummer) + '</span>' + (m.groesse ? ' · ' + escHtml(m.groesse) : '') : escHtml(m.groesse || '–');
                if (!m.nummer && m.anzahl > 1) nr += ' <span class="badge bg-primary">' + m.anzahl + '×</span>';
                tr.innerHTML = tr.innerHTML.split('<!--rest-->')[0] + '<!--rest-->'
                  + '<td>' + nr + '</td>'
                  + '<td><span class="badge bg-' + (ZFARBE[m.zustand] || 'secondary') + '">' + escHtml(ZUSTAENDE[m.zustand] || m.zustand || '–') + '</span></td>'
                  + '<td>' + datumDe(m.ausgabe_datum) + '</td>'
                  + '<td colspan="2"><span class="text-success"><i class="bi bi-check-circle-fill"></i> ausgegeben</span>'
                  + ' <a class="small ms-2" href="uniform_mitglied.php?id=' + m.id + '" target="_blank">zum Mitglied <i class="bi bi-box-arrow-up-right"></i></a></td>';
            }

            mitglieder.forEach(function(m) {
                var tr = document.createElement('tr');
                tr.dataset.mitgliedId = m.id;
                var kopf = '<td class="text-muted small ul-nr"></td>'
                  + '<td><strong>' + escHtml(m.nachname) + '</strong></td>'
                  + '<td>' + escHtml(m.vorname) + (m.nicht_benoetigt == 1 ? ' <span class="badge text-bg-light border ms-1">nicht benötigt</span>' : '') + '</td><!--rest-->';

                if (m.zid && m.nicht_benoetigt != 1) {
                    tr.innerHTML = kopf;
                    zeileHat(tr, m);
                    tbody.appendChild(tr);
                    return;
                }

                var groesseHtml;
                if (einzeln) {
                    groesseHtml = '<select class="form-select form-select-sm" name="stueck_id">' + stueckOptionen() + '</select>';
                } else if (groessen.length > 0) {
                    groesseHtml = '<select class="form-select form-select-sm" name="groesse"><option value="">—</option>';
                    groessen.forEach(function(z) {
                        groesseHtml += '<option value="' + escHtml(z.g) + '">' + escHtml(z.g) + ' (' + z.lager + ')</option>';
                    });
                    groesseHtml += '</select>';
                } else {
                    groesseHtml = '<input type="text" class="form-control form-control-sm" name="groesse" placeholder="z.B. M">';
                }

                tr.dataset.status = m.nicht_benoetigt == 1 ? 'nb' : 'ohne';
                if (m.nicht_benoetigt == 1) tr.className = 'ul-nb';
                if (!einzeln) groesseHtml = '<div class="d-flex gap-1">' + groesseHtml
                    + '<input type="number" class="form-control form-control-sm" name="anzahl" min="1" max="99" value="1" style="width:64px" title="Anzahl"></div>';
                tr.innerHTML = kopf
                  + '<td style="min-width:' + 190 + 'px">' + groesseHtml + '</td>'
                  + '<td style="min-width:130px">' + zustandSelect('gut', false) + '</td>'
                  + '<td style="min-width:140px"><input type="date" class="form-control form-control-sm" name="ausgabe_datum" value="' + today + '"></td>'
                  + '<td><button type="button" class="btn btn-sm btn-success btn-zuweisen"><i class="bi bi-check-lg"></i> Zuweisen</button></td>'
                  + '<td>' + (m.nicht_benoetigt == 1 ? '' : '<button type="button" class="btn btn-sm btn-outline-secondary btn-nicht-benoetigt" title="Dieses Kleidungsstück wird von dieser Person nicht benötigt"><i class="bi bi-slash-circle"></i> Nicht benötigt</button>') + '</td>';
                tbody.appendChild(tr);
                if (einzeln) {
                    zustandVomStueck(tr);
                    tr.querySelector('[name=stueck_id]').addEventListener('change', function() { zustandVomStueck(tr); });
                }
            });

            // Nach einer Ausgabe: Lagerstand in allen offenen Zeilen nachführen
            function lagerNachfuehren(groesse, stueckId, anzahl) {
                if (einzeln) {
                    stuecke = stuecke.filter(function(st) { return String(st.id) !== String(stueckId); });
                    tbody.querySelectorAll('select[name=stueck_id]').forEach(function(sel) {
                        var opt = sel.querySelector('option[value="' + stueckId + '"]');
                        if (opt && stueckId !== '') { opt.remove(); zustandVomStueck(sel.closest('tr')); }
                    });
                } else if (groesse !== '' && lager[groesse] !== undefined) {
                    lager[groesse] -= (anzahl || 1);
                    tbody.querySelectorAll('select[name=groesse] option').forEach(function(o) {
                        if (o.value === groesse) o.textContent = groesse + ' (' + lager[groesse] + ')';
                    });
                }
            }

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

                    fetch('uniform_kleidungsstuecke.php?ajax=nicht_benoetigt', { method: 'POST', credentials: 'same-origin', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            tr.dataset.status = 'nb';
                            tr.className = 'ul-nb';
                            tr.querySelector('td:nth-child(3)').insertAdjacentHTML('beforeend', ' <span class="badge text-bg-light border ms-1">nicht benötigt</span>');
                            btn.remove();
                            filtern();
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
                    var gEl        = tr.querySelector('[name=groesse]');
                    var sEl        = tr.querySelector('[name=stueck_id]');
                    var groesse    = gEl ? gEl.value : '';
                    var stueckId   = sEl ? sEl.value : '';
                    var aEl        = tr.querySelector('[name=anzahl]');
                    var anzahl     = aEl ? (parseInt(aEl.value, 10) || 1) : 1;
                    if (!einzeln && gEl && gEl.tagName === 'SELECT' && groesse !== '' && lager[groesse] < anzahl
                        && !confirm('In Größe ' + groesse + ' sind laut Bestand nur ' + lager[groesse] + ' im Lager. Trotzdem ausgeben?')) return;
                    var zustand    = tr.querySelector('[name=zustand]').value;
                    var datum      = tr.querySelector('[name=ausgabe_datum]').value;
                    var st         = stueckInfo[stueckId];

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                    var formData = new FormData();
                    formData.append('mitglied_id',        mitgliedId);
                    formData.append('kleidungsstueck_id', aktuelleKsId);
                    formData.append('groesse',            groesse);
                    formData.append('stueck_id',          stueckId);
                    formData.append('anzahl',             anzahl);
                    formData.append('zustand',            zustand);
                    formData.append('ausgabe_datum',      datum);

                    fetch('uniform_kleidungsstuecke.php?ajax=zuweisen', { method: 'POST', credentials: 'same-origin', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            lagerNachfuehren(groesse, stueckId, anzahl);
                            zeileHat(tr, { id: mitgliedId, nummer: st ? st.nummer : null, groesse: st ? st.groesse : groesse, anzahl: anzahl,
                                           zustand: st ? st.zustand : zustand, ausgabe_datum: datum });
                            tr.querySelectorAll('.badge.text-bg-light').forEach(function(b) { b.remove(); });
                            filtern();
                            // in „Nur ohne“ bleibt die Zeile kurz sichtbar, damit man sieht, was passiert ist
                            if (!document.getElementById('ul_alle').checked) { tr.style.display = ''; }
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

            filtern();
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
        document.getElementById('edit_ks_kuerzel').value = this.dataset.kuerzel || '';
        document.getElementById('edit_ks_beschreibung').value = this.dataset.beschreibung;
        document.getElementById('edit_ks_groessen').value = this.dataset.groessen;
        var artSel = document.getElementById('edit_ks_bestandsart');
        artSel.value = this.dataset.bestandsart || 'menge';
        // gesperrt: Auswahl sperren und den Wert über ein verstecktes Feld mitschicken
        var gesperrt = this.dataset.wechselbar !== '1';
        artSel.disabled = gesperrt;
        var hidden = document.getElementById('edit_ks_bestandsart_hidden');
        if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.id = 'edit_ks_bestandsart_hidden'; artSel.parentNode.appendChild(hidden); }
        hidden.name = gesperrt ? 'bestandsart' : '';
        hidden.value = artSel.value;
        document.getElementById('edit_ks_art_gesperrt').classList.toggle('d-none', !gesperrt);
        document.getElementById('edit_ks_sortierung').value = this.dataset.sortierung;
        new bootstrap.Modal(document.getElementById('editKleidungModal')).show();
    });
});

}); // end window.addEventListener('load')
</script>

<?php include 'includes/footer.php'; ?>