<?php
// mitglied_detail.php
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('mitglieder', 'lesen');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: mitglieder.php');
    exit;
}

$mitgliedObj = new Mitglied();
$mitglied = $mitgliedObj->getById($id);

if (!$mitglied) {
    Session::setFlashMessage('danger', 'Mitglied nicht gefunden');
    header('Location: mitglieder.php');
    exit;
}

// Verfügbare Instrumententypen laden
$db = Database::getInstance();
$instrumentTypen = $db->fetchAll("
    SELECT it.*, r.name as register_name
    FROM instrument_typen it
    LEFT JOIN register r ON it.register_id = r.id
    ORDER BY r.sortierung, it.name
");

// Formationen für Modal laden
$alleFormationen = [];
try {
    $alleFormationen = (new Formation())->getAll(true);
} catch (\Throwable $e) {}

// Ausgeliehene Instrumente des Mitglieds laden
$ausgelieheneInstrumente = $db->fetchAll("
    SELECT i.*, it.name as instrument_name, r.name as register_name
    FROM instrumente i
    JOIN instrument_typen it ON i.instrument_typ_id = it.id
    LEFT JOIN register r ON it.register_id = r.id
    WHERE i.mitglied_id = ?
", [$id]);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 d-flex align-items-center gap-3">
        <?php if (!empty($mitglied['foto']) || !empty($mitglied['bmv_foto'])): ?>
        <img src="api/mitglied_foto.php?id=<?php echo (int)$mitglied['id']; ?>&amp;v=<?php echo strtotime($mitglied['aktualisiert_am']); ?>" alt="Foto"
             class="rounded border" style="width:64px;height:64px;object-fit:cover">
        <?php else: ?>
        <i class="bi bi-person"></i>
        <?php endif; ?>
        <?php
        $anzeigeName = trim(($mitglied['bmv_titel'] ?? '') . ' ' . $mitglied['vorname'] . ' ' . $mitglied['nachname']);
        if (!empty($mitglied['bmv_titeln'])) {
            $anzeigeName .= ', ' . $mitglied['bmv_titeln'];
        }
        echo htmlspecialchars($anzeigeName);
        ?>
    </h1>
    <div>
        <a href="mitglieder.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Zurück
        </a>
        <?php if (Session::checkPermission('mitglieder', 'schreiben')): ?>
        <a href="mitglied_bearbeiten.php?id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Bearbeiten
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <!-- Stammdaten Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Stammdaten</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Mitgliedsnr.:</dt>
                    <dd class="col-sm-7"><strong><?php echo htmlspecialchars($mitglied['mitgliedsnummer']); ?></strong></dd>
                    
                    <dt class="col-sm-5">Mitgliedsart:</dt>
                    <dd class="col-sm-7">
                        <?php echo Mitgliedsart::badge($mitglied['mitgliedsart_code']); ?>
                        <?php if ($mitglied['mitgliedsart_code'] !== null && ($artSeit = Mitgliedsart::seit((int)$mitglied['id']))): ?>
                        <br><small class="text-muted">seit <?php echo date('d.m.Y', strtotime($artSeit)); ?></small>
                        <?php endif; ?>
                    </dd>
                    
                    <?php if ($mitglied['geburtsdatum']): ?>
                    <dt class="col-sm-5">Geburtsdatum:</dt>
                    <dd class="col-sm-7">
                        <?php echo date('d.m.Y', strtotime($mitglied['geburtsdatum'])); ?>
                        <?php
                        $alter = date_diff(date_create($mitglied['geburtsdatum']), date_create('today'))->y;
                        echo "<br><small class='text-muted'>{$alter} Jahre</small>";
                        ?>
                    </dd>
                    <?php endif; ?>
                    
                    <?php if ($mitglied['eintritt_datum']): ?>
                    <dt class="col-sm-5">Mitglied seit:</dt>
                    <dd class="col-sm-7">
                        <?php echo date('d.m.Y', strtotime($mitglied['eintritt_datum'])); ?>
                        <?php
                        $jahre = date_diff(date_create($mitglied['eintritt_datum']), date_create('today'))->y;
                        echo "<br><small class='text-muted'>{$jahre} Jahre</small>";
                        ?>
                    </dd>
                    <?php endif; ?>
                    
                    <?php if ($mitglied['register_name']): ?>
                    <dt class="col-sm-5">Register:</dt>
                    <dd class="col-sm-7"><span class="badge bg-info"><?php echo htmlspecialchars($mitglied['register_name']); ?></span></dd>
                    <?php endif; ?>

                    <?php foreach (['bmv_anrede' => 'Anrede', 'bmv_beruf' => 'Beruf', 'bmv_firma' => 'Firma'] as $feld => $label):
                        if (empty($mitglied[$feld])) continue; ?>
                    <dt class="col-sm-5"><?php echo $label; ?>:</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($mitglied[$feld]); ?></dd>
                    <?php endforeach; ?>

                    <?php // Kategorien kommen vom BMV als „;A;B;“
                    $kategorien = array_filter(array_map('trim', explode(';', (string)($mitglied['bmv_kategorien'] ?? ''))));
                    if ($kategorien): ?>
                    <dt class="col-sm-5">Kategorien:</dt>
                    <dd class="col-sm-7">
                        <?php foreach ($kategorien as $kategorie): ?>
                        <span class="badge bg-light text-dark border mb-1"><?php echo htmlspecialchars($kategorie); ?></span>
                        <?php endforeach; ?>
                    </dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        
        <!-- Kontakt Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Kontakt</h5>
            </div>
            <div class="card-body">
                <?php if ($mitglied['strasse'] || $mitglied['ort']): ?>
                <p class="mb-2">
                    <i class="bi bi-house"></i>
                    <?php echo htmlspecialchars($mitglied['strasse'] ?? ''); ?><br>
                    <?php echo htmlspecialchars(($mitglied['plz'] ?? '') . ' ' . ($mitglied['ort'] ?? '')); ?>
                    <?php if ($mitglied['land']): ?>
                    <br><?php echo htmlspecialchars($mitglied['land']); ?>
                    <?php endif; ?>
                </p>
                <hr>
                <?php endif; ?>
                
                <?php if ($mitglied['telefon']): ?>
                <p class="mb-2">
                    <i class="bi bi-telephone"></i>
                    <a href="tel:<?php echo htmlspecialchars($mitglied['telefon']); ?>">
                        <?php echo htmlspecialchars($mitglied['telefon']); ?>
                    </a>
                </p>
                <?php endif; ?>
                
                <?php if ($mitglied['mobil']): ?>
                <p class="mb-2">
                    <i class="bi bi-phone"></i>
                    <a href="tel:<?php echo htmlspecialchars($mitglied['mobil']); ?>">
                        <?php echo htmlspecialchars($mitglied['mobil']); ?>
                    </a>
                </p>
                <?php endif; ?>
                
                <?php foreach (['bmv_telefon2' => ['bi-telephone', 'Telefon 2'], 'bmv_telefon_firma' => ['bi-building', 'Telefon Firma']] as $feld => [$icon, $titel]):
                    if (empty($mitglied[$feld])) continue; ?>
                <p class="mb-2" title="<?php echo $titel; ?>">
                    <i class="bi <?php echo $icon; ?>"></i>
                    <a href="tel:<?php echo htmlspecialchars($mitglied[$feld]); ?>"><?php echo htmlspecialchars($mitglied[$feld]); ?></a>
                    <small class="text-muted">(<?php echo $titel; ?>)</small>
                </p>
                <?php endforeach; ?>

                <?php if (!empty($mitglied['bmv_fax'])): ?>
                <p class="mb-2">
                    <i class="bi bi-printer"></i> <?php echo htmlspecialchars($mitglied['bmv_fax']); ?> <small class="text-muted">(Fax)</small>
                </p>
                <?php endif; ?>

                <?php foreach (['email', 'bmv_email2'] as $feld):
                    if (empty($mitglied[$feld])) continue; ?>
                <p class="mb-2">
                    <i class="bi bi-envelope"></i>
                    <a href="mailto:<?php echo htmlspecialchars($mitglied[$feld]); ?>">
                        <?php echo htmlspecialchars($mitglied[$feld]); ?>
                    </a>
                </p>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if ($mitglied['notizen'] || !empty($mitglied['bmv_bemerkung'])): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Notizen</h5>
            </div>
            <div class="card-body">
                <?php if ($mitglied['notizen']): ?>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($mitglied['notizen'])); ?></p>
                <?php endif; ?>
                <?php if (!empty($mitglied['bmv_bemerkung'])): ?>
                <p class="mb-0 <?php echo $mitglied['notizen'] ? 'mt-2' : ''; ?>">
                    <small class="text-muted d-block">Bemerkung (BMV)</small>
                    <?php echo nl2br(htmlspecialchars($mitglied['bmv_bemerkung'])); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- BMV-Details (zugeklappt) -->
        <div class="card mb-3">
            <div class="card-header p-0">
                <button class="btn btn-link w-100 text-start text-decoration-none d-flex justify-content-between align-items-center px-3 py-2 collapsed"
                        type="button" data-bs-toggle="collapse" data-bs-target="#bmvDetails" aria-expanded="false" aria-controls="bmvDetails">
                    <span class="h5 mb-0"><i class="bi bi-cloud"></i> BMV-Details</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="collapse" id="bmvDetails">
                <div class="card-body small">
                    <h6>Datenschutz-Freigaben</h6>
                    <ul class="list-unstyled mb-3">
                        <?php foreach ([
                            'bmv_exp_telnr1' => 'Telefon 1 darf exportiert werden',
                            'bmv_exp_telnr2' => 'Telefon 2 darf exportiert werden',
                            'bmv_exp_email1' => 'E-Mail 1 darf exportiert werden',
                            'bmv_exp_email2' => 'E-Mail 2 darf exportiert werden',
                            'bmv_exp_adresse' => 'Adresse darf exportiert werden',
                            'bmv_exp_internet' => 'Im Internet veröffentlichen',
                        ] as $feld => $label): ?>
                        <li>
                            <?php if (!empty($mitglied[$feld])): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <?php else: ?>
                            <i class="bi bi-x-circle text-muted"></i>
                            <?php endif; ?>
                            <?php echo $label; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <h6>Vom BMV</h6>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">BMV-ID</dt>
                        <dd class="col-sm-7 text-break"><?php echo htmlspecialchars($mitglied['bmv_id'] ?? '–'); ?></dd>
                        <dt class="col-sm-5">BMV-Status</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars($mitglied['bmv_status'] ?? '–'); ?></dd>
                        <dt class="col-sm-5">Verein-ID</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars((string)($mitglied['bmv_verein_id'] ?? '–')); ?></dd>
                        <dt class="col-sm-5">Letzte Änderung</dt>
                        <dd class="col-sm-7"><?php echo !empty($mitglied['bmv_aenderung']) ? date('d.m.Y H:i', strtotime($mitglied['bmv_aenderung'])) : '–'; ?></dd>
                        <dt class="col-sm-5">Angelegt von</dt>
                        <dd class="col-sm-7 text-break"><?php echo htmlspecialchars($mitglied['bmv_pcanmeldename'] ?? '–'); ?></dd>
                        <dt class="col-sm-5">Zuletzt geholt</dt>
                        <dd class="col-sm-7"><?php echo !empty($mitglied['bmv_abgeglichen_am']) ? date('d.m.Y H:i', strtotime($mitglied['bmv_abgeglichen_am'])) : '–'; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <?php include 'includes/mitglied_laufbahn.php'; ?>

        <!-- Ausgeliehene Instrumente (aus Inventar) -->
        <?php if (!empty($ausgelieheneInstrumente)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-box"></i> Ausgeliehene Instrumente (Inventar)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Inventar-Nr.</th>
                                <th>Instrument</th>
                                <th>Hersteller/Modell</th>
                                <th>Ausgeliehen seit</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ausgelieheneInstrumente as $inv): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($inv['inventar_nummer']); ?></strong></td>
                                <td><?php echo htmlspecialchars($inv['instrument_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($inv['hersteller'] ?? '-'); ?>
                                    <?php if ($inv['modell']): ?>
                                    <br><small><?php echo htmlspecialchars($inv['modell']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($inv['ausgeliehen_seit'])); ?></td>
                                <td>
                                    <a href="instrument_detail.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
