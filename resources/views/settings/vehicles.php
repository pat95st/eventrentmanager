<?php
/**
 * Fleet settings: default cost per km of cars and trailers (migration 181).
 *
 * @var \App\Domain\Vehicle\VehicleSettings $settings
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */

use function App\Infrastructure\Http\e;

$amount = static fn (string $v): string => number_format((float) $v, 2, ',', '.');
$val = static fn (string $field, string $fallback): string => array_key_exists($field, $old) ? $old[$field] : $amount($fallback);
$input = 'mt-1 w-full max-w-[10rem] rounded-md border px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-900';
$border = static fn (string $key): string => isset($errors[$key]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700';
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Fahrzeuge</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Standardwerte für den Fuhrpark.</p>
</div>

<form method="post" action="/einstellungen/fahrzeuge" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Kilometerkosten</h2>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
            Gelten für jedes Fahrzeug, bei dem keine eigenen Kilometerkosten hinterlegt sind.
            Abweichende Werte lassen sich je Fahrzeug bzw. Anhänger in den Stammdaten eintragen.
        </p>
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="default_cost_per_km_auto" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Auto (€ pro km)</label>
            <input type="text" id="default_cost_per_km_auto" name="default_cost_per_km_auto" inputmode="decimal" required
                   value="<?= e($val('default_cost_per_km_auto', $settings->defaultCostPerKmAuto)) ?>"
                   class="<?= $input ?> <?= $border('defaultCostPerKmAuto') ?>">
            <?php if (isset($errors['defaultCostPerKmAuto'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['defaultCostPerKmAuto']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="default_cost_per_km_trailer" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Anhänger (€ pro km)</label>
            <input type="text" id="default_cost_per_km_trailer" name="default_cost_per_km_trailer" inputmode="decimal" required
                   value="<?= e($val('default_cost_per_km_trailer', $settings->defaultCostPerKmTrailer)) ?>"
                   class="<?= $input ?> <?= $border('defaultCostPerKmTrailer') ?>">
            <?php if (isset($errors['defaultCostPerKmTrailer'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['defaultCostPerKmTrailer']) ?></p><?php endif; ?>
        </div>
    </div>
    <div class="flex border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
    </div>
</form>
