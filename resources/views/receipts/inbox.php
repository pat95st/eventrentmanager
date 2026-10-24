<?php
/** @var \App\Application\ReceiptInbox\Query\ReceiptInboxMessageEntry[] $entries */
/** @var int $page */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */
/** @var string $paginationBaseUrl */
/** @var array<string, string> $paginationQuery */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('receipts.inbox.title')) ?></h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e(t('receipts.inbox.subtitle')) ?></p>
    </div>
    <a href="/belege" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
        <?= e(t('receipts.inbox.back_to_receipts')) ?>
    </a>
</div>

<?php if ($entries === []): ?>
    <div class="rounded-lg border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('receipts.inbox.empty')) ?></p>
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($entries as $entry): ?>
            <?php $message = $entry->message; ?>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                            <?= $message->subject !== null && trim($message->subject) !== '' ? e($message->subject) : e(t('receipts.inbox.no_subject')) ?>
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            <?= e($message->fromAddress) ?>
                            <?php if ($message->receivedAt !== null): ?>
                                · <?= e($message->receivedAt->format('d.m.Y H:i')) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <?php foreach ($entry->attachments as $attachment): ?>
                        <div class="flex w-56 flex-col gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
                            <?php if ($attachment->mimeType === 'image/jpeg' || $attachment->mimeType === 'image/png'): ?>
                                <img src="/belege/posteingang/anhaenge/<?= e($attachment->id) ?>/download" alt="<?= e($attachment->originalFilename) ?>" class="h-24 w-full rounded object-cover">
                            <?php else: ?>
                                <div class="flex h-24 w-full items-center justify-center rounded bg-slate-100 dark:bg-slate-800">
                                    <svg class="h-8 w-8 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L14 2.586A2 2 0 0012.586 2H6a2 2 0 00-2 1zm7 0v3a2 2 0 002 2h3l-5-5z" clip-rule="evenodd"/></svg>
                                </div>
                            <?php endif; ?>
                            <p class="truncate text-xs text-slate-600 dark:text-slate-400" title="<?= e($attachment->originalFilename) ?>"><?= e($attachment->originalFilename) ?></p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500"><?= e($attachment->humanReadableSize()) ?></p>
                            <a href="/belege/posteingang/anhaenge/<?= e($attachment->id) ?>/download" target="_blank" class="text-xs text-brand-600 hover:underline dark:text-brand-400"><?= e(t('receipts.inbox.download')) ?></a>
                            <?php if ($attachment->isConverted()): ?>
                                <a href="/belege/<?= e($attachment->convertedReceiptId) ?>" class="rounded-md bg-slate-100 px-2 py-1 text-center text-xs font-medium text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                                    <?= e(t('receipts.inbox.already_converted_short')) ?>
                                </a>
                            <?php else: ?>
                                <a href="/belege/posteingang/anhaenge/<?= e($attachment->id) ?>/beleg-erstellen" class="rounded-md bg-brand-600 px-2 py-1 text-center text-xs font-medium text-white hover:bg-brand-700">
                                    <?= e(t('receipts.inbox.convert_button')) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php require __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
