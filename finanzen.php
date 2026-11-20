<?php
// finanzen.php – Kassabuch: Konten, Buchungen, Mitgliedsbeiträge, Berichte
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'lesen');

$db = Database::getInstance();
$darfSchreiben = Session::checkPermission('finanzen', 'schreiben');
$darfLoeschen  = Session::checkPermission('finanzen', 'loeschen');

$jahr      = (int)($_GET['jahr'] ?? date('Y'));
$kontoId   = (int)($_GET['konto'] ?? 0);
$typ       = in_array($_GET['typ'] ?? '', ['einnahme', 'ausgabe', 'umbuchung'], true) ? $_GET['typ'] : '';
$kategorie = (int)($_GET['kategorie'] ?? 0);
$tab       = $_GET['tab'] ?? 'buchungen';

$erstesJahr = (int)($db->fetchOne("SELECT MIN(YEAR(datum)) j FROM finanzen")['j'] ?? date('Y')) ?: (int)date('Y');
$jahre = range(max((int)date('Y') + 1, $jahr), min($erstesJahr, $jahr, (int)date('Y') - 1));

$gesperrt = Kassabuch::istGesperrt($jahr);
$konten   = Kassabuch::kontenUebersicht($jahr);
$summen   = Kassabuch::summen($jahr);
$filterKonto = $kontoId ? Kassabuch::konto($kontoId) : null;

$buchungen = Kassabuch::buchungen([
    'jahr' => $jahr, 'konto_id' => $kontoId, 'typ' => $typ, 'kategorie_id' => $kategorie,
    'formation' => Formation::getFilterCondition(Session::getFormationId(), 'f'),
]);

// Laufender Saldo, wenn ein Konto gewählt ist
$anfangKonto = $filterKonto ? Kassabuch::anfangsbestand($kontoId, $jahr) : 0;

// Mitgliedsbeiträge (Gesamtverein)
$beitraege = $db->fetchAll("SELECT b.*, m.vorname, m.nachname, m.mitgliedsnummer FROM beitraege b JOIN mitglieder m ON b.mitglied_id = m.id
                            WHERE b.jahr = ? ORDER BY b.bezahlt, m.nachname", [$jahr]);
$beitraegeOffen = count(array_filter($beitraege, fn($b) => !$b['bezahlt']));
$beitraegeSumme = array_sum(array_column($beitraege, 'betrag'));
$beitraegeBezahlt = array_sum(array_map(fn($b) => $b['bezahlt'] ? $b['betrag'] : 0, $beitraege));

$kategorien = Kassabuch::kategorien(null, false);
$artSymbol = ['kassa' => 'bi-cash-stack', 'bank' => 'bi-bank', 'sparbuch' => 'bi-piggy-bank', 'sonstig' => 'bi-wallet2'];
$url = fn(array $neu) => 'finanzen.php?' . http_build_query(array_filter(array_merge(['jahr' => $jahr, 'konto' => $kontoId ?: null, 'typ' => $typ ?: null, 'kategorie' => $kategorie ?: null, 'tab' => $tab !== 'buchungen' ? $tab : null], $neu), fn($v) => $v !== null && $v !== ''));

include 'includes/header.php';
?>
<style>
.konto-karte { cursor: pointer; transition: box-shadow .15s, border-color .15s; border-width: 1px; }
.konto-karte:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); }
.konto-karte.gewaehlt { border-color: var(--c-primary, #4471A3); box-shadow: 0 0 0 2px rgba(68,113,163,.25); }
.konto-karte .stand { font-size: 1.45rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.buchungen td { vertical-align: middle; }
.buchungen .betrag { font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }
.buchungen .unterzeile { font-size: .78rem; color: var(--text-muted, #6c757d); }
.bericht-karte { height: 100%; }
.bericht-karte .card-body { display: flex; flex-direction: column; gap: 6px; }
.bericht-karte .knoepfe { margin-top: auto; display: flex; gap: 6px; flex-wrap: wrap; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0"><i class="bi bi-wallet2"></i> Kassabuch</h1>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-calendar3"></i> <?php echo $jahr; ?><?php if ($gesperrt): ?> <i class="bi bi-lock-fill"></i><?php endif; ?></button>
            <ul class="dropdown-menu">
                <?php foreach ($jahre as $j): ?>
                <li><a class="dropdown-item <?php echo $j === $jahr ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($url(['jahr' => $j])); ?>"><?php echo $j; ?><?php echo Kassabuch::istGesperrt($j) ? ' 🔒' : ''; ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($darfSchreiben): ?>
        <a href="finanz_konten.php" class="btn btn-outline-secondary"><i class="bi bi-gear"></i> Konten &amp; Kategorien</a>
        <?php if (!$gesperrt): ?>
        <a href="transaktion_bearbeiten.php?jahr=<?php echo $jahr; ?><?php echo $kontoId ? '&konto=' . $kontoId : ''; ?>" class="btn btn-success"><i class="bi bi-plus-circle"></i> Neue Buchung</a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($gesperrt): $ab = Kassabuch::abschluss($jahr); ?>
<div class="alert alert-secondary alert-permanent d-flex align-items-center gap-2">
    <i class="bi bi-lock-fill"></i>
    <div>Das Jahr <?php echo $jahr; ?> ist abgeschlossen<?php echo $ab['abgeschlossen_am'] ? ' (seit ' . date('d.m.Y', strtotime($ab['abgeschlossen_am'])) . ')' : ''; ?>. Buchungen können nicht mehr geändert werden.</div>
    <a href="finanz_abschluss.php?jahr=<?php echo $jahr; ?>" class="ms-auto">Jahresabschluss</a>
</div>
<?php endif; ?>

<!-- Konten -->
<div class="row g-3 mb-3">
    <?php foreach ($konten as $k): $gewaehlt = (int)$k['id'] === $kontoId; ?>
    <div class="col-6 col-md-4 col-xl-3">
        <a class="text-reset text-decoration-none" href="<?php echo htmlspecialchars($url(['konto' => $gewaehlt ? null : $k['id'], 'tab' => null])); ?>">
            <div class="card konto-karte h-100 <?php echo $gewaehlt ? 'gewaehlt' : ''; ?>">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="small text-muted"><i class="bi <?php echo $artSymbol[$k['art']] ?? 'bi-wallet2'; ?>"></i> <?php echo htmlspecialchars($k['name']); ?><?php echo !$k['aktiv'] ? ' (inaktiv)' : ''; ?></div>
                        <?php if ($k['formation_name']): ?><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($k['formation_name']); ?></span><?php endif; ?>
                    </div>
                    <div class="stand <?php echo $k['ende'] < 0 ? 'text-danger' : ''; ?>"><?php echo Kassabuch::euro($k['ende']); ?></div>
                    <div class="small text-muted">Anfang <?php echo $jahr; ?>: <?php echo Kassabuch::euro($k['anfang']); ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
    <div class="col-6 col-md-4 col-xl-3">
        <div class="card h-100">
            <div class="card-body py-3">
                <div class="small text-muted"><i class="bi bi-graph-up"></i> Ergebnis <?php echo $jahr; ?></div>
                <div class="fs-4 fw-bold <?php echo $summen['ergebnis'] < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo ($summen['ergebnis'] >= 0 ? '+' : '') . Kassabuch::euro($summen['ergebnis']); ?></div>
                <div class="small text-muted">Einnahmen <?php echo Kassabuch::euro($summen['einnahmen']); ?> · Ausgaben <?php echo Kassabuch::euro($summen['ausgaben']); ?></div>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?php echo $tab === 'buchungen' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($url(['tab' => null])); ?>"><i class="bi bi-journal-text"></i> Buchungen <span class="badge bg-secondary"><?php echo count($buchungen); ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?php echo $tab === 'beitraege' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($url(['tab' => 'beitraege'])); ?>"><i class="bi bi-people"></i> Mitgliedsbeiträge<?php if ($beitraegeOffen): ?> <span class="badge bg-warning text-dark"><?php echo $beitraegeOffen; ?> offen</span><?php endif; ?></a></li>
    <li class="nav-item"><a class="nav-link <?php echo $tab === 'berichte' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($url(['tab' => 'berichte'])); ?>"><i class="bi bi-printer"></i> Berichte &amp; Abschluss</a></li>
</ul>

<?php if ($tab === 'buchungen'): ?>
<!-- Filter -->
<form method="GET" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="jahr" value="<?php echo $jahr; ?>">
    <div class="col-sm-4 col-lg-3">
        <label class="form-label small mb-1" for="f-konto">Konto</label>
        <select name="konto" id="f-konto" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Alle Konten</option>
            <?php foreach ($konten as $k): ?><option value="<?php echo $k['id']; ?>" <?php echo (int)$k['id'] === $kontoId ? 'selected' : ''; ?>><?php echo htmlspecialchars($k['name']); ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-4 col-lg-3">
        <label class="form-label small mb-1" for="f-typ">Art</label>
        <select name="typ" id="f-typ" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Alle</option>
            <?php foreach (Kassabuch::TYPEN as $tv => $tl): ?><option value="<?php echo $tv; ?>" <?php echo $typ === $tv ? 'selected' : ''; ?>><?php echo $tl; ?>en</option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-4 col-lg-3">
        <label class="form-label small mb-1" for="f-kategorie">Kategorie</label>
        <select name="kategorie" id="f-kategorie" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Alle Kategorien</option>
            <?php foreach ($kategorien as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo (int)$c['id'] === $kategorie ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php if ($kontoId || $typ || $kategorie): ?>
    <div class="col-auto"><a href="finanzen.php?jahr=<?php echo $jahr; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i> Filter zurücksetzen</a></div>
    <?php endif; ?>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 buchungen">
            <thead class="table-light">
                <tr>
                    <th style="width:100px">Datum</th>
                    <th style="width:110px">Beleg</th>
                    <th>Buchungstext</th>
                    <?php if (!$filterKonto): ?><th>Konto</th><?php endif; ?>
                    <th class="text-end">Betrag</th>
                    <?php if ($filterKonto): ?><th class="text-end">Saldo</th><?php endif; ?>
                    <th class="text-end" style="width:110px"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($filterKonto && !$typ && !$kategorie): ?>
                <tr class="table-light">
                    <td><?php echo "01.01.$jahr"; ?></td><td></td><td colspan="2"><strong>Anfangsbestand</strong></td>
                    <td class="betrag fw-bold"><?php echo Kassabuch::euro($anfangKonto); ?></td><td></td>
                </tr>
            <?php endif; ?>
            <?php $saldo = $anfangKonto; foreach ($buchungen as $b):
                $betrag = $filterKonto ? Kassabuch::betragFuerKonto($b, $kontoId) : ($b['typ'] === 'ausgabe' ? -$b['betrag'] : (float)$b['betrag']);
                $saldo += $betrag;
            ?>
                <tr>
                    <td><?php echo date('d.m.Y', strtotime($b['datum'])); ?></td>
                    <td class="small text-nowrap"><?php echo htmlspecialchars($b['beleg_nummer'] ?? '–'); ?></td>
                    <td>
                        <?php echo htmlspecialchars($b['beschreibung'] ?: Kassabuch::TYPEN[$b['typ']]); ?>
                        <div class="unterzeile">
                            <?php if ($b['typ'] === 'umbuchung'): ?>
                            <span class="badge bg-secondary">Umbuchung</span> <?php echo htmlspecialchars($b['konto_name'] . ' → ' . $b['gegenkonto_name']); ?>
                            <?php else: ?>
                            <?php echo htmlspecialchars($b['kategorie_name'] ?? $b['kategorie'] ?? 'Ohne Kategorie'); ?>
                            <?php endif; ?>
                            <?php if ($b['beitrag_id']): ?> · <i class="bi bi-person-check"></i> Mitgliedsbeitrag<?php endif; ?>
                            <?php if ($b['fest_id']): ?> · <i class="bi bi-stars"></i> Fest-Abrechnung<?php endif; ?>
                        </div>
                    </td>
                    <?php if (!$filterKonto): ?><td class="small"><?php echo htmlspecialchars($b['typ'] === 'umbuchung' ? $b['konto_name'] . ' → ' . $b['gegenkonto_name'] : ($b['konto_name'] ?? '–')); ?></td><?php endif; ?>
                    <td class="betrag <?php echo $b['typ'] === 'umbuchung' && !$filterKonto ? 'text-muted' : ($betrag < 0 ? 'text-danger' : 'text-success'); ?>">
                        <?php echo $b['typ'] === 'umbuchung' && !$filterKonto ? Kassabuch::euro((float)$b['betrag']) : (($betrag >= 0 ? '+' : '−') . ' ' . Kassabuch::euro(abs($betrag))); ?>
                    </td>
                    <?php if ($filterKonto): ?><td class="betrag <?php echo $saldo < 0 ? 'text-danger' : ''; ?>"><?php echo Kassabuch::euro($saldo); ?></td><?php endif; ?>
                    <td class="text-end text-nowrap">
                        <?php if ($b['beleg_datei']): ?>
                        <a href="finanz_beleg.php?id=<?php echo $b['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Beleg ansehen: <?php echo htmlspecialchars($b['beleg_name'] ?? ''); ?>"><i class="bi bi-paperclip"></i></a>
                        <?php endif; ?>
                        <?php if ($darfSchreiben && !$gesperrt): ?>
                        <a href="transaktion_bearbeiten.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-primary" title="Bearbeiten"><i class="bi bi-pencil"></i></a>
                        <?php endif; ?>
                        <?php if ($darfLoeschen && !$gesperrt && !$b['beitrag_id']): ?>
                        <form method="POST" action="transaktion_loeschen.php" class="d-inline" onsubmit="return confirm('Buchung <?php echo htmlspecialchars(addslashes($b['beleg_nummer'] ?? '')); ?> wirklich löschen?')">
                            <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Löschen"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$buchungen): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Keine Buchungen<?php echo ($kontoId || $typ || $kategorie) ? ' für diese Auswahl' : ' in ' . $jahr; ?>.</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($filterKonto && !$typ && !$kategorie): ?>
            <tfoot><tr class="table-light"><td><?php echo $jahr == date('Y') ? date('d.m.Y') : "31.12.$jahr"; ?></td><td></td><td colspan="2"><strong>Endbestand</strong></td>
                <td class="betrag fw-bold"><?php echo Kassabuch::euro($saldo); ?></td><td></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php elseif ($tab === 'beitraege'): ?>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Mitgliedsbeiträge <?php echo $jahr; ?></h5>
        <div class="d-flex align-items-center gap-3">
            <span><strong>Bezahlt:</strong> <?php echo Kassabuch::euro($beitraegeBezahlt); ?> / <?php echo Kassabuch::euro($beitraegeSumme); ?></span>
            <?php if ($darfSchreiben): ?><a href="beitraege_verwalten.php?jahr=<?php echo $jahr; ?>" class="btn btn-sm btn-primary"><i class="bi bi-currency-euro"></i> Beiträge verwalten</a><?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <p class="small text-muted"><i class="bi bi-info-circle"></i> Wird ein Beitrag als bezahlt markiert, bucht Syncopa ihn automatisch als Einnahme ins Kassabuch (bar in die Kassa, sonst aufs Bankkonto).</p>
        <table class="table table-hover" id="beitraegeTable">
            <thead><tr><th>Mitglied</th><th>Mitgliedsnr.</th><th>Betrag</th><th>Status</th><th>Bezahlt am</th></tr></thead>
            <tbody>
            <?php foreach ($beitraege as $b): ?>
                <tr>
                    <td><?php echo htmlspecialchars($b['nachname'] . ' ' . $b['vorname']); ?></td>
                    <td><?php echo htmlspecialchars($b['mitgliedsnummer']); ?></td>
                    <td><?php echo Kassabuch::euro((float)$b['betrag']); ?></td>
                    <td><?php echo $b['bezahlt'] ? '<span class="badge bg-success">Bezahlt</span>' : '<span class="badge bg-warning text-dark">Offen</span>'; ?></td>
                    <td><?php echo $b['bezahlt_am'] ? date('d.m.Y', strtotime($b['bezahlt_am'])) : '–'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<?php
$berichte = [
    ['journal', 'bi-journal-text', 'Kassabuch (Journal)', 'Alle Buchungen je Konto mit Belegnummer, laufendem Saldo, Anfangs- und Endbestand.', 'Kassaprüfung'],
    ['ea', 'bi-bar-chart-steps', 'Einnahmen-Ausgaben-Rechnung', 'Summen nach Kategorien mit Vorjahresvergleich.', 'Jahreshauptversammlung, Prüfer'],
    ['vermoegen', 'bi-bank', 'Vermögensübersicht', 'Kontostände am Jahresanfang und -ende, offene Beiträge, Inventarwert.', 'Jahreshauptversammlung, Prüfer'],
    ['pruefung', 'bi-patch-check', 'Kassaprüfungsbericht', 'Vorlage für die Rechnungsprüfer mit Beständen, Feststellungen und Unterschriften.', 'Rechnungsprüfer'],
    ['jhv', 'bi-easel', 'Kassabericht', 'Kompakter Bericht mit Grafik und Vorjahresvergleich.', 'Jahreshauptversammlung'],
    ['belege', 'bi-receipt', 'Belegliste', 'Alle Belegnummern, fehlende Nummern und Buchungen ohne Belegdatei.', 'Kassaprüfung'],
    ['beitraege', 'bi-people', 'Beitragsliste', 'Bezahlte und offene Mitgliedsbeiträge.', 'Kassier, Obmann'],
    ['budget', 'bi-clipboard-data', 'Voranschlag / Soll-Ist', 'Budget je Kategorie im Vergleich mit dem Ergebnis.', 'Jahreshauptversammlung'],
];
?>
<div class="row g-3 mb-4">
    <?php foreach ($berichte as [$art, $icon, $titel, $text, $fuer]): ?>
    <div class="col-md-6 col-xl-3">
        <div class="card bericht-karte">
            <div class="card-body">
                <h6 class="mb-0"><i class="bi <?php echo $icon; ?>"></i> <?php echo $titel; ?></h6>
                <div class="small text-muted"><?php echo $text; ?></div>
                <div class="small"><span class="badge bg-light text-dark border"><?php echo $fuer; ?></span></div>
                <div class="knoepfe">
                    <a href="finanzen_bericht.php?art=<?php echo $art; ?>&jahr=<?php echo $jahr; ?>" target="_blank" class="btn btn-sm btn-primary"><i class="bi bi-printer"></i> Öffnen / Drucken</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<div class="row g-3">
    <div class="col-md-4">
        <a href="finanz_abschluss.php?jahr=<?php echo $jahr; ?>" class="card text-reset text-decoration-none h-100"><div class="card-body">
            <h6><i class="bi bi-lock"></i> Jahresabschluss <?php echo $jahr; ?></h6>
            <div class="small text-muted">Kassaprüfer und Feststellungen eintragen, das Jahr nach der Prüfung abschließen.</div>
            <div class="mt-2"><?php echo $gesperrt ? '<span class="badge bg-secondary"><i class="bi bi-lock-fill"></i> abgeschlossen</span>' : '<span class="badge bg-success">offen</span>'; ?></div>
        </div></a>
    </div>
    <div class="col-md-4">
        <a href="finanz_kassasturz.php" class="card text-reset text-decoration-none h-100"><div class="card-body">
            <h6><i class="bi bi-coin"></i> Kassasturz</h6>
            <div class="small text-muted">Bargeld nach Scheinen und Münzen zählen und mit dem Kassastand vergleichen. Protokoll zum Ausdrucken.</div>
        </div></a>
    </div>
    <div class="col-md-4">
        <a href="finanz_budget.php?jahr=<?php echo $jahr; ?>" class="card text-reset text-decoration-none h-100"><div class="card-body">
            <h6><i class="bi bi-clipboard-data"></i> Voranschlag <?php echo $jahr; ?></h6>
            <div class="small text-muted">Budget je Kategorie planen, z. B. für den Beschluss in der Jahreshauptversammlung.</div>
        </div></a>
    </div>
    <div class="col-12">
        <a href="finanzen_export.php?jahr=<?php echo $jahr; ?>" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv"></i> Alle Buchungen <?php echo $jahr; ?> als CSV (Excel)</a>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
<script>
$(function () {
    if ($('#beitraegeTable').length) $('#beitraegeTable').DataTable({ order: [[3, 'asc'], [0, 'asc']] });
});
</script>
