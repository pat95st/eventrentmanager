<?php
/** @var \App\Domain\Rental\Rental|null $rental */
/** @var \App\Domain\Customer\Customer[] $customers */
/** @var \App\Domain\Venue\Venue[] $venues */
/** @var array<string, bool> $needsDistributorFlagsByName */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */

use function App\Infrastructure\Http\e;
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
            <select id="customer_id" name="customer_id" required
                    class="mt-1 w-full rounded-md border <?= isset($errors['customerId']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <option value=""><?= e(t('rentals.form.customer_placeholder')) ?></option>
                <?php $selectedCustomer = $val('customer_id', $rental?->customerId); ?>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= e($customer->id) ?>" <?= $selectedCustomer === $customer->id ? 'selected' : '' ?>><?= e($customer->displayName()) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['customerId'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['customerId']) ?></p><?php endif; ?>
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
        <input type="text" id="event_location" name="event_location" list="venue-datalist"
               value="<?= e($initialEventLocation) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['eventLocation']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <datalist id="venue-datalist">
            <?php foreach ($venues as $venue): ?>
                <option value="<?= e($venue->name) ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <?php if (isset($errors['eventLocation'])): ?>
            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['eventLocation']) ?></p>
        <?php else: ?>
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
