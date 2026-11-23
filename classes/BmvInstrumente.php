<?php
// classes/BmvInstrumente.php
// Register und Instrumententypen aus den Namenslisten des BMV (SetupRegister, SetupInstrumente).
//
// - Der BMV ist maßgeblich: Register und Instrumente mit BMV-Nummer (Spalte bmv_code) bekommen Name, Reihenfolge
//   bzw. Register aus dem BMV und sind in den Stammdaten gesperrt.
// - Eigene Instrumententypen (ohne BMV-Nummer, z. B. Lyra) bleiben und lassen sich weiter pflegen.
// - Ein Instrument wird so mit dem BMV verknüpft: vorhandene BMV-Nummer → bisherige Zuordnung aus „BMV Daten holen“
//   (bmv_bezeichnungen.instrument_typ_id) → eigener Typ mit gleichem Namen → sonst neu anlegen.
// - Beim ersten Abgleich werden die bisherigen eigenen Register durch die BMV-Register ersetzt: Instrumententypen,
//   Mitglieder und Formations-Register werden umgehängt (Einstellung register_bmv_umgestellt), danach die alten
//   Register gelöscht, soweit nichts mehr darauf zeigt.

class BmvInstrumente {

    /**
     * Holt beide Listen aus dem BMV und gleicht ab. Gibt eine Zusammenfassung zurück:
     * ['register_neu', 'register_geaendert', 'register_entfernt', 'instrumente_neu', 'instrumente_verknuepft', 'instrumente_umbenannt']
     */
    public static function abgleichen(BmvApi $api): array {
        $register = $api->get('/api/SetupRegister');
        $instrumente = $api->get('/api/SetupInstrumente');
        if (!$register || !$instrumente) {
            throw new RuntimeException('Der BMV hat keine Register bzw. Instrumente geliefert.');
        }
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $ergebnis = self::abgleichenMit($register, $instrumente);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        $db->execute(
            "INSERT INTO einstellungen (schluessel, wert) VALUES ('instrumente_bmv_stand', ?) ON DUPLICATE KEY UPDATE wert = VALUES(wert)",
            [date('Y-m-d H:i:s')]
        );
        return $ergebnis;
    }

    /** Abgleich mit bereits geholten Listen (läuft in der Transaktion von abgleichen()) */
    public static function abgleichenMit(array $bmvRegister, array $bmvInstrumente): array {
        $db = Database::getInstance();
        $e = ['register_neu' => 0, 'register_geaendert' => 0, 'register_entfernt' => 0,
              'instrumente_neu' => 0, 'instrumente_verknuepft' => 0, 'instrumente_umbenannt' => 0];
        $ersterAbgleich = !$db->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'register_bmv_umgestellt'");

        // Stand vorher: Register je Instrumententyp (für das Umhängen der alten Register)
        $altesRegister = [];
        foreach ($db->fetchAll("SELECT id, register_id FROM instrument_typen") as $r) {
            $altesRegister[(int)$r['id']] = $r['register_id'] !== null ? (int)$r['register_id'] : null;
        }

        // 1. Register
        $registerId = []; // BMV-Nummer => register.id
        foreach ($bmvRegister as $r) {
            $code = (int)($r['id'] ?? 0);
            $name = mb_substr(trim((string)($r['bezeichnung'] ?? '')), 0, 50);
            if ($code <= 0 || $name === '') continue;
            $sort = (int)($r['sortiernr'] ?? $code);
            $vorhanden = $db->fetchOne("SELECT id, name, sortierung FROM register WHERE bmv_code = ?", [$code]);
            if ($vorhanden) {
                if ($vorhanden['name'] !== $name || (int)$vorhanden['sortierung'] !== $sort) {
                    $db->execute("UPDATE register SET name = ?, sortierung = ? WHERE id = ?", [$name, $sort, $vorhanden['id']]);
                    $e['register_geaendert']++;
                }
                $registerId[$code] = (int)$vorhanden['id'];
            } else {
                $db->execute("INSERT INTO register (name, sortierung, bmv_code) VALUES (?, ?, ?)", [$name, $sort, $code]);
                $registerId[$code] = (int)$db->lastInsertId();
                $e['register_neu']++;
            }
        }

        // 2. Instrumente
        $zuordnung = [];
        foreach ($db->fetchAll("SELECT code, instrument_typ_id FROM bmv_bezeichnungen WHERE art = 'instrument' AND instrument_typ_id IS NOT NULL") as $r) {
            $zuordnung[(int)$r['code']] = (int)$r['instrument_typ_id'];
        }
        $stimmen = []; // altes Register => [neues Register => Anzahl]
        foreach ($bmvInstrumente as $r) {
            $code = (int)($r['id'] ?? 0);
            $name = mb_substr(trim((string)($r['instrument'] ?? '')), 0, 100);
            if ($code <= 0 || $name === '') continue;
            $neuesRegister = $registerId[(int)($r['register_ID'] ?? 0)] ?? null;

            $typ = $db->fetchOne("SELECT id, name FROM instrument_typen WHERE bmv_code = ?", [$code]);
            if (!$typ && isset($zuordnung[$code])) {
                $typ = $db->fetchOne("SELECT id, name FROM instrument_typen WHERE id = ? AND bmv_code IS NULL", [$zuordnung[$code]]);
            }
            if (!$typ) {
                $typ = $db->fetchOne("SELECT id, name FROM instrument_typen WHERE bmv_code IS NULL AND LOWER(TRIM(name)) = LOWER(?) LIMIT 1", [$name]);
            }
            if ($typ) {
                $typId = (int)$typ['id'];
                $alt = $db->fetchOne("SELECT bmv_code FROM instrument_typen WHERE id = ?", [$typId]);
                if ($alt['bmv_code'] === null) $e['instrumente_verknuepft']++;
                if ($typ['name'] !== $name) $e['instrumente_umbenannt']++;
                $db->execute(
                    "UPDATE instrument_typen SET name = ?, register_id = ?, bmv_code = ?, bmv_automatisch = 0 WHERE id = ?",
                    [$name, $neuesRegister, $code, $typId]
                );
                $vorher = $altesRegister[$typId] ?? null;
                if ($vorher !== null && $neuesRegister !== null) {
                    $stimmen[$vorher][$neuesRegister] = ($stimmen[$vorher][$neuesRegister] ?? 0) + 1;
                }
            } else {
                $db->execute(
                    "INSERT INTO instrument_typen (name, register_id, bmv_code, bmv_automatisch) VALUES (?, ?, ?, 0)",
                    [$name, $neuesRegister, $code]
                );
                $typId = (int)$db->lastInsertId();
                $e['instrumente_neu']++;
            }
            // Zuordnung für „BMV Daten holen“ und die Einträge der Mitglieder aus dem BMV nachziehen
            $db->execute(
                "INSERT INTO bmv_bezeichnungen (art, code, name, instrument_typ_id) VALUES ('instrument', ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), instrument_typ_id = VALUES(instrument_typ_id)",
                [$code, $name, $typId]
            );
            $db->execute(
                "UPDATE mitglied_instrumente SET instrument_typ_id = ? WHERE bmv_code = ? AND bmv_eintrag_id IS NOT NULL",
                [$typId, $code]
            );
        }

        // 3. Einmalig: alte eigene Register durch die BMV-Register ersetzen
        if ($ersterAbgleich && $registerId) {
            $e['register_entfernt'] = self::alteRegisterErsetzen($stimmen, $registerId);
            $db->execute("INSERT INTO einstellungen (schluessel, wert) VALUES ('register_bmv_umgestellt', ?)", [date('Y-m-d H:i:s')]);
        }

        // Beim Holen automatisch angelegte Typen, die niemand mehr nutzt, entfernen
        $db->execute(
            "DELETE t FROM instrument_typen t
             WHERE t.bmv_automatisch = 1 AND t.bmv_code IS NULL
               AND NOT EXISTS (SELECT 1 FROM mitglied_instrumente mi WHERE mi.instrument_typ_id = t.id)
               AND NOT EXISTS (SELECT 1 FROM instrumente i WHERE i.instrument_typ_id = t.id)"
        );
        return $e;
    }

    /**
     * Hängt alles, was noch an einem Register ohne BMV-Nummer hängt, auf ein BMV-Register um und löscht die alten
     * Register. Welches BMV-Register zu einem alten gehört, ergibt sich aus den Instrumenten, die vorher darin waren
     * (Mehrheit); sonst „Sonstige“. Mitglieder bekommen bevorzugt das Register ihres Hauptinstruments.
     */
    private static function alteRegisterErsetzen(array $stimmen, array $registerId): int {
        $db = Database::getInstance();
        $sonstige = $registerId[15] ?? end($registerId);
        $alte = array_map(fn($r) => (int)$r['id'], $db->fetchAll("SELECT id FROM register WHERE bmv_code IS NULL"));
        $ziel = [];
        foreach ($alte as $alt) {
            if (!empty($stimmen[$alt])) {
                arsort($stimmen[$alt]);
                $ziel[$alt] = (int)array_key_first($stimmen[$alt]);
            } else {
                $ziel[$alt] = (int)$sonstige;
            }
        }
        foreach ($ziel as $alt => $neu) {
            $db->execute("UPDATE instrument_typen SET register_id = ? WHERE register_id = ? AND bmv_code IS NULL", [$neu, $alt]);
            // Mitglieder: Register des Hauptinstruments, sonst die Zuordnung des alten Registers
            $db->execute(
                "UPDATE mitglieder m SET m.register_id = COALESCE((
                    SELECT it.register_id FROM mitglied_instrumente mi JOIN instrument_typen it ON it.id = mi.instrument_typ_id
                    WHERE mi.mitglied_id = m.id AND mi.hauptinstrument = 1 AND it.register_id IS NOT NULL LIMIT 1
                 ), ?) WHERE m.register_id = ?",
                [$neu, $alt]
            );
            $db->execute("UPDATE mitglied_formationen SET register_id = ? WHERE register_id = ?", [$neu, $alt]);
        }
        $entfernt = 0;
        foreach ($alte as $alt) {
            $benutzt = $db->fetchOne(
                "SELECT (SELECT COUNT(*) FROM instrument_typen WHERE register_id = ?) + (SELECT COUNT(*) FROM mitglieder WHERE register_id = ?)
                      + (SELECT COUNT(*) FROM mitglied_formationen WHERE register_id = ?) AS n",
                [$alt, $alt, $alt]
            );
            if ((int)$benutzt['n'] === 0) {
                $db->execute("DELETE FROM register WHERE id = ?", [$alt]);
                $entfernt++;
            }
        }
        return $entfernt;
    }

    /** Abgleichen, wenn der letzte Abgleich älter als einen Tag ist (Fehler werden ignoriert) */
    public static function abgleichenFallsNoetig(BmvApi $api): void {
        $stand = Database::getInstance()->fetchOne("SELECT wert FROM einstellungen WHERE schluessel = 'instrumente_bmv_stand'");
        if (empty($stand['wert']) || strtotime($stand['wert']) < time() - 86400) {
            try {
                self::abgleichen($api);
            } catch (\Throwable $e) { /* alter Stand bleibt */ }
        }
    }

    /** Text für eine Meldung aus dem Ergebnis von abgleichen() */
    public static function meldung(array $e): string {
        $teile = [];
        if ($e['register_neu']) $teile[] = $e['register_neu'] . ' Register neu';
        if ($e['register_geaendert']) $teile[] = $e['register_geaendert'] . ' Register geändert';
        if ($e['register_entfernt']) $teile[] = $e['register_entfernt'] . ' alte Register ersetzt';
        if ($e['instrumente_neu']) $teile[] = $e['instrumente_neu'] . ' Instrumente neu';
        if ($e['instrumente_verknuepft']) $teile[] = $e['instrumente_verknuepft'] . ' Instrumente verknüpft';
        if ($e['instrumente_umbenannt']) $teile[] = $e['instrumente_umbenannt'] . ' Instrumente umbenannt';
        return $teile ? implode(', ', $teile) : 'alles stimmt überein';
    }
}
