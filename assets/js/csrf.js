/*
 * Syncopa – Prüfzeichen gegen untergeschobene Aktionen (CSRF).
 * Wird im Seitenkopf geladen, bevor andere Skripte laufen. Das Prüfzeichen steht in <meta name="csrf-token">.
 * Es wird automatisch angehängt an:
 *  - alle POST-Formulare (verstecktes Feld _csrf)
 *  - fetch()- und XMLHttpRequest-Aufrufe an Syncopa, die nicht GET/HEAD sind (Kopfzeile X-CSRF-Token)
 * Links auf Lösch-Seiten (…_loeschen.php, ?delete=) werden als bestätigtes Formular (POST) abgeschickt.
 */
(function () {
    'use strict';
    var meta = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.getAttribute('content') : '';
    if (!token) return;
    window.SYNCOPA_CSRF = token;

    function gleicheHerkunft(url) {
        try { return new URL(url, location.href).origin === location.origin; } catch (e) { return false; }
    }
    function aendernd(methode) {
        methode = String(methode || 'GET').toUpperCase();
        return methode !== 'GET' && methode !== 'HEAD' && methode !== 'OPTIONS';
    }

    // fetch()
    if (window.fetch) {
        var originalFetch = window.fetch;
        window.fetch = function (eingabe, optionen) {
            try {
                var url = (eingabe && typeof eingabe === 'object' && 'url' in eingabe) ? eingabe.url : String(eingabe);
                var methode = (optionen && optionen.method) || (eingabe && typeof eingabe === 'object' && eingabe.method) || 'GET';
                if (aendernd(methode) && gleicheHerkunft(url)) {
                    optionen = Object.assign({}, optionen || {});
                    var h = new Headers(optionen.headers || (eingabe && typeof eingabe === 'object' && eingabe.headers) || {});
                    if (!h.has('X-CSRF-Token')) h.set('X-CSRF-Token', token);
                    optionen.headers = h;
                }
            } catch (e) { /* im Zweifel unverändert senden */ }
            return originalFetch.call(this, eingabe, optionen);
        };
    }

    // XMLHttpRequest (auch jQuery)
    var open = XMLHttpRequest.prototype.open, send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (methode, url) {
        this._syncopaPruefen = aendernd(methode) && gleicheHerkunft(url);
        return open.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function () {
        if (this._syncopaPruefen) { try { this.setRequestHeader('X-CSRF-Token', token); } catch (e) {} }
        return send.apply(this, arguments);
    };

    // Formulare
    function feldEinsetzen(form) {
        if (!form || String(form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
        if (form.getAttribute('action') && !gleicheHerkunft(form.action)) return;
        var feld = form.querySelector('input[name="_csrf"]');
        if (!feld) {
            feld = document.createElement('input');
            feld.type = 'hidden';
            feld.name = '_csrf';
            form.appendChild(feld);
        }
        feld.value = token;
    }
    function alleFormulare(wurzel) {
        (wurzel.querySelectorAll ? wurzel.querySelectorAll('form') : []).forEach(feldEinsetzen);
        if (wurzel.tagName === 'FORM') feldEinsetzen(wurzel);
    }
    document.addEventListener('submit', function (e) { feldEinsetzen(e.target); }, true);
    // auch Formulare, die per form.submit() abgeschickt werden (dabei gibt es kein submit-Ereignis)
    var originalSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () { feldEinsetzen(this); return originalSubmit.apply(this, arguments); };
    function beobachten() {
        alleFormulare(document);
        new MutationObserver(function (liste) {
            liste.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) alleFormulare(n); }); });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', beobachten); else beobachten();

    // Lösch-Links: nicht mehr per einfachem Aufruf, sondern als Formular mit Prüfzeichen abschicken.
    // Läuft nach den Bestätigungs-Rückfragen (onclick="return confirm(…)"), die die Aktion vorher anhalten können.
    function istLoeschLink(a) {
        var href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || a.target === '_blank' || !gleicheHerkunft(a.href)) return false;
        return /_loeschen\.php(\?|$)/.test(href) || /[?&]delete=/.test(href);
    }
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target instanceof Element ? e.target.closest('a[href]') : null;
        if (!a || !istLoeschLink(a)) return;
        e.preventDefault();
        var form = document.createElement('form');
        form.method = 'post';
        form.action = a.href;
        form.style.display = 'none';
        document.body.appendChild(form);
        feldEinsetzen(form);
        originalSubmit.call(form);
    });
})();
