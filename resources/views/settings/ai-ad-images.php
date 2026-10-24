<?php
/** @var \App\Domain\Settings\AiAdImageSettings $settings */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.ai_ad_images.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.ai_ad_images.subtitle')) ?></p>
</div>

<form method="post" action="/einstellungen/ki-werbebilder" class="max-w-2xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <label for="openai_api_key" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.ai_ad_images.field.openai_api_key')) ?></label>
        <input type="password" id="openai_api_key" name="openai_api_key"
               placeholder="<?= $settings->isConfigured() ? e(t('settings.ai_ad_images.field.token_placeholder')) : '' ?>"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        <p class="mt-1 text-xs text-slate-400">
            <?php if ($settings->isConfigured()): ?>
                <?= e(t('settings.ai_ad_images.field.openai_api_key_configured')) ?>
            <?php else: ?>
                <?= e(t('settings.ai_ad_images.field.openai_api_key_hint')) ?>
            <?php endif; ?>
        </p>
    </div>

    <p class="text-xs text-slate-400">
        <?= e(t('settings.ai_ad_images.hint')) ?>
    </p>

    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
        <?= e(t('common.save')) ?>
    </button>
</form>
