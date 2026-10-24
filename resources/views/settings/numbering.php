<?php
/** @var \App\Application\Numbering\Query\NumberingSettingsOverviewEntry[] $entries */
/** @var array<string, string> $errors */

use App\Domain\Numbering\NumberingScheme;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.numbering.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.numbering.subtitle')) ?></p>
</div>

<form method="post" action="/einstellungen/nummerierung" class="max-w-3xl space-y-6">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <?php foreach ($entries as $entry): ?>
        <?php
        $type = $entry->settings->documentType;
        $fieldKey = $type->value;
        $nextNumberError = $errors[$fieldKey . '.next_number'] ?? null;
        $digitCountError = $errors[$fieldKey . '.digit_count'] ?? null;
        ?>
        <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e($type->label()) ?></h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.numbering.field.scheme')) ?></label>
                    <select name="entries[<?= e($fieldKey) ?>][scheme]" data-scheme-select data-for="<?= e($fieldKey) ?>"
                            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                        <?php foreach (NumberingScheme::cases() as $scheme): ?>
                            <option value="<?= e($scheme->value) ?>" <?= $entry->settings->scheme === $scheme ? 'selected' : '' ?>><?= e($scheme->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.numbering.field.prefix')) ?></label>
                    <input type="text" name="entries[<?= e($fieldKey) ?>][prefix]" value="<?= e((string) $entry->settings->prefix) ?>"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2" data-separator-row="<?= e($fieldKey) ?>" style="<?= $entry->settings->scheme === NumberingScheme::Yearly ? '' : 'display:none' ?>">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.numbering.field.separator')) ?></label>
                    <input type="text" name="entries[<?= e($fieldKey) ?>][separator]" value="<?= e($entry->settings->separator) ?>" maxlength="4"
                           class="mt-1 w-24 rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.numbering.field.digit_count')) ?></label>
                    <input type="number" name="entries[<?= e($fieldKey) ?>][digit_count]" min="1" max="10" step="1"
                           value="<?= e((string) $entry->settings->digitCount) ?>"
                           class="mt-1 w-32 rounded-md border <?= $digitCountError ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if ($digitCountError): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($digitCountError) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.numbering.field.next_number')) ?></label>
                    <input type="number" name="entries[<?= e($fieldKey) ?>][next_number]" min="1" step="1"
                           placeholder="<?= e((string) $entry->nextNumber) ?>"
                           class="mt-1 w-32 rounded-md border <?= $nextNumberError ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if ($nextNumberError): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($nextNumberError) ?></p><?php endif; ?>
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.numbering.field.next_number_hint')) ?></p>
                </div>
            </div>

            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                <?= e(t('settings.numbering.preview', ['example' => $entry->previewFormatted])) ?>
            </p>
        </div>
    <?php endforeach; ?>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>

<script>
document.querySelectorAll('[data-scheme-select]').forEach(function (select) {
    var key = select.getAttribute('data-for');
    var row = document.querySelector('[data-separator-row="' + key + '"]');
    if (!row) { return; }
    var sync = function () { row.style.display = select.value === 'yearly' ? '' : 'none'; };
    select.addEventListener('change', sync);
    sync();
});
</script>
