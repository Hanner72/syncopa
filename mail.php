<?php
// mail.php
// E-Mail-Programm im Outlook-Stil: Ordner | Nachrichtenliste | Lesebereich (bzw. Schreiben).
// Daten kommen über api/mail.php; Postfächer werden unter „Mein Konto“ bzw. (Funktions-Postfächer) vom Admin eingerichtet.
require_once 'config.php';
require_once 'includes.php';

Session::requireLogin();

$konten = array_map(fn($k) => [
    'id' => (int)$k['id'], 'bezeichnung' => $k['bezeichnung'], 'email' => $k['email'], 'anzeigename' => $k['anzeigename'],
    'funktion' => (bool)$k['funktion'], 'signatur' => $k['signatur'] ?? '',
], MailKonto::meine());
$adressbuch = Session::checkPermission('mitglieder', 'lesen');

include 'includes/header.php';
?>
<style>
    .mail-app { --m-akzent: #0f6cbd; --m-akzent-hell: rgba(15,108,189,.10); --m-auswahl: rgba(15,108,189,.16);
        display: grid; grid-template-rows: auto 1fr; height: calc(100vh - var(--topbar-h, 56px) - 40px); min-height: 520px;
        background: var(--bg-card); border-radius: 10px; box-shadow: var(--shadow-sm); overflow: hidden; color: var(--text-primary); }
    [data-theme="dark"] .mail-app { --m-akzent: #479ef5; --m-akzent-hell: rgba(71,158,245,.12); --m-auswahl: rgba(71,158,245,.22); }
    .mail-befehle { display: flex; align-items: center; gap: 2px; padding: 6px 10px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
    .mail-befehle .btn-cmd { border: 0; background: transparent; color: var(--text-primary); padding: 5px 9px; border-radius: 5px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    .mail-befehle .btn-cmd:hover:not(:disabled) { background: var(--m-akzent-hell); }
    .mail-befehle .btn-cmd:disabled { opacity: .4; }
    .mail-befehle .btn-cmd i { font-size: 15px; color: var(--m-akzent); }
    .mail-befehle .btn-neu { background: var(--m-akzent); color: #fff; font-weight: 600; padding: 6px 14px; }
    .mail-befehle .btn-neu i { color: #fff; }
    .mail-befehle .btn-neu:hover { background: var(--m-akzent) !important; filter: brightness(1.1); }
    .mail-befehle .trenner { width: 1px; height: 22px; background: var(--border); margin: 0 6px; }
    .mail-haupt { display: grid; grid-template-columns: 230px 380px 1fr; min-height: 0; }
    .mail-ordner, .mail-liste { border-right: 1px solid var(--border); overflow-y: auto; min-height: 0; }
    .mail-ordner { padding: 8px 0; font-size: 13.5px; }
    .mail-konto-kopf { padding: 8px 14px 4px; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; }
    .mail-konto-kopf .bi-chevron-down { transition: transform .15s; font-size: 11px; }
    .mail-konto.zu .mail-konto-kopf .bi-chevron-down { transform: rotate(-90deg); }
    .mail-konto.zu .mail-ordnerliste { display: none; }
    .mail-ordner-eintrag { display: flex; align-items: center; gap: 9px; padding: 6px 14px 6px 22px; cursor: pointer; border-left: 3px solid transparent; }
    .mail-ordner-eintrag:hover { background: var(--m-akzent-hell); }
    .mail-ordner-eintrag.aktiv { background: var(--m-auswahl); border-left-color: var(--m-akzent); font-weight: 600; }
    .mail-ordner-eintrag .zahl { color: var(--m-akzent); font-weight: 600; font-size: 12.5px; }
    .mail-ordner-eintrag.drop-ziel { outline: 2px dashed var(--m-akzent); outline-offset: -2px; }
    .mail-ordner-eintrag .mehr, .mail-konto-kopf .neu { border: 0; background: none; color: var(--text-secondary); padding: 0 4px; visibility: hidden; line-height: 1; }
    .mail-ordner-eintrag:hover .mehr, .mail-konto-kopf:hover .neu { visibility: visible; }
    .mail-ordner-eintrag > span:not(.zahl) { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    @media (hover: none) { .mail-ordner-eintrag .mehr, .mail-konto-kopf .neu { visibility: visible; } }
    .mail-kontextmenue { position: fixed; z-index: 1080; min-width: 200px; }
    #verschiebenMenue { max-height: 60vh; overflow-y: auto; }
    .ablage-wahl { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-secondary); }
    .ablage-wahl select { width: auto; max-width: 220px; }
    .mail-liste-kopf { position: sticky; top: 0; background: var(--bg-card); z-index: 2; padding: 10px 14px 6px; border-bottom: 1px solid var(--border-light); }
    .mail-liste-kopf h6 { margin: 0 0 6px; font-weight: 600; font-size: 15px; }
    .mail-filter button { border: 0; background: none; font-size: 13px; padding: 2px 8px; color: var(--text-secondary); border-bottom: 2px solid transparent; }
    .mail-filter button.aktiv { color: var(--m-akzent); border-bottom-color: var(--m-akzent); font-weight: 600; }
    .mail-gruppe { padding: 8px 14px 4px; font-size: 12px; font-weight: 600; color: var(--text-secondary); background: var(--bg-body); }
    .mail-zeile { display: grid; grid-template-columns: 1fr auto; gap: 2px 8px; padding: 9px 14px 9px 13px; border-bottom: 1px solid var(--border-light);
        border-left: 3px solid transparent; cursor: pointer; font-size: 13px; }
    .mail-zeile:hover { background: var(--m-akzent-hell); }
    .mail-zeile.ungelesen { border-left-color: var(--m-akzent); }
    .mail-zeile.ungelesen .absender, .mail-zeile.ungelesen .betreff { font-weight: 700; }
    .mail-zeile.ungelesen .betreff { color: var(--m-akzent); }
    .mail-zeile.ausgewaehlt { background: var(--m-auswahl); }
    .mail-zeile .absender { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 14px; }
    .mail-zeile .datum { font-size: 12px; color: var(--text-secondary); text-align: right; white-space: nowrap; }
    .mail-zeile .betreff { grid-column: 1 / 2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mail-zeile .symbole { color: var(--text-secondary); font-size: 12px; text-align: right; }
    .mail-zeile .symbole .bi-flag-fill { color: #c50f1f; }
    .mail-lesen { overflow-y: auto; min-height: 0; display: flex; flex-direction: column; }
    .mail-leer { margin: auto; text-align: center; color: var(--text-secondary); padding: 30px; }
    .mail-leer i { font-size: 54px; color: var(--m-akzent); opacity: .5; }
    .mail-kopf { padding: 16px 22px 10px; border-bottom: 1px solid var(--border-light); }
    .mail-kopf h4 { font-size: 19px; font-weight: 600; margin-bottom: 14px; word-break: break-word; }
    .mail-avatar { width: 40px; height: 40px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
    .mail-kopf .meta { font-size: 13px; color: var(--text-secondary); }
    .mail-aktionen .btn { font-size: 13px; }
    .mail-anhaenge { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px 22px; border-bottom: 1px solid var(--border-light); }
    .mail-anhang { display: flex; align-items: center; gap: 8px; border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; font-size: 12.5px;
        color: var(--text-primary); text-decoration: none; max-width: 260px; }
    .mail-anhang:hover { background: var(--m-akzent-hell); color: var(--text-primary); }
    .mail-anhang i { font-size: 20px; color: var(--m-akzent); }
    .mail-anhang .n { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mail-anhang .g { color: var(--text-secondary); font-size: 11px; }
    .mail-inhalt { padding: 12px 22px 22px; flex: 1; }
    .mail-inhalt iframe { width: 100%; border: 0; background: #fff; border-radius: 4px; min-height: 200px; }
    .mail-hinweis { background: var(--m-akzent-hell); font-size: 12.5px; padding: 7px 22px; }
    .mail-hinweis-junk { display: flex; gap: 8px; align-items: center; background: rgba(255, 193, 7, .18); border-bottom: 1px solid rgba(255, 193, 7, .45); }
    /* Schreiben */
    .mail-schreiben { display: flex; flex-direction: column; height: 100%; }
    .mail-schreiben .zeile { display: flex; align-items: flex-start; gap: 8px; padding: 4px 22px; border-bottom: 1px solid var(--border-light); }
    .mail-schreiben .zeile > label { width: 64px; padding-top: 7px; font-size: 13px; color: var(--text-secondary); flex-shrink: 0; }
    .mail-schreiben .zeile input, .mail-schreiben .zeile select { border: 0; background: transparent; color: var(--text-primary); width: 100%; padding: 6px 0; outline: none; font-size: 14px; }
    .chips { display: flex; flex-wrap: wrap; gap: 4px; flex: 1; position: relative; padding: 3px 0; }
    .chips input { flex: 1; min-width: 160px; }
    .chip { background: var(--m-akzent-hell); border-radius: 12px; padding: 2px 6px 2px 10px; font-size: 13px; display: inline-flex; align-items: center; gap: 4px; }
    .chip.gruppe { background: rgba(91,138,114,.18); }
    .chip.falsch { background: rgba(197,15,31,.15); }
    .chip button { border: 0; background: none; padding: 0 2px; color: var(--text-secondary); line-height: 1; }
    .vorschlaege { position: absolute; top: 100%; left: 0; z-index: 30; background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px;
        box-shadow: 0 6px 18px rgba(0,0,0,.15); min-width: 320px; max-height: 280px; overflow-y: auto; }
    .vorschlaege div { padding: 6px 12px; cursor: pointer; font-size: 13px; }
    .vorschlaege div.aktiv, .vorschlaege div:hover { background: var(--m-akzent-hell); }
    .vorschlaege small { display: block; color: var(--text-secondary); }
    .editor-leiste { display: flex; gap: 2px; padding: 6px 18px; border-bottom: 1px solid var(--border-light); flex-wrap: wrap; }
    .editor-leiste button { border: 0; background: none; color: var(--text-primary); padding: 4px 8px; border-radius: 4px; }
    .editor-leiste button:hover { background: var(--m-akzent-hell); }
    .mail-editor { flex: 1; min-height: 220px; padding: 14px 22px; outline: none; overflow-y: auto; font-family: 'Segoe UI', Arial, sans-serif; font-size: 14px; }
    .mail-editor blockquote { border-left: 2px solid var(--border); margin: 0; padding-left: 12px; color: var(--text-secondary); }
    .mail-schreiben-fuss { display: flex; align-items: center; gap: 8px; padding: 10px 22px; border-top: 1px solid var(--border); flex-wrap: wrap; }
    .mail-zurueck { display: none; }
    @media (max-width: 991px) {
        .mail-app { height: calc(100vh - var(--topbar-h, 56px) - 24px); }
        .mail-haupt { grid-template-columns: 1fr; }
        .mail-ordner, .mail-liste, .mail-lesen { display: none; border-right: 0; }
        .mail-app[data-ansicht="ordner"] .mail-ordner, .mail-app[data-ansicht="liste"] .mail-liste, .mail-app[data-ansicht="lesen"] .mail-lesen { display: flex; flex-direction: column; }
        .mail-app[data-ansicht="ordner"] .mail-ordner { display: block; }
        .mail-app[data-ansicht="liste"] .mail-liste { display: block; }
        .mail-zurueck { display: inline-flex; }
        .mail-befehle .btn-cmd span { display: none; }
    }
</style>

<?php if (!$konten): ?>
<div class="page-header"><h1 class="page-title"><i class="bi bi-envelope"></i> E-Mail</h1></div>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-envelope-plus" style="font-size:48px;color:var(--c-primary)"></i>
        <h5 class="mt-3">Noch kein Postfach eingerichtet</h5>
        <p class="text-muted">Trag unter <strong>Mein Konto</strong> dein E-Mail-Postfach ein – danach kannst du hier Mails lesen und schreiben.
            <?php if (Session::isAdmin()): ?><br>Funktions-Postfächer (z. B. obmann@…) für Rollen richtest du unter <a href="mail_konten.php">Funktions-Postfächer</a> ein.<?php endif; ?></p>
        <a href="konto.php#postfaecher" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Postfach einrichten</a>
    </div>
</div>
<?php else: ?>
<div class="mail-app" id="mailApp" data-ansicht="liste">
    <div class="mail-befehle">
        <button class="btn-cmd btn-neu" id="cmdNeu"><i class="bi bi-pencil-square"></i><span>Neue E-Mail</span></button>
        <div class="trenner"></div>
        <button class="btn-cmd mail-zurueck" id="cmdZurueck" title="Zurück"><i class="bi bi-arrow-left"></i></button>
        <button class="btn-cmd" id="cmdLoeschen" data-auswahl disabled title="Löschen (Entf)"><i class="bi bi-trash"></i><span>Löschen</span></button>
        <button class="btn-cmd" id="cmdArchiv" data-auswahl disabled title="Archivieren"><i class="bi bi-archive"></i><span>Archivieren</span></button>
        <div class="dropdown d-inline-block">
            <button class="btn-cmd dropdown-toggle" id="cmdVerschieben" data-auswahl disabled data-bs-toggle="dropdown"><i class="bi bi-folder-symlink"></i><span>Verschieben nach</span></button>
            <ul class="dropdown-menu" id="verschiebenMenue"></ul>
        </div>
        <div class="dropdown d-inline-block">
            <button class="btn-cmd dropdown-toggle" id="cmdJunk" data-bs-toggle="dropdown" title="Junk-E-Mail"><i class="bi bi-shield-exclamation"></i><span>Junk</span></button>
            <ul class="dropdown-menu" id="junkMenue"></ul>
        </div>
        <div class="trenner"></div>
        <button class="btn-cmd" id="cmdAntworten" data-einzeln disabled><i class="bi bi-reply"></i><span>Antworten</span></button>
        <button class="btn-cmd" id="cmdAllen" data-einzeln disabled><i class="bi bi-reply-all"></i><span>Allen antworten</span></button>
        <button class="btn-cmd" id="cmdWeiter" data-einzeln disabled><i class="bi bi-forward"></i><span>Weiterleiten</span></button>
        <div class="trenner"></div>
        <button class="btn-cmd" id="cmdGelesen" data-auswahl disabled><i class="bi bi-envelope-open"></i><span>Gelesen/Ungelesen</span></button>
        <button class="btn-cmd" id="cmdFlagge" data-auswahl disabled><i class="bi bi-flag"></i><span>Kennzeichnen</span></button>
        <div class="trenner"></div>
        <button class="btn-cmd" id="cmdAktualisieren" title="Aktualisieren"><i class="bi bi-arrow-clockwise"></i><span>Aktualisieren</span></button>
    </div>
    <div class="mail-haupt">
        <nav class="mail-ordner" id="ordnerBereich"></nav>
        <section class="mail-liste" id="listenBereich">
            <div class="mail-liste-kopf">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <button class="btn btn-sm btn-link p-0 mail-zurueck" id="listeZurueck" title="Ordner"><i class="bi bi-folder2"></i></button>
                    <h6 class="flex-grow-1" id="listenTitel">Posteingang</h6>
                </div>
                <input type="search" class="form-control form-control-sm mb-2" id="mailSuche" placeholder="Suchen">
                <div class="mail-filter"><button class="aktiv" data-filter="">Alle</button><button data-filter="1">Ungelesen</button></div>
            </div>
            <div id="mailListe"></div>
        </section>
        <section class="mail-lesen" id="leseBereich">
            <div class="mail-leer"><i class="bi bi-envelope-open"></i><p class="mt-2 mb-0">Wählen Sie ein Element aus, um es zu lesen.</p></div>
        </section>
    </div>
</div>

<div class="modal fade" id="junkModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-shield-exclamation"></i> Junk-E-Mail-Optionen <small class="text-muted" data-junk-konto></small></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#junkAllgemein" type="button">Optionen</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#junkSicher" type="button">Sichere Absender</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#junkBlockiert" type="button">Blockierte Absender</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#junkProtokoll" type="button">Aussortiert</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="junkAllgemein">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="junkAktiv">
                            <label class="form-check-label" for="junkAktiv"><strong>Spamfilter für dieses Postfach einschalten</strong></label>
                        </div>
                        <p class="small text-muted mb-2">Neue Nachrichten im Posteingang werden geprüft, sobald jemand das Postfach öffnet und zusätzlich alle 5 Minuten im Hintergrund. Junk wird in den Ordner <strong>Junk-E-Mail</strong> verschoben, nichts wird gelöscht.</p>
                        <ol class="small mb-3">
                            <li><strong>Sichere Absender</strong> kommen immer durch: Adressen von Mitgliedern und Benutzern in Syncopa, eigene Postfächer, Empfänger Ihrer gesendeten Mails und die Liste „Sichere Absender“.</li>
                            <li><strong>Blockierte Absender</strong> landen immer im Junk.</li>
                            <li>Was der <strong>Mailserver</strong> als Spam kennzeichnet, kommt in den Junk.</li>
                            <li>Der <strong>Lernfilter</strong> lernt aus „Junk-E-Mail“ und „Kein Junk“ und entscheidet selbst, sobald er genug Beispiele kennt.</li>
                        </ol>
                        <div class="alert alert-light border alert-permanent small mb-2" data-junk-stand></div>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-junk-zuruecksetzen><i class="bi bi-arrow-counterclockwise"></i> Gelerntes zurücksetzen</button>
                    </div>
                    <div class="tab-pane fade" id="junkSicher" data-junk-liste="sicher"></div>
                    <div class="tab-pane fade" id="junkBlockiert" data-junk-liste="blockiert"></div>
                    <div class="tab-pane fade" id="junkProtokoll"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="ordnerNameModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Ordner</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><input type="text" class="form-control" maxlength="100" placeholder="Ordnername" required></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button><button type="submit" class="btn btn-primary">OK</button></div>
        </form>
    </div>
</div>

<template id="vorlageSchreiben">
    <form class="mail-schreiben" autocomplete="off">
        <div class="zeile"><label>Von</label>
            <select name="konto"><?php foreach ($konten as $k): ?><option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars(($k['anzeigename'] ? $k['anzeigename'] . ' – ' : '') . $k['email']); ?></option><?php endforeach; ?></select>
        </div>
        <div class="zeile"><label>An</label><div class="chips" data-feld="an"><input type="text" placeholder="<?php echo $adressbuch ? 'Name, Adresse oder Gruppe' : 'E-Mail-Adresse'; ?>"></div>
            <button type="button" class="btn btn-sm btn-link" data-zeige="ccbcc">Cc/Bcc</button></div>
        <div class="zeile d-none" data-zeile="cc"><label>Cc</label><div class="chips" data-feld="cc"><input type="text"></div></div>
        <div class="zeile d-none" data-zeile="bcc"><label>Bcc</label><div class="chips" data-feld="bcc"><input type="text"></div></div>
        <div class="zeile"><label>Betreff</label><input type="text" name="betreff" placeholder="Betreff hinzufügen"></div>
        <div class="editor-leiste">
            <button type="button" data-befehl="bold" title="Fett"><i class="bi bi-type-bold"></i></button>
            <button type="button" data-befehl="italic" title="Kursiv"><i class="bi bi-type-italic"></i></button>
            <button type="button" data-befehl="underline" title="Unterstrichen"><i class="bi bi-type-underline"></i></button>
            <button type="button" data-befehl="insertUnorderedList" title="Aufzählung"><i class="bi bi-list-ul"></i></button>
            <button type="button" data-befehl="insertOrderedList" title="Nummerierung"><i class="bi bi-list-ol"></i></button>
            <button type="button" data-befehl="createLink" title="Link"><i class="bi bi-link-45deg"></i></button>
            <button type="button" data-befehl="removeFormat" title="Formatierung entfernen"><i class="bi bi-eraser"></i></button>
        </div>
        <div class="mail-editor" contenteditable="true"></div>
        <div class="mail-anhaenge d-none" data-anhaenge></div>
        <div class="mail-schreiben-fuss">
            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Senden</button>
            <label class="btn btn-outline-secondary mb-0"><i class="bi bi-paperclip"></i> Datei anfügen<input type="file" multiple hidden data-dateien></label>
            <span class="text-muted small ms-2" data-status></span>
            <label class="ablage-wahl ms-auto mb-0" title="In welchem Ordner die gesendete Nachricht gespeichert wird">
                <i class="bi bi-folder-check"></i> Gesendete speichern in
                <select class="form-select form-select-sm" data-ablage></select>
            </label>
            <button type="button" class="btn btn-outline-secondary" data-verwerfen><i class="bi bi-trash"></i> Verwerfen</button>
        </div>
    </form>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const KONTEN = <?php echo json_encode($konten, JSON_UNESCAPED_UNICODE); ?>;
    const ADRESSBUCH = <?php echo $adressbuch ? 'true' : 'false'; ?>;
    const app = document.getElementById('mailApp');
    const ordnerBereich = document.getElementById('ordnerBereich');
    const mailListe = document.getElementById('mailListe');
    const leseBereich = document.getElementById('leseBereich');
    const zustand = { konto: KONTEN[0].id, ordner: 'INBOX', rolle: 'posteingang', ordnerListe: {}, nachrichten: [], auswahl: [], offen: null, suche: '', ungelesen: '' };
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const ORDNER_SYMBOL = { posteingang: 'bi-inbox', entwuerfe: 'bi-file-earmark-text', gesendet: 'bi-send', papierkorb: 'bi-trash', spam: 'bi-exclamation-octagon', archiv: 'bi-archive' };

    function api(aktion, daten = {}, post = false) {
        if (post) {
            const fd = daten instanceof FormData ? daten : new FormData();
            if (!(daten instanceof FormData)) Object.entries(daten).forEach(([k, v]) => Array.isArray(v) ? v.forEach(x => fd.append(k + '[]', x)) : fd.append(k, v));
            fd.append('aktion', aktion);
            return fetch('api/mail.php', { method: 'POST', body: fd }).then(r => r.json());
        }
        return fetch('api/mail.php?' + new URLSearchParams({ aktion, ...daten })).then(r => r.json());
    }
    function meldung(text, typ = 'danger') { if (window.showToast) showToast(text, typ); else alert(text); }
    function ansicht(a) { app.dataset.ansicht = a; }

    // ---------------- Ordner ----------------
    function ordnerZeichnen() {
        ordnerBereich.innerHTML = KONTEN.map(k => {
            const liste = zustand.ordnerListe[k.id];
            const ordner = liste === undefined ? '<div class="px-4 py-2 small text-muted">Lädt …</div>'
                : liste.fehler ? `<div class="px-4 py-2 small text-danger">${esc(liste.fehler)}</div>`
                : liste.map(o => `<div class="mail-ordner-eintrag ${k.id === zustand.konto && o.name === zustand.ordner ? 'aktiv' : ''}"
                      data-konto="${k.id}" data-ordner="${esc(o.name)}" data-rolle="${o.rolle || ''}" style="padding-left:${22 + 14 * Math.min(o.ebene, 3)}px">
                      <i class="bi ${ORDNER_SYMBOL[o.rolle] || 'bi-folder2'}"></i><span>${esc(o.anzeige)}</span>
                      ${o.ungelesen ? `<span class="zahl">${o.ungelesen}</span>` : ''}
                      <button type="button" class="mehr" data-menue title="Ordner-Aktionen"><i class="bi bi-three-dots"></i></button></div>`).join('');
            return `<div class="mail-konto"><div class="mail-konto-kopf" title="${esc(k.email)}"><i class="bi bi-chevron-down"></i>
                ${k.funktion ? '<i class="bi bi-people" title="Funktions-Postfach"></i>' : ''}<span class="text-truncate">${esc(k.bezeichnung)}</span>
                <button type="button" class="neu ms-auto" data-neuer-ordner="${k.id}" title="Neuer Ordner"><i class="bi bi-folder-plus"></i></button></div>
                <div class="mail-ordnerliste">${ordner}</div></div>`;
        }).join('');
        verschiebenMenueZeichnen();
    }
    function ordnerLaden(kontoId) {
        return api('ordner', { konto: kontoId }).then(d => {
            zustand.ordnerListe[kontoId] = d.success ? d.ordner : { fehler: d.error };
            ordnerZeichnen();
        });
    }
    ordnerBereich.addEventListener('click', e => {
        const neu = e.target.closest('[data-neuer-ordner]');
        if (neu) { e.stopPropagation(); ordnerNeu(+neu.dataset.neuerOrdner, ''); return; }
        const menueKnopf = e.target.closest('[data-menue]');
        if (menueKnopf) { e.stopPropagation(); const r = menueKnopf.getBoundingClientRect(); kontextmenue(menueKnopf.closest('.mail-ordner-eintrag'), r.left, r.bottom); return; }
        const kopf = e.target.closest('.mail-konto-kopf');
        if (kopf) { kopf.parentElement.classList.toggle('zu'); return; }
        const o = e.target.closest('.mail-ordner-eintrag');
        if (!o) return;
        zustand.konto = +o.dataset.konto; zustand.ordner = o.dataset.ordner; zustand.rolle = o.dataset.rolle;
        document.getElementById('listenTitel').textContent = o.querySelector('span').textContent;
        zustand.suche = ''; document.getElementById('mailSuche').value = '';
        ordnerZeichnen(); listeLaden(); leer(); ansicht('liste');
    });
    // Nachrichten per Ziehen in einen Ordner verschieben
    ordnerBereich.addEventListener('dragover', e => { const o = e.target.closest('.mail-ordner-eintrag'); if (o && +o.dataset.konto === zustand.konto) { e.preventDefault(); o.classList.add('drop-ziel'); } });
    ordnerBereich.addEventListener('dragleave', e => e.target.closest('.mail-ordner-eintrag')?.classList.remove('drop-ziel'));
    ordnerBereich.addEventListener('drop', e => {
        const o = e.target.closest('.mail-ordner-eintrag'); if (!o) return; e.preventDefault(); o.classList.remove('drop-ziel');
        if (o.dataset.ordner !== zustand.ordner) verschieben(o.dataset.ordner);
    });

    // ---------------- Liste ----------------
    function gruppeVon(datum) {
        if (!datum) return 'Älter';
        const d = new Date(datum.replace(' ', 'T')), h = new Date(); h.setHours(0, 0, 0, 0);
        const tage = Math.floor((h - new Date(d.getFullYear(), d.getMonth(), d.getDate())) / 864e5);
        if (tage <= 0) return 'Heute';
        if (tage === 1) return 'Gestern';
        if (tage < 7) return 'Diese Woche';
        if (tage < 14) return 'Letzte Woche';
        if (d.getFullYear() === h.getFullYear() && d.getMonth() === h.getMonth()) return 'Diesen Monat';
        return 'Älter';
    }
    function zeitText(datum) {
        if (!datum) return '';
        const d = new Date(datum.replace(' ', 'T')), h = new Date();
        if (d.toDateString() === h.toDateString()) return d.toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit' });
        if (h - d < 6 * 864e5) return d.toLocaleDateString('de-AT', { weekday: 'short' }) + ' ' + d.toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit' });
        return d.toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: d.getFullYear() === h.getFullYear() ? undefined : '2-digit' });
    }
    function personText(liste) { return (liste || []).map(a => a[1] || a[0]).join(', '); }
    function listeLaden(seite = 1) {
        if (seite === 1) mailListe.innerHTML = '<div class="p-4 text-center text-muted small"><span class="spinner-border spinner-border-sm"></span> Lädt …</div>';
        zustand.auswahl = []; knoepfe();
        return api('liste', { konto: zustand.konto, ordner: zustand.ordner, seite, suche: zustand.suche, ungelesen: zustand.ungelesen }).then(d => {
            if (!d.success) { mailListe.innerHTML = `<div class="p-4 text-danger small">${esc(d.error)}</div>`; return; }
            zustand.nachrichten = seite === 1 ? d.nachrichten : zustand.nachrichten.concat(d.nachrichten);
            zustand.seite = d.seite; zustand.seiten = d.seiten;
            listeZeichnen();
            if (d.junk_verschoben) {
                meldung(d.junk_verschoben === 1 ? '1 Nachricht wurde als Junk-E-Mail aussortiert.' : `${d.junk_verschoben} Nachrichten wurden als Junk-E-Mail aussortiert.`, 'info');
                ordnerLaden(zustand.konto);
            }
        });
    }
    function listeZeichnen() {
        const gesendet = zustand.rolle === 'gesendet' || zustand.rolle === 'entwuerfe';
        if (!zustand.nachrichten.length) {
            mailListe.innerHTML = `<div class="mail-leer"><i class="bi bi-inbox"></i><p class="mt-2">${zustand.suche ? 'Keine Treffer.' : 'Hier ist nichts.'}</p></div>`;
            return;
        }
        let html = '', gruppe = '';
        for (const n of zustand.nachrichten) {
            const g = gruppeVon(n.datum);
            if (g !== gruppe) { html += `<div class="mail-gruppe">${g}</div>`; gruppe = g; }
            html += `<div class="mail-zeile ${n.gelesen ? '' : 'ungelesen'} ${zustand.auswahl.includes(n.uid) ? 'ausgewaehlt' : ''}" data-uid="${n.uid}" draggable="true">
                <div class="absender">${esc(gesendet ? 'An: ' + personText(n.an) : personText(n.von)) || '(unbekannt)'}</div>
                <div class="datum">${zeitText(n.datum)}</div>
                <div class="betreff">${esc(n.betreff) || '(kein Betreff)'}</div>
                <div class="symbole">${n.anhang ? '<i class="bi bi-paperclip"></i> ' : ''}${n.beantwortet ? '<i class="bi bi-reply" title="Beantwortet"></i> ' : ''}${n.markiert ? '<i class="bi bi-flag-fill"></i>' : ''}</div>
            </div>`;
        }
        if (zustand.seite < zustand.seiten) html += '<div class="p-3 text-center"><button class="btn btn-sm btn-outline-secondary" id="mehrLaden">Weitere laden</button></div>';
        mailListe.innerHTML = html;
    }
    mailListe.addEventListener('click', e => {
        if (e.target.id === 'mehrLaden') { listeLaden(zustand.seite + 1); return; }
        const z = e.target.closest('.mail-zeile'); if (!z) return;
        const uid = +z.dataset.uid;
        if (e.ctrlKey || e.metaKey) {
            zustand.auswahl = zustand.auswahl.includes(uid) ? zustand.auswahl.filter(u => u !== uid) : zustand.auswahl.concat(uid);
            listeZeichnen(); knoepfe(); return;
        }
        if (e.shiftKey && zustand.auswahl.length) {
            const uids = zustand.nachrichten.map(n => n.uid), a = uids.indexOf(zustand.auswahl[0]), b = uids.indexOf(uid);
            zustand.auswahl = uids.slice(Math.min(a, b), Math.max(a, b) + 1); listeZeichnen(); knoepfe(); return;
        }
        zustand.auswahl = [uid]; listeZeichnen(); knoepfe(); oeffnen(uid);
    });
    mailListe.addEventListener('dragstart', e => {
        const z = e.target.closest('.mail-zeile'); if (!z) return;
        if (!zustand.auswahl.includes(+z.dataset.uid)) { zustand.auswahl = [+z.dataset.uid]; listeZeichnen(); }
        e.dataTransfer.setData('text/plain', zustand.auswahl.join(','));
    });
    let suchTimer;
    document.getElementById('mailSuche').addEventListener('input', e => {
        clearTimeout(suchTimer); suchTimer = setTimeout(() => { zustand.suche = e.target.value.trim(); listeLaden(); }, 450);
    });
    document.querySelectorAll('.mail-filter button').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('.mail-filter button').forEach(x => x.classList.toggle('aktiv', x === b));
        zustand.ungelesen = b.dataset.filter; listeLaden();
    }));

    // ---------------- Lesen ----------------
    function leer() { zustand.offen = null; leseBereich.innerHTML = '<div class="mail-leer"><i class="bi bi-envelope-open"></i><p class="mt-2 mb-0">Wählen Sie ein Element aus, um es zu lesen.</p></div>'; knoepfe(); }
    function farbe(text) { let h = 0; for (const c of text) h = (h * 31 + c.charCodeAt(0)) % 360; return `hsl(${h},45%,45%)`; }
    function initialen(a) { const n = (a?.[1] || a?.[0] || '?').replace(/["']/g, '').trim(); const t = n.split(/[\s.@]+/).filter(Boolean); return ((t[0]?.[0] || '') + (t[1]?.[0] || '')).toUpperCase() || '?'; }
    function groesse(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
    function anhangSymbol(typ) { return typ.includes('pdf') ? 'bi-file-earmark-pdf' : typ.startsWith('image/') ? 'bi-file-earmark-image' : typ.includes('word') ? 'bi-file-earmark-word' : (typ.includes('excel') || typ.includes('sheet')) ? 'bi-file-earmark-excel' : 'bi-file-earmark'; }
    function oeffnen(uid, bilder = 0) {
        leseBereich.innerHTML = '<div class="mail-leer"><span class="spinner-border text-primary"></span></div>';
        ansicht('lesen');
        api('nachricht', { konto: zustand.konto, ordner: zustand.ordner, uid, bilder, junk: istJunkOrdner() ? 1 : '' }).then(d => {
            if (!d.success) { leseBereich.innerHTML = `<div class="mail-leer text-danger">${esc(d.error)}</div>`; return; }
            const n = d.nachricht; zustand.offen = n;
            const eintrag = zustand.nachrichten.find(x => x.uid === uid);
            if (eintrag && !eintrag.gelesen) { eintrag.gelesen = true; listeZeichnen(); ordnerLaden(zustand.konto); window.syncopaMailZaehler?.(true); }
            const absender = n.von[0] || ['', ''];
            const basis = `api/mail.php?aktion=anhang&konto=${zustand.konto}&ordner=${encodeURIComponent(zustand.ordner)}&uid=${uid}`;
            leseBereich.innerHTML = `
                <div class="mail-kopf">
                    <h4>${esc(n.betreff) || '(kein Betreff)'}</h4>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="mail-avatar" style="background:${farbe(absender[0])}">${esc(initialen(absender))}</div>
                        <div class="flex-grow-1 min-w-0">
                            <div><strong>${esc(absender[1] || absender[0])}</strong> <span class="meta">&lt;${esc(absender[0])}&gt;</span></div>
                            <div class="meta">An: ${esc(personText(n.an))}</div>
                            ${n.cc.length ? `<div class="meta">Cc: ${esc(personText(n.cc))}</div>` : ''}
                        </div>
                        <div class="text-end">
                            <div class="mail-aktionen btn-group btn-group-sm mb-1">
                                <button class="btn btn-outline-secondary" data-cmd="antworten" title="Antworten"><i class="bi bi-reply"></i></button>
                                <button class="btn btn-outline-secondary" data-cmd="allen" title="Allen antworten"><i class="bi bi-reply-all"></i></button>
                                <button class="btn btn-outline-secondary" data-cmd="weiterleiten" title="Weiterleiten"><i class="bi bi-forward"></i></button>
                            </div>
                            <div class="meta">${n.datum ? new Date(n.datum.replace(' ', 'T')).toLocaleString('de-AT', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''}</div>
                        </div>
                    </div>
                </div>
                ${istJunkOrdner() ? `<div class="mail-hinweis mail-hinweis-junk"><i class="bi bi-shield-exclamation"></i> <span>${n.junk_grund
                    ? `Als Junk eingestuft: <strong>${esc(n.junk_grund.grund)}</strong>.`
                    : 'Diese Nachricht liegt im Junk-E-Mail-Ordner.'} Seien Sie vorsichtig mit Links und Anhängen.</span>
                    <a href="#" data-cmd="keinjunk" class="ms-auto text-nowrap">Kein Junk</a></div>` : ''}
                ${n.externe_bilder && !bilder ? '<div class="mail-hinweis"><i class="bi bi-shield-lock"></i> Zum Schutz Ihrer Privatsphäre wurden Bilder aus dem Internet blockiert. <a href="#" data-cmd="bilder">Bilder anzeigen</a></div>' : ''}
                ${n.anhaenge.length ? `<div class="mail-anhaenge">${n.anhaenge.map(a => `<a class="mail-anhang" href="${basis}&teil=${encodeURIComponent(a.teil)}" title="${esc(a.name)} herunterladen">
                    <i class="bi ${anhangSymbol(a.typ)}"></i><span><span class="n d-block">${esc(a.name)}</span><span class="g">${groesse(a.groesse)}</span></span></a>`).join('')}</div>` : ''}
                <div class="mail-inhalt"><iframe sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" title="Nachricht"></iframe></div>`;
            const rahmen = leseBereich.querySelector('iframe');
            rahmen.srcdoc = `<!DOCTYPE html><html><head><meta charset="utf-8"><base target="_blank"><style>body{font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#222;margin:0;padding:8px;word-wrap:break-word}img{max-width:100%;height:auto}blockquote{border-left:2px solid #ccc;margin:0;padding-left:10px;color:#555}</style></head><body>${n.html}</body></html>`;
            rahmen.addEventListener('load', () => { try { rahmen.style.height = (rahmen.contentDocument.documentElement.scrollHeight + 20) + 'px'; } catch (e) { rahmen.style.height = '600px'; } });
            knoepfe();
        });
    }
    leseBereich.addEventListener('click', e => {
        const b = e.target.closest('[data-cmd]'); if (!b) return; e.preventDefault();
        if (b.dataset.cmd === 'bilder') oeffnen(zustand.offen.uid, 1);
        else if (b.dataset.cmd === 'keinjunk') junkAktion('kein_junk', [zustand.offen.uid]);
        else schreiben(b.dataset.cmd);
    });

    // ---------------- Aktionen ----------------
    function knoepfe() {
        const schreibt = !!leseBereich.querySelector('.mail-schreiben');
        document.querySelectorAll('[data-auswahl]').forEach(b => b.disabled = !zustand.auswahl.length || schreibt);
        document.querySelectorAll('[data-einzeln]').forEach(b => b.disabled = !zustand.offen || schreibt);
        document.getElementById('cmdArchiv').disabled = !zustand.auswahl.length || !archivOrdner() || zustand.rolle === 'archiv' || schreibt;
        junkMenueZeichnen(schreibt);
    }

    // ---------------- Junk-E-Mail ----------------
    /** Junk-Ordner des Postfachs oder ein Spam-Ordner des Mailservers (z. B. INBOX.spam) */
    function istJunkOrdner() { return zustand.rolle === 'spam' || /(^|[.\/])(spam|junk)$/i.test(zustand.ordner); }
    function junkMenueZeichnen(schreibt) {
        const aus = !zustand.auswahl.length || schreibt ? 'disabled' : '';
        const junk = istJunkOrdner();
        document.getElementById('junkMenue').innerHTML = (junk
            ? `<li><a class="dropdown-item ${aus}" href="#" data-junk="kein_junk"><i class="bi bi-envelope-check me-2"></i>Kein Junk</a></li>
               <li><a class="dropdown-item ${aus}" href="#" data-junk="kein_junk_nur"><i class="bi bi-inbox me-2"></i>Kein Junk, Absender nicht als sicher merken</a></li>`
            : `<li><a class="dropdown-item ${aus}" href="#" data-junk="junk"><i class="bi bi-shield-exclamation me-2"></i>Junk-E-Mail</a></li>
               <li><a class="dropdown-item ${aus}" href="#" data-junk="absender"><i class="bi bi-person-x me-2"></i>Absender blockieren</a></li>
               <li><a class="dropdown-item ${aus}" href="#" data-junk="domain"><i class="bi bi-globe me-2"></i>Domain des Absenders blockieren</a></li>`)
            + `<li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="#" data-junk="optionen"><i class="bi bi-gear me-2"></i>Junk-E-Mail-Optionen …</a></li>`;
    }
    document.getElementById('junkMenue').addEventListener('click', e => {
        const a = e.target.closest('[data-junk]'); if (!a || a.classList.contains('disabled')) return; e.preventDefault();
        const w = a.dataset.junk;
        if (w === 'optionen') return junkOptionen(zustand.konto);
        if (w === 'kein_junk' || w === 'kein_junk_nur') return junkAktion('kein_junk', zustand.auswahl.slice(), { sicher: w === 'kein_junk' ? 1 : 0 });
        junkAktion('junk', zustand.auswahl.slice(), { blockieren: w === 'junk' ? '' : w });
    });
    function junkAktion(aktion, uids, extra = {}) {
        zustand.auswahl = uids;
        api(aktion, { konto: zustand.konto, ordner: zustand.ordner, uids, ...extra }, true).then(d => {
            if (!d.success) return meldung(d.error);
            meldung(aktion === 'junk' ? 'In Junk-E-Mail verschoben, der Filter hat daraus gelernt.' : 'In den Posteingang verschoben, der Filter hat daraus gelernt.', 'success');
            nachAktion(true);
        });
    }
    let junkKonto = null;
    function junkOptionen(konto) {
        junkKonto = konto;
        const m = document.getElementById('junkModal');
        m.querySelector('[data-junk-konto]').textContent = KONTEN.find(k => k.id === konto)?.email || '';
        junkOptionenLaden();
        bootstrap.Modal.getOrCreateInstance(m).show();
    }
    function junkOptionenLaden() {
        const m = document.getElementById('junkModal');
        return api('spam_optionen', { konto: junkKonto }).then(d => {
            if (!d.success) return meldung(d.error);
            m.querySelector('#junkAktiv').checked = d.aktiv;
            const s = d.stand;
            m.querySelector('[data-junk-stand]').innerHTML = `<i class="bi bi-mortarboard"></i> Gelernt aus <strong>${s.spam}</strong> Junk- und <strong>${s.ham}</strong> erwünschten Nachrichten.
                ${s.spam >= 10 && s.ham >= 10 ? 'Der Lernfilter entscheidet mit.' : 'Der Lernfilter entscheidet erst ab je 10 Beispielen mit. Markieren Sie dazu Junk mit „Junk-E-Mail“.'}
                ${s.letzter_lauf ? `<br><span class="text-muted">Zuletzt geprüft: ${new Date(s.letzter_lauf.replace(' ', 'T')).toLocaleString('de-AT')}</span>` : ''}`;
            for (const art of ['sicher', 'blockiert']) {
                const liste = d[art];
                m.querySelector(`[data-junk-liste="${art}"]`).innerHTML = `
                    <p class="small text-muted">${art === 'sicher'
                        ? 'Nachrichten dieser Absender kommen nie in den Junk. Mitglieder aus Syncopa und Empfänger Ihrer gesendeten Mails gelten automatisch als sicher.'
                        : 'Nachrichten dieser Absender landen immer im Junk.'} Eine ganze Domain geben Sie als <code>@beispiel.at</code> ein.</p>
                    <form class="input-group input-group-sm mb-2" data-junk-neu="${art}">
                        <input type="text" class="form-control" placeholder="name@beispiel.at oder @beispiel.at" required>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Hinzufügen</button>
                    </form>
                    ${liste.length ? `<ul class="list-group list-group-flush border rounded" style="max-height:320px;overflow:auto">${liste.map(x => `
                        <li class="list-group-item d-flex align-items-center py-1 small"><span class="flex-grow-1 text-truncate">${esc(x)}</span>
                        <button type="button" class="btn btn-sm btn-link text-danger" data-junk-weg="${esc(x)}" title="Entfernen"><i class="bi bi-x-lg"></i></button></li>`).join('')}</ul>`
                        : '<p class="small text-muted fst-italic">Noch keine Einträge.</p>'}`;
            }
            m.querySelector('#junkProtokoll').innerHTML = d.protokoll.length
                ? `<p class="small text-muted">Die letzten aussortierten Nachrichten und warum (90 Tage).</p><div class="table-responsive"><table class="table table-sm small">
                   <thead><tr><th>Zeit</th><th>Absender</th><th>Betreff</th><th>Grund</th></tr></thead><tbody>${d.protokoll.map(p => `<tr>
                   <td class="text-nowrap">${new Date(p.zeit.replace(' ', 'T')).toLocaleString('de-AT', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}</td>
                   <td>${esc(p.absender)}</td><td>${esc(p.betreff)}</td><td>${esc(p.grund)}</td></tr>`).join('')}</tbody></table></div>`
                : '<p class="small text-muted fst-italic">Bisher wurde nichts aussortiert.</p>';
        });
    }
    function junkSpeichern(daten) {
        return api('spam_speichern', { konto: junkKonto, ...daten }, true).then(d => { if (!d.success) meldung(d.error); return junkOptionenLaden(); });
    }
    document.getElementById('junkAktiv').addEventListener('change', e => junkSpeichern({ was: 'aktiv', wert: e.target.checked ? 1 : 0 }));
    document.getElementById('junkModal').addEventListener('submit', e => {
        const f = e.target.closest('[data-junk-neu]'); if (!f) return; e.preventDefault();
        junkSpeichern({ was: 'eintragen', art: f.dataset.junkNeu, muster: f.querySelector('input').value.trim() });
    });
    document.getElementById('junkModal').addEventListener('click', e => {
        const weg = e.target.closest('[data-junk-weg]');
        if (weg) return junkSpeichern({ was: 'entfernen', muster: weg.dataset.junkWeg });
        if (e.target.closest('[data-junk-zuruecksetzen]') && confirm('Alles Gelernte vergessen? Der Filter lernt danach aus den vorhandenen Junk- und Posteingangs-Nachrichten neu.')) {
            junkSpeichern({ was: 'zuruecksetzen' });
        }
    });
    function ordnerDesKontos() { return Array.isArray(zustand.ordnerListe[zustand.konto]) ? zustand.ordnerListe[zustand.konto] : []; }
    function archivOrdner() { return ordnerDesKontos().find(o => o.rolle === 'archiv')?.name; }
    function ordnerOptionen(liste, ohne) {
        return liste.filter(o => o.name !== ohne).map(o => ({ ...o, einzug: 12 + 16 * Math.min(o.ebene, 3) }));
    }
    function verschiebenMenueZeichnen() {
        document.getElementById('verschiebenMenue').innerHTML = ordnerOptionen(ordnerDesKontos(), zustand.ordner)
            .map(o => `<li><a class="dropdown-item" href="#" data-ziel="${esc(o.name)}" style="padding-left:${o.einzug}px"><i class="bi ${ORDNER_SYMBOL[o.rolle] || 'bi-folder2'} me-2"></i>${esc(o.anzeige)}</a></li>`).join('')
            + '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="#" data-ziel-neu><i class="bi bi-folder-plus me-2"></i>Neuer Ordner …</a></li>';
    }
    document.getElementById('verschiebenMenue').addEventListener('click', e => {
        const a = e.target.closest('[data-ziel], [data-ziel-neu]'); if (!a) return; e.preventDefault();
        if (a.dataset.ziel !== undefined) { verschieben(a.dataset.ziel); return; }
        // Neuer Ordner im Posteingang, danach dorthin verschieben
        const inbox = ordnerDesKontos().find(o => o.rolle === 'posteingang');
        ordnerNeu(zustand.konto, inbox ? inbox.name : '', ziel => verschieben(ziel));
    });

    // ---------------- Ordner anlegen, umbenennen, löschen ----------------
    let menueEl = null;
    function menueSchliessen() { menueEl?.remove(); menueEl = null; }
    document.addEventListener('click', menueSchliessen);
    window.addEventListener('blur', menueSchliessen);
    function kontextmenue(eintrag, x, y) {
        menueSchliessen();
        const konto = +eintrag.dataset.konto, ordner = eintrag.dataset.ordner, standard = eintrag.dataset.rolle !== '';
        const anzeige = eintrag.querySelector('span').textContent;
        menueEl = document.createElement('ul');
        menueEl.className = 'dropdown-menu show mail-kontextmenue';
        menueEl.innerHTML = `<li><h6 class="dropdown-header text-truncate">${esc(anzeige)}</h6></li>
            <li><a class="dropdown-item" href="#" data-a="neu"><i class="bi bi-folder-plus me-2"></i>Neuer Unterordner …</a></li>
            <li><a class="dropdown-item ${standard ? 'disabled' : ''}" href="#" data-a="umbenennen"><i class="bi bi-pencil me-2"></i>Umbenennen …</a></li>
            <li><a class="dropdown-item text-danger ${standard ? 'disabled' : ''}" href="#" data-a="loeschen"><i class="bi bi-trash me-2"></i>Ordner löschen</a></li>
            ${standard ? '<li><span class="dropdown-item-text small text-muted">Standardordner können nicht umbenannt oder gelöscht werden.</span></li>' : ''}`;
        menueEl.style.left = Math.min(x, window.innerWidth - 230) + 'px';
        menueEl.style.top = Math.min(y, window.innerHeight - 180) + 'px';
        menueEl.addEventListener('click', e => {
            e.stopPropagation();
            const a = e.target.closest('[data-a]'); if (!a || a.classList.contains('disabled')) return; e.preventDefault(); menueSchliessen();
            if (a.dataset.a === 'neu') ordnerNeu(konto, ordner);
            if (a.dataset.a === 'umbenennen') ordnerName('Ordner umbenennen', anzeige, name =>
                api('ordner_umbenennen', { konto, ordner, name }, true).then(d => {
                    if (!d.success) return meldung(d.error);
                    if (zustand.konto === konto && zustand.ordner === ordner) { zustand.ordner = d.ordner; document.getElementById('listenTitel').textContent = name; }
                    ordnerLaden(konto);
                }));
            if (a.dataset.a === 'loeschen' && confirm(`Den Ordner „${anzeige}“ löschen? Er wird auch auf dem Mailserver gelöscht.`)) {
                api('ordner_loeschen', { konto, ordner }, true).then(d => {
                    if (!d.success) return meldung(d.error);
                    if (zustand.konto === konto && zustand.ordner === ordner) {
                        const inbox = ordnerDesKontos().find(o => o.rolle === 'posteingang');
                        zustand.ordner = inbox ? inbox.name : 'INBOX'; zustand.rolle = 'posteingang';
                        document.getElementById('listenTitel').textContent = 'Posteingang'; listeLaden(); leer();
                    }
                    ordnerLaden(konto);
                });
            }
        });
        document.body.appendChild(menueEl);
    }
    ordnerBereich.addEventListener('contextmenu', e => {
        const eintrag = e.target.closest('.mail-ordner-eintrag'); if (!eintrag) return;
        e.preventDefault(); kontextmenue(eintrag, e.clientX, e.clientY);
    });
    /** Neuer Ordner unter $eltern ('' = oberste Ebene); $danach(name) wird mit dem Servernamen aufgerufen */
    function ordnerNeu(konto, eltern, danach) {
        const elternName = eltern ? (zustand.ordnerListe[konto] || []).find?.(o => o.name === eltern)?.anzeige : null;
        ordnerName(elternName ? `Neuer Ordner in „${elternName}“` : 'Neuer Ordner', '', name =>
            api('ordner_anlegen', { konto, ordner: eltern, name }, true).then(d => {
                if (!d.success) return meldung(d.error);
                meldung(`Ordner „${name}“ angelegt.`, 'success');
                ordnerLaden(konto).then(() => danach && danach(d.ordner));
            }));
    }
    /** Kleiner Dialog für einen Ordnernamen */
    function ordnerName(titel, wert, ok) {
        const m = document.getElementById('ordnerNameModal');
        m.querySelector('.modal-title').textContent = titel;
        const eingabe = m.querySelector('input'); eingabe.value = wert;
        const modal = bootstrap.Modal.getOrCreateInstance(m);
        m.querySelector('form').onsubmit = e => { e.preventDefault(); const n = eingabe.value.trim(); if (!n) return; modal.hide(); ok(n); };
        m.addEventListener('shown.bs.modal', () => { eingabe.focus(); eingabe.select(); }, { once: true });
        modal.show();
    }
    function nachAktion(entfernen) {
        if (entfernen) { zustand.nachrichten = zustand.nachrichten.filter(n => !zustand.auswahl.includes(n.uid)); if (zustand.offen && zustand.auswahl.includes(zustand.offen.uid)) leer(); zustand.auswahl = []; }
        listeZeichnen(); knoepfe(); ordnerLaden(zustand.konto); window.syncopaMailZaehler?.(true);
    }
    function verschieben(ziel) {
        const uids = zustand.auswahl.slice();
        api('verschieben', { konto: zustand.konto, ordner: zustand.ordner, ziel, uids }, true).then(d => d.success ? nachAktion(true) : meldung(d.error));
    }
    function loeschen() {
        if (!zustand.auswahl.length) return;
        const endgueltig = zustand.rolle === 'papierkorb';
        if (endgueltig && !confirm('Die ausgewählten Nachrichten endgültig löschen?')) return;
        api('loeschen', { konto: zustand.konto, ordner: zustand.ordner, uids: zustand.auswahl }, true).then(d => d.success ? nachAktion(true) : meldung(d.error));
    }
    document.getElementById('cmdLoeschen').addEventListener('click', loeschen);
    document.getElementById('cmdArchiv').addEventListener('click', () => verschieben(archivOrdner()));
    document.getElementById('cmdGelesen').addEventListener('click', () => {
        const gewaehlt = zustand.nachrichten.filter(n => zustand.auswahl.includes(n.uid)), ziel = gewaehlt.some(n => !n.gelesen);
        api('markieren', { konto: zustand.konto, ordner: zustand.ordner, art: ziel ? 'gelesen' : 'ungelesen', uids: zustand.auswahl }, true)
            .then(d => { if (!d.success) return meldung(d.error); gewaehlt.forEach(n => n.gelesen = ziel); nachAktion(false); });
    });
    document.getElementById('cmdFlagge').addEventListener('click', () => {
        const gewaehlt = zustand.nachrichten.filter(n => zustand.auswahl.includes(n.uid)), ziel = gewaehlt.some(n => !n.markiert);
        api('markieren', { konto: zustand.konto, ordner: zustand.ordner, art: ziel ? 'markiert' : 'unmarkiert', uids: zustand.auswahl }, true)
            .then(d => { if (!d.success) return meldung(d.error); gewaehlt.forEach(n => n.markiert = ziel); listeZeichnen(); });
    });
    document.getElementById('cmdAktualisieren').addEventListener('click', () => { ordnerLaden(zustand.konto); listeLaden(); });
    document.getElementById('cmdNeu').addEventListener('click', () => schreiben('neu'));
    document.getElementById('cmdAntworten').addEventListener('click', () => schreiben('antworten'));
    document.getElementById('cmdAllen').addEventListener('click', () => schreiben('allen'));
    document.getElementById('cmdWeiter').addEventListener('click', () => schreiben('weiterleiten'));
    document.getElementById('cmdZurueck').addEventListener('click', () => ansicht(app.dataset.ansicht === 'lesen' ? 'liste' : 'ordner'));
    document.getElementById('listeZurueck').addEventListener('click', () => ansicht('ordner'));
    document.addEventListener('keydown', e => {
        const ziel = e.target instanceof Element ? e.target : null;
        if (e.key === 'Delete' && !ziel?.closest('input, textarea, [contenteditable]') && zustand.auswahl.length) loeschen();
    });

    // ---------------- Schreiben ----------------
    function signaturHtml(kontoId) {
        const s = KONTEN.find(k => k.id === +kontoId)?.signatur || '';
        return s ? `<div class="signatur"><br>${esc(s).replace(/\n/g, '<br>')}</div>` : '';
    }
    function schreiben(modus) {
        const o = zustand.offen;
        if (modus !== 'neu' && !o) return;
        if (leseBereich.querySelector('.mail-schreiben') && !confirm('Die angefangene Nachricht verwerfen?')) return;
        leseBereich.innerHTML = '';
        leseBereich.appendChild(document.getElementById('vorlageSchreiben').content.cloneNode(true));
        const f = leseBereich.querySelector('form'), editor = f.querySelector('.mail-editor');
        const feldKonto = f.elements.namedItem('konto'), feldBetreff = f.elements.namedItem('betreff');
        feldKonto.value = zustand.konto;
        const felder = {};
        f.querySelectorAll('.chips').forEach(c => felder[c.dataset.feld] = chipsFeld(c));
        const eigene = KONTEN.map(k => k.email.toLowerCase());
        let originalAnhaenge = [];
        if (modus === 'antworten' || modus === 'allen') {
            const an = (o.antwort_an.length ? o.antwort_an : o.von);
            an.forEach(a => felder.an.hinzu(a[1] ? `"${a[1]}" <${a[0]}>` : a[0], a[1] || a[0]));
            if (modus === 'allen') {
                o.an.concat(o.cc).filter(a => !eigene.includes(a[0].toLowerCase()) && !an.some(x => x[0] === a[0]))
                    .forEach(a => felder.cc.hinzu(a[1] ? `"${a[1]}" <${a[0]}>` : a[0], a[1] || a[0]));
            }
            feldBetreff.value = /^(AW|RE|Aw|Re):/.test(o.betreff) ? o.betreff : 'AW: ' + o.betreff;
        } else if (modus === 'weiterleiten') {
            feldBetreff.value = /^(WG|FW|Fwd|WG):/i.test(o.betreff) ? o.betreff : 'WG: ' + o.betreff;
            originalAnhaenge = o.anhaenge.map(a => ({ ...a, mit: true }));
        }
        if (felder.cc.werte().length) f.querySelector('[data-zeile="cc"]').classList.remove('d-none');
        let html = '<p><br></p>' + signaturHtml(feldKonto.value);
        if (modus !== 'neu') {
            const v = o.von[0] || ['', ''];
            html += `<hr><div style="font-size:13px"><b>Von:</b> ${esc(v[1] ? v[1] + ' <' + v[0] + '>' : v[0])}<br><b>Gesendet:</b> ${esc(new Date((o.datum || '').replace(' ', 'T')).toLocaleString('de-AT'))}<br>
                <b>An:</b> ${esc(personText(o.an))}<br>${o.cc.length ? `<b>Cc:</b> ${esc(personText(o.cc))}<br>` : ''}<b>Betreff:</b> ${esc(o.betreff)}</div><br><blockquote>${o.zitat_html}</blockquote>`;
        }
        editor.innerHTML = html;
        // Ablage der gesendeten Nachricht: Ordner des gewählten Absender-Postfachs, vorausgewählt „Gesendete Elemente“
        const ablage = f.querySelector('[data-ablage]');
        function ablageFuellen() {
            const liste = Array.isArray(zustand.ordnerListe[feldKonto.value]) ? zustand.ordnerListe[feldKonto.value] : [];
            ablage.innerHTML = liste.filter(o => !['entwuerfe', 'papierkorb', 'spam'].includes(o.rolle))
                .map(o => `<option value="${esc(o.name)}" ${o.rolle === 'gesendet' ? 'selected' : ''}>${'\u00a0'.repeat(2 * Math.min(o.ebene, 3))}${esc(o.anzeige)}</option>`).join('');
            ablage.closest('.ablage-wahl').classList.toggle('d-none', !liste.length);
        }
        ablageFuellen();
        // Signatur und Ablage beim Wechsel des Absenders anpassen
        feldKonto.addEventListener('change', () => { const s = editor.querySelector('.signatur'); if (s) s.outerHTML = signaturHtml(feldKonto.value) || ''; ablageFuellen(); });
        f.querySelector('[data-zeige="ccbcc"]').addEventListener('click', () => f.querySelectorAll('[data-zeile]').forEach(z => z.classList.remove('d-none')));
        f.querySelectorAll('[data-befehl]').forEach(b => b.addEventListener('click', () => {
            editor.focus();
            if (b.dataset.befehl === 'createLink') { const url = prompt('Adresse des Links:', 'https://'); if (url) document.execCommand('createLink', false, url); }
            else document.execCommand(b.dataset.befehl, false, null);
        }));
        // Anhänge
        let dateien = [];
        const anhangBereich = f.querySelector('[data-anhaenge]');
        function anhaengeZeichnen() {
            const alle = originalAnhaenge.filter(a => a.mit).map((a, i) => ({ name: a.name, groesse: a.groesse, typ: a.typ, orig: a.teil }))
                .concat(dateien.map((d, i) => ({ name: d.name, groesse: d.size, typ: d.type, neu: i })));
            anhangBereich.classList.toggle('d-none', !alle.length);
            anhangBereich.innerHTML = alle.map(a => `<span class="mail-anhang"><i class="bi ${anhangSymbol(a.typ || '')}"></i><span><span class="n d-block">${esc(a.name)}</span><span class="g">${groesse(a.groesse)}</span></span>
                <button type="button" class="btn btn-sm btn-link p-0 ms-1" ${a.orig !== undefined ? `data-orig="${esc(a.orig)}"` : `data-neu="${a.neu}"`} title="Entfernen"><i class="bi bi-x-lg" style="font-size:12px"></i></button></span>`).join('');
        }
        anhangBereich.addEventListener('click', e => {
            const b = e.target.closest('button'); if (!b) return;
            if (b.dataset.orig !== undefined) originalAnhaenge.find(a => a.teil === b.dataset.orig).mit = false; else dateien.splice(+b.dataset.neu, 1);
            anhaengeZeichnen();
        });
        f.querySelector('[data-dateien]').addEventListener('change', e => { dateien = dateien.concat([...e.target.files]); e.target.value = ''; anhaengeZeichnen(); });
        anhaengeZeichnen();
        f.querySelector('[data-verwerfen]').addEventListener('click', () => { if (confirm('Nachricht verwerfen?')) { zustand.offen ? oeffnen(zustand.offen.uid) : leer(); } });
        f.addEventListener('submit', e => {
            e.preventDefault();
            if (!felder.an.werte().length && !felder.cc.werte().length && !felder.bcc.werte().length) { meldung('Bitte mindestens einen Empfänger angeben.'); return; }
            if (f.querySelector('.chip.falsch')) { meldung('Bitte die rot markierten Adressen korrigieren.'); return; }
            if (!feldBetreff.value.trim() && !confirm('Ohne Betreff senden?')) return;
            const fd = new FormData();
            fd.append('konto', feldKonto.value);
            ['an', 'cc', 'bcc'].forEach(k => fd.append(k, felder[k].werte().join(', ')));
            fd.append('betreff', feldBetreff.value);
            fd.append('html', editor.innerHTML);
            if (ablage.value) fd.append('ablage', ablage.value);
            dateien.forEach(d => fd.append('anhaenge[]', d));
            if (modus !== 'neu') {
                fd.append('modus', modus); fd.append('bezug_uid', o.uid); fd.append('bezug_ordner', zustand.ordner);
                originalAnhaenge.filter(a => !a.mit).forEach(a => fd.append('ohne_anhang[]', a.teil));
            }
            const knopf = f.querySelector('[type="submit"]'), status = f.querySelector('[data-status]');
            knopf.disabled = true; status.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Wird gesendet …';
            api('senden', fd, true).then(d => {
                if (!d.success) { knopf.disabled = false; status.textContent = ''; meldung(d.error); return; }
                meldung(`Gesendet an ${d.empfaenger} Empfänger.`, 'success');
                if (o && modus !== 'weiterleiten' && modus !== 'neu') { const x = zustand.nachrichten.find(n => n.uid === o.uid); if (x) x.beantwortet = true; listeZeichnen(); }
                o ? oeffnen(o.uid) : leer();
            }).catch(() => { knopf.disabled = false; status.textContent = ''; meldung('Keine Verbindung zum Server.'); });
        });
        knoepfe(); ansicht('lesen');
        (modus === 'neu' || modus === 'weiterleiten' ? f.querySelector('[data-feld="an"] input') : editor).focus();
    }

    /** Empfängerfeld mit „Chips“ und Vorschlägen aus dem Adressbuch */
    function chipsFeld(box) {
        const eingabe = box.querySelector('input');
        const werte = [];
        let vorschlag = null, treffer = [], aktiv = -1, timer;
        function zeichnen() {
            box.querySelectorAll('.chip').forEach(c => c.remove());
            werte.forEach((w, i) => {
                const gueltig = w.wert.startsWith('gruppe:') || /[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+/.test(w.wert);
                const c = document.createElement('span');
                c.className = 'chip' + (w.wert.startsWith('gruppe:') ? ' gruppe' : '') + (gueltig ? '' : ' falsch');
                c.title = w.wert.startsWith('gruppe:') ? 'Gruppe – geht als Bcc an alle Mitglieder' : w.wert;
                c.innerHTML = `${w.wert.startsWith('gruppe:') ? '<i class="bi bi-people-fill"></i>' : ''}${esc(w.text)}<button type="button" title="Entfernen">&times;</button>`;
                c.querySelector('button').addEventListener('click', () => { werte.splice(i, 1); zeichnen(); });
                box.insertBefore(c, eingabe);
            });
        }
        function hinzu(wert, text) { wert = wert.trim(); if (!wert) return; werte.push({ wert, text: text || wert }); zeichnen(); }
        function schliessen() { vorschlag?.remove(); vorschlag = null; aktiv = -1; }
        function uebernehmen() {
            if (aktiv >= 0 && treffer[aktiv]) { hinzu(treffer[aktiv].wert, treffer[aktiv].name); }
            else if (eingabe.value.trim()) { eingabe.value.split(/[,;]/).forEach(v => hinzu(v, v.trim())); }
            eingabe.value = ''; schliessen();
        }
        function vorschlagZeichnen() {
            vorschlag?.remove(); vorschlag = null; // Auswahl (aktiv) bleibt erhalten
            if (!treffer.length) return;
            vorschlag = document.createElement('div'); vorschlag.className = 'vorschlaege';
            vorschlag.innerHTML = treffer.map((t, i) => `<div data-i="${i}" class="${i === aktiv ? 'aktiv' : ''}">${t.gruppe ? '<i class="bi bi-people-fill me-1"></i>' : ''}${esc(t.name)}<small>${esc(t.info)}</small></div>`).join('');
            vorschlag.addEventListener('mousedown', e => { const d = e.target.closest('[data-i]'); if (d) { e.preventDefault(); aktiv = +d.dataset.i; uebernehmen(); } });
            box.appendChild(vorschlag);
        }
        eingabe.addEventListener('input', () => {
            if (/[,;]$/.test(eingabe.value)) { uebernehmen(); return; }
            clearTimeout(timer);
            const q = eingabe.value.trim();
            if (q.length < 2 || !ADRESSBUCH) { treffer = []; schliessen(); return; }
            timer = setTimeout(() => api('adressen', { q }).then(d => { treffer = d.treffer || []; aktiv = treffer.length ? 0 : -1; vorschlagZeichnen(); }), 200);
        });
        eingabe.addEventListener('keydown', e => {
            if (e.key === 'ArrowDown' && treffer.length) { e.preventDefault(); aktiv = (aktiv + 1) % treffer.length; vorschlagZeichnen(); }
            else if (e.key === 'ArrowUp' && treffer.length) { e.preventDefault(); aktiv = (aktiv - 1 + treffer.length) % treffer.length; vorschlagZeichnen(); }
            else if (e.key === 'Enter' || e.key === 'Tab') { if (eingabe.value.trim() || aktiv >= 0) { e.preventDefault(); uebernehmen(); } }
            else if (e.key === 'Backspace' && !eingabe.value && werte.length) { werte.pop(); zeichnen(); }
            else if (e.key === 'Escape') schliessen();
        });
        eingabe.addEventListener('blur', () => setTimeout(() => { if (eingabe.value.trim()) { aktiv = -1; uebernehmen(); } schliessen(); }, 150));
        box.addEventListener('click', e => { if (e.target === box) eingabe.focus(); });
        return { hinzu, werte: () => werte.map(w => w.wert) };
    }

    // ---------------- Start ----------------
    ordnerZeichnen();
    Promise.all(KONTEN.map(k => ordnerLaden(k.id))).then(() => {
        const inbox = ordnerDesKontos().find(o => o.rolle === 'posteingang');
        if (inbox) zustand.ordner = inbox.name;
        ordnerZeichnen();
    });
    listeLaden();
    if (window.innerWidth >= 992) ansicht('liste');
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
