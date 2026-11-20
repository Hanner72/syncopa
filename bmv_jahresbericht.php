<?php
// bmv_jahresbericht.php – Jahresbericht an den Blasmusikverband
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::checkPermission('bmv', 'lesen')) {
    Session::setFlashMessage('danger', 'Keine Berechtigung für BMV Online');
    header('Location: index.php');
    exit;
}
$darfSchreiben = Session::checkPermission('bmv', 'schreiben');
$darfOeffnen   = Session::checkPermission('bmv', 'loeschen');

$benutzerId = (int)Session::getUserId();
$jahr = (int)($_GET['jahr'] ?? $_POST['jahr'] ?? (date('Y') - 1));
if ($jahr < 2000 || $jahr > (int)date('Y')) $jahr = (int)date('Y') - 1;
$zurueck = 'bmv_jahresbericht.php?jahr=' . $jahr;

// PDF ansehen (Entwurf oder abgeschlossener Bericht)
if (isset($_GET['pdf'])) {
    $bericht = BmvJahresbericht::laden($jahr);
    if ($bericht && $bericht['abgeschlossen_am']) {
        $pfad = BmvJahresbericht::pdfPfad($jahr);
        $inhalt = is_file($pfad) ? file_get_contents($pfad) : BmvJahresbericht::pdf($jahr, $bericht['werte']);
    } else {
        $inhalt = BmvJahresbericht::pdf($jahr, BmvJahresbericht::zusammenstellen($jahr, $benutzerId)['werte'], true);
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . BmvJahresbericht::pdfName($jahr) . '"');
    echo $inhalt;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktion = $_POST['aktion'] ?? '';
    try {
        if (!$darfSchreiben || ($aktion === 'oeffnen' && !$darfOeffnen)) {
            throw new RuntimeException('Dafür fehlt die Berechtigung (BMV Online).');
        }
        switch ($aktion) {
            case 'speichern':
                BmvJahresbericht::eingabenSpeichern($jahr, $_POST['f'] ?? [], $benutzerId);
                Session::setFlashMessage('success', 'Eingaben gespeichert.');
                break;
            case 'abschliessen':
                BmvJahresbericht::eingabenSpeichern($jahr, $_POST['f'] ?? [], $benutzerId);
                BmvJahresbericht::abschliessen($jahr, $benutzerId);
                if (!empty($_POST['senden'])) {
                    try {
                        BmvJahresbericht::senden($jahr, MailKonto::laden((int)($_POST['konto_id'] ?? 0)), trim($_POST['an'] ?? ''), (string)($_POST['text'] ?? ''));
                        Session::setFlashMessage('success', "Jahresbericht $jahr abgeschlossen und an " . trim($_POST['an']) . ' gesendet.');
                    } catch (\Throwable $e) {
                        Session::setFlashMessage('danger', "Jahresbericht $jahr ist abgeschlossen, das Senden hat aber nicht geklappt: " . $e->getMessage());
                    }
                } else {
                    Session::setFlashMessage('success', "Jahresbericht $jahr abgeschlossen.");
                }
                break;
            case 'senden':
                BmvJahresbericht::senden($jahr, MailKonto::laden((int)($_POST['konto_id'] ?? 0)), trim($_POST['an'] ?? ''), (string)($_POST['text'] ?? ''));
                Session::setFlashMessage('success', "Jahresbericht $jahr an " . trim($_POST['an']) . ' gesendet.');
                break;
            case 'oeffnen':
                BmvJahresbericht::wiederOeffnen($jahr);
                Session::setFlashMessage('success', "Jahresbericht $jahr ist wieder zum Bearbeiten offen.");
                break;
        }
    } catch (\Throwable $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: ' . $zurueck);
    exit;
}

$z = BmvJahresbericht::zusammenstellen($jahr, $benutzerId);
$w = $z['werte'];
$quelle = $z['quelle'];
$bericht = $z['bericht'];
$zu = $bericht && $bericht['abgeschlossen_am'];
$sperre = $zu || !$darfSchreiben;   // Felder nur lesbar
$felder = BmvJahresbericht::felder();
$konten = MailKonto::meine();
$empfaenger = BmvJahresbericht::empfaenger();
$kennung = BmvJahresbericht::vereinskennung();
$verein = BmvJahresbericht::vereinsname();
$alle = array_column(BmvJahresbericht::alle(), null, 'jahr');
$fehlend = count($z['fehlend']);
$standardText = "Sehr geehrte Damen und Herren,\n\nim Anhang senden wir Ihnen den Jahresbericht $jahr des $verein ($kennung).\n\nMit freundlichen Grüßen\n" . ($w['bearbeiter_name'] ?? '');

/** Eingabefeld für einen Schlüssel */
function feld(string $k, array $w, array $quelle, array $auto, bool $zu, string $extra = ''): string {
    $q = $quelle[$k] ?? 'fehlt';
    $wert = $w[$k] ?? '';
    $titel = ['auto' => 'aus Syncopa', 'geaendert' => 'von Hand geändert (Syncopa: ' . ($auto[$k] ?? '–') . ')', 'hand' => 'von Hand eingetragen',
              'vorjahr' => 'aus dem Vorjahr übernommen', 'fehlt' => 'fehlt – bitte eintragen', 'abgeschlossen' => ''][$q];
    $a = isset($auto[$k]) && $auto[$k] !== null ? ' data-auto="' . htmlspecialchars((string)$auto[$k]) . '"' : '';
    return '<input type="number" step="1" class="form-control form-control-sm text-center jb-feld jb-' . $q . '" name="f[' . $k . ']" value="'
        . htmlspecialchars((string)$wert) . '" title="' . htmlspecialchars($titel) . '"' . $a . ($zu ? ' readonly' : '') . $extra . '>';
}
$feld = fn($k, $extra = '') => feld($k, $w, $quelle, $z['auto'], $sperre, $extra);
$summe = function (array $keys) use ($w) {
    $s = 0; foreach ($keys as $k) { if (($w[$k] ?? null) === null || $w[$k] === '') return ''; $s += (int)$w[$k]; } return $s;
};

include 'includes/header.php';
?>
<style>
    .jb-feld, select.jb-auto, select.jb-vorjahr, select.jb-hand, select.jb-geaendert, select.jb-fehlt { border-width: 2px !important; }
    .jb-radio { border: 2px solid transparent; border-radius: 6px; }
    .jb-fehlt { background-color: rgba(220, 53, 69, .12) !important; border-color: #dc3545 !important; }
    .jb-geaendert, .jb-hand { border-color: #e0a800 !important; background-color: rgba(255, 193, 7, .12) !important; }
    .jb-vorjahr { border-color: #0dcaf0 !important; background-color: rgba(13, 202, 240, .08) !important; }
    .jb-auto { border-color: rgba(25, 135, 84, .55) !important; }
    .jb-tab th, .jb-tab td { vertical-align: middle; }
    .jb-tab .form-control { min-width: 56px; }
    .jb-legende span { display: inline-block; width: 14px; height: 14px; border-radius: 3px; border: 2px solid; vertical-align: -2px; margin-right: 4px; }
    .jb-summe { font-weight: 600; text-align: center; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0"><i class="bi bi-file-earmark-text"></i> Jahresbericht <?php echo $jahr; ?></h1>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <form method="GET" class="d-flex gap-2">
            <select name="jahr" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php for ($j = (int)date('Y'); $j >= (int)date('Y') - 6; $j--): ?>
                <option value="<?php echo $j; ?>" <?php echo $j === $jahr ? 'selected' : ''; ?>><?php echo $j; ?><?php echo isset($alle[$j]) && $alle[$j]['abgeschlossen_am'] ? ' ✓' : ''; ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <a href="<?php echo $zurueck; ?>&pdf=1" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> PDF<?php echo $zu ? '' : '-Vorschau'; ?></a>
    </div>
</div>

<?php if (!$kennung): ?>
<div class="alert alert-warning alert-permanent"><i class="bi bi-exclamation-triangle"></i> Die Vereinskennung (Land, Bezirk, Verein) fehlt. Bitte unter <a href="einstellungen.php">Einstellungen → BMV</a> eintragen.</div>
<?php endif; ?>

<?php if ($zu): ?>
<div class="alert alert-success alert-permanent d-flex flex-wrap align-items-center gap-2">
    <div class="flex-grow-1">
        <i class="bi bi-lock-fill"></i> <strong>Abgeschlossen</strong> am <?php echo date('d.m.Y H:i', strtotime($bericht['abgeschlossen_am'])); ?>.
        <?php if ($bericht['gesendet_am']): ?>
        Gesendet an <?php echo htmlspecialchars($bericht['gesendet_an']); ?> am <?php echo date('d.m.Y H:i', strtotime($bericht['gesendet_am'])); ?>.
        <?php else: ?>
        Noch nicht gesendet.
        <?php endif; ?>
    </div>
    <?php if ($darfSchreiben): ?>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#sendenModal"><i class="bi bi-send"></i> <?php echo $bericht['gesendet_am'] ? 'Erneut senden' : 'An BMV senden'; ?></button>
    <?php endif; ?>
    <?php if ($darfSchreiben && $darfOeffnen): ?>
    <form method="POST" onsubmit="return confirm('Abschluss aufheben und den Bericht wieder bearbeiten?')">
        <input type="hidden" name="aktion" value="oeffnen"><input type="hidden" name="jahr" value="<?php echo $jahr; ?>">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-unlock"></i> Wieder öffnen</button>
    </form>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="flex-grow-1">
            <?php if ($fehlend): ?>
            <strong class="text-danger"><i class="bi bi-exclamation-circle"></i> Es fehlen noch <?php echo $fehlend; ?> Angaben.</strong>
            Rot markierte Felder bitte eintragen, dann lässt sich der Bericht abschließen.
            <?php else: ?>
            <strong class="text-success"><i class="bi bi-check-circle"></i> Alle Angaben sind vorhanden.</strong> Der Bericht kann abgeschlossen werden.
            <?php endif; ?>
            <div class="jb-legende small text-muted mt-1">
                <span style="border-color:rgba(25,135,84,.6)"></span>aus Syncopa
                <span class="ms-3" style="border-color:#0dcaf0;background:rgba(13,202,240,.1)"></span>aus dem Vorjahr
                <span class="ms-3" style="border-color:#e0a800;background:rgba(255,193,7,.15)"></span>von Hand eingetragen/geändert
                <span class="ms-3" style="border-color:#dc3545;background:rgba(220,53,69,.15)"></span>fehlt
            </div>
        </div>
        <?php if ($darfSchreiben): ?>
        <button type="submit" form="jbForm" name="aktion" value="speichern" class="btn btn-outline-primary"><i class="bi bi-floppy"></i> Speichern</button>
        <button type="button" class="btn btn-success" <?php echo $fehlend ? 'disabled title="Es fehlen noch Angaben"' : ''; ?> data-bs-toggle="modal" data-bs-target="#sendenModal"><i class="bi bi-lock"></i> Abschließen …</button>
        <?php else: ?>
        <span class="badge text-bg-secondary"><i class="bi bi-eye"></i> Nur ansehen</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($z['hinweise']): ?>
<div class="alert alert-info alert-permanent small">
    <i class="bi bi-info-circle"></i> <?php echo implode('<br><i class="bi bi-info-circle"></i> ', array_map('htmlspecialchars', $z['hinweise'])); ?>
</div>
<?php endif; ?>

<form method="POST" id="jbForm">
<input type="hidden" name="jahr" value="<?php echo $jahr; ?>">

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between"><h5 class="mb-0">Finanzielles</h5><a href="finanz_konten.php#kategorien" class="small">Zuordnung der Kassabuch-Kategorien</a></div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-lg-6">
                <table class="table table-sm jb-tab mb-0">
                    <thead><tr><th>Subventionen</th><th style="width:130px" class="text-center">€</th></tr></thead>
                    <?php foreach (BmvJahresbericht::SUBVENTIONEN as $k): ?>
                    <tr><td><?php echo BmvJahresbericht::POSTEN[$k]; ?></td><td><?php echo $feld($k, ' data-summe="sub"'); ?></td></tr>
                    <?php endforeach; ?>
                    <tr><td class="fst-italic fw-semibold text-end">Summe</td><td class="jb-summe" data-summe-von="sub"><?php echo $summe(BmvJahresbericht::SUBVENTIONEN); ?></td></tr>
                </table>
            </div>
            <div class="col-lg-6">
                <table class="table table-sm jb-tab mb-0">
                    <thead><tr><th>Ausgaben</th><th style="width:130px" class="text-center">€</th></tr></thead>
                    <?php foreach (BmvJahresbericht::AUSGABEN as $k): ?>
                    <tr><td><?php echo BmvJahresbericht::POSTEN[$k]; ?></td><td><?php echo $feld($k, ' data-summe="aus"'); ?></td></tr>
                    <?php endforeach; ?>
                    <tr><td class="fst-italic fw-semibold text-end">Summe</td><td class="jb-summe" data-summe-von="aus"><?php echo $summe(BmvJahresbericht::AUSGABEN); ?></td></tr>
                    <tr><td class="fst-italic fw-semibold text-end">Wertschöpfung (Subventionen − Ausgaben)</td><td class="jb-summe" id="jbWert"></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Organisation und Vereinswesen</h5></div>
    <div class="card-body">
        <div class="row g-3 mb-3 align-items-center">
            <div class="col-md-8"><?php echo $felder['jugendkapelle_statut'][1]; ?></div>
            <div class="col-md-4">
                <?php $q = $quelle['jugendkapelle_statut'] ?? 'fehlt'; ?>
                <select name="f[jugendkapelle_statut]" class="form-select form-select-sm jb-feld-wahl jb-<?php echo $q; ?>" <?php echo $sperre ? 'disabled' : ''; ?>>
                    <option value="">– bitte wählen –</option>
                    <option value="nein" <?php echo ($w['jugendkapelle_statut'] ?? '') === 'nein' ? 'selected' : ''; ?>>nein</option>
                    <option value="ja" <?php echo ($w['jugendkapelle_statut'] ?? '') === 'ja' ? 'selected' : ''; ?>>ja</option>
                </select>
            </div>
            <div class="col-md-8"><?php echo $felder['jugendkapellen_anzahl'][1]; ?></div>
            <div class="col-md-4"><?php echo $feld('jugendkapellen_anzahl'); ?></div>
            <div class="col-md-8"><?php echo $felder['jugendreferenten'][1]; ?> <span class="text-muted small">– Funktion „Jugendreferent“ am 31.12.</span></div>
            <div class="col-md-4"><?php echo $feld('jugendreferenten'); ?></div>
            <div class="col-md-8"><?php echo $felder['unterstuetzend'][1]; ?> <span class="text-muted small">– Mitgliedsart am 31.12.</span></div>
            <div class="col-md-4"><?php echo $feld('unterstuetzend'); ?></div>
        </div>

        <?php foreach ([['Mitgliederstand mit 31. 12.', [['Aktive Mitglieder', 'akt'], ['Sonstige nicht musizierende Mitglieder', 'son']]],
                        ['In Ausbildung stehende Musiker mit Stand 31. 12.', [['bereits aktive Mitglieder', 'ausb_akt'], ['noch nicht aktive Mitglieder (MusikschülerInnen)', 'ausb_neu']]]] as [$ueber, $gruppen]): ?>
        <h6 class="mt-3"><?php echo $ueber; ?></h6>
        <div class="table-responsive">
        <table class="table table-sm table-bordered jb-tab text-center mb-2">
            <thead class="table-light">
                <tr><th rowspan="2"></th><?php foreach ($gruppen as [$t]): ?><th colspan="4"><?php echo $t; ?></th><?php endforeach; ?></tr>
                <tr><?php foreach ($gruppen as $_): ?><th>männl.</th><th>weibl.</th><th>divers</th><th>gesamt</th><?php endforeach; ?></tr>
            </thead>
            <tbody>
            <?php foreach (['j' => 'bis 30 Jahre', 'a' => 'über 30 Jahre'] as $al => $label): ?>
            <tr><th class="text-nowrap"><?php echo $label; ?></th>
                <?php foreach ($gruppen as [, $pre]): ?>
                    <?php foreach (['m', 'w', 'd'] as $g): ?><td><?php echo $feld("{$pre}_{$al}_{$g}", " data-summe=\"{$pre}_{$al} {$pre}_s_{$g} {$pre}_s\""); ?></td><?php endforeach; ?>
                    <td class="jb-summe" data-summe-von="<?php echo "{$pre}_{$al}"; ?>"></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            <tr class="table-light"><th>Summe</th>
                <?php foreach ($gruppen as [, $pre]): ?>
                    <?php foreach (['m', 'w', 'd'] as $g): ?><td class="jb-summe" data-summe-von="<?php echo "{$pre}_s_{$g}"; ?>"></td><?php endforeach; ?>
                    <td class="jb-summe" data-summe-von="<?php echo "{$pre}_s"; ?>"></td>
                <?php endforeach; ?>
            </tr>
            </tbody>
        </table>
        </div>
        <?php endforeach; ?>
        <div class="small text-muted">Aktive Mitglieder, unterstützende Mitglieder und MusikschülerInnen kommen aus der Mitgliedsart zum 31.12., Alter und Geschlecht aus den Mitgliederdaten.
            Alle in Ausbildung stehenden Musiker bis 30 Jahre zählen, soweit sie über den Verein organisiert werden – auch z. B. Blockflöte oder musikalische Früherziehung.</div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between"><h5 class="mb-0">Proben und Ausrückungen</h5><a href="ausrueckungen.php" class="small">Ausrückungen</a></div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-lg-4">
                <table class="table table-sm table-bordered jb-tab text-center">
                    <thead class="table-light"><tr><th></th><th>Gesamt-Orchester</th><th>Jugend-Orchester</th></tr></thead>
                    <?php foreach (['voll' => 'Vollproben', 'teil' => 'Teilproben'] as $p => $l): ?>
                    <tr><th><?php echo $l; ?></th><td><?php echo $feld("probe_{$p}_gesamt", ' data-summe="probe_gesamt"'); ?></td><td><?php echo $feld("probe_{$p}_jugend", ' data-summe="probe_jugend"'); ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="table-light"><th>Summe</th><td class="jb-summe" data-summe-von="probe_gesamt"></td><td class="jb-summe" data-summe-von="probe_jugend"></td></tr>
                </table>
                <div class="small text-muted">Proben zählen als Vollprobe, außer bei der Probe ist „Teilprobe“ gewählt. Proben von Formationen mit „Jugendorchester“ zählen beim Jugend-Orchester.</div>
            </div>
            <div class="col-lg-8">
                <div class="table-responsive">
                <table class="table table-sm table-bordered jb-tab text-center">
                    <thead class="table-light"><tr><th class="text-start">Ausrückungen</th><th>Gesamt-Orchester</th><th>Ensembles / kleine Gruppen</th><th>Jugend-Orchester</th></tr></thead>
                    <?php foreach (BmvJahresbericht::ANLAESSE as $a => $l): ?>
                    <tr><td class="text-start small"><?php echo $l; ?></td>
                        <?php foreach (array_keys(BmvJahresbericht::KLANGKOERPER) as $k): ?><td><?php echo $feld("ar_{$a}_{$k}", " data-summe=\"ar_{$k}\""); ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    <tr class="table-light"><th class="text-start">Summe der Ausrückungen</th><?php foreach (array_keys(BmvJahresbericht::KLANGKOERPER) as $k): ?><td class="jb-summe" data-summe-von="ar_<?php echo $k; ?>"></td><?php endforeach; ?></tr>
                </table>
                </div>
                <div class="small text-muted">Der Anlass kommt aus dem Feld „BMV-Anlass“ der Ausrückung, sonst aus ihrem Typ. Die Spalte ergibt sich aus der Formation. Abgesagte Termine zählen nicht.</div>
            </div>
        </div>
        <div class="row g-3 mt-1 align-items-center">
            <?php foreach (['konzertreisen', 'besuche_ausland', 'bezirksfeste', 'landesfeste'] as $k): ?>
            <div class="col-md-4"><?php echo $felder[$k][1]; ?></div><div class="col-md-2"><?php echo $feld($k); ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Probelokal, Kleidung und Bearbeiter/in</h5></div>
    <div class="card-body">
        <div class="row g-4">
            <?php foreach ([['probelokal', 'MK/MV probt in', BmvJahresbericht::PROBELOKALE], ['kleidung', 'MK/MV trägt', BmvJahresbericht::KLEIDUNG]] as [$k, $l, $optionen]): ?>
            <div class="col-lg-6">
                <div class="fw-semibold mb-1"><?php echo $l; ?></div>
                <div class="jb-radio jb-<?php echo $quelle[$k] ?? 'fehlt'; ?> p-2">
                <?php foreach ($optionen as $ov => $ol): ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="f[<?php echo $k; ?>]" id="<?php echo "$k-$ov"; ?>" value="<?php echo $ov; ?>" <?php echo ($w[$k] ?? '') === $ov ? 'checked' : ''; ?> <?php echo $sperre ? 'disabled' : ''; ?>>
                    <label class="form-check-label" for="<?php echo "$k-$ov"; ?>"><?php echo $ol; ?></label>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="row g-3 mt-2">
            <?php foreach (['bearbeiter_name' => 'Bearbeiter/in', 'bearbeiter_email' => 'E-Mail', 'bearbeiter_telefon' => 'Telefon'] as $k => $l): $q = $quelle[$k] ?? 'fehlt'; ?>
            <div class="col-md-4">
                <label class="form-label small mb-1" for="f-<?php echo $k; ?>"><?php echo $l; ?></label>
                <input type="text" class="form-control form-control-sm jb-<?php echo $q; ?>" id="f-<?php echo $k; ?>" name="f[<?php echo $k; ?>]" value="<?php echo htmlspecialchars((string)($w[$k] ?? '')); ?>" maxlength="150" <?php echo $sperre ? 'readonly' : ''; ?>>
            </div>
            <?php endforeach; ?>
            <div class="col-md-4">
                <label class="form-label small mb-1">Abschlussdatum</label>
                <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)($w['abschlussdatum'] ?? '')); ?>" readonly>
            </div>
        </div>
    </div>
</div>

<?php if (!$sperre): ?>
<div class="d-flex gap-2 mb-4">
    <button type="submit" name="aktion" value="speichern" class="btn btn-primary"><i class="bi bi-floppy"></i> Speichern</button>
    <span class="small text-muted align-self-center">Tipp: Ein Feld leeren holt wieder den Wert aus Syncopa.</span>
</div>
<?php endif; ?>
</form>

<!-- Abschließen / Senden -->
<div class="modal fade" id="sendenModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><?php echo $zu ? 'Jahresbericht senden' : 'Jahresbericht abschließen'; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <?php if (!$zu): ?>
            <p>Nach dem Abschließen sind die Werte festgeschrieben und der Bericht wird als PDF abgelegt. Ein Administrator kann ihn bei Bedarf wieder öffnen.</p>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="jbSenden" name="senden" value="1" form="jbSendenForm" <?php echo $konten && $empfaenger ? 'checked' : ''; ?> <?php echo $konten ? '' : 'disabled'; ?>>
                <label class="form-check-label" for="jbSenden">gleich per E-Mail an den BMV senden</label>
            </div>
            <?php endif; ?>
            <div id="jbSendenFelder">
                <?php if (!$konten): ?>
                <div class="alert alert-warning alert-permanent small mb-0">Du hast kein E-Mail-Postfach in Syncopa. Der Bericht kann abgeschlossen und als PDF heruntergeladen werden.</div>
                <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="jbAn">An</label>
                        <input type="email" class="form-control" id="jbAn" name="an" form="jbSendenForm" value="<?php echo htmlspecialchars($empfaenger); ?>" placeholder="Adresse des Verbands">
                        <?php if (!$empfaenger): ?><div class="form-text">Tipp: Die Adresse lässt sich unter <a href="einstellungen.php">Einstellungen → BMV</a> fest hinterlegen.</div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="jbKonto">Von</label>
                        <select class="form-select" id="jbKonto" name="konto_id" form="jbSendenForm">
                            <?php foreach ($konten as $k): ?><option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['bezeichnung'] . ' <' . $k['email'] . '>'); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="jbText">Nachricht</label>
                        <textarea class="form-control" id="jbText" name="text" rows="7" form="jbSendenForm"><?php echo htmlspecialchars($standardText); ?></textarea>
                        <div class="form-text">Anhang: <?php echo htmlspecialchars(BmvJahresbericht::pdfName($jahr)); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
            <?php if ($zu): ?>
            <button class="btn btn-primary" form="jbSendenForm" name="aktion" value="senden" <?php echo $konten ? '' : 'disabled'; ?>><i class="bi bi-send"></i> Senden</button>
            <?php else: ?>
            <button class="btn btn-success" id="jbAbschliessen"><i class="bi bi-lock"></i> Abschließen</button>
            <?php endif; ?>
        </div>
    </div></div>
</div>
<form method="POST" id="jbSendenForm"><input type="hidden" name="jahr" value="<?php echo $jahr; ?>"></form>

<?php include 'includes/footer.php'; ?>
<script>
(function () {
    // Summen laufend mitrechnen; leer bleibt die Summe, solange ein Feld fehlt
    function rechnen() {
        var gruppen = {};
        document.querySelectorAll('[data-summe]').forEach(function (el) {
            el.dataset.summe.split(' ').forEach(function (g) {
                gruppen[g] = gruppen[g] || { s: 0, leer: false };
                if (el.value === '') gruppen[g].leer = true; else gruppen[g].s += parseInt(el.value, 10) || 0;
            });
        });
        document.querySelectorAll('[data-summe-von]').forEach(function (z) {
            var g = gruppen[z.dataset.summeVon];
            z.textContent = !g || g.leer ? '' : g.s.toLocaleString('de-AT');
        });
        var sub = gruppen.sub, aus = gruppen.aus;
        document.getElementById('jbWert').textContent = sub && aus && !sub.leer && !aus.leer ? (sub.s - aus.s).toLocaleString('de-AT') : '';
    }
    document.querySelectorAll('.jb-feld').forEach(function (el) {
        el.addEventListener('input', function () {
            el.classList.remove('jb-fehlt', 'jb-auto', 'jb-geaendert', 'jb-hand', 'jb-vorjahr');
            if (el.value === '') el.classList.add(el.dataset.auto !== undefined ? 'jb-auto' : 'jb-fehlt');
            else if (el.dataset.auto !== undefined) el.classList.add(el.value === el.dataset.auto ? 'jb-auto' : 'jb-geaendert');
            else el.classList.add('jb-hand');
            rechnen();
        });
    });
    // Auswahlfelder und Optionen: nach Änderung als Handeingabe kennzeichnen
    document.querySelectorAll('select.jb-feld-wahl, .jb-radio').forEach(function (el) {
        el.addEventListener('change', function () {
            el.classList.remove('jb-fehlt', 'jb-auto', 'jb-geaendert', 'jb-vorjahr');
            el.classList.add('jb-hand');
        });
    });
    rechnen();

    // Abschließen: Eingaben und Sendedaten gemeinsam abschicken
    var knopf = document.getElementById('jbAbschliessen');
    if (knopf) knopf.addEventListener('click', function () {
        var form = document.getElementById('jbForm');
        var senden = document.getElementById('jbSenden');
        ['an', 'konto_id', 'text'].forEach(function (n) {
            var q = document.querySelector('[name="' + n + '"][form="jbSendenForm"]');
            if (!q) return;
            var h = document.createElement('input'); h.type = 'hidden'; h.name = n; h.value = q.value; form.appendChild(h);
        });
        if (senden && senden.checked) {
            if (!document.getElementById('jbAn').value) { alert('Bitte die Empfänger-Adresse eintragen.'); return; }
            var s = document.createElement('input'); s.type = 'hidden'; s.name = 'senden'; s.value = '1'; form.appendChild(s);
        }
        var a = document.createElement('input'); a.type = 'hidden'; a.name = 'aktion'; a.value = 'abschliessen'; form.appendChild(a);
        knopf.disabled = true;
        form.submit();
    });
    var schalter = document.getElementById('jbSenden');
    if (schalter) {
        var felder = document.getElementById('jbSendenFelder');
        var umschalten = function () { felder.style.opacity = schalter.checked ? 1 : .4; };
        schalter.addEventListener('change', umschalten); umschalten();
    }
})();
</script>
