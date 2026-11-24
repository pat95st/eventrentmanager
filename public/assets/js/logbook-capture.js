// Capture form of the driver's logbook (migrations 184/185), shared by the
// page in the normal layout (logbook/form.php) and the mobile page of the QR
// code (logbook/capture.php). Configuration via attributes of #trip-form:
// data-templates (JSON of the outbound/return templates of the event),
// data-api-base (prefix of the JSON helpers), data-distance-touched.
(function () {
    var form = document.getElementById('trip-form');
    var $ = function (sel) { return form.querySelector(sel); };
    var $$ = function (sel) { return Array.prototype.slice.call(form.querySelectorAll(sel)); };
    if (!form) { return; }
    var templates = JSON.parse(form.getAttribute('data-templates') || 'null');
    var API_BASE = form.getAttribute('data-api-base') || '/fahrtenbuch';
    var distanceTouched = form.getAttribute('data-distance-touched') === '1';

    function category() {
        var checked = $('[data-category]:checked');
        return checked ? checked.value : 'event';
    }

    function update() {
        var cat = category();
        $$('[data-show-for]').forEach(function (el) {
            el.hidden = el.getAttribute('data-show-for').split(' ').indexOf(cat) === -1;
        });
        form.querySelector('[data-purpose-label]').textContent = cat === 'business' ? 'Zweck der Fahrt *' : 'Zweck / Notiz';
        ['from', 'to'].forEach(function (which) {
            var select = $('[data-location="' + which + '"]');
            $('[data-custom="' + which + '"]').hidden = select.value !== 'custom:';
        });
        var vehicle = $('[data-vehicle-select]');
        var fixed = $('[data-fixed-vehicle]');
        var odometerRequired = fixed ? fixed.getAttribute('data-odometer') === '1' : false;
        if (vehicle) {
            $('[data-other-vehicle]').hidden = vehicle.value !== 'other';
            var option = vehicle.options[vehicle.selectedIndex];
            odometerRequired = option && option.getAttribute('data-odometer') === '1';
        }
        var bypass = $('[data-bypass]');
        if (bypass) { bypass.hidden = !odometerRequired; }
        $$('[data-odometer-label]').forEach(function (label, i) {
            label.textContent = (i === 0 ? 'Km-Stand Abfahrt' : 'Km-Stand Ankunft') + (odometerRequired ? ' *' : '');
        });
        var driver = $('[data-driver-select]');
        var external = $('[data-external-fields]');
        if (driver && external) {
            external.hidden = driver.value !== 'external';
        }
        var round = $('[data-round-trip]').checked;
        form.querySelector('[data-distance-label]').textContent = round ? 'Kilometer (einfache Strecke)' : 'Gefahrene Kilometer';
        total();
    }

    function number(input) {
        var raw = input.value.replace(/[.\s]/g, '').replace(',', '.');
        return raw === '' || isNaN(raw) ? null : Math.round(parseFloat(raw));
    }

    function total() {
        var start = number($('[data-odometer="start"]'));
        var end = number($('[data-odometer="end"]'));
        var box = $('[data-total]');
        var row = $('[data-distance-row]');
        if (start !== null && end !== null) {
            row.hidden = true;
            box.hidden = false;
            box.innerHTML = end >= start ? 'Gefahrene Strecke laut Kilometerstand: <b>' + (end - start) + ' km</b>' : 'Der Kilometerstand bei Ankunft ist kleiner als bei Abfahrt.';
            return;
        }
        row.hidden = false;
        var km = number($('[data-distance]'));
        if (km !== null && $('[data-round-trip]').checked) {
            box.hidden = false;
            box.innerHTML = 'Gesamt (hin und zurück): <b>' + (km * 2) + ' km</b>';
        } else {
            box.hidden = true;
        }
    }

    function fillLocationGroups(options) {
        ['from', 'to'].forEach(function (which) {
            var group = $('[data-location="' + which + '"] [data-rental-group]');
            var select = $('[data-location="' + which + '"]');
            var current = select.value;
            group.innerHTML = '';
            options.forEach(function (o) {
                var opt = document.createElement('option');
                opt.value = o.value;
                opt.textContent = o.label;
                group.appendChild(opt);
            });
            group.hidden = options.length === 0;
            if (current.indexOf('event_venue:') === 0 || current.indexOf('tour_stop:') === 0) {
                select.value = Array.prototype.some.call(select.options, function (o) { return o.value === current; }) ? current : '';
            }
        });
    }

    function loadRental() {
        var select = $('[data-rental-select]');
        var id = select.value;
        var stopField = $('[data-tour-stop]');
        var stopSelect = $('[data-tour-stop-select]');
        if (!id) {
            templates = null;
            $('[data-templates]').hidden = true;
            stopField.hidden = true;
            fillLocationGroups([]);
            return;
        }
        fetch(API_BASE + '/veranstaltungen/' + encodeURIComponent(id) + '/orte', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) { return; }
                templates = { outbound: data.outbound, 'return': data['return'] };
                $('[data-templates]').hidden = false;
                fillLocationGroups(data.options || []);
                stopSelect.innerHTML = '<option value="">ganze Tour</option>';
                (data.stops || []).forEach(function (s) {
                    var opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.label;
                    stopSelect.appendChild(opt);
                });
                stopField.hidden = (data.stops || []).length === 0;
            });
    }

    function loadEvents() {
        var date = $('[data-trip-date]').value;
        var select = $('[data-rental-select]');
        var current = select.value;
        if (!date) { return; }
        fetch(API_BASE + '/veranstaltungen?datum=' + encodeURIComponent(date) + '&veranstaltung=' + encodeURIComponent(current), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) { return; }
                select.innerHTML = '<option value="">– bitte wählen –</option>';
                data.events.forEach(function (ev) {
                    var opt = document.createElement('option');
                    opt.value = ev.id;
                    opt.textContent = ev.label;
                    opt.selected = ev.id === current;
                    select.appendChild(opt);
                });
            });
    }

    function suggest(auto) {
        var from = $('[data-location="from"]').value;
        var to = $('[data-location="to"]').value;
        var hint = $('[data-suggest-hint]');
        if (!from || !to) {
            if (!auto) { hint.textContent = 'Bitte zuerst Start und Ziel wählen.'; }
            return;
        }
        if (auto && distanceTouched) { return; }
        if (!$('[data-suggest]')) { return; }
        var params = new URLSearchParams({
            von: from, von_text: form.elements.from_text.value,
            nach: to, nach_text: form.elements.to_text.value,
            veranstaltung: category() === 'event' ? $('[data-rental-select]').value : ''
        });
        hint.textContent = 'Strecke wird berechnet …';
        fetch('/fahrtenbuch/strecke?' + params.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : { km: null, hint: 'Die Strecke konnte nicht berechnet werden.' }; })
            .then(function (data) {
                if (data.km !== null && data.km !== undefined) {
                    $('[data-distance]').value = data.km;
                    hint.textContent = 'Vorschlag laut Routenplanung (einfache Strecke) – bitte bei Abweichung anpassen.';
                    distanceTouched = false;
                    total();
                } else {
                    hint.textContent = data.hint || '';
                }
            })
            .catch(function () { hint.textContent = 'Die Strecke konnte nicht berechnet werden.'; });
    }

    $$('[data-category]').forEach(function (el) { el.addEventListener('change', update); });
    ['[data-vehicle-select]', '[data-driver-select]', '[data-round-trip]'].forEach(function (sel) {
        var el = $(sel);
        if (el) { el.addEventListener('change', update); }
    });
    $$('[data-location]').forEach(function (el) {
        el.addEventListener('change', function () { update(); suggest(true); });
    });
    ['from_text', 'to_text'].forEach(function (name) {
        form.elements[name].addEventListener('change', function () { suggest(true); });
    });
    $$('[data-odometer]').forEach(function (el) { el.addEventListener('input', total); });
    $('[data-distance]').addEventListener('input', function () { distanceTouched = this.value !== ''; total(); });
    $('[data-rental-select]').addEventListener('change', loadRental);
    $('[data-trip-date]').addEventListener('change', loadEvents);
    if ($('[data-suggest]')) { $('[data-suggest]').addEventListener('click', function () { suggest(false); }); }
    $('[data-swap]').addEventListener('click', function () {
        var from = $('[data-location="from"]');
        var to = $('[data-location="to"]');
        var value = from.value; from.value = to.value; to.value = value;
        var text = form.elements.from_text.value; form.elements.from_text.value = form.elements.to_text.value; form.elements.to_text.value = text;
        update();
    });
    $$('[data-template]').forEach(function (button) {
        button.addEventListener('click', function () {
            var leg = templates && templates[button.getAttribute('data-template')];
            if (!leg) { return; }
            if (leg.from) { $('[data-location="from"]').value = leg.from; }
            if (leg.to) { $('[data-location="to"]').value = leg.to; }
            var today = new Date().toISOString().slice(0, 10);
            $('[data-trip-date]').value = leg.date <= today ? leg.date : today;
            update();
            suggest(true);
        });
    });
    update();
})();
