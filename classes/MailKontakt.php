<?php
// classes/MailKontakt.php
// Kontakte des E-Mail-Programms. Ein Kontakt ist privat (nur der Ersteller sieht ihn) oder gemeinsam
// (alle Benutzer mit Zugang zu mindestens einem Postfach sehen ihn). Bearbeiten/Löschen: Ersteller oder Admin.

class MailKontakt {

    private static function db(): Database { return Database::getInstance(); }

    /** Darf der angemeldete Benutzer Kontakte nutzen? (hat ein Postfach oder ist Admin) */
    public static function darfNutzen(): bool {
        return Session::isAdmin() || (bool)MailKonto::meine();
    }

    public static function name(array $k): string {
        $n = trim(($k['vorname'] ?? '') . ' ' . ($k['nachname'] ?? ''));
        return $n !== '' ? $n : (($k['organisation'] ?? '') ?: $k['email']);
    }

    /** Für den angemeldeten Benutzer sichtbare Kontakte. $filter: '' (alle), 'gemeinsam', 'privat' */
    public static function sichtbare(string $filter = '', string $suche = '', int $limit = 0): array {
        $uid = (int)Session::getUserId();
        $w = ['(k.oeffentlich = 1 OR k.erstellt_von = ?)'];
        $p = [$uid];
        if ($filter === 'gemeinsam') $w[] = 'k.oeffentlich = 1';
        if ($filter === 'privat') { $w[] = 'k.oeffentlich = 0'; $w[] = 'k.erstellt_von = ?'; $p[] = $uid; }
        if ($suche !== '') {
            $w[] = "(CONCAT_WS(' ', k.vorname, k.nachname) LIKE ? OR CONCAT_WS(' ', k.nachname, k.vorname) LIKE ? OR k.organisation LIKE ? OR k.funktion LIKE ? OR k.email LIKE ?)";
            $like = '%' . $suche . '%';
            array_push($p, $like, $like, $like, $like, $like);
        }
        return self::db()->fetchAll(
            "SELECT k.*, b.benutzername AS ersteller FROM mail_kontakte k LEFT JOIN benutzer b ON b.id = k.erstellt_von
             WHERE " . implode(' AND ', $w) . " ORDER BY COALESCE(NULLIF(k.nachname, ''), NULLIF(k.organisation, ''), k.email), k.vorname" . ($limit ? " LIMIT " . (int)$limit : ''), $p);
    }

    public static function laden(int $id): ?array {
        $k = self::db()->fetchOne("SELECT * FROM mail_kontakte WHERE id = ?", [$id]);
        if (!$k || (!$k['oeffentlich'] && (int)$k['erstellt_von'] !== (int)Session::getUserId())) return null;
        return $k;
    }

    public static function darfBearbeiten(array $k): bool {
        return Session::isAdmin() || (int)$k['erstellt_von'] === (int)Session::getUserId();
    }

    public static function speichern(array $d, ?int $id = null): int {
        $email = strtolower(trim((string)($d['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Bitte eine gültige E-Mail-Adresse eingeben.');
        $werte = [];
        foreach (['vorname' => 100, 'nachname' => 100, 'organisation' => 150, 'funktion' => 150, 'telefon' => 50, 'adresse' => 250] as $feld => $max) {
            $werte[$feld] = mb_substr(trim((string)($d[$feld] ?? '')), 0, $max) ?: null;
        }
        if (!$werte['vorname'] && !$werte['nachname'] && !$werte['organisation']) throw new RuntimeException('Bitte einen Namen oder eine Organisation eingeben.');
        $notizen = trim((string)($d['notizen'] ?? '')) ?: null;
        $oeffentlich = !empty($d['oeffentlich']) ? 1 : 0;

        // Doppelte Adresse unter den eigenen bzw. gemeinsamen Kontakten vermeiden
        $doppelt = self::db()->fetchOne("SELECT id FROM mail_kontakte WHERE email = ? AND (oeffentlich = 1 OR erstellt_von = ?) AND id <> ?",
            [$email, (int)Session::getUserId(), (int)$id]);
        if ($doppelt) throw new RuntimeException("Einen Kontakt mit der Adresse $email gibt es schon.");

        if ($id) {
            $k = self::laden($id);
            if (!$k || !self::darfBearbeiten($k)) throw new RuntimeException('Diesen Kontakt darfst du nicht bearbeiten.');
            self::db()->execute("UPDATE mail_kontakte SET vorname=?, nachname=?, organisation=?, funktion=?, email=?, telefon=?, adresse=?, notizen=?, oeffentlich=?, aktualisiert_am=NOW() WHERE id=?",
                [$werte['vorname'], $werte['nachname'], $werte['organisation'], $werte['funktion'], $email, $werte['telefon'], $werte['adresse'], $notizen, $oeffentlich, $id]);
            return $id;
        }
        self::db()->execute("INSERT INTO mail_kontakte (vorname, nachname, organisation, funktion, email, telefon, adresse, notizen, oeffentlich, erstellt_von, erstellt_am)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$werte['vorname'], $werte['nachname'], $werte['organisation'], $werte['funktion'], $email, $werte['telefon'], $werte['adresse'], $notizen, $oeffentlich, Session::getUserId()]);
        return (int)self::db()->lastInsertId();
    }

    public static function loeschen(int $id): void {
        $k = self::laden($id);
        if (!$k || !self::darfBearbeiten($k)) throw new RuntimeException('Diesen Kontakt darfst du nicht löschen.');
        self::db()->execute("DELETE FROM mail_kontakte WHERE id = ?", [$id]);
    }
}
