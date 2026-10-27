<?php
/** @var array<string, string> $errors */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <a href="/kunden" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('customers.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('customers.import.title')) ?></h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e(t('customers.import.subtitle')) ?></p>
</div>

<div class="max-w-2xl space-y-5">
    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('customers.import.step1.heading')) ?></h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e(t('customers.import.step1.hint')) ?></p>
        <a href="/kunden/import/vorlage" class="mt-3 inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('customers.import.step1.download')) ?>
        </a>
    </div>

    <form method="post" action="/kunden/import" enctype="multipart/form-data" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

        <div>
            <h2 class="text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('customers.import.step2.heading')) ?></h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e(t('customers.import.step2.hint')) ?></p>
        </div>

        <div>
            <label for="file" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.import.field.file')) ?></label>
            <input type="file" id="file" name="file" accept=".csv,text/csv" required
                   class="mt-1 w-full rounded-md border <?= isset($errors['file']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-900">
            <?php if (isset($errors['file'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['file']) ?></p><?php endif; ?>
        </div>

        <?php
            // Alle übrigen Fehler sind zeilenbezogen ("Zeile N", siehe
            // ImportCustomersFromCsvHandler) — eigene Liste statt eines
            // einzelnen Feldfehlers, da bei einem fehlerhaften Import
            // mehrere Zeilen gleichzeitig betroffen sein können.
            $rowErrors = array_diff_key($errors, ['file' => null]);
        ?>
        <?php if ($rowErrors !== []): ?>
            <div class="rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950/40">
                <p class="text-xs font-medium text-red-700 dark:text-red-400"><?= e(t('customers.import.errors_heading')) ?></p>
                <ul class="mt-1 space-y-0.5 text-xs text-red-600 dark:text-red-400">
                    <?php foreach ($rowErrors as $label => $message): ?>
                        <li><strong><?= e($label) ?>:</strong> <?= e($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <?= e(t('customers.import.submit')) ?>
            </button>
            <a href="/kunden" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</div>
