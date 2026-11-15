/**
 * Mixing console section of the article form (migration 154), see
 * resources/views/mixing-consoles/_fields.php. Shows the section for the
 * category "Tonmischpult", switches between analog and digital fields, adds
 * and removes rows (interfaces, DSP channels, expansion cards), keeps totals
 * up to date and only offers stageboxes whose protocol the console provides.
 */
(function () {
    var section = document.getElementById('mixing_console_section');
    if (!section) { return; }
    var fields = section.querySelector('[data-mixing-console-fields]');
    var category = document.getElementById('category_id');
    var consoleCategory = section.getAttribute('data-mixing-console-category');
    var counter = Date.now();

    function syncVisibility() {
        section.classList.toggle('hidden', !(category && consoleCategory && category.value === consoleCategory));
    }

    function selectedType() {
        var checked = fields.querySelector('[data-mc-type]:checked');
        return checked ? checked.value : '';
    }

    function syncType() {
        var type = selectedType();
        fields.querySelectorAll('[data-mc-pane]').forEach(function (pane) {
            pane.classList.toggle('hidden', pane.getAttribute('data-mc-pane') !== type);
        });
    }

    function toInt(value) {
        var number = parseInt(value, 10);
        return isNaN(number) || number < 0 ? 0 : number;
    }

    function syncTotals() {
        fields.querySelectorAll('[data-mc-sum]').forEach(function (group) {
            var total = 0;
            group.querySelectorAll('[data-mc-count]').forEach(function (input) { total += toInt(input.value); });
            group.querySelector('[data-mc-total]').textContent = total;
        });
        var channels = 0;
        fields.querySelectorAll('[data-rows="channel"] input[name$="[quantity]"]').forEach(function (input) { channels += toInt(input.value); });
        var channelTotal = fields.querySelector('[data-mc-channel-total]');
        if (channelTotal) { channelTotal.textContent = channels; }
    }

    function consoleManufacturer() {
        var input = document.querySelector('input[name="manufacturer_id"], select[name="manufacturer_id"]');
        return input ? input.value : fields.getAttribute('data-manufacturer-id');
    }

    function syncStageboxes() {
        var protocols = {};
        fields.querySelectorAll('[data-protocol-select]').forEach(function (select) {
            if (select.value) { protocols[select.value] = true; }
        });
        var manufacturer = consoleManufacturer();
        var visible = 0;
        var rows = fields.querySelectorAll('[data-stagebox-row]');
        rows.forEach(function (row) {
            var select = row.querySelector('[data-stagebox-select]');
            var offered = !!protocols[row.getAttribute('data-protocol')];
            var show = offered || select.value !== '';
            row.classList.toggle('hidden', !show);
            if (show) { visible++; }
            var rowManufacturer = row.getAttribute('data-manufacturer');
            var otherManufacturer = !!manufacturer && !!rowManufacturer && rowManufacturer !== manufacturer;
            row.querySelector('[data-other-manufacturer]').classList.toggle('hidden', !otherManufacturer);
            var nativeOption = select.querySelector('option[value="native"]');
            if (nativeOption) {
                nativeOption.disabled = otherManufacturer;
                if (otherManufacturer && select.value === 'native') { select.value = 'limited'; }
            }
        });
        var empty = fields.querySelector('[data-mc-no-stagebox]');
        if (empty) { empty.classList.toggle('hidden', rows.length === 0 || visible > 0); }
    }

    function addRow(kind) {
        var template = fields.querySelector('[data-row-template="' + kind + '"]');
        var list = fields.querySelector('[data-rows="' + kind + '"]');
        var wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.split('__INDEX__').join(String(counter++)).trim();
        var row = wrapper.firstElementChild;
        list.appendChild(row);
        var first = row.querySelector('input, select');
        if (first) { first.focus(); }
    }

    fields.addEventListener('click', function (event) {
        var add = event.target.closest('[data-add-row]');
        if (add) {
            event.preventDefault();
            addRow(add.getAttribute('data-add-row'));
            syncAll();
            return;
        }
        var remove = event.target.closest('[data-remove-row]');
        if (remove) {
            event.preventDefault();
            remove.closest('[data-row]').remove();
            syncAll();
        }
    });

    function syncAll() {
        syncType();
        syncTotals();
        syncStageboxes();
    }

    if (category) { category.addEventListener('change', syncVisibility); }
    fields.addEventListener('input', syncTotals);
    fields.addEventListener('change', syncAll);
    // The manufacturer is picked in a live search field that changes its value without events.
    fields.addEventListener('focusin', syncStageboxes);
    syncVisibility();
    syncAll();
})();
