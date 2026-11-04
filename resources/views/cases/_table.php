<?php
/** @var \App\Domain\CaseItem\CaseItem[] $cases */
/** @var \App\Domain\Manufacturer\Manufacturer[] $manufacturers */
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use function App\Infrastructure\Http\e;

$manufacturerById = [];
foreach ($manufacturers as $manufacturer) {
    $manufacturerById[$manufacturer->id] = $manufacturer;
}
?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($cases === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">Keine Cases gefunden.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[480px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 40%">
                <col style="width: 20%">
                <col style="width: 25%">
                <col style="width: 15%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2.5 break-words">Name</th>
                    <th class="px-4 py-2.5 break-words">Präfix</th>
                    <th class="px-4 py-2.5 break-words">Hersteller</th>
                    <th class="px-4 py-2.5 break-words">Beschreibung</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($cases as $case): ?>
                    <?php $manufacturer = $manufacturerById[$case->manufacturerId] ?? null; ?>
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" onclick="window.location='/cases/<?= e($case->id) ?>'">
                        <td class="px-4 py-3 break-words font-medium text-slate-900 dark:text-slate-100"><?= e($case->name) ?></td>
                        <td class="px-4 py-3 break-words font-mono text-xs text-slate-500 dark:text-slate-400">
                            <?= ($manufacturer?->prefix !== null && $case->prefix !== null) ? e($manufacturer->prefix . '-' . $case->prefix) : '–' ?>
                        </td>
                        <td class="px-4 py-3 break-words text-slate-600 dark:text-slate-400"><?= e($manufacturer?->name ?? '–') ?></td>
                        <td class="px-4 py-3 break-words text-slate-600 dark:text-slate-400"><?= e($case->description ?? '–') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
