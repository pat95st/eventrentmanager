<?php
/** @var \App\Application\Vehicle\Query\RentalLoadingList $loadingList */

use App\Domain\Vehicle\VehicleCategory;
use function App\Infrastructure\Http\e;

$rental = $loadingList->rental;

/** Kurzform eines kg-Werts (oder "unbekannt", wenn null). */
$fmtKg = static function (?float $value): string {
    return $value === null ? 'unbekannt' : number_format($value, 1, ',', '.') . ' kg';
};

$autoVehicles = array_values(array_filter($loadingList->allVehicles, static fn ($v) => $v->category === VehicleCategory::Auto));
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück zur Veranstaltung</a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Beladungsliste: <?= e($rental->eventName) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Ordnet die bereits ausgegebenen Posten den Fahrzeugen zu und prüft die Zuladung/Anhängelast.</p>
    </div>
</div>

<?php if ($loadingList->allVehicles === []): ?>
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
        Es sind noch keine Fahrzeuge angelegt. <a href="/fahrzeuge/neu" class="underline">Jetzt ein Fahrzeug anlegen</a>.
    </div>
<?php endif; ?>

<div class="space-y-4">
    <?php foreach ($loadingList->vehicleSummaries as $summary): ?>
        <?php $vehicle = $summary->vehicle; ?>
        <div class="rounded-lg border <?= $summary->overloaded === true ? 'border-red-300 dark:border-red-700' : 'border-slate-200 dark:border-slate-800' ?> bg-white p-4 dark:bg-slate-900">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                        <?= e($vehicle->name) ?>
                        <span class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium <?= $vehicle->category === VehicleCategory::Auto ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' ?>"><?= e($vehicle->category->label()) ?></span>
                    </h2>
                    <p class="text-xs text-slate-400 dark:text-slate-500">
                        <?= $vehicle->licensePlate !== null ? e($vehicle->licensePlate) . ' · ' : '' ?>
                        Zuladung: <?= $summary->payloadKg !== null ? $fmtKg($summary->payloadKg) : 'nicht hinterlegt' ?>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Beladen: <?= $fmtKg($summary->totalWeightKg) ?></p>
                    <?php if ($summary->overloaded === true): ?>
                        <p class="text-xs font-semibold text-red-600 dark:text-red-400">⚠ Überladen um <?= $fmtKg(abs($summary->remainingPayloadKg ?? 0)) ?></p>
                    <?php elseif ($summary->overloaded === false): ?>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400">Noch <?= $fmtKg($summary->remainingPayloadKg) ?> frei</p>
                    <?php else: ?>
                        <p class="text-xs text-slate-400 dark:text-slate-500">Überladung nicht prüfbar (Zuladung oder Gewicht unbekannt)</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($vehicle->category === VehicleCategory::Anhaenger): ?>
                <div class="mb-3 rounded-md border border-slate-100 bg-slate-50 p-3 text-xs dark:border-slate-800 dark:bg-slate-800/50">
                    <?php if ($summary->towingVehicle !== null): ?>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-slate-600 dark:text-slate-400">
                                Zugfahrzeug: <strong><?= e($summary->towingVehicle->name) ?></strong>
                                · Anhänger inkl. Beladung: <?= $fmtKg($summary->trailerTotalWeightKg) ?>
                                <?php if ($summary->towOverloaded === true): ?>
                                    <span class="font-semibold text-red-600 dark:text-red-400">⚠ Anhängelast überschritten!</span>
                                <?php elseif ($summary->towOverloaded === false): ?>
                                    <span class="text-emerald-600 dark:text-emerald-400">Anhängelast eingehalten</span>
                                <?php else: ?>
                                    <span class="text-slate-400 dark:text-slate-500">Anhängelast nicht prüfbar (Leergewicht, Beladung oder Anhängelast unbekannt)</span>
                                <?php endif; ?>
                            </span>
                            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/beladung/zugfahrzeug/entfernen">
                                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <input type="hidden" name="towed_vehicle_id" value="<?= e($vehicle->id) ?>">
                                <button type="submit" class="font-medium text-red-600 hover:underline dark:text-red-400">Zugfahrzeug entfernen</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/beladung/zugfahrzeug" class="flex flex-wrap items-end gap-2">
                            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <input type="hidden" name="towed_vehicle_id" value="<?= e($vehicle->id) ?>">
                            <div>
                                <label class="block text-slate-500 dark:text-slate-400">Zugfahrzeug (Auto)</label>
                                <select name="towing_vehicle_id" required class="mt-1 rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700">
                                    <option value="">– wählen –</option>
                                    <?php foreach ($autoVehicles as $auto): ?>
                                        <?php if ($auto->towHitchAvailable === true): ?>
                                            <option value="<?= e($auto->id) ?>"><?= e($auto->name) ?> (<?= $fmtKg($auto->towCapacityKg !== null ? (float) $auto->towCapacityKg : null) ?> Anhängelast)</option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Zuordnen</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($summary->entries === []): ?>
                <p class="text-xs text-slate-400 dark:text-slate-500">Noch keine Posten in dieses Fahrzeug verladen.</p>
            <?php else: ?>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($summary->entries as $entry): ?>
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <div>
                                <p class="text-sm text-slate-700 dark:text-slate-300">
                                    <?= e($entry->issuedItem->displayName()) ?>
                                    <span class="text-xs text-slate-400 dark:text-slate-500">(<?= e($entry->issuedItem->inventoryNumber->number) ?>)</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-slate-500 dark:text-slate-400"><?= $fmtKg($entry->weightKg) ?></span>
                                <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/beladung/entfernen">
                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <input type="hidden" name="rental_issued_item_id" value="<?= e($entry->issuedItem->issuedItem->id) ?>">
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="mt-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Noch nicht verladene Posten</h2>

    <?php if ($loadingList->unassignedItems === []): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400">Alle ausgegebenen Posten sind einem Fahrzeug zugeordnet.</p>
    <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($loadingList->unassignedItems as $entry): ?>
                <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <div>
                        <p class="text-sm text-slate-700 dark:text-slate-300">
                            <?= e($entry->issuedItem->displayName()) ?>
                            <span class="text-xs text-slate-400 dark:text-slate-500">(<?= e($entry->issuedItem->inventoryNumber->number) ?>)</span>
                        </p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">Gewicht: <?= $fmtKg($entry->weightKg) ?></p>
                    </div>
                    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/beladung/zuordnen" class="flex items-end gap-2">
                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <input type="hidden" name="rental_issued_item_id" value="<?= e($entry->issuedItem->issuedItem->id) ?>">
                        <select name="vehicle_id" required class="rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700">
                            <option value="">Fahrzeug wählen</option>
                            <?php foreach ($loadingList->allVehicles as $vehicle): ?>
                                <option value="<?= e($vehicle->id) ?>"><?= e($vehicle->name) ?> (<?= e($vehicle->category->label()) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Verladen</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
