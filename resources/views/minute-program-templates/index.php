<?php
/** @var \App\Domain\MinuteProgram\MinuteProgramTemplate[] $templates */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('minute_program_templates.title')) ?></h1>
    <a href="/minutenprogramm-vorlagen/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('minute_program_templates.new')) ?></a>
</div>

<?php if ($templates === []): ?>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('minute_program_templates.empty')) ?></p>
<?php else: ?>
<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
            <tr>
                <th class="px-4 py-2"><?= e(t('minute_program_templates.col_name')) ?></th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($templates as $template): ?>
            <tr>
                <td class="px-4 py-2"><a href="/minutenprogramm-vorlagen/<?= e($template->id) ?>" class="font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e($template->name) ?></a></td>
                <td class="px-4 py-2 text-right">
                    <a href="/minutenprogramm-vorlagen/<?= e($template->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.edit')) ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
