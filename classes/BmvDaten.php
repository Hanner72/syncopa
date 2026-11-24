<?php
// classes/BmvDaten.php
// Daten aus dem BMV-Datenservice holen und prüfen, ob es dort etwas Neues gibt.
// Nur lesend: im BMV wird nie etwas geändert.

class BmvDaten {

    /** Einstellungsschlüssel für das Ergebnis der letzten Prüfung (JSON) */
    private const PRUEFUNG = 'bmv_pruefung';

    /** Vereinsdaten aus dem BMV mit den Einstellungen vergleichen: [schluessel => ['lokal' => …, 'bmv' => …]] */
    public static function vereinsdatenVergleich(array $verein): array {
        $lokal = self::einstellungen();
        $vergleich = [];
        foreach (BmvApi::vereinsdatenZuEinstellungen($verein) as $schluessel => $bmvWert) {
            $vergleich[$schluessel] = [
                'lokal'     => trim((string)($lokal[$schluessel] ?? '')),
                'bmv'       => $bmvWert,
                'geaendert' => trim((string)($lokal[$schluessel] ?? '')) !== $bmvWert,
            ];
        }
        return $vergleich;
    }

    /** Übernimmt die Vereinsdaten aus dem BMV in die Einstellungen. Schreibt nichts in den BMV. */
    public static function vereinsdatenUebernehmen(array $verein): void {
        $werte = BmvApi::vereinsdatenZuEinstellungen($verein);
        $werte['bmv_verein_nr']          = (string)($verein['nr'] ?? '');
        $werte['bmv_letzte_sync_verein'] = date('Y-m-d H:i:s');
        foreach ($werte as $schluessel => $wert) {
            self::einstellungSpeichern($schluessel, $wert);
        }
    }

    /**
     * Prüft im BMV, ob es neue oder geänderte Daten gibt, und speichert das Ergebnis für den Hinweis
     * in den Einstellungen. Fehler werden im Ergebnis vermerkt statt geworfen.
     */
    public static function pruefen(BmvApi $api): array {
        $ergebnis = ['zeitpunkt' => date('Y-m-d H:i:s')];
        try {
            $verein = $api->eigenenVereinHolen();
            $ergebnis['verein'] = count(array_filter(self::vereinsdatenVergleich($verein), fn($f) => $f['geaendert']));

            $vorschau = BmvMitgliederSync::vorschau($api);
            $ergebnis['mitglieder_neu']       = count($vorschau['neu']);
            $ergebnis['mitglieder_geaendert'] = count($vorschau['geaendert']);

            $zusatz = BmvZusatzdaten::vergleich(BmvZusatzdaten::holen($api));
            $ergebnis['zusatz'] = $zusatz['neu'] + $zusatz['geaendert'] + $zusatz['entfernt'];

            // Noten: Syncopa ist führend, daher zählen nur Stücke, die neu im BMV sind
            $ergebnis['noten_neu'] = BmvNoten::anzahlNeu($api);
        } catch (\Throwable $e) {
            $ergebnis['fehler'] = $e->getMessage();
        }
        self::einstellungSpeichern(self::PRUEFUNG, json_encode($ergebnis, JSON_UNESCAPED_UNICODE));
        return $ergebnis;
    }

    /**
     * Prüft erneut, wenn das Intervall aus den Einstellungen („bmv_sync_intervall_stunden", Standard 24h)
     * abgelaufen ist. Macht nichts, wenn der BMV nicht aktiv oder nicht vollständig eingerichtet ist.
     */
    public static function pruefenFallsFaellig(): ?array {
        $api = new BmvApi();
        if (!$api->istKonfiguriert()) {
            return null;
        }
        $letzte = self::letztePruefung();
        $stunden = (int)(self::einstellungen()['bmv_sync_intervall_stunden'] ?? 24);
        if ($stunden <= 0) {
            $stunden = 24;
        }
        if ($letzte && strtotime($letzte['zeitpunkt'] ?? '') > time() - $stunden * 3600) {
            return $letzte;
        }
        return self::pruefen($api);
    }

    /** Ergebnis der letzten Prüfung oder null */
    public static function letztePruefung(): ?array {
        $wert = self::einstellungen()[self::PRUEFUNG] ?? '';
        $daten = $wert !== '' ? json_decode($wert, true) : null;
        return is_array($daten) ? $daten : null;
    }

    /**
     * Anzahl der Neuigkeiten aus einer Prüfung. Vereinsdaten und Zusatzdaten (Ehrungen, Funktionen …)
     * zählen je als 1, egal wie viele Felder oder Einträge betroffen sind.
     */
    public static function anzahlNeuigkeiten(?array $pruefung): int {
        if (!$pruefung || isset($pruefung['fehler'])) {
            return 0;
        }
        return (($pruefung['verein'] ?? 0) > 0 ? 1 : 0)
            + (int)($pruefung['mitglieder_neu'] ?? 0)
            + (int)($pruefung['mitglieder_geaendert'] ?? 0)
            + (($pruefung['zusatz'] ?? 0) > 0 ? 1 : 0)
            + (($pruefung['noten_neu'] ?? 0) > 0 ? 1 : 0);
    }

    private static function einstellungen(): array {
        $s = [];
        foreach (Database::getInstance()->fetchAll("SELECT schluessel, wert FROM einstellungen") as $r) {
            $s[$r['schluessel']] = (string)$r['wert'];
        }
        return $s;
    }

    private static function einstellungSpeichern(string $schluessel, string $wert): void {
        Database::getInstance()->execute(
            "INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?) ON DUPLICATE KEY UPDATE wert = ?",
            [$schluessel, $wert, $wert]
        );
    }
}
