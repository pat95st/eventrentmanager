/**
 * Zeilen für Schutzorgane und Abgänge eines Stromverteilers im
 * Artikelformular (Migration 117), siehe resources/views/power-distributors/_fields.php.
 * Hält die Auswahllisten "Vorgeschaltet" und "Absicherung" aktuell.
 */
(function () {
    var form = document.querySelector('[data-distributor-fields]');
    if (!form) { return; }
    var devices = form.querySelector('[data-devices]');
    var outlets = form.querySelector('[data-outlets]');
    var counter = Date.now();
    var TYPE_LABEL = { rcd: 'FI', mcb: 'LS', rcbo: 'FI/LS', fuse: 'Sicherung' };

    function fromTemplate(selector, replacements) {
        var html = form.querySelector(selector).innerHTML;
        Object.keys(replacements).forEach(function (key) { html = html.split(key).join(replacements[key]); });
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        return wrapper.firstElementChild;
    }

    var THREE_PHASE = (form.getAttribute('data-three-phase-connectors') || '').split(',');
    var inlet = form.querySelector('[data-inlet-type]');

    function deviceInfo() {
        var list = [];
        devices.querySelectorAll('[data-device-row]').forEach(function (row, i) {
            var type = row.querySelector('[data-device-type]').value;
            var label = row.querySelector('[data-device-label]').value.trim() || ('Schutzorgan ' + (i + 1));
            var threeSingleBox = row.querySelector('[data-three-single]');
            var threeSingle = !!threeSingleBox && threeSingleBox.checked && (type === 'mcb' || type === 'fuse');
            var poles = parseInt(row.querySelector('[data-device-poles]').value, 10);
            if (type === 'fuse') { poles = threeSingle ? 3 : 1; } else if (threeSingle) { poles = 3; }
            list.push({
                key: row.querySelector('[data-device-key]').value, type: type, label: label, row: row,
                threeSingle: threeSingle, poles: isNaN(poles) ? null : poles,
                threePhase: threeSingle || (!isNaN(poles) && poles >= 3),
                singlePhase: !threeSingle && !isNaN(poles) && poles <= 2
            });
        });
        return list;
    }

    function setOptions(select, values) {
        var current = select.value || select.getAttribute('data-selected') || '';
        var wanted = values.join(',');
        if (select.getAttribute('data-current-list') === wanted) { return; }
        select.setAttribute('data-current-list', wanted);
        select.innerHTML = '<option value="">–</option>' + values.map(function (v) {
            return '<option value="' + v + '"' + (v === current ? ' selected' : '') + '>' + v + ' A</option>';
        }).join('');
        select.removeAttribute('data-selected');
    }

    function fillSelect(select, items, emptyText) {
        var current = select.value || select.getAttribute('data-selected') || '';
        select.innerHTML = '';
        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = emptyText;
        select.appendChild(empty);
        items.forEach(function (item) {
            var option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.text;
            if (item.value === current) { option.selected = true; }
            select.appendChild(option);
        });
        select.removeAttribute('data-selected');
    }

    function show(el, visible) { if (el) { el.style.display = visible ? '' : 'none'; } }

    function refresh() {
        var list = deviceInfo();
        var byKey = {};
        list.forEach(function (d) { byKey[d.key] = d; });

        list.forEach(function (d) {
            var row = d.row;
            row.querySelectorAll('[data-rcd-field]').forEach(function (el) { show(el, d.type === 'rcd' || d.type === 'rcbo'); });
            row.querySelectorAll('[data-mcb-field]').forEach(function (el) { show(el, d.type === 'mcb' || d.type === 'rcbo'); });
            row.querySelectorAll('[data-fuse-field]').forEach(function (el) { show(el, d.type === 'fuse'); });
            show(row.querySelector('[data-three-single-field]'), d.type === 'mcb' || d.type === 'fuse');
            show(row.querySelector('[data-device-poles]').parentNode, d.type !== 'fuse' && !d.threeSingle);
            show(row.querySelector('[data-phase-field]'), d.singlePhase);
            show(row.querySelector('[data-upstream-field]'), d.type === 'mcb' || d.type === 'fuse');
            row.querySelector('[data-rated-label]').textContent = d.type === 'rcd' ? 'Nennstrom (optional)' : 'Sicherungsgröße';
            var rated = row.querySelector('[data-rated-select]');
            setOptions(rated, (rated.getAttribute(d.type === 'fuse' ? 'data-fuse-currents' : 'data-mcb-currents') || '').split(','));
            var upstream = row.querySelector('[data-upstream-select]');
            fillSelect(upstream, list.filter(function (o) { return o.type === 'rcd' && o.key !== d.key; }).map(function (o) {
                return { value: o.key, text: 'hinter FI ' + o.label };
            }), 'kein FI (direkt an der Zuleitung)');
            if (d.type !== 'mcb' && d.type !== 'fuse') { upstream.value = ''; }
        });

        outlets.querySelectorAll('[data-outlet-row]').forEach(function (row) {
            var select = row.querySelector('[data-outlet-device]');
            var connector = row.querySelector('[data-outlet-connector]').value;
            var threePhaseOutlet = THREE_PHASE.indexOf(connector) !== -1;
            fillSelect(select, list.map(function (d) {
                var text;
                var name = (TYPE_LABEL[d.type] || '') + ' ' + d.label;
                if (d.type === 'rcd') {
                    text = 'nur hinter ' + name;
                } else if (d.type === 'rcbo') {
                    text = 'hinter ' + name;
                } else {
                    var up = d.row.querySelector('[data-upstream-select]').value;
                    text = up && byKey[up] ? 'hinter FI ' + byKey[up].label + ' und ' + name : 'nur hinter ' + name;
                }
                if (d.threeSingle) { text += ' (3 × 1-polig)'; } else if (d.poles) { text += ' (' + d.poles + '-polig)'; }
                return { value: d.key, text: text, invalid: threePhaseOutlet && d.singlePhase };
            }), 'direkt von der Zuleitung (ohne Schutzorgan)');
            // CEE rot: einzelne 1-/2-polige Absicherungen nicht wählbar (eine bereits gewählte bleibt sichtbar, der Server meldet den Fehler).
            Array.prototype.forEach.call(select.options, function (option) {
                var d = byKey[option.value];
                var invalid = !!d && threePhaseOutlet && d.singlePhase;
                option.disabled = invalid && !option.selected;
                if (invalid && option.textContent.indexOf('nicht für CEE rot') === -1) { option.textContent += ' – nicht für CEE rot'; }
            });
            var device = byKey[select.value];
            var supplyThreePhase = device ? device.threePhase : (inlet && THREE_PHASE.indexOf(inlet.value) !== -1);
            var phaseField = row.querySelector('[data-outlet-phase-field]');
            var needsPhase = !threePhaseOutlet && supplyThreePhase;
            show(phaseField, needsPhase);
            if (!needsPhase) { row.querySelector('[data-outlet-phase]').value = ''; }
        });
    }

    form.querySelector('[data-add-device]').addEventListener('click', function () {
        var key = 'n' + (counter++);
        devices.appendChild(fromTemplate('[data-template-device]', { '__I__': String(counter++), '__KEY__': key }));
        refresh();
    });
    form.querySelector('[data-add-outlet]').addEventListener('click', function () {
        outlets.appendChild(fromTemplate('[data-template-outlet]', { '__I__': String(counter++) }));
        refresh();
    });
    // Duplizieren: neue Zeile mit allen Einstellungen direkt unter der Vorlage.
    // Schutzorgane bekommen einen neuen Schlüssel und die nächste freie
    // F-Nummer (sonst eine leere Bezeichnung = automatisch).
    function nextLabel(label) {
        var match = /^([A-Za-z]+)(\d+)$/.exec(label.trim());
        if (!match) { return ''; }
        var used = {};
        devices.querySelectorAll('[data-device-label]').forEach(function (input) { used[input.value.trim().toLowerCase()] = true; });
        var number = parseInt(match[2], 10) + 1;
        while (used[(match[1] + number).toLowerCase()]) { number++; }
        return match[1] + number;
    }

    function fieldName(name) {
        var match = /\[([a-z_]+)\]$/.exec(name || '');
        return match ? match[1] : null;
    }

    function duplicate(source) {
        var isDevice = source.hasAttribute('data-device-row');
        var copy = isDevice
            ? fromTemplate('[data-template-device]', { '__I__': String(counter++), '__KEY__': 'n' + (counter++) })
            : fromTemplate('[data-template-outlet]', { '__I__': String(counter++) });
        var values = {};
        source.querySelectorAll('input[name], select[name]').forEach(function (field) { values[fieldName(field.name)] = field.value; });
        copy.querySelectorAll('input[name], select[name]').forEach(function (field) {
            var key = fieldName(field.name);
            if (key === 'key' || !(key in values)) { return; }
            if (key === 'label' && isDevice) { field.value = nextLabel(values.label); return; }
            if (field.tagName === 'SELECT' && field.hasAttribute('data-selected')) { field.setAttribute('data-selected', values[key]); }
            field.value = values[key];
        });
        source.parentNode.insertBefore(copy, source.nextSibling);
        refresh();
    }

    form.addEventListener('click', function (event) {
        var duplicateButton = event.target.closest('[data-duplicate-row]');
        if (duplicateButton) {
            duplicate(duplicateButton.closest('[data-device-row], [data-outlet-row]'));
            return;
        }
        var button = event.target.closest('[data-remove-row]');
        if (!button) { return; }
        button.closest('[data-device-row], [data-outlet-row]').remove();
        refresh();
    });
    devices.addEventListener('change', refresh);
    outlets.addEventListener('change', function (event) {
        if (event.target.matches('[data-outlet-connector], [data-outlet-device]')) { refresh(); }
    });
    if (inlet) { inlet.addEventListener('change', refresh); }
    devices.addEventListener('input', function (event) { if (event.target.matches('[data-device-label]')) { refresh(); } });

    // Neue Zeilen ohne Schlüssel (nach Validierungsfehler) bekommen einen.
    devices.querySelectorAll('[data-device-key]').forEach(function (input) { if (!input.value) { input.value = 'n' + (counter++); } });
    if (outlets.children.length === 0 && devices.children.length === 0) {
        form.querySelector('[data-add-outlet]').click();
    }
    refresh();
})();
