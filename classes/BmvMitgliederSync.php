<?php
// classes/BmvMitgliederSync.php
// Mitglieder aus dem BMV-Datenservice holen (Musiker-Felder, 1:1 zum BMV). Nur lesend, im BMV wird nichts geändert.

class BmvMitgliederSync {

    /**
     * Zuordnung lokale Spalte => [BMV-Feld, Typ, in Syncopa bearbeitbar, Anzeigename]
     * Bearbeitbare Felder kann man in Syncopa ändern (nur lokal). Beim nächsten Holen aus dem BMV
     * werden sie wieder mit dem BMV-Stand überschrieben.
     */
    public const ZUORDNUNG = [
        'bmv_id'            => ['m_NR', 'text', false, 'BMV-ID'],
        'bmv_status'        => ['status', 'text', false, 'BMV-Status'],
        'vorname'           => ['vorname', 'text', true, 'Vorname'],
        'nachname'          => ['zuname', 'text', true, 'Nachname'],
        'geburtsdatum'      => ['geB_DAT', 'datum', true, 'Geburtsdatum'],
        'geschlecht'        => ['geschl', 'geschlecht', true, 'Geschlecht'],
        'strasse'           => ['strasse', 'text', true, 'Straße'],
        'plz'               => ['pltz', 'text', true, 'PLZ'],
        'ort'               => ['ort', 'text', true, 'Ort'],
        'email'             => ['email1', 'text', true, 'E-Mail'],
        'telefon'           => ['teL_NR1', 'text', true, 'Telefon'],
        'mobil'             => ['teL_NR', 'text', true, 'Mobil'],
        'bmv_anrede'        => ['anrede', 'text', true, 'Anrede'],
        'bmv_titel'         => ['titel', 'text', true, 'Titel'],
        'bmv_titeln'        => ['titeln', 'text', true, 'Titel nachgestellt'],
        'bmv_telefon2'      => ['teL_NR2', 'text', true, 'Telefon 2'],
        'bmv_fax'           => ['fax', 'text', true, 'Fax'],
        'bmv_telefon_firma' => ['tel_Firma', 'text', true, 'Telefon Firma'],
        'bmv_email2'        => ['email2', 'text', true, 'E-Mail 2'],
        'bmv_beruf'         => ['beruf', 'text', true, 'Beruf'],
        'bmv_firma'         => ['firma', 'text', true, 'Firma'],
        'bmv_bemerkung'     => ['bemerkung', 'text', true, 'Bemerkung'],
        'bmv_kategorien'    => ['kategorien', 'text', true, 'Kategorien'],
        'bmv_exp_telnr1'    => ['exp_Telnr1', 'bool', true, 'Freigabe Telefon 1'],
        'bmv_exp_telnr2'    => ['exp_Telnr2', 'bool', true, 'Freigabe Telefon 2'],
        'bmv_exp_email1'    => ['exp_email1', 'bool', true, 'Freigabe E-Mail 1'],
        'bmv_exp_email2'    => ['exp_email2', 'bool', true, 'Freigabe E-Mail 2'],
        'bmv_exp_adresse'   => ['exp_Adresse', 'bool', true, 'Freigabe Adresse'],
        'bmv_exp_internet'  => ['exp_Internet', 'bool', true, 'Freigabe Internet'],
        'bmv_foto'          => ['foto', 'text', true, 'Foto'],
        'bmv_aenderung'     => ['änderung', 'text', false, 'Änderungsdatum (BMV)'],
        'bmv_pcanmeldename' => ['pcanmeldename', 'text', false, 'Geändert von (BMV)'],
        'bmv_pcipadresse'   => ['pcipadresse', 'text', false, 'IP-Adresse (BMV)'],
        'bmv_verein_id'     => ['verein_id', 'int', false, 'Verein-ID (BMV)'],
    ];

    /** Lokale Spalten, die in Syncopa bearbeitet werden können (werden nur lokal gespeichert) */
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
     * Bei zugeordneten Mitgliedern wird ermittelt, welche Felder sich unterscheiden.
     * Liefert nur Anzeigedaten, es wird nichts geschrieben.
     */
    public static function vorschau(BmvApi $api, ?array $bmvMusiker = null): array {
        $bmvMusiker = $bmvMusiker ?? self::alleMusiker($api);
        $lokal = Database::getInstance()->fetchAll(
            "SELECT id, mitgliedsnummer, mitgliedsart_code, " . implode(', ', self::alleSpalten()) . " FROM mitglieder"
        );
        $lokalNachLokalId = array_column($lokal, null, 'id');

        $lokalNachId = [];
        $lokalNachSchluessel = [];
        foreach ($lokal as $l) {
            if (!empty($l['bmv_id'])) {
                $lokalNachId[$l['bmv_id']] = $l;
            }
            $lokalNachSchluessel[self::schluessel($l['vorname'], $l['nachname'], $l['geburtsdatum'])][] = $l;
        }

        $neu = [];
        $geaendert = [];
        $unveraendert = 0;
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
                $felder = self::geaenderteFelder($lokalNachLokalId[$lokalId], self::felderAusBmv($m));
                if ($felder) {
                    $geaendert[] = $eintrag + ['lokal_id' => $lokalId, 'felder' => $felder];
                } else {
                    $unveraendert++;
                }
            }
        }

        $nichtImBmv = [];
        foreach ($lokal as $l) {
            // Gastmusiker sind keine Vereinsmitglieder und fehlen im BMV absichtlich
            if (empty($zugeordnet[$l['id']]) && empty($l['bmv_id']) && !Mitgliedsart::istGast($l['mitgliedsart_code'])) {
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
            'geaendert'    => $geaendert,
            'unveraendert' => $unveraendert,
            'nicht_im_bmv' => $nichtImBmv,
        ];
    }

    /** Anzeigenamen der Felder, die sich zwischen lokalem Datensatz und BMV-Stand unterscheiden. */
    public static function geaenderteFelder(array $lokal, array $ausBmv): array {
        $felder = [];
        foreach (self::ZUORDNUNG as $spalte => [, , , $name]) {
            $a = trim((string)($lokal[$spalte] ?? ''));
            $b = trim((string)($ausBmv[$spalte] ?? ''));
            if ($a !== $b) {
                $felder[] = $name;
            }
        }
        return $felder;
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
     * Wandelt eine hochgeladene Bilddatei in das Fotoformat des BMV um (wird nur lokal gespeichert):
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
        switch ($info[2]) {
            case IMAGETYPE_JPEG: $quelle = @imagecreatefromjpeg($pfad); break;
            case IMAGETYPE_PNG:  $quelle = @imagecreatefrompng($pfad);  break;
            case IMAGETYPE_GIF:  $quelle = @imagecreatefromgif($pfad);  break;
            case IMAGETYPE_WEBP: $quelle = @imagecreatefromwebp($pfad); break;
            default:             $quelle = false;
        }
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

    /**
     * Übernimmt die Vorschau in die lokale Datenbank. Schreibt nichts in den BMV.
     * Nicht im BMV vorhandene Mitglieder werden nur mit einem Zeitstempel markiert (bmv_id bleibt leer).
     */
    public static function uebernehmen(BmvApi $api): array {
        $bmvMusiker = self::alleMusiker($api);
        $vorschau = self::vorschau($api, $bmvMusiker);
        $bmvNachId = [];
        foreach ($bmvMusiker as $m) {
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

            foreach ($vorschau['geaendert'] as $eintrag) {
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
            'aktualisiert' => count($vorschau['geaendert']),
            'nicht_im_bmv' => count($vorschau['nicht_im_bmv']),
        ];
    }
}
