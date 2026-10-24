<?php
/** @var \App\Application\Auth\Query\ImpersonationListRow[] $rows */
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use function App\Infrastructure\Http\e;
?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($rows === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">Keine Impersonationen protokolliert.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 24%">
                <col style="width: 24%">
                <col style="width: 18%">
                <col style="width: 18%">
                <col style="width: 16%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5">Administrator</th>
                    <th class="break-words px-4 py-2.5">Zielbenutzer</th>
                    <th class="break-words px-4 py-2.5">Start</th>
                    <th class="break-words px-4 py-2.5">Ende</th>
                    <th class="break-words px-4 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($rows as $row): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                        <td class="break-words px-4 py-3 font-medium text-slate-900 dark:text-slate-100"><?= e($row->adminDisplayName ?? '(gelöschter Benutzer)') ?></td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($row->targetDisplayName ?? '(gelöschter Benutzer)') ?></td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($row->startedAt()->format('d.m.Y H:i')) ?></td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= $row->endedAt() !== null ? e($row->endedAt()->format('d.m.Y H:i')) : '—' ?></td>
                        <td class="break-words px-4 py-3">
                            <?php if ($row->isActive()): ?>
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700">Aktiv</span>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">Beendet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
