<?php
// classes/BmvApi.php
// BMV-Datenservice (ÖBV): Zugriff per HTTP Basic Auth, Zugangsdaten aus den Einstellungen

class BmvApi {
    private bool $aktiv;
    private string $baseUrl;
    private string $email;
    private string $land;
    private string $bezirk;
    private string $vereinNr;
    private string $passwort;

    public function __construct() {
        $db = Database::getInstance();
        $s  = [];
        foreach ($db->fetchAll("SELECT schluessel, wert FROM einstellungen WHERE schluessel LIKE 'bmv_%'") as $r) {
            $s[$r['schluessel']] = (string)$r['wert'];
        }
        $this->aktiv    = ($s['bmv_aktiv'] ?? '0') === '1';
        $this->baseUrl  = rtrim($s['bmv_api_url'] ?? '', '/');
        $this->email    = trim($s['bmv_email'] ?? '');
        $this->land     = strtoupper(trim($s['bmv_land'] ?? ''));
        $this->bezirk   = trim($s['bmv_bezirk'] ?? '');
        $this->vereinNr = trim($s['bmv_verein'] ?? '');
        $this->passwort = $s['bmv_passwort'] ?? '';
    }

    /**
     * true, wenn der Schalter „BMV-Synchronisation aktiv" eingeschaltet UND alle Zugangsdaten vorhanden sind.
     * Zentrale Prüfung – wird von allen Sync-Funktionen, Buttons und der automatischen Aktualisierung verwendet,
     * damit der Schalter in den Einstellungen die BMV-Funktionen auf der ganzen Seite auf einmal frei- bzw. abschaltet.
     */
    public function istKonfiguriert(): bool {
        return $this->aktiv
            && $this->baseUrl !== '' && $this->email !== '' && $this->land !== '' && $this->bezirk !== ''
            && $this->vereinNr !== '' && $this->passwort !== '';
    }

    /** Vereinskennung LAND-BEZIRK-VEREIN, Verein mit führenden Nullen auf 3 Stellen */
    public function getVereinskennung(): string {
        return $this->land . '-' . $this->bezirk . '-' . str_pad($this->vereinNr, 3, '0', STR_PAD_LEFT);
    }

    /** Benutzername für Basic Auth: E-Mail:Vereinskennung */
    public function getLogin(): string {
        return $this->email . ':' . $this->getVereinskennung();
    }

    /** GET-Anfrage, liefert die Antwort als Array. Wirft RuntimeException mit verständlicher Meldung. */
    public function get(string $pfad): array {
        [$code, $body, $err, $header] = $this->anfrage('GET', $this->baseUrl . $pfad, $this->kopfzeilen(false));
        $this->pruefeAntwort($code, $body, $err, $header);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Unerwartete Antwort vom BMV-Datenservice.');
        }
        return $data;
    }

    /** PUT-Anfrage mit JSON-Körper. Wirft RuntimeException bei Fehlern. */
    public function put(string $pfad, array $daten): void {
        $json = json_encode($daten, JSON_UNESCAPED_UNICODE);
        [$code, $body, $err, $header] = $this->anfrage('PUT', $this->baseUrl . $pfad, $this->kopfzeilen(true), $json);
        $this->pruefeAntwort($code, $body, $err, $header);
    }

    /** POST-Anfrage mit JSON-Körper, liefert die Antwort (den angelegten Datensatz) als Array. */
    public function post(string $pfad, array $daten): array {
        $json = json_encode($daten, JSON_UNESCAPED_UNICODE);
        [$code, $body, $err, $header] = $this->anfrage('POST', $this->baseUrl . $pfad, $this->kopfzeilen(true), $json);
        $this->pruefeAntwort($code, $body, $err, $header);
        $antwort = json_decode($body, true);
        if (!is_array($antwort)) {
            throw new RuntimeException('Unerwartete Antwort vom BMV-Datenservice.');
        }
        return $antwort;
    }

    /** Legt einen neuen Musiker im BMV an und liefert dessen BMV-ID (m_NR). */
    public function musikerErstellen(array $daten): string {
        $antwort = $this->post('/api/Musiker', $daten);
        $mNr = (string)($antwort['m_NR'] ?? '');
        if ($mNr === '') {
            throw new RuntimeException('Der BMV-Datenservice hat keine Musiker-ID zurückgegeben.');
        }
        return $mNr;
    }

    /**
     * Schreibt geänderte Felder in den BMV-Datensatz des Vereins.
     * Der vollständige Datensatz wird vorher geladen, damit nicht verwaltete Felder erhalten bleiben.
     */
    public function vereinsdatenAktualisieren(int $nr, array $aenderungen): void {
        $pfad    = '/api/KAPELLE/' . $nr;
        $aktuell = $this->get($pfad);
        $this->put($pfad, array_merge($aktuell, $aenderungen));
    }

    /** Schreibt geänderte Felder in den BMV-Datensatz eines Musikers (Datensatz wird vorher vollständig geladen). */
    public function musikerAktualisieren(string $mNr, array $aenderungen): void {
        $pfad    = '/api/Musiker/' . rawurlencode($mNr);
        $aktuell = $this->get($pfad);
        $this->put($pfad, array_merge($aktuell, $aenderungen));
    }

    private function kopfzeilen(bool $mitJson): array {
        $zeilen = [
            'Authorization: Basic ' . base64_encode($this->getLogin() . ':' . $this->passwort),
            'Accept: application/json',
        ];
        if ($mitJson) {
            $zeilen[] = 'Content-Type: application/json';
        }
        return $zeilen;
    }

    private function pruefeAntwort(int $code, $body, string $err, array $header = []): void {
        if ($body === false) {
            throw new RuntimeException('Verbindung zum BMV-Datenservice fehlgeschlagen: ' . $err);
        }
        if ($code === 401) {
            throw new RuntimeException('BMV-Zugangsdaten wurden abgelehnt. Bitte E-Mail, Vereinskennung und Passwort prüfen.');
        }
        if ($code === 403) {
            throw new RuntimeException('Der BMV-Datenservice hat die Anfrage aus Berechtigungsgründen abgelehnt (HTTP 403).'
                . $this->antworttext($body) . $this->headertext($header));
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('BMV-Datenservice antwortete mit HTTP ' . $code . '.'
                . $this->antworttext($body) . $this->headertext($header));
        }
    }

    /** Hängt den Antworttext des BMV an die Fehlermeldung an (gekürzt, nur Text), falls vorhanden. */
    private function antworttext(string $body): string {
        $text = trim(strip_tags($body));
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) > 300) {
            $text = mb_substr($text, 0, 300) . '…';
        }
        return ' Antwort des BMV: ' . $text;
    }

    /**
     * Hängt diagnostisch auffällige Antwort-Header an, falls vorhanden (z.B. wenn der Antworttext leer
     * ist, aber der eigentliche Grund nur im Header steht). Zeigt bewusst nicht alle Standard-Header.
     */
    private function headertext(array $header): string {
        $auffaellig = array_values(array_filter($header, function ($zeile) {
            return preg_match('/^(WWW-Authenticate|X-[\w-]+|Error|Reason)\s*:/i', $zeile) === 1;
        }));
        if (!$auffaellig) {
            return '';
        }
        return ' (' . implode(' | ', array_slice($auffaellig, 0, 5)) . ')';
    }

    /**
     * Führt die HTTP-Anfrage aus: cURL wenn vorhanden, sonst allow_url_fopen (wie bei den Updates).
     * Liefert zusätzlich die Antwort-Header, da die eigentliche Fehlerursache (z.B. bei einem nackten
     * 403 ohne Antworttext) manchmal nur dort steht.
     */
    private function anfrage(string $methode, string $url, array $kopfzeilen, ?string $body = null): array {
        if (function_exists('curl_init')) {
            $antwortHeader = [];
            $ch = curl_init($url);
            $optionen = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $methode,
                CURLOPT_HTTPHEADER     => $kopfzeilen,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HEADERFUNCTION => function ($curl, $zeile) use (&$antwortHeader) {
                    $z = trim($zeile);
                    if ($z !== '') $antwortHeader[] = $z;
                    return strlen($zeile);
                },
            ];
            if ($body !== null) {
                $optionen[CURLOPT_POSTFIELDS] = $body;
            }
            curl_setopt_array($ch, $optionen);
            $antwort = curl_exec($ch);
            $code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err     = curl_error($ch);
            curl_close($ch);
            return [$code, $antwort, $err, $antwortHeader];
        }
        if (ini_get('allow_url_fopen')) {
            $http = ['method' => $methode, 'header' => implode("\r\n", $kopfzeilen), 'timeout' => 30, 'ignore_errors' => true];
            if ($body !== null) {
                $http['content'] = $body;
            }
            $ctx      = stream_context_create(['http' => $http]);
            $antwort  = @file_get_contents($url, false, $ctx);
            $code     = 0;
            if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
                $code = (int)$m[1];
            }
            return [$code, $antwort, $antwort === false ? 'Keine Verbindung' : '', $http_response_header ?? []];
        }
        return [0, false, 'Weder cURL noch allow_url_fopen verfügbar', []];
    }

    /** Sucht den eigenen Verein in der KAPELLE-Liste (Land, Bezirk, Vereinsnummer). */
    public function findeEigenenVerein(array $vereine): ?array {
        foreach ($vereine as $v) {
            if (strtoupper((string)($v['land'] ?? '')) === $this->land
                && (int)($v['bezirk'] ?? -1) === (int)$this->bezirk
                && (int)($v['v_NR'] ?? -1) === (int)$this->vereinNr) {
                return $v;
            }
        }
        return null;
    }

    /** Übersetzt ein KAPELLE-Datensatz in Einstellungsschlüssel (verein_*). */
    public static function vereinsdatenZuEinstellungen(array $v): array {
        $kontaktperson = trim(implode(' ', array_filter([
            $v['titel'] ?? '', $v['vorname'] ?? '', $v['zuname'] ?? '',
        ])));
        $t = fn($wert) => trim((string)($wert ?? ''));
        return [
            'verein_name'          => $t($v['vereinsname'] ?? null),
            'verein_adresse'       => $t($v['strasse'] ?? null),
            'verein_plz'           => $t($v['pltz'] ?? null),
            'verein_ort'           => $t($v['ort'] ?? null),
            'verein_gemeinde'      => $t($v['gemeinde'] ?? null),
            'verein_email'         => $t($v['email'] ?? null),
            'verein_telefon'       => $t($v['telefon'] ?? null),
            'verein_website'       => $t($v['homepage'] ?? null),
            'verein_zvr_nr'        => $t($v['zvrNr'] ?? null),
            'verein_gruendungsjahr'=> $t($v['gruendungsJahr'] ?? null),
            'verein_kontaktperson' => $kontaktperson,
            'verein_kontoname'     => $t($v['kontoBez'] ?? null),
            'verein_iban'          => $t($v['iban'] ?? null),
            'verein_bic'           => $t($v['bic'] ?? null),
        ];
    }

    /**
     * Übersetzt geänderte Einstellungen (verein_*) zurück in KAPELLE-Felder.
     * Kontaktperson wird in Vorname und Zuname aufgeteilt, der Titel bleibt unverändert.
     */
    public static function einstellungenZuVereinsdaten(array $werte): array {
        $feldZuordnung = [
            'verein_name'           => 'vereinsname',
            'verein_adresse'        => 'strasse',
            'verein_plz'            => 'pltz',
            'verein_ort'            => 'ort',
            'verein_gemeinde'       => 'gemeinde',
            'verein_email'          => 'email',
            'verein_telefon'        => 'telefon',
            'verein_website'        => 'homepage',
            'verein_zvr_nr'         => 'zvrNr',
            'verein_kontoname'      => 'kontoBez',
            'verein_iban'           => 'iban',
            'verein_bic'            => 'bic',
        ];
        $daten = [];
        foreach ($feldZuordnung as $schluessel => $feld) {
            if (array_key_exists($schluessel, $werte)) {
                $daten[$feld] = trim((string)$werte[$schluessel]);
            }
        }
        if (array_key_exists('verein_gruendungsjahr', $werte)) {
            $jahr = trim((string)$werte['verein_gruendungsjahr']);
            $daten['gruendungsJahr'] = $jahr === '' ? null : (int)$jahr;
        }
        if (array_key_exists('verein_kontaktperson', $werte)) {
            $teile = preg_split('/\s+/', trim((string)$werte['verein_kontaktperson']), -1, PREG_SPLIT_NO_EMPTY);
            $daten['vorname'] = array_shift($teile) ?? '';
            $daten['zuname']  = implode(' ', $teile);
        }
        return $daten;
    }
}
