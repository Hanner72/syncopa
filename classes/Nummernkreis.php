<?php
/**
 * Nummernkreis.php
 * Zentrale Klasse zur automatischen, fortlaufenden Nummerngenerierung.
 * Präfix-Platzhalter: Y = 4-stelliges Jahr, y = 2-stelliges Jahr
 */
class Nummernkreis
{
    /** @var Database */
    private $db;

    /** Tabelle  → Spalte mit der Nummer */
    private const MAPPING = [
        'mitglieder'  => ['tabelle' => 'mitglieder',  'spalte' => 'mitgliedsnummer'],
        'noten'       => ['tabelle' => 'noten',        'spalte' => 'archiv_nummer'],
        'instrumente' => ['tabelle' => 'instrumente',  'spalte' => 'inventar_nummer'],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gibt die nächste freie Nummer für den gewünschten Typ zurück.
     *
     * @param  string $typ  'mitglieder' | 'noten' | 'instrumente' | 'uniformen'
     * @return string       fertige Nummer, z.B. "M042" oder "I260007"
     */
    public function naechsteNummer(string $typ): string
    {
        $config = $this->ladeConfig($typ);
        $prefix  = $this->resolvePrefix($config['prefix']);
        $stellen = (int) $config['stellen'];

        $naechste = $this->berechneNaechste($typ, $prefix, $stellen);

        return $prefix . str_pad((string)$naechste, $stellen, '0', STR_PAD_LEFT);
    }

    // ----------------------------------------------------------------- //

    /** Lädt Prefix + Stellen aus der einstellungen-Tabelle */
    private function ladeConfig(string $typ): array
    {
        $defaults = [
            'mitglieder'  => ['prefix' => 'M',  'stellen' => 3],
            'noten'       => ['prefix' => 'N',  'stellen' => 4],
            'instrumente' => ['prefix' => 'Iy', 'stellen' => 3],
        ];

        $pRow = $this->db->fetchOne(
            "SELECT wert FROM einstellungen WHERE schluessel = ?",
            ['nummernkreis_' . $typ . '_prefix']
        );
        $sRow = $this->db->fetchOne(
            "SELECT wert FROM einstellungen WHERE schluessel = ?",
            ['nummernkreis_' . $typ . '_stellen']
        );

        return [
            'prefix'  => $pRow ? $pRow['wert'] : $defaults[$typ]['prefix'],
            'stellen' => $sRow ? (int)$sRow['wert']  : $defaults[$typ]['stellen'],
        ];
    }

    /** Ersetzt Y → aktuelles Jahr 4-stellig, y → 2-stellig */
    private function resolvePrefix(string $prefix): string
    {
        $prefix = str_replace('Y', date('Y'), $prefix);
        $prefix = str_replace('y', date('y'), $prefix);
        return $prefix;
    }

    /**
     * Ermittelt den nächsten freien Zähler.
     * Sucht alle bestehenden Nummern die mit dem aktuellen Prefix beginnen,
     * extrahiert den numerischen Suffix und nimmt max() + 1.
     */
    private function berechneNaechste(string $typ, string $resolvedPrefix, int $stellen): int
    {
        $map    = self::MAPPING[$typ];
        $tabelle = $map['tabelle'];
        $spalte  = $map['spalte'];

        // Alle Nummern laden, die mit dem aktuellen Prefix beginnen
        $rows = $this->db->fetchAll(
            "SELECT {$spalte} FROM {$tabelle}
             WHERE {$spalte} LIKE ?
             ORDER BY {$spalte} DESC",
            [$resolvedPrefix . '%']
        );

        $maxZahl = 0;
        foreach ($rows as $row) {
            $nr     = $row[$spalte];
            $suffix = substr($nr, strlen($resolvedPrefix));  // numerischer Teil
            if (ctype_digit($suffix)) {
                $zahl = (int)$suffix;
                if ($zahl > $maxZahl) {
                    $maxZahl = $zahl;
                }
            }
        }

        return $maxZahl + 1;
    }
}
