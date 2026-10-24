<?php
/** @var \App\Domain\User\User $targetUser */
/** @var \App\Application\Auth\Query\SessionListEntry[] $sessions */

use function App\Infrastructure\Http\e;
?>

<div class="mb-6">
    <a href="/benutzer" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück zur Benutzerliste</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Sitzungen: <?= e($targetUser->displayName()) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($targetUser->email) ?></p>
</div>

<div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500 dark:text-slate-400">Hier sehen Sie, auf welchen Geräten dieser Benutzer aktuell angemeldet ist, und können einzelne oder alle Sitzungen beenden.</p>
        <?php if ($sessions !== []): ?>
            <form method="post" action="/benutzer/<?= e($targetUser->id) ?>/sitzungen/alle-beenden" data-confirm="Wirklich ALLE Sitzungen dieses Benutzers beenden? Der Benutzer wird auf allen Geräten abgemeldet." data-confirm-style="danger">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="whitespace-nowrap rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                    Alle Sitzungen beenden
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
                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-950/40 dark:text-brand-400">Diese Sitzung</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-slate-400 dark:text-slate-500">
                        Angemeldet am <?= e($session->createdAt->format('d.m.Y H:i')) ?>
                        &middot;
                        Zuletzt aktiv am <?= e($session->lastSeenAt->format('d.m.Y H:i')) ?>
                        <?php if ($session->ipAddress !== null && $session->ipAddress !== ''): ?>
                            &middot; <?= e($session->ipAddress) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <form method="post" action="/benutzer/<?= e($targetUser->id) ?>/sitzungen/<?= e($session->id) ?>/beenden" data-confirm="<?= e($entry->isCurrent ? 'Dies ist Ihre eigene, gerade benutzte Sitzung. Sie werden dabei sofort abgemeldet. Fortfahren?' : 'Diese Sitzung wirklich beenden?') ?>" data-confirm-style="danger">
                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <button type="submit" class="whitespace-nowrap rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                        Beenden
                    </button>
                </form>
            </li>
        <?php endforeach; ?>
        <?php if ($sessions === []): ?>
            <li class="py-3 text-sm text-slate-400 dark:text-slate-500">Keine aktiven Sitzungen.</li>
        <?php endif; ?>
    </ul>
</div>
