<?php
/** @var \App\Application\Offer\Query\OfferDetail $detail */
/** @var \App\Domain\Venue\Venue|null $venue */

use App\Application\Offer\Query\OfferRow;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$offer = $detail->offer;
$status = $offer->status;
// Vollständige Summenberechnung (Mietpreise -> Zubehörpauschale ->
// Kombirabatt -> Nettosumme), siehe App\Domain\Pricing\DocumentTotals.
$totals = $detail->totals();
// Reguläre Mietpositionen + Freitext-Posten + Zwischentexte, gemeinsam nach
// sortOrder sortiert (orderedRows()-Konzept, siehe OfferDetail). Arbeitszeit-
// /Verkaufspositionen bleiben davon bewusst unberührt (siehe Klassendoc) und
// werden weiterhin unten separat gerendert.
$rows = $detail->orderedRows();
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/angebote" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('offers.show.back')) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">
            <?= e(t('offers.show.heading', ['number' => $offer->offerNumber])) ?>
        </h1>
        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($status->badgeClasses()) ?>"><?= e($status->label()) ?></span>
            <?php if ($offer->isExpiredForDisplay()): ?>
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-400"><?= e(t('offers.badge.expired')) ?></span>
            <?php endif; ?>
            · <?= $detail->customer !== null ? e($detail->customer->displayName()) : e(t('offers.show.no_customer')) ?>
            · <?= $offer->eventLocation !== null ? e($offer->eventLocation) : e(t('offers.show.no_location')) ?>
            · <?= e($offer->startDate->format('d.m.Y')) ?> – <?= e($offer->endDate->format('d.m.Y')) ?>
        </p>
        <?php if ($offer->validUntil !== null): ?>
            <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('offers.show.valid_until', ['date' => $offer->validUntil->format('d.m.Y')])) ?></p>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <?php if ($offer->status === \App\Domain\Offer\OfferStatus::Draft): ?>
            <form method="post" action="/angebote/<?= e($offer->id) ?>/versendet">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('offers.show.mark_sent')) ?></button>
            </form>
            <a href="/angebote/<?= e($offer->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
            <form method="post" action="/angebote/<?= e($offer->id) ?>/loeschen" data-confirm="<?= e(t('offers.show.confirm_delete')) ?>" data-confirm-style="danger">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('common.delete')) ?></button>
            </form>
        <?php elseif ($offer->status === \App\Domain\Offer\OfferStatus::Sent): ?>
            <form method="post" action="/angebote/<?= e($offer->id) ?>/angenommen">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"><?= e(t('offers.show.mark_accepted')) ?></button>
            </form>
            <form method="post" action="/angebote/<?= e($offer->id) ?>/abgelehnt">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('offers.show.mark_declined')) ?></button>
            </form>
            <a href="/angebote/<?= e($offer->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('offers.show.edit_valid_until')) ?></a>
        <?php endif; ?>

        <a href="/angebote/<?= e($offer->id) ?>/angebot.pdf" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('offers.show.export_pdf')) ?>
        </a>
        <form method="post" action="/angebote/<?= e($offer->id) ?>/rechnung-erstellen" data-confirm="<?= e(t('offers.show.confirm_create_invoice')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('offers.show.create_invoice')) ?></button>
        </form>
        <form method="post" action="/angebote/<?= e($offer->id) ?>/auftragsbestaetigung-erstellen">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('offers.show.create_order_confirmation')) ?></button>
        </form>
        <details class="relative">
            <summary class="cursor-pointer list-none rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                <?= e(t('offers.show.send_email')) ?>
            </summary>
            <form method="post" action="/angebote/<?= e($offer->id) ?>/email"
                  class="absolute right-0 z-10 mt-2 w-80 space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.show.recipient_email')) ?></label>
                    <input type="email" name="recipient_email" required
                           value="<?= e($detail->customer->email ?? '') ?>"
                           class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('offers.show.note_optional')) ?></label>
                    <textarea name="note" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800"></textarea>
                </div>
                <button type="submit" class="w-full rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('offers.show.send_offer')) ?></button>
            </form>
        </details>
    </div>
</div>

<?php if ($offer->convertedRentalId !== null): ?>
    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/30 dark:text-green-300">
        <?= t('offers.show.converted_notice', ['link' => '<a href="/veranstaltungen/' . e($offer->convertedRentalId) . '" class="underline font-medium">' . e(t('offers.show.converted_link')) . '</a>']) ?>
    </div>
<?php elseif ($offer->status !== \App\Domain\Offer\OfferStatus::Declined): ?>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border <?= $offer->status === \App\Domain\Offer\OfferStatus::Accepted ? 'border-green-300 bg-green-50 dark:border-green-900 dark:bg-green-950/30' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' ?> p-4">
        <p class="text-sm <?= $offer->status === \App\Domain\Offer\OfferStatus::Accepted ? 'text-green-800 dark:text-green-300' : 'text-slate-600 dark:text-slate-400' ?>">
            <?= e(t('offers.show.convert_hint')) ?>
        </p>
        <form method="post" action="/angebote/<?= e($offer->id) ?>/umwandeln" data-confirm="<?= e(t('offers.show.confirm_convert')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"><?= e(t('offers.show.convert_button')) ?></button>
        </form>
    </div>
<?php endif; ?>

<?php if ($venue !== null): ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('offers.show.venue_title')) ?></h2>
        <p class="text-sm text-slate-600 dark:text-slate-400"><?= e($venue->name) ?> · <?= e($venue->fullAddress()) ?></p>
    </div>
<?php endif; ?>

<?php if ($offer->note !== null && trim($offer->note) !== ''): ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('offers.show.note_title')) ?></h2>
        <p class="whitespace-pre-line text-sm text-slate-600 dark:text-slate-400"><?= e($offer->note) ?></p>
    </div>
<?php endif; ?>

<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('offers.show.items_title')) ?></h2>

    <?php if ($rows === []): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('offers.show.no_items')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[480px] text-left text-sm">
            <thead class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-2 py-2"><?= e(t('offers.show.article_col')) ?></th>
                    <th class="px-2 py-2"><?= e(t('offers.show.quantity_col')) ?></th>
                    <th class="px-2 py-2"><?= e(t('offers.show.unit_price_col')) ?></th>
                    <th class="px-2 py-2 text-right"><?= e(t('offers.show.line_total_col')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($rows as $row): ?>
                    <?php if ($row->type === OfferRow::TYPE_SECTION): ?>
                        <tr class="bg-slate-50 dark:bg-slate-800/60">
                            <td class="px-2 py-2 font-semibold text-slate-700 dark:text-slate-300" colspan="4"><?= e($row->data->heading) ?></td>
                        </tr>
                    <?php elseif ($row->type === OfferRow::TYPE_FREE_TEXT): ?>
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
                    <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('offers.show.rental_subtotal')) ?></td>
                    <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300"><?= number_format($totals->rentalNetTotal, 2, ',', '.') ?> €</td>
                </tr>
                <?php if ($offer->accessoryFlatRateEnabled && $offer->accessoryFlatRatePercent !== null): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('offers.show.accessory_flat_rate', ['rate' => number_format((float) $offer->accessoryFlatRatePercent, 2, ',', '.')])) ?></td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">+<?= number_format($totals->accessoryFlatRateAmount, 2, ',', '.') ?> €</td>
                    </tr>
                <?php endif; ?>
                <?php if ($offer->discountType !== null && $offer->discountValue !== null): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3">
                            <?= $offer->discountType === 'percent'
                                ? e(t('offers.show.discount_percent', ['rate' => number_format((float) $offer->discountValue, 2, ',', '.')]))
                                : e(t('offers.show.discount_fixed')) ?>
                        </td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">−<?= number_format($totals->discountAmount, 2, ',', '.') ?> €</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($detail->laborItems as $laborEntry): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3">
                            <?= e(t('offers.show.labor_item', [
                                'name' => $laborEntry->laborTimeArticle->name,
                                'hours' => number_format((float) $laborEntry->item->hours, 2, ',', '.'),
                                'rate' => number_format((float) $laborEntry->item->unitRatePerHour, 2, ',', '.'),
                            ])) ?>
                        </td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">+<?= number_format($laborEntry->lineTotal(), 2, ',', '.') ?> €</td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($detail->saleItems as $saleEntry): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3">
                            <?= e($saleEntry->displayName()) ?>
                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                (<?= $saleEntry->item->inventoryNumberId !== null
                                    ? e(t('offers.show.sale_item_source_pool', ['number' => $saleEntry->inventoryNumber?->number ?? '?']))
                                    : e(t('offers.show.sale_item_source_stock', ['quantity' => $saleEntry->item->quantity])) ?>)
                            </span>
                            <span class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium <?= $saleEntry->item->isFulfilled() ? 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' ?>">
                                <?= $saleEntry->item->isFulfilled() ? e(t('offers.show.sale_item_fulfilled')) : e(t('offers.show.sale_item_open')) ?>
                            </span>
                        </td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">+<?= number_format($saleEntry->lineTotal(), 2, ',', '.') ?> €</td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($detail->freeTextTotal() > 0): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('offers.show.free_text_total')) ?></td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">+<?= number_format($detail->freeTextTotal(), 2, ',', '.') ?> €</td>
                    </tr>
                <?php endif; ?>
                <tr class="border-t border-slate-200 dark:border-slate-700">
                    <td class="px-2 py-2 font-semibold text-slate-800 dark:text-slate-200" colspan="3"><?= e(t('offers.show.grand_total')) ?></td>
                    <td class="px-2 py-2 text-right font-semibold text-slate-900 dark:text-slate-100"><?= number_format($totals->grossTotal, 2, ',', '.') ?> €</td>
                </tr>
            </tfoot>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($offer->closingText !== null && trim($offer->closingText) !== ''): ?>
<div class="mt-6 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <div class="max-w-none text-sm leading-relaxed text-slate-700 dark:text-slate-300
                [&_h1]:mb-3 [&_h1]:mt-6 [&_h1]:text-lg [&_h1]:font-semibold [&_h1]:text-slate-900 dark:[&_h1]:text-slate-100
                [&_h2]:mb-2 [&_h2]:mt-5 [&_h2]:text-base [&_h2]:font-semibold [&_h2]:text-slate-900 dark:[&_h2]:text-slate-100
                [&_h3]:mb-2 [&_h3]:mt-4 [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:text-slate-900 dark:[&_h3]:text-slate-100
                [&_p]:mb-3
                [&_ul]:mb-3 [&_ul]:list-disc [&_ul]:pl-5
                [&_ol]:mb-3 [&_ol]:list-decimal [&_ol]:pl-5
                [&_li]:mb-1
                [&_strong]:font-semibold">
        <?= $offer->closingText ?>
    </div>
</div>
<?php endif; ?>
