<?php
/** @var \App\Domain\Article\Article|null $article */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */
/** @var \App\Application\Cable\Query\ArticleCableDetail|null $cable */
/** @var string $cableCategoryId */
/** @var \App\Application\Cable\Query\CableSettings $cableSettings */
/** @var \App\Domain\Manufacturer\Manufacturer|null $genericManufacturer */

/*
 * Eigenes Formular für Leitungs-Artikel (Nutzerwunsch). Viele Angaben sind
 * für Leitungen fest und werden serverseitig gesetzt (siehe
 * App\Application\Cable\CableArticleRules): Hersteller „Generisch“, immer
 * Zubehör, nicht einzeln vermietbar, kein Einkaufs-/Tagespreis, keine
 * Firmware, kein Rackeinbau, Elektroprüfung nach Leitungstyp, keine
 * Schutzklasse.
 */

use function App\Infrastructure\Http\e;

$val = fn (string $field, ?string $fallback = null) => $old[$field] ?? $fallback ?? '';
$input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
$err = fn (string $key) => isset($errors[$key]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$key]) . '</p>' : '';
$label = 'block text-xs font-medium text-slate-600 dark:text-slate-400';

$cableTypeSelected = $val('cable_type_id', $cable?->cable->cableTypeId);
$cableConnectorA = $val('cable_connector_a', $cable?->cable->connectorAId);
$cableConnectorB = $val('cable_connector_b', $cable?->cable->connectorBId);
$cableLength = $val('cable_length', $cable !== null ? rtrim(rtrim(number_format((float) $cable->cable->lengthM, 2, ',', ''), '0'), ',') : null);
$selectedUnit = $val('weight_unit', $article?->weightUnit ?? 'g');
?>
<div class="mb-6">
    <a href="<?= $article !== null ? '/artikel/' . e($article->id) : '/artikel' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $editingId !== null ? 'Leitung bearbeiten' : ($article !== null ? 'Leitung duplizieren' : 'Neue Leitung') ?></h1>
    <?php if ($article !== null && $editingId === null): ?>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kopie von „<?= e($article->name) ?>“ – bitte ein neues Präfix vergeben. Inventurnummern werden nicht übernommen.</p>
    <?php endif; ?>
    <p class="text-sm text-slate-500 dark:text-slate-400">Leitungstypen und Steckverbinder pflegst du unter <a href="/einstellungen/leitungen" class="underline">Einstellungen › Sonstiges › Leitungen</a>.</p>
</div>

<form method="post" action="<?= $editingId !== null ? '/artikel/' . e($editingId) : '/artikel' ?>"
      class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <?php if ($article !== null && $editingId === null): /* Duplizieren: Quelle für Zubehör/Checks */ ?><input type="hidden" name="duplicate_of" value="<?= e($article->id) ?>"><?php endif; ?>
    <input type="hidden" id="category_id" name="category_id" value="<?= e($cableCategoryId) ?>">

    <?php if ($genericManufacturer === null): ?>
        <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
            Es gibt keinen Hersteller „Generisch“. Leitungen werden immer unter diesem Hersteller angelegt – bitte zuerst unter „Hersteller“ anlegen.
        </p>
    <?php endif; ?>
    <?php if (isset($errors['manufacturerId'])): ?>
        <p class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800"><?= e($errors['manufacturerId']) ?></p>
    <?php endif; ?>

    <!-- Leitung: Typ, Länge, Steckverbinder (Logik in cable-fields.js) -->
    <div id="cable_section" class="rounded-md border border-brand-200 bg-brand-50 p-4 dark:border-brand-800 dark:bg-brand-950/40"
         data-cable-category="<?= e($cableCategoryId) ?>"
         data-cable-config="<?= e((string) json_encode($cableSettings->formConfig(), JSON_UNESCAPED_UNICODE)) ?>"
         data-new-article="<?= $article === null ? '1' : '0' ?>">
        <input type="hidden" name="cable_section" value="1">
        <?php if ($cableSettings->types === []): ?>
            <p class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">Es sind noch keine Leitungstypen angelegt.</p>
        <?php endif; ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="cable_type_id" class="<?= $label ?>">Leitungstyp *</label>
                <select id="cable_type_id" name="cable_type_id" required class="<?= $input ?>">
                    <option value="">Bitte wählen…</option>
                    <?php foreach ($cableSettings->types as $type): ?>
                        <option value="<?= e($type->id) ?>" <?= $cableTypeSelected === $type->id ? 'selected' : '' ?>><?= e($type->name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('cable_type_id') ?>
            </div>
            <div>
                <label for="cable_length" class="<?= $label ?>">Länge in m *</label>
                <input type="text" inputmode="decimal" id="cable_length" name="cable_length" required value="<?= e($cableLength) ?>" placeholder="z. B. 10 oder 2,5" class="<?= $input ?>" style="max-width:10rem">
                <?= $err('cable_length') ?>
            </div>
            <?php foreach (['cable_connector_a' => [$cableConnectorA, 'Steckverbinder Seite A *'], 'cable_connector_b' => [$cableConnectorB, 'Steckverbinder Seite B *']] as $field => [$selected, $fieldLabel]): ?>
                <div>
                    <label for="<?= $field ?>" class="<?= $label ?>"><?= e($fieldLabel) ?></label>
                    <select id="<?= $field ?>" name="<?= $field ?>" required data-cable-connector class="<?= $input ?>">
                        <option value="">Bitte wählen…</option>
                        <?php foreach ($cableSettings->connectors as $connector): ?>
                            <option value="<?= e($connector->id) ?>" <?= $selected === $connector->id ? 'selected' : '' ?>><?= e($connector->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err($field) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p id="cable_inspection_info" class="mt-3 text-xs text-slate-600 dark:text-slate-400" aria-live="polite"></p>
    </div>

    <div>
        <label for="name" class="<?= $label ?>">Produktname</label>
        <input type="text" id="name" name="name" maxlength="255" value="<?= e($val('name', $article?->name)) ?>" placeholder="wird aus Leitungstyp, Länge und Steckverbindern gebildet" class="<?= $input ?>">
        <?= $err('name') ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="prefix" class="<?= $label ?>">Präfix für Inventurnummern *</label>
            <input type="text" id="prefix" name="prefix" required maxlength="10" placeholder="z. B. K32" value="<?= e($val('prefix', $article?->prefix)) ?>" class="<?= $input ?> font-mono uppercase">
            <?php if (isset($errors['prefix'])): ?>
                <?= $err('prefix') ?>
            <?php else: ?>
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Inventurnummern: <?= e($genericManufacturer?->prefix ?? 'GEN') ?>-PRÄFIX-0001</p>
            <?php endif; ?>
        </div>
        <div>
            <label for="weight_value" class="<?= $label ?>">Gewicht</label>
            <div class="mt-1 flex gap-2">
                <input type="number" step="any" min="0" id="weight_value" name="weight_value" value="<?= e($val('weight_value', $article?->weightValue)) ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                <select name="weight_unit" aria-label="Einheit" class="w-24 rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-700">
                    <option value="g" <?= $selectedUnit === 'g' ? 'selected' : '' ?>>g</option>
                    <option value="kg" <?= $selectedUnit === 'kg' ? 'selected' : '' ?>>kg</option>
                </select>
            </div>
        </div>
    </div>

    <div>
        <label for="description" class="<?= $label ?>">Beschreibung</label>
        <textarea id="description" name="description" rows="3" class="<?= $input ?>"><?= e($val('description', $article?->description)) ?></textarea>
    </div>

    <div>
        <label for="document_short_text" class="<?= $label ?>">Kurztext für Dokumente</label>
        <input type="text" id="document_short_text" name="document_short_text" maxlength="500" value="<?= e((string) $val('document_short_text', $article?->documentShortText)) ?>" class="<?= $input ?>">
        <?= $err('documentShortText') ?>
    </div>

    <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
        <p class="font-medium text-slate-700 dark:text-slate-300">Für Leitungen fest eingestellt</p>
        <ul class="mt-1 list-disc space-y-0.5 pl-4">
            <li>Hersteller „<?= e($genericManufacturer?->name ?? 'Generisch') ?>“</li>
            <li>Immer Zubehör – wird nicht als eigener Posten abgerechnet (kein Preis pro Einsatztag) und ist nicht einzeln vermietbar</li>
            <li>Kein Einkaufspreis, keine Firmware, nicht in ein Rack einbaubar</li>
            <li>Elektroprüfung, wenn der Leitungstyp sie verlangt (ohne Schutzklasse)</li>
        </ul>
    </div>

    <div class="form-actions flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= $editingId !== null ? 'Speichern' : 'Leitung anlegen' ?></button>
        <a href="<?= $article !== null ? '/artikel/' . e($article->id) : '/artikel' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
    </div>
</form>

<script src="/assets/js/cable-fields.js"></script>
<script>
(function () {
    var section = document.getElementById('cable_section');
    var type = document.getElementById('cable_type_id');
    var info = document.getElementById('cable_inspection_info');
    var config = {};
    try { config = JSON.parse(section.getAttribute('data-cable-config') || '{}'); } catch (e) { /* leer */ }
    function update() {
        var entry = type.value ? config[type.value] : null;
        info.textContent = !entry ? '' : (entry.inspection
            ? 'Elektroprüfung: erforderlich (vom Leitungstyp vorgegeben).'
            : 'Elektroprüfung: nicht erforderlich (vom Leitungstyp vorgegeben).');
    }
    type.addEventListener('change', update);
    update();
})();
</script>
