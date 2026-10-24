<?php
/** @var \App\Domain\MinuteProgram\MinuteProgramTemplate|null $template */
/** @var array<string, string> $errors */

use App\Infrastructure\Http\Csrf;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$isEditing = $template !== null;
$action = $isEditing ? '/minutenprogramm-vorlagen/' . e($template->id) : '/minutenprogramm-vorlagen';
?>

<div class="mb-6">
    <a href="/minutenprogramm-vorlagen" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('minute_program_templates.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $isEditing ? e(t('minute_program_templates.edit')) : e(t('minute_program_templates.new')) ?></h1>
</div>

<form method="post" action="<?= $action ?>" class="max-w-xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">

    <div>
        <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_program_templates.form.name')) ?></label>
        <input type="text" id="name" name="name" required value="<?= e($template?->name ?? '') ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <div class="flex items-center gap-2">
        <input type="checkbox" id="jingle_before" name="jingle_before" value="1" <?= ($template?->jingleBefore ?? false) ? 'checked' : '' ?>
               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        <label for="jingle_before" class="text-sm text-slate-700 dark:text-slate-300"><?= e(t('minute_programs.form.jingle_before')) ?></label>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="handheld_mic_count" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.form.handheld_mic_count')) ?></label>
            <input type="number" min="0" id="handheld_mic_count" name="handheld_mic_count" value="<?= e((string) ($template?->handheldMicCount ?? 0)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
        </div>
        <div>
            <label for="headset_mic_count" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.form.headset_mic_count')) ?></label>
            <input type="number" min="0" id="headset_mic_count" name="headset_mic_count" value="<?= e((string) ($template?->headsetMicCount ?? 0)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
        </div>
    </div>

    <div class="flex justify-end gap-2 pt-2">
        <a href="/minutenprogramm-vorlagen" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.cancel')) ?></a>
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
    </div>
</form>
