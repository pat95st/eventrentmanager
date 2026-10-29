<?php
/** @var \App\Domain\Article\Article|null $article */
/** @var \App\Domain\Manufacturer\Manufacturer[] $manufacturers */
/** @var \App\Domain\Category\Category[] $categories */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */
/** @var list<array{article_id: string, label: string, quantity: int}> $bundleComponentRows */

use App\Domain\Article\ProtectionClass;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\renderLiveSearchField;
use function App\Infrastructure\Http\t;

$byParent = [];
foreach ($categories as $category) {
    $byParent[$category->parentId ?? ''][] = $category;
}

$val = fn (string $field, ?string $fallback = null) => $old[$field] ?? $fallback ?? '';
$requiresElectricalInspection = array_key_exists('requires_electrical_inspection', $old)
    ? (bool) $old['requires_electrical_inspection']
    : ($article?->requiresElectricalInspection ?? false);
$protectionClassValue = $val('protection_class', $article?->protectionClass?->value);
// Neue Artikel sind standardmäßig abrechenbar; nur wer bewusst das Häkchen
// entfernt (z. B. für Zubehör wie Kabel), bekommt is_billable = false.
$isBillable = array_key_exists('is_billable', $old)
    ? (bool) $old['is_billable']
    : ($article?->isBillable ?? true);
$is19Inch = array_key_exists('is_19_inch', $old)
    ? (bool) $old['is_19_inch']
    : ($article?->is19Inch ?? false);
$hasFirmware = array_key_exists('has_firmware', $old)
    ? (bool) $old['has_firmware']
    : ($article?->hasFirmware ?? false);
// Bundles (Migration 107)
$notRentableIndividually = array_key_exists('not_rentable_individually', $old)
    ? (bool) $old['not_rentable_individually']
    : !($article?->isRentableIndividually ?? true);
$isBundle = array_key_exists('is_bundle', $old)
    ? (bool) $old['is_bundle']
    : ($article?->isBundle ?? false);
$bundleComponentRows ??= [];
?>

<div class="mb-6">
    <a href="<?= $article !== null ? '/artikel/' . e($article->id) : '/artikel' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('articles.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $article !== null ? e(t('articles.edit')) : e(t('articles.new')) ?></h1>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/artikel/' . e($editingId) : '/artikel' ?>"
    class="max-w-4xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.product_name')) ?></label>
        <input type="text" id="name" name="name" required
               value="<?= e($val('name', $article?->name)) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <label for="manufacturer_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.manufacturer')) ?></label>
            <div class="mt-1">
                <?php
                $selectedManufacturer = $val('manufacturer_id', $article?->manufacturerId);
                $selectedManufacturerLabel = null;
                foreach ($manufacturers as $manufacturer) {
                    if ($manufacturer->id === $selectedManufacturer) {
                        $selectedManufacturerLabel = $manufacturer->name;
                        break;
                    }
                }
                renderLiveSearchField(
                    'manufacturer_id',
                    '/hersteller/suche',
                    $selectedManufacturer !== '' ? $selectedManufacturer : null,
                    $selectedManufacturerLabel,
                    t('articles.form.manufacturer_placeholder'),
                    $errors['manufacturerId'] ?? null,
                    'select',
                    true,
                );
                ?>
            </div>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.manufacturer_hint')) ?></p>
        </div>

        <div>
            <label for="prefix" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.prefix')) ?></label>
            <input type="text" id="prefix" name="prefix" required maxlength="10" placeholder="z. B. AKS"
                   value="<?= e($val('prefix', $article?->prefix)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['prefix']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 font-mono text-sm uppercase focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['prefix'])): ?>
                <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['prefix']) ?></p>
            <?php else: ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.prefix_hint')) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="category_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.category')) ?></label>
            <select id="category_id" name="category_id"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                <option value=""><?= e(t('articles.form.category_none')) ?></option>
                <?php
                $selectedCategory = $val('category_id', $article?->categoryId);
                $renderOptions = function (?string $parentId, int $depth) use (&$renderOptions, &$byParent, $selectedCategory) {
                    foreach ($byParent[$parentId ?? ''] ?? [] as $category) {
                        $selected = $selectedCategory === $category->id ? 'selected' : '';
                        echo '<option value="' . e($category->id) . '" ' . $selected . '>' . str_repeat('— ', $depth) . e($category->name) . '</option>';
                        $renderOptions($category->id, $depth + 1);
                    }
                };
                $renderOptions(null, 0);
                ?>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <label for="weight_value" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.weight')) ?></label>
            <div class="mt-1 flex gap-2">
                <input type="number" step="any" min="0" id="weight_value" name="weight_value"
                       value="<?= e($val('weight_value', $article?->weightValue)) ?>"
                       class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                <?php $selectedUnit = $val('weight_unit', $article?->weightUnit ?? 'g'); ?>
                <select name="weight_unit" class="w-24 rounded-md border border-slate-300 px-2 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                    <option value="g" <?= $selectedUnit === 'g' ? 'selected' : '' ?>>g</option>
                    <option value="kg" <?= $selectedUnit === 'kg' ? 'selected' : '' ?>>kg</option>
                </select>
            </div>
        </div>
        <div>
            <label for="dimensions" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.dimensions')) ?></label>
            <input type="text" id="dimensions" name="dimensions" placeholder="z. B. 24 x 8 x 22 cm"
                   value="<?= e($val('dimensions', $article?->dimensions)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <div>
            <label for="purchase_price" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.purchase_price')) ?></label>
            <input type="number" step="0.01" min="0" id="purchase_price" name="purchase_price" placeholder="z. B. 199.99"
                   value="<?= e($val('purchase_price', $article?->purchasePrice)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['purchasePrice']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['purchasePrice'])): ?>
                <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['purchasePrice']) ?></p>
            <?php else: ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.purchase_price_hint')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-6">
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" id="requires_electrical_inspection" name="requires_electrical_inspection" value="1" <?= $requiresElectricalInspection ? 'checked' : '' ?>>
            <?= e(t('articles.form.electrical_inspection_checkbox')) ?>
        </label>

        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" id="is_19_inch" name="is_19_inch" value="1" <?= $is19Inch ? 'checked' : '' ?>>
            <?= e(t('articles.form.is_19_inch_checkbox')) ?>
        </label>
    </div>

    <div id="protection_class_field" class="max-w-xs <?= $requiresElectricalInspection ? '' : 'hidden' ?>">
        <label for="protection_class" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.protection_class')) ?></label>
        <select id="protection_class" name="protection_class"
                class="mt-1 w-full rounded-md border <?= isset($errors['protectionClass']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <option value=""><?= e(t('articles.form.protection_class_placeholder')) ?></option>
            <?php foreach (ProtectionClass::all() as $pcOption): ?>
                <option value="<?= e($pcOption->value) ?>" <?= $protectionClassValue === $pcOption->value ? 'selected' : '' ?>><?= e($pcOption->label()) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['protectionClass'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['protectionClass']) ?></p><?php endif; ?>
    </div>

    <div id="is_19_inch_fields" class="grid grid-cols-1 gap-4 sm:grid-cols-2 <?= $is19Inch ? '' : 'hidden' ?>">
        <div>
            <label for="height_units" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.height_units')) ?></label>
            <input type="number" step="1" min="1" id="height_units" name="height_units" placeholder="z. B. 3"
                   value="<?= e($val('height_units', $article?->heightUnits !== null ? (string) $article->heightUnits : null)) ?>"
                   class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['heightUnits']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['heightUnits'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['heightUnits']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="install_depth_cm" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.install_depth_cm')) ?></label>
            <input type="number" step="0.1" min="0" id="install_depth_cm" name="install_depth_cm" placeholder="z. B. 55.5"
                   value="<?= e($val('install_depth_cm', $article?->installDepthCm)) ?>"
                   class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['installDepthCm']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['installDepthCm'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['installDepthCm']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="has_firmware" value="1" <?= $hasFirmware ? 'checked' : '' ?>>
                <?= e(t('articles.form.has_firmware_checkbox')) ?>
            </label>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                <?= e(t('articles.form.has_firmware_hint')) ?>
            </p>
        </div>

        <div>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="is_billable" value="1" <?= $isBillable ? 'checked' : '' ?>>
                <?= e(t('articles.form.billable_checkbox')) ?>
            </label>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                <?= e(t('articles.form.billable_hint')) ?>
            </p>
        </div>

        <div>
            <label for="daily_rental_price" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.daily_rental_price')) ?></label>
            <input type="number" step="0.01" min="0" id="daily_rental_price" name="daily_rental_price" placeholder="z. B. 49.90"
                   value="<?= e($val('daily_rental_price', $article?->dailyRentalPrice)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['dailyRentalPrice']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['dailyRentalPrice'])): ?>
                <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['dailyRentalPrice']) ?></p>
            <?php else: ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.daily_rental_price_hint')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php
        // Vermietung: "nicht einzeln vermietbar" und Bundle (Migration 107).
        // Ein Bundle ist ein eigener Artikel mit eigenem Tagesmietsatz, dessen
        // Bestandteile beim Vermieten automatisch mit eingeplant werden.
    ?>
    <div class="space-y-4 border-t border-slate-100 pt-4 dark:border-slate-800">
        <h2 class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('articles.form.rental_section')) ?></h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div data-not-rentable-wrapper class="<?= $isBundle ? 'opacity-50' : '' ?>">
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="not_rentable_individually" value="1" data-not-rentable <?= $notRentableIndividually && !$isBundle ? 'checked' : '' ?> <?= $isBundle ? 'disabled' : '' ?>>
                    <?= e(t('articles.form.not_rentable_individually')) ?>
                </label>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.not_rentable_individually_hint')) ?></p>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_bundle" value="1" data-is-bundle <?= $isBundle ? 'checked' : '' ?>>
                    <?= e(t('articles.form.is_bundle')) ?>
                </label>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.is_bundle_hint')) ?></p>
                <?php if (isset($errors['isBundle'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['isBundle']) ?></p><?php endif; ?>
            </div>
        </div>

        <div data-bundle-section class="<?= $isBundle ? '' : 'hidden' ?> rounded-md border border-slate-200 p-4 dark:border-slate-700">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('articles.form.bundle_components')) ?></h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.bundle_components_hint')) ?></p>
                </div>
                <button type="button" data-bundle-add class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <?= e(t('articles.form.bundle_component_add')) ?>
                </button>
            </div>
            <?php if (isset($errors['bundleComponents'])): ?><p class="mb-2 text-xs text-red-600 dark:text-red-400"><?= e($errors['bundleComponents']) ?></p><?php endif; ?>
            <div data-bundle-rows class="space-y-2">
                <?php foreach ($bundleComponentRows as $componentRow): ?>
                    <div data-bundle-row class="flex flex-wrap items-end gap-2">
                        <div class="min-w-[220px] flex-1">
                            <?php renderLiveSearchField('bundle_component_article_id[]', '/artikel/suche', $componentRow['article_id'], $componentRow['label'], t('articles.form.bundle_component_placeholder'), null, 'select', false, false); ?>
                        </div>
                        <input type="number" name="bundle_component_quantity[]" min="1" max="999" value="<?= e((string) $componentRow['quantity']) ?>" title="<?= e(t('articles.form.bundle_component_quantity')) ?>"
                               class="w-20 rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-700">
                        <button type="button" data-bundle-remove class="px-2 py-2 text-sm text-red-600 hover:underline dark:text-red-400" title="<?= e(t('common.delete')) ?>">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-bundle-template>
                <div data-bundle-row class="flex flex-wrap items-end gap-2">
                    <div class="min-w-[220px] flex-1">
                        <?php renderLiveSearchField('bundle_component_article_id[]', '/artikel/suche', null, null, t('articles.form.bundle_component_placeholder'), null, 'select', false, false); ?>
                    </div>
                    <input type="number" name="bundle_component_quantity[]" min="1" max="999" value="1" title="<?= e(t('articles.form.bundle_component_quantity')) ?>"
                           class="w-20 rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-700">
                    <button type="button" data-bundle-remove class="px-2 py-2 text-sm text-red-600 hover:underline dark:text-red-400" title="<?= e(t('common.delete')) ?>">&times;</button>
                </div>
            </template>
        </div>
        <script>
            (function () {
                var bundleCheckbox = document.querySelector('[data-is-bundle]');
                var notRentable = document.querySelector('[data-not-rentable]');
                var notRentableWrapper = document.querySelector('[data-not-rentable-wrapper]');
                var section = document.querySelector('[data-bundle-section]');
                var rows = document.querySelector('[data-bundle-rows]');
                var template = document.querySelector('[data-bundle-template]');
                var addButton = document.querySelector('[data-bundle-add]');

                function addRow() {
                    var fragment = template.content.cloneNode(true);
                    var row = fragment.querySelector('[data-bundle-row]');
                    rows.appendChild(fragment);
                    if (window.LiveSearch) { window.LiveSearch.init(row); }
                }

                function sync() {
                    var isBundle = bundleCheckbox.checked;
                    section.classList.toggle('hidden', !isBundle);
                    // Ein Bundle ist selbst immer wählbar — die Option
                    // "nicht einzeln vermietbar" gilt nur für normale Artikel.
                    notRentable.disabled = isBundle;
                    if (isBundle) { notRentable.checked = false; }
                    notRentableWrapper.classList.toggle('opacity-50', isBundle);
                    if (isBundle && rows.querySelectorAll('[data-bundle-row]').length === 0) { addRow(); }
                }

                bundleCheckbox.addEventListener('change', sync);
                addButton.addEventListener('click', addRow);
                rows.addEventListener('click', function (event) {
                    var remove = event.target.closest('[data-bundle-remove]');
                    if (remove) { remove.closest('[data-bundle-row]').remove(); }
                });
                sync();
            })();
        </script>
    </div>

    <div>
        <label for="description" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.description')) ?></label>
        <textarea id="description" name="description" rows="4"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"
        ><?= e($val('description', $article?->description)) ?></textarea>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="<?= $article !== null ? '/artikel/' . e($article->id) : '/artikel' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>

<?php if ($article !== null): ?>
    <p class="mt-4 max-w-2xl text-xs text-slate-400 dark:text-slate-500">
        <?= e(t('articles.form.images_hint')) ?>
    </p>
<?php endif; ?>

<script>
    (function () {
        var checkbox = document.getElementById('is_19_inch');
        var fields = document.getElementById('is_19_inch_fields');

        function sync() {
            fields.classList.toggle('hidden', !checkbox.checked);
        }

        checkbox.addEventListener('change', sync);
        sync();
    })();

    (function () {
        var checkbox = document.getElementById('requires_electrical_inspection');
        var field = document.getElementById('protection_class_field');

        function sync() {
            field.classList.toggle('hidden', !checkbox.checked);
        }

        checkbox.addEventListener('change', sync);
        sync();
    })();
</script>
