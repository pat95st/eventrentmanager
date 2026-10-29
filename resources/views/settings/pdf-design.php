<?php
/** @var \App\Domain\Settings\PdfDesignSettings $settings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $key, ?string $fallback) => array_key_exists($key, $old) ? (string) $old[$key] : ($fallback ?? '');
$accentColor = $val('accent_color', $settings->accentColorHex());
$logoPosition = $val('logo_position', $settings->logoPosition);
$currencyDisplay = $val('currency_display', $settings->currencyDisplay);
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.pdf_design.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('settings.pdf_design.subtitle')) ?>
    </p>
</div>

<form method="post" action="/einstellungen/pdf-design" class="max-w-2xl space-y-6 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <label for="accent_color" class="block text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('settings.pdf_design.field.accent_color')) ?></label>
        <p class="mb-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.pdf_design.field.accent_color_hint')) ?></p>
        <div class="flex items-center gap-3">
            <input type="color" id="accent_color" name="accent_color" value="<?= e($accentColor) ?>"
                   class="h-10 w-16 cursor-pointer rounded-md border <?= isset($errors['accentColorHex']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> bg-white p-1 dark:bg-slate-950">
            <span class="text-sm text-slate-500 dark:text-slate-400"><?= e($accentColor) ?></span>
        </div>
        <?php if (isset($errors['accentColorHex'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['accentColorHex']) ?></p><?php endif; ?>
    </div>

    <div>
        <span class="block text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('settings.pdf_design.field.logo_position')) ?></span>
        <p class="mb-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.pdf_design.field.logo_position_hint')) ?></p>
        <div class="flex gap-4">
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="logo_position" value="right" <?= $logoPosition === 'right' ? 'checked' : '' ?>
                       class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('settings.pdf_design.field.logo_position_right')) ?>
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="logo_position" value="left" <?= $logoPosition === 'left' ? 'checked' : '' ?>
                       class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('settings.pdf_design.field.logo_position_left')) ?>
            </label>
        </div>
        <?php if (isset($errors['logoPosition'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['logoPosition']) ?></p><?php endif; ?>
    </div>

    <div>
        <span class="block text-sm font-medium text-slate-700 dark:text-slate-300"><?= e(t('settings.pdf_design.field.currency_display')) ?></span>
        <p class="mb-2 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.pdf_design.field.currency_display_hint')) ?></p>
        <div class="flex gap-4">
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="currency_display" value="code" <?= $currencyDisplay !== 'symbol' ? 'checked' : '' ?>
                       class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('settings.pdf_design.field.currency_display_code')) ?>
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="radio" name="currency_display" value="symbol" <?= $currencyDisplay === 'symbol' ? 'checked' : '' ?>
                       class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600">
                <?= e(t('settings.pdf_design.field.currency_display_symbol')) ?>
            </label>
        </div>
        <?php if (isset($errors['currencyDisplay'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['currencyDisplay']) ?></p><?php endif; ?>
    </div>

    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.pdf_design.hint')) ?></p>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>
