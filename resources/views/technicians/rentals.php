<?php
/**
 * Veranstaltungsliste für Techniker (Migration 134): nur angefragte und
 * eingeplante Veranstaltungen, ohne Kunden-/Abrechnungsdaten.
 *
 * @var list<array{assignment: \App\Domain\Technician\RentalTechnician, rental: \App\Domain\Rental\Rental, tradeNames: string[]}> $rows
 * @var bool $showPast
 */

use App\Domain\Technician\RentalTechnicianStatus;

use function App\Infrastructure\Http\e;

$open = array_values(array_filter($rows, static fn ($r) => $r['assignment']->status === RentalTechnicianStatus::Requested));
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Meine Veranstaltungen</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Veranstaltungen, für die du angefragt oder eingeplant bist.</p>
    </div>
    <a href="/veranstaltungen<?= $showPast ? '' : '?vergangene=1' ?>" class="text-sm text-brand-700 hover:underline dark:text-brand-400"><?= $showPast ? 'Kommende anzeigen' : 'Vergangene anzeigen' ?></a>
</div>

<?php if ($open !== [] && !$showPast): ?>
    <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
        <?= count($open) === 1 ? 'Eine Anfrage wartet' : count($open) . ' Anfragen warten' ?> auf deine Zu- oder Absage.
    </div>
<?php endif; ?>

<?php if ($rows === []): ?>
    <p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
        <?= $showPast ? 'Keine vergangenen Veranstaltungen.' : 'Aktuell bist du für keine Veranstaltung angefragt oder eingeplant.' ?>
    </p>
<?php endif; ?>

<div class="space-y-3">
    <?php foreach ($rows as $row): $r = $row['rental']; $a = $row['assignment']; ?>
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <a href="/veranstaltungen/<?= e($r->id) ?>" class="text-base font-semibold text-slate-900 hover:underline dark:text-slate-100"><?= e($r->eventName) ?></a>
                    <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium" style="<?= $a->status->badgeStyle() ?>"><?= e($a->status->label()) ?></span>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        <?= e($r->startDate->format('d.m.Y')) ?><?= $r->endDate->format('Y-m-d') !== $r->startDate->format('Y-m-d') ? ' – ' . e($r->endDate->format('d.m.Y')) : '' ?>
                        <?= $r->eventLocation !== null && $r->eventLocation !== '' ? ' · ' . e($r->eventLocation) : '' ?>
                    </p>
                    <?php if ($r->pickupAt !== null || $r->returnAt !== null): ?>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            <?= $r->pickupAt !== null ? 'Abholung ' . e($r->pickupAt->format('d.m. H:i')) : '' ?>
                            <?= $r->pickupAt !== null && $r->returnAt !== null ? ' · ' : '' ?>
                            <?= $r->returnAt !== null ? 'Rückgabe ' . e($r->returnAt->format('d.m. H:i')) : '' ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($row['tradeNames'] !== []): ?>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Gewerke: <?= e(implode(', ', $row['tradeNames'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!$showPast): $respondAssignment = $a; $respondBack = 'liste'; require __DIR__ . '/_respond.php'; endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
