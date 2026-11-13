<?php
/** @var array<string, \App\Application\Dashboard\DashboardTile> $tiles Kacheln, die der Benutzer sehen darf */
/** @var \App\Domain\Dashboard\DashboardLayout $layout seine aktuelle Anordnung */
/** @var bool $isCustomized weicht von der Standardanordnung ab */
/** @var list<array{template: \App\Domain\Dashboard\DashboardTemplate, layout: \App\Domain\Dashboard\DashboardLayout}> $templates Vorlagen, jeweils beschränkt auf die erlaubten Kacheln */
/** @var bool $isAdmin */

use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-2">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('profile.dashboard.title')) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.dashboard.subtitle')) ?></p>
    </div>
    <a href="/" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
        <?= e(t('profile.dashboard.to_dashboard')) ?>
    </a>
</div>

<?php if ($tiles === []): ?>
    <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
        <?= e(t('profile.dashboard.no_tiles')) ?>
    </div>
<?php else: ?>
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:p-6">
        <form id="dashboard-layout-form" method="post" action="/profil/dashboard">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <?= View::render('dashboard/_layout_editor', ['tiles' => $tiles, 'layout' => $layout]) ?>
        </form>
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <button type="submit" form="dashboard-layout-form" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <?= e(t('profile.dashboard.save')) ?>
            </button>
            <?php if ($isCustomized): ?>
                <form method="post" action="/profil/dashboard/zuruecksetzen" data-confirm="<?= e(t('profile.dashboard.reset_confirm')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                    <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
                        <?= e(t('profile.dashboard.reset')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($templates !== [] || $isAdmin): ?>
        <section class="mt-8" aria-labelledby="vorlagen-titel">
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h2 id="vorlagen-titel" class="text-base font-semibold text-slate-900 dark:text-slate-100"><?= e(t('profile.dashboard.templates.title')) ?></h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.dashboard.templates.subtitle')) ?></p>
                </div>
                <?php if ($isAdmin): ?>
                    <a href="/einstellungen/dashboard-vorlagen" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400"><?= e(t('profile.dashboard.templates.manage')) ?></a>
                <?php endif; ?>
            </div>
            <?php if ($templates === []): ?>
                <p class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"><?= e(t('profile.dashboard.templates.empty')) ?></p>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($templates as $entry): ?>
                        <div class="flex flex-col rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e($entry['template']->name) ?></h3>
                            <?php if ($entry['template']->description !== null): ?>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"><?= e($entry['template']->description) ?></p>
                            <?php endif; ?>
                            <?= View::render('dashboard/_layout_summary', ['tiles' => $tiles, 'layout' => $entry['layout']]) ?>
                            <form method="post" action="/profil/dashboard/vorlage/<?= e($entry['template']->id) ?>" class="mt-auto pt-4" data-confirm="<?= e(t('profile.dashboard.templates.apply_confirm', ['name' => $entry['template']->name])) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                                <button type="submit" class="rounded-md border border-brand-600 px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-950/40">
                                    <?= e(t('profile.dashboard.templates.apply')) ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
