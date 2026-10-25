<?php
/** @var \App\Domain\Vehicle\Vehicle|null $vehicle */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */

use App\Domain\Vehicle\VehicleCategory;
use function App\Infrastructure\Http\e;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
$selectedCategory = $val('category', $vehicle?->category->value) ?: VehicleCategory::Auto->value;
$towHitchChecked = array_key_exists('tow_hitch_available', $old) ? (bool) $old['tow_hitch_available'] : ($vehicle?->towHitchAvailable ?? false);
?>

<div class="mb-6">
    <a href="/fahrzeuge" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $vehicle !== null ? 'Fahrzeug bearbeiten' : 'Neues Fahrzeug' ?></h1>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/fahrzeuge/' . e($editingId) : '/fahrzeuge' ?>"
    class="max-w-3xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Kategorie *</label>
        <div class="mt-1 flex gap-4">
            <?php foreach (VehicleCategory::cases() as $category): ?>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="radio" name="category" value="<?= e($category->value) ?>" data-category-radio
                           <?= $selectedCategory === $category->value ? 'checked' : '' ?>>
                    <?= e($category->label()) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if (isset($errors['category'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['category']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bezeichnung *</label>
            <input type="text" id="name" name="name" required placeholder="z. B. Sprinter 1 oder Anhänger klein"
                   value="<?= e($val('name', $vehicle?->name)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>

        <div>
            <label for="license_plate" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Kennzeichen</label>
            <input type="text" id="license_plate" name="license_plate"
                   value="<?= e($val('license_plate', $vehicle?->licensePlate)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="payload_kg" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Zuladung (kg)</label>
            <input type="text" id="payload_kg" name="payload_kg" inputmode="decimal" placeholder="z. B. 750"
                   value="<?= e($val('payload_kg', $vehicle?->payloadKg)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['payloadKg']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['payloadKg'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['payloadKg']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="load_area_dimensions" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Abmessung der Ladefläche</label>
            <input type="text" id="load_area_dimensions" name="load_area_dimensions" placeholder="z. B. 300 x 180 x 200 cm (L x B x H)"
                   value="<?= e($val('load_area_dimensions', $vehicle?->loadAreaDimensions)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
    </div>

    <div data-category-section="auto" class="space-y-3 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nur bei Kategorie "Auto"</h3>
        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" id="tow_hitch_available" name="tow_hitch_available" value="1" data-tow-hitch-checkbox
                   <?= $towHitchChecked ? 'checked' : '' ?>>
            Anhängekupplung vorhanden
        </label>
        <div data-tow-capacity-field>
            <label for="tow_capacity_kg" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Anhängelast (kg)</label>
            <input type="text" id="tow_capacity_kg" name="tow_capacity_kg" inputmode="decimal" placeholder="z. B. 1800"
                   value="<?= e($val('tow_capacity_kg', $vehicle?->towCapacityKg)) ?>"
                   class="mt-1 w-full max-w-xs rounded-md border <?= isset($errors['towCapacityKg']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['towCapacityKg'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['towCapacityKg']) ?></p><?php endif; ?>
        </div>
    </div>

    <div data-category-section="anhaenger" class="space-y-3 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nur bei Kategorie "Anhänger"</h3>
        <div>
            <label for="empty_weight_kg" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Leergewicht (kg)</label>
            <input type="text" id="empty_weight_kg" name="empty_weight_kg" inputmode="decimal" placeholder="z. B. 350"
                   value="<?= e($val('empty_weight_kg', $vehicle?->emptyWeightKg)) ?>"
                   class="mt-1 w-full max-w-xs rounded-md border <?= isset($errors['emptyWeightKg']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['emptyWeightKg'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['emptyWeightKg']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="notes" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notizen</label>
        <textarea id="notes" name="notes" rows="2"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"
        ><?= e($val('notes', $vehicle?->notes)) ?></textarea>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            Speichern
        </button>
        <a href="/fahrzeuge" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
    </div>
</form>

<script>
    // Blendet die Zusatzfelder passend zur gewählten Kategorie ein/aus
    // (Anhängekupplung/Anhängelast nur bei Auto, Leergewicht nur bei
    // Anhänger) — serverseitig wird das unabhängig davon ebenfalls
    // erzwungen (siehe ValidatesVehicleInput).
    (function () {
        var form = document.currentScript.closest('form') || document.querySelector('form');
        var radios = form.querySelectorAll('[data-category-radio]');
        var sections = form.querySelectorAll('[data-category-section]');
        var towHitchCheckbox = form.querySelector('[data-tow-hitch-checkbox]');
        var towCapacityField = form.querySelector('[data-tow-capacity-field]');

        function update() {
            var selected = form.querySelector('[data-category-radio]:checked');
            var category = selected ? selected.value : 'auto';

            sections.forEach(function (section) {
                section.classList.toggle('hidden', section.getAttribute('data-category-section') !== category);
            });

            if (towCapacityField) {
                towCapacityField.classList.toggle('hidden', !(towHitchCheckbox && towHitchCheckbox.checked));
            }
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', update);
        });
        if (towHitchCheckbox) {
            towHitchCheckbox.addEventListener('change', update);
        }

        update();
    })();
</script>
