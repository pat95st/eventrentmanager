<?php
/**
 * @var \App\Domain\Settings\AiAdImageSettings $settings
 * @var array<string, string> $errors
 * @var string $imageModelValue
 * @var list<string> $availableModels
 * @var bool $modelsFromApi
 * @var string|null $modelListError
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.ai_ad_images.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.ai_ad_images.subtitle')) ?></p>
</div>

<form method="post" action="/einstellungen/ki-werbebilder" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
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

    <div>
        <label for="image_model" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.ai_ad_images.field.image_model')) ?></label>
        <input type="text" id="image_model" name="image_model" list="image_model_options" maxlength="100"
               value="<?= e($imageModelValue) ?>"
               placeholder="<?= e(\App\Domain\Settings\AiAdImageSettings::DEFAULT_IMAGE_MODEL) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['image_model']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <datalist id="image_model_options">
            <?php foreach ($availableModels as $model): ?>
                <option value="<?= e($model) ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <?php if (isset($errors['image_model'])): ?>
            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['image_model']) ?></p>
        <?php endif; ?>
        <p class="mt-1 text-xs text-slate-400">
            <?= e(t('settings.ai_ad_images.field.image_model_hint', ['default' => \App\Domain\Settings\AiAdImageSettings::DEFAULT_IMAGE_MODEL])) ?>
            <?php if ($modelsFromApi): ?>
                <?= e(t('settings.ai_ad_images.field.image_model_from_api', ['models' => implode(', ', $availableModels)])) ?>
            <?php elseif ($modelListError !== null): ?>
                <span class="text-amber-600 dark:text-amber-400"><?= e(t('settings.ai_ad_images.field.image_model_list_failed', ['error' => $modelListError])) ?></span>
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
