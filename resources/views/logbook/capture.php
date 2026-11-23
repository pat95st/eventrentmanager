<?php
/**
 * Mobile capture page of the driver's logbook (migration 184): records a new
 * trip or edits one. Standalone page in the style of the audio live page.
 *
 * @var \App\Domain\Logbook\LogbookEntry|null $entry
 * @var array<string, mixed> $form values by input name
 * @var array<string, string> $errors
 * @var \App\Domain\User\User $user
 * @var bool $manager
 * @var \App\Domain\Vehicle\Vehicle[] $vehicles
 * @var \App\Domain\Vehicle\Vehicle[] $trailers
 * @var array<string, string> $drivers user id => name (managers only)
 * @var list<array{id: string, label: string, isTour: bool}> $events
 * @var list<array{group: string, options: list<array{value: string, label: string}>}> $generalLocations
 * @var array{options: list<array{value: string, label: string}>, stops: list<array{id: string, label: string}>, outbound: array, return: array}|null $rentalLocations
 * @var \App\Domain\Logbook\LogbookEntryChange[] $history
 * @var \DateTimeImmutable|null $editableUntil
 * @var string $driverLabel
 */

use App\Domain\Logbook\LogbookCategory;
use App\Infrastructure\Http\CompanyBranding;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$v = static fn (string $key): string => is_scalar($form[$key] ?? null) ? (string) $form[$key] : '';
$on = static fn (string $key): bool => ($form[$key] ?? null) !== null && $form[$key] !== '' && $form[$key] !== false;
$err = static fn (string $key): string => isset($errors[$key]) ? '<span class="error">' . e($errors[$key]) . '</span>' : '';
$cls = static fn (string ...$keys): string => array_intersect_key($errors, array_flip($keys)) !== [] ? 'field err' : 'field';
$logo = CompanyBranding::darkLogoUrl() ?? CompanyBranding::logoUrl();
$category = $v('category') !== '' ? $v('category') : LogbookCategory::Event->value;
$categories = LogbookCategory::selectable();
if ($entry?->category === LogbookCategory::Unclear) {
    $categories[] = LogbookCategory::Unclear;
}
$isTour = false;
foreach ($events as $event) {
    if ($event['id'] === $v('rental_id')) {
        $isTour = $event['isTour'];
    }
}
$action = $entry !== null ? '/fahrtenbuch/' . $entry->id : '/fahrtenbuch';
$pageTitle = ($entry !== null ? 'Fahrt bearbeiten' : 'Fahrt erfassen') . ' – Fahrtenbuch';

/** Options of a start/destination select. */
$locationOptions = static function (string $selected) use ($generalLocations, $rentalLocations): string {
    $html = '<option value="">– bitte wählen –</option>';
    $html .= '<optgroup label="Veranstaltung" data-rental-group' . (($rentalLocations['options'] ?? []) === [] ? ' hidden' : '') . '>';
    foreach ($rentalLocations['options'] ?? [] as $option) {
        $html .= '<option value="' . e($option['value']) . '"' . ($option['value'] === $selected ? ' selected' : '') . '>' . e($option['label']) . '</option>';
    }
    $html .= '</optgroup>';
    foreach ($generalLocations as $group) {
        $html .= '<optgroup label="' . e($group['group']) . '">';
        foreach ($group['options'] as $option) {
            $html .= '<option value="' . e($option['value']) . '"' . ($option['value'] === $selected ? ' selected' : '') . '>' . e($option['label']) . '</option>';
        }
        $html .= '</optgroup>';
    }
    $html .= '<optgroup label="Sonstiges"><option value="custom:"' . ($selected === 'custom:' ? ' selected' : '') . '>Freie Adresse eingeben …</option></optgroup>';

    return $html;
};
require __DIR__ . '/_mobile-head.php';
?>
<body>
<header class="top">
    <div class="wrap top-inner">
        <?php if ($logo !== null): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
        <div class="title">
            <h1><?= $entry !== null ? 'Fahrt bearbeiten' : 'Fahrt erfassen' ?></h1>
            <p>Fahrtenbuch<?= $entry !== null ? ' · erfasst am ' . e($entry->createdAt->format('d.m.Y H:i')) : '' ?></p>
        </div>
        <div class="who"><?= e($user->displayName()) ?><br><a href="/fahrtenbuch">Zum Fahrtenbuch</a></div>
    </div>
</header>

<main class="wrap">
    <?php if ($errors !== []): ?>
        <div class="alert err" role="alert">Bitte die markierten Angaben prüfen.</div>
    <?php endif; ?>
    <?php if ($editableUntil !== null): ?>
        <div class="alert info">Du kannst diese Fahrt bis <?= e($editableUntil->format('d.m.Y H:i')) ?> Uhr ändern oder löschen.</div>
    <?php endif; ?>

    <form method="post" action="<?= e($action) ?>" id="trip-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <?php if ($v('return_to_rental') !== ''): ?><input type="hidden" name="return_to_rental" value="<?= e($v('return_to_rental')) ?>"><?php endif; ?>

        <section class="card">
            <h2>Wann &amp; wer</h2>
            <div class="grid3">
                <label class="<?= $cls('tripDate') ?>">Datum *
                    <input type="date" name="trip_date" value="<?= e($v('trip_date')) ?>" required data-trip-date><?= $err('tripDate') ?>
                </label>
                <label class="<?= $cls('departTime') ?>">Abfahrt
                    <input type="time" name="depart_time" value="<?= e($v('depart_time')) ?>"><?= $err('departTime') ?>
                </label>
                <label class="<?= $cls('arriveTime') ?>">Ankunft
                    <input type="time" name="arrive_time" value="<?= e($v('arrive_time')) ?>"><?= $err('arriveTime') ?>
                </label>
            </div>
            <?php if ($manager): ?>
                <label class="<?= $cls('driverUserId') ?>">Fahrer
                    <select name="driver_user_id" data-driver-select>
                        <?php foreach ($drivers as $driverId => $driverName): ?>
                            <option value="<?= e($driverId) ?>" <?= ($v('driver_user_id') !== '' ? $v('driver_user_id') : $user->id) === $driverId ? 'selected' : '' ?>><?= e($driverName) ?></option>
                        <?php endforeach; ?>
                        <option value="external" <?= $v('driver_user_id') === 'external' ? 'selected' : '' ?>>Externer Fahrer …</option>
                    </select><?= $err('driverUserId') ?>
                </label>
                <div class="grid2" data-external-fields>
                    <label class="<?= $cls('driverName') ?>">Name *
                        <input type="text" name="driver_name" value="<?= e($v('driver_name')) ?>" maxlength="150" autocomplete="name"><?= $err('driverName') ?>
                    </label>
                    <label class="field">Telefon
                        <input type="tel" name="driver_phone" value="<?= e($v('driver_phone')) ?>" maxlength="50" autocomplete="tel">
                    </label>
                </div>
            <?php else: ?>
                <p class="hint">Fahrer: <?= e($driverLabel) ?></p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Fahrzeug</h2>
            <label class="<?= $cls('vehicleId') ?>">Fahrzeug *
                <select name="vehicle_id" data-vehicle-select>
                    <option value="">– bitte wählen –</option>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <option value="<?= e($vehicle->id) ?>" data-odometer="<?= $vehicle->logbookOdometerCheck ? '1' : '0' ?>" <?= $v('vehicle_id') === $vehicle->id ? 'selected' : '' ?>>
                            <?= e($vehicle->name . ($vehicle->licensePlate !== null ? ' (' . $vehicle->licensePlate . ')' : '') . ($vehicle->isPrivate ? ' · privat' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="other" <?= $v('vehicle_id') === 'other' ? 'selected' : '' ?>>Sonstiges Fahrzeug …</option>
                </select><?= $err('vehicleId') ?>
            </label>
            <label class="<?= $cls('otherVehicleLabel') ?>" data-other-vehicle>Welches Fahrzeug? *
                <input type="text" name="other_vehicle_label" value="<?= e($v('other_vehicle_label')) ?>" maxlength="150" placeholder="z. B. Privat-PKW, M-XY 123"><?= $err('otherVehicleLabel') ?>
            </label>
            <?php if ($trailers !== []): ?>
                <label class="<?= $cls('trailerId') ?>">Anhänger
                    <select name="trailer_id">
                        <option value="">ohne Anhänger</option>
                        <?php foreach ($trailers as $trailer): ?>
                            <option value="<?= e($trailer->id) ?>" <?= $v('trailer_id') === $trailer->id ? 'selected' : '' ?>><?= e($trailer->name . ($trailer->licensePlate !== null ? ' (' . $trailer->licensePlate . ')' : '')) ?></option>
                        <?php endforeach; ?>
                    </select><?= $err('trailerId') ?>
                </label>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Art der Fahrt</h2>
            <div class="seg" role="radiogroup">
                <?php foreach ($categories as $option): ?>
                    <label><input type="radio" name="category" value="<?= e($option->value) ?>" <?= $category === $option->value ? 'checked' : '' ?> data-category><span><?= e($option->label()) ?></span></label>
                <?php endforeach; ?>
            </div>
            <?= $err('category') ?>

            <div data-show-for="event">
                <label class="<?= $cls('rentalId') ?>">Veranstaltung *
                    <select name="rental_id" data-rental-select>
                        <option value="">– bitte wählen –</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?= e($event['id']) ?>" data-tour="<?= $event['isTour'] ? '1' : '0' ?>" <?= $v('rental_id') === $event['id'] ? 'selected' : '' ?>><?= e($event['label']) ?></option>
                        <?php endforeach; ?>
                    </select><?= $err('rentalId') ?>
                </label>
                <p class="hint">Angeboten werden Veranstaltungen ±3 Tage um das Datum der Fahrt.</p>
                <label class="<?= $cls('tourStopId') ?>" data-tour-stop <?= $isTour ? '' : 'hidden' ?>>Tourstop
                    <select name="tour_stop_id" data-tour-stop-select>
                        <option value="">ganze Tour</option>
                        <?php foreach ($rentalLocations['stops'] ?? [] as $stop): ?>
                            <option value="<?= e($stop['id']) ?>" <?= $v('tour_stop_id') === $stop['id'] ? 'selected' : '' ?>><?= e($stop['label']) ?></option>
                        <?php endforeach; ?>
                    </select><?= $err('tourStopId') ?>
                </label>
                <div class="row" data-templates <?= $rentalLocations === null ? 'hidden' : '' ?>>
                    <button type="button" class="btn" data-template="outbound">➜ Hinfahrt</button>
                    <button type="button" class="btn" data-template="return">⟲ Rückfahrt</button>
                </div>
            </div>

            <label class="<?= $cls('purpose') ?>"><span data-purpose-label><?= $category === LogbookCategory::Business->value ? 'Zweck der Fahrt *' : 'Zweck / Notiz' ?></span>
                <input type="text" name="purpose" value="<?= e($v('purpose')) ?>" maxlength="500" placeholder="z. B. Material abholen"><?= $err('purpose') ?>
            </label>
        </section>

        <section class="card">
            <h2>Strecke</h2>
            <p class="hint" data-show-for="private unclear">Bei Privatfahrten sind Start und Ziel freiwillig.</p>
            <label class="<?= $cls('from') ?>">Start
                <select name="from" data-location="from"><?= $locationOptions($v('from')) ?></select><?= $err('from') ?>
            </label>
            <div data-custom="from">
                <label class="field">Adresse oder Ort
                    <input type="text" name="from_text" value="<?= e($v('from_text')) ?>" maxlength="255" placeholder="Straße Nr., PLZ Ort">
                </label>
                <label class="toggle"><input type="checkbox" name="save_from_address" value="1" <?= $on('save_from_address') ? 'checked' : '' ?>><span class="sw"></span>Als gespeicherte Adresse ablegen</label>
            </div>
            <div class="swap"><button type="button" data-swap title="Start und Ziel tauschen" aria-label="Start und Ziel tauschen">⇅</button></div>
            <label class="<?= $cls('to') ?>">Ziel
                <select name="to" data-location="to"><?= $locationOptions($v('to')) ?></select><?= $err('to') ?>
            </label>
            <div data-custom="to">
                <label class="field">Adresse oder Ort
                    <input type="text" name="to_text" value="<?= e($v('to_text')) ?>" maxlength="255" placeholder="Straße Nr., PLZ Ort">
                </label>
                <label class="toggle"><input type="checkbox" name="save_to_address" value="1" <?= $on('save_to_address') ? 'checked' : '' ?>><span class="sw"></span>Als gespeicherte Adresse ablegen</label>
            </div>
            <label class="toggle"><input type="checkbox" name="round_trip" value="1" <?= $on('round_trip') ? 'checked' : '' ?> data-round-trip><span class="sw"></span>Hin- und Rückfahrt (Start → Ziel → Start)</label>
        </section>

        <section class="card">
            <h2>Kilometer</h2>
            <div class="grid2">
                <label class="<?= $cls('odometerStart') ?>"><span data-odometer-label>Km-Stand Abfahrt</span>
                    <input type="text" name="odometer_start" value="<?= e($v('odometer_start')) ?>" inputmode="numeric" autocomplete="off" data-odometer="start"><?= $err('odometerStart') ?>
                </label>
                <label class="<?= $cls('odometerEnd') ?>"><span data-odometer-label>Km-Stand Ankunft</span>
                    <input type="text" name="odometer_end" value="<?= e($v('odometer_end')) ?>" inputmode="numeric" autocomplete="off" data-odometer="end"><?= $err('odometerEnd') ?>
                </label>
            </div>
            <div class="inline" data-distance-row>
                <label class="<?= $cls('distanceKm') ?>"><span data-distance-label><?= $on('round_trip') ? 'Kilometer (einfache Strecke)' : 'Gefahrene Kilometer' ?></span>
                    <input type="text" name="distance_km" value="<?= e($v('distance_km')) ?>" inputmode="numeric" autocomplete="off" data-distance><?= $err('distanceKm') ?>
                </label>
                <button type="button" class="btn" data-suggest>Vorschlagen</button>
            </div>
            <p class="hint" data-suggest-hint></p>
            <div class="result" data-total hidden></div>
            <?php if ($manager): ?>
                <label class="toggle"><input type="checkbox" name="not_billed" value="1" <?= $on('not_billed') ? 'checked' : '' ?>><span class="sw"></span>Nicht abrechnen (z. B. Mitfahrt oder Umweg)</label>
            <?php endif; ?>
        </section>

        <div class="bar">
            <div class="wrap"><button type="submit" class="btn primary big">Fahrt speichern</button></div>
        </div>
    </form>

    <?php if ($entry !== null): ?>
        <section class="card">
            <h2>Fahrt löschen</h2>
            <form method="post" action="/fahrtenbuch/<?= e($entry->id) ?>/loeschen" data-delete-form>
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                <button type="submit" class="btn danger big">Fahrt löschen</button>
            </form>
        </section>
        <?php if ($history !== []): ?>
            <section class="card">
                <h2>Verlauf</h2>
                <ul class="history">
                    <?php foreach (array_reverse($history) as $change): ?>
                        <li>
                            <?= e(ucfirst($change->actionLabel())) ?> von <?= e($change->actorName ?? 'Unbekannt') ?>
                            <small><?= e($change->createdAt->format('d.m.Y H:i')) ?><?= $change->action === 'updated' && $change->changes !== [] ? ' · ' . e(implode(', ', array_map([\App\Infrastructure\Http\LogbookLabels::class, 'field'], array_keys($change->changes)))) : '' ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php if ($entry !== null): ?>
<div class="modal" id="delete-confirm" role="dialog" aria-modal="true" aria-labelledby="delete-confirm-what" hidden>
    <div class="modal-box">
        <p class="q">Fahrt löschen?</p>
        <p class="what" id="delete-confirm-what"><?= e($entry->tripDate->format('d.m.Y')) ?> · <?= e((string) $entry->distanceKm) ?> km</p>
        <p class="sub">Die Fahrt verschwindet aus dem Fahrtenbuch; Fahrtenbuch-Verwalter können sie wiederherstellen.</p>
        <div class="modal-actions">
            <button type="button" class="btn" data-modal-no>Abbrechen</button>
            <button type="button" class="btn primary" data-modal-yes>Löschen</button>
        </div>
    </div>
</div>
<script>
(function () {
    var form = document.querySelector('[data-delete-form]');
    var modal = document.getElementById('delete-confirm');
    var confirmed = false;
    function close() { modal.classList.remove('show'); setTimeout(function () { modal.hidden = true; }, 150); }
    form.addEventListener('submit', function (event) {
        if (confirmed) { return; }
        event.preventDefault();
        modal.hidden = false;
        requestAnimationFrame(function () { modal.classList.add('show'); });
        modal.querySelector('[data-modal-yes]').focus();
    });
    modal.querySelector('[data-modal-no]').addEventListener('click', close);
    modal.addEventListener('click', function (event) { if (event.target === modal) { close(); } });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !modal.hidden) { close(); } });
    modal.querySelector('[data-modal-yes]').addEventListener('click', function () { confirmed = true; form.submit(); });
})();
</script>
<?php endif; ?>

<script>
(function () {
    var form = document.getElementById('trip-form');
    var $ = function (sel) { return form.querySelector(sel); };
    var $$ = function (sel) { return Array.prototype.slice.call(form.querySelectorAll(sel)); };
    var templates = <?= json_encode($rentalLocations !== null ? ['outbound' => $rentalLocations['outbound'], 'return' => $rentalLocations['return']] : null, JSON_THROW_ON_ERROR | JSON_HEX_TAG) ?>;
    var distanceTouched = <?= $v('distance_km') !== '' ? 'true' : 'false' ?>;

    function category() {
        var checked = $('[data-category]:checked');
        return checked ? checked.value : 'event';
    }

    function update() {
        var cat = category();
        $$('[data-show-for]').forEach(function (el) {
            el.hidden = el.getAttribute('data-show-for').split(' ').indexOf(cat) === -1;
        });
        document.querySelector('[data-purpose-label]').textContent = cat === 'business' ? 'Zweck der Fahrt *' : 'Zweck / Notiz';
        ['from', 'to'].forEach(function (which) {
            var select = $('[data-location="' + which + '"]');
            $('[data-custom="' + which + '"]').hidden = select.value !== 'custom:';
        });
        var vehicle = $('[data-vehicle-select]');
        $('[data-other-vehicle]').hidden = vehicle.value !== 'other';
        var option = vehicle.options[vehicle.selectedIndex];
        var odometerRequired = option && option.getAttribute('data-odometer') === '1';
        $$('[data-odometer-label]').forEach(function (label, i) {
            label.textContent = (i === 0 ? 'Km-Stand Abfahrt' : 'Km-Stand Ankunft') + (odometerRequired ? ' *' : '');
        });
        var driver = $('[data-driver-select]');
        var external = $('[data-external-fields]');
        if (driver && external) {
            external.hidden = driver.value !== 'external';
        }
        var round = $('[data-round-trip]').checked;
        document.querySelector('[data-distance-label]').textContent = round ? 'Kilometer (einfache Strecke)' : 'Gefahrene Kilometer';
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
        fetch('/fahrtenbuch/veranstaltungen/' + encodeURIComponent(id) + '/orte', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
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
        fetch('/fahrtenbuch/veranstaltungen?datum=' + encodeURIComponent(date) + '&veranstaltung=' + encodeURIComponent(current), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
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
    $('[data-suggest]').addEventListener('click', function () { suggest(false); });
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
</script>
</body>
</html>
