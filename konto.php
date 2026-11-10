<?php
// konto.php
// „Mein Konto“: jeder Benutzer pflegt hier seine eigenen Daten – E-Mail-Adresse, Passwort und E-Mail-Postfächer.
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
$db = Database::getInstance();
$benutzerId = (int)Session::getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['aktion'] ?? '') {
            case 'profil_speichern':
                $email = trim((string)($_POST['email'] ?? ''));
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Bitte eine gültige E-Mail-Adresse angeben.');
                if ($email !== '' && $db->fetchOne("SELECT id FROM benutzer WHERE email = ? AND id <> ?", [$email, $benutzerId])) {
                    throw new RuntimeException('Diese E-Mail-Adresse verwendet bereits ein anderer Benutzer.');
                }
                $db->execute("UPDATE benutzer SET email = ? WHERE id = ?", [$email ?: null, $benutzerId]);
                Session::setFlashMessage('success', 'Profil gespeichert.');
                break;
            case 'passwort_aendern':
                $b = $db->fetchOne("SELECT passwort_hash FROM benutzer WHERE id = ?", [$benutzerId]);
                if (!$b || !password_verify((string)($_POST['alt'] ?? ''), (string)$b['passwort_hash'])) throw new RuntimeException('Das aktuelle Passwort stimmt nicht.');
                $neu = (string)($_POST['neu'] ?? '');
                if (mb_strlen($neu) < 8) throw new RuntimeException('Das neue Passwort muss mindestens 8 Zeichen lang sein.');
                if ($neu !== (string)($_POST['neu2'] ?? '')) throw new RuntimeException('Die beiden neuen Passwörter stimmen nicht überein.');
                $db->execute("UPDATE benutzer SET passwort_hash = ? WHERE id = ?", [password_hash($neu, PASSWORD_DEFAULT), $benutzerId]);
                Session::setFlashMessage('success', 'Passwort geändert.');
                break;
            case 'postfach_speichern':
                MailKonto::speichern($_POST, !empty($_POST['id']) ? (int)$_POST['id'] : null, false);
                unset($_SESSION['mail_ungelesen']);
                Session::setFlashMessage('success', 'Postfach gespeichert.');
                break;
            case 'postfach_loeschen':
                MailKonto::loeschen((int)($_POST['id'] ?? 0));
                unset($_SESSION['mail_ungelesen']);
                Session::setFlashMessage('success', 'Postfach entfernt.');
                break;
            case 'freigabe_loeschen':
                MailFreigabe::loeschen((int)($_POST['id'] ?? 0));
                Session::setFlashMessage('success', 'Download-Link gelöscht.');
                break;
        }
    } catch (RuntimeException $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: konto.php' . (str_starts_with($_POST['aktion'] ?? '', 'postfach') ? '#postfaecher' : (($_POST['aktion'] ?? '') === 'freigabe_loeschen' ? '#freigaben' : '')));
    exit;
}

$benutzer = $db->fetchOne(
    "SELECT b.*, m.id AS m_id, m.vorname, m.nachname FROM benutzer b
     LEFT JOIN mitglieder m ON m.id = b.mitglied_id OR m.benutzer_id = b.id WHERE b.id = ? LIMIT 1", [$benutzerId]);
$rollen = array_column($db->fetchAll(
    "SELECT r.name FROM benutzer_rollen br JOIN rollen r ON r.id = br.rolle_id WHERE br.benutzer_id = ? ORDER BY r.name", [$benutzerId]), 'name');
$postfaecher = MailKonto::meine();
$eigene = array_filter($postfaecher, fn($k) => !$k['funktion']);
$funktion = array_filter($postfaecher, fn($k) => $k['funktion']);

include 'includes/header.php';
?>
<div class="page-header">
    <h1 class="page-title"><i class="bi bi-person-circle"></i> Mein Konto</h1>
</div>

<div class="row">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Profil</h5></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="aktion" value="profil_speichern">
                    <dl class="row small mb-3">
                        <dt class="col-sm-4">Benutzername</dt><dd class="col-sm-8"><?php echo htmlspecialchars($benutzer['benutzername']); ?></dd>
                        <dt class="col-sm-4">Rollen</dt><dd class="col-sm-8"><?php echo htmlspecialchars(implode(', ', array_map('ucfirst', $rollen)) ?: ucfirst(Session::getRole())); ?></dd>
                        <?php if (!empty($benutzer['m_id'])): ?>
                        <dt class="col-sm-4">Mitglied</dt><dd class="col-sm-8"><a href="mitglied_detail.php?id=<?php echo (int)$benutzer['m_id']; ?>"><?php echo htmlspecialchars($benutzer['vorname'] . ' ' . $benutzer['nachname']); ?></a></dd>
                        <?php endif; ?>
                        <?php if (!empty($benutzer['letzter_login'])): ?>
                        <dt class="col-sm-4">Letzte Anmeldung</dt><dd class="col-sm-8"><?php echo date('d.m.Y H:i', strtotime($benutzer['letzter_login'])); ?></dd>
                        <?php endif; ?>
                    </dl>
                    <label class="form-label" for="email">E-Mail-Adresse</label>
                    <div class="input-group">
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($benutzer['email'] ?? ''); ?>">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Speichern</button>
                    </div>
                    <div class="form-text">Für die Anmeldung mit Google und Benachrichtigungen.</div>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">Passwort ändern</h5></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="aktion" value="passwort_aendern">
                    <div class="mb-2"><label class="form-label" for="alt">Aktuelles Passwort</label><input type="password" class="form-control" id="alt" name="alt" required autocomplete="current-password"></div>
                    <div class="mb-2"><label class="form-label" for="neu">Neues Passwort</label><input type="password" class="form-control" id="neu" name="neu" required minlength="8" autocomplete="new-password"><div class="form-text">Mindestens 8 Zeichen.</div></div>
                    <div class="mb-3"><label class="form-label" for="neu2">Neues Passwort wiederholen</label><input type="password" class="form-control" id="neu2" name="neu2" required minlength="8" autocomplete="new-password"></div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-key"></i> Passwort ändern</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3" id="postfaecher">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-envelope"></i> E-Mail-Postfächer</h5>
                <button class="btn btn-sm btn-primary" onclick="postfachOeffnen(null)"><i class="bi bi-plus-lg"></i> Postfach hinzufügen</button>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Deine eigenen Postfächer kannst du im <a href="mail.php">E-Mail-Programm</a> lesen und von dort aus schreiben.
                    Das Passwort wird verschlüsselt gespeichert und nur für den Abruf verwendet.
                </p>
                <?php if (!$eigene): ?>
                <p class="text-muted mb-0"><i class="bi bi-inbox"></i> Noch kein eigenes Postfach eingerichtet.</p>
                <?php else: ?>
                <div class="list-group">
                    <?php foreach ($eigene as $k):
                        $daten = array_intersect_key($k, array_flip(['id', 'email', 'anzeigename', 'bezeichnung', 'benutzername', 'imap_host', 'imap_port', 'imap_sicherheit', 'smtp_host', 'smtp_port', 'smtp_sicherheit', 'signatur'])); ?>
                    <div class="list-group-item d-flex align-items-center gap-3">
                        <i class="bi bi-envelope-at fs-4 text-primary"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate"><?php echo htmlspecialchars($k['bezeichnung']); ?></div>
                            <div class="small text-muted text-truncate"><?php echo htmlspecialchars($k['email'] . ' · ' . $k['imap_host']); ?></div>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" onclick='postfachOeffnen(<?php echo htmlspecialchars(json_encode($daten, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>)'><i class="bi bi-pencil"></i></button>
                        <form method="POST" onsubmit="return confirm('Postfach aus Syncopa entfernen? Die Mails beim Anbieter bleiben erhalten.');">
                            <input type="hidden" name="aktion" value="postfach_loeschen"><input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($funktion): ?>
                <h6 class="mt-4">Über deine Rolle freigegeben</h6>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($funktion as $k): ?>
                    <li class="mb-1"><i class="bi bi-people text-primary"></i> <?php echo htmlspecialchars($k['bezeichnung']); ?> <span class="text-muted">&lt;<?php echo htmlspecialchars($k['email']); ?>&gt;</span></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php if (Session::isAdmin()): ?>
                <p class="small mt-3 mb-0"><a href="mail_konten.php"><i class="bi bi-gear"></i> Funktions-Postfächer für Rollen verwalten</a></p>
                <?php endif; ?>
            </div>
        </div>

        <?php $freigaben = MailFreigabe::meine(); if ($freigaben): ?>
        <div class="card mb-3" id="freigaben">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-link-45deg"></i> Meine Download-Links</h5></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($freigaben as $fr): $abgelaufen = $fr['gueltig_bis'] && strtotime($fr['gueltig_bis']) < time(); ?>
                <li class="list-group-item d-flex flex-wrap align-items-center gap-2 px-3 py-2">
                    <div class="flex-grow-1" style="min-width:200px">
                        <div class="text-truncate"><i class="bi bi-file-earmark me-1"></i> <?php echo htmlspecialchars($fr['dateiname']); ?></div>
                        <div class="small text-muted"><?php echo MailFreigabe::groesseText((int)$fr['groesse']); ?> ·
                            <?php echo $fr['gueltig_bis'] ? ($abgelaufen ? 'abgelaufen' : 'gültig bis ' . date('d.m.Y', strtotime($fr['gueltig_bis']))) : 'noch nicht versendet'; ?> ·
                            <?php echo (int)$fr['downloads']; ?>× heruntergeladen</div>
                    </div>
                    <?php if ($fr['gueltig_bis'] && !$abgelaufen): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars(MailFreigabe::link($fr['token'])); ?>').then(() => window.showToast && showToast('Link kopiert', 'success'))" title="Link kopieren"><i class="bi bi-clipboard"></i></button>
                    <?php endif; ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Download-Link löschen? Die Datei ist danach nicht mehr abrufbar.')">
                        <input type="hidden" name="aktion" value="freigabe_loeschen"><input type="hidden" name="id" value="<?php echo $fr['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Löschen"><i class="bi bi-trash"></i></button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-footer small text-muted">Abgelaufene Links und ihre Dateien löscht Syncopa automatisch.</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $formularFunktion = false; include 'includes/mail_konto_formular.php'; ?>
<?php include 'includes/footer.php'; ?>
