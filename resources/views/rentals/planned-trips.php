<?php
/**
 * Planned trips and travel costs of an event that is not a tour (migration 186).
 *
 * @var \App\Domain\Rental\Rental $rental
 * @var \App\Domain\PlannedTrip\PlannedTrip[] $trips
 * @var \App\Application\TravelCost\TourTravelCostProposal $proposal
 * @var list<array{id: string, vehicle: \App\Domain\Vehicle\Vehicle, towing: ?\App\Domain\Vehicle\Vehicle}> $planned
 * @var \App\Domain\Vehicle\Vehicle[] $vehicles
 * @var array<string, string> $vehicleNames
 * @var list<array{group: string, options: list<array{value: string, label: string}>}> $generalLocations
 * @var array{options: list<array{value: string, label: string}>} $rentalLocations
 * @var \App\Domain\Logbook\LogbookEntry[] $actual
 * @var int $actualBillableKm
 */

use App\Domain\Logbook\LogbookLocationType;
use App\Domain\TravelCost\TravelCostMode;

use function App\Infrastructure\Http\e;

require __DIR__ . '/series/_styles.php';
$km = static fn (int $v): string => number_format($v, 0, ',', '.') . ' km';
$money = static fn (float $v): string => number_format($v, 2, ',', '.') . ' €';
$plannedKm = $proposal->totalKilometers();
$choice = static fn (?\App\Domain\Logbook\LogbookLocation $l): string => $l === null ? '' : $l->type->value . ':' . ($l->type === LogbookLocationType::Custom ? '' : (string) $l->refId);
$locationSelect = static function (string $name, string $selected) use ($generalLocations, $rentalLocations, $input): string {
    $option = static fn (string $value, string $label): string => '<option value="' . e($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    $html = '<select name="' . e($name) . '" required class="' . $input . '" data-location-select><option value="">– bitte wählen –</option>';
    if ($rentalLocations['options'] !== []) {
        $html .= '<optgroup label="Veranstaltung">' . implode('', array_map(static fn ($o) => $option($o['value'], $o['label']), $rentalLocations['options'])) . '</optgroup>';
    }
    foreach ($generalLocations as $group) {
        $html .= '<optgroup label="' . e($group['group']) . '">' . implode('', array_map(static fn ($o) => $option($o['value'], $o['label']), $group['options'])) . '</optgroup>';
    }

    return $html . '<optgroup label="Sonstiges">' . $option('custom:', 'Freie Adresse …') . '</optgroup></select>';
};
/** Form fields of a trip (add and edit). */
$tripFields = static function (?\App\Domain\PlannedTrip\PlannedTrip $trip) use ($locationSelect, $choice, $input, $label, $rental): string {
    $customFrom = $trip?->from?->type === LogbookLocationType::Custom ? $trip->from->label : '';
    $customTo = $trip?->to?->type === LogbookLocationType::Custom ? $trip->to->label : '';
    ob_start(); ?>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="<?= $label ?>">Datum
            <input type="date" name="trip_date" required value="<?= e(($trip?->tripDate ?? $rental->startDate)->format('Y-m-d')) ?>" class="<?= $input ?>">
        </label>
        <label class="<?= $label ?>">Start
            <?= $locationSelect('from', $choice($trip?->from)) ?>
            <input type="text" name="from_text" value="<?= e($customFrom) ?>" placeholder="Straße Nr., PLZ Ort" class="<?= $input ?>" data-custom-text <?= $customFrom === '' ? 'hidden' : '' ?>>
        </label>
        <label class="<?= $label ?>">Ziel
            <?= $locationSelect('to', $choice($trip?->to)) ?>
            <input type="text" name="to_text" value="<?= e($customTo) ?>" placeholder="Straße Nr., PLZ Ort" class="<?= $input ?>" data-custom-text <?= $customTo === '' ? 'hidden' : '' ?>>
        </label>
        <label class="<?= $label ?>">Kilometer von Hand
            <input type="text" name="km_override" inputmode="numeric" value="<?= e($trip?->kmOverride !== null ? (string) $trip->kmOverride : '') ?>" placeholder="<?= e($trip?->distanceKm !== null ? 'berechnet: ' . $trip->distanceKm : 'leer = berechnen') ?>" class="<?= $input ?>">
        </label>
    </div>
    <?php
    return (string) ob_get_clean();
};
?>
<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Zurück zur Veranstaltung</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Fahrten &amp; Fahrtkosten: <?= e($rental->eventName) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($rental->startDate->format('d.m.Y')) ?><?= $rental->endDate != $rental->startDate ? ' – ' . e($rental->endDate->format('d.m.Y')) : '' ?><?= $rental->eventLocation ? ' · ' . e($rental->eventLocation) : '' ?></p>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="<?= $card ?>">
        <p class="text-xs text-slate-500 dark:text-slate-400">Geplant</p>
        <p class="text-2xl font-semibold text-slate-900 dark:text-slate-100"><?= e($km($plannedKm)) ?></p>
        <p class="text-xs text-slate-500 dark:text-slate-400"><?= count($trips) ?> <?= count($trips) === 1 ? 'Fahrt' : 'Fahrten' ?></p>
    </div>
    <div class="<?= $card ?>">
        <p class="text-xs text-slate-500 dark:text-slate-400">Tatsächlich gefahren (abrechenbar)</p>
        <p class="text-2xl font-semibold text-slate-900 dark:text-slate-100"><?= e($km($actualBillableKm)) ?></p>
        <p class="text-xs text-slate-500 dark:text-slate-400"><?= count($actual) ?> <?= count($actual) === 1 ? 'Fahrt' : 'Fahrten' ?> im Fahrtenbuch · <a href="/fahrtenbuch?veranstaltung=<?= e($rental->id) ?>" class="underline">anzeigen</a></p>
    </div>
    <div class="<?= $card ?>">
        <p class="text-xs text-slate-500 dark:text-slate-400">Fahrtkosten laut Planung</p>
        <p class="text-2xl font-semibold text-slate-900 dark:text-slate-100"><?= $proposal->vehicles !== [] ? e($money($proposal->total())) : '–' ?></p>
        <p class="text-xs text-slate-500 dark:text-slate-400">
            <?= $proposal->vehicles !== [] ? e(number_format((float) $proposal->ratePerKm, 2, ',', '.')) . ' € / km' : 'keine Fahrzeuge geplant' ?>
            · <?= $rental->travelCostsEnabled ? 'wird abgerechnet' : '<strong>wird nicht abgerechnet</strong>' ?>
        </p>
    </div>
</div>

<?php if ($proposal->hints !== []): ?>
    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
        <?= implode('<br>', array_map(static fn (string $h) => e($h), $proposal->hints)) ?>
    </div>
<?php endif; ?>

<div class="<?= $card ?> mb-6">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Geplante Fahrten</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Hin- und Rückfahrt werden automatisch angelegt und folgen Änderungen an Lager, Ort und Datum der Veranstaltung, solange sie nicht bearbeitet wurden. Die Kilometer werden über die Routenplanung berechnet; von Hand eingetragene Kilometer haben Vorrang.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/fahrten/neu-berechnen"><?= $csrf ?><button type="submit" class="<?= $secondaryButton ?>">Strecken neu berechnen</button></form>
            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/fahrten/standard" data-confirm-key="rentals.planned_trips.restore" data-confirm="Hin- und Rückfahrt neu anlegen? Bearbeitete Hin- und Rückfahrten werden dabei ersetzt, weitere Fahrten bleiben erhalten."><?= $csrf ?><button type="submit" class="<?= $secondaryButton ?>">Standardfahrten wiederherstellen</button></form>
        </div>
    </div>

    <?php if ($trips === []): ?>
        <p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">Keine geplanten Fahrten.<?= $rental->venueId === null && trim((string) $rental->eventLocation) === '' ? ' Hin- und Rückfahrt entstehen, sobald die Veranstaltung einen Ort hat.' : '' ?></p>
    <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($trips as $trip): ?>
                <details class="group py-3">
                    <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                        <span class="w-24 font-medium text-slate-900 dark:text-slate-100"><?= e($trip->tripDate?->format('d.m.Y') ?? '–') ?></span>
                        <span class="min-w-0 flex-1 text-slate-700 dark:text-slate-300">
                            <?= e($trip->from?->label ?? '?') ?> → <?= e($trip->to?->label ?? '?') ?>
                            <?php if ($trip->isDefault()): ?>
                                <span class="ml-1 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800"><?= e((string) $trip->defaultLabel()) ?> · automatisch</span>
                            <?php endif; ?>
                        </span>
                        <span class="w-28 text-right font-medium text-slate-900 dark:text-slate-100">
                            <?php if ($trip->kilometers() !== null): ?><?= e($km((int) $trip->kilometers())) ?><?php else: ?><span class="text-amber-700 dark:text-amber-400">km fehlen</span><?php endif; ?>
                            <?php if ($trip->kmOverride !== null): ?><span class="block text-[11px] font-normal text-slate-500">von Hand</span><?php endif; ?>
                            <?php if ($trip->wasDefault()): ?><span class="block text-[11px] font-normal text-slate-500">angepasst</span><?php endif; ?>
                        </span>
                        <span class="text-xs text-brand-700 group-open:hidden dark:text-brand-400">bearbeiten</span>
                    </summary>
                    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/fahrten/<?= e($trip->id) ?>" class="mt-3 space-y-3 rounded-md bg-slate-50 p-3 dark:bg-slate-800/40">
                        <?= $csrf ?>
                        <?= $tripFields($trip) ?>
                        <?php if ($trip->isDefault()): ?><p class="text-xs text-slate-500">Nach dem Speichern folgt die Fahrt nicht mehr automatisch der Veranstaltung.</p><?php endif; ?>
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="<?= $primaryButton ?>">Speichern</button>
                            <button type="submit" formaction="/veranstaltungen/<?= e($rental->id) ?>/fahrten/<?= e($trip->id) ?>/loeschen" formnovalidate class="rounded-md px-3 py-1.5 text-sm font-medium text-red-600 hover:underline dark:text-red-400">Fahrt löschen</button>
                        </div>
                    </form>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <details class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
        <summary class="cursor-pointer text-sm font-medium text-brand-700 dark:text-brand-400">+ Weitere Fahrt planen</summary>
        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/fahrten" class="mt-3 space-y-3">
            <?= $csrf ?>
            <?= $tripFields(null) ?>
            <button type="submit" class="<?= $primaryButton ?>">Fahrt hinzufügen</button>
        </form>
    </details>
</div>

<div class="mb-6 grid gap-4 lg:grid-cols-2">
    <div id="fahrtkosten" class="<?= $card ?> space-y-3">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Fahrtkosten</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">Kilometer × Kilometerkosten der geplanten Fahrzeuge (Aufschlag unter <a href="/einstellungen/fahrzeuge" class="underline">Einstellungen › Fahrzeuge</a>). Bei der Rechnung wählst du, ob die geplanten oder die tatsächlich gefahrenen Kilometer abgerechnet werden.</p>
        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/fahrten/fahrtkosten" class="flex flex-wrap items-center gap-3 text-sm">
            <?= $csrf ?>
            <label class="flex items-center gap-2"><input type="checkbox" name="enabled" value="1"<?= $rental->travelCostsEnabled ? ' checked' : '' ?>> Fahrtkosten abrechnen</label>
            <?php foreach (TravelCostMode::cases() as $mode): ?>
                <label class="flex items-center gap-1"><input type="radio" name="mode" value="<?= e($mode->value) ?>"<?= $rental->travelCostsMode === $mode->value ? ' checked' : '' ?>> <?= e($mode->label()) ?></label>
            <?php endforeach; ?>
            <button type="submit" class="<?= $secondaryButton ?>">Speichern</button>
        </form>
    </div>

    <div class="<?= $card ?> space-y-2 text-sm">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Geplante Fahrzeuge</h2>
        <?php if ($planned === []): ?><p class="text-xs text-slate-400">Noch keine Fahrzeuge geplant.</p><?php endif; ?>
        <?php foreach ($planned as $entry): ?>
            <div class="flex items-center justify-between gap-2">
                <span><?= e($entry['vehicle']->displayName()) ?><?= $entry['towing'] !== null ? ' <span class="text-xs text-slate-500">gezogen von ' . e($entry['towing']->name) . '</span>' : '' ?></span>
                <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/fahrten/fahrzeuge/<?= e($entry['id']) ?>/loeschen"><?= $csrf ?><button type="submit" class="text-xs text-red-600 hover:underline dark:text-red-400">entfernen</button></form>
            </div>
        <?php endforeach; ?>
        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/fahrten/fahrzeuge" class="grid gap-2 border-t border-slate-100 pt-2 sm:grid-cols-3 dark:border-slate-800">
            <?= $csrf ?>
            <select name="vehicle_id" required class="<?= $input ?>">
                <option value="">Fahrzeug wählen …</option>
                <?php foreach ($vehicles as $vehicle): ?><option value="<?= e($vehicle->id) ?>"><?= e($vehicle->name) ?><?= $vehicle->isAnhaenger() ? ' (Anhänger)' : '' ?></option><?php endforeach; ?>
            </select>
            <select name="towing_vehicle_id" class="<?= $input ?>">
                <option value="">kein Zugfahrzeug</option>
                <?php foreach ($vehicles as $vehicle): ?><?php if ($vehicle->isAuto()): ?><option value="<?= e($vehicle->id) ?>">gezogen von <?= e($vehicle->name) ?></option><?php endif; ?><?php endforeach; ?>
            </select>
            <button type="submit" class="<?= $secondaryButton ?> mt-1 justify-center">Fahrzeug planen</button>
        </form>
    </div>
</div>

<div class="<?= $card ?> mb-6">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Tatsächlich gefahren (Fahrtenbuch)</h2>
        <a href="/fahrtenbuch/neu?veranstaltung=<?= e($rental->id) ?>" class="<?= $secondaryButton ?>">+ Fahrt erfassen</a>
    </div>
    <?php if ($actual === []): ?>
        <p class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">Noch keine Fahrten im Fahrtenbuch.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
            <?php foreach ($actual as $entry): ?>
                <li class="flex flex-wrap items-center gap-x-4 gap-y-1 py-2 <?= $entry->isBillable() ? '' : 'text-slate-400 dark:text-slate-500' ?>">
                    <span class="w-24"><?= e($entry->tripDate->format('d.m.Y')) ?></span>
                    <span class="min-w-0 flex-1"><?= e($entry->from?->label ?? '?') ?> → <?= e($entry->to?->label ?? '?') ?><?= $entry->roundTrip ? ' → ' . e($entry->from?->label ?? '?') : '' ?> <span class="text-xs text-slate-500">· <?= e($entry->vehicleId !== null ? ($vehicleNames[$entry->vehicleId] ?? '') : (string) $entry->otherVehicleLabel) ?></span></span>
                    <span class="w-24 text-right font-medium"><?= e($km($entry->distanceKm)) ?></span>
                    <?php if (!$entry->isBillable()): ?><span class="text-xs"><?= e($entry->notBilled ? 'nicht abrechnen' : $entry->status->label()) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<script>
    // Free address field only for "Freie Adresse …".
    document.querySelectorAll('[data-location-select]').forEach(function (select) {
        var text = select.parentElement.querySelector('[data-custom-text]');
        var update = function () { if (text) { text.hidden = select.value !== 'custom:'; text.required = select.value === 'custom:'; } };
        select.addEventListener('change', update);
        update();
    });
</script>
