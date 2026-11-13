        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/de.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <script>
    // Service Worker für die Installierbarkeit als App registrieren
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('sw.js').catch(function() {});
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function openSidebar() {
            sidebar?.classList.add('show');
            sidebarOverlay?.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeSidebar() {
            sidebar?.classList.remove('show');
            sidebarOverlay?.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        sidebarToggle?.addEventListener('click', openSidebar);
        sidebarClose?.addEventListener('click', closeSidebar);
        sidebarOverlay?.addEventListener('click', closeSidebar);
        
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        html.setAttribute('data-bs-theme', savedTheme); // aktiviert Bootstraps eigene Dark-Mode-Farben (-subtle/-emphasis Utilities)
        updateThemeIcon(savedTheme);

        themeToggle?.addEventListener('click', function() {
            const current = html.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            updateThemeIcon(next);
        });
        
        function updateThemeIcon(theme) {
            const icon = themeToggle?.querySelector('i');
            if (icon) {
                icon.className = theme === 'light' ? 'bi bi-moon' : 'bi bi-sun';
            }
        }
        
        // Close sidebar on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth > 991.98) {
                closeSidebar();
            }
        });
    });
    
    // DataTables Config
    $.extend(true, $.fn.dataTable.defaults, {
        language: { url: 'assets/js/libs/datatables-de-DE.json' },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Alle']],
        responsive: true,
        dom: '<"row align-items-center mb-2"<"col-auto"l><"col"f>><"table-responsive"t><"row align-items-center mt-2"<"col-sm-5"i><"col-sm-7"p>>'
    });
    
    function confirmDelete(msg) {
        return confirm(msg || 'Wirklich löschen?');
    }

    /*
     * Bestätigungen als eigenes Modal statt Browser-Dialog.
     * confirm() ist im Browser blockierend – deshalb: Beim ersten Aufruf während eines Klicks bzw. Absendens
     * wird die Aktion angehalten (confirm liefert false) und das Modal gezeigt. Bei „OK“ wird derselbe Klick
     * bzw. dasselbe Absenden wiederholt; dabei liefert confirm() true. So funktionieren alle bestehenden
     * onsubmit="return confirm(…)", onclick="…" und if (!confirm(…)) return; ohne Umbau.
     * Ohne erkennbaren Auslöser (z. B. nach einem Server-Aufruf) bleibt der Browser-Dialog.
     */
    (function () {
        const browserConfirm = window.confirm.bind(window);
        let ausloeser = null;      // { art: 'click'|'submit', el, submitter }
        let freigaben = 0;         // so viele confirm()-Aufrufe liefern beim Wiederholen true
        let offen = false;

        function merken(info) {
            ausloeser = info;
            setTimeout(() => { if (ausloeser === info) ausloeser = null; }, 0);
        }
        document.addEventListener('click', e => { if (e.target instanceof Element) merken({ art: 'click', el: e.target }); }, true);
        document.addEventListener('submit', e => merken({ art: 'submit', el: e.target, submitter: e.submitter || null }), true);

        function modal() {
            let m = document.getElementById('syncopaConfirm');
            if (m) return m;
            m = document.createElement('div');
            m.id = 'syncopaConfirm';
            m.className = 'modal fade syncopa-confirm';
            m.tabIndex = -1;
            m.setAttribute('aria-hidden', 'true');
            m.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
                + '<div class="modal-header"><h5 class="modal-title"><i class="bi"></i> <span></span></h5>'
                + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button></div>'
                + '<div class="modal-body"><p class="mb-0 syncopa-confirm-text"></p></div>'
                + '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>'
                + '<button type="button" class="btn syncopa-confirm-ok"></button></div></div></div>';
            document.body.appendChild(m);
            return m;
        }

        function zeigen(text, beiOk) {
            const m = modal();
            const gefaehrlich = /lösch|entfern|verwerf|vergessen|endgültig|zurücksetz|ausscheid|unwiderruflich/i.test(text);
            const loeschen = /lösch/i.test(text);
            m.querySelector('.modal-title span').textContent = gefaehrlich ? 'Bitte bestätigen' : 'Rückfrage';
            m.querySelector('.modal-title i').className = 'bi ' + (gefaehrlich ? 'bi-exclamation-triangle text-danger' : 'bi-question-circle text-primary');
            m.querySelector('.syncopa-confirm-text').textContent = text;
            const ok = m.querySelector('.syncopa-confirm-ok');
            ok.className = 'btn syncopa-confirm-ok ' + (gefaehrlich ? 'btn-danger' : 'btn-primary');
            ok.innerHTML = loeschen ? '<i class="bi bi-trash"></i> Löschen' : 'OK';
            const bs = bootstrap.Modal.getOrCreateInstance(m, { backdrop: false });
            let bestaetigt = false;
            ok.onclick = () => { bestaetigt = true; bs.hide(); };
            m.addEventListener('shown.bs.modal', () => ok.focus(), { once: true });
            m.addEventListener('hidden.bs.modal', () => { offen = false; if (bestaetigt) beiOk(); }, { once: true });
            offen = true;
            bs.show();
        }

        function wiederholen(a, anzahl) {
            freigaben = anzahl;
            try {
                if (a.art === 'submit') {
                    if (a.submitter && a.submitter.form === a.el) a.el.requestSubmit(a.submitter); else a.el.requestSubmit();
                } else {
                    a.el.click();
                }
            } finally {
                freigaben = 0;
            }
        }

        let letzteZusagen = { el: null, anzahl: 0 };
        window.confirm = function (text) {
            if (freigaben > 0) { freigaben--; return true; }
            const a = ausloeser;
            if (!a || offen || typeof bootstrap === 'undefined' || !document.body.contains(a.el)) return browserConfirm(text);
            ausloeser = null;
            // Mehrere Rückfragen hintereinander für dieselbe Aktion: bereits bestätigte beim Wiederholen überspringen
            const bisher = letzteZusagen.el === a.el ? letzteZusagen.anzahl : 0;
            zeigen(String(text ?? ''), () => {
                letzteZusagen = { el: a.el, anzahl: bisher + 1 };
                wiederholen(a, bisher + 1);
                letzteZusagen = { el: null, anzahl: 0 };
            });
            return false;
        };
    })();
    
    /**
     * Meldung als Toast oben rechts anzeigen (schwebt über dem Inhalt, nichts verschiebt sich).
     * type: success | danger | warning | info; Fehler und Warnungen bleiben länger stehen,
     * mit der Maus darüber bleibt der Toast offen.
     */
    function showToast(message, type = 'success', delay) {
        const typ = ({ error: 'danger', primary: 'info', secondary: 'info' })[type] || type;
        const icons = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-octagon-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed syncopa-toasts';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
        const el = document.createElement('div');
        el.className = 'toast syncopa-toast t-' + typ;
        el.setAttribute('role', typ === 'danger' ? 'alert' : 'status');
        el.innerHTML = '<div class="toast-body"><i class="bi ' + (icons[typ] || icons.info) + ' toast-icon"></i>'
            + '<div class="toast-text"></div><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Schließen"></button></div>';
        const text = el.querySelector('.toast-text');
        if (message instanceof Node) text.appendChild(message); else text.innerHTML = message;
        container.appendChild(el);
        const dauer = delay || ((typ === 'danger' || typ === 'warning') ? 10000 : 5000);
        new bootstrap.Toast(el, { delay: dauer }).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
        return el;
    }

    /*
     * Hinweis-Fenster (alert) als Toast statt Browser-Dialog. Die Art wird aus dem Text erkannt.
     * Lädt die Seite direkt danach neu oder wechselt sie, wird der Hinweis auf der nächsten Seite gezeigt.
     */
    (function () {
        const SCHLUESSEL = 'syncopaHinweise';
        let neue = [];
        function art(text) {
            if (/fehler|fehlgeschlagen|ungültig|nicht möglich|konnte nicht|nicht gefunden|keine berechtigung|error|❌/i.test(text)) return 'danger';
            if (/erfolgreich|gespeichert|kopiert|gesendet|erledigt|✅/i.test(text)) return 'success';
            if (/bitte|muss|fehlt|zuerst|übersprungen|achtung|⚠/i.test(text)) return 'warning';
            return 'info';
        }
        function zeigen(text, typ) {
            const div = document.createElement('div');
            div.style.whiteSpace = 'pre-line';
            div.textContent = text;
            showToast(div, typ);
        }
        window.alert = function (nachricht) {
            const text = String(nachricht ?? '').replace(/^\s*(✅|❌|⚠️?)\s*/u, '').replace(/\n\s*\n+/g, '\n').trim();
            if (!text) return;
            const typ = art(String(nachricht ?? ''));
            const eintrag = { text, typ, zeit: Date.now() };
            neue.push(eintrag);
            setTimeout(() => { neue = neue.filter(e => e !== eintrag); }, 2000);
            zeigen(text, typ);
        };
        // Hinweise kurz vor einem Seitenwechsel für die nächste Seite merken
        window.addEventListener('pagehide', () => {
            if (!neue.length) return;
            try { sessionStorage.setItem(SCHLUESSEL, JSON.stringify(neue)); } catch (e) {}
        });
        try {
            const gemerkt = JSON.parse(sessionStorage.getItem(SCHLUESSEL) || '[]');
            sessionStorage.removeItem(SCHLUESSEL);
            gemerkt.filter(e => Date.now() - e.zeit < 15000).forEach(e => zeigen(e.text, e.typ));
        } catch (e) {}
    })();

    // Flash-Meldungen (nach Speichern, Löschen …) als Toast zeigen
    document.querySelectorAll('template.flash-toast').forEach(t => {
        showToast(t.content.cloneNode(true), t.dataset.typ || 'info');
        t.remove();
    });

    // Erfolgs-/Fehlermeldungen, die Seiten selbst als .alert ausgeben, ebenfalls als Toast zeigen –
    // statt sie nach ein paar Sekunden aus der Seite zu entfernen (dabei verrutschte der Inhalt).
    // Hinweisboxen (info/warning ohne Schließen-Knopf), Boxen mit id, in Dialogen und ausgeblendete bleiben, wo sie sind.
    document.querySelectorAll('main .alert:not(.alert-permanent)').forEach(el => {
        if (el.id || el.closest('.modal, .offcanvas, template') || el.offsetParent === null) return;
        const typ = ['success', 'danger', 'warning', 'info'].find(k => el.classList.contains('alert-' + k));
        if (!typ) return;
        if (!(typ === 'success' || typ === 'danger' || el.classList.contains('alert-dismissible'))) return;
        const inhalt = document.createDocumentFragment();
        el.querySelectorAll('.btn-close').forEach(b => b.remove());
        while (el.firstChild) inhalt.appendChild(el.firstChild);
        el.remove();
        // eigenes Symbol am Anfang weglassen – der Toast bringt eines mit
        const erstes = Array.from(inhalt.childNodes).find(n => n.nodeType === 1 || n.textContent.trim() !== '');
        if (erstes && erstes.nodeType === 1 && erstes.matches('i.bi')) erstes.remove();
        showToast(inhalt, typ);
    });
    
    // Form validation
    document.querySelectorAll('.needs-validation').forEach(form => {
        form.addEventListener('submit', e => {
            if (!form.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
            form.classList.add('was-validated');
        });
    });
    
    </script>
</body>
</html>
