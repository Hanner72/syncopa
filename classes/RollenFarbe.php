<?php
// classes/RollenFarbe.php
// Frei wählbare Farben für Rollen-Badges: Hex-Wert in rollen.farbe, Schriftfarbe automatisch (Kontrast),
// Vorschläge aus einer Palette, die sich von den schon vergebenen Farben deutlich unterscheiden.

class RollenFarbe {

    /** Frühere Bootstrap-Farbnamen → Hex (Farbtöne des Syncopa-Designs) */
    public const ALT = [
        'primary'   => '#4471a3',
        'secondary' => '#8699ac',
        'success'   => '#5b8a72',
        'danger'    => '#f44336',
        'warning'   => '#d19a3e',
        'info'      => '#77bad7',
        'dark'      => '#343a40',
        'purple'    => '#7e57c2',
        'light'     => '#cfd8dc',
    ];

    /** Palette für Vorschläge */
    public const PALETTE = [
        '#4471a3', '#5b8a72', '#d19a3e', '#f44336', '#77bad7', '#7e57c2', '#8699ac', '#343a40',
        '#2a9d8f', '#e76f51', '#c2185b', '#3f51b5', '#689f38', '#00838f', '#f4a261', '#6d4c41',
        '#ab47bc', '#546e7a', '#0288d1', '#afb42b', '#8d6e63', '#e9c46a', '#d81b60', '#26a69a',
    ];

    /** Gespeicherten Wert in einen Hex-Wert umwandeln (alte Namen, ungültige Werte → grau) */
    public static function hex(?string $farbe): string {
        $f = strtolower(trim((string)$farbe));
        if (isset(self::ALT[$f])) return self::ALT[$f];
        if (preg_match('/^#[0-9a-f]{6}$/', $f)) return $f;
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $f, $m)) return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        return self::ALT['secondary'];
    }

    /** Eingabe aus dem Formular prüfen: gültiger Hex-Wert oder null */
    public static function pruefen(?string $farbe): ?string {
        $f = strtolower(trim((string)$farbe));
        if (preg_match('/^#?([0-9a-f]{6})$/', $f, $m)) return '#' . $m[1];
        return null;
    }

    private static function rgb(string $hex): array {
        $hex = ltrim(self::hex($hex), '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function luminanz(string $hex): float {
        $l = array_map(function ($c) {
            $c /= 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));
        return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
    }

    /** Weiße Schrift, solange sie gut lesbar ist (Kontrast ≥ 3), sonst Dunkelgrau */
    public static function textfarbe(?string $farbe): string {
        $kontrastWeiss = 1.05 / (self::luminanz(self::hex($farbe)) + 0.05);
        return $kontrastWeiss >= 3 ? '#ffffff' : '#1f2937';
    }

    /** style-Attribut-Inhalt für ein Badge */
    public static function stil(?string $farbe): string {
        return 'background-color:' . self::hex($farbe) . ';color:' . self::textfarbe($farbe);
    }

    /** Farbabstand (gewichtetes RGB, „redmean“) */
    public static function abstand(string $a, string $b): float {
        [$r1, $g1, $b1] = self::rgb($a); [$r2, $g2, $b2] = self::rgb($b);
        $rm = ($r1 + $r2) / 2;
        return sqrt((2 + $rm / 256) * ($r1 - $r2) ** 2 + 4 * ($g1 - $g2) ** 2 + (2 + (255 - $rm) / 256) * ($b1 - $b2) ** 2);
    }

    /**
     * Vorschläge: Palettenfarben, die sich von allen vergebenen Farben deutlich unterscheiden,
     * die unterschiedlichsten zuerst.
     */
    public static function vorschlaege(array $vergeben, int $anzahl = 10): array {
        $vergeben = array_map([self::class, 'hex'], $vergeben);
        $liste = [];
        foreach (self::PALETTE as $p) {
            $min = PHP_FLOAT_MAX;
            foreach ($vergeben as $v) $min = min($min, self::abstand($p, $v));
            if ($min > 90) $liste[$p] = $min;
        }
        arsort($liste);
        return array_slice(array_keys($liste), 0, $anzahl);
    }
}
