<?php
// classes/BmvMitgliederSync.php
// Abgleich der Vereinsmitglieder mit dem BMV-Datenservice (Musiker-Felder, 1:1 zum BMV)

class BmvMitgliederSync {

    /**
     * Zuordnung lokale Spalte => [BMV-Feld, Typ, änderbar in Syncopa]
     * Nicht änderbare Felder werden vom BMV übernommen, aber nicht zurückgeschrieben.
     */
    public const ZUORDNUNG = [
        'bmv_id'            => ['m_NR', 'text', false],
        'bmv_status'        => ['status', 'text', false],
        'vorname'           => ['vorname', 'text', true],
        'nachname'          => ['zuname', 'text', true],
        'geburtsdatum'      => ['geB_DAT', 'datum', true],
        'geschlecht'        => ['geschl', 'geschlecht', true],
        'strasse'           => ['strasse', 'text', true],
        'plz'               => ['pltz', 'text', true],
        'ort'               => ['ort', 'text', true],
        'email'             => ['email1', 'text', true],
        'telefon'           => ['teL_NR1', 'text', true],
        'mobil'             => ['teL_NR', 'text', true],
        'bmv_anrede'        => ['anrede', 'text', true],
        'bmv_titel'         => ['titel', 'text', true],
        'bmv_titeln'        => ['titeln', 'text', true],
        'bmv_telefon2'      => ['teL_NR2', 'text', true],
        'bmv_fax'           => ['fax', 'text', true],
        'bmv_telefon_firma' => ['tel_Firma', 'text', true],
        'bmv_email2'        => ['email2', 'text', true],
        'bmv_beruf'         => ['beruf', 'text', true],
        'bmv_firma'         => ['firma', 'text', true],
        'bmv_bemerkung'     => ['bemerkung', 'text', true],
        'bmv_kategorien'    => ['kategorien', 'text', true],
        'bmv_exp_telnr1'    => ['exp_Telnr1', 'bool', true],
        'bmv_exp_telnr2'    => ['exp_Telnr2', 'bool', true],
        'bmv_exp_email1'    => ['exp_email1', 'bool', true],
        'bmv_exp_email2'    => ['exp_email2', 'bool', true],
        'bmv_exp_adresse'   => ['exp_Adresse', 'bool', true],
        'bmv_exp_internet'  => ['exp_Internet', 'bool', true],
        'bmv_foto'          => ['foto', 'text', true],
        'bmv_aenderung'     => ['änderung', 'text', false],
        'bmv_pcanmeldename' => ['pcanmeldename', 'text', false],
        'bmv_pcipadresse'   => ['pcipadresse', 'text', false],
        'bmv_verein_id'     => ['verein_id', 'int', false],
    ];

    /** Lokale Spalten, die in Syncopa geändert und an den BMV zurückgeschrieben werden können */
    public static function aenderbareSpalten(): array {
        return array_keys(array_filter(self::ZUORDNUNG, fn($z) => $z[2]));
    }

    /** Alle lokalen Spalten, die aus dem BMV befüllt werden */
    public static function alleSpalten(): array {
        return array_keys(self::ZUORDNUNG);
    }

    /** Alle Musiker, die der BMV-Datenservice für den Login liefert (inkl. anderer Vereine und ohne Vereinsnummer). */
    public static function alleMusiker(BmvApi $api): array {
        return $api->get('/api/Musiker');
    }

    /** Schlüssel für den Abgleich ohne BMV-ID: Vorname | Nachname | Geburtsdatum */
    public static function schluessel(?string $vorname, ?string $nachname, ?string $geburtsdatum): string {
        return mb_strtolower(trim((string)$vorname)) . '|' . mb_strtolower(trim((string)$nachname)) . '|'
            . substr(trim((string)$geburtsdatum), 0, 10);
    }

    /** BMV-Geburtsdatum (z.B. 1969-07-15T00:00:00) als Y-m-d */
    public static function bmvDatum(?string $wert): string {
        return substr(trim((string)$wert), 0, 10);
    }

    /**
     * Vergleicht die BMV-Musiker mit den lokalen Mitgliedern.
     * Zuordnung: zuerst über die BMV-ID, sonst über Name + Geburtsdatum.
     * Liefert nur Anzeigedaten, es wird nichts geschrieben.
     */
    public static function vorschau(BmvApi $api): array {
        $bmvMusiker = self::alleMusiker($api);
        $lokal = Database::getInstance()->fetchAll(
            "SELECT id, mitgliedsnummer, vorname, nachname, geburtsdatum, bmv_id FROM mitglieder"
        );

        $lokalNachId = [];
        $lokalNachSchluessel = [];
        foreach ($lokal as $l) {
            if (!empty($l['bmv_id'])) {
                $lokalNachId[$l['bmv_id']] = $l;
            }
            $lokalNachSchluessel[self::schluessel($l['vorname'], $l['nachname'], $l['geburtsdatum'])][] = $l;
        }

        $neu = [];
        $aktualisiert = [];
        $zugeordnet = [];
        foreach ($bmvMusiker as $m) {
            $bmvId = (string)($m['m_NR'] ?? '');
            $bmvSchluessel = self::schluessel($m['vorname'] ?? '', $m['zuname'] ?? '', self::bmvDatum($m['geB_DAT'] ?? ''));
            $eintrag = [
                'bmv_id'     => $bmvId,
                'vorname'    => trim((string)($m['vorname'] ?? '')),
                'nachname'   => trim((string)($m['zuname'] ?? '')),
                'geburtsdatum' => self::bmvDatum($m['geB_DAT'] ?? ''),
                'bmv_status' => (string)($m['status'] ?? ''),
            ];

            if (isset($lokalNachId[$bmvId])) {
                $lokalId = $lokalNachId[$bmvId]['id'];
            } elseif (!empty($lokalNachSchluessel[$bmvSchluessel])) {
                $kandidaten = array_filter($lokalNachSchluessel[$bmvSchluessel], fn($l) => empty($l['bmv_id']));
                $lokalId = $kandidaten ? reset($kandidaten)['id'] : null;
            } else {
                $lokalId = null;
            }

            if ($lokalId === null) {
                $neu[] = $eintrag;
            } else {
                $zugeordnet[$lokalId] = true;
                $aktualisiert[] = $eintrag + ['lokal_id' => $lokalId];
            }
        }

        $nichtImBmv = [];
        foreach ($lokal as $l) {
            if (empty($zugeordnet[$l['id']]) && empty($l['bmv_id'])) {
                $nichtImBmv[] = [
                    'lokal_id'        => $l['id'],
                    'mitgliedsnummer' => $l['mitgliedsnummer'],
                    'vorname'         => $l['vorname'],
                    'nachname'        => $l['nachname'],
                    'geburtsdatum'    => $l['geburtsdatum'],
                ];
            }
        }

        return [
            'anzahl_bmv'   => count($bmvMusiker),
            'neu'          => $neu,
            'aktualisiert' => $aktualisiert,
            'nicht_im_bmv' => $nichtImBmv,
        ];
    }

    /** BMV-Wert in den lokalen Datentyp umwandeln */
    private static function ausBmv(string $typ, $wert) {
        switch ($typ) {
            case 'datum':
                $d = substr(trim((string)$wert), 0, 10);
                return $d !== '' ? $d : null;
            case 'bool':
                return $wert ? 1 : 0;
            case 'int':
                return ($wert === null || $wert === '') ? null : (int)$wert;
            case 'geschlecht':
                $g = strtolower(trim((string)$wert));
                return in_array($g, ['m', 'w'], true) ? $g : 'd';
            default:
                $t = trim((string)($wert ?? ''));
                return $t === '' ? null : $t;
        }
    }

    /** Lokalen Wert in das BMV-Format umwandeln */
    private static function fuerBmv(string $typ, $wert) {
        switch ($typ) {
            case 'datum':
                return ($wert === null || $wert === '') ? null : substr((string)$wert, 0, 10) . 'T00:00:00';
            case 'bool':
                return (bool)$wert;
            case 'int':
                return ($wert === null || $wert === '') ? null : (int)$wert;
            case 'geschlecht':
                return in_array($wert, ['m', 'w'], true) ? strtoupper($wert) : null;
            default:
                $t = trim((string)($wert ?? ''));
                return $t === '' ? null : $t;
        }
    }

    /** Vergleichswert für die Änderungserkennung */
    private static function vergleichswert(string $typ, $wert): string {
        return (string)self::ausBmv($typ, $wert === null ? null : (string)$wert);
    }

    /** Übersetzt einen BMV-Musiker in die lokalen Spalten. */
    public static function felderAusBmv(array $m): array {
        $felder = [];
        foreach (self::ZUORDNUNG as $lokal => [$bmv, $typ]) {
            $felder[$lokal] = self::ausBmv($typ, $m[$bmv] ?? null);
        }
        $felder['vorname'] = $felder['vorname'] ?? '';
        $felder['nachname'] = $felder['nachname'] ?? '';
        return $felder;
    }

    /**
     * Ermittelt die BMV-Felder, die sich zwischen altem und neuem Stand geändert haben.
     * Nur änderbare Felder. Geschlecht „d" wird nicht übertragen, weil der BMV nur M und W kennt.
     */
    public static function aenderungenZuBmv(array $alt, array $neu): array {
        $aenderungen = [];
        foreach (self::ZUORDNUNG as $lokal => [$bmv, $typ, $aenderbar]) {
            if (!$aenderbar || !array_key_exists($lokal, $neu)) continue;
            if (self::vergleichswert($typ, $alt[$lokal] ?? null) === self::vergleichswert($typ, $neu[$lokal])) continue;
            if ($typ === 'geschlecht' && !in_array($neu[$lokal], ['m', 'w'], true)) continue;
            $aenderungen[$bmv] = self::fuerBmv($typ, $neu[$lokal]);
        }
        return $aenderungen;
    }

    /**
     * Wandelt eine hochgeladene Bilddatei in das BMV-Fotoformat um:
     * quadratisch zugeschnitten, 128×128 Pixel, PNG, Base64-kodiert (ohne data:-Präfix).
     */
    public static function fotoAusDatei(string $pfad): string {
        $maxBytes = 5 * 1024 * 1024;
        if (!is_file($pfad)) {
            throw new RuntimeException('Das Foto konnte nicht hochgeladen werden.');
        }
        if (filesize($pfad) > $maxBytes) {
            throw new RuntimeException('Das Foto ist zu groß (maximal 5 MB).');
        }
        $info = @getimagesize($pfad);
        if ($info === false) {
            throw new RuntimeException('Die Datei ist kein lesbares Bild.');
        }
        $quelle = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($pfad),
            IMAGETYPE_PNG  => @imagecreatefrompng($pfad),
            IMAGETYPE_GIF  => @imagecreatefromgif($pfad),
            IMAGETYPE_WEBP => @imagecreatefromwebp($pfad),
            default        => false,
        };
        if ($quelle === false) {
            throw new RuntimeException('Bildformat nicht unterstützt (JPG, PNG, GIF oder WebP).');
        }

        [$breite, $hoehe] = [imagesx($quelle), imagesy($quelle)];
        $seite = min($breite, $hoehe);
        $ziel = imagecreatetruecolor(128, 128);
        imagealphablending($ziel, false);
        imagesavealpha($ziel, true);
        imagecopyresampled($ziel, $quelle, 0, 0, intdiv($breite - $seite, 2), intdiv($hoehe - $seite, 2), 128, 128, $seite, $seite);

        ob_start();
        imagepng($ziel);
        $png = ob_get_clean();
        imagedestroy($quelle);
        imagedestroy($ziel);
        return base64_encode($png);
    }

    /** Alle änderbaren Felder eines Mitglieds in das BMV-Format (für eine vollständige Übertragung). */
    public static function komplettZuBmv(array $neu): array {
        $daten = [];
        foreach (self::ZUORDNUNG as $lokal => [$bmv, $typ, $aenderbar]) {
            if (!$aenderbar || !array_key_exists($lokal, $neu)) continue;
            if ($typ === 'geschlecht' && !in_array($neu[$lokal], ['m', 'w'], true)) continue;
            $daten[$bmv] = self::fuerBmv($typ, $neu[$lokal]);
        }
        return $daten;
    }

    /**
     * Übernimmt die Vorschau in die lokale Datenbank. Schreibt nichts in den BMV.
     * Nicht im BMV vorhandene Mitglieder werden nur mit einem Zeitstempel markiert (bmv_id bleibt leer).
     */
    public static function uebernehmen(BmvApi $api): array {
        $vorschau = self::vorschau($api);
        $bmvNachId = [];
        foreach (self::alleMusiker($api) as $m) {
            $bmvNachId[(string)$m['m_NR']] = $m;
        }

        $db = Database::getInstance();
        $jetzt = date('Y-m-d H:i:s');
        $spalten = self::alleSpalten();
        $db->beginTransaction();
        try {
            foreach ($vorschau['neu'] as $eintrag) {
                $f = self::felderAusBmv($bmvNachId[$eintrag['bmv_id']]);
                $nummer = (new Nummernkreis())->naechsteNummer('mitglieder');
                $spaltenListe = array_merge(['mitgliedsnummer'], $spalten, ['bmv_abgeglichen_am', 'status']);
                $werte = array_merge([$nummer], array_map(fn($c) => $f[$c], $spalten), [$jetzt, 'ueberpruefen']);
                $db->execute(
                    "INSERT INTO mitglieder (" . implode(', ', $spaltenListe) . ") VALUES ("
                        . implode(', ', array_fill(0, count($spaltenListe), '?')) . ")",
                    $werte
                );
            }

            foreach ($vorschau['aktualisiert'] as $eintrag) {
                $f = self::felderAusBmv($bmvNachId[$eintrag['bmv_id']]);
                $set = implode(', ', array_map(fn($c) => "$c = ?", $spalten)) . ', bmv_abgeglichen_am = ?, bmv_nicht_uebertragen = 0';
                $db->execute(
                    "UPDATE mitglieder SET $set WHERE id = ?",
                    array_merge(array_map(fn($c) => $f[$c], $spalten), [$jetzt, $eintrag['lokal_id']])
                );
            }

            foreach ($vorschau['nicht_im_bmv'] as $eintrag) {
                $db->execute(
                    "UPDATE mitglieder SET bmv_abgeglichen_am = ? WHERE id = ? AND bmv_id IS NULL",
                    [$jetzt, $eintrag['lokal_id']]
                );
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        return [
            'neu'          => count($vorschau['neu']),
            'aktualisiert' => count($vorschau['aktualisiert']),
            'nicht_im_bmv' => count($vorschau['nicht_im_bmv']),
        ];
    }
}
