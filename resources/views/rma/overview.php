<?php
/** @var \App\Application\Rma\Query\RmaOverviewEntry[] $entries */
/** @var \App\Domain\Rma\RmaStatus|null $selectedStatus */
/** @var string $search */

use App\Domain\Article\ArticleDisplayName;
use App\Domain\Rma\RmaStatus;

use function App\Infrastructure\Http\e;
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">RMA-Verwaltung</h1>
</div>

<form method="get" action="/rma" class="mb-6 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Suche</label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Artikel, Hersteller oder Inventurnummer"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
        <select name="status" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <option value="">Alle</option>
            <?php foreach (RmaStatus::cases() as $status): ?>
                <option value="<?= e($status->value) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= e($status->label()) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex items-end gap-3 sm:col-span-4">
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Filtern
        </button>
        <a href="/rma" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Zurücksetzen</a>
    </div>
</form>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($entries === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">Keine RMA-Vorgänge gefunden.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] table-fixed text-left text-sm">
            <colgroup>
                <col style="width: 9%">
                <col style="width: 9%">
                <col style="width: 15%">
                <col style="width: 13%">
                <col style="width: 22%">
                <col style="width: 11%">
                <col style="width: 11%">
                <col style="width: 10%">
            </colgroup>
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="break-words px-4 py-2.5">Status</th>
                    <th class="break-words px-4 py-2.5">Art</th>
                    <th class="break-words px-4 py-2.5">Artikel</th>
                    <th class="break-words px-4 py-2.5">Inventurnummer</th>
                    <th class="break-words px-4 py-2.5">Grund</th>
                    <th class="break-words px-4 py-2.5">Abgeschickt</th>
                    <th class="break-words px-4 py-2.5">Zurückerhalten</th>
                    <th class="break-words px-4 py-2.5">Dokumente</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($entries as $entry): ?>
                    <?php $rma = $entry->rma; $status = $rma->status(); ?>
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" onclick="window.location='/inventurnummern/<?= e($entry->inventoryNumber->id) ?>/rma'">
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full <?= $status->badgeClasses() ?> px-2.5 py-0.5 text-xs font-medium">
                                <?= e($status->label()) ?>
                            </span>
                        </td>
                        <td class="break-words px-4 py-3 text-xs text-slate-600 dark:text-slate-400"><?= e($rma->kind->label()) ?></td>
                        <td class="break-words px-4 py-3">
                            <span class="font-medium text-slate-900 dark:text-slate-100"><?= e(ArticleDisplayName::of($entry->article, $entry->manufacturer)) ?></span>
                        </td>
                        <td class="break-words px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400"><?= e($entry->inventoryNumber->number) ?></td>
                        <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($rma->reason ?? '–') ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400"><?= $rma->sentAt !== null ? e($rma->sentAt->format('d.m.Y')) : '–' ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400"><?= $rma->returnedAt !== null ? e($rma->returnedAt->format('d.m.Y')) : '–' ?></td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= $entry->documentCount > 0 ? e((string) $entry->documentCount) : '–' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
    Neue RMA-/Wartungsvorgänge werden je Inventurnummer auf der Artikel-Detailseite angelegt.
</p>
