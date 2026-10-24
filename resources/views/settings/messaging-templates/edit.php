<?php
/** @var \App\Domain\Messaging\MessagingChannel $channel */
/** @var \App\Domain\Messaging\MessageTemplateKey $key */
/** @var \App\Domain\Messaging\MessageTemplate $template */
/** @var array<string, string> $errors */

use App\Domain\Messaging\MessagingChannel;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <a href="/einstellungen/messenger-vorlagen?kanal=<?= e($channel->value) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; <?= e(t('settings.messaging_templates.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($channel->label()) ?>: <?= e($key->label()) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($key->description()) ?></p>
</div>

<form method="post" action="/einstellungen/messenger-vorlagen/<?= e($channel->value) ?>/<?= e($key->value) ?>"
      class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <?php if ($channel === MessagingChannel::WhatsApp): ?>
        <div class="rounded-md bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
            <?= e(t('settings.messaging_templates.whatsapp_notice')) ?>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="meta_template_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.messaging_templates.field.meta_template_name')) ?></label>
                <input type="text" id="meta_template_name" name="meta_template_name" required value="<?= e($template->metaTemplateName ?? '') ?>"
                       class="mt-1 w-full rounded-md border <?= isset($errors['metaTemplateName']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <?php if (isset($errors['metaTemplateName'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['metaTemplateName']) ?></p><?php endif; ?>
            </div>
            <div>
                <label for="meta_template_language" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.messaging_templates.field.meta_template_language')) ?></label>
                <input type="text" id="meta_template_language" name="meta_template_language" placeholder="de" value="<?= e($template->metaTemplateLanguage) ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
        </div>
        <?php if ($key->whatsappParameterOrder() !== []): ?>
            <p class="text-xs text-slate-400">
                <?= e(t('settings.messaging_templates.whatsapp_parameters_hint')) ?>
                <?php foreach ($key->whatsappParameterOrder() as $index => $variable): ?>
                    <code class="rounded bg-slate-100 px-1 dark:bg-slate-800">{{<?= $index + 1 ?>}} = <?= e($variable) ?></code><?= $index < count($key->whatsappParameterOrder()) - 1 ? ', ' : '' ?>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    <?php else: ?>
        <div>
            <label for="body_text" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.messaging_templates.field.body_text')) ?></label>
            <textarea id="body_text" name="body_text" rows="8" required
                      class="mt-1 w-full rounded-md border <?= isset($errors['bodyText']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"><?= e($template->bodyText ?? '') ?></textarea>
            <?php if (isset($errors['bodyText'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['bodyText']) ?></p><?php endif; ?>
        </div>
        <?php if ($key->availableVariables() !== []): ?>
            <div class="rounded-md bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                <p class="mb-1 font-medium"><?= e(t('settings.messaging_templates.variables_hint')) ?></p>
                <ul class="space-y-0.5">
                    <?php foreach ($key->availableVariables() as $variable => $explanation): ?>
                        <li><code class="rounded bg-slate-200 px-1 dark:bg-slate-700">{{<?= e($variable) ?>}}</code> &mdash; <?= e($explanation) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>

<form method="post" action="/einstellungen/messenger-vorlagen/<?= e($channel->value) ?>/<?= e($key->value) ?>/zuruecksetzen" class="mt-3 max-w-2xl"
      onsubmit="return confirm('<?= e(t('settings.messaging_templates.confirm_reset')) ?>')">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <button type="submit" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('settings.messaging_templates.reset')) ?></button>
</form>
