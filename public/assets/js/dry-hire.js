/**
 * Dry-Hire-Preise in den Dokumentformularen (Angebot, AB, Rechnung) – Migration 141.
 *
 * Die Formulare rufen DryHire.init({...}) auf und fragen in ihrer
 * Preisvorschlag-Funktion DryHire.active()/price()/factor() ab. Dieses Modul
 * kümmert sich um die Checkbox (Standard aus dem Kunden), das Neuberechnen
 * nicht manuell geänderter Preise beim Umschalten und die Warnung an
 * Positionen ohne Dry-Hire-Preis.
 */
(function () {
    'use strict';
    var cfg = null;

    function data() { return (cfg && cfg.data) || { tiers: [], customers: [], prices: {}, checked: false, available: false }; }

    function factor(days) {
        var tiers = data().tiers.slice().sort(function (a, b) { return a.from - b.from; });
        if (!tiers.length || tiers[0].from !== 1) { tiers.unshift({ from: 1, pct: 100 }); }
        var total = 0, n = Math.max(1, days | 0);
        for (var d = 1; d <= n; d++) {
            var pct = 100;
            for (var i = 0; i < tiers.length; i++) { if (tiers[i].from <= d) { pct = tiers[i].pct; } }
            total += pct / 100;
        }
        return Math.round(total * 1e6) / 1e6;
    }

    function price(articleId) {
        var p = data().prices[articleId];
        return p === undefined || p === null || p === '' ? null : p;
    }

    function active() { return !!(cfg && cfg.checkbox && cfg.checkbox.checked); }

    function isDryHireCustomer(id) { return !!id && data().customers.indexOf(id) >= 0; }

    function rows() { return cfg ? Array.prototype.slice.call(cfg.container.querySelectorAll(cfg.rowSelector)) : []; }

    function articleIdOf(row) {
        var h = row.querySelector('[data-live-search-value]');
        return h ? h.value : '';
    }

    function updateWarning(row) {
        var priceInput = row.querySelector(cfg.priceSelector);
        if (!priceInput) { return; }
        var warn = row.querySelector('[data-dry-hire-warning]');
        var id = articleIdOf(row);
        var show = active() && id !== '' && price(id) === null;
        if (show && !warn) {
            warn = document.createElement('p');
            warn.setAttribute('data-dry-hire-warning', '');
            warn.style.cssText = 'margin-top:.25rem;font-size:.75rem;line-height:1.2;color:#b45309';
            warn.textContent = '⚠ Kein Dry-Hire-Preis hinterlegt – normaler Preis verwendet (Kunde ist Dry-Hire-Kunde).';
            priceInput.insertAdjacentElement('afterend', warn);
        } else if (!show && warn) {
            warn.remove();
        }
    }

    function updateSummary() {
        if (!cfg.summary) { return; }
        var missing = rows().filter(function (r) { var id = articleIdOf(r); return active() && id !== '' && price(id) === null; }).length;
        cfg.summary.hidden = missing === 0;
        cfg.summary.textContent = missing === 1
            ? '⚠ Für 1 Position ist kein Dry-Hire-Preis hinterlegt – dort wird der normale Preis berechnet.'
            : '⚠ Für ' + missing + ' Positionen ist kein Dry-Hire-Preis hinterlegt – dort wird der normale Preis berechnet.';
    }

    function refresh(resuggest) {
        if (!cfg) { return; }
        rows().forEach(function (row) {
            if (resuggest) {
                var priceInput = row.querySelector(cfg.priceSelector);
                if (priceInput && !priceInput.dataset.touched && articleIdOf(row) !== '') {
                    var s = cfg.suggest(articleIdOf(row));
                    if (s !== null) { priceInput.value = s; priceInput.dispatchEvent(new Event('change', { bubbles: true })); }
                }
            }
            updateWarning(row);
        });
        updateSummary();
        if (resuggest && typeof cfg.onChange === 'function') { cfg.onChange(); }
    }

    function syncVisibility() {
        var customerId = cfg.customerInput ? cfg.customerInput.value : '';
        var show = isDryHireCustomer(customerId) || active();
        cfg.wrap.hidden = !show;
        if (cfg.badge) { cfg.badge.hidden = !isDryHireCustomer(customerId); }
    }

    function init(options) {
        cfg = options;
        if (!cfg.checkbox || !cfg.container) { return; }
        cfg.checkbox.checked = !!data().checked;
        syncVisibility();
        cfg.checkbox.addEventListener('change', function () { syncVisibility(); refresh(true); });
        if (cfg.customerInput) {
            cfg.customerInput.addEventListener('change', function () {
                cfg.checkbox.checked = isDryHireCustomer(cfg.customerInput.value);
                syncVisibility();
                refresh(true);
            });
        }
        // manuell geänderte Preise nicht überschreiben
        cfg.container.addEventListener('input', function (e) {
            if (e.target.matches && e.target.matches(cfg.priceSelector)) { e.target.dataset.touched = '1'; }
        });
        // Warnung nach Artikelwechsel aktualisieren (nach dem Listener des Formulars)
        cfg.container.addEventListener('change', function (e) {
            if (e.target.matches && e.target.matches('[data-live-search-value]')) {
                var row = e.target.closest(cfg.rowSelector);
                if (row) { setTimeout(function () { updateWarning(row); updateSummary(); }, 0); }
            }
        });
        // neu eingefügte Zeilen (Mehrfachauswahl, „+ Position“)
        new MutationObserver(function () { refresh(false); }).observe(cfg.container, { childList: true, subtree: false });
        refresh(false);
    }

    window.DryHire = { init: init, active: active, price: price, factor: factor, refresh: refresh };
})();
