<?php
/**
 * Hinweise zu Migration 130 auf der Veranstaltungsseite: exklusive
 * Veranstaltung (blockiert andere Vermietungen) und Betriebsurlaub
 * (Abholung vorher, Rückgabe nachher).
 *
 * @var \App\Domain\Rental\Rental $rental
 * @var \App\Domain\CompanyHoliday\CompanyHoliday[] $rentalHolidays
 * @var \App\Domain\Rental\Rental[] $blockingConflicts
 */

use function App\Infrastructure\Http\e;

$rentalHolidays ??= [];
$blockingConflicts ??= [];
?>
<?php if ($rental->blocksOtherRentals): ?>
    <div class="mb-4 rounded-md border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-800 dark:bg-brand-950/40 dark:text-brand-300">
        <span class="font-medium">Exklusive Veranstaltung:</span>
        vom <?= e($rental->occupiedFrom()->format('d.m.Y')) ?> bis <?= e($rental->occupiedUntil()->format('d.m.Y')) ?> sind keine weiteren Vermietungen möglich.
    </div>
<?php endif; ?>
<?php if ($blockingConflicts !== []): ?>
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
        <span class="font-medium">Zeitraum überschneidet sich mit <?= $rental->blocksOtherRentals ? 'anderen Veranstaltungen' : 'einer exklusiven Veranstaltung' ?>:</span>
        <?php foreach ($blockingConflicts as $i => $other): ?>
            <?= $i > 0 ? ', ' : '' ?><a href="/veranstaltungen/<?= e($other->id) ?>" class="underline"><?= e($other->eventName) ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($rentalHolidays !== []): ?>
    <?php
    $firstHoliday = $rentalHolidays[0];
    $lastHoliday = $rentalHolidays[count($rentalHolidays) - 1];
    $pickupOk = $rental->pickupAt !== null && $rental->pickupAt->format('Y-m-d') < $firstHoliday->startDate->format('Y-m-d');
    $returnOk = $rental->returnAt !== null && $rental->returnAt->format('Y-m-d') > $lastHoliday->endDate->format('Y-m-d');
    ?>
    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
        <p><span class="font-medium">Betriebsurlaub:</span> <?= e(implode(', ', array_map(static fn ($h) => $h->label(), $rentalHolidays))) ?> – keine Ausgabe und keine Rücknahme.</p>
        <p class="mt-1">
            Abholung spätestens am <?= e($firstHoliday->lastPickupDay()->format('d.m.Y')) ?>
            (<?= $rental->pickupAt !== null ? 'geplant: ' . e($rental->pickupAt->format('d.m.Y H:i')) : 'noch kein Termin' ?><?= $pickupOk ? '' : ' ⚠' ?>),
            Rückgabe frühestens am <?= e($lastHoliday->firstReturnDay()->format('d.m.Y')) ?>
            (<?= $rental->returnAt !== null ? 'geplant: ' . e($rental->returnAt->format('d.m.Y H:i')) : 'noch kein Termin' ?><?= $returnOk ? '' : ' ⚠' ?>).
        </p>
    </div>
<?php endif; ?>
