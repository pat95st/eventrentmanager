<?php
/**
 * Formular für Sets (Migration 113): Grunddaten, Inhalt (Artikel/Bundles
 * mit Menge), zeitliche Regeln und Upgrades. Zeilen werden per JS ergänzt;
 * die Indizes in den Feldnamen (items[7][article_id]) sind beliebig.
 *
 * @var \App\Application\ArticleSet\Query\ArticleSetDetail|null $detail
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var callable(string): string $labelFor
 */

use App\Domain\ArticleSet\ArticleSetRule;
use App\Domain\ArticleSet\ArticleSetRuleType;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\renderLiveSearchField;
use function App\Infrastructure\Http\t;

$set = $detail?->set;
$hasOld = $old !== [];
$val = fn (string $key, ?string $fallback = null) => $hasOld ? (string) ($old[$key] ?? '') : ($fallback ?? '');
$isActive = $hasOld ? isset($old['is_active']) : ($set?->isActive ?? true);

// Zeilen: nach Validierungsfehler aus $old, sonst aus dem gespeicherten Set.
if ($hasOld) {
    $itemRows = array_values(array_filter((array) ($old['items'] ?? []), 'is_array'));
    foreach ($itemRows as &$row) {
        $row['label'] = trim((string) ($row['article_id'] ?? '')) !== '' ? $labelFor((string) $row['article_id']) : '';
    }
    unset($row);
    $ruleRows = array_values(array_filter((array) ($old['rules'] ?? []), 'is_array'));
    $upgradeRows = array_values(array_filter((array) ($old['upgrades'] ?? []), 'is_array'));
    foreach ($upgradeRows as &$upgrade) {
        $upgrade['items'] = array_values(array_filter((array) ($upgrade['items'] ?? []), 'is_array'));
        foreach ($upgrade['items'] as &$row) {
            $row['label'] = trim((string) ($row['article_id'] ?? '')) !== '' ? $labelFor((string) $row['article_id']) : '';
        }
        unset($row);
    }
    unset($upgrade);
} else {
    $itemRows = array_map(static fn (array $item) => ['article_id' => $item['articleId'], 'label' => $item['label'], 'quantity' => (string) $item['quantity']], $detail?->items ?? []);
    $ruleRows = array_map(static fn (ArticleSetRule $rule) => [
        'type' => $rule->type->value,
        'from_month_day' => $rule->fromMonthDay !== null ? ArticleSetRule::formatMonthDay($rule->fromMonthDay) : '',
        'to_month_day' => $rule->toMonthDay !== null ? ArticleSetRule::formatMonthDay($rule->toMonthDay) : '',
        'from_date' => $rule->fromDate?->format('Y-m-d') ?? '',
        'to_date' => $rule->toDate?->format('Y-m-d') ?? '',
    ], $detail?->rules ?? []);
    $upgradeRows = array_map(static fn (array $upgrade) => [
        'id' => $upgrade['id'],
        'name' => $upgrade['name'],
        'price' => $upgrade['price'],
        'items' => array_map(static fn (array $item) => ['article_id' => $item['articleId'], 'label' => $item['label'], 'quantity' => (string) $item['quantity']], $upgrade['items']),
    ], $detail?->upgrades ?? []);
}

$inputClass = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
$errorText = fn (string $key) => isset($errors[$key]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$key]) . '</p>' : '';

$renderItemRow = function (string $name, string $index, array $row) use ($inputClass): void {
    ?>
    <div data-set-row class="flex flex-wrap items-end gap-2">
        <div class="min-w-[220px] flex-1">
            <?php renderLiveSearchField($name . '[' . $index . '][article_id]', '/artikel/suche/vermietung', ($row['article_id'] ?? '') !== '' ? (string) $row['article_id'] : null, ($row['label'] ?? '') !== '' ? (string) $row['label'] : null, t('sets.form.article_placeholder'), null, 'select', false, false); ?>
        </div>
        <input type="number" name="<?= e($name . '[' . $index . '][quantity]') ?>" min="1" max="999" value="<?= e((string) ($row['quantity'] ?? '1')) ?>" title="<?= e(t('sets.form.quantity')) ?>"
               class="w-20 rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-700">
        <button type="button" data-remove-row class="px-2 py-2 text-sm text-red-600 hover:underline dark:text-red-400" title="<?= e(t('common.delete')) ?>">&times;</button>
    </div>
    <?php
};

$renderRuleRow = function (string $index, array $row) use ($inputClass): void {
    $type = (string) ($row['type'] ?? ArticleSetRuleType::Weekends->value);
    ?>
    <div data-set-row data-rule-row class="flex flex-wrap items-end gap-2">
        <select name="rules[<?= e($index) ?>][type]" data-rule-type class="rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
            <?php foreach (ArticleSetRuleType::cases() as $case): ?>
                <option value="<?= e($case->value) ?>" <?= $type === $case->value ? 'selected' : '' ?>><?= e($case->label()) ?></option>
            <?php endforeach; ?>
        </select>
        <span data-rule-yearly class="flex flex-wrap items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
            <?= e(t('sets.form.rule_from')) ?>
            <input type="text" name="rules[<?= e($index) ?>][from_month_day]" value="<?= e((string) ($row['from_month_day'] ?? '')) ?>" placeholder="01.05." class="w-20 rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-700">
            <?= e(t('sets.form.rule_to')) ?>
            <input type="text" name="rules[<?= e($index) ?>][to_month_day]" value="<?= e((string) ($row['to_month_day'] ?? '')) ?>" placeholder="30.09." class="w-20 rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-700">
        </span>
        <span data-rule-range class="flex flex-wrap items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
            <?= e(t('sets.form.rule_from')) ?>
            <input type="date" name="rules[<?= e($index) ?>][from_date]" value="<?= e((string) ($row['from_date'] ?? '')) ?>" class="rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-700">
            <?= e(t('sets.form.rule_to')) ?>
            <input type="date" name="rules[<?= e($index) ?>][to_date]" value="<?= e((string) ($row['to_date'] ?? '')) ?>" class="rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-700">
        </span>
        <button type="button" data-remove-row class="px-2 py-2 text-sm text-red-600 hover:underline dark:text-red-400" title="<?= e(t('common.delete')) ?>">&times;</button>
    </div>
    <?php
};

$renderUpgrade = function (string $index, array $upgrade) use ($renderItemRow, $inputClass): void {
    ?>
    <div data-set-row data-upgrade class="space-y-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <input type="hidden" name="upgrades[<?= e($index) ?>][id]" value="<?= e((string) ($upgrade['id'] ?? '')) ?>">
        <div class="flex flex-wrap items-end gap-2">
            <div class="min-w-[220px] flex-1">
                <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('sets.form.upgrade_name')) ?></label>
                <input type="text" name="upgrades[<?= e($index) ?>][name]" value="<?= e((string) ($upgrade['name'] ?? '')) ?>" placeholder="<?= e(t('sets.form.upgrade_name_placeholder')) ?>" class="mt-1 <?= $inputClass ?>">
            </div>
            <div class="w-32">
                <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('sets.form.upgrade_price')) ?></label>
                <input type="number" step="0.01" min="0" name="upgrades[<?= e($index) ?>][price]" value="<?= e((string) ($upgrade['price'] ?? '')) ?>" class="mt-1 <?= $inputClass ?>">
            </div>
            <button type="button" data-remove-row class="px-2 py-2 text-sm text-red-600 hover:underline dark:text-red-400"><?= e(t('sets.form.upgrade_remove')) ?></button>
        </div>
        <div data-upgrade-items class="space-y-2" data-name="upgrades[<?= e($index) ?>][items]">
            <?php foreach ($upgrade['items'] ?? [] as $j => $row): ?>
                <?php $renderItemRow('upgrades[' . $index . '][items]', (string) $j, $row); ?>
            <?php endforeach; ?>
        </div>
        <button type="button" data-add-upgrade-item class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">+ <?= e(t('sets.form.upgrade_add_article')) ?></button>
    </div>
    <?php
};
?>

<div class="mb-6">
    <a href="<?= $set !== null ? '/sets/' . e($set->id) : '/sets' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('articles.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($set !== null ? t('sets.edit') : t('sets.new')) ?></h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e(t('sets.subtitle')) ?></p>
</div>

<form method="post" action="<?= $set !== null ? '/sets/' . e($set->id) : '/sets' ?>" class="space-y-6 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="space-y-4">
        <div>
            <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('sets.form.name')) ?></label>
            <input type="text" id="name" name="name" required value="<?= e($val('name', $set?->name)) ?>" placeholder="<?= e(t('sets.form.name_placeholder')) ?>" class="mt-1 <?= $inputClass ?>">
            <?= $errorText('name') ?>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label for="price" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('sets.form.price')) ?></label>
                <input type="number" step="0.01" min="0" id="price" name="price" required value="<?= e($val('price', $set?->price)) ?>" class="mt-1 <?= $inputClass ?>">
                <?= $errorText('price') ?>
            </div>
            <div>
                <label for="deployment_days" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('sets.form.deployment_days')) ?></label>
                <input type="number" min="1" max="365" id="deployment_days" name="deployment_days" required value="<?= e($val('deployment_days', $set !== null ? (string) $set->deploymentDays : '')) ?>" class="mt-1 <?= $inputClass ?>">
                <?php if (isset($errors['deploymentDays'])): ?><?= $errorText('deploymentDays') ?><?php else: ?><p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('sets.form.deployment_days_hint')) ?></p><?php endif; ?>
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                    <?= e(t('sets.form.is_active')) ?>
                </label>
            </div>
        </div>
        <div>
            <label for="description" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('sets.form.description')) ?></label>
            <textarea id="description" name="description" rows="3" class="mt-1 <?= $inputClass ?>"><?= e($val('description', $set?->description)) ?></textarea>
        </div>
    </div>

    <section class="space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('sets.form.items_title')) ?></h2>
                <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('sets.form.items_hint')) ?></p>
            </div>
            <button type="button" data-add-item class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">+ <?= e(t('sets.form.add_article')) ?></button>
        </div>
        <?= $errorText('items') ?>
        <div data-items class="space-y-2">
            <?php foreach ($itemRows as $i => $row): ?>
                <?php $renderItemRow('items', (string) $i, $row); ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('sets.form.rules_title')) ?></h2>
                <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('sets.form.rules_hint')) ?></p>
            </div>
            <button type="button" data-add-rule class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">+ <?= e(t('sets.form.add_rule')) ?></button>
        </div>
        <?= $errorText('rules') ?>
        <div data-rules class="space-y-2">
            <?php foreach ($ruleRows as $i => $row): ?>
                <?php $renderRuleRow((string) $i, $row); ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('sets.form.upgrades_title')) ?></h2>
                <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('sets.form.upgrades_hint')) ?></p>
            </div>
            <button type="button" data-add-upgrade class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">+ <?= e(t('sets.form.add_upgrade')) ?></button>
        </div>
        <?= $errorText('upgrades') ?>
        <div data-upgrades class="space-y-3">
            <?php foreach ($upgradeRows as $i => $upgrade): ?>
                <?php $renderUpgrade((string) $i, $upgrade); ?>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="form-actions flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
        <a href="<?= $set !== null ? '/sets/' . e($set->id) : '/sets' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>

<template data-template-item><?php $renderItemRow('items', '__I__', []); ?></template>
<template data-template-rule><?php $renderRuleRow('__I__', []); ?></template>
<template data-template-upgrade><?php $renderUpgrade('__I__', ['items' => [['article_id' => '', 'label' => '', 'quantity' => '1']]]); ?></template>
<template data-template-upgrade-item><?php $renderItemRow('__NAME__', '__J__', []); ?></template>

<script>
    (function () {
        var counter = Date.now();
        function fromTemplate(selector, replacements) {
            var html = document.querySelector(selector).innerHTML;
            Object.keys(replacements).forEach(function (key) { html = html.split(key).join(replacements[key]); });
            var wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            return wrapper.firstElementChild;
        }
        function append(container, element) {
            container.appendChild(element);
            if (window.LiveSearch) { window.LiveSearch.init(element); }
            syncRules(element);
        }
        function syncRules(root) {
            (root.matches && root.matches('[data-rule-row]') ? [root] : Array.prototype.slice.call(root.querySelectorAll('[data-rule-row]'))).forEach(function (row) {
                var type = row.querySelector('[data-rule-type]').value;
                row.querySelector('[data-rule-yearly]').classList.toggle('hidden', type !== 'yearly_period');
                row.querySelector('[data-rule-range]').classList.toggle('hidden', type !== 'date_range');
            });
        }

        var items = document.querySelector('[data-items]');
        var rules = document.querySelector('[data-rules]');
        var upgrades = document.querySelector('[data-upgrades]');

        document.querySelector('[data-add-item]').addEventListener('click', function () {
            append(items, fromTemplate('[data-template-item]', { '__I__': String(counter++) }));
        });
        document.querySelector('[data-add-rule]').addEventListener('click', function () {
            append(rules, fromTemplate('[data-template-rule]', { '__I__': String(counter++) }));
        });
        document.querySelector('[data-add-upgrade]').addEventListener('click', function () {
            append(upgrades, fromTemplate('[data-template-upgrade]', { '__I__': String(counter++) }));
        });

        document.addEventListener('click', function (event) {
            var remove = event.target.closest('[data-remove-row]');
            if (remove) { remove.closest('[data-set-row]').remove(); return; }
            var addUpgradeItem = event.target.closest('[data-add-upgrade-item]');
            if (addUpgradeItem) {
                var container = addUpgradeItem.closest('[data-upgrade]').querySelector('[data-upgrade-items]');
                append(container, fromTemplate('[data-template-upgrade-item]', { '__NAME__': container.getAttribute('data-name'), '__J__': String(counter++) }));
            }
        });
        document.addEventListener('change', function (event) {
            if (event.target.matches('[data-rule-type]')) { syncRules(event.target.closest('[data-rule-row]')); }
        });

        syncRules(document);
        if (items.children.length === 0) { document.querySelector('[data-add-item]').click(); }
    })();
</script>
