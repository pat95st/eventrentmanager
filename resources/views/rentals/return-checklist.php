<?php
/** @var \App\Application\Rental\Query\RentalDetail $detail */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$rental = $detail->rental;

$pendingByArticle = [];
$returnedByArticle = [];
foreach ($detail->issuedItems as $issued) {
    if ($issued->issuedItem->returnedAt === null) {
        $pendingByArticle[$issued->article->id][] = $issued;
    } else {
        $returnedByArticle[$issued->article->id][] = $issued;
    }
}
?>

<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('rentals.return_checklist.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('rentals.return_checklist.heading', ['event' => $rental->eventName])) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e($detail->customer->displayName()) ?> · <?= e($rental->startDate->format('d.m.Y')) ?> – <?= e($rental->endDate->format('d.m.Y')) ?>
    </p>
</div>

<?php if ($detail->issuedItems === []): ?>
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
        <?= t('rentals.return_checklist.no_issued', ['link' => '<a href="/veranstaltungen/' . e($rental->id) . '/ausgabe" class="underline">' . e(t('rentals.return_checklist.checklist_link')) . '</a>']) ?>
    </div>
<?php else: ?>
    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/rueckgabe" class="mb-6 rounded-lg border border-brand-200 bg-brand-50 p-4 dark:border-brand-800 dark:bg-brand-950/40">
      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <label for="scan-input" class="block text-sm font-semibold text-brand-800 dark:text-brand-300"><?= e(t('rentals.return_checklist.scanner_label')) ?></label>
        <p class="mb-2 text-xs text-brand-700 dark:text-brand-400">
            <?= e(t('rentals.return_checklist.scanner_hint')) ?>
        </p>
        <div class="mb-2 flex flex-wrap items-center gap-4 text-sm text-brand-800 dark:text-brand-300">
            <label class="flex items-center gap-1.5">
                <input type="radio" name="function_test_passed" value="1" checked>
                <?= e(t('rentals.return_checklist.test_passed')) ?>
            </label>
            <label class="flex items-center gap-1.5">
                <input type="radio" name="function_test_passed" value="0">
                <?= e(t('rentals.return_checklist.test_failed')) ?>
            </label>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                id="scan-input"
                name="number"
                autofocus
                autocomplete="off"
                placeholder="z. B. BOS-AKS-0001"
                class="w-full rounded-md border border-brand-300 px-3 py-2 font-mono text-sm dark:border-brand-700 sm:max-w-xs"
            >
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <?= e(t('rentals.return_checklist.capture')) ?>
            </button>
            <button type="button" id="camera-scan-button" class="inline-flex items-center gap-1.5 rounded-md border border-brand-300 bg-white px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:border-brand-700 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-slate-800">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M4 5a2 2 0 00-2 2v7a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-.828-.828A2 2 0 0012.172 3H7.828a2 2 0 00-1.414.586l-.828.828A1 1 0 014.879 5H4z"/>
                    <path fill-rule="evenodd" d="M10 8a3 3 0 100 6 3 3 0 000-6zm-4.5 3a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z" clip-rule="evenodd"/>
                </svg>
                <?= e(t('rentals.return_checklist.scan_with_camera')) ?>
            </button>
        </div>
    </form>

    <dialog id="camera-scan-dialog" class="w-full max-w-md rounded-lg border border-slate-200 p-0 backdrop:bg-black/40 dark:border-slate-700 dark:bg-slate-900">
        <div class="p-4">
            <h3 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e(t('rentals.return_checklist.camera_scan_title')) ?></h3>
            <div class="relative overflow-hidden rounded-md bg-black">
                <video id="camera-scan-video" class="w-full" autoplay playsinline muted></video>
                <div id="camera-scan-flash" class="pointer-events-none absolute inset-0 hidden border-4 border-emerald-400"></div>
            </div>
            <p id="camera-scan-error" class="mt-3 hidden rounded-md bg-red-50 px-3 py-2 text-xs text-red-800 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800"></p>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.return_checklist.camera_scan_hint')) ?></p>
            <div class="mt-4 flex justify-end">
                <button type="button" id="camera-scan-cancel" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <?= e(t('common.cancel')) ?>
                </button>
            </div>
        </div>
    </dialog>

    <script src="/assets/js/barcode-scanner.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.BarcodeScanner) {
                window.BarcodeScanner.attach({
                    buttonId: 'camera-scan-button',
                    dialogId: 'camera-scan-dialog',
                    videoId: 'camera-scan-video',
                    flashId: 'camera-scan-flash',
                    errorId: 'camera-scan-error',
                    cancelId: 'camera-scan-cancel',
                    inputId: 'scan-input',
                    messages: {
                        permission: <?= json_encode(t('rentals.return_checklist.camera_error_permission'), JSON_THROW_ON_ERROR) ?>,
                        noCamera: <?= json_encode(t('rentals.return_checklist.camera_error_no_camera'), JSON_THROW_ON_ERROR) ?>,
                        insecure: <?= json_encode(t('rentals.return_checklist.camera_error_insecure'), JSON_THROW_ON_ERROR) ?>,
                        generic: <?= json_encode(t('rentals.return_checklist.camera_error_generic'), JSON_THROW_ON_ERROR) ?>
                    }
                });
            }
        });
    </script>

    <div class="space-y-4">
        <?php foreach ($detail->items as $entry): ?>
            <?php
            $pending = $pendingByArticle[$entry->article->id] ?? [];
            $returned = $returnedByArticle[$entry->article->id] ?? [];
            if ($pending === [] && $returned === []) {
                continue;
            }
            $complete = $pending === [];
            ?>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e($entry->displayName()) ?></h2>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $complete ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700' ?>">
                        <?= e(t('rentals.return_checklist.returned_of', ['returned' => (string) count($returned), 'total' => (string) (count($returned) + count($pending))])) ?>
                    </span>
                </div>

                <?php if ($returned !== []): ?>
                    <div class="mb-3 divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($returned as $entryReturned): ?>
                            <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <span class="font-mono text-slate-700 dark:text-slate-300">
                                    ✓ <?= e($entryReturned->inventoryNumber->number) ?>
                                    <?php if ($entryReturned->issuedItem->functionTestPassed === false): ?>
                                        <span class="ml-1 inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-800 ring-1 ring-red-300 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-700">
                                            <?= e(t('rentals.return_checklist.test_failed_badge')) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="ml-1 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700">
                                            <?= e(t('rentals.return_checklist.test_ok_badge')) ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <form method="post" action="/ausgaben/<?= e($entryReturned->issuedItem->id) ?>/rueckgabe-rueckgaengig" data-confirm="<?= e(t('rentals.return_checklist.confirm_undo_return')) ?>" data-confirm-style="danger">
                                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <input type="hidden" name="rental_id" value="<?= e($rental->id) ?>">
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('rentals.return_checklist.undo_short')) ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($pending !== []): ?>
                    <div class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-slate-800 dark:border-slate-800">
                        <?php foreach ($pending as $entryPending): ?>
                            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/rueckgabe" class="flex flex-wrap items-center justify-between gap-3 py-2">
                              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                <input type="hidden" name="issued_item_id" value="<?= e($entryPending->issuedItem->id) ?>">
                                <span class="font-mono text-sm text-slate-700 dark:text-slate-300"><?= e($entryPending->inventoryNumber->number) ?></span>
                                <div class="flex flex-wrap items-center gap-3">
                                    <label class="flex items-center gap-1 text-xs text-slate-600 dark:text-slate-400">
                                        <input type="radio" name="function_test_passed" value="1" checked>
                                        <?= e(t('rentals.return_checklist.test_ok_short')) ?>
                                    </label>
                                    <label class="flex items-center gap-1 text-xs text-slate-600 dark:text-slate-400">
                                        <input type="radio" name="function_test_passed" value="0">
                                        <?= e(t('rentals.return_checklist.test_failed_short')) ?>
                                    </label>
                                    <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700">
                                        <?= e(t('rentals.return_checklist.record_return')) ?>
                                    </button>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
