<?php
/**
 * Abschnitt "Rechnungskorrekturen" in der Rechnungs-Detailansicht (Migration 121).
 *
 * @var \App\Domain\Invoice\Invoice $invoice
 * @var \App\Application\InvoiceCorrection\Query\InvoiceCorrectionListEntry[] $corrections
 * @var float $invoiceGross
 */

use App\Domain\Invoice\InvoiceStatus;
use App\Domain\InvoiceCorrection\InvoiceCorrectionType;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;

$money = static fn (float $v): string => number_format($v, 2, ',', '.') . ' €';
$hasCancellation = false;
$issuedSum = 0.0;
foreach ($corrections as $entry) {
    $hasCancellation = $hasCancellation || $entry->correction->type === InvoiceCorrectionType::Cancellation;
    if (!$entry->correction->isDraft()) {
        $issuedSum += $entry->correction->grossTotal();
    }
}
$canCorrect = !$hasCancellation && in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Paid], true);
if ($corrections === [] && !$canCorrect) {
    return;
}
?>
<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Rechnungskorrekturen</h2>
        <?php if ($canCorrect): ?>
            <div class="flex flex-wrap gap-2">
                <a href="/rechnungen/<?= e($invoice->id) ?>/korrektur/neu" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Rechnung korrigieren</a>
                <a href="/rechnungen/<?= e($invoice->id) ?>/korrektur/neu?typ=storno" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">Stornorechnung erstellen</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($corrections === []): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400">Noch keine Korrekturen. Eine versendete Rechnung wird nicht mehr geändert – Änderungen erfolgen über eine Rechnungskorrektur (Teilbetrag) oder eine Stornorechnung (komplett).</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
            <?php foreach ($corrections as $entry): $c = $entry->correction; ?>
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span class="flex flex-wrap items-center gap-2">
                        <a href="/rechnungskorrekturen/<?= e($c->id) ?>" class="font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e($c->title()) ?></a>
                        <?php if ($c->correctionDate !== null): ?><span class="text-xs text-slate-500 dark:text-slate-400"><?= e($c->correctionDate->format('d.m.Y')) ?></span><?php endif; ?>
                        <?= View::render('invoice-corrections/_badge', ['correction' => $c]) ?>
                    </span>
                    <span class="flex items-center gap-3">
                        <span class=" <?= $c->grossTotal() < 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-300' ?>" style="font-variant-numeric:tabular-nums"><?= e($money($c->grossTotal())) ?></span>
                        <a href="/rechnungskorrekturen/<?= e($c->id) ?>/pdf" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">PDF</a>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (abs($issuedSum) >= 0.005): ?>
            <p class="mt-3 flex justify-between border-t border-slate-100 pt-3 text-sm font-medium text-slate-700 dark:border-slate-800 dark:text-slate-300">
                <span>Rechnungsbetrag nach Korrekturen</span>
                <span class="" style="font-variant-numeric:tabular-nums"><?= e($money($invoiceGross + $issuedSum)) ?></span>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
