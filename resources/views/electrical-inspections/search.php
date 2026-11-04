<?php
/** @var string $query */
/** @var bool $notFound */
/** @var string $filter */
/** @var list<\App\Application\ElectricalInspection\Query\RecentElectricalInspection> $recent */

use function App\Infrastructure\Http\e;
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Elektroprüfung</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Inventurnummer eingeben oder den Barcode scannen: Danach siehst du das Gerät mit Schutzklasse, Anschaffung, früheren Prüfungen und RMA-Einsätzen und kannst die neue Prüfung eintragen.</p>
</div>

<form method="get" action="/elektropruefung" class="mb-6 max-w-2xl space-y-3 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <label for="nummer" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Inventurnummer</label>
    <div class="flex flex-wrap gap-2">
        <input id="nummer" name="nummer" value="<?= e($query) ?>" required autofocus autocomplete="off" placeholder="z. B. THO-PLM-0012"
               class="min-w-0 flex-1 rounded-md border px-3 py-2 font-mono text-sm uppercase focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 <?= $notFound ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?>"
               style="flex-basis:14rem">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Gerät öffnen</button>
    </div>
    <?php if ($notFound): ?>
        <p class="text-sm text-red-600 dark:text-red-400" role="alert">Zur Inventurnummer „<?= e($query) ?>“ wurde kein Gerät gefunden.</p>
    <?php endif; ?>
</form>

<section aria-labelledby="pruefungen-titel">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 id="pruefungen-titel" class="text-sm font-semibold text-slate-700 dark:text-slate-300">Bisherige Prüfungen</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Die letzten <?= count($recent) ?> Prüfungen<?= $filter !== '' ? ' zu „' . e($filter) . '“' : '' ?>, neueste zuerst.</p>
        </div>
        <form method="get" action="/elektropruefung" class="flex flex-wrap items-center gap-2">
            <input name="suche" value="<?= e($filter) ?>" placeholder="Nummer oder Artikel" aria-label="Prüfungen filtern"
                   class="rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700" style="width:14rem">
            <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Filtern</button>
            <?php if ($filter !== ''): ?><a href="/elektropruefung" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Zurücksetzen</a><?php endif; ?>
        </form>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <?php if ($recent === []): ?>
            <p class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">Keine Prüfungen gefunden.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm" style="min-width:720px">
                    <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-2.5">Geprüft am</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Inventurnummer</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Gerät</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Ergebnis</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Gültig bis</th>
                            <th class="whitespace-nowrap px-4 py-2.5">Prüfer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($recent as $entry): ?>
                            <?php $inspection = $entry->inspection; $status = $inspection->status(); ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800">
                                <td class="whitespace-nowrap px-4 py-2.5 text-slate-700 dark:text-slate-300"><?= e($inspection->inspectedAt->format('d.m.Y')) ?></td>
                                <td class="whitespace-nowrap px-4 py-2.5"><a href="/inventurnummern/<?= e($entry->inventoryNumberId) ?>/elektropruefungen" class="font-mono text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e($entry->inventoryNumber) ?></a></td>
                                <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300" style="min-width:12rem"><?= e($entry->articleLabel) ?></td>
                                <td class="whitespace-nowrap px-4 py-2.5"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium <?= $status->badgeClasses() ?>"><?= $inspection->passed ? 'bestanden' : 'nicht bestanden' ?> · <?= e($status->label()) ?></span></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-slate-600 dark:text-slate-400"><?= e($inspection->validUntil()->format('d.m.Y')) ?></td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-slate-600 dark:text-slate-400"><?= e($inspection->inspector ?? '–') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
