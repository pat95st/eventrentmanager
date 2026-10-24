<?php
/** @var \App\Domain\Mail\EmailTemplate[] $templates */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.email_templates.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('settings.email_templates.subtitle')) ?>
    </p>
</div>

<div class="max-w-3xl divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
    <?php foreach ($templates as $template): ?>
        <a href="/einstellungen/email-vorlagen/<?= e($template->key->value) ?>" class="flex items-center justify-between gap-4 px-6 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/60">
            <div class="min-w-0">
                <div class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($template->key->label()) ?></div>
                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500"><?= e($template->key->description()) ?></p>
                <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400"><?= e(t('settings.email_templates.field.subject')) ?>: <?= e($template->subject) ?></p>
            </div>
            <svg class="h-4 w-4 flex-shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L10.94 10 7.23 6.29a.75.75 0 111.06-1.06l4.25 4.24a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.08-.01z" clip-rule="evenodd"/></svg>
        </a>
    <?php endforeach; ?>
</div>
