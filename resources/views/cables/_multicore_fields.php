<?php
/**
 * Analog multicore fields of a cable (migration 156). Only shown (by
 * cable-fields.js) when the selected cable type is an analog multicore.
 *
 * @var \App\Domain\Cable\CableMulticore|null $multicore saved details
 * @var array<string, mixed> $old
 * @var array<string, string> $errors
 * @var bool $visible initially visible (selected type is a multicore)
 */

use function App\Infrastructure\Http\e;

$count = fn (string $field, ?int $saved) => (string) (array_key_exists($field, $old) ? ($old[$field] ?? '') : ($saved !== null ? (string) $saved : ''));
$flag = fn (string $field, ?bool $saved) => array_key_exists($field, $old) ? (bool) $old[$field] : (bool) $saved;
$err = fn (string $key) => isset($errors[$key]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$key]) . '</p>' : '';
$inputClass = fn (string $key) => 'mt-1 w-full rounded-md border ' . (isset($errors[$key]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700') . ' px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500';
?>
<fieldset id="cable_multicore_fields" data-cable-multicore-fields class="mt-4 border-t border-brand-200 pt-4 dark:border-brand-800 <?= $visible ? '' : 'hidden' ?>">
    <legend class="sr-only">Analoges Multicore</legend>
    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Analoges Multicore</p>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Anzahl der Sends und Returns erfassen (leere Felder zählen als 0). Analoge Multicores brauchen keine Elektroprüfung.</p>
    <div class="mt-3 grid grid-cols-2 gap-4 sm:max-w-md">
        <?php foreach (['multicore_sends' => ['Sends', $multicore?->sends], 'multicore_returns' => ['Returns', $multicore?->returns]] as $field => [$fieldLabel, $saved]): ?>
            <div>
                <label for="<?= $field ?>" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e($fieldLabel) ?></label>
                <input type="number" min="0" max="<?= \App\Domain\Cable\CableMulticore::MAX_CHANNELS ?>" step="1" inputmode="numeric" placeholder="0"
                       id="<?= $field ?>" name="<?= $field ?>" value="<?= e($count($field, $saved)) ?>" data-cable-multicore-count
                       class="<?= $inputClass($field) ?>">
                <?= $err($field) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2">
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="multicore_on_drum" value="1" <?= $flag('multicore_on_drum', $multicore?->onDrum) ? 'checked' : '' ?>>
            Auf Trommel
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="multicore_has_harting" value="1" <?= $flag('multicore_has_harting', $multicore?->hasHarting) ? 'checked' : '' ?>>
            Mit Harting-Steckverbinder
        </label>
    </div>
</fieldset>
