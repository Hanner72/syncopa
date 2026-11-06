<?php
// bmv_daten.php
// BMV Daten holen: Vereinsdaten und Mitglieder aus dem BMV-Datenservice in Syncopa übernehmen,
// das Vereinsinventar aus dem CSV-Export des BMV-Portals. Nur lesend gegenüber dem BMV – dort wird nichts geändert.
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin()) {
    Session::setFlashMessage('danger', 'Nur Administratoren haben Zugriff');
    header('Location: index.php');
    exit;
}

$bereiche = [
    'verein'     => ['label' => 'Vereinsdaten', 'icon' => 'bi-building'],
    'mitglieder'     => ['label' => 'Mitglieder',     'icon' => 'bi-people'],
    'noten'          => ['label' => 'Noten',          'icon' => 'bi-music-note-list'],
    'inventar'       => ['label' => 'Inventar',       'icon' => 'bi-box-seam'],
    'bezeichnungen'  => ['label' => 'Bezeichnungen',  'icon' => 'bi-tags'],
];
$bereich = $_GET['bereich'] ?? 'verein';
if (!isset($bereiche[$bereich])) {
    $bereich = 'verein';
}
$seitenUrl = 'bmv_daten.php?bereich=' . $bereich;

$api    = new BmvApi();
$fehler = null;

// Inventar: CSV-Export aus dem BMV-Portal (braucht keine Verbindung zum BMV-Datenservice)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with($_POST['aktion'] ?? '', 'inventar_')) {
    try {
        switch ($_POST['aktion']) {
            case 'inventar_hochladen':
                $datei = $_FILES['datei'] ?? null;
                if (!$datei || $datei['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($datei['tmp_name'])) {
                    throw new RuntimeException('Bitte eine CSV-Datei auswählen.');
                }
                if ($datei['size'] > 5 * 1024 * 1024) {
                    throw new RuntimeException('Die Datei ist zu groß (maximal 5 MB).');
                }
                $_SESSION['bmv_inventar'] = [
                    'datei' => basename((string)$datei['name']),
                    'zeit'  => date('Y-m-d H:i:s'),
                    'daten' => BmvInventar::dateiLesen((string)file_get_contents($datei['tmp_name'])),
                ];
                break;
            case 'inventar_verwerfen':
                unset($_SESSION['bmv_inventar']);
                break;
            case 'inventar_uebernehmen':
                if (empty($_SESSION['bmv_inventar'])) {
                    throw new RuntimeException('Bitte die Datei erneut hochladen.');
                }
                $zuordnung = [];
                foreach ($_POST['bez'] ?? [] as $i => $bez) {
                    $wahl = $_POST['typ'][$i] ?? 'neu';
                    $zuordnung[$bez] = $wahl === 'neu' ? 'neu' : (int)$wahl;
                }
                $e = BmvInventar::uebernehmen($_SESSION['bmv_inventar']['daten']['instrumente'], $zuordnung, $_POST['konflikt_zusammen'] ?? []);
                unset($_SESSION['bmv_inventar']);
                Session::setFlashMessage('success', 'Inventar übernommen: ' . $e['neu'] . ' neu, ' . $e['zusammen'] . ' zusammengeführt, '
                    . $e['ergaenzt'] . ' ergänzt, ' . $e['unveraendert'] . ' unverändert' . ($e['uebersprungen'] ? ', ' . $e['uebersprungen'] . ' übersprungen' : '') . '.');
                break;
        }
    } catch (RuntimeException $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: bmv_daten.php?bereich=inventar');
    exit;
}

// Übernehmen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $api->istKonfiguriert()) {
    try {
        if (($_POST['aktion'] ?? '') === 'verein_uebernehmen') {
            BmvDaten::vereinsdatenUebernehmen($api->eigenenVereinHolen());
            Session::setFlashMessage('success', 'Vereinsdaten aus dem BMV übernommen.');
        } elseif (($_POST['aktion'] ?? '') === 'mitglieder_uebernehmen') {
            $ergebnis = BmvMitgliederSync::uebernehmen($api);
            // Zusatzdaten erst danach, damit neu angelegte Mitglieder ihre Ehrungen usw. gleich mitbekommen
            $zusatz = BmvZusatzdaten::uebernehmen($api);
            Session::setFlashMessage('success', 'Mitglieder übernommen: ' . $ergebnis['neu'] . ' neu, '
                . $ergebnis['aktualisiert'] . ' aktualisiert, ' . $ergebnis['nicht_im_bmv'] . ' nicht im BMV markiert. '
                . 'Ehrungen, Funktionen u. a.: ' . $zusatz['neu'] . ' neu, ' . $zusatz['geaendert'] . ' geändert, '
                . $zusatz['entfernt'] . ' entfernt.');
        } elseif (($_POST['aktion'] ?? '') === 'noten_uebernehmen') {
            $e = BmvNoten::uebernehmen($api, !empty($_POST['ohne_material']));
            Session::setFlashMessage('success', 'Noten übernommen: ' . $e['neu'] . ' neu, ' . $e['zusammen'] . ' zusammengeführt, '
                . $e['ergaenzt'] . ' ergänzt, ' . $e['unveraendert'] . ' unverändert'
                . ($e['uebersprungen'] ? ', ' . $e['uebersprungen'] . ' ohne Notenmaterial übersprungen' : '') . '.');
        } elseif (($_POST['aktion'] ?? '') === 'bezeichnungen_speichern') {
            $eingaben = $_POST['bezeichnung'] ?? [];
            // Neue Nummer (z. B. eine Ehrung, die noch niemand im Verein hat)
            $neuArt = $_POST['neu_art'] ?? '';
            $neuCode = (int)($_POST['neu_code'] ?? 0);
            if ($neuCode > 0 && isset(BmvZusatzdaten::BEZEICHNUNG_ARTEN[$neuArt])) {
                $eingaben[$neuArt][$neuCode] = $_POST['neu_name'] ?? '';
            }
            BmvZusatzdaten::bezeichnungenSpeichern($eingaben);
            BmvZusatzdaten::instrumentZuordnungSpeichern($_POST['instrument_typ'] ?? []);
            Session::setFlashMessage('success', 'Bezeichnungen gespeichert.');
            header('Location: ' . $seitenUrl);
            exit;
        }
        BmvDaten::pruefen($api); // Hinweis in den Einstellungen auf den neuen Stand bringen
    } catch (Throwable $e) {
        Session::setFlashMessage('danger', 'Übernahme fehlgeschlagen, es wurde nichts geändert: ' . $e->getMessage());
    }
    header('Location: ' . $seitenUrl);
    exit;
}

// Daten für die Anzeige holen
$vergleich = null;
$vorschau  = null;
if (!$api->istKonfiguriert()) {
    $fehler = 'Der BMV ist nicht eingerichtet. Bitte unter Einstellungen → BMV Zugangsdaten die Zugangsdaten eintragen und den Schalter aktivieren.';
} else {
    try {
        if ($bereich === 'verein') {
            $vergleich = BmvDaten::vereinsdatenVergleich($api->eigenenVereinHolen());
        } elseif ($bereich === 'noten') {
            $notenVorschau = BmvNoten::vorschau(BmvNoten::holen($api));
            $notenBisher = Database::getInstance()->fetchOne(
                "SELECT COUNT(*) AS anzahl, MAX(bmv_import_am) AS zuletzt FROM noten WHERE bmv_noten_id IS NOT NULL"
            );
        } elseif ($bereich === 'mitglieder') {
            $vorschau = BmvMitgliederSync::vorschau($api);
            $zusatzVergleich = BmvZusatzdaten::vergleich(BmvZusatzdaten::holen($api));
        }
    } catch (RuntimeException $e) {
        $fehler = $e->getMessage();
    }
}

if ($bereich === 'inventar') {
    $fehler = null; // Inventar kommt aus einer Datei und braucht keine BMV-Verbindung
    $inventarImport = $_SESSION['bmv_inventar'] ?? null;
    if ($inventarImport) {
        $inventarVorschau = BmvInventar::vorschau($inventarImport['daten']['instrumente']);
        $inventarTypen = Database::getInstance()->fetchAll(
            "SELECT it.id, it.name, r.name AS register_name FROM instrument_typen it
             LEFT JOIN register r ON r.id = it.register_id ORDER BY r.sortierung IS NULL, r.sortierung, it.name"
        );
    }
    $inventarBisher = Database::getInstance()->fetchOne(
        "SELECT COUNT(*) AS anzahl, MAX(bmv_import_am) AS zuletzt FROM instrumente WHERE bmv_inventar_id IS NOT NULL"
    );
}

if ($bereich === 'bezeichnungen') {
    $fehler = null; // Bezeichnungen sind lokal und brauchen keine BMV-Verbindung
    $verwendung = [];
    foreach (Database::getInstance()->fetchAll(
        "SELECT art, code, COUNT(*) AS anzahl FROM bmv_mitglied_daten WHERE code IS NOT NULL GROUP BY art, code
         UNION ALL SELECT 'bereich', bereich_code, COUNT(*) FROM bmv_mitglied_daten WHERE bereich_code IS NOT NULL GROUP BY bereich_code
         UNION ALL SELECT 'instrument', instrument_code, COUNT(*) FROM bmv_mitglied_daten WHERE instrument_code IS NOT NULL GROUP BY instrument_code
         UNION ALL SELECT 'instrument', bmv_code, COUNT(*) FROM mitglied_instrumente WHERE bmv_code IS NOT NULL GROUP BY bmv_code"
    ) as $r) {
        $verwendung[$r['art']][(int)$r['code']] = ($verwendung[$r['art']][(int)$r['code']] ?? 0) + (int)$r['anzahl'];
    }
    $bezeichnungen = BmvZusatzdaten::bezeichnungen();          // gilt (eigene, sonst Standard)
    $eigeneBezeichnungen = BmvZusatzdaten::eigeneBezeichnungen();
    $standardBezeichnungen = BmvZusatzdaten::standardBezeichnungen();
    $instrumentZuordnung = BmvZusatzdaten::instrumentZuordnung();
    $instrumentTypen = Database::getInstance()->fetchAll(
        "SELECT it.id, it.name, it.bmv_automatisch, r.name AS register_name FROM instrument_typen it
         LEFT JOIN register r ON r.id = it.register_id ORDER BY r.sortierung IS NULL, r.sortierung, it.name"
    );
}

$einstellungen = [];
foreach (Database::getInstance()->fetchAll("SELECT schluessel, wert FROM einstellungen WHERE schluessel IN ('bmv_letzte_sync_verein', 'bmv_verein_nr')") as $r) {
    $einstellungen[$r['schluessel']] = $r['wert'];
}

include 'includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-cloud-download"></i> BMV Daten holen</h1>
    <a href="einstellungen.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Einstellungen</a>
</div>

<div class="alert alert-permanent alert-info small">
    <i class="bi bi-info-circle"></i>
    Daten werden nur aus dem BMV-Datenservice geholt. Im BMV wird nichts geändert.
    Beim Übernehmen werden die Daten in Syncopa mit dem BMV-Stand überschrieben.
</div>

<ul class="nav nav-tabs mb-4">
    <?php foreach ($bereiche as $schluessel => $b): ?>
    <li class="nav-item">
        <a class="nav-link <?php echo $schluessel === $bereich ? 'active' : ''; ?>" href="bmv_daten.php?bereich=<?php echo $schluessel; ?>">
            <i class="bi <?php echo $b['icon']; ?>"></i> <?php echo $b['label']; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if ($fehler): ?>
<div class="alert alert-permanent alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($fehler); ?></div>

<?php elseif ($bereich === 'verein'):
    $anzahlGeaendert = count(array_filter($vergleich, fn($f) => $f['geaendert']));
    $nochNieGeholt   = empty($einstellungen['bmv_verein_nr']);
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Vereinsdaten</h5>
        <?php if ($anzahlGeaendert > 0): ?>
        <span class="badge bg-warning text-dark"><?php echo $anzahlGeaendert; ?> <?php echo $anzahlGeaendert === 1 ? 'Feld' : 'Felder'; ?> unterschiedlich</span>
        <?php else: ?>
        <span class="badge bg-success">Aktuell</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            <?php if (!empty($einstellungen['bmv_letzte_sync_verein'])): ?>
            Zuletzt aus dem BMV übernommen: <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($einstellungen['bmv_letzte_sync_verein']))); ?>
            <?php else: ?>
            Die Vereinsdaten wurden noch nie aus dem BMV übernommen.
            <?php endif; ?>
        </p>
        <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Feld</th><th>In Syncopa</th><th>Im BMV</th></tr></thead>
            <tbody>
            <?php foreach (BmvApi::VEREIN_FELDER as $schluessel => $label):
                $f = $vergleich[$schluessel]; ?>
            <tr class="<?php echo $f['geaendert'] ? 'table-warning' : ''; ?>">
                <td class="fw-semibold"><?php echo htmlspecialchars($label); ?></td>
                <td><?php echo $f['lokal'] !== '' ? htmlspecialchars($f['lokal']) : '<span class="text-muted">–</span>'; ?></td>
                <td><?php echo $f['bmv'] !== '' ? htmlspecialchars($f['bmv']) : '<span class="text-muted">–</span>'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<form method="POST" class="d-flex justify-content-end"
      onsubmit="return confirm('Vereinsdaten aus dem BMV übernehmen? Die Werte in Syncopa werden überschrieben. Im BMV wird nichts geändert.');">
    <input type="hidden" name="aktion" value="verein_uebernehmen">
    <button type="submit" class="btn btn-primary btn-lg" <?php echo ($anzahlGeaendert === 0 && !$nochNieGeholt) ? 'disabled' : ''; ?>>
        <i class="bi bi-download"></i> Vereinsdaten übernehmen
    </button>
</form>

<?php elseif ($bereich === 'noten'):
    $zaehl = array_count_values(array_column($notenVorschau, 'aktion'));
    $ohneMaterial = count(array_filter($notenVorschau, fn($z) => $z['aktion'] === 'neu' && !$z['bmv_notenmaterial']));
    $aenderungen = ($zaehl['neu'] ?? 0) + ($zaehl['zusammen'] ?? 0) + count(array_filter($notenVorschau, fn($z) => $z['aktion'] === 'vorhanden' && $z['ergaenzt']));
    $notenAktion = [
        'neu'       => ['Neu', 'success', 'Wird mit einer neuen Syncopa-Archivnummer angelegt.'],
        'zusammen'  => ['Zusammenführen', 'info', 'Dasselbe Stück (Titel und Komponist) ist in Syncopa schon erfasst – wird verknüpft, leere Felder werden ergänzt.'],
        'vorhanden' => ['Schon übernommen', 'secondary', 'Wurde bereits übernommen – nur leere Felder werden ergänzt.'],
    ];
?>
<div class="alert alert-permanent alert-secondary small">
    <i class="bi bi-info-circle"></i>
    <strong>Woher kommen diese Daten?</strong> Aus dem Notenarchiv des Vereins im BMV-Datenservice.
    Die Noten werden danach <strong>in Syncopa gepflegt</strong> – an den BMV wird nichts zurückgeschickt.
    Holst du später erneut, kommen nur neue Stücke dazu; bei vorhandenen werden nur leere Felder ergänzt, deine Änderungen bleiben.<br>
    <strong>Archivnummern:</strong> Im BMV teilen sich oft mehrere Stücke eine Nummer. Syncopa vergibt deshalb eigene, eindeutige
    Archivnummern (Nummernkreis, z. B. N0001). Die BMV-Nummer wird beim Stück als „BMV-Nr.“ mitgespeichert.
</div>

<div class="row mb-3">
    <?php foreach ($notenAktion as $a => [$titel, $farbe]): ?>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-<?php echo $farbe; ?>"><div class="card-body"><div>
            <h6><?php echo $titel; ?></h6><h2><?php echo (int)($zaehl[$a] ?? 0); ?></h2>
        </div></div></div>
    </div>
    <?php endforeach; ?>
    <div class="col-6 col-md-3 mb-2">
        <div class="card stat-card border-warning"><div class="card-body"><div>
            <h6>Ohne Notenmaterial</h6><h2><?php echo $ohneMaterial; ?></h2>
        </div></div></div>
    </div>
</div>

<?php if ((int)$notenBisher['anzahl'] > 0): ?>
<p class="text-muted small">Bisher übernommen: <?php echo (int)$notenBisher['anzahl']; ?> Stücke, zuletzt am <?php echo date('d.m.Y H:i', strtotime($notenBisher['zuletzt'])); ?>.</p>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Stücke im BMV</h5>
        <input type="search" class="form-control form-control-sm" style="max-width:240px" placeholder="Suchen …" id="notenSuche">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:520px">
        <table class="table table-sm table-hover align-middle mb-0" id="notenTabelle">
            <thead class="sticky-top"><tr><th class="ps-3">BMV-Nr.</th><th>Titel</th><th>Komponist / Bearbeiter</th><th>Musikart</th><th>Notenmaterial</th><th class="pe-3">Was passiert</th></tr></thead>
            <tbody>
            <?php foreach ($notenVorschau as $z): [$titel, $farbe, $erklaerung] = $notenAktion[$z['aktion']]; ?>
            <tr>
                <td class="ps-3 text-muted"><?php echo htmlspecialchars($z['bmv_nummer'] ?? '–'); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($z['titel']); ?></strong>
                    <?php if ($z['doppelt']): ?><span class="badge bg-warning-subtle text-warning-emphasis ms-1" data-bs-toggle="tooltip" title="Der BMV führt dieses Stück (gleicher Titel und Komponist) mehrfach – es wird mehrfach übernommen. Doppelte kannst du danach in Syncopa löschen.">doppelt im BMV</span><?php endif; ?>
                </td>
                <td class="small"><?php echo htmlspecialchars($z['komponist'] ?? '–'); ?><?php if ($z['arrangeur']): ?><br><span class="text-muted">Bearb.: <?php echo htmlspecialchars($z['arrangeur']); ?></span><?php endif; ?></td>
                <td class="small"><?php echo htmlspecialchars($z['genre'] ?? '–'); ?></td>
                <td><?php echo $z['bmv_notenmaterial'] ? '<i class="bi bi-check-lg text-success" title="vorhanden"></i>' : '<span class="text-muted small">nein</span>'; ?></td>
                <td class="small pe-3">
                    <span class="badge bg-<?php echo $farbe; ?>" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($erklaerung); ?>"><?php echo $titel; ?></span>
                    <?php if ($z['vorhanden']): ?><div class="text-muted">Syncopa: <a href="noten_bearbeiten.php?id=<?php echo (int)$z['vorhanden']['id']; ?>"><?php echo htmlspecialchars($z['vorhanden']['archiv_nummer']); ?></a></div><?php endif; ?>
                    <?php if ($z['ergaenzt']): ?><div class="text-muted">ergänzt: <?php echo htmlspecialchars(implode(', ', $z['ergaenzt'])); ?></div><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<form method="POST" class="d-flex flex-wrap justify-content-end align-items-center gap-3 page-actions-sticky"
      onsubmit="return confirm('Noten jetzt in Syncopa übernehmen? Im BMV wird nichts geändert.');">
    <input type="hidden" name="aktion" value="noten_uebernehmen">
    <?php if ($ohneMaterial): ?>
    <div class="form-check mb-0">
        <input class="form-check-input" type="checkbox" name="ohne_material" value="1" id="ohneMaterial" checked>
        <label class="form-check-label" for="ohneMaterial">Auch die <?php echo $ohneMaterial; ?> Stücke ohne Notenmaterial übernehmen</label>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary btn-lg" <?php echo $aenderungen ? '' : 'disabled'; ?>>
        <i class="bi bi-download"></i> Noten übernehmen
    </button>
</form>
<script>
document.getElementById('notenSuche')?.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#notenTabelle tbody tr').forEach(tr => tr.classList.toggle('d-none', q !== '' && !tr.textContent.toLowerCase().includes(q)));
});
</script>

<?php elseif ($bereich === 'inventar'): ?>
<div class="alert alert-permanent alert-secondary small">
    <i class="bi bi-info-circle"></i>
    <strong>Woher kommen diese Daten?</strong> Das Vereinsinventar liefert die BMV-Schnittstelle nicht. Exportiere es im
    BMV-Portal unter <em>Inventar → CSV-Export</em> und lade die Datei hier hoch. Übernommen werden nur Einträge vom Typ
    „Instrument“ (Einrichtung, Sonstiges usw. werden übersprungen).<br>
    <strong>Nur in Syncopa:</strong> Das Inventar wird danach in Syncopa gepflegt. An den BMV wird nichts zurückgeschickt,
    diese Daten werden dort nicht gebraucht. Bei jedem Instrument steht, dass es aus dem BMV-Inventar übernommen wurde.
</div>

<?php if (!$inventarImport): ?>
<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">CSV-Export hochladen</h5></div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
            <input type="hidden" name="aktion" value="inventar_hochladen">
            <input type="file" class="form-control" style="max-width:420px" name="datei" accept=".csv,text/csv" required>
            <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Hochladen und prüfen</button>
        </form>
        <p class="text-muted small mt-2 mb-0">
            Es wird noch nichts übernommen – zuerst siehst du eine Vorschau.
            <?php if ((int)$inventarBisher['anzahl'] > 0): ?>
            Bisher übernommen: <?php echo (int)$inventarBisher['anzahl']; ?> Instrumente, zuletzt am <?php echo date('d.m.Y H:i', strtotime($inventarBisher['zuletzt'])); ?>.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php else:
    $zaehl = array_count_values(array_column($inventarVorschau, 'aktion'));
    $bezeichnungenListe = [];
    foreach ($inventarVorschau as $z) {
        $bezeichnungenListe[$z['bmv_bezeichnung']] ??= ['anzahl' => 0, 'typ' => $z['instrument_typ_id']];
        $bezeichnungenListe[$z['bmv_bezeichnung']]['anzahl']++;
    }
    ksort($bezeichnungenListe, SORT_NATURAL | SORT_FLAG_CASE);
    $aktionInfo = [
        'neu'       => ['Neu', 'success', 'Wird neu angelegt.'],
        'zusammen'  => ['Zusammenführen', 'info', 'Gleiche Inventarnummer und passendes Instrument in Syncopa – wird verknüpft, leere Felder werden ergänzt.'],
        'vorhanden' => ['Schon übernommen', 'secondary', 'Wurde bereits früher übernommen – nur leere Felder werden ergänzt.'],
        'konflikt'  => ['Konflikt', 'danger', 'Die Inventarnummer ist in Syncopa an ein anderes Instrument vergeben.'],
    ];
?>
<form method="POST">
    <input type="hidden" name="aktion" value="inventar_uebernehmen">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            <i class="bi bi-file-earmark-spreadsheet"></i> <strong><?php echo htmlspecialchars($inventarImport['datei']); ?></strong>,
            hochgeladen <?php echo date('d.m.Y H:i', strtotime($inventarImport['zeit'])); ?> –
            <?php echo (int)$inventarImport['daten']['gesamt']; ?> Einträge, davon <?php echo count($inventarVorschau); ?> Instrumente
            <?php if ($inventarImport['daten']['andere']): ?>
            (übersprungen: <?php echo htmlspecialchars(implode(', ', array_map(fn($t, $n) => "{$n}× {$t}", array_keys($inventarImport['daten']['andere']), $inventarImport['daten']['andere']))); ?>)
            <?php endif; ?>
        </div>
        <button type="submit" form="inventarVerwerfen" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> Andere Datei</button>
    </div>

    <div class="row mb-3">
        <?php foreach ($aktionInfo as $a => [$titel, $farbe]): ?>
        <div class="col-6 col-md-3 mb-2">
            <div class="card stat-card border-<?php echo $farbe; ?>"><div class="card-body"><div>
                <h6><?php echo $titel; ?></h6><h2><?php echo (int)($zaehl[$a] ?? 0); ?></h2>
            </div></div></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">1. Instrumentart zuordnen</h5></div>
        <div class="card-body">
            <p class="text-muted small">Zu jeder Bezeichnung aus dem BMV die passende Instrumentart in Syncopa. Vorschläge sind schon eingetragen; die Wahl wird für den nächsten Import gemerkt.</p>
            <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Bezeichnung im BMV</th><th class="text-end">Anzahl</th><th style="min-width:260px">Instrumentart in Syncopa</th></tr></thead>
                <tbody>
                <?php $i = 0; foreach ($bezeichnungenListe as $bez => $info): ?>
                <tr>
                    <td><?php echo htmlspecialchars($bez); ?><input type="hidden" name="bez[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($bez); ?>"></td>
                    <td class="text-end text-muted"><?php echo $info['anzahl']; ?></td>
                    <td>
                        <select class="form-select form-select-sm <?php echo $info['typ'] === 'neu' ? 'border-warning' : ''; ?>" name="typ[<?php echo $i; ?>]">
                            <option value="neu" <?php echo $info['typ'] === 'neu' ? 'selected' : ''; ?>>+ Neue Instrumentart „<?php echo htmlspecialchars(mb_substr($bez, 0, 40)); ?>“</option>
                            <?php foreach ($inventarTypen as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>" <?php echo $info['typ'] === (int)$t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name'] . ($t['register_name'] ? ' (' . $t['register_name'] . ')' : '')); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php $i++; endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">2. Instrumente prüfen</h5></div>
        <div class="card-body">
            <p class="text-muted small">
                Werte, die in Syncopa schon eingetragen sind, bleiben erhalten – aus dem BMV werden nur leere Felder ergänzt.
                Zugeteilt wird über das Mitglied im BMV bzw. Name und Geburtsdatum.
            </p>
            <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Inv.-Nr.</th><th>Bezeichnung</th><th>Fabrikat / Seriennr.</th><th>Kauf</th><th>Zugeteilt an</th><th>Was passiert</th></tr></thead>
                <tbody>
                <?php foreach ($inventarVorschau as $z): [$titel, $farbe, $erklaerung] = $aktionInfo[$z['aktion']]; ?>
                <tr>
                    <td class="fw-semibold"><?php echo htmlspecialchars($z['inventar_nummer']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($z['bmv_bezeichnung']); ?>
                        <?php if ($z['bmv_anmerkung']): ?><i class="bi bi-chat-left-text text-muted ms-1" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($z['bmv_anmerkung']); ?>"></i><?php endif; ?>
                    </td>
                    <td class="small"><?php echo htmlspecialchars($z['hersteller'] ?? '–'); ?><br><span class="text-muted"><?php echo htmlspecialchars($z['seriennummer'] ?? ''); ?></span></td>
                    <td class="small text-nowrap">
                        <?php echo $z['anschaffungsdatum'] ? date('d.m.Y', strtotime($z['anschaffungsdatum'])) : '–'; ?><br>
                        <span class="text-muted"><?php echo $z['anschaffungspreis'] !== null ? number_format($z['anschaffungspreis'], 2, ',', '.') . ' €' : ''; ?></span>
                    </td>
                    <td class="small">
                        <?php if ($z['mitglied_name']): ?>
                        <i class="bi bi-person-check text-success"></i> <?php echo htmlspecialchars($z['mitglied_name']); ?>
                        <?php elseif ($z['zugeteilt_text']): ?>
                        <span class="text-warning" data-bs-toggle="tooltip" title="Kein passendes Mitglied in Syncopa gefunden – wird ohne Zuteilung übernommen"><i class="bi bi-person-x"></i> <?php echo htmlspecialchars($z['zugeteilt_text']); ?></span>
                        <?php else: ?><span class="text-muted">–</span><?php endif; ?>
                    </td>
                    <td class="small">
                        <span class="badge bg-<?php echo $farbe; ?>" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($erklaerung); ?>"><?php echo $titel; ?></span>
                        <?php if ($z['vorhanden']): ?>
                        <div class="text-muted mt-1">
                            Syncopa: <?php echo htmlspecialchars($z['vorhanden']['inventar_nummer'] . ' ' . $z['vorhanden']['typ']
                                . ($z['vorhanden']['hersteller'] ? ', ' . $z['vorhanden']['hersteller'] : '')
                                . ($z['vorhanden']['seriennummer'] ? ', SN ' . $z['vorhanden']['seriennummer'] : '')); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($z['ergaenzt']): ?><div class="text-muted">ergänzt: <?php echo htmlspecialchars(implode(', ', $z['ergaenzt'])); ?></div><?php endif; ?>
                        <?php if ($z['aktion'] === 'konflikt'): ?>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="konflikt_zusammen[]" value="<?php echo htmlspecialchars($z['bmv_inventar_id']); ?>" id="k<?php echo htmlspecialchars($z['bmv_inventar_id']); ?>">
                            <label class="form-check-label" for="k<?php echo htmlspecialchars($z['bmv_inventar_id']); ?>">Trotzdem als dasselbe Instrument verknüpfen</label>
                        </div>
                        <div class="text-muted">Sonst wird es übersprungen. Tipp: in Syncopa die Inventarnummer ändern und die Datei neu hochladen.</div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end page-actions-sticky">
        <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Inventar jetzt in Syncopa übernehmen? Im BMV wird nichts geändert.');">
            <i class="bi bi-download"></i> Inventar übernehmen
        </button>
    </div>
</form>
<form method="POST" id="inventarVerwerfen"><input type="hidden" name="aktion" value="inventar_verwerfen"></form>
<?php endif; ?>

<?php elseif ($bereich === 'bezeichnungen'): ?>
<div class="alert alert-permanent alert-secondary small">
    <i class="bi bi-tags"></i>
    Der BMV liefert zu Ehrungen, Funktionen usw. nur Nummern. Syncopa bringt für die bekannten Nummern Standardnamen mit
    (grau im leeren Feld) – trägst du selbst etwas ein, gilt dein Name. Ohne Namen erscheint z. B. „Ehrenzeichen Nr. 31“.
    Einige Namen sind aus den Prüfungsurkunden vorbefüllt.
    Bei den Instrumenten wählst du zusätzlich, welches Syncopa-Instrument dazugehört – so kommen BMV- und eigene Instrumente
    in einer Liste zusammen. Mehrere BMV-Nummern dürfen dasselbe Syncopa-Instrument haben.<br>
    Mitgliedsarten pflegst du unter <a href="stammdaten.php?tab=mitgliedsarten">Stammdaten → Mitgliedsarten</a>.
</div>
<?php if (!$verwendung): ?>
<div class="alert alert-permanent alert-info">Noch keine Daten vorhanden. Bitte zuerst unter <a href="bmv_daten.php?bereich=mitglieder">Mitglieder</a> übernehmen.</div>
<?php else: ?>
<form method="POST">
    <input type="hidden" name="aktion" value="bezeichnungen_speichern">
    <div class="row">
    <?php foreach (BmvZusatzdaten::BEZEICHNUNG_ARTEN as $art => $titel):
        if ($art === 'mitgliedsart') continue; // eigener Katalog unter Stammdaten → Mitgliedsarten
        $codes = array_keys(($verwendung[$art] ?? []) + ($bezeichnungen[$art] ?? []));
        if (!$codes) continue;
        sort($codes);
        $offen = count(array_filter($codes, fn($c) => ($bezeichnungen[$art][$c] ?? '') === ''));
        $istInstrument = $art === 'instrument'; ?>
    <div class="<?php echo $istInstrument ? 'col-12' : 'col-lg-6'; ?>">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo $titel; ?></h5>
                <?php if ($offen): ?><span class="badge bg-warning text-dark"><?php echo $offen; ?> ohne Namen</span><?php endif; ?>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th class="ps-3" style="width:70px">Nr.</th><th>Name im BMV</th><?php if ($istInstrument): ?><th>Syncopa-Instrument</th><?php endif; ?><th class="text-end pe-3" style="width:90px">Einträge</th></tr></thead>
                    <tbody>
                    <?php foreach ($codes as $code): ?>
                    <tr>
                        <td class="ps-3 text-muted"><?php echo (int)$code; ?></td>
                        <td>
                            <input type="text" class="form-control form-control-sm" maxlength="150"
                                   name="bezeichnung[<?php echo $art; ?>][<?php echo (int)$code; ?>]"
                                   value="<?php echo htmlspecialchars($eigeneBezeichnungen[$art][$code] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($standardBezeichnungen[$art][$code] ?? ''); ?>"
                                   <?php if (!empty($standardBezeichnungen[$art][$code])): ?>title="Standardname: <?php echo htmlspecialchars($standardBezeichnungen[$art][$code]); ?> – gilt, solange das Feld leer ist"<?php endif; ?>>
                        </td>
                        <?php if ($istInstrument): ?>
                        <td>
                            <select class="form-select form-select-sm" name="instrument_typ[<?php echo (int)$code; ?>]">
                                <option value="">– wird beim Holen automatisch zugeordnet –</option>
                                <?php foreach ($instrumentTypen as $typ): ?>
                                <option value="<?php echo (int)$typ['id']; ?>" <?php echo ($instrumentZuordnung[$code] ?? null) === (int)$typ['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($typ['name'] . ($typ['register_name'] ? ' (' . $typ['register_name'] . ')' : '') . ($typ['bmv_automatisch'] ? ' – neu aus BMV' : '')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <?php endif; ?>
                        <td class="text-end pe-3 text-muted"><?php echo (int)($verwendung[$art][$code] ?? 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Nummer hinzufügen</h5></div>
        <div class="card-body">
            <p class="text-muted small">Für Nummern, die im Verein noch niemand hat, z. B. ein Ehrenzeichen, das man selbst eintragen möchte.
                Die Nummer findest du im BMV-Portal.</p>
            <div class="row g-2">
                <div class="col-md-4">
                    <select class="form-select" name="neu_art">
                        <?php foreach (BmvZusatzdaten::BEZEICHNUNG_ARTEN as $art => $titel): if ($art === 'mitgliedsart') continue; ?>
                        <option value="<?php echo $art; ?>"><?php echo $titel; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><input type="number" class="form-control" name="neu_code" min="1" placeholder="Nr."></div>
                <div class="col-md-6"><input type="text" class="form-control" name="neu_name" maxlength="150" placeholder="Name"></div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end page-actions-sticky">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Bezeichnungen speichern</button>
    </div>
</form>
<?php endif; ?>

<?php else: /* Mitglieder */ ?>
<div class="row mb-3">
    <?php
    $kacheln = [
        ['Im BMV (alle Musiker)', $vorschau['anzahl_bmv'], 'primary'],
        ['Neu im BMV', count($vorschau['neu']), 'success'],
        ['Geändert', count($vorschau['geaendert']), 'warning'],
        ['Unverändert', $vorschau['unveraendert'], 'info'],
        ['Nur in Syncopa', count($vorschau['nicht_im_bmv']), 'secondary'],
    ];
    foreach ($kacheln as [$titel, $zahl, $farbe]): ?>
    <div class="col-6 col-md mb-2">
        <div class="card stat-card border-<?php echo $farbe; ?>"><div class="card-body"><div>
            <h6><?php echo $titel; ?></h6><h2><?php echo $zahl; ?></h2>
        </div></div></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Neu im BMV</h5></div>
    <div class="card-body">
        <p class="text-muted small">Diese Musiker gibt es nur im BMV. Beim Übernehmen werden sie in Syncopa mit dem Status „überprüfen“ angelegt.</p>
        <?php if (empty($vorschau['neu'])): ?>
        <p class="text-muted mb-0">Keine.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover table-sm">
            <thead><tr><th>Nachname</th><th>Vorname</th><th>Geburtsdatum</th><th>BMV-Status</th></tr></thead>
            <tbody>
            <?php foreach ($vorschau['neu'] as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars($m['nachname']); ?></td>
                <td><?php echo htmlspecialchars($m['vorname']); ?></td>
                <td><?php echo $m['geburtsdatum'] ? date('d.m.Y', strtotime($m['geburtsdatum'])) : '–'; ?></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($m['bmv_status']); ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Geändert im BMV</h5></div>
    <div class="card-body">
        <p class="text-muted small">Bei diesen Mitgliedern unterscheiden sich die Daten. Beim Übernehmen werden die genannten Felder mit dem BMV-Stand überschrieben.</p>
        <?php if (empty($vorschau['geaendert'])): ?>
        <p class="text-muted mb-0">Keine.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover table-sm">
            <thead><tr><th>Nachname</th><th>Vorname</th><th>Unterschiedliche Felder</th></tr></thead>
            <tbody>
            <?php foreach ($vorschau['geaendert'] as $m): ?>
            <tr>
                <td><a href="mitglied_detail.php?id=<?php echo (int)$m['lokal_id']; ?>"><?php echo htmlspecialchars($m['nachname']); ?></a></td>
                <td><?php echo htmlspecialchars($m['vorname']); ?></td>
                <td><?php foreach ($m['felder'] as $feld): ?><span class="badge bg-light text-dark border me-1"><?php echo htmlspecialchars($feld); ?></span><?php endforeach; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Nur in Syncopa</h5></div>
    <div class="card-body">
        <p class="text-muted small">Diese Mitglieder gibt es im BMV nicht. Sie werden beim Übernehmen nur markiert, nicht gelöscht.</p>
        <?php if (empty($vorschau['nicht_im_bmv'])): ?>
        <p class="text-muted mb-0">Keine.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table table-hover table-sm">
            <thead><tr><th>Mitgliedsnr.</th><th>Nachname</th><th>Vorname</th><th>Geburtsdatum</th></tr></thead>
            <tbody>
            <?php foreach ($vorschau['nicht_im_bmv'] as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars((string)$m['mitgliedsnummer']); ?></td>
                <td><a href="mitglied_detail.php?id=<?php echo (int)$m['lokal_id']; ?>"><?php echo htmlspecialchars($m['nachname']); ?></a></td>
                <td><?php echo htmlspecialchars($m['vorname']); ?></td>
                <td><?php echo $m['geburtsdatum'] ? date('d.m.Y', strtotime($m['geburtsdatum'])) : '–'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Funktionen, Ehrungen, Instrumente, Prüfungen, Mitgliedsarten</h5></div>
    <div class="card-body">
        <p class="text-muted small">
            Werden beim Übernehmen mitgeholt. Einträge, die im BMV gelöscht wurden, werden auch in Syncopa entfernt.
            Eigene Einträge aus Syncopa bleiben erhalten; gibt es denselben Eintrag auch im BMV, wird er damit verknüpft.
        </p>
        <?php $offeneUebertragungen = BmvZusatzdaten::offeneUebertragungen(); if ($offeneUebertragungen['gesamt'] > 0): ?>
        <div class="alert alert-permanent alert-secondary small py-2">
            <i class="bi bi-hdd"></i>
            <?php echo $offeneUebertragungen['gesamt']; ?> eigene Einträge sind nur in Syncopa gespeichert. Sie werden an den BMV übertragen,
            sobald das Schreiben in den BMV möglich ist.
        </div>
        <?php endif; ?>
        <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th></th><th class="text-end">Im BMV</th><th class="text-end">Neu</th><th class="text-end">Geändert</th><th class="text-end">Entfernt</th></tr></thead>
            <tbody>
            <?php foreach (BmvZusatzdaten::ARTEN as $art => $info):
                $z = $zusatzVergleich['je_art'][$art]; ?>
            <tr>
                <td class="fw-semibold"><?php echo $info['mehrzahl']; ?></td>
                <td class="text-end"><?php echo $z['bmv']; ?></td>
                <td class="text-end <?php echo $z['neu'] ? 'text-success fw-semibold' : 'text-muted'; ?>"><?php echo $z['neu']; ?></td>
                <td class="text-end <?php echo $z['geaendert'] ? 'text-warning fw-semibold' : 'text-muted'; ?>"><?php echo $z['geaendert']; ?></td>
                <td class="text-end <?php echo $z['entfernt'] ? 'text-danger fw-semibold' : 'text-muted'; ?>"><?php echo $z['entfernt']; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php $zusatzAenderungen = $zusatzVergleich['neu'] + $zusatzVergleich['geaendert'] + $zusatzVergleich['entfernt']; ?>
<form method="POST" class="d-flex justify-content-end"
      onsubmit="return confirm('Mitglieder aus dem BMV übernehmen? Neue Mitglieder werden angelegt, geänderte mit dem BMV-Stand überschrieben, Ehrungen, Funktionen usw. aktualisiert. Im BMV wird nichts geändert.');">
    <input type="hidden" name="aktion" value="mitglieder_uebernehmen">
    <button type="submit" class="btn btn-primary btn-lg"
            <?php echo (empty($vorschau['neu']) && empty($vorschau['geaendert']) && empty($vorschau['nicht_im_bmv']) && $zusatzAenderungen === 0) ? 'disabled' : ''; ?>>
        <i class="bi bi-download"></i> Mitglieder übernehmen
    </button>
</form>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
