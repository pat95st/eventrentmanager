<?php
/** @var \App\Domain\Rental\Rental|null $rental */
/** @var \App\Domain\Customer\Customer[] $customers */
/** @var \App\Domain\Venue\Venue[] $venues */
/** @var array<string, bool> $needsDistributorFlagsByName */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */
/** @var \App\Domain\CompanyHoliday\CompanyHoliday[] $companyHolidays */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\renderLiveSearchField;
use function App\Infrastructure\Http\t;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
?>

<div class="mb-6">
    <a href="<?= $rental !== null ? '/veranstaltungen/' . e($rental->id) : '/veranstaltungen' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('rentals.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $rental !== null ? e(t('rentals.edit')) : e(t('rentals.new')) ?></h1>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/veranstaltungen/' . e($editingId) : '/veranstaltungen' ?>"
    class="max-w-3xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="customer_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('rentals.form.customer')) ?></label>
            <div class="mt-1">
                <?php
                $selectedCustomer = $val('customer_id', $rental?->customerId);
                $selectedCustomerLabel = null;
                foreach ($customers as $customer) {
                    if ($customer->id === $selectedCustomer) {
                        $selectedCustomerLabel = $customer->displayName();
                        break;
                    }
                }
                renderLiveSearchField(
                    'customer_id',
                    '/kunden/suche',
                    $selectedCustomer !== '' ? $selectedCustomer : null,
                    $selectedCustomerLabel,
                    t('rentals.form.customer_placeholder'),
                    $errors['customerId'] ?? null,
                    'select',
                    true,
                );
                ?>
            </div>
            <?php if ($customers === []): ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= t('rentals.form.no_customers', ['link' => '<a href="/kunden/neu" class="underline">' . e(t('rentals.form.no_customers_link')) . '</a>']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="event_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('rentals.form.event_name')) ?></label>
            <input type="text" id="event_name" name="event_name" required
                   value="<?= e($val('event_name', $rental?->eventName)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['eventName']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['eventName'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['eventName']) ?></p><?php endif; ?>
        </div>
    </div>

    <?php
        $initialEventLocation = $val('event_location', $rental?->eventLocation);
        $initialNeedsDistributor = $initialEventLocation !== ''
            && ($needsDistributorFlagsByName[mb_strtolower($initialEventLocation)] ?? false);
    ?>
    <div>
        <label for="event_location" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('rentals.form.event_location')) ?></label>
        <div class="mt-1">
            <?php
            renderLiveSearchField(
                'event_location',
                '/veranstaltungsorte/suche',
                null,
                $initialEventLocation,
                t('rentals.form.event_location'),
                $errors['eventLocation'] ?? null,
                'freetext',
            );
            ?>
        </div>
        <?php if (!isset($errors['eventLocation'])): ?>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                <?= t('rentals.form.event_location_hint', ['link' => '<a href="/veranstaltungsorte" class="underline" target="_blank">' . e(t('rentals.form.event_location_link')) . '</a>']) ?>
            </p>
        <?php endif; ?>
        <p id="venue-distributor-warning" class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-400" <?= $initialNeedsDistributor ? '' : 'hidden' ?>>
            <?= e(t('rentals.form.distributor_warning')) ?>
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="start_date" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('rentals.form.start_date')) ?></label>
            <input type="date" id="start_date" name="start_date" required
                   value="<?= e($val('start_date', $rental?->startDate->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['startDate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['startDate'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['startDate']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="end_date" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('rentals.form.end_date')) ?></label>
            <input type="date" id="end_date" name="end_date" required
                   value="<?= e($val('end_date', $rental?->endDate->format('Y-m-d'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['endDate']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['endDate'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['endDate']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="deployment_days" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('rentals.form.deployment_days')) ?></label>
        <input type="number" id="deployment_days" name="deployment_days" min="1" required
               value="<?= e($val('deployment_days', $rental !== null ? (string) $rental->deploymentDays : null)) ?>"
               class="mt-1 w-32 rounded-md border <?= isset($errors['deploymentDays']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['deploymentDays'])): ?>
            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['deploymentDays']) ?></p>
        <?php else: ?>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('rentals.form.deployment_days_hint')) ?></p>
        <?php endif; ?>
    </div>

    <?php
    // Exklusiv + Betriebsurlaub (Migration 130)
    $companyHolidays ??= [];
    $blocksChecked = array_key_exists('blocks_other_rentals', $old) ? (bool) $old['blocks_other_rentals'] : ($rental?->blocksOtherRentals ?? false);
    $pickupValue = $val('pickup_at', $rental?->pickupAt?->format('Y-m-d\TH:i'));
    $returnValue = $val('return_at', $rental?->returnAt?->format('Y-m-d\TH:i'));
    $holidaysJson = array_map(static fn ($h) => [
        'id' => $h->id, 'start' => $h->startDate->format('Y-m-d'), 'end' => $h->endDate->format('Y-m-d'), 'label' => $h->label(),
        'lastPickup' => $h->lastPickupDay()->format('Y-m-d'), 'firstReturn' => $h->firstReturnDay()->format('Y-m-d'),
        'lastPickupLabel' => $h->lastPickupDay()->format('d.m.Y'), 'firstReturnLabel' => $h->firstReturnDay()->format('d.m.Y'),
    ], $companyHolidays);
    $errClass = fn (string $key) => isset($errors[$key]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700';
    ?>
    <div>
        <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="blocks_other_rentals" value="1" class="mt-0.5" <?= $blocksChecked ? 'checked' : '' ?>>
            <span>
                Andere Vermietungen in diesem Zeitraum blockieren
                <span class="block text-xs text-slate-400 dark:text-slate-500">Exklusive Veranstaltung: Solange sie läuft (inkl. Abholung und Rückgabe), lassen sich keine weiteren Veranstaltungen anlegen.</span>
            </span>
        </label>
        <?php if (isset($errors['blocksOtherRentals'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['blocksOtherRentals']) ?></p><?php endif; ?>
    </div>

    <input type="hidden" name="holiday_confirmed" value="" data-holiday-confirmed>
    <div id="holiday-section" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
         <?= isset($errors['holiday']) || isset($errors['pickupAt']) || isset($errors['returnAt']) ? '' : 'hidden' ?>>
        <p class="font-medium">Betriebsurlaub</p>
        <p id="holiday-text" class="mt-1"><?= isset($errors['holiday']) ? e($errors['holiday']) : '' ?></p>
        <p class="mt-1 text-xs">Die Veranstaltung ist nur mit Abholung vor und Rückgabe nach dem Betriebsurlaub möglich.</p>
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="pickup_at" class="block text-xs font-medium">Abholung *</label>
                <input type="datetime-local" id="pickup_at" name="pickup_at" value="<?= e($pickupValue) ?>"
                       class="mt-1 w-full rounded-md border <?= $errClass('pickupAt') ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <p id="pickup-hint" class="mt-1 text-xs"></p>
                <?php if (isset($errors['pickupAt'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['pickupAt']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="return_at" class="block text-xs font-medium">Rückgabe *</label>
                <input type="datetime-local" id="return_at" name="return_at" value="<?= e($returnValue) ?>"
                       class="mt-1 w-full rounded-md border <?= $errClass('returnAt') ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <p id="return-hint" class="mt-1 text-xs"></p>
                <?php if (isset($errors['returnAt'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['returnAt']) ?></p><?php endif; ?>
            </div>
        </div>
        <?php if (isset($errors['holiday'])): ?>
            <label class="mt-3 flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="holiday_confirmed" value="1" data-holiday-confirm-box> Trotzdem anlegen
            </label>
        <?php endif; ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="<?= $rental !== null ? '/veranstaltungen/' . e($rental->id) : '/veranstaltungen' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>

<?php if ($rental !== null): ?>
    <p class="mt-4 max-w-2xl text-xs text-slate-400 dark:text-slate-500">
        <?= e(t('rentals.form.items_hint')) ?>
    </p>
<?php endif; ?>

<script>
    // Betriebsurlaub (Migration 130): Hinweis, Abhol-/Rückgabefelder und
    // Rückfrage per Bestätigungs-Modal vor dem Anlegen. Bestätigen muss man
    // nur Urlaube, in die die Veranstaltung neu fällt (beim Bearbeiten).
    (function () {
        var HOLIDAYS = <?= json_encode($holidaysJson, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
        var form = document.querySelector('form[action="<?= $editingId !== null ? '/veranstaltungen/' . e($editingId) : '/veranstaltungen' ?>"]');
        var start = document.getElementById('start_date');
        var end = document.getElementById('end_date');
        var section = document.getElementById('holiday-section');
        if (!form || !start || !end || !section) { return; }
        var text = document.getElementById('holiday-text');
        var pickup = document.getElementById('pickup_at');
        var ret = document.getElementById('return_at');
        var confirmed = form.querySelector('[data-holiday-confirmed]');
        var initialIds = overlapping(start.value, end.value).map(function (h) { return h.id; });
        var isNew = <?= $editingId === null ? 'true' : 'false' ?>;

        function overlapping(from, to) {
            if (!from || !to || to < from) { return []; }
            return HOLIDAYS.filter(function (h) { return from <= h.end && to >= h.start; });
        }

        function update() {
            var list = overlapping(start.value, end.value);
            var hasServerError = section.querySelector('.text-red-600') !== null;
            section.hidden = list.length === 0 && !hasServerError;
            pickup.required = list.length > 0;
            ret.required = list.length > 0;
            if (list.length === 0) { return; }
            var first = list[0], last = list[list.length - 1];
            text.textContent = 'Die Veranstaltung fällt in den Betriebsurlaub ' + list.map(function (h) { return h.label; }).join(', ') + '.';
            document.getElementById('pickup-hint').textContent = 'spätestens am ' + first.lastPickupLabel;
            document.getElementById('return-hint').textContent = 'frühestens am ' + last.firstReturnLabel;
            pickup.max = first.lastPickup + 'T23:59';
            ret.min = last.firstReturn + 'T00:00';
            if (!pickup.value) { pickup.value = first.lastPickup + 'T16:00'; }
            if (!ret.value) { ret.value = last.firstReturn + 'T10:00'; }
        }

        start.addEventListener('change', update);
        end.addEventListener('change', update);
        update();

        form.addEventListener('submit', function (event) {
            var list = overlapping(start.value, end.value);
            var box = form.querySelector('[data-holiday-confirm-box]');
            var needs = list.filter(function (h) { return isNew || initialIds.indexOf(h.id) === -1; });
            if (needs.length === 0 || confirmed.value === '1' || (box && box.checked) || typeof window.confirmModal !== 'function') { return; }
            event.preventDefault();
            var message = 'Achtung, Betriebsurlaub: ' + needs.map(function (h) { return h.label; }).join(', ')
                + '. In dieser Zeit gibt es keine Ausgabe und keine Rücknahme – die Veranstaltung ist nur mit Abholung bis '
                + needs[0].lastPickupLabel + ' und Rückgabe ab ' + needs[needs.length - 1].firstReturnLabel
                + ' möglich. Möchtest du die Veranstaltung trotzdem ' + (isNew ? 'anlegen' : 'speichern') + '?';
            window.confirmModal(message).then(function (ok) {
                if (ok) { confirmed.value = '1'; form.submit(); }
            });
        });
    })();

    // Veranstaltungsort => "Verteiler mit RCD nötig"-Flag (siehe
    // App\Domain\Venue\PowerConnection), Namen klein geschrieben für den
    // Abgleich mit dem frei eingegebenen Text im Feld unten. Wird serverseitig
    // beim ersten Rendern bereits berücksichtigt (siehe #venue-distributor-warning
    // oben); dieses Skript hält die Anzeige live, während der Text getippt
    // oder aus der Datalist ausgewählt wird — kein Server-Roundtrip nötig.
    var VENUE_DISTRIBUTOR_FLAGS = <?= json_encode($needsDistributorFlagsByName, JSON_THROW_ON_ERROR) ?>;

    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('event_location');
        var warning = document.getElementById('venue-distributor-warning');
        if (!input || !warning) {
            return;
        }

        function updateWarning() {
            var key = input.value.trim().toLowerCase();
            var needsDistributor = Object.prototype.hasOwnProperty.call(VENUE_DISTRIBUTOR_FLAGS, key)
                && VENUE_DISTRIBUTOR_FLAGS[key];
            warning.hidden = !needsDistributor;
        }

        input.addEventListener('input', updateWarning);
        input.addEventListener('change', updateWarning);
    });
</script>
