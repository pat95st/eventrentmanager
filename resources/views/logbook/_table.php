<?php
/**
 * Table of the logbook list (also loaded via AJAX for paging).
 *
 * @var \App\Domain\Logbook\LogbookEntry[] $entries
 * @var bool $manager
 * @var \App\Domain\Logbook\LogbookSettings $settings
 * @var array<string, string> $vehicleNames
 * @var array{drivers: array<string, string>, rentals: array<string, string>} $names
 * @var \App\Domain\User\User $currentUser
 * @var int $page
 * @var int $pageSize
 * @var int $totalCount
 * @var int $totalPages
 * @var string $paginationBaseUrl
 * @var array<string, string> $paginationQuery
 */

use App\Domain\Logbook\LogbookAccess;
use App\Domain\Logbook\LogbookCategory;
use App\Domain\Logbook\LogbookStatus;

use function App\Infrastructure\Http\e;

$badge = static fn (string $text, string $tone): string => '<span class="mr-1 inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium ' . match ($tone) {
    'amber' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:ring-amber-800',
    'red' => 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200 dark:bg-red-950/40 dark:text-red-400 dark:ring-red-800',
    'blue' => 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200 dark:bg-brand-950/40 dark:text-brand-400 dark:ring-brand-800',
    default => 'bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600',
} . '">' . e($text) . '</span>';
?>
<div class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <?php if ($entries === []): ?>
        <p class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">Keine Fahrten gefunden.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full table-fixed text-left text-sm" style="min-width: 900px" data-responsive>
                <colgroup>
                    <col style="width: 11%"><col style="width: 13%"><col style="width: 14%"><col style="width: 17%"><col style="width: 25%"><col style="width: 7%"><col style="width: 13%">
                </colgroup>
                <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-2.5">Datum</th>
                        <th data-priority="2" class="px-4 py-2.5">Fahrer</th>
                        <th data-priority="1" class="px-4 py-2.5">Fahrzeug</th>
                        <th class="px-4 py-2.5">Art</th>
                        <th class="px-4 py-2.5">Strecke</th>
                        <th class="px-4 py-2.5 text-right">km</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($entries as $entry): ?>
                        <tr class="<?= $entry->isDeleted() ? 'opacity-60' : '' ?> hover:bg-slate-50 dark:hover:bg-slate-800">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-900 dark:text-slate-100">
                                <?= e($entry->tripDate->format('d.m.Y')) ?>
                                <?php if ($entry->departTime !== null || $entry->arriveTime !== null): ?>
                                    <span class="block text-[11px] text-slate-500 dark:text-slate-400"><?= e(trim(($entry->departTime ?? '') . ' – ' . ($entry->arriveTime ?? ''), ' –')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="break-words px-4 py-3 text-slate-700 dark:text-slate-300">
                                <?= e($names['drivers'][$entry->id] ?? '') ?>
                                <?php if ($entry->isExternal): ?><span class="block"><?= $badge('extern', 'slate') ?></span><?php endif; ?>
                            </td>
                            <td class="break-words px-4 py-3 text-slate-700 dark:text-slate-300">
                                <?= e($entry->vehicleId !== null ? ($vehicleNames[$entry->vehicleId] ?? 'Unbekannt') : (string) $entry->otherVehicleLabel) ?>
                                <?php if ($entry->trailerId !== null): ?><span class="block text-[11px] text-slate-500 dark:text-slate-400">+ <?= e($vehicleNames[$entry->trailerId] ?? 'Anhänger') ?></span><?php endif; ?>
                            </td>
                            <td class="break-words px-4 py-3 text-slate-700 dark:text-slate-300">
                                <?= $badge($entry->category->label(), $entry->category === LogbookCategory::Event ? 'blue' : ($entry->category === LogbookCategory::Unclear ? 'red' : 'slate')) ?>
                                <?php if ($entry->rentalId !== null && isset($names['rentals'][$entry->id])): ?>
                                    <span class="mt-1 block"><?= $manager || $currentUser->has(\App\Domain\User\Permission::AusgabeRuecknahme) ? '<a href="/veranstaltungen/' . e($entry->rentalId) . '" class="hover:underline">' . e($names['rentals'][$entry->id]) . '</a>' : e($names['rentals'][$entry->id]) ?></span>
                                <?php endif; ?>
                                <?php if ($entry->purpose !== null): ?><span class="mt-1 block text-[11px] text-slate-500 dark:text-slate-400"><?= e($entry->purpose) ?></span><?php endif; ?>
                            </td>
                            <td class="break-words px-4 py-3 text-slate-600 dark:text-slate-400">
                                <?php if ($entry->from !== null || $entry->to !== null): ?>
                                    <?= e($entry->from?->label ?? '?') ?> → <?= e($entry->to?->label ?? '?') ?><?= $entry->roundTrip ? ' → ' . e($entry->from?->label ?? '?') : '' ?>
                                <?php else: ?>–<?php endif; ?>
                                <?php if ($entry->odometerStart !== null): ?>
                                    <span class="block text-[11px] text-slate-500 dark:text-slate-400">Km-Stand <?= e(number_format($entry->odometerStart, 0, ',', '.')) ?> – <?= e(number_format((int) $entry->odometerEnd, 0, ',', '.')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100"><?= e(number_format($entry->distanceKm, 0, ',', '.')) ?></td>
                            <td class="break-words px-4 py-3 text-right">
                                <?php if ($entry->status !== LogbookStatus::Approved): ?><span class="block"><?= $badge($entry->status->label(), $entry->status === LogbookStatus::Pending ? 'amber' : 'red') ?></span><?php endif; ?>
                                <?php if ($manager && $entry->status === LogbookStatus::Pending && !$entry->isDeleted()): ?>
                                    <span class="mb-1 flex justify-end gap-2">
                                        <form method="post" action="/fahrtenbuch/<?= e($entry->id) ?>/freigeben"><input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>"><input type="hidden" name="back" value="<?= e('/fahrtenbuch?' . http_build_query($paginationQuery)) ?>"><button type="submit" class="text-xs font-medium text-green-700 hover:underline dark:text-green-400">Freigeben</button></form>
                                        <form method="post" action="/fahrtenbuch/<?= e($entry->id) ?>/ablehnen"><input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>"><input type="hidden" name="back" value="<?= e('/fahrtenbuch?' . http_build_query($paginationQuery)) ?>"><button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Ablehnen</button></form>
                                    </span>
                                <?php endif; ?>
                                <?php if ($manager && $entry->notBilled): ?><span class="block"><?= $badge('nicht abrechnen', 'slate') ?></span><?php endif; ?>
                                <?php if ($entry->odometerCheckBypassed): ?><span class="block"><?= $badge('ohne Prüfung', 'amber') ?></span><?php endif; ?>
                                <?php if ($entry->isDeleted()): ?>
                                    <span class="block"><?= $badge('gelöscht', 'red') ?></span>
                                    <?php if ($manager): ?>
                                        <form method="post" action="/fahrtenbuch/<?= e($entry->id) ?>/wiederherstellen" class="mt-1">
                                            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                            <button type="submit" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Wiederherstellen</button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif (LogbookAccess::canEdit($currentUser, $entry, $settings)): ?>
                                    <a href="/fahrtenbuch/<?= e($entry->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/pagination.php'; ?>
