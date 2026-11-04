<?php
/** @var \App\Domain\Article\Article|null $article */
/** @var \App\Domain\Manufacturer\Manufacturer[] $manufacturers */
/** @var \App\Domain\Category\Category[] $categories */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */
/** @var \App\Domain\PowerDistributor\PowerDistributor|null $powerDistributor gespeicherte Stromverteiler-Angaben (Migration 117) */
/** @var string|null $powerDistributorCategoryId Kategorie "Stromverteiler" (Migration 118) */

use App\Domain\Article\ProtectionClass;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\renderLiveSearchField;
use function App\Infrastructure\Http\t;

$byParent = [];
foreach ($categories as $category) {
    $byParent[$category->parentId ?? ''][] = $category;
}

$val = fn (string $field, ?string $fallback = null) => $old[$field] ?? $fallback ?? '';
$powerDistributor ??= null;
// Stromverteiler (Migration 117): nach Validierungsfehler aus $old, sonst aus den gespeicherten Angaben.
$powerDistributorCategoryId ??= null;
// Stromverteiler = Kategorie "Stromverteiler" gewählt (Migration 118).
$isPowerDistributor = $powerDistributorCategoryId !== null && $val('category_id', $article?->categoryId) === $powerDistributorCategoryId;
$requiresElectricalInspection = $isPowerDistributor || (array_key_exists('requires_electrical_inspection', $old)
    ? (bool) $old['requires_electrical_inspection']
    : ($article?->requiresElectricalInspection ?? false));
// Leitungen (Migration 125): Kategorie "Leitungen" gewählt.
$cable ??= null;
$cableCategoryId ??= null;
$cableSettings ??= null;
$isCable = $cableCategoryId !== null && $val('category_id', $article?->categoryId) === $cableCategoryId;
$protectionClassValue = $val('protection_class', $article?->protectionClass?->value ?? ($isPowerDistributor ? 'I' : null));
// Neue Artikel sind standardmäßig abrechenbar; nur wer bewusst das Häkchen
// entfernt (z. B. für Zubehör wie Kabel), bekommt is_billable = false.
$isBillable = array_key_exists('is_billable', $old)
    ? (bool) $old['is_billable']
    : ($article?->isBillable ?? true);
$is19Inch = array_key_exists('is_19_inch', $old)
    ? (bool) $old['is_19_inch']
    : ($article?->is19Inch ?? false);
// Migration 128: Einbaubreite (19 Zoll / 9,5 Zoll)
$rackWidthValue = array_key_exists('rack_width', $old) && $old['rack_width'] !== null
    ? (string) $old['rack_width']
    : ($article?->rackWidth->value ?? \App\Domain\Rack\RackWidth::Full->value);
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
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $editingId !== null ? e(t('articles.edit')) : ($article !== null ? 'Artikel duplizieren' : e(t('articles.new'))) ?></h1>
<?php if ($article !== null && $editingId === null): ?>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kopie von „<?= e($article->name) ?>“ – bitte ein neues Präfix für die Inventurnummern vergeben. Zubehör und Vor-Ausgabe-Checks werden mit übernommen, Inventurnummern und Bilder nicht.</p>
<?php endif; ?>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/artikel/' . e($editingId) : '/artikel' ?>"
    class="max-w-4xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <?php if ($article !== null && $editingId === null): /* Duplizieren: Quelle für Zubehör/Checks */ ?><input type="hidden" name="duplicate_of" value="<?= e($article->id) ?>"><?php endif; ?>
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
                // Leitungen haben ein eigenes Formular ("Neuer Artikel › Leitung"), daher hier nicht wählbar.
                $hiddenCategoryId = $cableCategoryId !== null && $selectedCategory !== $cableCategoryId ? $cableCategoryId : null;
                $renderOptions = function (?string $parentId, int $depth) use (&$renderOptions, &$byParent, $selectedCategory, $hiddenCategoryId) {
                    foreach ($byParent[$parentId ?? ''] ?? [] as $category) {
                        if ($category->id === $hiddenCategoryId) {
                            continue;
                        }
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

    <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-6">
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="hidden" name="requires_electrical_inspection" value="1" id="requires_electrical_inspection_forced" <?= $isPowerDistributor ? '' : 'disabled' ?>>
            <input type="checkbox" id="requires_electrical_inspection" name="requires_electrical_inspection" value="1" <?= $requiresElectricalInspection ? 'checked' : '' ?> <?= $isPowerDistributor ? 'disabled' : '' ?>>
            <?= e(t('articles.form.electrical_inspection_checkbox')) ?>
            <span id="electrical_inspection_forced_hint" class="text-xs text-slate-500 dark:text-slate-400 <?= $isPowerDistributor ? '' : 'hidden' ?>">(bei Stromverteilern immer erforderlich)</span>
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

    <!-- Stromverteiler (Migration 117/118): erscheint bei Kategorie "Stromverteiler" -->
    <div id="power_distributor_section" class="rounded-md border border-amber-300 bg-amber-50/40 p-4 dark:border-amber-700 dark:bg-amber-950/20 <?= $isPowerDistributor ? '' : 'hidden' ?>" data-power-distributor-category="<?= e((string) $powerDistributorCategoryId) ?>">
        <input type="hidden" name="power_distributor_section" value="1">
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Stromverteiler</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Zuleitung, FI/LS und Abgänge erfassen. Die Elektroprüfung ist bei Stromverteilern immer erforderlich (Standard Schutzklasse I). Andere Kategorie wählen, wenn der Artikel kein Stromverteiler ist.</p>
        <div id="power_distributor_fields" class="mt-4">
            <?= \App\Infrastructure\Http\View::render('power-distributors/_fields', [
                'distributor' => $powerDistributor,
                'old' => array_key_exists('power_distributor', $old) ? $old['power_distributor'] : null,
                'errors' => $errors,
            ]) ?>
        </div>
    </div>

    <!-- Leitungen (Migration 125): erscheint bei Kategorie "Leitungen" -->
    <?php if ($cableCategoryId !== null && $cableSettings !== null): ?>
    <?php
        $cableTypeSelected = $val('cable_type_id', $cable?->cable->cableTypeId);
        $cableConnectorA = $val('cable_connector_a', $cable?->cable->connectorAId);
        $cableConnectorB = $val('cable_connector_b', $cable?->cable->connectorBId);
        $cableLength = $val('cable_length', $cable !== null ? rtrim(rtrim(number_format((float) $cable->cable->lengthM, 2, ',', ''), '0'), ',') : null);
        $cableErr = fn (string $key) => isset($errors[$key]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$key]) . '</p>' : '';
        $cableInput = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
    ?>
    <div id="cable_section" class="rounded-md border border-brand-200 bg-brand-50 p-4 dark:border-brand-800 dark:bg-brand-950/40 <?= $isCable ? '' : 'hidden' ?>"
         data-cable-category="<?= e($cableCategoryId) ?>"
         data-cable-config="<?= e((string) json_encode($cableSettings->formConfig(), JSON_UNESCAPED_UNICODE)) ?>"
         data-new-article="<?= $article === null ? '1' : '0' ?>">
        <input type="hidden" name="cable_section" value="1">
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Leitung</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Leitungstyp, Länge und die beiden Steckverbinder wählen – angeboten werden nur Steckverbinder, die zum Leitungstyp passen. Leitungstypen, Steckverbinder und sinnvolle Kombinationen pflegst du unter <a href="/einstellungen/leitungen" class="underline">Einstellungen › Leitungen</a>. Bleibt der Produktname leer, wird er aus den Angaben gebildet.</p>
        <?php if ($cableSettings->types === []): ?>
            <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">Es sind noch keine Leitungstypen angelegt.</p>
        <?php endif; ?>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="cable_type_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Leitungstyp *</label>
                <select id="cable_type_id" name="cable_type_id" class="<?= $cableInput ?>">
                    <option value="">Bitte wählen…</option>
                    <?php foreach ($cableSettings->types as $cableTypeOption): ?>
                        <option value="<?= e($cableTypeOption->id) ?>" <?= $cableTypeSelected === $cableTypeOption->id ? 'selected' : '' ?>><?= e($cableTypeOption->name) ?><?= $cableTypeOption->requiresElectricalInspection ? ' (prüfpflichtig)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $cableErr('cable_type_id') ?>
            </div>
            <div>
                <label for="cable_length" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Länge in m *</label>
                <input type="text" inputmode="decimal" id="cable_length" name="cable_length" value="<?= e($cableLength) ?>" placeholder="z. B. 10 oder 2,5" class="<?= $cableInput ?> max-w-[10rem]">
                <?= $cableErr('cable_length') ?>
            </div>
            <?php foreach (['cable_connector_a' => [$cableConnectorA, 'Steckverbinder Seite A *'], 'cable_connector_b' => [$cableConnectorB, 'Steckverbinder Seite B *']] as $cableField => [$cableSelected, $cableLabel]): ?>
                <div>
                    <label for="<?= $cableField ?>" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e($cableLabel) ?></label>
                    <select id="<?= $cableField ?>" name="<?= $cableField ?>" data-cable-connector class="<?= $cableInput ?>">
                        <option value="">Bitte wählen…</option>
                        <?php foreach ($cableSettings->connectors as $connectorOption): ?>
                            <option value="<?= e($connectorOption->id) ?>" <?= $cableSelected === $connectorOption->id ? 'selected' : '' ?>><?= e($connectorOption->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $cableErr($cableField) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div id="is_19_inch_fields" class="grid grid-cols-1 gap-4 sm:grid-cols-3 <?= $is19Inch ? '' : 'hidden' ?>">
        <fieldset>
            <legend class="block text-xs font-medium text-slate-600 dark:text-slate-400">Einbaubreite</legend>
            <div class="mt-2 flex flex-wrap gap-4">
                <?php foreach (\App\Domain\Rack\RackWidth::cases() as $rackWidthOption): ?>
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="radio" name="rack_width" value="<?= e($rackWidthOption->value) ?>" <?= $rackWidthValue === $rackWidthOption->value ? 'checked' : '' ?>>
                        <?= e($rackWidthOption->label()) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">9,5-Zoll-Geräte passen in 9,5-Zoll-Racks oder zu zweit nebeneinander in ein 19-Zoll-Rack.</p>
        </fieldset>
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
            <div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="not_rentable_individually" value="1" <?= $notRentableIndividually ? 'checked' : '' ?>>
                    <?= e(t('articles.form.not_rentable_individually')) ?>
                </label>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.not_rentable_individually_hint')) ?></p>
            </div>
        </div>

    </div>

    <div>
        <label for="description" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.description')) ?></label>
        <textarea id="description" name="description" rows="4"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"
        ><?= e($val('description', $article?->description)) ?></textarea>
    </div>

    <div>
        <label for="document_short_text" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('articles.form.document_short_text')) ?></label>
        <input type="text" id="document_short_text" name="document_short_text" maxlength="500"
               value="<?= e((string) $val('document_short_text', $article?->documentShortText)) ?>"
               placeholder="<?= e(t('articles.form.document_short_text_placeholder')) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['documentShortText']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['documentShortText'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['documentShortText']) ?></p><?php endif; ?>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('articles.form.document_short_text_hint')) ?></p>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="<?= $article !== null ? '/artikel/' . e($article->id) : '/artikel' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>

<?php if ($editingId !== null): ?>
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
        var forced = document.getElementById('requires_electrical_inspection_forced');
        var hint = document.getElementById('electrical_inspection_forced_hint');
        var field = document.getElementById('protection_class_field');
        var protectionClass = document.getElementById('protection_class');
        var category = document.getElementById('category_id');
        var distributorSection = document.getElementById('power_distributor_section');
        var distributorCategory = distributorSection ? distributorSection.getAttribute('data-power-distributor-category') : '';

        function sync() {
            // Stromverteiler: Elektroprüfung immer erforderlich, Schutzklasse standardmäßig I.
            var isDistributor = !!distributorCategory && category && category.value === distributorCategory;
            if (isDistributor) {
                checkbox.checked = true;
                if (protectionClass && !protectionClass.value) { protectionClass.value = 'I'; }
            }
            checkbox.disabled = isDistributor;
            forced.disabled = !isDistributor;
            hint.classList.toggle('hidden', !isDistributor);
            if (distributorSection) { distributorSection.classList.toggle('hidden', !isDistributor); }
            field.classList.toggle('hidden', !checkbox.checked);
        }

        checkbox.addEventListener('change', sync);
        if (category) { category.addEventListener('change', sync); }
        sync();
    })();
</script>
<script src="/assets/js/power-distributor-fields.js"></script>
<script src="/assets/js/cable-fields.js"></script>
