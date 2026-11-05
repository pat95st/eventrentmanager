<?php
/**
 * Veranstaltung aus Sicht eines Technikers (Migration 134): Termine, Ort,
 * Team, Gewerke, geplante Artikel und Ausgabe/Rückgabe – bewusst OHNE
 * Preise, Rechnungen, Gutscheine, Kunden-Kontaktdaten und Bearbeitung.
 *
 * @var \App\Application\Rental\Query\RentalDetail $detail
 * @var \App\Domain\Technician\RentalTechnician|null $assignment
 * @var \App\Application\Technician\Query\RentalTechnicianTeam $team
 * @var \App\Domain\Venue\Venue|null $venue
 * @var \App\Domain\Warehouse\Warehouse[] $warehouses
 */

use App\Domain\Technician\RentalTechnicianStatus;

use function App\Infrastructure\Http\e;

$rental = $detail->rental;
$warehouseName = static function (?string $id) use ($warehouses): ?string {
    foreach ($warehouses as $warehouse) {
        if ($warehouse->id === $id) {
            return $warehouse->name . ', ' . $warehouse->fullAddress();
        }
    }

    return null;
};
$totalPlanned = array_sum(array_map(fn ($entry) => $entry->item->quantity, $detail->items));
$totalIssued = count($detail->issuedItems);
$totalReturned = count(array_filter($detail->issuedItems, fn ($issued) => $issued->issuedItem->returnedAt !== null));
$booked = $assignment !== null && $assignment->status->isBooked();
$card = 'mb-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900';
?>
<div class="mb-4">
    <a href="/veranstaltungen" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Meine Veranstaltungen</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($rental->eventName) ?>
        <?php if ($assignment !== null): ?><span class="ml-1 rounded-full px-2 py-0.5 align-middle text-xs font-medium" style="<?= $assignment->status->badgeStyle() ?>"><?= e($assignment->status->label()) ?></span><?php endif; ?>
    </h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e($rental->startDate->format('d.m.Y')) ?><?= $rental->endDate->format('Y-m-d') !== $rental->startDate->format('Y-m-d') ? ' – ' . e($rental->endDate->format('d.m.Y')) : '' ?>
        <?= $rental->eventLocation !== null && $rental->eventLocation !== '' ? ' · ' . e($rental->eventLocation) : '' ?>
    </p>
</div>

<?php if ($assignment !== null && $assignment->status === RentalTechnicianStatus::Requested): ?>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
        <span>Du bist für diese Veranstaltung angefragt. Bitte sag zu oder ab.</span>
        <?php $respondAssignment = $assignment; $respondBack = 'detail'; require __DIR__ . '/_respond.php'; ?>
    </div>
<?php endif; ?>

<div class="mb-4 flex flex-wrap gap-2">
    <?php if ($booked): ?>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/ausgabe" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Ausgabe (<?= $totalIssued ?>/<?= $totalPlanned ?>)</a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/rueckgabe" class="rounded-md border border-brand-300 px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-950/40">Rückgabe (<?= $totalReturned ?>/<?= $totalIssued ?>)</a>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/rma" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Defekt melden (RMA)</a>
    <?php endif; ?>
    <?php if ($assignment !== null && $assignment->status === RentalTechnicianStatus::Confirmed): $respondAssignment = $assignment; $respondBack = 'detail'; require __DIR__ . '/_respond.php'; endif; ?>
</div>

<?php require __DIR__ . '/../rentals/_spare_fuses.php'; ?>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="<?= $card ?>">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Termine</h2>
        <dl class="space-y-1 text-sm">
            <div><dt class="inline text-slate-500 dark:text-slate-400">Abholung:</dt> <dd class="inline text-slate-800 dark:text-slate-200"><?= $rental->pickupAt !== null ? e($rental->pickupAt->format('d.m.Y H:i')) . ' Uhr' : 'noch offen' ?></dd></div>
            <?php if (($w = $warehouseName($rental->pickupWarehouseId)) !== null): ?><div class="text-xs text-slate-500 dark:text-slate-400"><?= e($w) ?></div><?php endif; ?>
            <div><dt class="inline text-slate-500 dark:text-slate-400">Rückgabe:</dt> <dd class="inline text-slate-800 dark:text-slate-200"><?= $rental->returnAt !== null ? e($rental->returnAt->format('d.m.Y H:i')) . ' Uhr' : 'noch offen' ?></dd></div>
            <?php if (($w = $warehouseName($rental->returnWarehouseId)) !== null): ?><div class="text-xs text-slate-500 dark:text-slate-400"><?= e($w) ?></div><?php endif; ?>
            <?php if ($rental->transportNote !== null && trim($rental->transportNote) !== ''): ?>
                <div class="pt-1 text-slate-700 dark:text-slate-300"><?= nl2br(e($rental->transportNote)) ?></div>
            <?php endif; ?>
        </dl>
    </div>

    <div class="<?= $card ?>">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Team &amp; Gewerke</h2>
        <?php if ($team->rentalTradeNames() !== []): ?><p class="mb-2 text-xs text-slate-500 dark:text-slate-400">Benötigt: <?= e(implode(', ', $team->rentalTradeNames())) ?></p><?php endif; ?>
        <ul class="space-y-1 text-sm">
            <?php foreach ($team->rows as $row): if (!$row['assignment']->status->isBooked() && $row['assignment']->userId !== $assignment?->userId) { continue; } ?>
                <li class="text-slate-800 dark:text-slate-200">
                    <?= e($row['user']?->displayName() ?? '–') ?><?= $row['assignment']->userId === $assignment?->userId ? ' (du)' : '' ?>
                    <?php if ($row['tradeNames'] !== []): ?><span class="text-xs text-slate-500 dark:text-slate-400"> · <?= e(implode(', ', $row['tradeNames'])) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($assignment?->note !== null && $assignment->note !== ''): ?><p class="mt-2 text-xs text-slate-600 dark:text-slate-400">Hinweis für dich: <?= e($assignment->note) ?></p><?php endif; ?>
    </div>
</div>

<?php if ($venue !== null): ?>
    <div class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300">Veranstaltungsort</h2>
        <p class="text-sm text-slate-700 dark:text-slate-300"><?= e($venue->name) ?> · <?= e($venue->fullAddress()) ?></p>
        <div class="mt-2 flex flex-wrap gap-3 text-xs">
            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($venue->fullAddress()) ?>&travelmode=driving" target="_blank" rel="noopener" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Route</a>
            <?php if ($venue->hasCoordinates()): ?>
                <button type="button" data-map-modal data-lat="<?= e((string) $venue->latitude) ?>" data-lon="<?= e((string) $venue->longitude) ?>" data-title="<?= e($venue->name) ?>" data-address="<?= e($venue->fullAddress()) ?>" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Karte</button>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="<?= $card ?>">
    <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Geplante Artikel</h2>
    <?php if ($detail->items === []): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400">Noch keine Artikel geplant.</p>
    <?php else: ?>
        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($detail->items as $entry): ?>
                <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                    <span class="text-slate-800 dark:text-slate-200"><?= e($entry->displayName()) ?></span>
                    <span class="text-xs text-slate-500 dark:text-slate-400"><?= $entry->item->quantity ?>× geplant · <?= $entry->issuedCount ?> ausgegeben</span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../_map_modal.php'; ?>
