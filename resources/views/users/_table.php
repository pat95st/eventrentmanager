<?php
/** @var \App\Domain\User\User[] $users */
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use function App\Infrastructure\Http\e;
?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($users === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">Keine Benutzer gefunden.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 20%">
                <col style="width: 22%">
                <col style="width: 24%">
                <col style="width: 10%">
                <col style="width: 12%">
                <col style="width: 12%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5">Name</th>
                    <th class="break-words px-4 py-2.5">E-Mail</th>
                    <th class="break-words px-4 py-2.5">Rechte</th>
                    <th class="break-words px-4 py-2.5">2FA</th>
                    <th class="break-words px-4 py-2.5">Status</th>
                    <th class="break-words px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($users as $index => $user): ?>
                    <?php $openUpward = $index >= count($users) - 2; ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100"><?= e($user->displayName()) ?></td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($user->email) ?></td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?= e(implode(', ', array_map(static fn ($p) => $p->label(), $user->permissions))) ?>
                        </td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($user->twoFactorMethod->label()) ?></td>
                        <td class="break-words px-4 py-3">
                            <?php if ($user->isActive): ?>
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700">Aktiv</span>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">Inaktiv</span>
                            <?php endif; ?>
                        </td>
                        <td class="break-words px-4 py-3 text-right">
                            <details class="relative inline-block text-left" data-dropdown>
                                <summary class="flex h-7 w-7 cursor-pointer list-none items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 14a1.5 1.5 0 110 3 1.5 1.5 0 010-3z"/></svg>
                                    <span class="sr-only">Aktionen</span>
                                </summary>
                                <div data-dropdown-menu class="absolute right-0 z-10 w-48 rounded-md border border-slate-200 bg-white py-1 text-left shadow-lg dark:border-slate-700 dark:bg-slate-900 <?= $openUpward ? 'bottom-full mb-1' : 'top-full mt-1' ?>">
                                    <a href="/benutzer/<?= e($user->id) ?>/bearbeiten" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">Bearbeiten</a>
                                    <a href="/benutzer/<?= e($user->id) ?>/passwort" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">Passwort</a>
                                    <a href="/benutzer/<?= e($user->id) ?>/sitzungen" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">Sitzungen</a>
                                    <?php if ($user->isActive && !$user->isAdministrator() && $user->id !== \App\Infrastructure\Http\Auth\CurrentUser::get()?->id): ?>
                                        <form method="post" action="/benutzer/<?= e($user->id) ?>/impersonieren" data-confirm="Als &quot;<?= e($user->displayName()) ?>&quot; anmelden?">
                                            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                            <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">Als Benutzer anmelden</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($user->twoFactorMethod->value !== 'none'): ?>
                                        <form method="post" action="/benutzer/<?= e($user->id) ?>/2fa-zuruecksetzen" data-confirm="Zweiten Faktor für diesen Benutzer zurücksetzen?">
                                            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                            <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">2FA zurücksetzen</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="/benutzer/<?= e($user->id) ?>/loeschen" data-confirm="Diesen Benutzer wirklich löschen?" data-confirm-style="danger">
                                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">Löschen</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
