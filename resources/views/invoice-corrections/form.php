<?php
/** @var \App\Application\InvoiceCorrection\Query\InvoiceCorrectionDetail $detail */
/** @var \App\Domain\InvoiceCorrection\InvoiceCorrectionType $type */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use App\Domain\InvoiceCorrection\InvoiceCorrectionType;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$correction = $detail->correction;
$invoice = $detail->invoice->invoice;
$isCancellation = $type === InvoiceCorrectionType::Cancellation;
$submitted = isset($old['_submitted']);
$val = static fn (string $key, ?string $fallback) => $submitted ? (string) ($old[$key] ?? '') : (string) ($fallback ?? '');
$err = static fn (string $key) => isset($errors[$key]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$key]) . '</p>' : '';
$money = static fn (float $v): string => number_format($v, 2, ',', '.') . ' €';
$plain = static fn (string $v): string => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
$priceText = static fn (string $v): string => number_format((float) $v, 2, ',', '');

$isSmallBusiness = $correction?->isSmallBusiness ?? ($invoice->isSmallBusiness ?? false);
$vatRate = (float) ($correction?->vatRatePercent ?? ($invoice->vatRatePercent ?? '19'));

if ($submitted) {
    $lines = $old['lines'] ?? [];
} elseif ($correction !== null) {
    $lines = array_map(static fn ($l) => ['description' => $l->description, 'quantity' => $plain($l->quantity), 'unit_price' => $priceText($l->unitPrice)], $correction->lines);
} else {
    $lines = [];
}

if ($isCancellation) {
    // Vorschau: Positionen der Rechnung negativ (bisherige Teilkorrekturen werden beim Ausstellen ebenfalls umgekehrt).
    $previewLines = $correction !== null
        ? array_map(static fn ($l) => ['description' => $l->description, 'quantity' => $l->quantity, 'unitPrice' => $l->unitPrice], $correction->lines)
        : array_map(static fn ($l) => ['description' => $l['description'], 'quantity' => $l['quantity'], 'unitPrice' => number_format(-(float) $l['unitPrice'], 2, '.', '')], $detail->invoiceLines);
}
$action = $correction !== null ? '/rechnungskorrekturen/' . $correction->id : '/rechnungen/' . $invoice->id . '/korrekturen';
$inputClass = 'w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
?>
<div class="mb-6">
    <a href="<?= $correction !== null ? '/rechnungskorrekturen/' . e($correction->id) : '/rechnungen/' . e($invoice->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($type->label()) ?> zu Rechnung <?= e($invoice->invoiceNumber ?? '') ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= $isCancellation
            ? 'Hebt die Rechnung vollständig auf: alle Positionen werden negativ übernommen. Ist die Rechnung noch offen, wird sie beim Ausstellen auf „Storniert“ gesetzt.'
            : 'Weist nur die Änderung aus: Positionen, die wegfallen oder günstiger werden, mit negativem Betrag; zusätzliche Leistungen mit positivem Betrag.' ?>
    </p>
</div>

<div class="mb-6 grid gap-4 rounded-lg border border-slate-200 bg-white p-4 text-sm sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-900">
    <div><div class="text-xs text-slate-500 dark:text-slate-400">Rechnung</div><a href="/rechnungen/<?= e($invoice->id) ?>" class="font-mono text-brand-700 hover:underline dark:text-brand-400"><?= e($invoice->invoiceNumber ?? '–') ?></a> vom <?= e($invoice->invoiceDate?->format('d.m.Y') ?? '–') ?></div>
    <div><div class="text-xs text-slate-500 dark:text-slate-400">Kunde</div><?= e($detail->invoice->customer?->displayName() ?? '–') ?></div>
    <div><div class="text-xs text-slate-500 dark:text-slate-400">Rechnungsbetrag brutto</div><?= e($money($detail->invoiceGrossTotal)) ?></div>
    <div><div class="text-xs text-slate-500 dark:text-slate-400">Nach bisherigen Korrekturen</div><?= e($money($detail->grossBefore())) ?><?php if ($detail->issuedCorrections !== []): ?> <span class="text-xs text-slate-500">(<?= count($detail->issuedCorrections) ?> Korrektur<?= count($detail->issuedCorrections) > 1 ? 'en' : '' ?>)</span><?php endif; ?></div>
</div>

<form method="post" action="<?= e($action) ?>" class="space-y-6" id="invoice-correction-form"
      data-vat-rate="<?= e((string) $vatRate) ?>" data-small-business="<?= $isSmallBusiness ? '1' : '0' ?>" data-gross-before="<?= e((string) $detail->grossBefore()) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
    <input type="hidden" name="type" value="<?= e($type->value) ?>">

    <div class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div>
            <label for="reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Grund der Korrektur *</label>
            <textarea id="reason" name="reason" rows="2" required class="mt-1 <?= $inputClass ?>" placeholder="<?= $isCancellation ? 'z. B. Veranstaltung abgesagt, Rechnung an falschen Empfänger' : 'z. B. Funkstrecke nicht genutzt, Rabatt nachträglich gewährt' ?>"><?= e($val('reason', $correction?->reason)) ?></textarea>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Erscheint auf dem Dokument.</p>
            <?= $err('reason') ?>
        </div>
        <div>
            <label for="note" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Zusätzlicher Hinweis (optional)</label>
            <textarea id="note" name="note" rows="2" class="mt-1 <?= $inputClass ?>"><?= e($val('note', $correction?->note)) ?></textarea>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Positionen <span class="font-normal text-slate-500">(Beträge netto)</span></h2>
        <?= $err('lines') ?>

        <?php if ($isCancellation): ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr><th class="px-2 py-2">Bezeichnung</th><th class="px-2 py-2 text-right">Menge</th><th class="px-2 py-2 text-right">Einzelpreis</th><th class="px-2 py-2 text-right">Summe</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php $net = 0.0; foreach ($previewLines as $line): $sum = round((float) $line['quantity'] * (float) $line['unitPrice'], 2); $net += $sum; ?>
                            <tr>
                                <td class="px-2 py-2 text-slate-700 dark:text-slate-300"><?= e($line['description']) ?></td>
                                <td class="px-2 py-2 text-right " style="font-variant-numeric:tabular-nums"><?= e($plain($line['quantity'])) ?></td>
                                <td class="px-2 py-2 text-right " style="font-variant-numeric:tabular-nums"><?= e($money((float) $line['unitPrice'])) ?></td>
                                <td class="px-2 py-2 text-right  text-red-600 dark:text-red-400" style="font-variant-numeric:tabular-nums"><?= e($money($sum)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($detail->issuedCorrections !== [] && $correction === null): ?>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Die bereits ausgestellten Teilkorrekturen werden beim Anlegen ebenfalls umgekehrt, sodass sich insgesamt 0 € ergeben.</p>
            <?php endif; ?>
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Die Positionen werden beim Ausstellen noch einmal aus der Rechnung übernommen und lassen sich nicht ändern.</p>
        <?php else: ?>
            <?php if ($detail->invoiceLines !== []): ?>
                <div class="mb-4 flex flex-wrap items-end gap-2 rounded-md bg-slate-50 p-3 dark:bg-slate-800/60">
                    <div class="min-w-0 flex-1">
                        <label for="source_line" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Position aus der Rechnung übernehmen</label>
                        <select id="source_line" class="mt-1 <?= $inputClass ?>">
                            <?php foreach ($detail->invoiceLines as $line): ?>
                                <option data-description="<?= e($line['description']) ?>" data-quantity="<?= e($plain($line['quantity'])) ?>" data-price="<?= e($priceText($line['unitPrice'])) ?>"><?= e($line['description']) ?> – <?= e($plain($line['quantity'])) ?> × <?= e($money((float) $line['unitPrice'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" data-take-line="credit" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">Als Gutschrift (−)</button>
                    <button type="button" data-take-line="charge" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">Als Nachberechnung (+)</button>
                </div>
            <?php endif; ?>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr><th class="px-2 py-2">Bezeichnung</th><th class="px-2 py-2" style="width:7rem">Menge</th><th class="px-2 py-2" style="width:9rem">Einzelpreis</th><th class="px-2 py-2 text-right" style="width:8rem">Summe</th><th class="px-2 py-2" style="width:2.5rem"></th></tr>
                    </thead>
                    <tbody id="correction-lines" data-next-index="<?= count($lines) ?>">
                        <?php foreach (array_values($lines) as $i => $line): ?>
                            <tr data-line>
                                <td class="px-2 py-1.5"><input type="text" name="lines[<?= $i ?>][description]" value="<?= e((string) ($line['description'] ?? '')) ?>" maxlength="500" class="<?= $inputClass ?>"></td>
                                <td class="px-2 py-1.5"><input type="text" inputmode="decimal" name="lines[<?= $i ?>][quantity]" value="<?= e((string) ($line['quantity'] ?? '1')) ?>" class="<?= $inputClass ?> text-right" data-qty></td>
                                <td class="px-2 py-1.5"><input type="text" inputmode="decimal" name="lines[<?= $i ?>][unit_price]" value="<?= e((string) ($line['unit_price'] ?? '')) ?>" class="<?= $inputClass ?> text-right" data-price placeholder="-0,00"></td>
                                <td class="px-2 py-1.5 text-right " style="font-variant-numeric:tabular-nums" data-sum>–</td>
                                <td class="px-2 py-1.5 text-right"><button type="button" data-remove-line class="rounded-md px-2 py-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Position entfernen">✕</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <template id="correction-line-template">
                <tr data-line>
                    <td class="px-2 py-1.5"><input type="text" data-name="description" maxlength="500" class="<?= $inputClass ?>"></td>
                    <td class="px-2 py-1.5"><input type="text" inputmode="decimal" data-name="quantity" value="1" class="<?= $inputClass ?> text-right" data-qty></td>
                    <td class="px-2 py-1.5"><input type="text" inputmode="decimal" data-name="unit_price" class="<?= $inputClass ?> text-right" data-price placeholder="-0,00"></td>
                    <td class="px-2 py-1.5 text-right " style="font-variant-numeric:tabular-nums" data-sum>–</td>
                    <td class="px-2 py-1.5 text-right"><button type="button" data-remove-line class="rounded-md px-2 py-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Position entfernen">✕</button></td>
                </tr>
            </template>
            <button type="button" id="add-correction-line" class="mt-3 rounded-md border border-dashed border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">+ Freie Position</button>

            <dl class="mt-4 ml-auto max-w-xs space-y-1 border-t border-slate-100 pt-3 text-sm dark:border-slate-800">
                <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">Korrektur netto</dt><dd class="" style="font-variant-numeric:tabular-nums" data-total="net">–</dd></div>
                <?php if (!$isSmallBusiness): ?>
                    <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">USt <?= e(rtrim(rtrim(number_format($vatRate, 2, ',', ''), '0'), ',')) ?> %</dt><dd class="" style="font-variant-numeric:tabular-nums" data-total="tax">–</dd></div>
                <?php endif; ?>
                <div class="flex justify-between font-semibold"><dt>Korrektur brutto</dt><dd class="" style="font-variant-numeric:tabular-nums" data-total="gross">–</dd></div>
                <div class="flex justify-between text-slate-600 dark:text-slate-400"><dt>Neuer Rechnungsbetrag</dt><dd class="" style="font-variant-numeric:tabular-nums" data-total="after">–</dd></div>
            </dl>
        <?php endif; ?>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= $correction !== null ? 'Entwurf speichern' : 'Als Entwurf anlegen' ?></button>
        <a href="<?= $correction !== null ? '/rechnungskorrekturen/' . e($correction->id) : '/rechnungen/' . e($invoice->id) ?>" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Abbrechen</a>
    </div>
</form>
<?php if (!$isCancellation): ?>
<script src="/assets/js/invoice-correction-form.js" defer></script>
<?php endif; ?>
