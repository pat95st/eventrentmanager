<?php
/**
 * @var \App\Application\OAuth\AuthorizationRequest $authorization
 * @var string $clientName
 * @var string $redirectHost
 * @var \App\Domain\Api\ApiScope[] $offeredScopes
 * @var \App\Domain\Api\ApiScope[] $preselected
 * @var bool $mcpReadOnly
 * @var \App\Domain\User\User|null $user
 * @var array<string, string> $errors
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$selected = isset($_POST['scopes']) && is_array($_POST['scopes']) ? array_map('strval', $_POST['scopes']) : array_map(static fn ($s) => $s->value, $preselected);
?>
<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('oauth.consent.heading', ['client' => $clientName])) ?></h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e(t('oauth.consent.intro', ['user' => $user?->displayName() ?? ''])) ?></p>
    </div>

    <form method="post" action="/oauth/authorize" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <?php foreach ($authorization->parameters() as $name => $value): ?>
            <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
        <?php endforeach; ?>

        <div class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
            <p><?= e(t('oauth.consent.redirect', ['host' => $redirectHost])) ?></p>
            <p class="mt-1 text-xs"><?= e(t('oauth.consent.redirect_warning')) ?></p>
        </div>

        <fieldset>
            <legend class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('oauth.consent.scopes')) ?></legend>
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400"><?= e(t('oauth.consent.scopes_hint')) ?></p>
            <div class="grid gap-1 sm:grid-cols-2">
                <?php foreach ($offeredScopes as $scope): ?>
                    <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="scopes[]" value="<?= e($scope->value) ?>" <?= in_array($scope->value, $selected, true) ? 'checked' : '' ?> class="mt-0.5 rounded border-slate-300">
                        <span><span class="font-mono text-xs <?= $scope->isRead() ? 'text-slate-500' : 'text-amber-700 dark:text-amber-400' ?>"><?= e($scope->value) ?></span><br><span class="text-xs text-slate-500 dark:text-slate-400"><?= e($scope->label()) ?></span></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (isset($errors['scopes'])): ?>
                <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['scopes']) ?></p>
            <?php endif; ?>
        </fieldset>

        <?php if ($mcpReadOnly): ?>
            <p class="rounded-md bg-sky-50 px-3 py-2 text-xs text-sky-800 dark:bg-sky-950/40 dark:text-sky-300"><?= e(t('oauth.consent.read_only_note')) ?></p>
        <?php endif; ?>
        <p class="text-xs text-slate-500 dark:text-slate-400"><?= e(t('oauth.consent.never')) ?> <?= e(t('oauth.consent.revoke_hint')) ?></p>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" name="decision" value="allow" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('oauth.consent.allow')) ?></button>
            <button type="submit" name="decision" value="deny" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('oauth.consent.deny')) ?></button>
        </div>
    </form>
</div>
