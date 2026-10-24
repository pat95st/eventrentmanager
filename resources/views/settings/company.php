<?php
/** @var \App\Domain\Settings\CompanySettings $settings */
/** @var array<string, string> $errors */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.company.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('settings.company.subtitle')) ?>
    </p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:items-start">
<form method="post" action="/einstellungen/firma" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.company.field.name')) ?></label>
        <input type="text" id="name" name="name"
               value="<?= e($settings->name) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="brand_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.company.field.brand_name')) ?></label>
        <p class="mb-2 mt-0.5 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.company.field.brand_name_hint')) ?></p>
        <input type="text" id="brand_name" name="brand_name"
               value="<?= e($settings->brandName ?? '') ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['brandName']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['brandName'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['brandName']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="street" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.company.field.street')) ?></label>
        <input type="text" id="street" name="street"
               value="<?= e($settings->street) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['street']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['street'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['street']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="postal_code" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.company.field.postal_code')) ?></label>
            <input type="text" id="postal_code" name="postal_code"
                   value="<?= e($settings->postalCode) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['postalCode']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['postalCode'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['postalCode']) ?></p><?php endif; ?>
        </div>
        <div class="col-span-2">
            <label for="city" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.company.field.city')) ?></label>
            <input type="text" id="city" name="city"
                   value="<?= e($settings->city) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['city']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['city'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['city']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="country" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.company.field.country')) ?></label>
        <input type="text" id="country" name="country"
               value="<?= e($settings->country) ?>"
               placeholder="Deutschland"
               class="mt-1 w-full rounded-md border <?= isset($errors['country']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['country'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['country']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="logo_max_height_px" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.company.field.logo_max_height_px')) ?></label>
        <p class="mb-2 mt-0.5 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.company.field.logo_max_height_px_hint')) ?></p>
        <input type="number" id="logo_max_height_px" name="logo_max_height_px" min="16" max="200" step="1"
               placeholder="40"
               value="<?= $settings->logoMaxHeightPx !== null ? e((string) $settings->logoMaxHeightPx) : '' ?>"
               class="mt-1 w-full max-w-[10rem] rounded-md border <?= isset($errors['logoMaxHeightPx']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['logoMaxHeightPx'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['logoMaxHeightPx']) ?></p><?php endif; ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>

<div class="space-y-6 lg:col-span-1">
<div class="space-y-3 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.company.logo.title')) ?></h2>

    <?php if ($settings->logoFilename !== null): ?>
        <div class="flex items-center gap-4">
            <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-800">
                <img src="/einstellungen/firma/logo" alt="<?= e(t('settings.company.logo.alt')) ?>" class="max-h-full max-w-full object-contain">
            </div>
            <form method="post" action="/einstellungen/firma/logo/loeschen" data-confirm="<?= e(t('settings.company.logo.confirm_remove')) ?>" data-confirm-style="danger">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                    <?= e(t('settings.company.logo.remove')) ?>
                </button>
            </form>
        </div>
    <?php else: ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.company.logo.none')) ?></p>
    <?php endif; ?>

    <form method="post" action="/einstellungen/firma/logo" enctype="multipart/form-data" class="flex items-center gap-3">
      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <input type="file" name="logo" accept="image/*"
               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-400 dark:file:bg-slate-800 dark:file:text-slate-300 dark:hover:file:bg-slate-700">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('settings.company.logo.upload')) ?>
        </button>
    </form>
    <?php if (isset($errors['logo'])): ?><p class="text-xs text-red-600 dark:text-red-400"><?= e($errors['logo']) ?></p><?php endif; ?>
</div>

<div class="space-y-3 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.company.dark_logo.title')) ?></h2>
    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.company.dark_logo.hint')) ?></p>

    <?php if ($settings->darkLogoFilename !== null): ?>
        <div class="flex items-center gap-4">
            <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-800 p-2 dark:border-slate-700">
                <img src="/einstellungen/firma/logo-dunkel" alt="<?= e(t('settings.company.dark_logo.alt')) ?>" class="max-h-full max-w-full object-contain">
            </div>
            <form method="post" action="/einstellungen/firma/logo-dunkel/loeschen" data-confirm="<?= e(t('settings.company.dark_logo.confirm_remove')) ?>" data-confirm-style="danger">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                    <?= e(t('settings.company.dark_logo.remove')) ?>
                </button>
            </form>
        </div>
    <?php else: ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.company.dark_logo.none')) ?></p>
    <?php endif; ?>

    <form method="post" action="/einstellungen/firma/logo-dunkel" enctype="multipart/form-data" class="flex items-center gap-3">
      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <input type="file" name="dark_logo" accept="image/*"
               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-400 dark:file:bg-slate-800 dark:file:text-slate-300 dark:hover:file:bg-slate-700">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('settings.company.dark_logo.upload')) ?>
        </button>
    </form>
    <?php if (isset($errors['darkLogo'])): ?><p class="text-xs text-red-600 dark:text-red-400"><?= e($errors['darkLogo']) ?></p><?php endif; ?>
</div>

<div class="space-y-3 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.company.background_image.title')) ?></h2>
    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.company.background_image.hint')) ?></p>

    <?php if ($settings->backgroundImageFilename !== null): ?>
        <div class="flex items-center gap-4">
            <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                <img src="/einstellungen/firma/hintergrundbild" alt="<?= e(t('settings.company.background_image.alt')) ?>" class="h-full w-full object-cover">
            </div>
            <form method="post" action="/einstellungen/firma/hintergrundbild/loeschen" data-confirm="<?= e(t('settings.company.background_image.confirm_remove')) ?>" data-confirm-style="danger">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                    <?= e(t('settings.company.background_image.remove')) ?>
                </button>
            </form>
        </div>
    <?php else: ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.company.background_image.none')) ?></p>
    <?php endif; ?>

    <form method="post" action="/einstellungen/firma/hintergrundbild" enctype="multipart/form-data" class="flex items-center gap-3">
      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <input type="file" name="background_image" accept="image/*"
               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-400 dark:file:bg-slate-800 dark:file:text-slate-300 dark:hover:file:bg-slate-700">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('settings.company.background_image.upload')) ?>
        </button>
    </form>
    <?php if (isset($errors['background_image'])): ?><p class="text-xs text-red-600 dark:text-red-400"><?= e($errors['background_image']) ?></p><?php endif; ?>
</div>
</div>
</div>
