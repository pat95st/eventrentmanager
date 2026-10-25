<?php
/** @var \App\Domain\Rental\Rental $rental */
/** @var \App\Domain\MinuteProgram\MinuteProgram|null $program */
/** @var \App\Domain\MinuteProgram\MinuteProgramTemplate[] $templates */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use App\Infrastructure\Http\Csrf;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
$isEditing = $program !== null;
$action = $isEditing ? '/minutenprogramme/' . e($program->id) : '/veranstaltungen/' . e($rental->id) . '/minutenprogramme';
?>

<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('minute_programs.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">
        <?= $isEditing ? e(t('minute_programs.edit')) : e(t('minute_programs.new')) ?>
    </h1>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e($rental->eventName) ?></p>
</div>

<form method="post" action="<?= $action ?>" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">

    <?php if (!$isEditing && $templates !== []): ?>
    <div>
        <label for="template_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.form.template')) ?></label>
        <select id="template_id" name="template_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <option value=""><?= e(t('minute_programs.form.template_none')) ?></option>
            <?php foreach ($templates as $template): ?>
                <option value="<?= e($template->id) ?>"><?= e($template->name) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('minute_programs.form.template_hint')) ?></p>
    </div>
    <?php endif; ?>

    <div>
        <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.form.name')) ?></label>
        <input type="text" id="name" name="name" required value="<?= e($val('name', $program?->name)) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="room" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.form.room')) ?></label>
            <input type="text" id="room" name="room" value="<?= e($val('room', $program?->room)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <div>
            <label for="start_at" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.form.start_at')) ?></label>
            <input type="datetime-local" id="start_at" name="start_at" required
                   value="<?= e($val('start_at', $program?->startAt->format('Y-m-d\TH:i'))) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['startAt']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['startAt'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['startAt']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="flex items-center gap-2">
        <input type="checkbox" id="jingle_before" name="jingle_before" value="1" <?= ($program?->jingleBefore ?? false) ? 'checked' : '' ?>
               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        <label for="jingle_before" class="text-sm text-slate-700 dark:text-slate-300"><?= e(t('minute_programs.form.jingle_before')) ?></label>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="handheld_mic_count" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.form.handheld_mic_count')) ?></label>
            <input type="number" min="0" id="handheld_mic_count" name="handheld_mic_count" value="<?= e((string) ($program?->handheldMicCount ?? 0)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <div>
            <label for="headset_mic_count" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.form.headset_mic_count')) ?></label>
            <input type="number" min="0" id="headset_mic_count" name="headset_mic_count" value="<?= e((string) ($program?->headsetMicCount ?? 0)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
    </div>

    <div class="flex justify-end gap-2 pt-2">
        <a href="/veranstaltungen/<?= e($rental->id) ?>" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.cancel')) ?></a>
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
    </div>
</form>
