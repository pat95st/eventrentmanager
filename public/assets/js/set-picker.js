/**
 * Set-Auswahl für Angebot, Auftragsbestätigung und Rechnung (Migration 113).
 *
 * Ein Set wird als Freitext-Position mit Verweis (free_text_set_id[]) zum
 * Festpreis eingefügt, gewählte Upgrades als weitere Freitext-Positionen
 * (free_text_set_upgrade_id[]). Preise solcher Zeilen sind schreibgeschützt;
 * Zeitregeln und Einsatztage prüft der Server beim Speichern.
 *
 * Nutzung im Formular:
 *   SetPicker.attach(buttonElement, function (rowData) { addFreeTextRow(rowData); });
 *   // in addFreeTextRow(data): SetPicker.decorateRow(row, data);
 */
(function () {
    'use strict';

    var cache = null;

    function load() {
        if (cache !== null) {
            return Promise.resolve(cache);
        }

        return fetch('/sets/auswahl', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) { cache = data.items || []; return cache; });
    }

    function euro(value) {
        return Number(value || 0).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined) { node.textContent = text; }
        return node;
    }

    function decorateRow(row, data) {
        data = data || {};
        var setId = data.set_id || '';
        var upgradeId = data.set_upgrade_id || '';
        var setInput = row.querySelector('[data-set-id]');
        var upgradeInput = row.querySelector('[data-set-upgrade-id]');
        if (setInput) { setInput.value = setId; }
        if (upgradeInput) { upgradeInput.value = upgradeId; }
        if (!setId && !upgradeId) { return; }

        var badge = row.querySelector('[data-set-badge]');
        if (badge) {
            badge.textContent = setId ? 'Set' : 'Upgrade';
            badge.classList.remove('hidden');
        }
        var price = row.querySelector('input[name="free_text_unit_price[]"]');
        if (price) {
            price.readOnly = true;
            price.title = 'Festpreis aus dem Set';
            price.classList.add('bg-slate-50');
        }
    }

    function openModal(onAdd) {
        var overlay = el('div', 'fixed inset-0 z-50 flex items-center justify-center p-4');
        overlay.style.background = 'rgba(15, 23, 42, 0.5)';
        var panel = el('div', 'w-full max-w-lg overflow-y-auto rounded-lg bg-white p-4 shadow-xl dark:bg-slate-900');
        panel.style.maxHeight = '85vh';
        overlay.appendChild(panel);
        panel.appendChild(el('h2', 'mb-3 text-base font-semibold text-slate-900 dark:text-slate-100', 'Set hinzufügen'));
        var body = el('div', 'text-sm text-slate-600 dark:text-slate-300', 'Sets werden geladen …');
        panel.appendChild(body);
        var actions = el('div', 'mt-4 flex items-center justify-end gap-3');
        var cancel = el('button', 'text-sm text-slate-500 hover:underline dark:text-slate-400', 'Abbrechen');
        cancel.type = 'button';
        var confirm = el('button', 'rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700', 'Übernehmen');
        confirm.type = 'button';
        confirm.disabled = true;
        actions.appendChild(cancel);
        actions.appendChild(confirm);
        panel.appendChild(actions);
        document.body.appendChild(overlay);

        function close() { overlay.remove(); }
        cancel.addEventListener('click', close);
        overlay.addEventListener('click', function (event) { if (event.target === overlay) { close(); } });

        var selected = null;
        load().then(function (sets) {
            body.textContent = '';
            if (sets.length === 0) {
                body.textContent = 'Es sind noch keine aktiven Sets angelegt (Artikel › Sets).';
                return;
            }
            var list = el('div', 'divide-y divide-slate-100 dark:divide-slate-800');
            body.appendChild(list);
            var upgradesBox = el('div', 'mt-3 hidden rounded-md border border-slate-200 p-3 dark:border-slate-700');
            body.appendChild(upgradesBox);

            sets.forEach(function (set) {
                var label = el('label', 'flex cursor-pointer items-start gap-3 py-2');
                var radio = el('input');
                radio.type = 'radio';
                radio.name = 'set-picker-choice';
                radio.className = 'mt-1';
                label.appendChild(radio);
                var text = el('div', 'min-w-0 flex-1');
                var head = el('div', 'flex items-baseline justify-between gap-2');
                head.appendChild(el('span', 'font-medium text-slate-800 dark:text-slate-100', set.name));
                head.appendChild(el('span', 'text-slate-700 dark:text-slate-300', euro(set.price)));
                text.appendChild(head);
                text.appendChild(el('div', 'text-xs text-slate-500 dark:text-slate-400', set.deploymentDays + ' Einsatztag(e)' + (set.rules ? ' · ' + set.rules : '')));
                text.appendChild(el('div', 'text-xs text-slate-500 dark:text-slate-400', set.contents));
                label.appendChild(text);
                list.appendChild(label);

                radio.addEventListener('change', function () {
                    selected = set;
                    confirm.disabled = false;
                    upgradesBox.textContent = '';
                    upgradesBox.classList.toggle('hidden', set.upgrades.length === 0);
                    if (set.upgrades.length === 0) { return; }
                    upgradesBox.appendChild(el('p', 'mb-2 text-xs font-medium text-slate-500 dark:text-slate-400', 'Upgrades (optional)'));
                    set.upgrades.forEach(function (upgrade) {
                        var upgradeLabel = el('label', 'flex cursor-pointer items-start gap-2 py-1');
                        var checkbox = el('input');
                        checkbox.type = 'checkbox';
                        checkbox.value = upgrade.id;
                        checkbox.className = 'mt-1';
                        checkbox.setAttribute('data-upgrade-choice', '');
                        upgradeLabel.appendChild(checkbox);
                        var upgradeText = el('div', 'min-w-0 flex-1');
                        var upgradeHead = el('div', 'flex items-baseline justify-between gap-2');
                        upgradeHead.appendChild(el('span', 'text-slate-800 dark:text-slate-100', upgrade.name));
                        upgradeHead.appendChild(el('span', 'text-slate-700 dark:text-slate-300', '+' + euro(upgrade.price)));
                        upgradeText.appendChild(upgradeHead);
                        upgradeText.appendChild(el('div', 'text-xs text-slate-500 dark:text-slate-400', upgrade.contents));
                        upgradeLabel.appendChild(upgradeText);
                        upgradesBox.appendChild(upgradeLabel);
                    });
                });
            });

            confirm.addEventListener('click', function () {
                if (!selected) { return; }
                onAdd({ description: selected.name, quantity: '1', unit_price: selected.price, set_id: selected.id });
                upgradesBox.querySelectorAll('[data-upgrade-choice]:checked').forEach(function (checkbox) {
                    var upgrade = selected.upgrades.filter(function (u) { return u.id === checkbox.value; })[0];
                    if (upgrade) {
                        onAdd({ description: 'Upgrade: ' + upgrade.name, quantity: '1', unit_price: upgrade.price, set_upgrade_id: upgrade.id });
                    }
                });
                close();
            });
        }).catch(function () {
            body.textContent = 'Die Sets konnten nicht geladen werden.';
        });
    }

    function attach(button, addRow) {
        if (!button || button.hasAttribute('data-set-picker-ready')) { return; }
        button.setAttribute('data-set-picker-ready', '');
        button.addEventListener('click', function () {
            openModal(function (rowData) {
                addRow(rowData);
                var form = button.closest('form');
                if (form) { form.dispatchEvent(new Event('input', { bubbles: true })); }
            });
        });
    }

    window.SetPicker = { attach: attach, decorateRow: decorateRow };
})();
