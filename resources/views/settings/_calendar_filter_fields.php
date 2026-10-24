<?php
/**
 * Gemeinsame Filter-Auswahlfelder für "Vermietungskalender anlegen" und
 * "Vermietungskalender bearbeiten" (siehe calendars.php). Erwartet
 * $categories/$manufacturers/$warehouses/$venues sowie optional
 * $editCalendar (null beim Anlegen) für die aktuell ausgewählten Werte.
 *
 * @var \App\Domain\Category\Category[] $categories
 * @var \App\Domain\Manufacturer\Manufacturer[] $manufacturers
 * @var \App\Domain\Warehouse\Warehouse[] $warehouses
 * @var \App\Domain\Venue\Venue[] $venues
 * @var \App\Domain\Calendar\Calendar|null $editCalendar
 * @var array<string, string> $errors
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>
<?php if (isset($errors['filter'])): ?>
    <p class="text-xs text-red-600"><?= e($errors['filter']) ?></p>
<?php endif; ?>
<p class="text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.calendars.filter_hint')) ?></p>
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.calendars.filter.category')) ?></label>
        <select name="category_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
            <option value=""><?= e(t('settings.calendars.filter.none')) ?></option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category->id) ?>" <?= ($editCalendar?->categoryId === $category->id) ? 'selected' : '' ?>><?= e($category->name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.calendars.filter.manufacturer')) ?></label>
        <select name="manufacturer_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
            <option value=""><?= e(t('settings.calendars.filter.none')) ?></option>
            <?php foreach ($manufacturers as $manufacturer): ?>
                <option value="<?= e($manufacturer->id) ?>" <?= ($editCalendar?->manufacturerId === $manufacturer->id) ? 'selected' : '' ?>><?= e($manufacturer->name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.calendars.filter.warehouse')) ?></label>
        <select name="warehouse_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
            <option value=""><?= e(t('settings.calendars.filter.none')) ?></option>
            <?php foreach ($warehouses as $warehouse): ?>
                <option value="<?= e($warehouse->id) ?>" <?= ($editCalendar?->warehouseId === $warehouse->id) ? 'selected' : '' ?>><?= e($warehouse->name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.calendars.filter.venue')) ?></label>
        <select name="venue_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
            <option value=""><?= e(t('settings.calendars.filter.none')) ?></option>
            <?php foreach ($venues as $venue): ?>
                <option value="<?= e($venue->id) ?>" <?= ($editCalendar?->venueId === $venue->id) ? 'selected' : '' ?>><?= e($venue->name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
