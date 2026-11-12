<?php
/** @var \App\Application\Rental\Query\RentalDetail $detail */
/** @var list<array{id: string, label: string, quantity: int, description: string}> $bookedBundles */
/** @var array<string, int> $bundleRequiredByArticle */
/** @var \App\Domain\Article\Article[] $articles */
/** @var array<string, string> $articleDisplayNames Artikel-ID => "Hersteller Artikelbezeichnung" */
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

<?php
// Status-Hinweis neben dem Titel: wann die Veranstaltung beginnt bzw. ob
// sie läuft oder noch Geräte zurückerwartet werden. Reine Anzeige aus
// Datum + Ausgabe-/Rückgabestand, nichts davon wird gespeichert.
$today = new \DateTimeImmutable('today');
$startDay = $rental->startDate->setTime(0, 0);
$endDay = $rental->endDate->setTime(0, 0);
$daysUntilStart = (int) $today->diff($startDay)->format('%r%a');
if ($daysUntilStart > 1) {
    $statusLabel = t('rentals.show.status.starts_in', ['days' => (string) $daysUntilStart]);
    $statusClass = $daysUntilStart <= 7
        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
} elseif ($daysUntilStart === 1) {
    $statusLabel = t('rentals.show.status.starts_tomorrow');
    $statusClass = 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300';
} elseif ($today <= $endDay) {
    $statusLabel = t('rentals.show.status.running');
    $statusClass = 'bg-brand-100 text-brand-800 dark:bg-brand-950/60 dark:text-brand-300';
} elseif ($totalReturned < $totalIssued) {
    $statusLabel = t('rentals.show.status.return_open', ['count' => (string) ($totalIssued - $totalReturned)]);
    $statusClass = 'bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300';
} else {
    $statusLabel = t('rentals.show.status.finished');
    $statusClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300';
}
$menuItemClass = 'block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800';
$secondaryButtonClass = 'inline-flex cursor-pointer list-none items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800';
?>
<div class="mb-6">
    <a href="/veranstaltungen" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('rentals.show.back')) ?></a>
    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($rental->eventName) ?></h1>
        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?= $statusClass ?>"><?= e($statusLabel) ?></span>
    </div>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e($detail->customer->displayName()) ?> · <?= $rental->eventLocation !== null ? e($rental->eventLocation) : e(t('rentals.show.no_location')) ?>
        · <?= e($rental->startDate->format('d.m.Y')) ?> – <?= e($rental->endDate->format('d.m.Y')) ?>
        · <?= $rental->deploymentDays ?> <?= $rental->deploymentDays === 1 ? e(t('rentals.show.deployment_day')) : e(t('rentals.show.deployment_days_plural')) ?>
    </p>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <a href="/veranstaltungen/<?= e($rental->id) ?>/ausgabe" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('rentals.show.checklist_link', ['issued' => (string) $totalIssued, 'planned' => (string) $totalPlanned])) ?>
        </a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/rueckgabe" class="rounded-md border border-brand-300 bg-white px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:border-brand-700 dark:bg-slate-900 dark:text-brand-400 dark:hover:bg-brand-950/40">
            <?= e(t('rentals.show.return_checklist_link', ['returned' => (string) $totalReturned, 'issued' => (string) $totalIssued])) ?>
        </a>

        <span class="mx-1 hidden h-6 w-px bg-slate-200 dark:bg-slate-700 sm:block" aria-hidden="true"></span>

        <details class="relative">
            <summary class="<?= $secondaryButtonClass ?>">
                <?= e(t('rentals.show.documents_menu')) ?>
                <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
            </summary>
            <div data-dropdown-menu role="menu" class="absolute left-0 top-full z-20 mt-1 w-80 rounded-md border border-slate-200 bg-white py-1 text-left shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <a href="/veranstaltungen/<?= e($rental->id) ?>/beladung" class="<?= $menuItemClass ?>"><?= e(t('rentals.show.loading_list')) ?></a>
                <a href="/veranstaltungen/<?= e($rental->id) ?>/packliste.pdf" target="_blank" class="<?= $menuItemClass ?>"><?= e(t('rentals.show.export_pdf')) ?></a>
                <a href="/veranstaltungen/<?= e($rental->id) ?>/versicherungsliste" class="<?= $menuItemClass ?>"><?= e(t('rentals.show.insurance_list')) ?></a>
                <details class="group">
                    <summary class="<?= $menuItemClass ?> flex cursor-pointer list-none items-center justify-between">
                        <?= e(t('rentals.show.send_email')) ?>
                        <svg class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </summary>
                    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/packliste/email" class="space-y-3 border-y border-slate-100 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-800/40">
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
                <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
                <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/rechnung-erstellen" data-confirm="<?= e(t('rentals.show.confirm_create_invoice')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <button type="submit" class="<?= $menuItemClass ?>"><?= e(t('rentals.show.create_invoice')) ?></button>
                </form>
            </div>
        </details>

        <a href="/veranstaltungen/<?= e($rental->id) ?>/bearbeiten" class="<?= $secondaryButtonClass ?>">
            <?= e(t('common.edit')) ?>
        </a>

        <details class="relative">
            <summary class="<?= $secondaryButtonClass ?> px-2.5" title="<?= e(t('rentals.show.more_actions')) ?>">
                <svg class="h-5 w-5 text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM8.5 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM15.5 8.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/></svg>
                <span class="sr-only"><?= e(t('rentals.show.more_actions')) ?></span>
            </summary>
            <div data-dropdown-menu role="menu" class="menu-fit absolute left-0 top-full z-20 mt-1 rounded-md border border-slate-200 bg-white py-1 text-left shadow-lg dark:border-slate-700 dark:bg-slate-900 sm:left-auto sm:right-0">
                <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/loeschen" data-confirm="<?= e(t('rentals.show.confirm_delete')) ?>" data-confirm-style="danger">
                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">
                        <?= e(t('rentals.show.delete_event')) ?>
                    </button>
                </form>
            </div>
        </details>
    </div>
</div>
<?php require __DIR__ . '/_spare_fuses.php'; /* Ersatzsicherungen (Migration 129) */ ?>
<?php require __DIR__ . '/_schedule_notes.php'; /* Exklusiv / Betriebsurlaub (Migration 130) */ ?>

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

<div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <div>
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Audio &amp; Durchsagen</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">Jingles, Audio-Dateien, Veranstalter-Zugänge und Durchsagen über das Abspielgerät am Mischpult.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="/veranstaltungen/<?= e($rental->id) ?>/audio" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Verwalten</a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/live" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Live-Oberfläche</a>
    </div>
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

<?php require __DIR__ . '/_technicians.php'; /* Techniker & Gewerke (Migration 134) */ ?>
<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('rentals.show.planned_items_title')) ?></h2>

    <?php
        // Gebuchte Bundles (Migration 107): ihre Bestandteile stehen unten als
        // geplante Artikel und werden wie gewohnt ausgegeben; entfernt wird
        // ein Bundle nur als Ganzes.
        $bookedBundles ??= [];
        $bundleRequiredByArticle ??= [];
    ?>
    <?php if ($bookedBundles !== []): ?>
        <div class="mb-4 rounded-md border border-brand-200 bg-brand-50 p-3 dark:border-brand-800 dark:bg-brand-950/40">
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-brand-700 dark:text-brand-400"><?= e(t('rentals.show.bundles_title')) ?></p>
            <div class="divide-y divide-brand-100 dark:divide-brand-900">
                <?php foreach ($bookedBundles as $bookedBundle): ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <div>
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= (int) $bookedBundle['quantity'] ?>× <?= e($bookedBundle['label']) ?></p>
                            <?php if ($bookedBundle['description'] !== ''): ?>
                                <p class="text-xs text-slate-500 dark:text-slate-400"><?= e($bookedBundle['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="/veranstaltungsbundles/<?= e($bookedBundle['id']) ?>/loeschen" data-confirm="<?= e(t('rentals.show.confirm_remove_bundle')) ?>" data-confirm-style="danger">
                            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <input type="hidden" name="rental_id" value="<?= e($rental->id) ?>">
                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('rentals.show.remove')) ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php $bookedSets ??= []; ?>
    <?php if ($bookedSets !== []): /* Sets (Migration 113) */ ?>
        <div class="mb-4 rounded-md border border-brand-200 bg-brand-50 p-3 dark:border-brand-800 dark:bg-brand-950/40">
            <p class="mb-2 text-xs font-medium uppercase tracking-wide text-brand-700 dark:text-brand-400"><?= e(t('rentals.show.sets_title')) ?></p>
            <div class="divide-y divide-brand-100 dark:divide-brand-900">
                <?php foreach ($bookedSets as $bookedSet): ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <div>
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= (int) $bookedSet['quantity'] ?>× <?= e($bookedSet['label']) ?></p>
                            <?php if ($bookedSet['description'] !== ''): ?>
                                <p class="text-xs text-slate-500 dark:text-slate-400"><?= e($bookedSet['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="/veranstaltungssets/<?= e($bookedSet['id']) ?>/loeschen" data-confirm="<?= e(t('rentals.show.confirm_remove_set')) ?>" data-confirm-style="danger">
                            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                            <input type="hidden" name="rental_id" value="<?= e($rental->id) ?>">
                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('rentals.show.remove')) ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($detail->items === []): ?>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('rentals.show.no_items_planned')) ?></p>
    <?php else: ?>
        <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($detail->items as $entry): ?>
                <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                    <div>
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($entry->displayName()) ?></p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">
                            <?= e(t('rentals.show.planned_label')) ?>: <?= $entry->item->quantity ?> · <?= e(t('rentals.show.issued_label')) ?>: <?= $entry->issuedCount ?>
                            <?php if (($bundleRequiredByArticle[$entry->article->id] ?? 0) > 0): ?>
                                · <?= e(t('rentals.show.of_which_bundle', ['count' => (string) $bundleRequiredByArticle[$entry->article->id]])) ?>
                            <?php endif; ?>
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

    <!-- Artikel hinzufügen über das Artikel-Auswahl-Modal der Dokumente (Nutzerwunsch):
         mehrere Artikel samt Menge auf einmal, mit Suche und Hersteller-/Kategoriefilter. -->
    <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/artikel/mehrere" data-rental-picker-form class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <div data-rental-picker-items hidden></div>
        <button type="button" data-rental-picker-open class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
            + <?= e(t('rentals.show.add')) ?> …
        </button>
        <span class="text-xs text-slate-400 dark:text-slate-500">Mehrere Artikel samt Menge auswählen – mit Suche und Filter nach Hersteller und Kategorie.</span>
    </form>
    <script>
        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-rental-picker-open]') || !window.ArticlePicker) { return; }
            window.ArticlePicker.open(function (items) {
                var form = document.querySelector('[data-rental-picker-form]');
                var box = form.querySelector('[data-rental-picker-items]');
                box.innerHTML = '';
                items.forEach(function (item, index) {
                    ['article_id', 'quantity'].forEach(function (key) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'items[' + index + '][' + key + ']';
                        input.value = item[key];
                        box.appendChild(input);
                    });
                });
                if (items.length > 0) { form.submit(); }
            });
        });
    </script>
</div>
<?php \App\Infrastructure\Http\renderArticlePickerModal(); /* Artikel-Auswahl-Modal */ ?>
