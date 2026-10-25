<?php
/** @var \App\Application\Invoice\Query\InvoiceDetail $detail */
/** @var \App\Domain\Venue\Venue|null $venue */
/** @var \App\Domain\Settings\InvoiceSettings $invoiceSettings */
/** @var \App\Domain\Dunning\DunningNotice[] $dunningNotices */
/** @var \App\Domain\Dunning\DunningLevel[] $dunningLevels */

use App\Application\Invoice\Query\InvoiceRow;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$invoice = $detail->invoice;
$status = $invoice->status;
$isOverdue = $status === \App\Domain\Invoice\InvoiceStatus::Sent && $invoice->dueDate !== null && $invoice->dueDate < new DateTimeImmutable('today');

$isSmallBusiness = $invoice->isSmallBusiness ?? $invoiceSettings->isSmallBusiness;
$vatRatePercent = $invoice->vatRatePercent ?? $invoiceSettings->vatRatePercent;
// Vollständige Summenberechnung (Mietpreise -> Zubehörpauschale ->
// Kombirabatt -> Netto/USt/Brutto), siehe App\Domain\Pricing\DocumentTotals.
$totals = $detail->totals($invoiceSettings);
// Reguläre Mietpositionen + Freitext-Posten + Zwischentexte, gemeinsam nach
// sortOrder sortiert (orderedRows()-Konzept, siehe InvoiceDetail).
// Arbeitszeitpositionen bleiben davon bewusst unberührt und werden weiterhin
// separat gerendert.
$rows = $detail->orderedRows();
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/rechnungen" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('invoices.show.back')) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">
            <?= $invoice->invoiceNumber !== null
                ? e(t('invoices.show.heading', ['number' => $invoice->invoiceNumber]))
                : e(t('invoices.show.heading_draft')) ?>
        </h1>
        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($status->badgeClasses()) ?>"><?= e($status->label()) ?></span>
            · <?= $detail->customer !== null ? e($detail->customer->displayName()) : e(t('invoices.show.no_customer')) ?>
            · <?= $invoice->eventLocation !== null ? e($invoice->eventLocation) : e(t('invoices.show.no_location')) ?>
            · <?= e($invoice->startDate->format('d.m.Y')) ?> – <?= e($invoice->endDate->format('d.m.Y')) ?>
        </p>
        <?php if ($invoice->invoiceDate !== null): ?>
            <p class="text-xs text-slate-400 dark:text-slate-500">
                <?= e(t('invoices.show.invoice_date', ['date' => $invoice->invoiceDate->format('d.m.Y')])) ?>
                <?php if ($invoice->dueDate !== null): ?>
                    · <?= e(t('invoices.show.due_date', ['date' => $invoice->dueDate->format('d.m.Y')])) ?>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('invoices.show.not_sent_yet')) ?></p>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <?php if ($status === \App\Domain\Invoice\InvoiceStatus::Draft): ?>
            <form method="post" action="/rechnungen/<?= e($invoice->id) ?>/versenden" data-confirm="<?= e(t('invoices.show.confirm_mark_sent')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('invoices.show.mark_sent')) ?></button>
            </form>
            <a href="/rechnungen/<?= e($invoice->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
            <form method="post" action="/rechnungen/<?= e($invoice->id) ?>/stornieren" data-confirm="<?= e(t('invoices.show.confirm_cancel')) ?>" data-confirm-style="danger">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('invoices.show.mark_cancelled')) ?></button>
            </form>
            <form method="post" action="/rechnungen/<?= e($invoice->id) ?>/loeschen" data-confirm="<?= e(t('invoices.show.confirm_delete')) ?>" data-confirm-style="danger">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('common.delete')) ?></button>
            </form>
        <?php elseif ($status === \App\Domain\Invoice\InvoiceStatus::Sent): ?>
            <form method="post" action="/rechnungen/<?= e($invoice->id) ?>/bezahlt">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"><?= e(t('invoices.show.mark_paid')) ?></button>
            </form>
            <form method="post" action="/rechnungen/<?= e($invoice->id) ?>/stornieren" data-confirm="<?= e(t('invoices.show.confirm_cancel')) ?>" data-confirm-style="danger">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('invoices.show.mark_cancelled')) ?></button>
            </form>
        <?php endif; ?>

        <a href="/rechnungen/<?= e($invoice->id) ?>/pdf" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('invoices.show.export_pdf')) ?>
        </a>
        <?php if ($invoice->isFinalized()): ?>
            <a href="/rechnungen/<?= e($invoice->id) ?>/xrechnung" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                <?= e(t('invoices.show.export_xrechnung')) ?>
            </a>
            <details class="relative">
                <summary class="cursor-pointer list-none rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <?= e(t('invoices.show.send_email')) ?>
                </summary>
                <form method="post" action="/rechnungen/<?= e($invoice->id) ?>/email"
                      class="absolute right-0 z-10 mt-2 w-80 space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.show.recipient_email')) ?></label>
                        <input type="email" name="recipient_email" required
                               value="<?= e($detail->customer->email ?? '') ?>"
                               class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.show.note_optional')) ?></label>
                        <textarea name="note" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('invoices.show.send_invoice')) ?></button>
                </form>
            </details>
        <?php endif; ?>
    </div>
</div>

<?php if ($venue !== null): ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('invoices.show.venue_title')) ?></h2>
        <p class="text-sm text-slate-600 dark:text-slate-400"><?= e($venue->name) ?> · <?= e($venue->fullAddress()) ?></p>
    </div>
<?php endif; ?>

<?php if ($invoice->buyerReference !== null && trim($invoice->buyerReference) !== ''): ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('invoices.show.buyer_reference_title')) ?></h2>
        <p class="text-sm text-slate-600 dark:text-slate-400"><?= e($invoice->buyerReference) ?></p>
    </div>
<?php endif; ?>

<?php if ($invoice->note !== null && trim($invoice->note) !== ''): ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('invoices.show.note_title')) ?></h2>
        <p class="whitespace-pre-line text-sm text-slate-600 dark:text-slate-400"><?= e($invoice->note) ?></p>
    </div>
<?php endif; ?>

<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('invoices.show.dunning_title')) ?></h2>

    <?php if ($dunningNotices === []): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('invoices.show.dunning_none')) ?></p>
    <?php else: ?>
        <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($dunningNotices as $notice): ?>
                <div class="flex flex-wrap items-center justify-between gap-2 px-1 py-2">
                    <span class="text-sm text-slate-700 dark:text-slate-300">
                        <?= e($notice->dunningNumber) ?> · <?= e($notice->levelName) ?> · <?= e($notice->createdAt->format('d.m.Y')) ?>
                        <?php if ($notice->sentAt !== null): ?>
                            <span class="ml-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950/40 dark:text-green-400"><?= e(t('invoices.show.dunning_sent')) ?></span>
                        <?php else: ?>
                            <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-400"><?= e(t('invoices.show.dunning_not_sent')) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="flex items-center gap-3">
                        <a href="/mahnungen/<?= e($notice->id) ?>/pdf" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('invoices.show.dunning_export_pdf')) ?></a>
                        <details class="relative">
                            <summary class="cursor-pointer list-none text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('invoices.show.send_email')) ?></summary>
                            <form method="post" action="/mahnungen/<?= e($notice->id) ?>/email"
                                  class="absolute right-0 z-10 mt-2 w-72 space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <div>
                                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('invoices.show.recipient_email')) ?></label>
                                    <input type="email" name="recipient_email" required
                                           value="<?= e($detail->customer->email ?? '') ?>"
                                           class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
                                </div>
                                <button type="submit" class="w-full rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('invoices.show.send_invoice')) ?></button>
                            </form>
                        </details>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($isOverdue && $dunningLevels !== []): ?>
        <form method="post" action="/rechnungen/<?= e($invoice->id) ?>/mahnungen" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label for="dunning_level_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('invoices.show.dunning_create_level')) ?></label>
                <select id="dunning_level_id" name="dunning_level_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                    <?php foreach ($dunningLevels as $level): ?>
                        <option value="<?= e($level->id) ?>"><?= e($level->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('invoices.show.dunning_create')) ?></button>
        </form>
    <?php elseif ($isOverdue): ?>
        <p class="border-t border-slate-100 pt-4 text-xs text-slate-400 dark:border-slate-800 dark:text-slate-500"><?= e(t('invoices.show.dunning_no_levels')) ?></p>
    <?php endif; ?>
</div>

<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('invoices.show.items_title')) ?></h2>

    <?php if ($rows === []): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('invoices.show.no_items')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[480px] text-left text-sm">
            <thead class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-2 py-2"><?= e(t('invoices.show.article_col')) ?></th>
                    <th class="px-2 py-2"><?= e(t('invoices.show.quantity_col')) ?></th>
                    <th class="px-2 py-2"><?= e(t('invoices.show.unit_price_col')) ?></th>
                    <th class="px-2 py-2 text-right"><?= e(t('invoices.show.line_total_col')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($rows as $row): ?>
                    <?php if ($row->type === InvoiceRow::TYPE_SECTION): ?>
                        <tr class="bg-slate-50 dark:bg-slate-800/60">
                            <td class="px-2 py-2 font-semibold text-slate-700 dark:text-slate-300" colspan="4"><?= e($row->data->heading) ?></td>
                        </tr>
                    <?php elseif ($row->type === InvoiceRow::TYPE_FREE_TEXT): ?>
                        <?php $item = $row->data; ?>
                        <tr>
                            <td class="px-2 py-2 text-slate-800 dark:text-slate-200"><?= e($item->description) ?></td>
                            <td class="px-2 py-2 text-slate-600 dark:text-slate-400"><?= e(rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',')) ?></td>
                            <td class="px-2 py-2 text-slate-600 dark:text-slate-400"><?= number_format((float) $item->unitPrice, 2, ',', '.') ?> €</td>
                            <td class="px-2 py-2 text-right font-medium text-slate-800 dark:text-slate-200"><?= number_format($item->lineTotal(), 2, ',', '.') ?> €</td>
                        </tr>
                    <?php else: ?>
                        <?php $entry = $row->data; ?>
                        <tr>
                            <td class="px-2 py-2 text-slate-800 dark:text-slate-200">
                                <?= e($entry->displayName()) ?>
                                <?php if ($entry->item->lineNote !== null && trim($entry->item->lineNote) !== ''): ?>
                                    <span class="block text-xs text-slate-400 dark:text-slate-500"><?= e($entry->item->lineNote) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 py-2 text-slate-600 dark:text-slate-400"><?= $entry->item->quantity ?></td>
                            <td class="px-2 py-2 text-slate-600 dark:text-slate-400"><?= number_format((float) $entry->item->unitPricePerDay, 2, ',', '.') ?> €</td>
                            <td class="px-2 py-2 text-right font-medium text-slate-800 dark:text-slate-200"><?= number_format($entry->lineTotal(), 2, ',', '.') ?> €</td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="border-t border-slate-200 dark:border-slate-700">
                    <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('invoices.show.rental_subtotal')) ?></td>
                    <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300"><?= number_format($totals->rentalNetTotal, 2, ',', '.') ?> €</td>
                </tr>
                <?php if ($invoice->accessoryFlatRateEnabled && $invoice->accessoryFlatRatePercent !== null): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('invoices.show.accessory_flat_rate', ['rate' => number_format((float) $invoice->accessoryFlatRatePercent, 2, ',', '.')])) ?></td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">+<?= number_format($totals->accessoryFlatRateAmount, 2, ',', '.') ?> €</td>
                    </tr>
                <?php endif; ?>
                <?php if ($invoice->discountType !== null && $invoice->discountValue !== null): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3">
                            <?= $invoice->discountType === 'percent'
                                ? e(t('invoices.show.discount_percent', ['rate' => number_format((float) $invoice->discountValue, 2, ',', '.')]))
                                : e(t('invoices.show.discount_fixed')) ?>
                        </td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">−<?= number_format($totals->discountAmount, 2, ',', '.') ?> €</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($detail->laborItems as $laborEntry): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3">
                            <?= e(t('invoices.show.labor_item', [
                                'name' => $laborEntry->laborTimeArticle->name,
                                'hours' => number_format((float) $laborEntry->item->hours, 2, ',', '.'),
                                'rate' => number_format((float) $laborEntry->item->unitRatePerHour, 2, ',', '.'),
                            ])) ?>
                        </td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">+<?= number_format($laborEntry->lineTotal(), 2, ',', '.') ?> €</td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($detail->freeTextTotal() > 0): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('invoices.show.free_text_total')) ?></td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">+<?= number_format($detail->freeTextTotal(), 2, ',', '.') ?> €</td>
                    </tr>
                <?php endif; ?>
                <?php if ($invoice->shippingAmount !== null): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('invoices.show.shipping')) ?></td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">+<?= number_format((float) $invoice->shippingAmount, 2, ',', '.') ?> €</td>
                    </tr>
                <?php endif; ?>
                <?php if ($isSmallBusiness): ?>
                    <tr class="border-t border-slate-200 dark:border-slate-700">
                        <td class="px-2 py-2 font-semibold text-slate-800 dark:text-slate-200" colspan="3"><?= e(t('invoices.show.grand_total')) ?></td>
                        <td class="px-2 py-2 text-right font-semibold text-slate-900 dark:text-slate-100"><?= number_format($totals->grossTotal, 2, ',', '.') ?> €</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 text-xs text-slate-400 dark:text-slate-500" colspan="4"><?= e(t('invoices.show.small_business_notice')) ?></td>
                    </tr>
                <?php else: ?>
                    <tr class="border-t border-slate-200 dark:border-slate-700">
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('invoices.show.net_total')) ?></td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300"><?= number_format($totals->netTotal, 2, ',', '.') ?> €</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('invoices.show.vat_amount', ['rate' => number_format((float) $vatRatePercent, 2, ',', '.')])) ?></td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300"><?= number_format($totals->taxAmount, 2, ',', '.') ?> €</td>
                    </tr>
                    <tr>
                        <td class="px-2 py-2 font-semibold text-slate-800 dark:text-slate-200" colspan="3"><?= e(t('invoices.show.gross_total')) ?></td>
                        <td class="px-2 py-2 text-right font-semibold text-slate-900 dark:text-slate-100"><?= number_format($totals->grossTotal, 2, ',', '.') ?> €</td>
                    </tr>
                <?php endif; ?>
            </tfoot>
        </table>
        </div>
    <?php endif; ?>
</div>
