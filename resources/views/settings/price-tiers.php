<?php
/** @var \App\Domain\Settings\PriceTier[] $tiers */
/** @var int $maxDays */
/** @var \App\Domain\Settings\PricingSettings $pricingSettings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use App\Domain\Settings\PriceRoundingDirection;
use App\Domain\Settings\PriceRoundingStep;
use App\Domain\Settings\PriceTier;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$percentageByDay = [];
foreach ($tiers as $tier) {
    $percentageByDay[$tier->dayCount] = $tier->percentage;
}

$val = fn (int $day) => $old["percentage_{$day}"] ?? ($percentageByDay[$day] ?? '');
$roundingStepVal = (string) ($old['price_rounding_step'] ?? $pricingSettings->priceRoundingStep->value);
$roundingDirectionVal = (string) ($old['price_rounding_direction'] ?? $pricingSettings->priceRoundingDirection->value);
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.price_tiers.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('settings.price_tiers.subtitle')) ?>
    </p>
</div>

<form method="get" action="/einstellungen/preisstaffelung" class="mb-4 flex max-w-2xl flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <div>
        <label for="tage" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.price_tiers.days_count_label')) ?></label>
        <input type="number" id="tage" name="tage" min="2" max="<?= PriceTier::MAX_DAY_COUNT ?>" value="<?= $maxDays ?>"
               class="mt-1 w-24 rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700">
    </div>
    <button type="submit" class="rounded-md border border-slate-300 px-4 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
        <?= e(t('settings.price_tiers.days_count_apply')) ?>
    </button>
    <p class="w-full text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.price_tiers.days_count_hint')) ?></p>
</form>

<form method="post" action="/einstellungen/preisstaffelung" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
  <input type="hidden" name="max_days" value="<?= $maxDays ?>">
    <?php if (isset($errors['max_days'])): ?>
        <p class="text-xs text-red-600 dark:text-red-400"><?= e($errors['max_days']) ?></p>
    <?php endif; ?>
    <div class="overflow-hidden rounded-md border border-slate-200 dark:border-slate-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2.5"><?= e(t('settings.price_tiers.table.days')) ?></th>
                    <th class="px-4 py-2.5"><?= e(t('settings.price_tiers.table.percentage')) ?></th>
                    <th class="px-4 py-2.5 text-right"><?= e(t('settings.price_tiers.table.example')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr>
                    <td class="px-4 py-2.5 font-medium text-slate-800 dark:text-slate-200"><?= e(t('settings.price_tiers.day_singular')) ?></td>
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-2">
                            <input type="text" value="100" disabled
                                   class="w-24 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                            <span class="text-slate-400 dark:text-slate-500"><?= e(t('settings.price_tiers.fixed_hint')) ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300" data-example-price data-percentage="100">–</td>
                </tr>
                <?php for ($day = 2; $day <= $maxDays; $day++): ?>
                    <tr>
                        <td class="px-4 py-2.5 font-medium text-slate-800 dark:text-slate-200"><?= e(t('settings.price_tiers.day_plural', ['count' => (string) $day])) ?></td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <input type="number" step="0.01" min="0" name="percentage_<?= $day ?>" data-percentage-input
                                       value="<?= e((string) $val($day)) ?>"
                                       class="w-24 rounded-md border <?= isset($errors["percentage_{$day}"]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                <span class="text-slate-400 dark:text-slate-500">%</span>
                            </div>
                            <?php if (isset($errors["percentage_{$day}"])): ?>
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors["percentage_{$day}"]) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-slate-700 dark:text-slate-300" data-example-price>–</td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <p class="text-xs text-slate-400 dark:text-slate-500">
        <?= e(t('settings.price_tiers.example')) ?>
    </p>

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label for="example_daily_price" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.price_tiers.example_daily_price')) ?></label>
            <input type="text" id="example_daily_price" inputmode="decimal" value="12,34" data-example-daily-price
                   class="mt-1 w-28 rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700">
        </div>
        <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.price_tiers.example_daily_price_hint')) ?></p>
    </div>

    <?php
        // Nutzerwunsch: Preisrundung direkt bei der Preisstaffelung (zuvor
        // unter Einstellungen > Preise) — sie wirkt auf alle aus der Staffel
        // berechneten Preise (Preisvorschläge in Dokumenten, Preisliste
        // PDF/CSV, Artikeldetail) und wird hier in der Beispielspalte
        // sofort sichtbar.
    ?>
    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
        <h2 class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('settings.pricing.rounding.heading')) ?></h2>
        <p class="mb-3 mt-0.5 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.pricing.rounding.hint')) ?></p>

        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="sm:flex-1">
                <label for="price_rounding_step" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.pricing.field.price_rounding_step')) ?></label>
                <select id="price_rounding_step" name="price_rounding_step" data-rounding-step
                        class="mt-1 w-full rounded-md border <?= isset($errors['priceRoundingStep']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php foreach (PriceRoundingStep::cases() as $step): ?>
                        <option value="<?= e($step->value) ?>" data-amount="<?= e((string) ($step->amount() ?? '')) ?>" <?= $roundingStepVal === $step->value ? 'selected' : '' ?>><?= e($step->label()) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['priceRoundingStep'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['priceRoundingStep']) ?></p><?php endif; ?>
            </div>

            <div class="sm:flex-1">
                <label for="price_rounding_direction" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.pricing.field.price_rounding_direction')) ?></label>
                <select id="price_rounding_direction" name="price_rounding_direction" data-rounding-direction
                        class="mt-1 w-full rounded-md border <?= isset($errors['priceRoundingDirection']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php foreach (PriceRoundingDirection::cases() as $direction): ?>
                        <option value="<?= e($direction->value) ?>" <?= $roundingDirectionVal === $direction->value ? 'selected' : '' ?>><?= e($direction->label()) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['priceRoundingDirection'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['priceRoundingDirection']) ?></p><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>

<script>
    // Beispielspalte: zeigt live, welcher (gerundete) Preis sich je
    // Staffel aus dem Beispiel-Tagesmietsatz ergibt — identische Rechnung
    // wie App\Domain\Settings\RentalPriceCalculator::calculate()/round().
    (function () {
        var dailyInput = document.querySelector('[data-example-daily-price]');
        var stepSelect = document.querySelector('[data-rounding-step]');
        var directionSelect = document.querySelector('[data-rounding-direction]');
        var rows = document.querySelectorAll('[data-example-price]');

        function parseAmount(value) {
            var normalized = String(value || '').trim().replace(/\./g, '').replace(',', '.');
            var number = parseFloat(normalized);
            return isNaN(number) ? null : number;
        }

        function roundPrice(price, step, direction) {
            if (!step || step <= 0) { return Math.round(price * 100) / 100; }
            var units = Math.round((price / step) * 1e6) / 1e6;
            var roundedUnits = direction === 'up' ? Math.ceil(units) : (direction === 'down' ? Math.floor(units) : Math.round(units));
            return Math.round(roundedUnits * step * 100) / 100;
        }

        function formatEuro(value) {
            return value.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
        }

        function update() {
            var daily = parseAmount(dailyInput ? dailyInput.value : '');
            var selected = stepSelect ? stepSelect.options[stepSelect.selectedIndex] : null;
            var step = selected && selected.getAttribute('data-amount') !== '' ? parseFloat(selected.getAttribute('data-amount')) : null;
            var direction = directionSelect ? directionSelect.value : 'nearest';
            rows.forEach(function (cell) {
                var percentage = cell.hasAttribute('data-percentage')
                    ? parseFloat(cell.getAttribute('data-percentage'))
                    : parseAmount((cell.closest('tr').querySelector('[data-percentage-input]') || {}).value);
                if (daily === null || percentage === null || isNaN(percentage)) {
                    cell.textContent = '–';
                    return;
                }
                cell.textContent = formatEuro(roundPrice(daily * percentage / 100, step, direction));
            });
        }

        document.addEventListener('input', function (event) {
            if (event.target.matches('[data-example-daily-price], [data-percentage-input]')) { update(); }
        });
        [stepSelect, directionSelect].forEach(function (el) { if (el) { el.addEventListener('change', update); } });
        update();
    })();
</script>
