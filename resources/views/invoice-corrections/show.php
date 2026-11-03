<?php
/** @var \App\Application\InvoiceCorrection\Query\InvoiceCorrectionDetail $detail */

use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;

$correction = $detail->correction;
$invoice = $detail->invoice->invoice;
$money = static fn (float $v): string => number_format($v, 2, ',', '.') . ' €';
$plain = static fn (string $v): string => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
$btn = 'rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
$gross = $correction->grossTotal();
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/rechnungen/<?= e($invoice->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Rechnung <?= e($invoice->invoiceNumber ?? '') ?></a>
        <h1 class="mt-1 flex flex-wrap items-center gap-2 text-xl font-semibold text-slate-900 dark:text-slate-100">
            <?= e($correction->title()) ?>
            <?= View::render('invoice-corrections/_badge', ['correction' => $correction]) ?>
        </h1>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <?php if ($correction->isDraft()): ?>
            <form method="post" action="/rechnungskorrekturen/<?= e($correction->id) ?>/ausstellen" data-confirm="<?= e($correction->type->label()) ?> jetzt ausstellen? Danach wird eine Nummer vergeben und das Dokument kann nicht mehr geändert werden.">
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Ausstellen</button>
            </form>
            <a href="/rechnungskorrekturen/<?= e($correction->id) ?>/bearbeiten" class="<?= $btn ?>">Bearbeiten</a>
            <form method="post" action="/rechnungskorrekturen/<?= e($correction->id) ?>/loeschen" data-confirm="Diesen Entwurf löschen?" data-confirm-style="danger">
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">Löschen</button>
            </form>
        <?php endif; ?>
        <a href="/rechnungskorrekturen/<?= e($correction->id) ?>/pdf" target="_blank" class="<?= $btn ?>"><?= $correction->isDraft() ? 'PDF-Vorschau' : 'PDF' ?></a>
    </div>
</div>

<?php if ($correction->awaitsSettlement()): ?>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900 dark:bg-amber-950/30">
        <p class="text-amber-800 dark:text-amber-300">
            Die Rechnung war beim Ausstellen bereits bezahlt.
            <?= $gross < 0 ? 'Dem Kunden müssen ' . e($money(-$gross)) . ' erstattet werden.' : 'Der Kunde muss ' . e($money($gross)) . ' nachzahlen.' ?>
        </p>
        <form method="post" action="/rechnungskorrekturen/<?= e($correction->id) ?>/erledigt" class="flex items-end gap-2">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <div>
                <label for="settled_at" class="block text-xs text-amber-800 dark:text-amber-300"><?= $gross < 0 ? 'Erstattet am' : 'Bezahlt am' ?></label>
                <input type="date" id="settled_at" name="settled_at" value="<?= e((new DateTimeImmutable('today'))->format('Y-m-d')) ?>" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
            </div>
            <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">Als erledigt vermerken</button>
        </form>
    </div>
<?php endif; ?>

<div class="mb-6 grid gap-4 rounded-lg border border-slate-200 bg-white p-4 text-sm sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-900">
    <div><div class="text-xs text-slate-500 dark:text-slate-400">Datum</div><?= e($correction->correctionDate?->format('d.m.Y') ?? 'wird beim Ausstellen gesetzt') ?></div>
    <div><div class="text-xs text-slate-500 dark:text-slate-400">Bezug</div>Rechnung <a href="/rechnungen/<?= e($invoice->id) ?>" class="font-mono text-brand-700 hover:underline dark:text-brand-400"><?= e($invoice->invoiceNumber ?? '–') ?></a> vom <?= e($invoice->invoiceDate?->format('d.m.Y') ?? '–') ?></div>
    <div><div class="text-xs text-slate-500 dark:text-slate-400">Kunde</div><?= e($detail->invoice->customer?->displayName() ?? '–') ?></div>
    <div><div class="text-xs text-slate-500 dark:text-slate-400"><?= $correction->settledAt !== null ? ($gross < 0 ? 'Erstattet am' : 'Bezahlt am') : 'Rechnungsstatus' ?></div><?= e($correction->settledAt?->format('d.m.Y') ?? $invoice->status->label()) ?></div>
</div>

<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300">Grund</h2>
    <p class="whitespace-pre-line text-slate-600 dark:text-slate-400"><?= e($correction->reason) ?></p>
    <?php if ($correction->note !== null): ?>
        <h2 class="mb-1 mt-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Hinweis</h2>
        <p class="whitespace-pre-line text-slate-600 dark:text-slate-400"><?= e($correction->note) ?></p>
    <?php endif; ?>
</div>

<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Positionen <span class="font-normal text-slate-500">(netto)</span></h2>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[560px] text-left text-sm">
            <thead class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <tr><th class="px-2 py-2">Bezeichnung</th><th class="px-2 py-2 text-right">Menge</th><th class="px-2 py-2 text-right">Einzelpreis</th><th class="px-2 py-2 text-right">Summe</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($correction->lines as $line): ?>
                    <tr>
                        <td class="px-2 py-2 text-slate-700 dark:text-slate-300"><?= e($line->description) ?></td>
                        <td class="px-2 py-2 text-right " style="font-variant-numeric:tabular-nums"><?= e($plain($line->quantity)) ?></td>
                        <td class="px-2 py-2 text-right " style="font-variant-numeric:tabular-nums"><?= e($money((float) $line->unitPrice)) ?></td>
                        <td class="px-2 py-2 text-right  <?= $line->lineTotal() < 0 ? 'text-red-600 dark:text-red-400' : '' ?>" style="font-variant-numeric:tabular-nums"><?= e($money($line->lineTotal())) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <dl class="mt-4 ml-auto max-w-xs space-y-1 border-t border-slate-100 pt-3 text-sm dark:border-slate-800">
        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Korrektur netto</dt><dd class="" style="font-variant-numeric:tabular-nums"><?= e($money($correction->netTotal())) ?></dd></div>
        <?php if (!$correction->isSmallBusiness): ?>
            <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">USt <?= e($plain($correction->vatRatePercent)) ?> %</dt><dd class="" style="font-variant-numeric:tabular-nums"><?= e($money($correction->taxAmount())) ?></dd></div>
        <?php endif; ?>
        <div class="flex justify-between font-semibold"><dt>Korrektur brutto</dt><dd class="" style="font-variant-numeric:tabular-nums"><?= e($money($gross)) ?></dd></div>
        <div class="flex justify-between border-t border-slate-100 pt-1 text-slate-600 dark:border-slate-800 dark:text-slate-400"><dt>Rechnungsbetrag bisher</dt><dd class="" style="font-variant-numeric:tabular-nums"><?= e($money($detail->grossBefore())) ?></dd></div>
        <div class="flex justify-between font-medium text-slate-700 dark:text-slate-300"><dt>Neuer Rechnungsbetrag</dt><dd class="" style="font-variant-numeric:tabular-nums"><?= e($money($detail->grossAfter())) ?></dd></div>
    </dl>
</div>
