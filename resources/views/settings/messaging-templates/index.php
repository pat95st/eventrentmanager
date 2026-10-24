<?php
/** @var \App\Domain\Messaging\MessagingChannel $channel */
/** @var \App\Domain\Messaging\MessageTemplate[] $templates */

use App\Domain\Messaging\MessagingChannel;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.messaging_templates.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.messaging_templates.subtitle')) ?></p>
</div>

<div class="mb-4 flex gap-2">
    <?php foreach (MessagingChannel::cases() as $option): ?>
        <a href="/einstellungen/messenger-vorlagen?kanal=<?= e($option->value) ?>"
           class="rounded-md px-3 py-1.5 text-sm font-medium <?= $channel === $option ? 'bg-brand-600 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' ?>">
            <?= e($option->label()) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3"><?= e(t('settings.messaging_templates.column.name')) ?></th>
                <th class="px-4 py-3"><?= e(t('settings.messaging_templates.column.description')) ?></th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($templates as $template): ?>
                <tr>
                    <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200"><?= e($template->key->label()) ?></td>
                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400"><?= e($template->key->description()) ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="/einstellungen/messenger-vorlagen/<?= e($channel->value) ?>/<?= e($template->key->value) ?>" class="text-brand-600 hover:underline dark:text-brand-400">
                            <?= e(t('common.edit')) ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
