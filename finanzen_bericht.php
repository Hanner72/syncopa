<?php
// finanzen_bericht.php – Druckfertige Berichte des Kassabuchs (A4):
// journal, ea (Einnahmen-Ausgaben-Rechnung), vermoegen, pruefung, jhv (Kassabericht), belege, beitraege, budget, kassasturz
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'lesen');

$db   = Database::getInstance();
$art  = $_GET['art'] ?? 'journal';
$jahr = (int)($_GET['jahr'] ?? date('Y'));
$kopf = Kassabuch::vereinskopf();
$e    = fn($s) => htmlspecialchars((string)$s);
$eur  = fn($b) => Kassabuch::euro((float)$b);
$titel = [
    'journal' => 'Kassabuch ' . $jahr, 'ea' => 'Einnahmen-Ausgaben-Rechnung ' . $jahr, 'vermoegen' => 'Vermögensübersicht ' . $jahr,
    'pruefung' => 'Bericht der Rechnungsprüfer ' . $jahr, 'jhv' => 'Kassabericht ' . $jahr, 'belege' => 'Belegliste ' . $jahr,
    'beitraege' => 'Mitgliedsbeiträge ' . $jahr, 'budget' => 'Voranschlag ' . $jahr, 'kassasturz' => 'Kassasturz-Protokoll',
][$art] ?? 'Bericht';

function unterschriften(array $wer): string {
    $h = '<div class="unterschriften">';
    foreach ($wer as $w) $h .= '<div><div class="linie"></div>' . htmlspecialchars($w) . '</div>';
    return $h . '</div>';
}
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $e($titel . ' – ' . $kopf['name']); ?></title>
<style>
    @page { size: A4; margin: 16mm 14mm 16mm 16mm; }
    * { box-sizing: border-box; }
    body { font-family: "Segoe UI", Arial, sans-serif; font-size: 10.5pt; color: #1d2733; margin: 0; background: #e9edf2; }
    .blatt { background: #fff; max-width: 210mm; margin: 16px auto; padding: 16mm 14mm 18mm 16mm; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
    .leiste { max-width: 210mm; margin: 12px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
    .leiste button, .leiste a { font: 600 13px "Segoe UI", Arial; padding: 8px 14px; border-radius: 6px; border: 1px solid #4471A3; background: #4471A3; color: #fff; text-decoration: none; cursor: pointer; }
    .leiste a { background: #fff; color: #4471A3; }
    header.kopf { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #4471A3; padding-bottom: 8px; margin-bottom: 14px; }
    header.kopf .verein { font-size: 13pt; font-weight: 700; }
    header.kopf .klein { font-size: 8.5pt; color: #5b6878; }
    h1 { font-size: 17pt; margin: 0 0 4px; }
    h2 { font-size: 12pt; margin: 18px 0 6px; color: #2d4f78; }
    .untertitel { color: #5b6878; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { padding: 4px 6px; border-bottom: 1px solid #dfe5ec; text-align: left; vertical-align: top; }
    th { font-size: 8.5pt; text-transform: uppercase; letter-spacing: .04em; color: #5b6878; border-bottom: 1.5px solid #9fb0c4; }
    td.b, th.b { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    tr.summe td { font-weight: 700; border-top: 1.5px solid #1d2733; border-bottom: none; }
    tr.zwischen td { font-weight: 600; background: #f2f5f9; }
    tr.gruppe td { font-weight: 700; background: #e6edf5; }
    .klein { font-size: 8.5pt; color: #5b6878; }
    .minus { color: #b42318; } .plus { color: #1e7a45; }
    .kasten { border: 1px solid #c9d3df; border-radius: 6px; padding: 10px 12px; margin: 10px 0; }
    .zahlen { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 10px 0 16px; }
    .zahlen div { border: 1px solid #c9d3df; border-radius: 6px; padding: 10px; }
    .zahlen b { display: block; font-size: 15pt; margin-top: 2px; }
    .balken { display: grid; grid-template-columns: 38% 1fr 90px; gap: 4px 8px; align-items: center; font-size: 9.5pt; }
    .balken .bar { height: 12px; border-radius: 3px; background: #4471A3; }
    .balken .bar.aus { background: #d0705f; }
    .unterschriften { display: flex; gap: 40px; margin-top: 46px; page-break-inside: avoid; }
    .unterschriften > div { flex: 1; font-size: 9pt; color: #5b6878; text-align: center; }
    .unterschriften .linie { border-bottom: 1px solid #1d2733; height: 34px; margin-bottom: 4px; }
    .ort { margin-top: 30px; }
    .warnung { color: #b42318; font-weight: 600; }
    .nr { white-space: nowrap; }
    .seitenumbruch { page-break-before: always; }
    footer { margin-top: 18px; font-size: 8pt; color: #8693a3; display: flex; justify-content: space-between; }
    @media print {
        body { background: #fff; }
        .blatt { box-shadow: none; margin: 0; padding: 0; max-width: none; }
        .leiste { display: none; }
        tr { page-break-inside: avoid; }
    }
</style>
</head>
<body>
<div class="leiste">
    <a href="finanzen.php?jahr=<?php echo $jahr; ?>&tab=berichte">Zurück zum Kassabuch</a>
    <button onclick="window.print()">Drucken / als PDF speichern</button>
</div>
<div class="blatt">
<header class="kopf">
    <div>
        <div class="verein"><?php echo $e($kopf['name']); ?></div>
        <div class="klein"><?php echo $e(implode(' · ', array_filter([$kopf['adresse'], $kopf['zvr'] ? 'ZVR ' . $kopf['zvr'] : '', $kopf['kontakt']]))); ?></div>
    </div>
    <div class="klein">erstellt am <?php echo date('d.m.Y'); ?></div>
</header>
<h1><?php echo $e($titel); ?></h1>
<?php
// ======================================================================== JOURNAL
if ($art === 'journal'):
    $konten = Kassabuch::kontenUebersicht($jahr);
    echo '<div class="untertitel">Rechnungsjahr 1.1.–31.12.' . $jahr . ' · alle Buchungen je Konto mit laufendem Saldo</div>';
    foreach ($konten as $i => $k):
        $buch = Kassabuch::buchungen(['jahr' => $jahr, 'konto_id' => $k['id']]);
        $saldo = $k['anfang']; ?>
    <h2 class="<?php echo $i ? 'seitenumbruch' : ''; ?>"><?php echo $e($k['name']); ?> <span class="klein">(<?php echo $e(Kassabuch::ARTEN[$k['art']] . ($k['iban'] ? ', ' . $k['iban'] : '')); ?>)</span></h2>
    <table>
        <thead><tr><th style="width:62px">Datum</th><th style="width:80px">Beleg</th><th>Buchungstext / Kategorie</th><th class="b">Einnahme</th><th class="b">Ausgabe</th><th class="b">Saldo</th></tr></thead>
        <tbody>
        <tr class="zwischen"><td>01.01.</td><td></td><td>Anfangsbestand</td><td></td><td></td><td class="b"><?php echo $eur($saldo); ?></td></tr>
        <?php foreach ($buch as $b): $betrag = Kassabuch::betragFuerKonto($b, (int)$k['id']); $saldo += $betrag; ?>
        <tr>
            <td><?php echo date('d.m.', strtotime($b['datum'])); ?></td>
            <td class="nr"><?php echo $e($b['beleg_nummer']); ?></td>
            <td><?php echo $e($b['beschreibung'] ?: Kassabuch::TYPEN[$b['typ']]); ?>
                <div class="klein"><?php echo $b['typ'] === 'umbuchung' ? 'Umbuchung ' . $e($b['konto_name'] . ' → ' . $b['gegenkonto_name']) : $e($b['kategorie_name'] ?? 'ohne Kategorie'); ?></div></td>
            <td class="b"><?php echo $betrag > 0 ? $eur($betrag) : ''; ?></td>
            <td class="b"><?php echo $betrag < 0 ? $eur(-$betrag) : ''; ?></td>
            <td class="b"><?php echo $eur($saldo); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="summe"><td>31.12.</td><td></td><td>Endbestand · <?php echo count($buch); ?> Buchungen</td>
            <td class="b"><?php echo $eur($k['einnahmen'] + $k['umbuchung_ein']); ?></td><td class="b"><?php echo $eur($k['ausgaben'] + $k['umbuchung_aus']); ?></td><td class="b"><?php echo $eur($k['ende']); ?></td></tr>
        </tbody>
    </table>
    <?php endforeach;
    if (!$konten) echo '<p>Keine Konten vorhanden.</p>';
    echo unterschriften(['Kassier/in']);

// ======================================================================== EINNAHMEN-AUSGABEN-RECHNUNG
elseif ($art === 'ea'):
    $ist = Kassabuch::kategorieSummen($jahr);
    $vj  = Kassabuch::kategorieSummen($jahr - 1);
    $s = Kassabuch::summen($jahr); $sv = Kassabuch::summen($jahr - 1);
    echo '<div class="untertitel">Rechnungsjahr 1.1.–31.12.' . $jahr . ' · gemäß § 21 Vereinsgesetz 2002 · Umbuchungen zwischen eigenen Konten sind nicht enthalten</div>';
    foreach (['einnahmen' => 'Einnahmen', 'ausgaben' => 'Ausgaben'] as $feld => $label):
        $ids = array_unique(array_merge(array_keys(array_filter($ist, fn($x) => $x[$feld] != 0)), array_keys(array_filter($vj, fn($x) => $x[$feld] != 0)))); ?>
    <h2><?php echo $label; ?></h2>
    <table>
        <thead><tr><th>Kategorie</th><th class="b"><?php echo $jahr; ?></th><th class="b"><?php echo $jahr - 1; ?></th><th class="b">Veränderung</th></tr></thead>
        <tbody>
        <?php foreach ($ids as $id): $a = $ist[$id][$feld] ?? 0; $b = $vj[$id][$feld] ?? 0; ?>
        <tr><td><?php echo $e($ist[$id]['name'] ?? $vj[$id]['name']); ?></td><td class="b"><?php echo $eur($a); ?></td><td class="b klein"><?php echo $eur($b); ?></td><td class="b klein"><?php echo ($a - $b >= 0 ? '+' : '') . $eur($a - $b); ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$ids): ?><tr><td colspan="4" class="klein">keine</td></tr><?php endif; ?>
        <tr class="summe"><td>Summe <?php echo $label; ?></td><td class="b"><?php echo $eur($s[$feld]); ?></td><td class="b"><?php echo $eur($sv[$feld]); ?></td><td class="b"><?php echo ($s[$feld] - $sv[$feld] >= 0 ? '+' : '') . $eur($s[$feld] - $sv[$feld]); ?></td></tr>
        </tbody>
    </table>
    <?php endforeach; ?>
    <table>
        <tr class="summe"><td>Jahresergebnis (<?php echo $s['ergebnis'] >= 0 ? 'Überschuss' : 'Fehlbetrag'; ?>)</td>
            <td class="b <?php echo $s['ergebnis'] < 0 ? 'minus' : 'plus'; ?>"><?php echo $eur($s['ergebnis']); ?></td><td class="b"><?php echo $eur($sv['ergebnis']); ?></td><td class="b"></td></tr>
    </table>
    <?php echo unterschriften(['Kassier/in', 'Obmann / Obfrau']);

// ======================================================================== VERMÖGENSÜBERSICHT
elseif ($art === 'vermoegen'):
    $konten = Kassabuch::kontenUebersicht($jahr);
    $anfang = array_sum(array_column($konten, 'anfang')); $ende = array_sum(array_column($konten, 'ende'));
    $s = Kassabuch::summen($jahr);
    $offen = $db->fetchOne("SELECT COUNT(*) n, COALESCE(SUM(betrag),0) s FROM beitraege WHERE jahr <= ? AND bezahlt = 0", [$jahr]);
    $inventar = $db->fetchOne("SELECT COUNT(*) n, COALESCE(SUM(anschaffungspreis),0) a, COALESCE(SUM(versicherungswert),0) v FROM instrumente");
    echo '<div class="untertitel">Stand 31.12.' . $jahr . ' im Vergleich zum 1.1.' . $jahr . '</div>'; ?>
    <h2>Geldbestände</h2>
    <table>
        <thead><tr><th>Konto</th><th>Art</th><th class="b">1.1.<?php echo $jahr; ?></th><th class="b">31.12.<?php echo $jahr; ?></th><th class="b">Veränderung</th></tr></thead>
        <tbody>
        <?php foreach ($konten as $k): ?>
        <tr><td><?php echo $e($k['name']); ?></td><td class="klein"><?php echo $e(Kassabuch::ARTEN[$k['art']]); ?></td><td class="b"><?php echo $eur($k['anfang']); ?></td><td class="b"><?php echo $eur($k['ende']); ?></td><td class="b"><?php echo ($k['ende'] - $k['anfang'] >= 0 ? '+' : '') . $eur($k['ende'] - $k['anfang']); ?></td></tr>
        <?php endforeach; ?>
        <tr class="summe"><td colspan="2">Summe Geldbestände</td><td class="b"><?php echo $eur($anfang); ?></td><td class="b"><?php echo $eur($ende); ?></td><td class="b"><?php echo ($ende - $anfang >= 0 ? '+' : '') . $eur($ende - $anfang); ?></td></tr>
        </tbody>
    </table>
    <div class="kasten klein">Abstimmung: Veränderung der Geldbestände <?php echo $eur($ende - $anfang); ?> = Jahresergebnis laut Einnahmen-Ausgaben-Rechnung <?php echo $eur($s['ergebnis']); ?>
        <?php echo abs(($ende - $anfang) - $s['ergebnis']) < 0.01 ? ' ✓ stimmt überein' : ' <span class="warnung">– Differenz ' . $eur(($ende - $anfang) - $s['ergebnis']) . ', bitte Anfangsbestände prüfen</span>'; ?></div>
    <h2>Forderungen und weitere Vermögenswerte</h2>
    <table>
        <tbody>
        <tr><td>Offene Mitgliedsbeiträge (bis <?php echo $jahr; ?>, <?php echo (int)$offen['n']; ?> Beiträge)</td><td class="b"><?php echo $eur($offen['s']); ?></td></tr>
        <tr><td>Instrumente laut Inventar (<?php echo (int)$inventar['n']; ?> Stück, Anschaffungswert)<div class="klein">Versicherungswert: <?php echo $eur($inventar['v']); ?> · nur zur Information</div></td><td class="b"><?php echo $eur($inventar['a']); ?></td></tr>
        </tbody>
    </table>
    <h2>Verbindlichkeiten</h2>
    <div class="kasten klein" style="min-height:60px">Offene Rechnungen, Darlehen o. Ä. zum 31.12.<?php echo $jahr; ?>:</div>
    <?php echo unterschriften(['Kassier/in', 'Obmann / Obfrau']);

// ======================================================================== PRÜFBERICHT
elseif ($art === 'pruefung'):
    $ab = Kassabuch::abschluss($jahr);
    $konten = Kassabuch::kontenUebersicht($jahr);
    $s = Kassabuch::summen($jahr);
    $p1 = $ab['pruefer1'] ?: '____________________'; $p2 = $ab['pruefer2'] ?: '____________________'; ?>
    <div class="untertitel">Prüfung des Rechnungsjahres 1.1.–31.12.<?php echo $jahr; ?> gemäß § 21 Abs. 2–5 Vereinsgesetz 2002</div>
    <p>Die unterzeichneten Rechnungsprüfer <strong><?php echo $e($p1); ?></strong> und <strong><?php echo $e($p2); ?></strong> haben am
       <strong><?php echo $ab['pruef_datum'] ? date('d.m.Y', strtotime($ab['pruef_datum'])) : '____________'; ?></strong> die Finanzgebarung des Vereins
       <?php echo $e($kopf['name']); ?> für das Rechnungsjahr <?php echo $jahr; ?> geprüft. Geprüft wurden das Kassabuch, die Belege und die Kontostände.</p>
    <h2>Geprüfte Bestände zum 31.12.<?php echo $jahr; ?></h2>
    <table>
        <thead><tr><th>Konto</th><th class="b">laut Kassabuch</th><th>bestätigt durch</th></tr></thead>
        <tbody>
        <?php foreach ($konten as $k): ?><tr><td><?php echo $e($k['name']); ?></td><td class="b"><?php echo $eur($k['ende']); ?></td><td class="klein"><?php echo $k['art'] === 'kassa' ? '☐ Kassasturz' : '☐ Kontoauszug'; ?></td></tr><?php endforeach; ?>
        </tbody>
    </table>
    <table>
        <tr><td>Einnahmen</td><td class="b"><?php echo $eur($s['einnahmen']); ?></td></tr>
        <tr><td>Ausgaben</td><td class="b"><?php echo $eur($s['ausgaben']); ?></td></tr>
        <tr class="summe"><td>Jahresergebnis</td><td class="b"><?php echo $eur($s['ergebnis']); ?></td></tr>
        <tr><td class="klein">Anzahl Buchungen</td><td class="b klein"><?php echo $s['anzahl']; ?></td></tr>
    </table>
    <h2>Feststellungen</h2>
    <div class="kasten" style="min-height:80px"><?php echo $ab['feststellungen'] ? nl2br($e($ab['feststellungen'])) : '<span class="klein">☐ Die Buchungen sind vollständig und durch Belege nachgewiesen. ☐ Die Mittel wurden statutengemäß verwendet. ☐ Es gab keine Beanstandungen.</span>'; ?></div>
    <h2>Ergebnis</h2>
    <p>Die Rechnungsprüfer bestätigen die Ordnungsmäßigkeit der Rechnungslegung und die statutengemäße Verwendung der Mittel.
       Sie beantragen bei der Mitgliederversammlung die <strong>Entlastung des Kassiers und des Vorstandes</strong> für das Rechnungsjahr <?php echo $jahr; ?>.</p>
    <div class="ort">Ort, Datum: ______________________________</div>
    <?php echo unterschriften(['Rechnungsprüfer/in: ' . ($ab['pruefer1'] ?: ''), 'Rechnungsprüfer/in: ' . ($ab['pruefer2'] ?: '')]);

// ======================================================================== KASSABERICHT JHV
elseif ($art === 'jhv'):
    $s = Kassabuch::summen($jahr); $sv = Kassabuch::summen($jahr - 1);
    $kat = Kassabuch::kategorieSummen($jahr);
    $konten = Kassabuch::kontenUebersicht($jahr);
    $top = function ($feld) use ($kat) { $l = array_filter($kat, fn($x) => $x[$feld] > 0); uasort($l, fn($a, $b) => $b[$feld] <=> $a[$feld]); return array_slice($l, 0, 8, true); };
    $max = max(1, ...array_map(fn($x) => max($x['einnahmen'], $x['ausgaben']), $kat ?: [['einnahmen' => 1, 'ausgaben' => 1]])); ?>
    <div class="untertitel">für die Jahreshauptversammlung · Rechnungsjahr <?php echo $jahr; ?></div>
    <div class="zahlen">
        <div>Einnahmen<b class="plus"><?php echo $eur($s['einnahmen']); ?></b><span class="klein">Vorjahr <?php echo $eur($sv['einnahmen']); ?></span></div>
        <div>Ausgaben<b class="minus"><?php echo $eur($s['ausgaben']); ?></b><span class="klein">Vorjahr <?php echo $eur($sv['ausgaben']); ?></span></div>
        <div>Ergebnis<b class="<?php echo $s['ergebnis'] < 0 ? 'minus' : 'plus'; ?>"><?php echo $eur($s['ergebnis']); ?></b><span class="klein">Vorjahr <?php echo $eur($sv['ergebnis']); ?></span></div>
    </div>
    <?php foreach (['einnahmen' => 'Woher kam das Geld?', 'ausgaben' => 'Wofür wurde es ausgegeben?'] as $feld => $frage): ?>
    <h2><?php echo $frage; ?></h2>
    <div class="balken">
        <?php foreach ($top($feld) as $x): ?>
        <div><?php echo $e($x['name']); ?></div><div><div class="bar <?php echo $feld === 'ausgaben' ? 'aus' : ''; ?>" style="width:<?php echo max(1, round($x[$feld] / $max * 100)); ?>%"></div></div><div class="b" style="text-align:right"><?php echo $eur($x[$feld]); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <h2>Kontostände</h2>
    <table>
        <thead><tr><th>Konto</th><th class="b">1.1.</th><th class="b">31.12.</th></tr></thead>
        <tbody><?php foreach ($konten as $k): ?><tr><td><?php echo $e($k['name']); ?></td><td class="b"><?php echo $eur($k['anfang']); ?></td><td class="b"><?php echo $eur($k['ende']); ?></td></tr><?php endforeach; ?>
        <tr class="summe"><td>Summe</td><td class="b"><?php echo $eur(array_sum(array_column($konten, 'anfang'))); ?></td><td class="b"><?php echo $eur(array_sum(array_column($konten, 'ende'))); ?></td></tr></tbody>
    </table>
    <?php echo unterschriften(['Kassier/in']);

// ======================================================================== BELEGLISTE
elseif ($art === 'belege'):
    $buch = Kassabuch::buchungen(['jahr' => $jahr]);
    usort($buch, fn($a, $b) => strnatcmp((string)$a['beleg_nummer'], (string)$b['beleg_nummer']));
    // Lücken in der Nummerierung je Präfix finden
    $gruppen = [];
    foreach ($buch as $b) if (preg_match('/^(.*?)(\d+)$/', (string)$b['beleg_nummer'], $m)) $gruppen[$m[1]][(int)$m[2]] = strlen($m[2]);
    $luecken = [];
    foreach ($gruppen as $p => $nr) { ksort($nr); $stellen = reset($nr); for ($i = min(array_keys($nr)); $i <= max(array_keys($nr)); $i++) if (!isset($nr[$i])) $luecken[] = $p . str_pad((string)$i, $stellen, '0', STR_PAD_LEFT); }
    $ohneNr = count(array_filter($buch, fn($b) => trim((string)$b['beleg_nummer']) === ''));
    $ohneDatei = count(array_filter($buch, fn($b) => !$b['beleg_datei'])); ?>
    <div class="untertitel"><?php echo count($buch); ?> Buchungen · <?php echo $ohneDatei; ?> ohne Belegdatei · <?php echo $ohneNr; ?> ohne Belegnummer · <?php echo count($luecken); ?> fehlende Nummern</div>
    <?php if ($luecken): ?><div class="kasten"><strong>Fehlende Belegnummern:</strong> <span class="warnung"><?php echo $e(implode(', ', array_slice($luecken, 0, 80))); ?><?php echo count($luecken) > 80 ? ' …' : ''; ?></span></div><?php endif; ?>
    <table>
        <thead><tr><th style="width:84px">Beleg</th><th style="width:62px">Datum</th><th>Buchungstext</th><th>Konto</th><th class="b">Betrag</th><th style="width:60px">Datei</th><th style="width:44px">geprüft</th></tr></thead>
        <tbody>
        <?php foreach ($buch as $b): ?>
        <tr>
            <td class="nr"><?php echo $b['beleg_nummer'] ? $e($b['beleg_nummer']) : '<span class="warnung">fehlt</span>'; ?></td>
            <td><?php echo date('d.m.', strtotime($b['datum'])); ?></td>
            <td><?php echo $e($b['beschreibung'] ?: Kassabuch::TYPEN[$b['typ']]); ?><div class="klein"><?php echo $e($b['typ'] === 'umbuchung' ? 'Umbuchung' : ($b['kategorie_name'] ?? '')); ?></div></td>
            <td class="klein"><?php echo $e($b['typ'] === 'umbuchung' ? $b['konto_name'] . ' → ' . $b['gegenkonto_name'] : $b['konto_name']); ?></td>
            <td class="b <?php echo $b['typ'] === 'ausgabe' ? 'minus' : ''; ?>"><?php echo ($b['typ'] === 'ausgabe' ? '− ' : '') . $eur($b['betrag']); ?></td>
            <td class="klein"><?php echo $b['beleg_datei'] ? 'ja' : '<span class="warnung">nein</span>'; ?></td>
            <td>☐</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php // ======================================================================== BEITRÄGE
elseif ($art === 'beitraege'):
    $liste = $db->fetchAll("SELECT b.*, m.vorname, m.nachname, m.mitgliedsnummer FROM beitraege b JOIN mitglieder m ON m.id = b.mitglied_id WHERE b.jahr = ? ORDER BY b.bezahlt, m.nachname, m.vorname", [$jahr]);
    $bez = array_filter($liste, fn($b) => $b['bezahlt']); ?>
    <div class="untertitel"><?php echo count($liste); ?> Beiträge · bezahlt <?php echo count($bez); ?> (<?php echo $eur(array_sum(array_column($bez, 'betrag'))); ?>) · offen <?php echo count($liste) - count($bez); ?> (<?php echo $eur(array_sum(array_column($liste, 'betrag')) - array_sum(array_column($bez, 'betrag'))); ?>)</div>
    <table>
        <thead><tr><th>Mitglied</th><th>Nr.</th><th class="b">Betrag</th><th>Status</th><th>bezahlt am</th><th>Zahlungsart</th></tr></thead>
        <tbody>
        <?php foreach ($liste as $b): ?>
        <tr><td><?php echo $e($b['nachname'] . ' ' . $b['vorname']); ?></td><td class="klein"><?php echo $e($b['mitgliedsnummer']); ?></td><td class="b"><?php echo $eur($b['betrag']); ?></td>
            <td><?php echo $b['bezahlt'] ? 'bezahlt' : '<span class="warnung">offen</span>'; ?></td><td><?php echo $b['bezahlt_am'] ? date('d.m.Y', strtotime($b['bezahlt_am'])) : ''; ?></td><td class="klein"><?php echo $e($b['zahlungsart'] ?? ''); ?></td></tr>
        <?php endforeach; ?>
        <tr class="summe"><td colspan="2">Summe</td><td class="b"><?php echo $eur(array_sum(array_column($liste, 'betrag'))); ?></td><td colspan="3"></td></tr>
        </tbody>
    </table>

<?php elseif ($art === 'budget'):
    $budget = Kassabuch::budget($jahr); $ist = Kassabuch::kategorieSummen($jahr); $vj = Kassabuch::kategorieSummen($jahr - 1);
    $kat = Kassabuch::kategorien(null, true); $summe = ['einnahme' => [0, 0, 0], 'ausgabe' => [0, 0, 0]]; ?>
    <div class="untertitel">Voranschlag je Kategorie mit Ist-Werten <?php echo $jahr - 1; ?> und <?php echo $jahr; ?></div>
    <table>
        <thead><tr><th>Kategorie</th><th class="b">Ist <?php echo $jahr - 1; ?></th><th class="b">Voranschlag <?php echo $jahr; ?></th><th class="b">Ist <?php echo $jahr; ?></th></tr></thead>
        <tbody>
        <?php foreach (['einnahme' => 'Einnahmen', 'ausgabe' => 'Ausgaben'] as $gt => $gl): ?>
            <tr class="gruppe"><td colspan="4"><?php echo $gl; ?></td></tr>
            <?php foreach ($kat as $c): if ($c['typ'] !== $gt && !($c['typ'] === 'beide' && $gt === 'einnahme')) continue;
                $w = fn($s) => $s ? ($c['typ'] === 'beide' ? $s['einnahmen'] - $s['ausgaben'] : $s[$gt === 'einnahme' ? 'einnahmen' : 'ausgaben']) : 0;
                $a = $w($vj[(int)$c['id']] ?? null); $bu = $budget[(int)$c['id']] ?? 0; $i = $w($ist[(int)$c['id']] ?? null);
                if (!$a && !$bu && !$i) continue;
                $summe[$gt][0] += $a; $summe[$gt][1] += $bu; $summe[$gt][2] += $i; ?>
            <tr><td><?php echo $e($c['name']); ?></td><td class="b klein"><?php echo $eur($a); ?></td><td class="b"><?php echo $eur($bu); ?></td><td class="b"><?php echo $eur($i); ?></td></tr>
            <?php endforeach; ?>
            <tr class="zwischen"><td>Summe <?php echo $gl; ?></td><td class="b"><?php echo $eur($summe[$gt][0]); ?></td><td class="b"><?php echo $eur($summe[$gt][1]); ?></td><td class="b"><?php echo $eur($summe[$gt][2]); ?></td></tr>
        <?php endforeach; ?>
        <tr class="summe"><td>Ergebnis</td><td class="b"><?php echo $eur($summe['einnahme'][0] - $summe['ausgabe'][0]); ?></td><td class="b"><?php echo $eur($summe['einnahme'][1] - $summe['ausgabe'][1]); ?></td><td class="b"><?php echo $eur($summe['einnahme'][2] - $summe['ausgabe'][2]); ?></td></tr>
        </tbody>
    </table>
    <?php echo unterschriften(['Kassier/in', 'Obmann / Obfrau']);

// ======================================================================== KASSASTURZ
elseif ($art === 'kassasturz'):
    $p = !empty($_GET['id']) ? $db->fetchOne("SELECT s.*, k.name konto_name FROM finanz_kassasturz s JOIN finanz_konten k ON k.id = s.konto_id WHERE s.id = ?", [(int)$_GET['id']]) : null;
    $konto = $p ? Kassabuch::konto((int)$p['konto_id']) : Kassabuch::konto((int)($_GET['konto'] ?? 0));
    $zaehlung = $p ? (json_decode((string)$p['zaehlung'], true) ?: []) : [];
    $soll = $p ? (float)$p['soll_betrag'] : ($konto ? Kassabuch::stand((int)$konto['id'], date('Y-m-d')) : 0); ?>
    <div class="untertitel"><?php echo $e($konto['name'] ?? 'Kassa'); ?> · <?php echo $p ? 'gezählt am ' . date('d.m.Y \u\m H:i', strtotime($p['datum'])) . ($p['gezaehlt_von'] ? ' von ' . $e($p['gezaehlt_von']) : '') : 'Datum: ______________  Uhrzeit: ________'; ?></div>
    <table style="max-width:120mm">
        <thead><tr><th>Wert</th><th class="b">Anzahl</th><th class="b">Summe</th></tr></thead>
        <tbody>
        <?php foreach (Kassabuch::STUECKELUNG as $w): $n = $zaehlung[(string)$w] ?? null; ?>
        <tr><td><?php echo $w >= 1 ? (int)$w . ' €' : (int)round($w * 100) . ' Cent'; ?></td><td class="b"><?php echo $p ? (int)$n : '______'; ?></td><td class="b"><?php echo $p ? $eur(($n ?? 0) * $w) : '__________'; ?></td></tr>
        <?php endforeach; ?>
        <tr class="summe"><td colspan="2">Gezählt (Ist)</td><td class="b"><?php echo $p ? $eur($p['ist_betrag']) : '__________'; ?></td></tr>
        <tr><td colspan="2">Laut Kassabuch (Soll)</td><td class="b"><?php echo $eur($soll); ?></td></tr>
        <tr class="summe"><td colspan="2">Differenz</td><td class="b"><?php echo $p ? $eur($p['ist_betrag'] - $p['soll_betrag']) : '__________'; ?></td></tr>
        </tbody>
    </table>
    <?php if ($p && $p['bemerkung']): ?><div class="kasten"><?php echo nl2br($e($p['bemerkung'])); ?></div><?php endif; ?>
    <?php echo unterschriften(['Kassier/in', 'Kassaprüfer/in']);
endif; ?>
<footer><span><?php echo $e($kopf['name']); ?> · <?php echo $e($titel); ?></span><span>Syncopa</span></footer>
</div>
</body>
</html>
