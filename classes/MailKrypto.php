<?php
// classes/MailKrypto.php
// Verschlüsselt die Passwörter der E-Mail-Postfächer (libsodium, secretbox).
// Der Schlüssel liegt in config.mailkey.php im Hauptverzeichnis: nicht in Git, wird beim Update nicht überschrieben
// und beim ersten Gebrauch automatisch erzeugt. Ohne diese Datei lassen sich gespeicherte Passwörter nicht mehr lesen –
// sie gehört also zur Sicherung dazu (wie config.php).

class MailKrypto {

    private const DATEI = 'config.mailkey.php';

    private static ?string $schluessel = null;

    private static function schluessel(): string {
        if (self::$schluessel !== null) {
            return self::$schluessel;
        }
        $pfad = BASE_PATH . '/' . self::DATEI;
        if (is_file($pfad)) {
            $wert = base64_decode((string)(require $pfad), true);
            if ($wert === false || strlen($wert) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new RuntimeException('Der Schlüssel in ' . self::DATEI . ' ist ungültig.');
            }
        } else {
            $wert = sodium_crypto_secretbox_keygen();
            $inhalt = "<?php\n// Schlüssel für die gespeicherten E-Mail-Passwörter – nicht löschen, gehört zur Sicherung.\n"
                . "return '" . base64_encode($wert) . "';\n";
            if (@file_put_contents($pfad, $inhalt, LOCK_EX) === false) {
                throw new RuntimeException('Der Schlüssel für E-Mail-Passwörter kann nicht gespeichert werden (' . self::DATEI . ' nicht beschreibbar).');
            }
            @chmod($pfad, 0640);
        }
        return self::$schluessel = $wert;
    }

    public static function verschluesseln(string $klartext): string {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($klartext, $nonce, self::schluessel()));
    }

    public static function entschluesseln(string $gespeichert): string {
        $roh = base64_decode($gespeichert, true);
        if ($roh === false || strlen($roh) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Gespeichertes Passwort ist beschädigt.');
        }
        $klartext = sodium_crypto_secretbox_open(
            substr($roh, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($roh, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::schluessel()
        );
        if ($klartext === false) {
            throw new RuntimeException('Gespeichertes Passwort kann nicht entschlüsselt werden (Schlüssel geändert?). Bitte das Passwort neu eintragen.');
        }
        return $klartext;
    }
}
