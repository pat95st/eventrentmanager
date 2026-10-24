<?php

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('bank_import.title')) ?></h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e(t('bank_import.intro')) ?></p>
</div>

<form
    method="post"
    action="/zahlungsabgleich/importieren"
    enctype="multipart/form-data"
    class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <label for="file" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('bank_import.form.file')) ?></label>
        <input type="file" id="file" name="file" accept=".sta,.mt940,.xml,.txt" required
               class="mt-1 block w-full text-sm text-slate-700 dark:text-slate-300">
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('bank_import.form.file_hint')) ?></p>
    </div>

    <button type="submit" class="inline-flex items-center rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
        <?= e(t('bank_import.form.submit')) ?>
    </button>
</form>
