<?php
/** @var \App\Application\Firmware\Query\FirmwareUpdateEntry[] $entries */
/** @var string $search */

use App\Domain\Article\ArticleDisplayName;
use function App\Infrastructure\Http\e;
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Firmware-Updates verfügbar</h1>
</div>

<form method="get" action="/firmware" class="mb-6 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Suche</label>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Artikel, Hersteller oder Inventurnummer"
               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
    </div>
    <div class="flex items-end gap-3">
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Filtern
        </button>
        <a href="/firmware" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Zurücksetzen</a>
    </div>
</form>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($entries === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">Für kein Gerät ist derzeit ein Firmware-Update verfügbar.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2.5">Artikel</th>
                    <th class="px-4 py-2.5">Inventurnummer</th>
                    <th class="px-4 py-2.5">Installierte Version</th>
                    <th class="px-4 py-2.5">Aktuellste Version</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($entries as $entry): ?>
                    <tr class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" onclick="window.location='/inventurnummern/<?= e($entry->inventoryNumber->id) ?>'">
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-900 dark:text-slate-100"><?= e(ArticleDisplayName::of($entry->article, $entry->manufacturer)) ?></span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400"><?= e($entry->inventoryNumber->number) ?></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700">
                                <?= e($entry->installedVersion->version) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-700">
                                <?= e($entry->latestVersion->version) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
    Firmware-Versionen werden je Artikel auf der Artikel-Detailseite verwaltet. Die installierte Version wird je Inventurnummer dort bzw. bei der Inventurnummer selbst ausgewählt.
</p>
