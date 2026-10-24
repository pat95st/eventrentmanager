<?php
/** @var \App\Application\Purchase\Query\PurchaseOrderDetail $detail */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$order = $detail->order;
$status = $order->status;
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/bestellungen" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('purchase_orders.show.back')) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">
            <?= e(t('purchase_orders.show.heading', ['number' => strtoupper(substr($order->id, 0, 8))])) ?>
        </h1>
        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($status->badgeClasses()) ?>"><?= e($status->label()) ?></span>
            · <?= $detail->supplier !== null ? e($detail->supplier->name) : e(t('purchase_orders.table.no_supplier')) ?>
            <?php if ($order->orderedAt !== null): ?>
                · <?= e(t('purchase_orders.show.ordered_at', ['date' => $order->orderedAt->format('d.m.Y')])) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <?php if ($status === \App\Domain\Purchase\PurchaseOrderStatus::Draft): ?>
            <a href="/bestellungen/<?= e($order->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
            <form method="post" action="/bestellungen/<?= e($order->id) ?>/bestellt" data-confirm="<?= e(t('purchase_orders.show.confirm_order')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('purchase_orders.show.mark_ordered')) ?></button>
            </form>
            <form method="post" action="/bestellungen/<?= e($order->id) ?>/loeschen" data-confirm="<?= e(t('purchase_orders.show.confirm_delete')) ?>" data-confirm-style="danger">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('common.delete')) ?></button>
            </form>
        <?php elseif ($status === \App\Domain\Purchase\PurchaseOrderStatus::Ordered): ?>
            <form method="post" action="/bestellungen/<?= e($order->id) ?>/storniert" data-confirm="<?= e(t('purchase_orders.show.confirm_cancel')) ?>" data-confirm-style="danger">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('purchase_orders.show.cancel_button')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($order->note !== null && trim($order->note) !== ''): ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('purchase_orders.show.note_title')) ?></h2>
        <p class="whitespace-pre-line text-sm text-slate-600 dark:text-slate-400"><?= e($order->note) ?></p>
    </div>
<?php endif; ?>

<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('purchase_orders.show.items_title')) ?></h2>

    <?php if ($detail->items === []): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('purchase_orders.show.no_items')) ?></p>
    <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[700px] text-left text-sm">
            <thead class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-2 py-2"><?= e(t('purchase_orders.show.article_col')) ?></th>
                    <th class="px-2 py-2"><?= e(t('purchase_orders.show.target_col')) ?></th>
                    <th class="px-2 py-2"><?= e(t('purchase_orders.show.ordered_col')) ?></th>
                    <th class="px-2 py-2"><?= e(t('purchase_orders.show.received_col')) ?></th>
                    <th class="px-2 py-2"><?= e(t('purchase_orders.show.unit_price_col')) ?></th>
                    <th class="px-2 py-2 text-right"><?= e(t('purchase_orders.show.line_total_col')) ?></th>
                    <?php if ($status === \App\Domain\Purchase\PurchaseOrderStatus::Ordered): ?>
                        <th class="px-2 py-2"></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($detail->items as $entry): ?>
                    <tr>
                        <td class="px-2 py-2 text-slate-800 dark:text-slate-200"><?= e($entry->article->name) ?></td>
                        <td class="px-2 py-2 text-slate-600 dark:text-slate-400"><?= e($entry->item->targetType->label()) ?></td>
                        <td class="px-2 py-2 text-slate-600 dark:text-slate-400"><?= $entry->item->orderedQuantity ?></td>
                        <td class="px-2 py-2 text-slate-600 dark:text-slate-400">
                            <?= $entry->item->receivedQuantity ?>
                            <?php if ($entry->item->isFullyReceived()): ?>
                                <span class="ml-1 rounded-full bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-950/40 dark:text-green-400"><?= e(t('purchase_orders.show.fully_received')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-2 py-2 text-slate-600 dark:text-slate-400"><?= number_format((float) $entry->item->unitPrice, 2, ',', '.') ?> €</td>
                        <td class="px-2 py-2 text-right font-medium text-slate-800 dark:text-slate-200"><?= number_format($entry->item->lineTotal(), 2, ',', '.') ?> €</td>
                        <?php if ($status === \App\Domain\Purchase\PurchaseOrderStatus::Ordered): ?>
                            <td class="px-2 py-2">
                                <?php if (!$entry->item->isFullyReceived()): ?>
                                    <form method="post" action="/bestellungen/<?= e($order->id) ?>/wareneingang" class="flex items-center gap-1.5">
                                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <input type="hidden" name="item_id" value="<?= e($entry->item->id) ?>">
                                        <input type="number" name="quantity" min="1" max="<?= $entry->item->openQuantity() ?>" value="<?= $entry->item->openQuantity() ?>"
                                               class="w-20 rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700">
                                        <button type="submit" class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('purchase_orders.show.receive_button')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="border-t border-slate-200 dark:border-slate-700">
                    <td class="px-2 py-2 font-semibold text-slate-800 dark:text-slate-200" colspan="<?= $status === \App\Domain\Purchase\PurchaseOrderStatus::Ordered ? 5 : 5 ?>"><?= e(t('purchase_orders.show.grand_total')) ?></td>
                    <td class="px-2 py-2 text-right font-semibold text-slate-900 dark:text-slate-100"><?= number_format($detail->totalValue(), 2, ',', '.') ?> €</td>
                    <?php if ($status === \App\Domain\Purchase\PurchaseOrderStatus::Ordered): ?>
                        <td class="px-2 py-2"></td>
                    <?php endif; ?>
                </tr>
            </tfoot>
        </table>
        </div>
    <?php endif; ?>
</div>
