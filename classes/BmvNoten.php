<?php
// classes/BmvNoten.php
// Noten aus dem BMV-Datenservice (/api/Noten) übernehmen.
// Syncopa ist bei den Noten führend: einmal übernehmen, danach in Syncopa pflegen. Bei erneutem Holen werden nur
// leere Felder ergänzt; Werte in Syncopa bleiben. An den BMV wird nichts zurückgeschickt.
// Die BMV-Nummer ist nicht eindeutig (mehrere Stücke teilen sich eine Nummer) – Syncopa vergibt daher eigene
// Archivnummern über den Nummernkreis und speichert die BMV-Nummer als Zusatzinfo (bmv_nummer).

class BmvNoten {

    /** Felder, die aus dem BMV ergänzt werden, wenn sie in Syncopa leer sind (Syncopa-Spalte => Anzeigename) */
    public const FELDER = [
        'untertitel'         => 'Untertitel',
        'komponist'          => 'Komponist',
        'arrangeur'          => 'Bearbeiter',
        'verlag'             => 'Verlag',
        'besetzung'          => 'Besetzung',
        'genre'              => 'Musikart',
        'schwierigkeitsgrad' => 'Schwierigkeit',
        'dauer_minuten'      => 'Spieldauer',
    ];

    /** Herkunftsangaben aus dem BMV, die bei jedem Holen aktualisiert werden (reine Info) */
    private const HERKUNFT = ['bmv_noten_id', 'bmv_nummer', 'bmv_notenmaterial', 'bmv_letzte_auffuehrung', 'bmv_anmerkung', 'bmv_aenderung'];

    /** Alle Noten des Vereins aus dem BMV, als Syncopa-Zeilen (ohne Einträge ohne Titel) */
    public static function holen(BmvApi $api): array {
        $zeilen = [];
        foreach ($api->get('/api/Noten') as $r) {
            $z = self::zeile($r);
            if ($z['titel'] !== null) {
                $zeilen[] = $z;
            }
        }
        // Reihenfolge für die Nummernvergabe: nach BMV-Nummer, dann Titel
        usort($zeilen, fn($a, $b) => strnatcmp((string)$a['bmv_nummer'], (string)$b['bmv_nummer']) ?: strcasecmp($a['titel'], $b['titel']));
        return $zeilen;
    }

    private static function zeile(array $r): array {
        $text = fn($w, $max = 150) => ($w = preg_replace('/\s+/u', ' ', trim((string)($w ?? '')))) === '' ? null : mb_substr($w, 0, $max);
        $einstufung = trim((string)($r['einstufung'] ?? ''));
        $dauer = trim((string)($r['spieldauer'] ?? ''));
        $minuten = null;
        if (preg_match('/^(\d{1,3})(?:[:.,](\d{1,2}))?/', $dauer, $m)) {
            $minuten = (int)$m[1] + (isset($m[2]) && (int)$m[2] >= 30 ? 1 : 0);
        }
        return [
            'bmv_noten_id'           => (string)$r['id'],
            'bmv_nummer'             => $text($r['sT_NR'] ?? null, 20),
            'titel'                  => $text($r['m_TITEL'] ?? null, 200),
            'untertitel'             => $text($r['untertitel'] ?? null, 200),
            'komponist'              => $text($r['komponist'] ?? null),
            'arrangeur'              => $text($r['bearbeiter'] ?? null),
            'verlag'                 => $text($r['verlag'] ?? null, 100),
            'besetzung'              => $text($r['besetzung'] ?? null, 100),
            'genre'                  => $text($r['musikart'] ?? null, 50),
            'schwierigkeitsgrad'     => preg_match('/^[1-6]$/', $einstufung) ? $einstufung : null,
            'dauer_minuten'          => $minuten ?: null,
            'bmv_notenmaterial'      => !empty($r['notenmaterial']) ? 1 : 0,
            'bmv_letzte_auffuehrung' => !empty($r['letzteAuffuehrung']) ? substr((string)$r['letzteAuffuehrung'], 0, 10) : null,
            'bmv_anmerkung'          => BmvInventar::textAusHtml((string)($r['anmerkung'] ?? '')),
            'bmv_aenderung'          => $text($r['aenderung'] ?? null, 50),
        ];
    }

    /**
     * Was beim Übernehmen mit jedem Stück passiert:
     *  'neu'       – wird mit neuer Archivnummer angelegt
     *  'zusammen'  – gleiches Stück (Titel und Komponist) ist in Syncopa schon erfasst; wird verknüpft, leere Felder ergänzt
     *  'vorhanden' – wurde schon übernommen; leere Felder werden ergänzt
     * Zusätzlich 'doppelt' = true, wenn der BMV dasselbe Stück (Titel + Komponist) mehrfach führt.
     */
    public static function vorschau(array $zeilen): array {
        $db = Database::getInstance();
        $norm = fn($s) => mb_strtolower(preg_replace('/[^\p{L}\p{N}]/u', '', (string)$s));
        $nachBmvId = [];
        $nachTitel = [];
        foreach ($db->fetchAll("SELECT * FROM noten") as $n) {
            if ($n['bmv_noten_id']) {
                $nachBmvId[$n['bmv_noten_id']] = $n;
            } else {
                $nachTitel[$norm($n['titel'])][] = $n;
            }
        }
        $imBmv = array_count_values(array_map(fn($z) => $norm($z['titel']) . '|' . $norm($z['komponist']), $zeilen));

        $vergeben = [];
        $ergebnis = [];
        foreach ($zeilen as $z) {
            $vorhanden = $nachBmvId[$z['bmv_noten_id']] ?? null;
            $aktion = $vorhanden ? 'vorhanden' : 'neu';
            if (!$vorhanden) {
                foreach ($nachTitel[$norm($z['titel'])] ?? [] as $kandidat) {
                    $kk = $norm($kandidat['komponist']);
                    if (!isset($vergeben[$kandidat['id']]) && ($kk === '' || $norm($z['komponist']) === '' || $kk === $norm($z['komponist']))) {
                        $vorhanden = $kandidat;
                        $vergeben[$kandidat['id']] = true;
                        $aktion = 'zusammen';
                        break;
                    }
                }
            }
            $ergaenzt = [];
            if ($vorhanden) {
                foreach (self::FELDER as $spalte => $name) {
                    if (($vorhanden[$spalte] === null || $vorhanden[$spalte] === '') && $z[$spalte] !== null) {
                        $ergaenzt[$spalte] = $name;
                    }
                }
            }
            $ergebnis[] = $z + [
                'aktion'    => $aktion,
                'vorhanden' => $vorhanden ? ['id' => (int)$vorhanden['id'], 'archiv_nummer' => $vorhanden['archiv_nummer'], 'titel' => $vorhanden['titel']] : null,
                'ergaenzt'  => $ergaenzt,
                'doppelt'   => $imBmv[$norm($z['titel']) . '|' . $norm($z['komponist'])] > 1,
            ];
        }
        return $ergebnis;
    }

    /** Anzahl Stücke im BMV, die noch nicht in Syncopa sind (für den Hinweis auf neue Daten) */
    public static function anzahlNeu(BmvApi $api): int {
        return count(array_filter(self::vorschau(self::holen($api)), fn($z) => $z['aktion'] === 'neu'));
    }

    /**
     * Übernimmt die Noten. $ohneMaterial: auch Stücke übernehmen, zu denen der Verein laut BMV kein Notenmaterial hat.
     * Liefert ['neu', 'zusammen', 'ergaenzt', 'unveraendert', 'uebersprungen'].
     */
    public static function uebernehmen(BmvApi $api, bool $ohneMaterial = true): array {
        $db = Database::getInstance();
        $nk = new Nummernkreis();
        $jetzt = date('Y-m-d H:i:s');
        $zaehler = ['neu' => 0, 'zusammen' => 0, 'ergaenzt' => 0, 'unveraendert' => 0, 'uebersprungen' => 0];

        $db->beginTransaction();
        try {
            foreach (self::vorschau(self::holen($api)) as $z) {
                $herkunft = array_map(fn($s) => $z[$s], self::HERKUNFT);
                if ($z['aktion'] === 'neu') {
                    if (!$ohneMaterial && !$z['bmv_notenmaterial']) {
                        $zaehler['uebersprungen']++;
                        continue;
                    }
                    $spalten = array_merge(['archiv_nummer', 'titel'], array_keys(self::FELDER), self::HERKUNFT, ['bmv_import_am']);
                    $werte = array_merge([$nk->naechsteNummer('noten'), $z['titel']],
                        array_map(fn($s) => $z[$s], array_keys(self::FELDER)), $herkunft, [$jetzt]);
                    $db->execute(
                        "INSERT INTO noten (" . implode(', ', $spalten) . ") VALUES (" . implode(', ', array_fill(0, count($spalten), '?')) . ")",
                        $werte
                    );
                    $zaehler['neu']++;
                    continue;
                }
                // Schon vorhanden: nur leere Felder ergänzen, Herkunftsangaben aktualisieren
                $set = array_map(fn($s) => "$s = ?", array_keys($z['ergaenzt']));
                $werte = array_map(fn($s) => $z[$s], array_keys($z['ergaenzt']));
                $set = array_merge($set, array_map(fn($s) => "$s = ?", self::HERKUNFT), ['bmv_import_am = ?']);
                $db->execute(
                    "UPDATE noten SET " . implode(', ', $set) . " WHERE id = ?",
                    array_merge($werte, $herkunft, [$jetzt, $z['vorhanden']['id']])
                );
                if ($z['aktion'] === 'zusammen') {
                    $zaehler['zusammen']++;
                } else {
                    $zaehler[$z['ergaenzt'] ? 'ergaenzt' : 'unveraendert']++;
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        return $zaehler;
    }
}
