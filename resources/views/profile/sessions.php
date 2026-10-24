<?php
/** @var \App\Application\Auth\Query\SessionListEntry[] $sessions */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('profile.sessions.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.sessions.subtitle')) ?></p>
</div>

<div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.sessions.hint')) ?></p>
        <?php if (count($sessions) > 1): ?>
            <form method="post" action="/profil/sitzungen/andere-beenden" data-confirm="<?= e(t('profile.sessions.confirm_revoke_others')) ?>" data-confirm-style="danger">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="whitespace-nowrap rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                    <?= e(t('profile.sessions.revoke_others')) ?>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <ul class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($sessions as $entry): ?>
            <?php $session = $entry->session; ?>
            <li class="flex items-center justify-between gap-3 py-3">
                <div class="min-w-0">
                    <p class="flex items-center gap-2 text-sm font-medium text-slate-800 dark:text-slate-200">
                        <?= e($session->deviceLabel()) ?>
                        <?php if ($entry->isCurrent): ?>
                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-950/40 dark:text-brand-400"><?= e(t('profile.sessions.this_session')) ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-slate-400 dark:text-slate-500">
                        <?= e(t('profile.sessions.created_on', ['date' => $session->createdAt->format('d.m.Y H:i')])) ?>
                        &middot;
                        <?= e(t('profile.sessions.last_active_on', ['date' => $session->lastSeenAt->format('d.m.Y H:i')])) ?>
                        <?php if ($session->ipAddress !== null && $session->ipAddress !== ''): ?>
                            &middot; <?= e($session->ipAddress) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (!$entry->isCurrent): ?>
                    <form method="post" action="/profil/sitzungen/<?= e($session->id) ?>/beenden" data-confirm="<?= e(t('profile.sessions.confirm_revoke')) ?>" data-confirm-style="danger">
                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <button type="submit" class="whitespace-nowrap rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                            <?= e(t('profile.sessions.revoke')) ?>
                        </button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if ($sessions === []): ?>
            <li class="py-3 text-sm text-slate-400 dark:text-slate-500"><?= e(t('profile.sessions.empty')) ?></li>
        <?php endif; ?>
    </ul>
</div>
