<?php
/** @var \App\Application\InvoiceCorrection\Query\InvoiceCorrectionListEntry[] $entries */
/** @var string $search */

use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;

$money = static fn (float $v): string => number_format($v, 2, ',', '.') . ' €';
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Rechnungskorrekturen</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Stornorechnungen und Teilkorrekturen. Neue Korrekturen legst du in der jeweiligen Rechnung an.</p>
    </div>
    <a href="/rechnungen" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Zu den Rechnungen</a>
</div>

<form method="get" action="/rechnungskorrekturen" class="mb-4 flex gap-2">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Korrektur-, Rechnungsnummer oder Grund…" class="w-full max-w-sm rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
    <button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Suchen</button>
</form>

<?php if ($entries === []): ?>
    <div class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
        <?= $search !== '' ? 'Keine Rechnungskorrekturen gefunden.' : 'Noch keine Rechnungskorrekturen vorhanden.' ?>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2.5">Nummer</th>
                    <th class="px-4 py-2.5">Art</th>
                    <th class="px-4 py-2.5">Rechnung</th>
                    <th class="px-4 py-2.5">Kunde</th>
                    <th class="px-4 py-2.5">Datum</th>
                    <th class="px-4 py-2.5 text-right">Betrag brutto</th>
                    <th class="px-4 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($entries as $entry): ?>
                    <?php $correction = $entry->correction; ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3"><a href="/rechnungskorrekturen/<?= e($correction->id) ?>" class="font-mono font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e($correction->correctionNumber ?? 'Entwurf') ?></a></td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($correction->type->label()) ?></td>
                        <td class="px-4 py-3"><a href="/rechnungen/<?= e($correction->invoiceId) ?>" class="font-mono text-xs text-slate-600 hover:underline dark:text-slate-400"><?= e($entry->invoiceNumber ?? '–') ?></a></td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($entry->customerName ?? '–') ?></td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400" data-sort-value="<?= e($correction->correctionDate?->format('Y-m-d') ?? '') ?>"><?= e($correction->correctionDate?->format('d.m.Y') ?? '–') ?></td>
                        <td class="px-4 py-3 text-right  <?= $correction->grossTotal() < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-300' ?>" style="font-variant-numeric:tabular-nums" data-sort-value="<?= $correction->grossTotal() ?>"><?= e($money($correction->grossTotal())) ?></td>
                        <td class="px-4 py-3"><?= View::render('invoice-corrections/_badge', ['correction' => $correction]) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
