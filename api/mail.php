<?php
// api/mail.php
// Schnittstelle des E-Mail-Programms (mail.php). Jede Anfrage prüft, ob der Benutzer auf das Postfach zugreifen darf
// (eigene Postfächer bzw. für seine Rollen freigegebene Funktions-Postfächer).
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes.php';

$aktion = $_REQUEST['aktion'] ?? '';
if ($aktion !== 'anhang') {
    header('Content-Type: application/json; charset=utf-8');
}
if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
    exit;
}

// Sitzung früh freigeben, damit langsame Mailserver andere Seitenaufrufe nicht blockieren
// (nur „ungelesen“ und „senden“ schreiben noch in die Sitzung; Lesen von $_SESSION bleibt möglich)
if (!in_array($aktion, ['ungelesen', 'senden'], true)) {
    session_write_close();
}

/** Deutsche Namen der Sonderordner (wie in Outlook) */
const ORDNER_NAMEN = [
    'posteingang' => 'Posteingang', 'entwuerfe' => 'Entwürfe', 'gesendet' => 'Gesendete Elemente',
    'papierkorb' => 'Gelöschte Elemente', 'spam' => 'Junk-E-Mail', 'archiv' => 'Archiv',
];

function antworten(array $daten): void {
    echo json_encode(['success' => true] + $daten, JSON_UNESCAPED_UNICODE);
    exit;
}

function verbinden(array $konto): ImapClient {
    $imap = new ImapClient($konto['imap_host'], (int)$konto['imap_port'], $konto['imap_sicherheit']);
    $imap->anmelden($konto['benutzername'], MailKonto::passwort($konto));
    return $imap;
}

function uidListe(): array {
    $uids = $_POST['uids'] ?? [];
    $uids = is_array($uids) ? $uids : explode(',', (string)$uids);
    return array_values(array_filter(array_map('intval', $uids), fn($u) => $u > 0));
}

/** Adressfeld („a@b.at, Name <c@d.at>, gruppe:aktiv“) → [Adressen, Gruppen-Adressen] */
function empfaengerLesen(string $text): array {
    $adressen = [];
    $gruppen = [];
    foreach (preg_split('/[,;\n]+/', $text) as $teil) {
        $teil = trim($teil);
        if ($teil === '') continue;
        if (preg_match('/^gruppe:([a-z]+):?(\d*)$/', $teil, $m)) {
            $gruppen = array_merge($gruppen, gruppeAdressen($m[1], $m[2] === '' ? null : (int)$m[2]));
            continue;
        }
        foreach (MailNachricht::adressenZerlegen($teil) as $a) {
            if (!filter_var($a[0], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException("Ungültige E-Mail-Adresse: {$a[0]}");
            }
            $adressen[] = $a;
        }
    }
    return [$adressen, $gruppen];
}

/** Empfängergruppen aus Syncopa (nur mit Leserecht auf Mitglieder) */
function gruppen(): array {
    if (!Session::checkPermission('mitglieder', 'lesen')) return [];
    $db = Database::getInstance();
    $g = [
        ['gruppe:aktiv', 'Alle aktiven Mitglieder', $db->fetchOne("SELECT COUNT(*) c FROM mitglieder m WHERE " . Mitgliedsart::sqlAktiv('m') . " AND m.email LIKE '%@%'")['c']],
        ['gruppe:mitglieder', 'Alle aktuellen Mitglieder', $db->fetchOne("SELECT COUNT(*) c FROM mitglieder m WHERE " . Mitgliedsart::sqlMitglied('m') . " AND m.email LIKE '%@%'")['c']],
    ];
    foreach ($db->fetchAll("SELECT f.id, f.name, COUNT(m.id) c FROM formationen f JOIN mitglied_formationen mf ON mf.formation_id = f.id
                            JOIN mitglieder m ON m.id = mf.mitglied_id AND m.mitgliedsart_code IS NOT NULL AND m.email LIKE '%@%' GROUP BY f.id ORDER BY f.name") as $f) {
        $g[] = ['gruppe:formation:' . $f['id'], 'Formation ' . $f['name'], $f['c']];
    }
    foreach ($db->fetchAll("SELECT r.id, r.name, COUNT(m.id) c FROM register r JOIN mitglieder m ON m.register_id = r.id
                            AND " . Mitgliedsart::sqlAktiv('m', true) . " AND m.email LIKE '%@%' GROUP BY r.id ORDER BY r.sortierung") as $r) {
        $g[] = ['gruppe:register:' . $r['id'], 'Register ' . $r['name'], $r['c']];
    }
    foreach (Mitgliedsart::alle() as $code => $a) {
        $c = $db->fetchOne("SELECT COUNT(*) c FROM mitglieder WHERE mitgliedsart_code = ? AND email LIKE '%@%'", [$code])['c'];
        if ($c) $g[] = ['gruppe:art:' . $code, $a['name'], $c];
    }
    return array_values(array_filter($g, fn($x) => $x[2] > 0));
}

function gruppeAdressen(string $typ, ?int $id): array {
    if (!Session::checkPermission('mitglieder', 'lesen')) {
        throw new RuntimeException('Keine Berechtigung für Empfängergruppen.');
    }
    $db = Database::getInstance();
    $sql = match ($typ) {
        'aktiv'      => "SELECT m.email, CONCAT(m.vorname, ' ', m.nachname) n FROM mitglieder m WHERE " . Mitgliedsart::sqlAktiv('m'),
        'mitglieder' => "SELECT m.email, CONCAT(m.vorname, ' ', m.nachname) n FROM mitglieder m WHERE " . Mitgliedsart::sqlMitglied('m'),
        'formation'  => "SELECT m.email, CONCAT(m.vorname, ' ', m.nachname) n FROM mitglieder m JOIN mitglied_formationen mf ON mf.mitglied_id = m.id
                         WHERE mf.formation_id = ? AND m.mitgliedsart_code IS NOT NULL",
        'register'   => "SELECT m.email, CONCAT(m.vorname, ' ', m.nachname) n FROM mitglieder m WHERE m.register_id = ? AND " . Mitgliedsart::sqlAktiv('m', true),
        'art'        => "SELECT m.email, CONCAT(m.vorname, ' ', m.nachname) n FROM mitglieder m WHERE m.mitgliedsart_code = ?",
        default      => throw new RuntimeException('Unbekannte Empfängergruppe.'),
    };
    $zeilen = $db->fetchAll($sql . " AND m.email LIKE '%@%'", $id !== null ? [$id] : []);
    return array_values(array_filter(array_map(fn($r) => [trim($r['email']), $r['n']], $zeilen), fn($a) => filter_var($a[0], FILTER_VALIDATE_EMAIL)));
}

try {
    switch ($aktion) {

        // Postfächer mit Ordnern und ungelesenen Nachrichten
        case 'ordner': {
            $konto = MailKonto::laden((int)($_GET['konto'] ?? 0));
            $imap = verbinden($konto);
            $liste = [];
            foreach ($imap->ordner() as $o) {
                if (!$o['auswaehlbar']) continue;
                $ungelesen = 0;
                if ($o['rolle'] !== 'gesendet' && $o['rolle'] !== 'entwuerfe' && count($liste) < 40) {
                    try { $ungelesen = $imap->status($o['name'])['ungelesen']; } catch (\Throwable $e) {}
                }
                $liste[] = [
                    'name' => $o['name'], 'anzeige' => ORDNER_NAMEN[$o['rolle']] ?? $o['anzeige'],
                    'rolle' => $o['rolle'], 'ebene' => $o['ebene'], 'ungelesen' => $ungelesen,
                ];
            }
            $imap->abmelden();
            antworten(['ordner' => $liste]);
        }

        // Nachrichtenliste eines Ordners (seitenweise, neueste zuerst)
        case 'liste': {
            $konto = MailKonto::laden((int)($_GET['konto'] ?? 0));
            $imap = verbinden($konto);
            $ordner = (string)($_GET['ordner'] ?? 'INBOX');
            $junkVerschoben = 0;
            if (!empty($konto['spamfilter']) && (int)($_GET['seite'] ?? 1) <= 1 && $ordner === ($imap->sonderordner('posteingang') ?? 'INBOX')) {
                try { $junkVerschoben = MailSpamfilter::pruefen($konto, $imap, true); } catch (\Throwable $e) {}
            }
            $imap->waehlen($ordner, true);
            $uids = array_reverse($imap->suchen(trim((string)($_GET['suche'] ?? '')) ?: null, !empty($_GET['ungelesen'])));
            $proSeite = 50;
            $seite = max(1, (int)($_GET['seite'] ?? 1));
            $auswahl = array_slice($uids, ($seite - 1) * $proSeite, $proSeite);
            $uebersicht = $imap->uebersicht($auswahl);
            $imap->abmelden();
            $nachrichten = [];
            foreach ($auswahl as $uid) {
                if (isset($uebersicht[$uid])) $nachrichten[] = $uebersicht[$uid];
            }
            antworten(['nachrichten' => $nachrichten, 'junk_verschoben' => $junkVerschoben, 'gesamt' => count($uids), 'seite' => $seite, 'seiten' => max(1, (int)ceil(count($uids) / $proSeite))]);
        }

        // Eine Nachricht lesen (wird dabei als gelesen markiert)
        case 'nachricht': {
            $konto = MailKonto::laden((int)($_GET['konto'] ?? 0));
            $ordner = (string)($_GET['ordner'] ?? 'INBOX');
            $uid = (int)($_GET['uid'] ?? 0);
            $imap = verbinden($konto);
            $imap->waehlen($ordner);
            $roh = $imap->rohNachricht($uid);
            if ($roh === null) throw new RuntimeException('Die Nachricht gibt es nicht mehr.');
            $imap->kennzeichnen([$uid], '\\Seen', true);
            $imap->abmelden();
            $n = MailNachricht::zerlegen($roh);
            [$html, $extern] = MailNachricht::anzeigeHtml($n, !empty($_GET['bilder']));
            antworten(['nachricht' => [
                'uid' => $uid, 'betreff' => $n['betreff'], 'von' => $n['von'], 'an' => $n['an'], 'cc' => $n['cc'],
                'antwort_an' => $n['antwort_an'], 'datum' => $n['datum'], 'message_id' => $n['message_id'], 'referenzen' => $n['referenzen'],
                'html' => $html, 'externe_bilder' => $extern,
                'zitat_html' => $n['html'] !== null ? MailNachricht::anzeigeHtml($n, false)[0] : nl2br(htmlspecialchars((string)$n['text'])),
                'anhaenge' => array_values(array_filter($n['anhaenge'], fn($a) => !$a['inline'])),
                'junk_grund' => !empty($_GET['junk']) ? MailSpamfilter::grund((int)$konto['id'], $n['message_id']) : null,
            ]]);
        }

        // Anhang herunterladen
        case 'anhang': {
            $konto = MailKonto::laden((int)($_GET['konto'] ?? 0));
            $imap = verbinden($konto);
            $imap->waehlen((string)($_GET['ordner'] ?? 'INBOX'), true);
            $roh = $imap->rohNachricht((int)($_GET['uid'] ?? 0));
            $imap->abmelden();
            $n = $roh !== null ? MailNachricht::zerlegen($roh) : null;
            $teil = $n['teile'][(string)($_GET['teil'] ?? '')] ?? null;
            if (!$teil || $teil['multipart']) {
                http_response_code(404);
                exit('Anhang nicht gefunden');
            }
            $name = '';
            foreach ($n['anhaenge'] as $a) if ($a['teil'] === $_GET['teil']) $name = $a['name'];
            $name = $name ?: 'anhang';
            $ansehen = !empty($_GET['ansehen']) && in_array($teil['typ'], ['application/pdf', 'image/png', 'image/jpeg', 'image/gif'], true);
            header('Content-Type: ' . ($ansehen ? $teil['typ'] : 'application/octet-stream'));
            header('X-Content-Type-Options: nosniff');
            header("Content-Disposition: " . ($ansehen ? 'inline' : 'attachment') . "; filename=\"" . preg_replace('/[^\x20-\x7e]|"/', '_', $name) . "\"; filename*=UTF-8''" . rawurlencode($name));
            header('Content-Length: ' . strlen($teil['daten']));
            echo $teil['daten'];
            exit;
        }

        // Kennzeichen: gelesen, ungelesen, markiert, unmarkiert
        case 'markieren': {
            $konto = MailKonto::laden((int)($_POST['konto'] ?? 0));
            $art = (string)($_POST['art'] ?? '');
            $kz = ['gelesen' => ['\\Seen', true], 'ungelesen' => ['\\Seen', false], 'markiert' => ['\\Flagged', true], 'unmarkiert' => ['\\Flagged', false]][$art] ?? null;
            if (!$kz) throw new RuntimeException('Unbekannte Markierung.');
            $imap = verbinden($konto);
            $imap->waehlen((string)($_POST['ordner'] ?? 'INBOX'));
            $imap->kennzeichnen(uidListe(), $kz[0], $kz[1]);
            $imap->abmelden();
            antworten([]);
        }

        case 'verschieben':
        case 'loeschen': {
            $konto = MailKonto::laden((int)($_POST['konto'] ?? 0));
            $ordner = (string)($_POST['ordner'] ?? 'INBOX');
            $imap = verbinden($konto);
            $imap->waehlen($ordner);
            if ($aktion === 'verschieben') {
                $imap->verschieben(uidListe(), (string)($_POST['ziel'] ?? ''));
            } else {
                $papierkorb = $imap->sonderordner('papierkorb');
                if ($papierkorb === null || $papierkorb === $ordner || !empty($_POST['endgueltig'])) {
                    $imap->endgueltigLoeschen(uidListe());
                } else {
                    $imap->verschieben(uidListe(), $papierkorb);
                }
            }
            $imap->abmelden();
            antworten([]);
        }

        // Neue Nachricht, Antwort oder Weiterleitung senden
        case 'senden': {
            $konto = MailKonto::laden((int)($_POST['konto'] ?? 0));
            [$an, $gruppeAn] = empfaengerLesen((string)($_POST['an'] ?? ''));
            [$cc, $gruppeCc] = empfaengerLesen((string)($_POST['cc'] ?? ''));
            [$bcc, $gruppeBcc] = empfaengerLesen((string)($_POST['bcc'] ?? ''));
            // Gruppen gehen immer in Bcc, damit die Mitglieder ihre Adressen nicht gegenseitig sehen
            $bcc = array_merge($bcc, $gruppeAn, $gruppeCc, $gruppeBcc);
            if (!$an && !$cc && !$bcc) throw new RuntimeException('Bitte mindestens einen Empfänger angeben.');
            if (!$an) $an = [[$konto['email'], $konto['anzeigename'] ?: '']]; // nur Bcc: an sich selbst

            $anhaenge = [];
            $groesse = 0;
            foreach ((array)($_FILES['anhaenge']['tmp_name'] ?? []) as $i => $tmp) {
                if (!is_uploaded_file($tmp) || ($_FILES['anhaenge']['error'][$i] ?? 1) !== UPLOAD_ERR_OK) continue;
                $daten = (string)file_get_contents($tmp);
                $groesse += strlen($daten);
                $anhaenge[] = [basename((string)$_FILES['anhaenge']['name'][$i]), (string)($_FILES['anhaenge']['type'][$i] ?: 'application/octet-stream'), $daten];
            }
            // Bezug zur Original-Nachricht (Antwort / Weiterleitung)
            $bezug = null;
            if (!empty($_POST['bezug_uid'])) {
                $imap = verbinden($konto);
                $imap->waehlen((string)$_POST['bezug_ordner'], true);
                $roh = $imap->rohNachricht((int)$_POST['bezug_uid']);
                $imap->abmelden();
                if ($roh !== null) {
                    $bezug = MailNachricht::zerlegen($roh);
                    // Weiterleiten: Anhänge des Originals mitschicken (außer abgewählten)
                    if (($_POST['modus'] ?? '') === 'weiterleiten') {
                        $weg = array_map('strval', (array)($_POST['ohne_anhang'] ?? []));
                        foreach ($bezug['anhaenge'] as $a) {
                            if ($a['inline'] || in_array($a['teil'], $weg, true)) continue;
                            $anhaenge[] = [$a['name'], $a['typ'], $bezug['teile'][$a['teil']]['daten']];
                            $groesse += $a['groesse'];
                        }
                    }
                }
            }
            if ($groesse > 20 * 1024 * 1024) throw new RuntimeException('Die Anhänge sind zusammen größer als 20 MB.');

            $erg = MailSender::senden($konto, [
                'an' => $an, 'cc' => $cc, 'bcc' => $bcc,
                'betreff' => trim((string)($_POST['betreff'] ?? '')),
                'html' => (string)($_POST['html'] ?? ''),
                'anhaenge' => $anhaenge,
                'antwort_auf' => ($bezug && in_array($_POST['modus'] ?? '', ['antworten', 'allen'], true)) ? $bezug['message_id'] : null,
                'referenzen' => $bezug['referenzen'] ?? '',
                'ablage' => trim((string)($_POST['ablage'] ?? '')),
            ]);
            // Original als beantwortet kennzeichnen
            if ($bezug && in_array($_POST['modus'] ?? '', ['antworten', 'allen'], true)) {
                try {
                    $imap = verbinden($konto);
                    $imap->waehlen((string)$_POST['bezug_ordner']);
                    $imap->kennzeichnen([(int)$_POST['bezug_uid']], '\\Answered', true);
                    $imap->abmelden();
                } catch (\Throwable $e) {}
            }
            MailSpamfilter::empfaengerAlsSicher((int)$konto['id'], array_column(array_merge($an, $cc), 0));
            unset($_SESSION['mail_ungelesen']);
            antworten(['empfaenger' => count($an) + count($cc) + count($bcc), 'gesendet_ordner' => $erg['gesendet_ordner']]);
        }

        // Adressbuch: Mitglieder und Gruppen für die Empfänger-Felder
        case 'adressen': {
            $q = trim((string)($_GET['q'] ?? ''));
            $treffer = [];
            if (mb_strlen($q) >= 2) {
                $like = '%' . $q . '%';
                foreach (MailKontakt::sichtbare('', $q, 10) as $k) {
                    $name = MailKontakt::name($k);
                    $treffer[] = ['wert' => MailNachricht::adresse($k['email'], $name), 'name' => $name,
                        'info' => $k['email'] . ' · Kontakt' . ($k['oeffentlich'] ? '' : ' (privat)'), 'gruppe' => false];
                }
                foreach (gruppen() as [$token, $name, $anzahl]) {
                    if (mb_stripos($name, $q) !== false) $treffer[] = ['wert' => $token, 'name' => $name, 'info' => $anzahl . ' Empfänger (als Bcc)', 'gruppe' => true];
                }
                if (Session::checkPermission('mitglieder', 'lesen')) {
                    foreach (Database::getInstance()->fetchAll(
                        "SELECT vorname, nachname, email FROM mitglieder WHERE email LIKE '%@%' AND mitgliedsart_code IS NOT NULL
                           AND (CONCAT(vorname, ' ', nachname) LIKE ? OR CONCAT(nachname, ' ', vorname) LIKE ? OR email LIKE ?)
                         ORDER BY nachname, vorname LIMIT 15", [$like, $like, $like]) as $m) {
                        $treffer[] = ['wert' => MailNachricht::adresse($m['email'], $m['vorname'] . ' ' . $m['nachname']),
                            'name' => $m['vorname'] . ' ' . $m['nachname'], 'info' => $m['email'], 'gruppe' => false];
                    }
                }
            }
            antworten(['treffer' => array_slice($treffer, 0, 20)]);
        }

        // Ordner anlegen, umbenennen, löschen (auf dem Server – dadurch überall sichtbar)
        case 'ordner_anlegen':
        case 'ordner_umbenennen':
        case 'ordner_loeschen': {
            $konto = MailKonto::laden((int)($_POST['konto'] ?? 0));
            $imap = verbinden($konto);
            $ordner = (string)($_POST['ordner'] ?? '');
            $vorhanden = array_column($imap->ordner(), null, 'name');
            if ($aktion !== 'ordner_anlegen' || $ordner !== '') {
                if (!isset($vorhanden[$ordner])) throw new RuntimeException('Den Ordner gibt es nicht mehr.');
            }
            if ($aktion !== 'ordner_anlegen' && $vorhanden[$ordner]['rolle'] !== null) {
                throw new RuntimeException('„' . (ORDNER_NAMEN[$vorhanden[$ordner]['rolle']] ?? $ordner) . '“ ist ein Standardordner und kann nicht umbenannt oder gelöscht werden.');
            }
            if ($aktion === 'ordner_anlegen') {
                $name = $imap->ordnerAnlegen($ordner !== '' ? $ordner : null, (string)($_POST['name'] ?? ''));
            } elseif ($aktion === 'ordner_umbenennen') {
                $name = $imap->ordnerUmbenennen($ordner, (string)($_POST['name'] ?? ''));
            } else {
                $imap->waehlen($ordner, true);
                $inhalt = count($imap->suchen());
                if ($inhalt > 0 && empty($_POST['mit_inhalt'])) {
                    throw new RuntimeException("Der Ordner enthält noch $inhalt Nachrichten. Bitte zuerst verschieben oder löschen.");
                }
                $imap->ordnerLoeschen($ordner);
                $name = null;
            }
            $imap->abmelden();
            antworten(['ordner' => $name]);
        }

        // Junk-E-Mail: als Junk markieren (verschieben, lernen, optional Absender/Domain blockieren)
        case 'junk':
        case 'kein_junk': {
            $konto = MailKonto::laden((int)($_POST['konto'] ?? 0));
            $uids = uidListe();
            if (!$uids) throw new RuntimeException('Keine Nachricht ausgewählt.');
            $imap = verbinden($konto);
            if ($aktion === 'junk') {
                $blockieren = in_array($_POST['blockieren'] ?? '', ['absender', 'domain'], true) ? $_POST['blockieren'] : '';
                MailSpamfilter::alsJunk($konto, $imap, (string)($_POST['ordner'] ?? 'INBOX'), $uids, $blockieren);
            } else {
                MailSpamfilter::keinJunk($konto, $imap, (string)($_POST['ordner'] ?? 'INBOX'), $uids, ($_POST['sicher'] ?? '1') !== '0');
            }
            $imap->abmelden();
            antworten([]);
        }

        // Junk-E-Mail-Optionen eines Postfachs
        case 'spam_optionen': {
            $konto = MailKonto::laden((int)($_GET['konto'] ?? 0));
            $id = (int)$konto['id'];
            $stand = MailSpamfilter::stand($id);
            $db = Database::getInstance();
            antworten([
                'aktiv' => !empty($konto['spamfilter']),
                'stand' => ['spam' => (int)$stand['spam_mails'], 'ham' => (int)$stand['ham_mails'], 'angelernt' => (bool)$stand['angelernt'], 'letzter_lauf' => $stand['letzter_lauf']],
                'sicher' => array_keys(MailSpamfilter::liste($id, 'sicher')),
                'blockiert' => array_keys(MailSpamfilter::liste($id, 'blockiert')),
                'protokoll' => $db->fetchAll("SELECT absender, betreff, grund, zeit FROM mail_spam_protokoll WHERE konto_id = ? AND grund <> ? ORDER BY id DESC LIMIT 30", [$id, MailSpamfilter::KEIN_JUNK]),
            ]);
        }

        case 'spam_speichern': {
            $konto = MailKonto::laden((int)($_POST['konto'] ?? 0));
            $id = (int)$konto['id'];
            $db = Database::getInstance();
            switch ($_POST['was'] ?? '') {
                case 'aktiv':
                    $db->execute("UPDATE mail_konten SET spamfilter = ? WHERE id = ?", [!empty($_POST['wert']) ? 1 : 0, $id]);
                    break;
                case 'eintragen':
                    MailSpamfilter::listeEintragen($id, (string)($_POST['muster'] ?? ''), (string)($_POST['art'] ?? ''));
                    break;
                case 'entfernen':
                    MailSpamfilter::listeEntfernen($id, (string)($_POST['muster'] ?? ''));
                    break;
                case 'zuruecksetzen':
                    // Gelerntes vergessen; beim nächsten Lauf wird neu angelernt
                    $db->execute("DELETE FROM mail_spam_woerter WHERE konto_id = ?", [$id]);
                    $db->execute("UPDATE mail_spam_stand SET spam_mails = 0, ham_mails = 0, angelernt = 0 WHERE konto_id = ?", [$id]);
                    break;
                default:
                    throw new RuntimeException('Unbekannte Einstellung.');
            }
            antworten([]);
        }

        // Verbindung testen (aus dem Formular, vor dem Speichern)
        case 'testen': {
            $p = $_POST;
            $passwort = (string)($p['passwort'] ?? '');
            if ($passwort === '' && !empty($p['id'])) {
                $passwort = MailKonto::passwort(MailKonto::zumBearbeiten((int)$p['id'])); // prüft die Berechtigung
            }
            if ($passwort === '') throw new RuntimeException('Bitte das Passwort eingeben.');
            $konto = [
                'imap_host' => trim((string)($p['imap_host'] ?? '')), 'imap_port' => (int)($p['imap_port'] ?? 993),
                'imap_sicherheit' => (string)($p['imap_sicherheit'] ?? 'ssl'),
                'smtp_host' => trim((string)($p['smtp_host'] ?? '')), 'smtp_port' => (int)($p['smtp_port'] ?? 465),
                'smtp_sicherheit' => (string)($p['smtp_sicherheit'] ?? 'ssl'),
                'benutzername' => trim((string)($p['benutzername'] ?? '')) ?: trim((string)($p['email'] ?? '')),
            ];
            MailKonto::serverPruefen('imap', $konto['imap_host'], $konto['imap_port']);
            MailKonto::serverPruefen('smtp', $konto['smtp_host'], $konto['smtp_port']);
            antworten(['ergebnis' => MailKonto::testen($konto, $passwort)]);
        }

        // Ungelesene Nachrichten im Posteingang aller eigenen Postfächer (für die Benachrichtigung, 60 s zwischengespeichert)
        case 'ungelesen': {
            $cache = $_SESSION['mail_ungelesen'] ?? null;
            if ($cache && $cache['zeit'] > time() - 60 && empty($_GET['neu'])) {
                antworten($cache['daten']);
            }
            $gesamt = 0;
            $je = [];
            foreach (MailKonto::meine() as $konto) {
                try {
                    $imap = verbinden($konto);
                    if (!empty($konto['spamfilter'])) {
                        try { MailSpamfilter::pruefen($konto, $imap, true); } catch (\Throwable $e) {}
                    }
                    $n = $imap->status($imap->sonderordner('posteingang') ?? 'INBOX')['ungelesen'];
                    $imap->abmelden();
                    $je[$konto['id']] = $n;
                    $gesamt += $n;
                } catch (\Throwable $e) {
                    $je[$konto['id']] = null;
                }
            }
            $daten = ['gesamt' => $gesamt, 'konten' => $je];
            $_SESSION['mail_ungelesen'] = ['zeit' => time(), 'daten' => $daten];
            antworten($daten);
        }

        default:
            throw new RuntimeException('Unbekannte Aktion.');
    }
} catch (RuntimeException $e) {
    if ($aktion === 'anhang') {
        http_response_code(404);
        exit($e->getMessage());
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
