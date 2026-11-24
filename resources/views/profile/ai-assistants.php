<?php
/**
 * @var \App\Application\OAuth\OAuthConnection[] $connections
 * @var bool $mcpAvailable
 * @var bool $mayConnect
 * @var string $mcpUrl
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('profile.ai.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.ai.subtitle')) ?></p>
</div>

<div class="space-y-6">
    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <?php if (!$mcpAvailable): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.ai.unavailable')) ?></p>
        <?php elseif (!$mayConnect): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.ai.no_permissions')) ?></p>
        <?php else: ?>
            <p class="text-sm text-slate-600 dark:text-slate-300"><?= e(t('profile.ai.setup_hint')) ?></p>
            <p class="mt-2 select-all break-all rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"><?= e($mcpUrl) ?></p>
        <?php endif; ?>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('profile.ai.connections')) ?></h2>
        <?php if ($connections === []): ?>
            <p class="text-sm text-slate-400 dark:text-slate-500"><?= e(t('profile.ai.empty')) ?></p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($connections as $connection): ?>
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($connection->clientName) ?> <span class="text-xs font-normal text-slate-400"><?= e($connection->redirectHost) ?></span></p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                <?= e(t('profile.ai.connected_on', ['date' => $connection->grant->createdAt->format('d.m.Y H:i')])) ?>
                                &middot;
                                <?= e($connection->lastUsedAt !== null ? t('profile.ai.last_used', ['date' => $connection->lastUsedAt->format('d.m.Y H:i')]) : t('profile.ai.never_used')) ?>
                            </p>
                            <p class="mt-1 flex flex-wrap gap-1">
                                <?php foreach ($connection->grant->scopes as $scope): ?>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] <?= $scope->isRead() ? 'text-slate-500 dark:text-slate-400' : 'text-amber-700 dark:text-amber-400' ?> dark:bg-slate-800"><?= e($scope->value) ?></span>
                                <?php endforeach; ?>
                            </p>
                        </div>
                        <form method="post" action="/profil/ki-assistenten/<?= e($connection->grant->id) ?>/trennen" data-confirm-key="profile.ai.disconnect" data-confirm="<?= e(t('profile.ai.confirm_disconnect')) ?>" data-confirm-style="danger">
                            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <button type="submit" class="whitespace-nowrap rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('profile.ai.disconnect')) ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
