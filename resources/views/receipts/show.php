<?php
/** @var \App\Application\Receipt\Query\ReceiptDetail $detail */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$receipt = $detail->receipt;
$status = $receipt->status;
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/belege" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('receipts.show.back')) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">
            <?= $receipt->receiptNumber !== null
                ? e(t('receipts.show.heading', ['number' => $receipt->receiptNumber]))
                : e(t('receipts.show.heading_no_number')) ?>
        </h1>
        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= e($status->badgeClasses()) ?>"><?= e($status->label()) ?></span>
            · <?= $detail->supplier !== null ? e($detail->supplier->name) : e(t('receipts.show.no_supplier')) ?>
            · <?= e($receipt->receiptDate->format('d.m.Y')) ?>
        </p>
        <?php if ($receipt->status === \App\Domain\Receipt\ReceiptStatus::Paid && $receipt->paidAt !== null): ?>
            <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('receipts.show.paid_at', ['date' => $receipt->paidAt->format('d.m.Y')])) ?></p>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <?php if ($status === \App\Domain\Receipt\ReceiptStatus::Open): ?>
            <form method="post" action="/belege/<?= e($receipt->id) ?>/bezahlt">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700"><?= e(t('receipts.show.mark_paid')) ?></button>
            </form>
        <?php else: ?>
            <form method="post" action="/belege/<?= e($receipt->id) ?>/offen">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('receipts.show.mark_open')) ?></button>
            </form>
        <?php endif; ?>
        <a href="/belege/<?= e($receipt->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
        <form method="post" action="/belege/<?= e($receipt->id) ?>/loeschen" data-confirm="<?= e(t('receipts.show.confirm_delete')) ?>" data-confirm-style="danger">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('common.delete')) ?></button>
        </form>
    </div>
</div>

<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('receipts.show.amount_title')) ?></h2>
        <p class="text-lg font-semibold text-slate-900 dark:text-slate-100"><?= number_format((float) $receipt->amount, 2, ',', '.') ?> €</p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('receipts.show.category_title')) ?></h2>
        <p class="text-sm text-slate-600 dark:text-slate-400"><?= e($receipt->category->label()) ?></p>
    </div>
</div>

<?php if ($receipt->note !== null && trim($receipt->note) !== ''): ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('receipts.show.note_title')) ?></h2>
        <p class="whitespace-pre-line text-sm text-slate-600 dark:text-slate-400"><?= e($receipt->note) ?></p>
    </div>
<?php endif; ?>

<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('receipts.show.document_title')) ?></h2>
    <?php if ($receipt->mimeType === 'image/jpeg' || $receipt->mimeType === 'image/png'): ?>
        <a href="/belege/<?= e($receipt->id) ?>/download" target="_blank" class="mb-3 block">
            <img src="/belege/<?= e($receipt->id) ?>/download" alt="<?= e($receipt->originalFilename) ?>" class="max-h-96 rounded-md border border-slate-200 dark:border-slate-700">
        </a>
    <?php endif; ?>
    <a href="/belege/<?= e($receipt->id) ?>/download" target="_blank"
       class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
        <?= e(t('receipts.show.download')) ?>: <?= e($receipt->originalFilename) ?> (<?= e($receipt->humanReadableSize()) ?>)
    </a>
</div>

<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('receipts.show.linked_inventory_numbers_title')) ?></h2>
    <?php if ($detail->inventoryNumbers === []): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('receipts.show.linked_inventory_numbers_none')) ?></p>
    <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($detail->inventoryNumbers as $entry): ?>
                <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <a href="/inventurnummern/<?= e($entry->inventoryNumber->id) ?>" class="font-mono text-brand-700 hover:underline dark:text-brand-400"><?= e($entry->inventoryNumber->number) ?></a>
                    <?php if ($entry->article !== null): ?>
                        <a href="/artikel/<?= e($entry->article->id) ?>" class="text-slate-600 hover:underline dark:text-slate-400"><?= e($entry->article->name) ?></a>
                    <?php else: ?>
                        <span class="text-slate-400 dark:text-slate-500"><?= e(t('receipts.show.linked_inventory_number_article_deleted')) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
