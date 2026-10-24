<?php
/** @var \App\Application\Rental\Query\RentalDetail $detail */
/** @var \App\Domain\Article\Article[] $articles */
/** @var \App\Domain\Venue\Venue|null $venue */
/** @var \App\Domain\Warehouse\Warehouse[] $warehouses */
/** @var string $companyAddress */
/** @var string|null $customerChecklistUrl */
/** @var string|null $customerChecklistUrlError */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$rental = $detail->rental;
$totalPlanned = array_sum(array_map(fn ($entry) => $entry->item->quantity, $detail->items));
$totalIssued = count($detail->issuedItems);
$totalReturned = count(array_filter($detail->issuedItems, fn ($issued) => $issued->issuedItem->returnedAt !== null));
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/veranstaltungen" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('rentals.show.back')) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($rental->eventName) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            <?= e($detail->customer->displayName()) ?> · <?= $rental->eventLocation !== null ? e($rental->eventLocation) : e(t('rentals.show.no_location')) ?>
            · <?= e($rental->startDate->format('d.m.Y')) ?> – <?= e($rental->endDate->format('d.m.Y')) ?>
            · <?= $rental->deploymentDays ?> <?= $rental->deploymentDays === 1 ? e(t('rentals.show.deployment_day')) : e(t('rentals.show.deployment_days_plural')) ?>
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <a href="/veranstaltungen/<?= e($rental->id) ?>/ausgabe" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('rentals.show.checklist_link', ['issued' => (string) $totalIssued, 'planned' => (string) $totalPlanned])) ?>
        </a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/rueckgabe" class="rounded-md border border-brand-300 px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-950/40">
            <?= e(t('rentals.show.return_checklist_link', ['returned' => (string) $totalReturned, 'issued' => (string) $totalIssued])) ?>
        </a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/beladung" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Beladungsliste
        </a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('common.edit')) ?>
        </a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/packliste.pdf" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('rentals.show.export_pdf')) ?>
        </a>
        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/rechnung-erstellen" data-confirm="<?= e(t('rentals.show.confirm_create_invoice')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('rentals.show.create_invoice')) ?></button>
        </form>
        <details class="relative">
            <summary class="cursor-pointer list-none rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                <?= e(t('rentals.show.send_email')) ?>
            </summary>
            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/packliste/email"
                  class="absolute right-0 z-10 mt-2 w-80 space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-slate-900">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.show.recipient_email')) ?></label>
                    <input type="email" name="recipient_email" required
                           value="<?= e($detail->customer->email ?? '') ?>"
                           class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.show.note_optional')) ?></label>
                    <textarea name="note" rows="2"
                              class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800"></textarea>
                </div>
                <button type="submit" class="w-full rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
                    <?= e(t('rentals.show.send_packing_list')) ?>
                </button>
            </form>
        </details>
        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/loeschen" data-confirm="<?= e(t('rentals.show.confirm_delete')) ?>" data-confirm-style="danger">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                <?= e(t('common.delete')) ?>
            </button>
        </form>
    </div>
</div>

<?php if ($venue !== null): ?>
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('rentals.show.venue_title')) ?></h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    <?= e($venue->name) ?> · <?= e($venue->fullAddress()) ?>
                </p>
            </div>
            <a href="/veranstaltungsorte/<?= e($venue->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('rentals.show.edit_venue_link')) ?></a>
        </div>

        <?php if ($venue->hasCoordinates()): ?>
            <?php
            $lat = $venue->latitude;
            $lon = $venue->longitude;
            $delta = 0.006;
            $bbox = ($lon - $delta) . ',' . ($lat - $delta) . ',' . ($lon + $delta) . ',' . ($lat + $delta);
            $embedUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=' . urlencode($bbox) . '&layer=mapnik&marker=' . urlencode($lat . ',' . $lon);
            $largeMapUrl = 'https://www.openstreetmap.org/?mlat=' . urlencode((string) $lat) . '&mlon=' . urlencode((string) $lon) . '#map=16/' . $lat . '/' . $lon;
            ?>
            <div class="overflow-hidden rounded-md border border-slate-200 dark:border-slate-700">
                <iframe
                    src="<?= e($embedUrl) ?>"
                    class="h-72 w-full"
                    style="border: 0"
                    loading="lazy"
                    title="<?= e(t('rentals.show.map_title', ['name' => $venue->name])) ?>"
                ></iframe>
            </div>
            <a href="<?= e($largeMapUrl) ?>" target="_blank" rel="noopener" class="mt-2 inline-block text-xs text-brand-700 hover:underline dark:text-brand-400">
                <?= e(t('rentals.show.larger_map_link')) ?>
            </a>
        <?php else: ?>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                <?= t('rentals.show.no_coordinates', ['link' => '<a href="/veranstaltungsorte/' . e($venue->id) . '/bearbeiten" class="underline">' . e(t('rentals.show.check_address_link')) . '</a>']) ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('rentals.logistics.title')) ?></h2>
    <p class="mb-4 text-xs text-slate-400 dark:text-slate-500"><?= e(t('rentals.logistics.hint')) ?></p>

    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/logistik" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-3 rounded-md border border-slate-200 p-3 dark:border-slate-700">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= e(t('rentals.logistics.pickup_title')) ?></h3>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.logistics.warehouse_label')) ?></label>
                    <select name="pickup_warehouse_id" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                        <option value=""><?= e(t('rentals.logistics.warehouse_placeholder')) ?></option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= e($warehouse->id) ?>" <?= $rental->pickupWarehouseId === $warehouse->id ? 'selected' : '' ?>><?= e($warehouse->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.logistics.appointment_label')) ?></label>
                    <input type="datetime-local" name="pickup_at"
                           value="<?= $rental->pickupAt !== null ? e($rental->pickupAt->format('Y-m-d\TH:i')) : '' ?>"
                           class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                </div>
            </div>
            <div class="space-y-3 rounded-md border border-slate-200 p-3 dark:border-slate-700">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400"><?= e(t('rentals.logistics.return_title')) ?></h3>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.logistics.warehouse_label')) ?></label>
                    <select name="return_warehouse_id" class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                        <option value=""><?= e(t('rentals.logistics.warehouse_placeholder')) ?></option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?= e($warehouse->id) ?>" <?= $rental->returnWarehouseId === $warehouse->id ? 'selected' : '' ?>><?= e($warehouse->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.logistics.appointment_label')) ?></label>
                    <input type="datetime-local" name="return_at"
                           value="<?= $rental->returnAt !== null ? e($rental->returnAt->format('Y-m-d\TH:i')) : '' ?>"
                           class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.logistics.transport_note_label')) ?></label>
            <textarea name="transport_note" rows="2" placeholder="<?= e(t('rentals.logistics.transport_note_placeholder')) ?>"
                      class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700"
            ><?= e($rental->transportNote ?? '') ?></textarea>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
                <?= e(t('common.save')) ?>
            </button>
            <?php if ($venue !== null): ?>
                <details class="relative inline-block">
                    <summary class="cursor-pointer list-none rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        <?= e(t('rentals.logistics.route_button')) ?>
                    </summary>
                    <div class="absolute left-0 z-10 mt-2 w-56 rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                        <?php if ($companyAddress !== ''): ?>
                            <a href="https://www.google.com/maps/dir/?api=1&origin=<?= urlencode($companyAddress) ?>&destination=<?= urlencode($venue->fullAddress()) ?>&travelmode=driving" target="_blank" rel="noopener" class="block px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('rentals.logistics.route_from_company')) ?></a>
                        <?php endif; ?>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <a href="https://www.google.com/maps/dir/?api=1&origin=<?= urlencode($warehouse->fullAddress()) ?>&destination=<?= urlencode($venue->fullAddress()) ?>&travelmode=driving" target="_blank" rel="noopener" class="block px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e($warehouse->name) ?></a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Gutschein</h2>

    <?php if ($voucher !== null && $voucherRedemption !== null): ?>
        <?php $voucherDiscountLabel = $voucher->discountType === \App\Domain\Voucher\VoucherDiscountType::Percentage
            ? str_replace('.', ',', $voucher->discountValue) . ' %'
            : str_replace('.', ',', $voucher->discountValue) . ' €'; ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Code <span class="font-mono font-medium text-slate-900 dark:text-slate-100"><?= e($voucher->code) ?></span>
                · Rabatt <?= e($voucherDiscountLabel) ?>
                · eingelöst am <?= e($voucherRedemption->redeemedAt->format('d.m.Y')) ?>
            </p>
            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/gutschein/entfernen" data-confirm="Die Gutschein-Zuordnung wirklich aufheben? Der Gutschein selbst bleibt bestehen und kann danach erneut eingelöst werden.">
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Einlösung aufheben</button>
            </form>
        </div>
    <?php else: ?>
        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/gutschein" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div class="flex-1">
                <label class="block text-xs text-slate-500 dark:text-slate-400">Gutscheincode</label>
                <input type="text" name="code" placeholder="z. B. SOMMER-A1B2C3D4E5"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-mono focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Einlösen</button>
        </form>
    <?php endif; ?>
</div>

<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Minutenprogramme</h2>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/minutenprogramme/neu" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">+ Neues Minutenprogramm</a>
    </div>
    <?php if ($minutePrograms === []): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400">Noch kein Minutenprogramm für diese Veranstaltung angelegt.</p>
    <?php else: ?>
        <ul class="space-y-2">
            <?php foreach ($minutePrograms as $minuteProgram): ?>
                <li class="flex items-center justify-between rounded-md border border-slate-200 p-2 text-sm dark:border-slate-800">
                    <a href="/minutenprogramme/<?= e($minuteProgram->id) ?>" class="font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e($minuteProgram->name) ?></a>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        <?php if ($minuteProgram->room !== null): ?><?= e($minuteProgram->room) ?> · <?php endif; ?>
                        <?= e($minuteProgram->startAt->format('d.m.Y H:i')) ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php if ($noticeDocuments !== []): ?>
<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Hinweisdokumente</h2>
    <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Für die gebuchten Artikel dieser Veranstaltung sind folgende Hinweisdokumente hinterlegt:</p>
    <ul class="mb-4 space-y-1">
        <?php foreach ($noticeDocuments as $noticeDocument): ?>
            <li class="text-sm">
                <a href="/hinweisdokumente/<?= e($noticeDocument->id) ?>/herunterladen" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($noticeDocument->name) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/hinweisdokumente/senden" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <div class="flex-1">
            <label class="block text-xs text-slate-500 dark:text-slate-400">Empfänger-E-Mail</label>
            <input type="email" name="recipient_email" required value="<?= e($detail->customer->email ?? '') ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
        </div>
        <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">An Kunden senden</button>
    </form>
</div>
<?php endif; ?>

<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('rentals.show.customer_checklist.title')) ?></h2>
    <p class="mb-3 text-xs text-slate-400 dark:text-slate-500"><?= e(t('rentals.show.customer_checklist.hint')) ?></p>

    <?php if ($customerChecklistUrl !== null): ?>
        <div class="flex flex-wrap items-center gap-2">
            <input type="text" readonly value="<?= e($customerChecklistUrl) ?>" onclick="this.select()" class="min-w-0 flex-1 rounded-md border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
            <button type="button" data-copy-value="<?= e($customerChecklistUrl) ?>" onclick="navigator.clipboard.writeText(this.getAttribute('data-copy-value'))" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('rentals.show.customer_checklist.copy')) ?></button>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/kundencheckliste/senden">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700"><?= e(t('rentals.show.customer_checklist.send_now')) ?></button>
            </form>
            <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/kundencheckliste/link/erneuern" data-confirm="<?= e(t('rentals.show.customer_checklist.confirm_regenerate')) ?>" data-confirm-style="danger">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('rentals.show.customer_checklist.regenerate')) ?></button>
            </form>
        </div>
    <?php else: ?>
        <?php if ($customerChecklistUrlError !== null): ?>
            <p class="mb-2 text-xs text-amber-700 dark:text-amber-400"><?= e($customerChecklistUrlError) ?></p>
        <?php endif; ?>
        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/kundencheckliste/link">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700"><?= e(t('rentals.show.customer_checklist.create')) ?></button>
        </form>
    <?php endif; ?>
</div>

<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('rentals.show.planned_items_title')) ?></h2>

    <?php if ($detail->items === []): ?>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('rentals.show.no_items_planned')) ?></p>
    <?php else: ?>
        <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($detail->items as $entry): ?>
                <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <div>
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($entry->article->name) ?></p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">
                            <?= e(t('rentals.show.planned_label')) ?>: <?= $entry->item->quantity ?> · <?= e(t('rentals.show.issued_label')) ?>: <?= $entry->issuedCount ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <form method="post" action="/veranstaltungsposten/<?= e($entry->item->id) ?>" class="flex items-center gap-1">
                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <input type="hidden" name="rental_id" value="<?= e($rental->id) ?>">
                            <input type="number" name="quantity" min="1" value="<?= e((string) $entry->item->quantity) ?>"
                                   class="w-16 rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700">
                            <button type="submit" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('rentals.show.change')) ?></button>
                        </form>
                        <form method="post" action="/veranstaltungsposten/<?= e($entry->item->id) ?>/loeschen" data-confirm="<?= e(t('rentals.show.confirm_remove_item')) ?>" data-confirm-style="danger">
                          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <input type="hidden" name="rental_id" value="<?= e($rental->id) ?>">
                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('rentals.show.remove')) ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/artikel" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <div>
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.show.article_label')) ?></label>
            <select name="article_id" required class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                <option value=""><?= e(t('rentals.show.article_placeholder')) ?></option>
                <?php foreach ($articles as $article): ?>
                    <option value="<?= e($article->id) ?>"><?= e($article->name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e(t('rentals.show.quantity_label')) ?></label>
            <input type="number" name="quantity" min="1" value="1" class="mt-1 w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
        </div>
        <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('rentals.show.add')) ?>
        </button>
    </form>
</div>
