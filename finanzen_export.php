<?php
// finanzen_export.php – Alle Buchungen eines Jahres als CSV (Excel-tauglich: Semikolon, UTF-8 mit BOM, Komma als Dezimalzeichen)
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
Session::requirePermission('finanzen', 'lesen');

$jahr = (int)($_GET['jahr'] ?? date('Y'));
$zahl = fn($b) => number_format((float)$b, 2, ',', '');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="kassabuch_' . $jahr . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Datum', 'Belegnummer', 'Art', 'Konto', 'Gegenkonto', 'Kategorie', 'Buchungstext', 'Einnahme', 'Ausgabe', 'Zahlungsart', 'Beleg-Datei'], ';');
foreach (Kassabuch::buchungen(['jahr' => $jahr]) as $b) {
    fputcsv($out, [
        date('d.m.Y', strtotime($b['datum'])), $b['beleg_nummer'], Kassabuch::TYPEN[$b['typ']] ?? $b['typ'], $b['konto_name'], $b['gegenkonto_name'],
        $b['kategorie_name'] ?? $b['kategorie'], $b['beschreibung'],
        $b['typ'] === 'einnahme' ? $zahl($b['betrag']) : '', $b['typ'] === 'ausgabe' ? $zahl($b['betrag']) : ($b['typ'] === 'umbuchung' ? $zahl($b['betrag']) : ''),
        $b['zahlungsart'], $b['beleg_name'] ?? '',
    ], ';');
}
fclose($out);
