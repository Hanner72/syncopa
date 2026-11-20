<?php
// classes/MailVerbindung.php
// Gemeinsame Netzwerkverbindung für IMAP und SMTP: SSL/TLS von Anfang an, STARTTLS oder unverschlüsselt,
// mit Zeitlimit und verständlichen Fehlermeldungen. Zertifikate werden geprüft.

class MailVerbindung {

    /** @var resource|null */
    private $socket = null;
    private string $host;

    public function __construct(string $host, int $port, string $sicherheit, int $zeitlimit = 20) {
        $this->host = $host;
        $kontext = stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'SNI_enabled' => true,
        ]]);
        $schema = $sicherheit === 'ssl' ? 'ssl' : 'tcp';
        $socket = @stream_socket_client("$schema://$host:$port", $nr, $text, $zeitlimit, STREAM_CLIENT_CONNECT, $kontext);
        if (!$socket) {
            $grund = $text ?: 'keine Antwort';
            if (stripos($grund, 'certificate') !== false || stripos($grund, 'SSL') !== false) {
                $grund = 'Verschlüsselte Verbindung fehlgeschlagen (Zertifikat oder Port/Sicherheit falsch?)';
            }
            throw new RuntimeException("Keine Verbindung zu $host:$port – $grund.");
        }
        stream_set_timeout($socket, $zeitlimit);
        $this->socket = $socket;
    }

    /** Wechselt auf eine verschlüsselte Verbindung (nach STARTTLS) */
    public function tlsStarten(): void {
        $ok = @stream_socket_enable_crypto($this->socket, true,
            STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        if ($ok !== true) {
            throw new RuntimeException("Verschlüsselung (STARTTLS) mit {$this->host} fehlgeschlagen.");
        }
    }

    public function schreiben(string $daten): void {
        $gesamt = strlen($daten);
        $geschrieben = 0;
        while ($geschrieben < $gesamt) {
            $n = @fwrite($this->socket, substr($daten, $geschrieben, 65536));
            if ($n === false || $n === 0) {
                throw new RuntimeException("Verbindung zu {$this->host} unterbrochen (Senden).");
            }
            $geschrieben += $n;
        }
    }

    /** Eine Zeile inkl. CRLF lesen */
    public function zeile(): string {
        $zeile = fgets($this->socket);
        if ($zeile === false) {
            $meta = stream_get_meta_data($this->socket);
            throw new RuntimeException($meta['timed_out'] ? "{$this->host} antwortet nicht (Zeitüberschreitung)." : "Verbindung zu {$this->host} unterbrochen.");
        }
        return $zeile;
    }

    /** Genau $anzahl Bytes lesen (für IMAP-Literale) */
    public function bytes(int $anzahl): string {
        $daten = '';
        while (strlen($daten) < $anzahl) {
            $stueck = fread($this->socket, min(65536, $anzahl - strlen($daten)));
            if ($stueck === false || $stueck === '') {
                $meta = stream_get_meta_data($this->socket);
                if ($meta['timed_out'] || feof($this->socket)) {
                    throw new RuntimeException("Verbindung zu {$this->host} unterbrochen (Lesen).");
                }
                continue;
            }
            $daten .= $stueck;
        }
        return $daten;
    }

    public function schliessen(): void {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function __destruct() {
        $this->schliessen();
    }
}
