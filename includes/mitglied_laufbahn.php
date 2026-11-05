<?php
// includes/mitglied_laufbahn.php
// Karte „Laufbahn“ in der Mitglied-Detailansicht: Instrumente, Funktionen, Ehrungen, Prüfungen, Mitgliedsart.
// Einträge aus dem BMV sind gesperrt, eigene können angelegt, geändert und gelöscht werden (api/mitglied_laufbahn.php).
// Erwartet: $mitglied, $instrumentTypen, $alleFormationen

$laufbahn     = BmvZusatzdaten::fuerMitglied((int)$mitglied['id']);
$ehrungInfo   = Ehrungen::fuerMitglied((int)$mitglied['id'], $laufbahn);
$darfSchreiben = Session::checkPermission('mitglieder', 'schreiben');
$heute        = date('Y-m-d');
$datum        = fn($d) => $d ? date('d.m.Y', strtotime($d)) : '–';
$beendet      = fn($z) => !empty($z['bis']) && $z['bis'] < $heute;

$tabs = [
    'instrument'   => ['label' => 'Instrumente',  'icon' => 'bi-music-note-beamed'],
    'funktion'     => ['label' => 'Funktionen',   'icon' => 'bi-person-badge'],
    'ehrung'       => ['label' => 'Ehrungen',     'icon' => 'bi-award'],
    'pruefung'     => ['label' => 'Prüfungen',    'icon' => 'bi-mortarboard'],
    'mitgliedsart' => ['label' => 'Mitgliedsart', 'icon' => 'bi-person-vcard'],
];

/** Herkunft eines Eintrags als kleines Abzeichen */
$herkunft = function (array $z): string {
    if (!empty($z['bmv_eintrag_id'])) {
        return '<span class="badge bg-primary-subtle text-primary-emphasis" data-bs-toggle="tooltip"'
            . ' title="Aus dem BMV. Änderungen bitte im BMV-Portal machen, Syncopa übernimmt sie beim nächsten Holen.">'
            . '<i class="bi bi-cloud-check"></i> BMV</span>';
    }
    return '<span class="badge bg-warning-subtle text-warning-emphasis" data-bs-toggle="tooltip"'
        . ' title="Nur in Syncopa eingetragen. Wird an den BMV übertragen, sobald das möglich ist.">'
        . '<i class="bi bi-hdd"></i> Syncopa</span>';
};

/** Aktions-Knöpfe einer Zeile */
$aktionen = function (string $art, array $z, array $formular) use ($darfSchreiben): string {
    if (!$darfSchreiben) return '';
    $json = htmlspecialchars(json_encode($formular, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
    $gesperrt = !BmvZusatzdaten::bearbeitbar($z);
    $html = '';
    if (!$gesperrt || $art === 'instrument') {
        $titel = $gesperrt ? 'Hauptinstrument / Formation ändern' : 'Bearbeiten';
        $html .= '<button type="button" class="btn btn-sm btn-link p-0 me-2 laufbahn-bearbeiten" data-art="' . $art
            . '" data-eintrag="' . $json . '" title="' . $titel . '"><i class="bi bi-pencil"></i></button>';
    }
    if ($gesperrt) {
        $html .= '<i class="bi bi-lock text-muted" data-bs-toggle="tooltip" title="Aus dem BMV – kann nur dort geändert werden"></i>';
    } else {
        $html .= '<button type="button" class="btn btn-sm btn-link p-0 text-danger laufbahn-loeschen" data-art="' . $art
            . '" data-id="' . (int)$z['id'] . '" title="Löschen"><i class="bi bi-trash"></i></button>';
    }
    return '<td class="text-end text-nowrap">' . $html . '</td>';
};

$leer = function (string $text) {
    echo '<p class="text-muted mb-0"><i class="bi bi-inbox"></i> ' . $text . '</p>';
};
?>

<div class="card mb-3" id="laufbahn">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <?php foreach ($tabs as $art => $t):
                $anzahl = count($laufbahn[$art]); ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#laufbahn-<?php echo $art; ?>" data-art="<?php echo $art; ?>" type="button" role="tab">
                    <i class="bi <?php echo $t['icon']; ?> d-none d-md-inline"></i> <?php echo $t['label']; ?>
                    <?php if ($anzahl): ?><span class="badge bg-secondary ms-1"><?php echo $anzahl; ?></span><?php endif; ?>
                    <?php if ($art === 'ehrung' && array_filter($ehrungInfo['anstehend'], fn($a) => $a['faellig'])): ?><span class="badge bg-danger ms-1" title="Ehrung fällig">!</span><?php endif; ?>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="card-body tab-content">

        <!-- Instrumente -->
        <div class="tab-pane fade" id="laufbahn-instrument" role="tabpanel">
            <?php if ($darfSchreiben): ?>
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-primary laufbahn-neu" data-art="instrument"><i class="bi bi-plus-lg"></i> Instrument hinzufügen</button>
            </div>
            <?php endif; ?>
            <?php if (!$laufbahn['instrument']): $leer('Noch keine Instrumente.'); else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Instrument</th><th>Register</th><th>Stimme</th><th>Formation</th><th>Seit</th><th>Bis</th><th>Herkunft</th><?php echo $darfSchreiben ? '<th></th>' : ''; ?></tr></thead>
                <tbody>
                <?php foreach ($laufbahn['instrument'] as $z): ?>
                <tr class="<?php echo $beendet($z) ? 'text-muted opacity-75' : ''; ?>">
                    <td>
                        <strong><?php echo htmlspecialchars($z['instrument_name']); ?></strong>
                        <?php if ($z['hauptinstrument']): ?><i class="bi bi-star-fill text-warning ms-1" title="Hauptinstrument"></i><?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($z['register_name'] ?? '–'); ?></td>
                    <td><?php echo htmlspecialchars($z['stimme'] ?? ''); ?></td>
                    <td>
                        <?php if (!empty($z['formation_name'])): ?>
                        <span class="badge" style="background-color:<?php echo htmlspecialchars($z['formation_farbe']); ?>;color:#fff"><?php echo htmlspecialchars($z['formation_kuerzel'] ?: $z['formation_name']); ?></span>
                        <?php else: ?><span class="text-muted small">Alle</span><?php endif; ?>
                    </td>
                    <td><?php echo $datum($z['von']); ?></td>
                    <td><?php echo $z['bis'] ? $datum($z['bis']) : '<span class="badge bg-success">aktuell</span>'; ?></td>
                    <td><?php echo $herkunft($z); ?></td>
                    <?php echo $aktionen('instrument', $z, [
                        'id' => (int)$z['id'], 'instrument_typ_id' => (int)$z['instrument_typ_id'], 'stimme' => $z['stimme'],
                        'seit_datum' => $z['seit_datum'], 'bis_datum' => $z['bis_datum'], 'hauptinstrument' => (int)$z['hauptinstrument'],
                        'formation_id' => $z['formation_id'], 'gesperrt' => !BmvZusatzdaten::bearbeitbar($z),
                    ]); ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>

        <!-- Funktionen -->
        <div class="tab-pane fade" id="laufbahn-funktion" role="tabpanel">
            <?php if ($darfSchreiben): ?>
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-primary laufbahn-neu" data-art="funktion"><i class="bi bi-plus-lg"></i> Funktion hinzufügen</button>
            </div>
            <?php endif; ?>
            <?php if (!$laufbahn['funktion']): $leer('Keine Funktionen.'); else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Funktion</th><th>Bereich</th><th>Von</th><th>Bis</th><th>Bemerkung</th><th>Herkunft</th><?php echo $darfSchreiben ? '<th></th>' : ''; ?></tr></thead>
                <tbody>
                <?php foreach ($laufbahn['funktion'] as $z): ?>
                <tr class="<?php echo $beendet($z) ? 'text-muted opacity-75' : ''; ?>">
                    <td><strong><?php echo htmlspecialchars(BmvZusatzdaten::name('funktion', $z['code'])); ?></strong></td>
                    <td><?php echo $z['bereich_code'] !== null ? htmlspecialchars(BmvZusatzdaten::name('bereich', $z['bereich_code'])) : '–'; ?></td>
                    <td><?php echo $datum($z['von']); ?></td>
                    <td><?php echo $z['bis'] ? $datum($z['bis']) : '<span class="badge bg-success">aktuell</span>'; ?></td>
                    <td class="small"><?php echo htmlspecialchars($z['bemerkung'] ?? ''); ?></td>
                    <td><?php echo $herkunft($z); ?></td>
                    <?php echo $aktionen('funktion', $z, ['id' => (int)$z['id'], 'code' => $z['code'], 'bereich_code' => $z['bereich_code'],
                        'von' => $z['von'], 'bis' => $z['bis'], 'bemerkung' => $z['bemerkung']]); ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>

        <!-- Ehrungen -->
        <div class="tab-pane fade" id="laufbahn-ehrung" role="tabpanel">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div>
                    <strong><?php echo number_format($ehrungInfo['jahre'], 1, ',', ''); ?></strong> Mitgliedsjahre
                    <?php if ($ehrungInfo['laufend']): ?><span class="text-muted small">(laufend)</span><?php endif; ?>
                </div>
                <?php if ($darfSchreiben): ?>
                <button type="button" class="btn btn-sm btn-primary laufbahn-neu" data-art="ehrung"><i class="bi bi-plus-lg"></i> Ehrung hinzufügen</button>
                <?php endif; ?>
            </div>
            <?php if ($ehrungInfo['anstehend']): ?>
            <div class="alert alert-permanent alert-warning py-2 small mb-3">
                <?php foreach ($ehrungInfo['anstehend'] as $a): ?>
                <div>
                    <i class="bi bi-award"></i> <strong><?php echo htmlspecialchars($a['name']); ?></strong>
                    <?php echo $a['faellig'] ? '– <span class="badge bg-danger">fällig seit ' . $a['datum']->format('d.m.Y') . '</span>'
                        : '– erreicht am ' . $a['datum']->format('d.m.Y'); ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!$ehrungInfo['erhalten']): $leer('Keine Ehrungen.'); else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Datum</th><th>Ehrung</th><th>Bemerkung</th><th>Herkunft</th><?php echo $darfSchreiben ? '<th></th>' : ''; ?></tr></thead>
                <tbody>
                <?php foreach ($ehrungInfo['erhalten'] as $z): ?>
                <tr>
                    <td class="text-nowrap"><?php echo $datum($z['von']); ?></td>
                    <td><strong><?php echo htmlspecialchars(BmvZusatzdaten::name('ehrung', $z['code'])); ?></strong></td>
                    <td class="small"><?php echo htmlspecialchars($z['bemerkung'] ?? ''); ?></td>
                    <td><?php echo $herkunft($z); ?></td>
                    <?php echo $aktionen('ehrung', $z, ['id' => (int)$z['id'], 'code' => $z['code'], 'von' => $z['von'], 'bemerkung' => $z['bemerkung']]); ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>

        <!-- Prüfungen -->
        <div class="tab-pane fade" id="laufbahn-pruefung" role="tabpanel">
            <?php if ($darfSchreiben): ?>
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-primary laufbahn-neu" data-art="pruefung"><i class="bi bi-plus-lg"></i> Prüfung hinzufügen</button>
            </div>
            <?php endif; ?>
            <?php if (!$laufbahn['pruefung']): $leer('Keine Prüfungen.'); else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Datum</th><th>Prüfung</th><th>Instrument</th><th>Erfolg</th><th>Ort / Bemerkung</th><th>Herkunft</th><?php echo $darfSchreiben ? '<th></th>' : ''; ?></tr></thead>
                <tbody>
                <?php foreach ($laufbahn['pruefung'] as $z): ?>
                <tr>
                    <td class="text-nowrap"><?php echo $datum($z['von']); ?></td>
                    <td><strong><?php echo htmlspecialchars(BmvZusatzdaten::name('pruefung', $z['code'])); ?></strong></td>
                    <td><?php echo $z['instrument_code'] ? htmlspecialchars(BmvZusatzdaten::name('instrument', $z['instrument_code'])) : '–'; ?></td>
                    <td <?php echo !empty($z['ergebnistext']) ? 'data-bs-toggle="tooltip" title="' . htmlspecialchars($z['ergebnistext']) . '"' : ''; ?>>
                        <?php echo htmlspecialchars($z['ergebnis'] ?? ''); ?>
                        <?php if (!empty($z['ergebnistext'])): ?><i class="bi bi-info-circle text-muted"></i><?php endif; ?>
                    </td>
                    <td class="small"><?php echo htmlspecialchars($z['bemerkung'] ?? ''); ?></td>
                    <td><?php echo $herkunft($z); ?></td>
                    <?php echo $aktionen('pruefung', $z, ['id' => (int)$z['id'], 'code' => $z['code'], 'von' => $z['von'],
                        'instrument_code' => $z['instrument_code'], 'ergebnis' => $z['ergebnis'], 'bemerkung' => $z['bemerkung']]); ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>

        <!-- Mitgliedsart -->
        <div class="tab-pane fade" id="laufbahn-mitgliedsart" role="tabpanel">
            <?php if ($darfSchreiben): ?>
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-sm btn-primary laufbahn-neu" data-art="mitgliedsart"><i class="bi bi-plus-lg"></i> Mitgliedsart hinzufügen</button>
            </div>
            <?php endif; ?>
            <?php if (!$laufbahn['mitgliedsart']): $leer('Keine Mitgliedsarten.'); else: ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th>Mitgliedsart</th><th>Von</th><th>Bis</th><th>Bemerkung</th><th>Herkunft</th><?php echo $darfSchreiben ? '<th></th>' : ''; ?></tr></thead>
                <tbody>
                <?php foreach ($laufbahn['mitgliedsart'] as $z): ?>
                <tr class="<?php echo $beendet($z) ? 'text-muted opacity-75' : ''; ?>">
                    <td><strong><?php echo htmlspecialchars(BmvZusatzdaten::name('mitgliedsart', $z['code'])); ?></strong></td>
                    <td><?php echo $datum($z['von']); ?></td>
                    <td><?php echo $z['bis'] ? $datum($z['bis']) : '<span class="badge bg-success">aktuell</span>'; ?></td>
                    <td class="small"><?php echo htmlspecialchars($z['bemerkung'] ?? ''); ?></td>
                    <td><?php echo $herkunft($z); ?></td>
                    <?php echo $aktionen('mitgliedsart', $z, ['id' => (int)$z['id'], 'code' => $z['code'], 'von' => $z['von'],
                        'bis' => $z['bis'], 'bemerkung' => $z['bemerkung']]); ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-footer small text-muted d-flex flex-wrap gap-3">
        <span><span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-cloud-check"></i> BMV</span> aus dem BMV, wird beim Holen aktualisiert</span>
        <span><span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-hdd"></i> Syncopa</span> nur hier eingetragen, wird später an den BMV übertragen</span>
    </div>
</div>

<?php if ($darfSchreiben):
    $optionen = function (array $liste) {
        foreach ($liste as $wert => $text) {
            echo '<option value="' . htmlspecialchars((string)$wert) . '">' . htmlspecialchars($text) . '</option>';
        }
    };
    $hinweisBezeichnungen = function (string $art) {
        if (!BmvZusatzdaten::auswahl($art) && Session::isAdmin()) {
            echo '<div class="form-text text-warning">Noch keine Auswahl vorhanden. Namen kommen aus dem BMV bzw. unter '
                . '<a href="bmv_daten.php?bereich=bezeichnungen">BMV Daten holen → Bezeichnungen</a>.</div>';
        }
    };
?>
<!-- Dialog für eigene Einträge (ein Formular, Felder je nach Art) -->
<div class="modal fade" id="laufbahnModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="laufbahnForm">
            <div class="modal-header">
                <h5 class="modal-title" id="laufbahnTitel">Eintrag</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="aktion" value="speichern">
                <input type="hidden" name="art">
                <input type="hidden" name="id">
                <input type="hidden" name="mitglied_id" value="<?php echo (int)$mitglied['id']; ?>">

                <div class="alert alert-permanent alert-info small py-2 d-none" id="laufbahnGesperrt">
                    <i class="bi bi-lock"></i> Dieses Instrument kommt aus dem BMV. Hier lassen sich nur Hauptinstrument und Formation ändern.
                </div>

                <!-- Instrument -->
                <div data-fuer="instrument">
                    <div class="mb-3">
                        <label class="form-label">Instrument *</label>
                        <select class="form-select" name="instrument_typ_id" data-bmv-feld>
                            <option value="">Bitte wählen …</option>
                            <?php
                            $gruppe = null;
                            foreach ($instrumentTypen as $typ):
                                if ($typ['register_name'] !== $gruppe):
                                    if ($gruppe !== null) echo '</optgroup>';
                                    $gruppe = $typ['register_name'];
                                    echo '<optgroup label="' . htmlspecialchars($gruppe ?? 'Ohne Register') . '">';
                                endif; ?>
                            <option value="<?php echo (int)$typ['id']; ?>"><?php echo htmlspecialchars($typ['name']); ?></option>
                            <?php endforeach; if ($gruppe !== null) echo '</optgroup>'; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Seit</label><input type="date" class="form-control" name="seit_datum" data-bmv-feld></div>
                        <div class="col-6 mb-3"><label class="form-label">Bis</label><input type="date" class="form-control" name="bis_datum" data-bmv-feld><div class="form-text">Leer = spielt es noch</div></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Stimme</label><input type="text" class="form-control" name="stimme" maxlength="50" placeholder="z. B. 1. Stimme" data-bmv-feld></div>
                    <?php if (!empty($alleFormationen)): ?>
                    <div class="mb-3">
                        <label class="form-label">Formation</label>
                        <select class="form-select" name="formation_id">
                            <option value="">Alle Formationen</option>
                            <?php foreach ($alleFormationen as $f): ?>
                            <option value="<?php echo (int)$f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="hauptinstrument" value="1" id="lbHaupt">
                        <label class="form-check-label" for="lbHaupt">Hauptinstrument</label>
                    </div>
                </div>

                <!-- Funktion -->
                <div data-fuer="funktion">
                    <div class="mb-3">
                        <label class="form-label">Funktion *</label>
                        <select class="form-select" name="code"><option value="">Bitte wählen …</option><?php $optionen(BmvZusatzdaten::auswahl('funktion')); ?></select>
                        <?php $hinweisBezeichnungen('funktion'); ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bereich</label>
                        <select class="form-select" name="bereich_code"><option value="">–</option><?php $optionen(BmvZusatzdaten::auswahl('bereich')); ?></select>
                    </div>
                </div>

                <!-- Ehrung -->
                <div data-fuer="ehrung">
                    <div class="mb-3">
                        <label class="form-label">Ehrung *</label>
                        <select class="form-select" name="code"><option value="">Bitte wählen …</option><?php $optionen(BmvZusatzdaten::auswahl('ehrung')); ?></select>
                        <?php $hinweisBezeichnungen('ehrung'); ?>
                    </div>
                </div>

                <!-- Prüfung -->
                <div data-fuer="pruefung">
                    <div class="mb-3">
                        <label class="form-label">Prüfung *</label>
                        <select class="form-select" name="code"><option value="">Bitte wählen …</option><?php $optionen(BmvZusatzdaten::auswahl('pruefung')); ?></select>
                        <?php $hinweisBezeichnungen('pruefung'); ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Instrument</label>
                        <select class="form-select" name="instrument_code"><option value="">–</option><?php $optionen(BmvZusatzdaten::auswahl('instrument')); ?></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Erfolg</label>
                        <input type="text" class="form-control" name="ergebnis" list="lbErgebnisse" maxlength="255" placeholder="z. B. Ausgezeichneter Erfolg">
                        <datalist id="lbErgebnisse"><?php foreach (BmvZusatzdaten::ERGEBNISSE as $e): ?><option value="<?php echo htmlspecialchars($e); ?>"><?php endforeach; ?></datalist>
                    </div>
                </div>

                <!-- Mitgliedsart -->
                <div data-fuer="mitgliedsart">
                    <div class="mb-3">
                        <label class="form-label">Mitgliedsart *</label>
                        <select class="form-select" name="code"><option value="">Bitte wählen …</option><?php $optionen(BmvZusatzdaten::auswahl('mitgliedsart')); ?></select>
                        <?php $hinweisBezeichnungen('mitgliedsart'); ?>
                    </div>
                </div>

                <!-- Gemeinsame Felder -->
                <div class="row" data-fuer="funktion mitgliedsart">
                    <div class="col-6 mb-3"><label class="form-label" data-label-von>Von</label><input type="date" class="form-control" name="von"></div>
                    <div class="col-6 mb-3"><label class="form-label">Bis</label><input type="date" class="form-control" name="bis"><div class="form-text">Leer = läuft noch</div></div>
                </div>
                <div class="mb-3" data-fuer="ehrung pruefung">
                    <label class="form-label">Datum *</label><input type="date" class="form-control" name="von">
                </div>
                <div class="mb-0" data-fuer="funktion ehrung pruefung mitgliedsart">
                    <label class="form-label" data-label-bemerkung>Bemerkung</label>
                    <input type="text" class="form-control" name="bemerkung" maxlength="255">
                </div>

                <div class="alert alert-permanent alert-danger small py-2 mt-3 mb-0 d-none" id="laufbahnFehler"></div>
            </div>
            <div class="modal-footer">
                <span class="small text-muted me-auto" id="laufbahnHinweisNeu"><i class="bi bi-hdd"></i> Wird nur in Syncopa gespeichert.</span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Speichern</button>
            </div>
        </form>
    </div>
</div>

<script>
// Bootstrap wird erst im Footer geladen – daher erst nach dem Laden der Seite starten
document.addEventListener('DOMContentLoaded', function() {
    const karte = document.getElementById('laufbahn');
    const modalEl = document.getElementById('laufbahnModal');
    const form = document.getElementById('laufbahnForm');
    const fehler = document.getElementById('laufbahnFehler');
    const titel = { instrument: 'Instrument', funktion: 'Funktion', ehrung: 'Ehrung', pruefung: 'Prüfung', mitgliedsart: 'Mitgliedsart' };
    let modal;

    // Zuletzt gewählten Reiter merken (pro Browser), sonst „Instrumente“
    const tabKey = 'syncopa_laufbahn_tab';
    let start = 'instrument';
    try { start = sessionStorage.getItem(tabKey) || start; } catch (e) {}
    const startBtn = karte.querySelector('[data-bs-target="#laufbahn-' + start + '"]') || karte.querySelector('[data-bs-toggle="tab"]');
    bootstrap.Tab.getOrCreateInstance(startBtn).show();
    karte.querySelectorAll('[data-bs-toggle="tab"]').forEach(b => b.addEventListener('shown.bs.tab', () => {
        try { sessionStorage.setItem(tabKey, b.dataset.art); } catch (e) {}
    }));

    function oeffnen(art, eintrag) {
        modal = modal || new bootstrap.Modal(modalEl);
        form.reset();
        fehler.classList.add('d-none');
        form.elements.namedItem('art').value = art;
        form.elements.namedItem('id').value = eintrag ? eintrag.id : '';
        document.getElementById('laufbahnTitel').textContent = titel[art] + (eintrag ? ' bearbeiten' : ' hinzufügen');

        // Nur die Felder dieser Art anzeigen und mitsenden
        modalEl.querySelectorAll('[data-fuer]').forEach(block => {
            const aktiv = block.dataset.fuer.split(' ').includes(art);
            block.classList.toggle('d-none', !aktiv);
            block.querySelectorAll('input, select').forEach(f => f.disabled = !aktiv);
        });
        const vonLabel = modalEl.querySelector('[data-label-von]');
        if (vonLabel) vonLabel.textContent = art === 'mitgliedsart' ? 'Von *' : 'Von';
        const bemLabel = modalEl.querySelector('[data-label-bemerkung]');
        if (bemLabel) bemLabel.textContent = art === 'pruefung' ? 'Ort / Bemerkung' : 'Bemerkung';

        // Werte eintragen
        if (eintrag) {
            Object.keys(eintrag).forEach(name => {
                modalEl.querySelectorAll('[name="' + name + '"]:not([disabled])').forEach(f => {
                    if (f.type === 'checkbox') f.checked = !!Number(eintrag[name]);
                    else f.value = eintrag[name] ?? '';
                });
            });
        }
        // BMV-Instrument: BMV-Felder sperren
        const gesperrt = !!(eintrag && eintrag.gesperrt);
        document.getElementById('laufbahnGesperrt')?.classList.toggle('d-none', !gesperrt);
        modalEl.querySelectorAll('[data-bmv-feld]').forEach(f => { if (art === 'instrument') f.disabled = gesperrt; });
        document.getElementById('laufbahnHinweisNeu')?.classList.toggle('d-none', gesperrt);
        modal.show();
    }

    karte.querySelectorAll('.laufbahn-neu').forEach(b => b.addEventListener('click', () => oeffnen(b.dataset.art, null)));
    karte.querySelectorAll('.laufbahn-bearbeiten').forEach(b => b.addEventListener('click', () => oeffnen(b.dataset.art, JSON.parse(b.dataset.eintrag))));

    function senden(daten) {
        return fetch('api/mitglied_laufbahn.php', { method: 'POST', body: daten })
            .then(r => r.json())
            .catch(() => ({ success: false, error: 'Keine Verbindung zum Server.' }));
    }

    form.addEventListener('submit', e => {
        e.preventDefault();
        const knopf = form.querySelector('[type="submit"]');
        knopf.disabled = true;
        senden(new FormData(form)).then(d => {
            if (d.success) { location.reload(); return; }
            fehler.textContent = d.error || 'Speichern fehlgeschlagen.';
            fehler.classList.remove('d-none');
            knopf.disabled = false;
        });
    });

    karte.querySelectorAll('.laufbahn-loeschen').forEach(b => b.addEventListener('click', () => {
        if (!confirm(titel[b.dataset.art] + ' wirklich löschen?')) return;
        const daten = new FormData();
        daten.append('aktion', 'loeschen');
        daten.append('art', b.dataset.art);
        daten.append('id', b.dataset.id);
        daten.append('mitglied_id', '<?php echo (int)$mitglied['id']; ?>');
        senden(daten).then(d => d.success ? location.reload() : alert(d.error || 'Löschen fehlgeschlagen.'));
    }));
});
</script>
<?php else: ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.querySelector('#laufbahn [data-bs-toggle="tab"]');
    if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
});
</script>
<?php endif; ?>
