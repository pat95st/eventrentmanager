<?php
/** @var \App\Domain\Dashboard\DashboardTemplate[] $templates */
/** @var array<string, \App\Application\Dashboard\DashboardTile> $tiles alle Kacheln */

use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-2">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.dashboard_templates.title')) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.dashboard_templates.subtitle')) ?></p>
    </div>
    <a href="/einstellungen/dashboard-vorlagen/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
        <?= e(t('settings.dashboard_templates.new')) ?>
    </a>
</div>

<?php if ($templates === []): ?>
    <p class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
        <?= e(t('settings.dashboard_templates.empty')) ?>
    </p>
<?php else: ?>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($templates as $template): ?>
            <div class="flex flex-col rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e($template->name) ?></h2>
                <?php if ($template->description !== null): ?>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"><?= e($template->description) ?></p>
                <?php endif; ?>
                <?= View::render('dashboard/_layout_summary', ['tiles' => $tiles, 'layout' => $template->layout->only(array_keys($tiles))]) ?>
                <div class="mt-auto flex items-center gap-4 pt-4">
                    <a href="/einstellungen/dashboard-vorlagen/<?= e($template->id) ?>/bearbeiten" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('common.edit')) ?></a>
                    <form method="post" action="/einstellungen/dashboard-vorlagen/<?= e($template->id) ?>/loeschen" data-confirm="<?= e(t('settings.dashboard_templates.confirm_delete', ['name' => $template->name])) ?>" data-confirm-style="danger">
                        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                        <button type="submit" class="text-sm font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
