<?php
// includes/bmv_bezeichnungen_standard.php
// Standardnamen zu den Nummern, die der BMV-Datenservice liefert (Funktionen, Instrumente, Prüfungen, Mitgliedsarten,
// Ehrenzeichen). Der BMV gibt die Namenslisten (Setup*) für Vereinszugänge nicht frei, daher stehen sie hier.
// Es sind nur Vorschläge: Was ein Verein unter „BMV Daten holen → Bezeichnungen“ selbst einträgt, hat Vorrang.
// Ehrenzeichen sind Sache des Landesverbands und gelten nur für das jeweilige BMV-Land (Einstellung bmv_land).
// Stand: 05.11.2026, erfasst vom MV Palfau (ST-16-013).

return [
    // Für alle Landesverbände
    'alle' => [
        // Funktionen
        'funktion' => [
              4 => 'Beirat',
             16 => 'Instrumentenwart',
             17 => 'Jugendreferent',
             19 => 'Kapellmeister',
             20 => 'Kapellmeister Stv.',
             24 => 'Marketenderin',
             29 => 'Notenwart',
             30 => 'Obmann',
             31 => 'Obmann Stv.',
             34 => 'Schriftführer',
             35 => 'Schriftführer Stv.',
             36 => 'Stabführer',
             37 => 'Stabführer Stv.',
             44 => 'Kassier/Finanzreferent',
             45 => 'Kassier/Finanzreferent Stv.',
             67 => 'Bekleidungswart',
        ],
        // Instrumente
        'instrument' => [
              1 => 'Althorn',
              2 => 'Bassflügelhorn',
              4 => 'B-Tuba',
              8 => 'Flügelhorn',
              9 => 'F-Tuba',
             10 => 'Glockenspiel',
             11 => 'Klarinette B',
             12 => 'Es-Klarinette',
             17 => 'Querflöte',
             18 => 'Saxophon Alt',
             23 => 'Schlagzeug',
             24 => 'Schlagzeug Groß',
             27 => 'Trompete',
             28 => 'Ventilposaune',
             29 => 'Waldhorn',
             30 => 'Zugposaune',
             31 => 'B u. Es-Trompete',
             32 => 'Es-Trompete',
             34 => 'Es-Horn',
             35 => 'F-Horn',
             37 => 'Tenorhorn',
             38 => 'Bariton',
             54 => 'Baßposaune',
             56 => 'C-Tuba',
        ],
        // Prüfungen
        'pruefung' => [
              6 => 'Dirigieren Landesverband',
             21 => 'ÖBV-Junior-Leistungsabzeichen',
             22 => 'Stabführerprüfung',
             25 => 'ÖBV-Leistungsabzeichen in Bronze',
             26 => 'Jungmusiker-Leistungsabzeichen in Silber',
             27 => 'Jungmusiker-Leistungsabzeichen in Gold',
             31 => 'Stimmführerkurs I (Landesverband)',
             36 => 'Stabführerkurs',
             40 => 'ÖBV-Leistungsabzeichen-Theorie Junior',
             41 => 'ÖBV-Leistungsabzeichen-Theorie Bronze',
             42 => 'ÖBV-Leistungsabzeichen-Theorie Silber',
             43 => 'ÖBV-Leistungsabzeichen-Theorie Gold',
        ],
        // Mitgliedsarten
        'mitgliedsart' => [
              1 => 'aktives Mitglied',
              3 => 'unterstützendes Mitglied',
              5 => 'MusikschülerIn',
        ],
    ],

    // Je Landesverband (BMV-Land)
    'land' => [
        'ST' => [
            // Ehrenzeichen
            'ehrung' => [
                 15 => 'Ehrennadel in Gold',
                 18 => 'Ehrennadel in Silber',
                 30 => 'Ehrenzeichen Bronze (10 Jahre, alt)',
                 31 => 'Ehrenzeichen Silber (15 Jahre, alt)',
                 32 => 'Ehrenzeichen Silber-Gold (25 Jahre, alt)',
                 33 => 'Ehrenzeichen Gold (40 Jahre, alt)',
                102 => 'Ehrenzeichen Bronze (10 Jahre)',
                103 => 'Ehrenzeichen Silber (15 Jahre)',
                105 => 'Ehrenzeichen Silber-Gold (25 Jahre)',
                106 => 'Ehrenzeichen 30 Jahre Silber-Gold',
                107 => 'Ehrenzeichen Silber-Gold (30 Jahre)',
                108 => 'Ehrenzeichen 35 Jahre Gold (alt)',
                109 => 'Ehrenzeichen Gold (40 Jahre)',
                110 => 'Ehrenzeichen Gold (50 Jahre)',
                114 => 'Förderernadel in Gold',
                115 => 'Förderernadel in Silber',
                119 => 'Verdienstkreuz in Bronze am Band',
                120 => 'Verdienstkreuz in Gold am Band',
                122 => 'Verdienstkreuz in Silber am Band',
            ],
        ],
    ],
];
