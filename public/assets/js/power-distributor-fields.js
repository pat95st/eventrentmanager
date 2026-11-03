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
    var TYPE_LABEL = { rcd: 'FI', mcb: 'LS', rcbo: 'FI/LS' };

    function fromTemplate(selector, replacements) {
        var html = form.querySelector(selector).innerHTML;
        Object.keys(replacements).forEach(function (key) { html = html.split(key).join(replacements[key]); });
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        return wrapper.firstElementChild;
    }

    function deviceInfo() {
        var list = [];
        devices.querySelectorAll('[data-device-row]').forEach(function (row, i) {
            var type = row.querySelector('[data-device-type]').value;
            var label = row.querySelector('[data-device-label]').value.trim() || ('Schutzorgan ' + (i + 1));
            list.push({ key: row.querySelector('[data-device-key]').value, type: type, label: label, row: row });
        });
        return list;
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

    function refresh() {
        var list = deviceInfo();
        var byKey = {};
        list.forEach(function (d) { byKey[d.key] = d; });

        list.forEach(function (d) {
            var row = d.row;
            row.querySelectorAll('[data-rcd-field]').forEach(function (el) { el.style.display = d.type === 'mcb' ? 'none' : ''; });
            row.querySelectorAll('[data-mcb-field]').forEach(function (el) { el.style.display = d.type === 'rcd' ? 'none' : ''; });
            row.querySelector('[data-upstream-field]').style.display = d.type === 'mcb' ? '' : 'none';
            row.querySelector('[data-rated-label]').textContent = d.type === 'rcd' ? 'Nennstrom (optional)' : 'Sicherungsgröße';
            var upstream = row.querySelector('[data-upstream-select]');
            fillSelect(upstream, list.filter(function (o) { return o.type === 'rcd' && o.key !== d.key; }).map(function (o) {
                return { value: o.key, text: 'hinter FI ' + o.label };
            }), 'kein FI (LS direkt an der Zuleitung)');
            if (d.type !== 'mcb') { upstream.value = ''; }
        });

        outlets.querySelectorAll('[data-outlet-device]').forEach(function (select) {
            fillSelect(select, list.map(function (d) {
                var text;
                if (d.type === 'rcd') {
                    text = 'nur hinter FI ' + d.label;
                } else if (d.type === 'rcbo') {
                    text = 'hinter FI/LS ' + d.label;
                } else {
                    var up = d.row.querySelector('[data-upstream-select]').value;
                    text = up && byKey[up] ? 'hinter FI ' + byKey[up].label + ' und LS ' + d.label : 'nur hinter LS ' + d.label;
                }
                return { value: d.key, text: text };
            }), 'direkt von der Zuleitung (ohne Schutzorgan)');
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
    devices.addEventListener('input', function (event) { if (event.target.matches('[data-device-label]')) { refresh(); } });

    // Neue Zeilen ohne Schlüssel (nach Validierungsfehler) bekommen einen.
    devices.querySelectorAll('[data-device-key]').forEach(function (input) { if (!input.value) { input.value = 'n' + (counter++); } });
    if (outlets.children.length === 0 && devices.children.length === 0) {
        form.querySelector('[data-add-outlet]').click();
    }
    refresh();
})();
