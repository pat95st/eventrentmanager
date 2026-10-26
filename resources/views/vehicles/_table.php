<?php
/** @var \App\Domain\Vehicle\Vehicle[] $vehicles */
/** @var string $search */

use App\Domain\Vehicle\VehicleCategory;
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use function App\Infrastructure\Http\e;

/**
 * Entfernt eine überflüssige ".0"-Nachkommastelle aus einem DECIMAL(6,1)-
 * Wert wie "300.0" (=> "300"), lässt eine echte Nachkommastelle wie "180.5"
 * unverändert. Bewusst kein pauschales rtrim($v, '0'), das bei einem Wert
 * wie "100" auch die signifikanten Nullen abschneiden würde.
 */
if (!function_exists('formatVehicleDimension')) {
    function formatVehicleDimension(string $value): string
    {
        if (!str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }
}
?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($vehicles === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">Keine Fahrzeuge gefunden.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 22%">
                <col style="width: 12%">
                <col style="width: 14%">
                <col style="width: 14%">
                <col style="width: 20%">
                <col style="width: 18%">
                <col style="width: 10%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5">Bezeichnung</th>
                    <th class="break-words px-4 py-2.5">Kategorie</th>
                    <th class="break-words px-4 py-2.5">Kennzeichen</th>
                    <th class="break-words px-4 py-2.5">Zuladung</th>
                    <th class="break-words px-4 py-2.5">Ladefläche</th>
                    <th class="break-words px-4 py-2.5">Anhängekupplung / Leergewicht</th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($vehicles as $vehicle): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                            <?= e($vehicle->name) ?>
                        </td>
                        <td class="break-words px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $vehicle->category === VehicleCategory::Auto ? 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800' : 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600' ?>">
                                <?= e($vehicle->category->label()) ?>
                            </span>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?= $vehicle->licensePlate !== null ? e($vehicle->licensePlate) : '–' ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?= $vehicle->payloadKg !== null ? e($vehicle->payloadKg) . ' kg' : '–' ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?php
                            // L x B x H, wobei H bei offener Ladefläche (kein
                            // Aufbau, keine Höhenbegrenzung) durch den
                            // Hinweistext statt einer Zahl ersetzt wird.
                            $dimensionParts = [];
                            if ($vehicle->loadAreaLengthCm !== null) {
                                $dimensionParts[] = formatVehicleDimension($vehicle->loadAreaLengthCm);
                            }
                            if ($vehicle->loadAreaWidthCm !== null) {
                                $dimensionParts[] = formatVehicleDimension($vehicle->loadAreaWidthCm);
                            }
                            if (!$vehicle->loadAreaOpenTop && $vehicle->loadAreaHeightCm !== null) {
                                $dimensionParts[] = formatVehicleDimension($vehicle->loadAreaHeightCm);
                            }

                            $label = $dimensionParts !== [] ? implode(' x ', $dimensionParts) . ' cm' : '';
                            if ($vehicle->loadAreaOpenTop) {
                                $label = $label !== '' ? $label . ' (offene Ladefläche)' : 'offene Ladefläche';
                            }

                            echo $label !== '' ? e($label) : '–';
                            ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?php if ($vehicle->category === VehicleCategory::Auto): ?>
                                <?php if ($vehicle->towHitchAvailable): ?>
                                    Ja<?= $vehicle->towCapacityKg !== null ? ' (' . e($vehicle->towCapacityKg) . ' kg)' : '' ?>
                                <?php else: ?>
                                    Nein
                                <?php endif; ?>
                            <?php else: ?>
                                <?= $vehicle->emptyWeightKg !== null ? e($vehicle->emptyWeightKg) . ' kg' : '–' ?>
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-right">
                            <a href="/fahrzeuge/<?= e($vehicle->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                            <form method="post" action="/fahrzeuge/<?= e($vehicle->id) ?>/loeschen" data-confirm="Dieses Fahrzeug wirklich löschen?" data-confirm-style="danger" class="mt-1">
                              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
