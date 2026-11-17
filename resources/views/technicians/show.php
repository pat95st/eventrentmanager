<?php
/**
 * Read-only technician page for planners (see TechnicianController):
 * contact details and upcoming confirmed/assigned events.
 *
 * @var \App\Domain\User\User $technician
 * @var ?\App\Domain\Technician\TechnicianProfile $profile
 * @var list<array{assignment: \App\Domain\Technician\RentalTechnician, rental: \App\Domain\Rental\Rental, tradeNames: string[]}> $rows
 * @var bool $canEditUser
 */

use function App\Infrastructure\Http\e;

$phone = $profile?->phone;
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">
            <?= e($technician->displayName()) ?>
            <?php if (!$technician->isActive): ?>
                <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 align-middle text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Inaktiv</span>
            <?php endif; ?>
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Techniker</p>
    </div>
    <?php if ($canEditUser): ?>
        <a href="/benutzer/<?= e(rawurlencode($technician->id)) ?>/bearbeiten" class="text-sm text-brand-700 hover:underline dark:text-brand-400">Benutzer bearbeiten</a>
    <?php endif; ?>
</div>

<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <dl class="grid gap-3 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-slate-500 dark:text-slate-400">E-Mail</dt>
            <dd class="break-all"><a href="mailto:<?= e($technician->email) ?>" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($technician->email) ?></a></dd>
        </div>
        <div>
            <dt class="text-slate-500 dark:text-slate-400">Telefon</dt>
            <dd>
                <?php if ($phone !== null && $phone !== ''): ?>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone) ?? '') ?>" class="text-brand-700 hover:underline dark:text-brand-400"><?= e($phone) ?></a>
                <?php else: ?>
                    <span class="text-slate-400">–</span>
                <?php endif; ?>
            </dd>
        </div>
    </dl>
</div>

<h2 class="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">Kommende Einsätze</h2>

<?php if ($rows === []): ?>
    <p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
        Aktuell für keine kommende Veranstaltung eingeplant.
    </p>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($rows as $row): $r = $row['rental']; $a = $row['assignment']; ?>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <a href="/veranstaltungen/<?= e($r->id) ?>" class="font-semibold text-slate-900 hover:underline dark:text-slate-100"><?= e($r->eventName) ?></a>
                <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium" style="<?= $a->status->badgeStyle() ?>"><?= e($a->status->label()) ?></span>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    <?= e($r->startDate->format('d.m.Y')) ?><?= $r->endDate->format('Y-m-d') !== $r->startDate->format('Y-m-d') ? ' – ' . e($r->endDate->format('d.m.Y')) : '' ?>
                    <?= $r->eventLocation !== null && $r->eventLocation !== '' ? ' · ' . e($r->eventLocation) : '' ?>
                </p>
                <?php if ($row['tradeNames'] !== []): ?>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Gewerke: <?= e(implode(', ', $row['tradeNames'])) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
