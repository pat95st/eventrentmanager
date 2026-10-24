<?php
/** @var \App\Application\Article\Query\InventoryNumberContext $context */
/** @var \App\Application\Rma\Query\RmaWithDocuments[] $rmas */
/** @var \App\Application\Rma\Query\RmaWithDocuments|null $editingRma */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var \App\Application\Receipt\Query\ReceiptListEntry[] $receiptOptions */

use App\Domain\Rma\RmaStatus;

use function App\Infrastructure\Http\e;

$inv = $context->inventoryNumber;
$basePath = '/inventurnummern/' . $inv->id . '/rma';

$formAction = $editingRma !== null ? $basePath . '/' . $editingRma->rma->id : $basePath;
$formTarget = $editingRma?->rma;

$val = fn (string $field, ?string $fallback = null) => $old[$field] ?? $fallback ?? '';
?>

<div class="mb-6">
    <a href="/artikel/<?= e($context->article->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; <?= e($context->article->name) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">RMA-Verwaltung</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e($context->manufacturer->name) ?> · <?= e($context->article->name) ?> ·
        Inventurnummer <span class="font-mono"><?= e($inv->number) ?></span>
    </p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
        <?php if ($rmas === []): ?>
            <div class="rounded-lg border border-dashed border-slate-300 bg-white py-12 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                Für diese Inventurnummer wurde noch kein RMA-/Wartungsvorgang erfasst.
            </div>
        <?php endif; ?>

        <?php foreach ($rmas as $entry): ?>
            <?php $rma = $entry->rma; $status = $rma->status(); ?>
            <div class="rounded-lg border <?= $editingRma?->rma->id === $rma->id ? 'border-brand-300 ring-1 ring-brand-200 dark:border-brand-600 dark:ring-brand-800' : 'border-slate-200 dark:border-slate-800' ?> bg-white p-4 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <span class="inline-flex items-center rounded-full <?= $status->badgeClasses() ?> px-2.5 py-0.5 text-xs font-medium">
                            <?= e($status->label()) ?>
                        </span>
                        <span class="ml-1.5 inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <?= e($rma->kind->label()) ?>
                        </span>
                        <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100"><?= e($rma->reason ?? 'Kein Grund angegeben') ?></p>
                        <?php if ($rma->kind === \App\Domain\Rma\RmaKind::Extern): ?>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                <?php if ($rma->serviceProvider !== null): ?>Dienstleister: <?= e($rma->serviceProvider) ?><?php endif; ?>
                                <?php if ($rma->rmaReference !== null): ?> · RMA-Nr.: <span class="font-mono"><?= e($rma->rmaReference) ?></span><?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($rma->kind === \App\Domain\Rma\RmaKind::Eigenreparatur): ?>
                            <?php if ($rma->workedHours !== null): ?>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Arbeitszeit: <?= e(number_format((float) $rma->workedHours, 2, ',', '.')) ?> Std.</p>
                            <?php endif; ?>
                            <?php if ($entry->receipts !== []): ?>
                                <div class="mt-2">
                                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Verknüpfte Belege</p>
                                    <ul class="mt-1 space-y-1">
                                        <?php foreach ($entry->receipts as $receipt): ?>
                                            <li class="text-sm">
                                                <a href="/belege/<?= e($receipt->id) ?>" class="text-brand-700 hover:underline dark:text-brand-400">
                                                    Beleg vom <?= e($receipt->receiptDate->format('d.m.Y')) ?> · <?= e(number_format((float) $receipt->amount, 2, ',', '.')) ?> €
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <a href="<?= e($basePath) ?>/<?= e($rma->id) ?>/bearbeiten" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                        <form method="post" action="<?= e($basePath) ?>/<?= e($rma->id) ?>/loeschen" data-confirm="Diesen RMA-Vorgang inkl. aller Dokumente wirklich löschen?" data-confirm-style="danger">
                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <button type="submit" class="font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                        </form>
                    </div>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-3 border-t border-slate-100 pt-3 text-sm sm:grid-cols-3 dark:border-slate-800">
                    <?php if ($rma->kind === \App\Domain\Rma\RmaKind::Extern): ?>
                        <div>
                            <dt class="text-xs text-slate-400 dark:text-slate-500">Abgeschickt am</dt>
                            <dd class="font-mono text-slate-800 dark:text-slate-200"><?= $rma->sentAt !== null ? e($rma->sentAt->format('d.m.Y')) : '–' ?></dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt class="text-xs text-slate-400 dark:text-slate-500">Abgeschlossen / zurückerhalten am</dt>
                        <dd class="font-mono text-slate-800 dark:text-slate-200"><?= $rma->returnedAt !== null ? e($rma->returnedAt->format('d.m.Y')) : '–' ?></dd>
                    </div>
                </dl>

                <?php if ($rma->notes !== null): ?>
                    <p class="mt-3 whitespace-pre-line text-sm text-slate-600 dark:text-slate-400"><?= e($rma->notes) ?></p>
                <?php endif; ?>

                <!-- Dokumente -->
                <div class="mt-4 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Dokumente</p>
                    <?php if ($entry->documents === []): ?>
                        <p class="text-sm text-slate-400 dark:text-slate-500">Keine Dokumente hinterlegt.</p>
                    <?php else: ?>
                        <ul class="space-y-1.5">
                            <?php foreach ($entry->documents as $document): ?>
                                <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <a href="<?= e($basePath) ?>/<?= e($rma->id) ?>/dokumente/<?= e($document->id) ?>" target="_blank"
                                       class="truncate text-brand-700 hover:underline dark:text-brand-400">
                                        <?= e($document->originalFilename) ?>
                                    </a>
                                    <span class="flex shrink-0 items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                                        <?= e($document->humanReadableSize()) ?>
                                        <form method="post" action="<?= e($basePath) ?>-dokumente/<?= e($document->id) ?>/loeschen" data-confirm="Dokument löschen?" data-confirm-style="danger">
                                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                            <button type="submit" class="font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                                        </form>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <form method="post" action="<?= e($basePath) ?>/<?= e($rma->id) ?>/dokumente" enctype="multipart/form-data" class="mt-2">
                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <label class="inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">
                            + Dokument hinzufügen (PDF, Bild oder .eml)
                            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp,.eml" class="hidden" onchange="this.form.submit()">
                        </label>
                    </form>
                </div>

                <!-- Fotos vor dem Versand -->
                <div class="mt-4 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Fotos vor dem Versand</p>
                    <?php if ($entry->packagePhotos === []): ?>
                        <p class="text-sm text-slate-400 dark:text-slate-500">Keine Fotos hinterlegt.</p>
                    <?php else: ?>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($entry->packagePhotos as $photo): ?>
                                <div class="group relative">
                                    <a href="<?= e($basePath) ?>/<?= e($rma->id) ?>/versandfotos/<?= e($photo->id) ?>" target="_blank">
                                        <img src="<?= e($basePath) ?>/<?= e($rma->id) ?>/versandfotos/<?= e($photo->id) ?>"
                                             alt="<?= e($photo->originalFilename) ?>"
                                             class="h-20 w-20 rounded-md border border-slate-200 object-cover dark:border-slate-700">
                                    </a>
                                    <form method="post" action="<?= e($basePath) ?>-versandfotos/<?= e($photo->id) ?>/loeschen" data-confirm="Foto löschen?" data-confirm-style="danger"
                                          class="absolute -right-1.5 -top-1.5 opacity-0 transition-opacity group-hover:opacity-100">
                                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <button type="submit" title="Foto löschen"
                                                class="flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white shadow hover:bg-red-700">&times;</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= e($basePath) ?>/<?= e($rma->id) ?>/versandfotos" enctype="multipart/form-data" class="mt-2">
                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <label class="inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">
                            + Foto hinzufügen
                            <input type="file" name="package_photo" accept="image/*" class="hidden" onchange="this.form.submit()">
                        </label>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
            <?= $formTarget !== null ? 'RMA-Vorgang bearbeiten' : 'Neuer RMA-Vorgang' ?>
        </h2>

        <form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Art</label>
                <div class="mt-1 flex gap-4 text-sm">
                    <label class="inline-flex items-center gap-1.5">
                        <input type="radio" name="kind" value="extern" id="rma-kind-extern" onchange="window.__rmaToggleKind()" <?= ($val('kind', $formTarget?->kind?->value ?? 'extern')) === 'extern' ? 'checked' : '' ?>>
                        Extern (Werkstatt/Hersteller)
                    </label>
                    <label class="inline-flex items-center gap-1.5">
                        <input type="radio" name="kind" value="eigenreparatur" id="rma-kind-eigenreparatur" onchange="window.__rmaToggleKind()" <?= ($val('kind', $formTarget?->kind?->value ?? 'extern')) === 'eigenreparatur' ? 'checked' : '' ?>>
                        Eigenreparatur
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Grund der Einsendung</label>
                <input type="text" name="reason" value="<?= e($val('reason', $formTarget?->reason)) ?>"
                       placeholder="z. B. Gerät startet nicht mehr"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div id="rma-extern-only-sent">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Abgeschickt am</label>
                    <input type="date" name="sent_at" value="<?= e($val('sent_at', $formTarget?->sentAt?->format('Y-m-d'))) ?>"
                           class="mt-1 w-full rounded-md border <?= isset($errors['sentAt']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Abgeschlossen / zurückerhalten am</label>
                    <input type="date" name="returned_at" value="<?= e($val('returned_at', $formTarget?->returnedAt?->format('Y-m-d'))) ?>"
                           class="mt-1 w-full rounded-md border <?= isset($errors['returnedAt']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if (isset($errors['returnedAt'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['returnedAt']) ?></p><?php endif; ?>
                </div>
            </div>

            <div id="rma-eigenreparatur-only" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Arbeitszeit (Stunden)</label>
                    <input type="text" name="worked_hours" inputmode="decimal"
                           value="<?= e($val('worked_hours', $formTarget?->workedHours)) ?>"
                           placeholder="z. B. 2,5"
                           class="mt-1 w-full rounded-md border <?= isset($errors['workedHours']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if (isset($errors['workedHours'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['workedHours']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Verknüpfte Belege (Ersatzteile)</label>
                    <?php $linkedReceiptIds = $formTarget !== null ? array_map(fn ($r) => $r->id, $editingRma->receipts) : []; ?>
                    <?php if ($receiptOptions === []): ?>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Es sind noch keine Belege erfasst. <a href="/belege/neu" class="text-brand-700 hover:underline dark:text-brand-400">Beleg anlegen</a></p>
                    <?php else: ?>
                        <input type="text" id="rma-receipt-filter" placeholder="Belege filtern …" oninput="window.__rmaFilterReceipts(this.value)"
                               class="mt-1 mb-1.5 w-full rounded-md border border-slate-300 px-3 py-1.5 text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                        <div class="max-h-48 space-y-1 overflow-y-auto rounded-md border border-slate-200 p-2 dark:border-slate-700">
                            <?php foreach ($receiptOptions as $option): ?>
                                <?php $r = $option->receipt; ?>
                                <label class="rma-receipt-option flex items-center gap-2 rounded px-1 py-1 text-xs hover:bg-slate-50 dark:hover:bg-slate-800" data-search="<?= e(mb_strtolower(($option->supplier?->name ?? '') . ' ' . ($r->receiptNumber ?? ''))) ?>">
                                    <input type="checkbox" name="receipt_ids[]" value="<?= e($r->id) ?>" <?= in_array($r->id, $linkedReceiptIds, true) ? 'checked' : '' ?>>
                                    <span class="truncate"><?= e($r->receiptDate->format('d.m.Y')) ?> · <?= e($option->supplier?->name ?? 'Unbekannter Lieferant') ?><?= $r->receiptNumber !== null ? ' · ' . e($r->receiptNumber) : '' ?> · <?= e(number_format((float) $r->amount, 2, ',', '.')) ?> €</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="rma-extern-only" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Dienstleister / Werkstatt</label>
                <input type="text" name="service_provider" value="<?= e($val('service_provider', $formTarget?->serviceProvider)) ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">RMA-/Vorgangsnummer</label>
                <input type="text" name="rma_reference" value="<?= e($val('rma_reference', $formTarget?->rmaReference)) ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Notizen</label>
                <textarea name="notes" rows="3"
                          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"
                ><?= e($val('notes', $formTarget?->notes)) ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Dokumente (Rechnung, E-Mail als PDF, …)</label>
                <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.eml"
                       class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-400 dark:file:bg-slate-700 dark:file:text-slate-200 dark:hover:file:bg-slate-600">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Fotos vor dem Versand</label>
                <input type="file" name="package_photos[]" multiple accept="image/*"
                       class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-400 dark:file:bg-slate-700 dark:file:text-slate-200 dark:hover:file:bg-slate-600">
            </div>

            <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    Speichern
                </button>
                <?php if ($formTarget !== null): ?>
                    <a href="<?= e($basePath) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
window.__rmaToggleKind = function () {
    var isEigen = document.getElementById('rma-kind-eigenreparatur').checked;
    document.getElementById('rma-extern-only').classList.toggle('hidden', isEigen);
    var sentField = document.getElementById('rma-extern-only-sent');
    if (sentField) { sentField.classList.toggle('hidden', isEigen); }
    document.getElementById('rma-eigenreparatur-only').classList.toggle('hidden', !isEigen);
};
window.__rmaFilterReceipts = function (term) {
    term = term.toLowerCase();
    document.querySelectorAll('.rma-receipt-option').forEach(function (el) {
        el.style.display = el.dataset.search.indexOf(term) === -1 ? 'none' : '';
    });
};
document.addEventListener('DOMContentLoaded', window.__rmaToggleKind);
</script>
