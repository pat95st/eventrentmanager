<?php
/** @var \App\Domain\Depreciation\DepreciationSettings $settings */
/** @var array<string, int> $categoryYears */
/** @var list<array{id: string, name: string, depth: int, parentId: string|null}> $categories */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use App\Domain\Depreciation\DepreciationFirstYearMode;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$money = static fn (string $value): string => number_format((float) $value, 2, ',', '.');
$val = static fn (string $key, string $fallback): string => array_key_exists($key, $old) && is_scalar($old[$key]) ? (string) $old[$key] : $fallback;
$inputClass = static fn (bool $error): string => 'w-full rounded-md border ' . ($error ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700') . ' px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-900';
$mode = $val('first_year_mode', $settings->firstYearMode->value);
$oldCategoryYears = is_array($old['category_years'] ?? null) ? $old['category_years'] : null;

// Geerbte Nutzungsdauer je Kategorie (für den Platzhalter): eigene Angabe der nächsten Elternkategorie, sonst Standard.
$effective = [];
$inheritedYears = [];
foreach ($categories as $category) {
    $inherited = $category['parentId'] !== null ? ($effective[$category['parentId']] ?? $settings->defaultUsefulLifeYears) : $settings->defaultUsefulLifeYears;
    $effective[$category['id']] = $categoryYears[$category['id']] ?? $inherited;
    $inheritedYears[$category['id']] = $inherited;
}
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.depreciation.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.depreciation.subtitle')) ?></p>
</div>

<div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
    <?= e(t('settings.depreciation.basis_hint')) ?>
    <a href="/berichte/abschreibungen" class="text-brand-700 underline dark:text-brand-400"><?= e(t('settings.depreciation.report_link')) ?></a>
</div>

<form method="post" action="/einstellungen/abschreibungen" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.depreciation.section.rules')) ?></h2>

        <div>
            <label for="default_useful_life_years" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.depreciation.field.default_years')) ?></label>
            <div class="mt-1 flex max-w-[10rem] items-center gap-1">
                <input type="number" min="1" max="<?= \App\Domain\Depreciation\DepreciationSettings::MAX_USEFUL_LIFE_YEARS ?>" step="1" id="default_useful_life_years" name="default_useful_life_years"
                       value="<?= e($val('default_useful_life_years', (string) $settings->defaultUsefulLifeYears)) ?>"
                       class="<?= $inputClass(isset($errors['default_useful_life_years'])) ?>">
                <span class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.depreciation.years')) ?></span>
            </div>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.depreciation.field.default_years_hint')) ?></p>
            <?php if (isset($errors['default_useful_life_years'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['default_useful_life_years']) ?></p><?php endif; ?>
        </div>

        <div>
            <label for="low_value_threshold" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.depreciation.field.low_value_threshold')) ?></label>
            <div class="mt-1 flex max-w-[10rem] items-center gap-1">
                <input type="text" inputmode="decimal" id="low_value_threshold" name="low_value_threshold"
                       value="<?= e($val('low_value_threshold', $money($settings->lowValueThreshold))) ?>"
                       class="<?= $inputClass(isset($errors['low_value_threshold'])) ?>">
                <span class="text-sm text-slate-500 dark:text-slate-400">€</span>
            </div>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.depreciation.field.low_value_threshold_hint')) ?></p>
            <?php if (isset($errors['low_value_threshold'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['low_value_threshold']) ?></p><?php endif; ?>
        </div>

        <fieldset>
            <legend class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.depreciation.field.first_year_mode')) ?></legend>
            <div class="mt-2 space-y-2">
                <?php foreach (DepreciationFirstYearMode::cases() as $case): ?>
                    <div class="flex items-start gap-2">
                        <input type="radio" id="first_year_mode_<?= e($case->value) ?>" name="first_year_mode" value="<?= e($case->value) ?>" <?= $mode === $case->value ? 'checked' : '' ?>
                               class="mt-1 h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                        <div>
                            <label for="first_year_mode_<?= e($case->value) ?>" class="block text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('settings.depreciation.first_year_mode.' . $case->value)) ?></label>
                            <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.depreciation.first_year_mode.' . $case->value . '_hint')) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (isset($errors['first_year_mode'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['first_year_mode']) ?></p><?php endif; ?>
        </fieldset>

        <div>
            <label for="residual_value" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.depreciation.field.residual_value')) ?></label>
            <div class="mt-1 flex max-w-[10rem] items-center gap-1">
                <input type="text" inputmode="decimal" id="residual_value" name="residual_value"
                       value="<?= e($val('residual_value', $money($settings->residualValue))) ?>"
                       class="<?= $inputClass(isset($errors['residual_value'])) ?>">
                <span class="text-sm text-slate-500 dark:text-slate-400">€</span>
            </div>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.depreciation.field.residual_value_hint')) ?></p>
            <?php if (isset($errors['residual_value'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['residual_value']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.depreciation.section.categories')) ?></h2>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.depreciation.section.categories_hint')) ?></p>
        <?php if (isset($errors['category_years'])): ?><p class="mt-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['category_years']) ?></p><?php endif; ?>

        <?php if ($categories === []): ?>
            <p class="mt-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.depreciation.no_categories')) ?></p>
        <?php else: ?>
            <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $own = $oldCategoryYears !== null
                        ? (is_scalar($oldCategoryYears[$category['id']] ?? null) ? (string) $oldCategoryYears[$category['id']] : '')
                        : (isset($categoryYears[$category['id']]) ? (string) $categoryYears[$category['id']] : '');
                    ?>
                    <li class="flex items-center justify-between gap-3 py-2">
                        <label for="category_years_<?= e($category['id']) ?>" class="text-sm text-slate-700 dark:text-slate-300" style="padding-left: <?= $category['depth'] * 1.25 ?>rem">
                            <?= e($category['name']) ?>
                        </label>
                        <div class="flex shrink-0 items-center gap-1" style="width: 9rem">
                            <input type="number" min="1" max="<?= \App\Domain\Depreciation\DepreciationSettings::MAX_USEFUL_LIFE_YEARS ?>" step="1"
                                   id="category_years_<?= e($category['id']) ?>" name="category_years[<?= e($category['id']) ?>]"
                                   value="<?= e($own) ?>" placeholder="<?= (int) $inheritedYears[$category['id']] ?>"
                                   class="<?= $inputClass(false) ?>">
                            <span class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.depreciation.years')) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="form-actions flex items-center gap-3">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
    </div>
</form>
