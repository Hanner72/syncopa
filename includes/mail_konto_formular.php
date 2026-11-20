<?php
// includes/mail_konto_formular.php
// Dialog zum Anlegen/Bearbeiten eines E-Mail-Postfachs (Mein Konto und Funktions-Postfächer).
// Erwartet: $formularFunktion (bool) – Funktions-Postfach mit Rollenauswahl; $formularRollen (für Funktions-Postfächer)
?>
<div class="modal fade" id="postfachModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" id="postfachForm" autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title" id="postfachTitel">Postfach</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="aktion" value="postfach_speichern">
                <input type="hidden" name="id">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">E-Mail-Adresse *</label>
                        <input type="email" class="form-control" name="email" required placeholder="obmann@verein.at">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Name beim Empfänger</label>
                        <input type="text" class="form-control" name="anzeigename" maxlength="150" placeholder="z. B. Obmann Musikverein">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bezeichnung in Syncopa</label>
                        <input type="text" class="form-control" name="bezeichnung" maxlength="100" placeholder="leer = E-Mail-Adresse">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Anbieter</label>
                        <select class="form-select" id="postfachAnbieter">
                            <option value="">Server selbst eintragen …</option>
                            <?php foreach (MailKonto::ANBIETER as $schluessel => $a): ?>
                            <option value="<?php echo $schluessel; ?>" data-werte="<?php echo htmlspecialchars(json_encode(array_slice($a, 1))); ?>"><?php echo htmlspecialchars($a[0]); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Füllt die Server-Angaben aus. Bei eigener Vereins-Domain stehen sie in den Unterlagen des Webhosters.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Benutzername</label>
                        <input type="text" class="form-control" name="benutzername" placeholder="meist die E-Mail-Adresse" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Passwort <span data-pw-pflicht>*</span></label>
                        <input type="password" class="form-control" name="passwort" autocomplete="new-password">
                        <div class="form-text" data-pw-hinweis>Wird verschlüsselt gespeichert.</div>
                    </div>
                </div>

                <h6 class="mt-4 mb-2">Posteingang (IMAP)</h6>
                <div class="row g-2">
                    <div class="col-md-6"><input type="text" class="form-control" name="imap_host" placeholder="imap.anbieter.at" required></div>
                    <div class="col-md-2"><input type="number" class="form-control" name="imap_port" value="993" min="1" max="65535" title="Port"></div>
                    <div class="col-md-4"><select class="form-select" name="imap_sicherheit"><?php foreach (MailKonto::SICHERHEIT as $w => $t): ?><option value="<?php echo $w; ?>"><?php echo $t; ?></option><?php endforeach; ?></select></div>
                </div>
                <h6 class="mt-3 mb-2">Postausgang (SMTP)</h6>
                <div class="row g-2">
                    <div class="col-md-6"><input type="text" class="form-control" name="smtp_host" placeholder="smtp.anbieter.at" required></div>
                    <div class="col-md-2"><input type="number" class="form-control" name="smtp_port" value="465" min="1" max="65535" title="Port"></div>
                    <div class="col-md-4"><select class="form-select" name="smtp_sicherheit"><?php foreach (MailKonto::SICHERHEIT as $w => $t): ?><option value="<?php echo $w; ?>"><?php echo $t; ?></option><?php endforeach; ?></select></div>
                </div>

                <div class="mt-3">
                    <label class="form-label">Signatur</label>
                    <textarea class="form-control" name="signatur" rows="3" placeholder="z. B.&#10;Mit musikalischen Grüßen&#10;Max Muster, Obmann"></textarea>
                </div>

                <?php if (!empty($formularFunktion)): ?>
                <div class="mt-3">
                    <label class="form-label">Freigegeben für die Rollen</label>
                    <div class="row">
                        <?php foreach ($formularRollen as $r): ?>
                        <div class="col-md-4"><div class="form-check">
                            <input class="form-check-input" type="checkbox" name="rollen[]" value="<?php echo (int)$r['id']; ?>" id="rolle<?php echo (int)$r['id']; ?>">
                            <label class="form-check-label" for="rolle<?php echo (int)$r['id']; ?>"><?php echo htmlspecialchars(ucfirst($r['name'])); ?></label>
                        </div></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">Alle Benutzer mit einer dieser Rollen sehen das Postfach im E-Mail-Programm. Wechselt z. B. der Obmann, bekommt der Nachfolger es automatisch.</div>
                </div>
                <?php endif; ?>

                <div class="alert alert-permanent small py-2 mt-3 mb-0 d-none" id="postfachTest"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary me-auto" id="postfachTesten"><i class="bi bi-plug"></i> Verbindung testen</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Speichern</button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('postfachModal'), f = document.getElementById('postfachForm');
    const feld = n => f.elements.namedItem(n);
    const testBox = document.getElementById('postfachTest');
    document.getElementById('postfachAnbieter').addEventListener('change', function () {
        const o = this.selectedOptions[0]; if (!o.dataset.werte) return;
        const [ih, ip, is, sh, sp, ss] = JSON.parse(o.dataset.werte);
        feld('imap_host').value = ih; feld('imap_port').value = ip; feld('imap_sicherheit').value = is;
        feld('smtp_host').value = sh; feld('smtp_port').value = sp; feld('smtp_sicherheit').value = ss;
    });
    // Port passend zur Sicherheit vorschlagen
    [['imap', { ssl: 993, tls: 143, keine: 143 }], ['smtp', { ssl: 465, tls: 587, keine: 25 }]].forEach(([p, ports]) =>
        feld(p + '_sicherheit').addEventListener('change', e => { feld(p + '_port').value = ports[e.target.value]; }));
    feld('email').addEventListener('change', e => { if (!feld('benutzername').value) feld('benutzername').value = e.target.value; });

    window.postfachOeffnen = function (daten) {
        f.reset(); testBox.classList.add('d-none');
        f.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false);
        document.getElementById('postfachTitel').textContent = daten ? 'Postfach bearbeiten' : 'Postfach hinzufügen';
        feld('id').value = daten ? daten.id : '';
        if (daten) {
            ['email', 'anzeigename', 'bezeichnung', 'benutzername', 'imap_host', 'imap_port', 'imap_sicherheit', 'smtp_host', 'smtp_port', 'smtp_sicherheit', 'signatur']
                .forEach(n => { if (feld(n)) feld(n).value = daten[n] ?? ''; });
            (daten.rollen || []).forEach(r => { const c = document.getElementById('rolle' + r); if (c) c.checked = true; });
        }
        feld('passwort').required = !daten;
        f.querySelector('[data-pw-pflicht]').classList.toggle('d-none', !!daten);
        f.querySelector('[data-pw-hinweis]').textContent = daten ? 'Leer lassen, um das gespeicherte Passwort zu behalten.' : 'Wird verschlüsselt gespeichert.';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    };
    document.getElementById('postfachTesten').addEventListener('click', function () {
        const knopf = this, fd = new FormData(f);
        fd.set('aktion', 'testen');
        knopf.disabled = true; testBox.className = 'alert alert-permanent alert-secondary small py-2 mt-3 mb-0';
        testBox.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verbindung wird geprüft …';
        fetch('api/mail.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
            if (!d.success) { testBox.className = 'alert alert-permanent alert-danger small py-2 mt-3 mb-0'; testBox.textContent = d.error; return; }
            const zeile = (name, e) => `<div>${e === true ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>'} ${name}: ${e === true ? 'in Ordnung' : e.replace(/[<>&]/g, '')}</div>`;
            testBox.className = 'alert alert-permanent small py-2 mt-3 mb-0 ' + (d.ergebnis.imap === true && d.ergebnis.smtp === true ? 'alert-success' : 'alert-warning');
            testBox.innerHTML = zeile('Posteingang (IMAP)', d.ergebnis.imap) + zeile('Postausgang (SMTP)', d.ergebnis.smtp);
        }).catch(() => { testBox.className = 'alert alert-permanent alert-danger small py-2 mt-3 mb-0'; testBox.textContent = 'Keine Verbindung zum Server.'; })
          .finally(() => knopf.disabled = false);
    });
});
</script>
