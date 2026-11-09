<?php
// mail_konten.php
// Funktions-Postfächer (z. B. obmann@verein.at): vom Admin eingerichtet und für Rollen freigegeben.
// Alle Benutzer mit einer freigegebenen Rolle sehen das Postfach im E-Mail-Programm.
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();
if (!Session::isAdmin()) {
    Session::setFlashMessage('danger', 'Nur Administratoren haben Zugriff');
    header('Location: index.php');
    exit;
}
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['aktion'] ?? '') === 'postfach_speichern') {
            MailKonto::speichern($_POST, !empty($_POST['id']) ? (int)$_POST['id'] : null, true, (array)($_POST['rollen'] ?? []));
            Session::setFlashMessage('success', 'Funktions-Postfach gespeichert.');
        } elseif (($_POST['aktion'] ?? '') === 'link_adresse') {
            MailFreigabe::adresseSpeichern((string)($_POST['adresse'] ?? ''));
            Session::setFlashMessage('success', 'Adresse für Download-Links gespeichert.');
        } elseif (($_POST['aktion'] ?? '') === 'postfach_loeschen') {
            MailKonto::loeschen((int)($_POST['id'] ?? 0));
            Session::setFlashMessage('success', 'Funktions-Postfach entfernt.');
        }
        unset($_SESSION['mail_ungelesen']);
    } catch (RuntimeException $e) {
        Session::setFlashMessage('danger', $e->getMessage());
    }
    header('Location: mail_konten.php');
    exit;
}

$postfaecher = MailKonto::funktionsPostfaecher();
$formularRollen = $db->fetchAll("SELECT id, name FROM rollen WHERE aktiv = 1 AND name <> 'extern' ORDER BY sortierung, name");

include 'includes/header.php';
?>
<div class="page-header">
    <h1 class="page-title"><i class="bi bi-envelope-at"></i> Funktions-Postfächer</h1>
    <button class="btn btn-primary" onclick="postfachOeffnen(null)"><i class="bi bi-plus-lg"></i> Funktions-Postfach hinzufügen</button>
</div>

<div class="alert alert-permanent alert-secondary small">
    <i class="bi bi-info-circle"></i>
    Funktions-Postfächer gehören dem Verein, nicht einer Person – z. B. <em>obmann@…</em> oder <em>kassier@…</em>.
    Du gibst sie Rollen frei; alle Benutzer mit dieser Rolle sehen das Postfach im <a href="mail.php">E-Mail-Programm</a>.
    Wechselt die Funktion, bekommt der Nachfolger das Postfach automatisch. Persönliche Postfächer trägt jeder unter <a href="konto.php">Mein Konto</a> selbst ein.
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (!$postfaecher): ?>
        <p class="text-muted p-4 mb-0"><i class="bi bi-inbox"></i> Noch kein Funktions-Postfach eingerichtet.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th class="ps-3">Postfach</th><th>Server</th><th>Freigegeben für</th><th class="pe-3"></th></tr></thead>
            <tbody>
            <?php foreach ($postfaecher as $k):
                $daten = array_intersect_key($k, array_flip(['id', 'email', 'anzeigename', 'bezeichnung', 'benutzername', 'imap_host', 'imap_port', 'imap_sicherheit', 'smtp_host', 'smtp_port', 'smtp_sicherheit', 'signatur']));
                $daten['rollen'] = MailKonto::zumBearbeiten((int)$k['id'])['rollen']; ?>
            <tr>
                <td class="ps-3"><div class="fw-semibold"><?php echo htmlspecialchars($k['bezeichnung']); ?></div><div class="small text-muted"><?php echo htmlspecialchars($k['email']); ?></div></td>
                <td class="small text-muted"><?php echo htmlspecialchars($k['imap_host']); ?></td>
                <td><?php foreach ($k['rollen_namen'] as $r): ?><span class="badge bg-secondary me-1"><?php echo htmlspecialchars(ucfirst($r)); ?></span><?php endforeach; ?>
                    <?php if (!$k['rollen_namen']): ?><span class="text-warning small">noch keiner Rolle freigegeben</span><?php endif; ?></td>
                <td class="pe-3 text-end text-nowrap">
                    <button class="btn btn-sm btn-outline-secondary" onclick='postfachOeffnen(<?php echo htmlspecialchars(json_encode($daten, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>)'><i class="bi bi-pencil"></i></button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Funktions-Postfach aus Syncopa entfernen? Die Mails beim Anbieter bleiben erhalten.');">
                        <input type="hidden" name="aktion" value="postfach_loeschen"><input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php $linkAdresse = MailFreigabe::basisAdresse(); $linkOk = MailFreigabe::linksMoeglich(); ?>
<div class="card mt-4" id="download-links">
    <div class="card-header"><h5 class="mb-0"><i class="bi bi-link-45deg"></i> Große Dateien als Download-Link</h5></div>
    <div class="card-body">
        <p class="small text-muted">Dateien über 10 MB (oder über 20 MB zusammen) verschickt Syncopa als Download-Link statt als Anhang. Dafür braucht es die Adresse, unter der Syncopa <strong>von außen</strong> erreichbar ist.</p>
        <?php if (!$linkOk): ?>
        <div class="alert alert-warning alert-permanent small"><i class="bi bi-exclamation-triangle"></i> Derzeit „<?php echo htmlspecialchars($linkAdresse ?: '(keine)'); ?>“: Diese Adresse ist von außen nicht erreichbar. Bis hier eine öffentliche Adresse steht, können keine Download-Links verschickt werden.</div>
        <?php endif; ?>
        <form method="POST" class="row g-2 align-items-end">
            <input type="hidden" name="aktion" value="link_adresse">
            <div class="col-md-8"><label class="form-label small" for="adresse">Öffentliche Adresse von Syncopa</label>
                <input class="form-control" id="adresse" name="adresse" placeholder="https://verein.at/syncopa" value="<?php echo htmlspecialchars($linkOk ? $linkAdresse : ''); ?>"></div>
            <div class="col-md-4"><button class="btn btn-primary w-100"><i class="bi bi-save"></i> Speichern</button></div>
        </form>
        <div class="form-text">Leer lassen, um die Adresse aus config.php (BASE_URL) zu verwenden.</div>
    </div>
</div>

<?php $formularFunktion = true; include 'includes/mail_konto_formular.php'; ?>
<?php include 'includes/footer.php'; ?>
