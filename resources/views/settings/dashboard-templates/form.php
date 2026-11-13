<?php
/** @var \App\Domain\Dashboard\DashboardTemplate|null $template NULL = neue Vorlage */
/** @var string $name */
/** @var string $description */
/** @var array<string, \App\Application\Dashboard\DashboardTile> $tiles alle Kacheln */
/** @var \App\Domain\Dashboard\DashboardLayout $layout */
/** @var array<string, string> $errors */

use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$inputClass = fn (string $errorKey) => 'mt-1 w-full rounded-md border ' . (isset($errors[$errorKey]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700') . ' px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500';
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">
        <?= e(t($template !== null ? 'settings.dashboard_templates.form.title_edit' : 'settings.dashboard_templates.form.title_create')) ?>
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.dashboard_templates.form.subtitle')) ?></p>
</div>

<form method="post" action="<?= $template !== null ? '/einstellungen/dashboard-vorlagen/' . e($template->id) : '/einstellungen/dashboard-vorlagen' ?>"
      class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:p-6">
    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div>
            <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.dashboard_templates.form.name')) ?> *</label>
            <input type="text" id="name" name="name" required maxlength="100" value="<?= e($name) ?>"
                   placeholder="<?= e(t('settings.dashboard_templates.form.name_placeholder')) ?>" class="<?= $inputClass('name') ?>">
            <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="lg:col-span-2">
            <label for="description" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.dashboard_templates.form.description')) ?></label>
            <input type="text" id="description" name="description" maxlength="255" value="<?= e($description) ?>"
                   placeholder="<?= e(t('settings.dashboard_templates.form.description_placeholder')) ?>" class="<?= $inputClass('description') ?>">
            <?php if (isset($errors['description'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['description']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="mt-6">
        <?php if (isset($errors['layout'])): ?><p class="mb-2 text-sm text-red-600 dark:text-red-400"><?= e($errors['layout']) ?></p><?php endif; ?>
        <?= View::render('dashboard/_layout_editor', ['tiles' => $tiles, 'layout' => $layout, 'showPermissions' => true]) ?>
    </div>

    <div class="mt-6 flex items-center gap-3">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
        <a href="/einstellungen/dashboard-vorlagen" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>
