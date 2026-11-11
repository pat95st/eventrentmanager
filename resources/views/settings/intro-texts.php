<?php
/** @var array<string, string> $texts */
/** @var array<string, string> $errors */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$documentTypes = [
    'offer' => [t('settings.intro_texts.offer'), "{anrede},\n\nvielen Dank für Ihre Anfrage. Gerne bieten wir Ihnen folgende Artikel an:"],
    'order_confirmation' => [t('settings.intro_texts.order_confirmation'), "{anrede},\n\nvielen Dank für Ihren Auftrag. Wir bestätigen Ihnen folgende Artikel:"],
    'invoice' => [t('settings.intro_texts.invoice'), "{anrede},\n\nwir erlauben uns, folgende Artikel in Rechnung zu stellen:"],
];
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.intro_texts.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.intro_texts.subtitle')) ?></p>
</div>

<form method="post" action="/einstellungen/starttexte" class="space-y-6 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div class="rounded-md bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
        <p class="mb-1 font-medium"><?= e(t('settings.intro_texts.placeholders_title')) ?></p>
        <ul class="space-y-0.5">
            <li><code>{anrede}</code> – <?= e(t('settings.intro_texts.placeholder_anrede')) ?></li>
            <li><code>{vorname}</code>, <code>{nachname}</code>, <code>{firma}</code> – <?= e(t('settings.intro_texts.placeholder_names')) ?></li>
            <li><code>{nummer}</code> – <?= e(t('settings.intro_texts.placeholder_number')) ?></li>
        </ul>
    </div>

    <?php foreach ($documentTypes as $type => [$label, $example]): ?>
        <div>
            <label for="intro_<?= e($type) ?>" class="block text-sm font-medium text-slate-700 dark:text-slate-300"><?= e($label) ?></label>
            <textarea id="intro_<?= e($type) ?>" name="<?= e($type) ?>" rows="5" placeholder="<?= e($example) ?>"
                      class="mt-1 w-full rounded-md border <?= isset($errors[$type]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"><?= e($texts[$type] ?? '') ?></textarea>
            <?php if (isset($errors[$type])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors[$type]) ?></p><?php endif; ?>
        </div>
    <?php endforeach; ?>

    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.intro_texts.hint')) ?></p>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
    </div>
</form>
