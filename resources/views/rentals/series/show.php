<?php
/**
 * Series overview: all occurrences with status and warnings plus the
 * series actions (extend, change rhythm, end).
 *
 * @var \App\Application\RentalSeries\Query\RentalSeriesOverview $overview
 */

use App\Domain\RentalSeries\CopyScope;
use App\Domain\RentalSeries\RecurrenceType;
use function App\Infrastructure\Http\e;

require __DIR__ . '/_styles.php';
$series = $overview->series;
$rows = $overview->occurrences;
$first = $rows[0]['rental'] ?? null;
$today = new DateTimeImmutable('today');
$scopes = array_map(static fn (CopyScope $s) => $s->value, CopyScope::defaults());
?>
<div class="mb-6">
    <a href="/serien" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Wiederkehrende Veranstaltungen</a>
    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">🔁 Serie: <?= e($first?->eventName ?? '–') ?></h1>
        <?php if ($series->isEnded()): ?>
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">beendet am <?= e($series->endedAt?->format('d.m.Y') ?? '') ?></span>
        <?php endif; ?>
    </div>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(ucfirst($series->rule->describe())) ?> · <?= count($rows) ?> Termine angelegt<?= $series->nameTemplate !== null ? ' · Name: „' . e($series->nameTemplate) . '“' : '' ?></p>
</div>

<div class="<?= $card ?> mb-6 overflow-x-auto">
    <table class="w-full min-w-[640px] text-sm">
        <thead class="text-left text-xs text-slate-500 dark:text-slate-400">
            <tr>
                <th class="py-2 pr-2 font-medium">#</th>
                <th class="py-2 pr-2 font-medium">Datum</th>
                <th class="py-2 pr-2 font-medium">Veranstaltung</th>
                <th class="py-2 pr-2 font-medium">Ort</th>
                <th class="py-2 pr-2 font-medium">Status</th>
                <th class="py-2 font-medium">Hinweise</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($rows as $i => $row): ?>
                <?php
                $rental = $row['rental'];
                if ($row['started']) {
                    $status = $rental->occupiedUntil() < $today ? 'vorbei' : 'Material ausgegeben';
                    $statusClass = 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
                } elseif ($rental->endDate < $today) {
                    $status = 'vorbei';
                    $statusClass = 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
                } else {
                    $status = 'geplant';
                    $statusClass = 'bg-brand-100 text-brand-800 dark:bg-brand-950/60 dark:text-brand-300';
                }
                ?>
                <tr class="align-top">
                    <td class="py-2 pr-2 text-slate-500"><?= $i + 1 ?></td>
                    <td class="whitespace-nowrap py-2 pr-2"><?= e($rental->startDate->format('d.m.Y')) ?><?= $rental->endDate != $rental->startDate ? ' – ' . e($rental->endDate->format('d.m.Y')) : '' ?></td>
                    <td class="py-2 pr-2">
                        <a href="/veranstaltungen/<?= e($rental->id) ?>" class="font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e($rental->eventName) ?></a>
                        <?php if ($row['customer'] !== '' && ($first === null || $rental->customerId !== $first->customerId)): ?>
                            <span class="block text-xs text-slate-500"><?= e($row['customer']) ?></span>
                        <?php endif; ?>
                        <?php if ($rental->seriesCustomized): ?>
                            <span class="block text-xs text-slate-500">Datum von Hand angepasst</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-2 pr-2"><?= e($rental->eventLocation ?? '–') ?></td>
                    <td class="py-2 pr-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $statusClass ?>"><?= e($status) ?></span></td>
                    <td class="py-2 text-xs">
                        <?php foreach ($row['conflicts'] as $conflict): ?>
                            <p class="text-amber-700 dark:text-amber-400">⚠ <?= e($conflict->describe()) ?></p>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (!$series->isEnded()): ?>
    <div class="grid gap-4 lg:grid-cols-2">
        <form method="post" action="/serien/<?= e($series->id) ?>/verlaengern" class="<?= $card ?> space-y-4">
            <?= $csrf ?>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Weitere Termine erzeugen</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Jeder neue Termin übernimmt die Daten des Termins davor.</p>
            <?php if ($series->rule->type === RecurrenceType::Manual): ?>
                <div>
                    <label class="<?= $label ?>" for="extend-manual">Weitere Termine (eines pro Zeile)</label>
                    <textarea id="extend-manual" name="manual_dates" rows="4" class="<?= $input ?> font-mono" placeholder="TT.MM.JJJJ"></textarea>
                </div>
            <?php else: ?>
                <div class="space-y-2 text-sm">
                    <label class="flex flex-wrap items-center gap-2">
                        <input type="radio" name="end_mode" value="count" checked>
                        <input type="number" name="count" min="1" max="100" value="5" class="w-20 rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900">
                        weitere Termine
                    </label>
                    <label class="flex flex-wrap items-center gap-2">
                        <input type="radio" name="end_mode" value="date">
                        bis einschließlich
                        <input type="date" name="ends_on" class="rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900">
                    </label>
                </div>
            <?php endif; ?>
            <details>
                <summary class="cursor-pointer text-sm text-slate-600 dark:text-slate-400">Was wird übernommen?</summary>
                <div class="mt-2"><?php require __DIR__ . '/_scopes.php'; ?></div>
            </details>
            <button type="submit" class="<?= $primaryButton ?>">Termine erzeugen</button>
        </form>

        <div class="<?= $card ?> space-y-4">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Serie bearbeiten</h2>
            <p class="text-sm">
                <a href="/serien/<?= e($series->id) ?>/rhythmus" class="<?= $secondaryButton ?>">Rhythmus ändern</a>
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Ein einzelner Termin lässt sich auf seiner Detailseite aus der Serie lösen.</p>
            <form method="post" action="/serien/<?= e($series->id) ?>/beenden" class="space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800"
                  data-confirm-key="rentals.series.end" data-confirm="Serie wirklich beenden? Danach lassen sich keine weiteren Termine mehr erzeugen." data-confirm-style="danger">
                <?= $csrf ?>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Serie beenden</p>
                <label class="flex items-start gap-2 text-sm">
                    <input type="radio" name="delete_future" value="0" checked class="mt-0.5">
                    <span>Zukünftige Termine als normale Veranstaltungen behalten</span>
                </label>
                <label class="flex items-start gap-2 text-sm">
                    <input type="radio" name="delete_future" value="1" class="mt-0.5">
                    <span>Zukünftige Termine löschen (nur solche ohne ausgegebenes Material)</span>
                </label>
                <button type="submit" class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:bg-slate-900 dark:text-red-400 dark:hover:bg-red-950/40">Serie beenden</button>
            </form>
        </div>
    </div>
<?php endif; ?>
