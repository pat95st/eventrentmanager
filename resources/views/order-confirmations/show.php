<?php
/** @var \App\Application\OrderConfirmation\Query\OrderConfirmationDetail $detail */
/** @var \App\Domain\Venue\Venue|null $venue */
/** @var \App\Domain\Rental\Rental|null $linkedRental */

use App\Application\OrderConfirmation\Query\OrderConfirmationRow;
use App\Domain\OrderConfirmation\OrderConfirmationStatus;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$orderConfirmation = $detail->orderConfirmation;
$status = $orderConfirmation->status;
$totals = $detail->totals();
$rows = $detail->orderedRows();
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/auftragsbestaetigungen" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('order_confirmations.show.back')) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">
            <?= e(t('order_confirmations.show.heading', ['number' => $orderConfirmation->orderConfirmationNumber])) ?>
        </h1>
        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($status->badgeClasses()) ?>"><?= e($status->label()) ?></span>
            · <?= $detail->customer !== null ? e($detail->customer->displayName()) : e(t('order_confirmations.show.no_customer')) ?>
            · <?= $orderConfirmation->eventLocation !== null ? e($orderConfirmation->eventLocation) : e(t('order_confirmations.show.no_location')) ?>
            · <?= $orderConfirmation->startDate !== null && $orderConfirmation->endDate !== null
                ? e($orderConfirmation->startDate->format('d.m.Y')) . ' – ' . e($orderConfirmation->endDate->format('d.m.Y'))
                : e(t('order_confirmations.show.no_period')) ?>
        </p>
        <?php if ($orderConfirmation->sourceOfferId !== null): ?>
            <p class="text-xs text-slate-400 dark:text-slate-500">
                <?= t('order_confirmations.show.source_offer_notice', ['link' => '<a href="/angebote/' . e($orderConfirmation->sourceOfferId) . '" class="underline">' . e(t('order_confirmations.show.source_offer_link')) . '</a>']) ?>
            </p>
        <?php endif; ?>
        <?php if ($linkedRental !== null): ?>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                <?= e(t('order_confirmations.show.rental_link')) ?>:
                <a href="/veranstaltungen/<?= e($linkedRental->id) ?>" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($linkedRental->eventName) ?></a>
            </p>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <?php if ($status === OrderConfirmationStatus::Draft): ?>
            <form method="post" action="/auftragsbestaetigungen/<?= e($orderConfirmation->id) ?>/versendet">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('order_confirmations.show.mark_sent')) ?></button>
            </form>
            <a href="/auftragsbestaetigungen/<?= e($orderConfirmation->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
            <form method="post" action="/auftragsbestaetigungen/<?= e($orderConfirmation->id) ?>/loeschen" data-confirm="<?= e(t('order_confirmations.show.confirm_delete')) ?>" data-confirm-style="danger">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('common.delete')) ?></button>
            </form>
        <?php elseif ($status === OrderConfirmationStatus::Sent): ?>
            <form method="post" action="/auftragsbestaetigungen/<?= e($orderConfirmation->id) ?>/bestaetigt">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"><?= e(t('order_confirmations.show.mark_confirmed')) ?></button>
            </form>
        <?php endif; ?>

        <?php if (in_array($status, [OrderConfirmationStatus::Draft, OrderConfirmationStatus::Sent], true)): ?>
            <form method="post" action="/auftragsbestaetigungen/<?= e($orderConfirmation->id) ?>/storniert" data-confirm="<?= e(t('order_confirmations.show.confirm_cancel')) ?>" data-confirm-style="danger">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('order_confirmations.show.mark_cancelled')) ?></button>
            </form>
        <?php endif; ?>

        <a href="/auftragsbestaetigungen/<?= e($orderConfirmation->id) ?>/auftragsbestaetigung.pdf" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('order_confirmations.show.export_pdf')) ?>
        </a>
    </div>
</div>

<?php if ($venue !== null): ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('order_confirmations.show.venue_title')) ?></h2>
        <p class="text-sm text-slate-600 dark:text-slate-400"><?= e($venue->name) ?> · <?= e($venue->fullAddress()) ?></p>
    </div>
<?php endif; ?>

<?php if ($orderConfirmation->note !== null && trim($orderConfirmation->note) !== ''): ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('order_confirmations.show.note_title')) ?></h2>
        <p class="whitespace-pre-line text-sm text-slate-600 dark:text-slate-400"><?= e($orderConfirmation->note) ?></p>
    </div>
<?php endif; ?>

<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('order_confirmations.show.items_title')) ?></h2>

    <?php if ($rows === []): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('order_confirmations.show.no_items')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[480px] text-left text-sm">
            <thead class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-2 py-2"><?= e(t('order_confirmations.show.article_col')) ?></th>
                    <th class="px-2 py-2"><?= e(t('order_confirmations.show.quantity_col')) ?></th>
                    <th class="px-2 py-2"><?= e(t('order_confirmations.show.unit_price_col')) ?></th>
                    <th class="px-2 py-2 text-right"><?= e(t('order_confirmations.show.line_total_col')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($rows as $row): ?>
                    <?php if ($row->type === OrderConfirmationRow::TYPE_SECTION): ?>
                        <tr class="bg-slate-50 dark:bg-slate-800/60">
                            <td class="px-2 py-2 font-semibold text-slate-700 dark:text-slate-300" colspan="4"><?= e($row->data->heading) ?></td>
                        </tr>
                    <?php elseif ($row->type === OrderConfirmationRow::TYPE_FREE_TEXT): ?>
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
                    <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('order_confirmations.show.rental_subtotal')) ?></td>
                    <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300"><?= number_format($totals->rentalNetTotal, 2, ',', '.') ?> €</td>
                </tr>
                <?php if ($detail->freeTextTotal() > 0): ?>
                    <?php
                        // Nutzerwunsch: Zubehörpauschale auch auf
                        // Freitext-Positionen anwenden — Freitext-Posten
                        // fließen daher (zusammen mit den Mietpreisen) VOR
                        // der Zubehörpauschale/dem Kombirabatt in die Basis
                        // ein (siehe DocumentTotals::calculate()), werden
                        // hier aber weiterhin als eigene Zwischensumme
                        // ausgewiesen.
                    ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('order_confirmations.show.free_text_total')) ?></td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">+<?= number_format($detail->freeTextTotal(), 2, ',', '.') ?> €</td>
                    </tr>
                <?php endif; ?>
                <?php if ($orderConfirmation->accessoryFlatRateEnabled && $orderConfirmation->accessoryFlatRatePercent !== null): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3"><?= e(t('order_confirmations.show.accessory_flat_rate', ['rate' => number_format((float) $orderConfirmation->accessoryFlatRatePercent, 2, ',', '.')])) ?></td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">+<?= number_format($totals->accessoryFlatRateAmount, 2, ',', '.') ?> €</td>
                    </tr>
                <?php endif; ?>
                <?php if ($orderConfirmation->discountType !== null && $orderConfirmation->discountValue !== null): ?>
                    <tr>
                        <td class="px-2 py-1 text-slate-600 dark:text-slate-400" colspan="3">
                            <?= $orderConfirmation->discountType === 'percent'
                                ? e(t('order_confirmations.show.discount_percent', ['rate' => number_format((float) $orderConfirmation->discountValue, 2, ',', '.')]))
                                : e(t('order_confirmations.show.discount_fixed')) ?>
                        </td>
                        <td class="px-2 py-1 text-right text-slate-700 dark:text-slate-300">−<?= number_format($totals->discountAmount, 2, ',', '.') ?> €</td>
                    </tr>
                <?php endif; ?>
                <tr class="border-t border-slate-200 dark:border-slate-700">
                    <td class="px-2 py-2 font-semibold text-slate-800 dark:text-slate-200" colspan="3"><?= e(t('order_confirmations.show.grand_total')) ?></td>
                    <td class="px-2 py-2 text-right font-semibold text-slate-900 dark:text-slate-100"><?= number_format($totals->grossTotal, 2, ',', '.') ?> €</td>
                </tr>
            </tfoot>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($orderConfirmation->closingText !== null && trim($orderConfirmation->closingText) !== ''): ?>
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
        <?= $orderConfirmation->closingText ?>
    </div>
</div>
<?php endif; ?>
