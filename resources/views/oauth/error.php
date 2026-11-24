<?php
/** @var \App\Application\OAuth\OAuthError $error */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>
<div class="mx-auto max-w-xl rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100"><?= e(t('oauth.error.heading')) ?></h1>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300"><?= e($error->description) ?></p>
    <p class="mt-1 font-mono text-xs text-slate-400 dark:text-slate-500"><?= e($error->error) ?></p>
    <a href="/" class="mt-4 inline-block text-sm font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('oauth.error.back')) ?></a>
</div>
