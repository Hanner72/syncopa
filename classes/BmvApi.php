<?php
// classes/BmvApi.php
// BMV-Datenservice (ÖBV): Zugriff per HTTP Basic Auth, Zugangsdaten aus den Einstellungen.
// Nur lesend: Syncopa holt Daten aus dem BMV, schreibt aber nie etwas dorthin (nur GET-Anfragen).

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
        $this->passwort = Sicherheit::geheimLesen($s['bmv_passwort'] ?? ''); // verschlüsselt gespeichert
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

    /**
     * Ob Syncopa in den BMV schreiben darf. Derzeit nicht: Einträge aus dem BMV sind in Syncopa gesperrt,
     * eigene Einträge werden mit sync_status 'neu' vorgemerkt. Sobald das Schreiben gebaut ist, wird hier
     * umgestellt (und die vorgemerkten Einträge werden über BmvZusatzdaten::zuBmv() übertragen).
     */
    public static function kannSchreiben(): bool {
        return false;
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
        [$code, $body, $err, $header] = $this->anfrage($this->baseUrl . $pfad, $this->kopfzeilen());
        $this->pruefeAntwort($code, $body, $err, $header);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Unerwartete Antwort vom BMV-Datenservice.');
        }
        return $data;
    }

    private function kopfzeilen(): array {
        return [
            'Authorization: Basic ' . base64_encode($this->getLogin() . ':' . $this->passwort),
            'Accept: application/json',
        ];
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
     * Führt eine GET-Anfrage aus (andere Methoden gibt es bewusst nicht): cURL wenn vorhanden, sonst allow_url_fopen (wie bei den Updates).
     * Liefert zusätzlich die Antwort-Header, da die eigentliche Fehlerursache (z.B. bei einem nackten
     * 403 ohne Antworttext) manchmal nur dort steht.
     */
    private function anfrage(string $url, array $kopfzeilen): array {
        if (function_exists('curl_init')) {
            $antwortHeader = [];
            $ch = curl_init($url);
            $optionen = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPGET        => true,
                CURLOPT_HTTPHEADER     => $kopfzeilen,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HEADERFUNCTION => function ($curl, $zeile) use (&$antwortHeader) {
                    $z = trim($zeile);
                    if ($z !== '') $antwortHeader[] = $z;
                    return strlen($zeile);
                },
            ];
            curl_setopt_array($ch, $optionen);
            $antwort = curl_exec($ch);
            $code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err     = curl_error($ch);
            curl_close($ch);
            return [$code, $antwort, $err, $antwortHeader];
        }
        if (ini_get('allow_url_fopen')) {
            $http = ['method' => 'GET', 'header' => implode("\r\n", $kopfzeilen), 'timeout' => 30, 'ignore_errors' => true];
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

    /** Bezeichnungen der Vereinsfelder (Einstellungsschlüssel => Anzeigename) */
    public const VEREIN_FELDER = [
        'verein_name'           => 'Vereinsname',
        'verein_kontaktperson'  => 'Kontaktperson',
        'verein_adresse'        => 'Straße',
        'verein_plz'            => 'PLZ',
        'verein_ort'            => 'Ort',
        'verein_gemeinde'       => 'Gemeinde',
        'verein_email'          => 'E-Mail',
        'verein_telefon'        => 'Telefon',
        'verein_website'        => 'Website',
        'verein_zvr_nr'         => 'ZVR-Nummer',
        'verein_gruendungsjahr' => 'Gründungsjahr',
        'verein_kontoname'      => 'Kontobezeichnung',
        'verein_iban'           => 'IBAN',
        'verein_bic'            => 'BIC',
    ];

    /** Holt den KAPELLE-Datensatz des eigenen Vereins. Wirft RuntimeException, wenn er nicht gefunden wird. */
    public function eigenenVereinHolen(): array {
        $verein = $this->findeEigenenVerein($this->get('/api/KAPELLE'));
        if ($verein === null) {
            throw new RuntimeException('Der Verein ' . $this->getVereinskennung() . ' wurde im BMV-Datenservice nicht gefunden.');
        }
        return $verein;
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
}
