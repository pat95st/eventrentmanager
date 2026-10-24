<?php
/** @var \App\Application\MinuteProgram\Query\MinuteProgramTemplateDetail $detail */

use App\Infrastructure\Http\Csrf;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$template = $detail->template;
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/minutenprogramm-vorlagen" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('minute_program_templates.form.back')) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($template->name) ?></h1>
    </div>
    <div class="flex gap-2">
        <a href="/minutenprogramm-vorlagen/<?= e($template->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
        <form method="post" action="/minutenprogramm-vorlagen/<?= e($template->id) ?>/loeschen" onsubmit="return confirm('<?= e(t('minute_program_templates.delete_confirm')) ?>')">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400"><?= e(t('common.delete')) ?></button>
        </form>
    </div>
</div>

<div class="mb-6 overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
            <tr>
                <th class="px-4 py-2"><?= e(t('minute_programs.show.col_name')) ?></th>
                <th class="px-4 py-2"><?= e(t('minute_programs.show.col_duration')) ?></th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($detail->items as $item): ?>
            <tr>
                <td class="px-4 py-2 align-top">
                    <div class="font-medium text-slate-900 dark:text-slate-100"><?= e($item->name) ?></div>
                    <?php if ($item->description !== null): ?><div class="text-xs text-slate-500 dark:text-slate-400"><?= e($item->description) ?></div><?php endif; ?>
                </td>
                <td class="px-4 py-2 align-top text-xs text-slate-500"><?= $item->durationMinutes !== null ? e((string) $item->durationMinutes) . ' min' : '–' ?></td>
                <td class="px-4 py-2 align-top text-right">
                    <div class="flex items-center justify-end gap-1">
                        <form method="post" action="/minutenprogramm-vorlagen-punkte/<?= e($item->id) ?>/verschieben">
                            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                            <input type="hidden" name="template_id" value="<?= e($template->id) ?>">
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">↑</button>
                        </form>
                        <form method="post" action="/minutenprogramm-vorlagen-punkte/<?= e($item->id) ?>/verschieben">
                            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                            <input type="hidden" name="template_id" value="<?= e($template->id) ?>">
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">↓</button>
                        </form>
                        <form method="post" action="/minutenprogramm-vorlagen-punkte/<?= e($item->id) ?>/loeschen" onsubmit="return confirm('<?= e(t('minute_programs.show.delete_item_confirm')) ?>')">
                            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                            <input type="hidden" name="template_id" value="<?= e($template->id) ?>">
                            <button type="submit" class="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400">×</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="border-t border-slate-200 p-4 dark:border-slate-800">
        <form method="post" action="/minutenprogramm-vorlagen/<?= e($template->id) ?>/punkte" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <input type="text" name="name" required placeholder="<?= e(t('minute_programs.item_form.name')) ?>" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
            <input type="number" min="0" name="duration_minutes" placeholder="<?= e(t('minute_programs.item_form.duration')) ?>" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
            <input type="text" name="description" placeholder="<?= e(t('minute_programs.item_form.description')) ?>" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('minute_programs.show.add_item')) ?></button>
        </form>
    </div>
</div>
