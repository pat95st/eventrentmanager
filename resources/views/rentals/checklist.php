<?php
/** @var \App\Application\Rental\Query\RentalDetail $detail */
/** @var array<string, \App\Domain\InventoryNumber\InventoryNumber[]> $availableByArticle */
/** @var string|null $rentalTermsMarkdown */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$rental = $detail->rental;

$issuedByArticle = [];
foreach ($detail->issuedItems as $issued) {
    $issuedByArticle[$issued->article->id][] = $issued;
}
?>

<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('rentals.checklist.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('rentals.checklist.heading', ['event' => $rental->eventName])) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e($detail->customer->displayName()) ?> · <?= e($rental->startDate->format('d.m.Y')) ?> – <?= e($rental->endDate->format('d.m.Y')) ?>
    </p>
</div>

<?php if ($rental->termsSignedAt === null || $detail->customer->idCheckedAt === null): ?>
    <div class="mb-6 space-y-4 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
        <h2 class="text-sm font-semibold text-amber-800 dark:text-amber-300"><?= e(t('rentals.checklist.compliance_heading')) ?></h2>
        <p class="text-xs text-amber-700 dark:text-amber-400"><?= e(t('rentals.checklist.compliance_hint')) ?></p>

        <?php if ($detail->customer->idCheckedAt === null): ?>
            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/ausweispruefung" class="rounded-md bg-white p-3 dark:bg-slate-900">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <input type="hidden" name="customer_id" value="<?= e($detail->customer->id) ?>">
                <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" required class="mt-0.5 h-4 w-4 rounded border-slate-300 dark:border-slate-700">
                    <?= e(t('rentals.checklist.id_check_checkbox')) ?>
                </label>
                <button type="submit" class="mt-3 rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
                    <?= e(t('rentals.checklist.id_check_confirm')) ?>
                </button>
            </form>
        <?php else: ?>
            <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700">
                <?= e(t('rentals.checklist.id_checked_done', ['date' => $detail->customer->idCheckedAt->format('d.m.Y H:i')])) ?>
            </p>
        <?php endif; ?>

        <?php if ($rental->termsSignedAt === null): ?>
            <div class="rounded-md bg-white p-3 dark:bg-slate-900">
                <?php if ($rentalTermsMarkdown !== null && trim($rentalTermsMarkdown) !== ''): ?>
                    <div class="mb-3 max-h-40 overflow-y-auto rounded-md border border-slate-200 p-2 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-300">
                        <?= \App\Infrastructure\Http\MarkdownRenderer::toHtml($rentalTermsMarkdown) ?>
                    </div>
                <?php endif; ?>
                <label class="mb-1 block text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.checklist.signature_label')) ?></label>
                <canvas id="terms-signature-pad" class="w-full touch-none rounded-md border border-slate-300 bg-white dark:border-slate-700" height="150"></canvas>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <button type="button" id="terms-signature-clear" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        <?= e(t('rentals.checklist.signature_clear')) ?>
                    </button>
                    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/vermietbedingungen-unterschrift" id="terms-signature-form">
                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <input type="hidden" name="signature_data_url" id="terms-signature-data-url">
                        <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700">
                            <?= e(t('rentals.checklist.signature_submit')) ?>
                        </button>
                    </form>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var canvas = document.getElementById('terms-signature-pad');
                if (!canvas) { return; }
                var ctx = canvas.getContext('2d');
                canvas.width = canvas.offsetWidth;
                ctx.lineWidth = 2;
                ctx.lineJoin = 'round';
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#1e293b';
                var drawing = false;

                function pos(e) {
                    var rect = canvas.getBoundingClientRect();
                    var point = (e.touches && e.touches[0]) ? e.touches[0] : e;
                    return { x: point.clientX - rect.left, y: point.clientY - rect.top };
                }
                function start(e) { drawing = true; var p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
                function move(e) { if (!drawing) { return; } var p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); }
                function end() { drawing = false; }

                canvas.addEventListener('mousedown', start);
                canvas.addEventListener('mousemove', move);
                canvas.addEventListener('mouseup', end);
                canvas.addEventListener('mouseleave', end);
                canvas.addEventListener('touchstart', start, { passive: false });
                canvas.addEventListener('touchmove', move, { passive: false });
                canvas.addEventListener('touchend', end);

                document.getElementById('terms-signature-clear').addEventListener('click', function () {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                });

                document.getElementById('terms-signature-form').addEventListener('submit', function () {
                    document.getElementById('terms-signature-data-url').value = canvas.toDataURL('image/png');
                });
            });
            </script>
        <?php else: ?>
            <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700">
                <?= e(t('rentals.checklist.terms_signed_done', ['date' => $rental->termsSignedAt->format('d.m.Y H:i')])) ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($detail->items === []): ?>
    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
        <?= t('rentals.checklist.no_items', ['link' => '<a href="/veranstaltungen/' . e($rental->id) . '" class="underline">' . e(t('rentals.checklist.detail_link')) . '</a>']) ?>
    </div>
<?php else: ?>
    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/ausgabe" class="mb-6 rounded-lg border border-brand-200 bg-brand-50 p-4 dark:border-brand-800 dark:bg-brand-950/40">
      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <label for="scan-input" class="block text-sm font-semibold text-brand-800 dark:text-brand-300"><?= e(t('rentals.checklist.scanner_label')) ?></label>
        <p class="mb-2 text-xs text-brand-700 dark:text-brand-400"><?= e(t('rentals.checklist.scanner_hint')) ?></p>
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
                <?= e(t('rentals.checklist.capture')) ?>
            </button>
            <button type="button" id="camera-scan-button" class="inline-flex items-center gap-1.5 rounded-md border border-brand-300 bg-white px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:border-brand-700 dark:bg-slate-900 dark:text-brand-300 dark:hover:bg-slate-800">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M4 5a2 2 0 00-2 2v7a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-.828-.828A2 2 0 0012.172 3H7.828a2 2 0 00-1.414.586l-.828.828A1 1 0 014.879 5H4z"/>
                    <path fill-rule="evenodd" d="M10 8a3 3 0 100 6 3 3 0 000-6zm-4.5 3a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z" clip-rule="evenodd"/>
                </svg>
                <?= e(t('rentals.checklist.scan_with_camera')) ?>
            </button>
        </div>
    </form>

    <dialog id="camera-scan-dialog" class="w-full max-w-md rounded-lg border border-slate-200 p-0 backdrop:bg-black/40 dark:border-slate-700 dark:bg-slate-900">
        <div class="p-4">
            <h3 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e(t('rentals.checklist.camera_scan_title')) ?></h3>
            <div class="relative overflow-hidden rounded-md bg-black">
                <video id="camera-scan-video" class="w-full" autoplay playsinline muted></video>
                <div id="camera-scan-flash" class="pointer-events-none absolute inset-0 hidden border-4 border-emerald-400"></div>
            </div>
            <p id="camera-scan-error" class="mt-3 hidden rounded-md bg-red-50 px-3 py-2 text-xs text-red-800 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800"></p>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.checklist.camera_scan_hint')) ?></p>
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
                        permission: <?= json_encode(t('rentals.checklist.camera_error_permission'), JSON_THROW_ON_ERROR) ?>,
                        noCamera: <?= json_encode(t('rentals.checklist.camera_error_no_camera'), JSON_THROW_ON_ERROR) ?>,
                        insecure: <?= json_encode(t('rentals.checklist.camera_error_insecure'), JSON_THROW_ON_ERROR) ?>,
                        generic: <?= json_encode(t('rentals.checklist.camera_error_generic'), JSON_THROW_ON_ERROR) ?>
                    }
                });
            }
        });
    </script>

    <div class="space-y-4">
        <?php foreach ($detail->items as $entry): ?>
            <?php
            $issued = $issuedByArticle[$entry->article->id] ?? [];
            $available = $availableByArticle[$entry->article->id] ?? [];
            $complete = count($issued) >= $entry->item->quantity;
            ?>
            <?php $hasAccessories = $entry->accessories !== []; ?>
            <?php $hasPreIssueChecks = $entry->preIssueChecks !== []; ?>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e($entry->article->name) ?></h2>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $complete ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-700' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-700' ?>">
                        <?= e(t('rentals.checklist.issued_of', ['issued' => (string) count($issued), 'planned' => (string) $entry->item->quantity])) ?>
                    </span>
                </div>

                <?php if ($hasAccessories): ?>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-md <?= $entry->item->accessoryChecked ? 'bg-emerald-50 dark:bg-emerald-950/40' : 'bg-amber-50 dark:bg-amber-950/40' ?> px-3 py-2 text-sm">
                        <span class="<?= $entry->item->accessoryChecked ? 'text-emerald-800 dark:text-emerald-300' : 'text-amber-800 dark:text-amber-300' ?>">
                            <?= $entry->item->accessoryChecked ? e(t('rentals.checklist.accessories_checked')) : e(t('rentals.checklist.accessories_pending')) ?>
                        </span>
                        <button type="button" onclick="document.getElementById('accessory-modal-<?= e($entry->item->id) ?>').showModal()"
                                class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                            <?= $entry->item->accessoryChecked ? e(t('rentals.checklist.recheck_accessories')) : e(t('rentals.checklist.check_accessories')) ?>
                        </button>
                    </div>

                    <dialog id="accessory-modal-<?= e($entry->item->id) ?>" class="w-full max-w-md rounded-lg border border-slate-200 p-0 backdrop:bg-black/40 dark:border-slate-700 dark:bg-slate-900">
                        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/zubehoer-pruefung" class="p-4">
                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <input type="hidden" name="rental_item_id" value="<?= e($entry->item->id) ?>">
                            <h3 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e(t('rentals.checklist.accessories_modal_title', ['article' => $entry->article->name])) ?></h3>
                            <ul class="mb-4 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                                <?php foreach ($entry->accessories as $accessory): ?>
                                    <li><span class="font-mono text-slate-500 dark:text-slate-400"><?= $accessory->quantity ?>×</span> <?= e($accessory->description) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <label class="mb-4 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input type="checkbox" required class="h-4 w-4 rounded border-slate-300 dark:border-slate-700">
                                <?= e(t('rentals.checklist.accessories_confirm_checkbox')) ?>
                            </label>
                            <div class="flex justify-end gap-2">
                                <button type="button" onclick="document.getElementById('accessory-modal-<?= e($entry->item->id) ?>').close()"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                    <?= e(t('common.cancel')) ?>
                                </button>
                                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
                                    <?= e(t('rentals.checklist.confirm')) ?>
                                </button>
                            </div>
                        </form>
                    </dialog>
                <?php endif; ?>

                <?php if ($hasPreIssueChecks): ?>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-md <?= $entry->item->preIssueChecksConfirmed ? 'bg-emerald-50 dark:bg-emerald-950/40' : 'bg-amber-50 dark:bg-amber-950/40' ?> px-3 py-2 text-sm">
                        <span class="<?= $entry->item->preIssueChecksConfirmed ? 'text-emerald-800 dark:text-emerald-300' : 'text-amber-800 dark:text-amber-300' ?>">
                            <?= $entry->item->preIssueChecksConfirmed ? e(t('rentals.checklist.pre_issue_checks_checked')) : e(t('rentals.checklist.pre_issue_checks_pending')) ?>
                        </span>
                        <button type="button" onclick="document.getElementById('pre-issue-check-modal-<?= e($entry->item->id) ?>').showModal()"
                                class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">
                            <?= $entry->item->preIssueChecksConfirmed ? e(t('rentals.checklist.recheck_pre_issue_checks')) : e(t('rentals.checklist.check_pre_issue_checks')) ?>
                        </button>
                    </div>

                    <dialog id="pre-issue-check-modal-<?= e($entry->item->id) ?>" class="w-full max-w-md rounded-lg border border-slate-200 p-0 backdrop:bg-black/40 dark:border-slate-700 dark:bg-slate-900">
                        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/vor-ausgabe-pruefung" class="p-4">
                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <input type="hidden" name="rental_item_id" value="<?= e($entry->item->id) ?>">
                            <h3 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e(t('rentals.checklist.pre_issue_checks_modal_title', ['article' => $entry->article->name])) ?></h3>
                            <ul class="mb-4 space-y-1 text-sm text-slate-700 dark:text-slate-300">
                                <?php foreach ($entry->preIssueChecks as $preIssueCheck): ?>
                                    <li><?= e($preIssueCheck->description) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <label class="mb-4 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input type="checkbox" required class="h-4 w-4 rounded border-slate-300 dark:border-slate-700">
                                <?= e(t('rentals.checklist.pre_issue_checks_confirm_checkbox')) ?>
                            </label>
                            <div class="flex justify-end gap-2">
                                <button type="button" onclick="document.getElementById('pre-issue-check-modal-<?= e($entry->item->id) ?>').close()"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                    <?= e(t('common.cancel')) ?>
                                </button>
                                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
                                    <?= e(t('rentals.checklist.confirm')) ?>
                                </button>
                            </div>
                        </form>
                    </dialog>
                <?php endif; ?>

                <?php if ($issued !== []): ?>
                    <div class="mb-3 divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($issued as $issuedEntry): ?>
                            <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                                <span class="font-mono text-slate-700 dark:text-slate-300">✓ <?= e($issuedEntry->inventoryNumber->number) ?></span>
                                <form method="post" action="/ausgaben/<?= e($issuedEntry->issuedItem->id) ?>/loeschen" data-confirm="<?= e(t('rentals.checklist.confirm_undo_issue')) ?>" data-confirm-style="danger">
                                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <input type="hidden" name="rental_id" value="<?= e($rental->id) ?>">
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('rentals.checklist.undo')) ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$complete): ?>
                    <?php if ($hasAccessories && !$entry->item->accessoryChecked): ?>
                        <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
                            <?= e(t('rentals.checklist.check_accessories_first')) ?>
                        </p>
                    <?php elseif ($hasPreIssueChecks && !$entry->item->preIssueChecksConfirmed): ?>
                        <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
                            <?= e(t('rentals.checklist.check_pre_issue_checks_first')) ?>
                        </p>
                    <?php elseif ($available === []): ?>
                        <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
                            <?= t('rentals.checklist.no_available_numbers', ['link' => '<a href="/artikel" class="underline">' . e(t('rentals.checklist.create_inventory_number_link')) . '</a>']) ?>
                        </p>
                    <?php else: ?>
                        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/ausgabe" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <div>
                                <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.checklist.issue_select_label')) ?></label>
                                <select name="inventory_number_id" required class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm font-mono dark:border-slate-700">
                                    <option value=""><?= e(t('rentals.checklist.issue_select_placeholder')) ?></option>
                                    <?php foreach ($available as $inv): ?>
                                        <option value="<?= e($inv->id) ?>"><?= e($inv->number) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
                                <?= e(t('rentals.checklist.check_off')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
