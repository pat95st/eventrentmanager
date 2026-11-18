<?php
/**
 * Load lists of a tour: start load and per stop the needed material plus
 * what to load/unload compared to the previous stop. Printable.
 *
 * @var \App\Application\Tour\Query\TourOverview $tour
 */

use function App\Infrastructure\Http\e;

require __DIR__ . '/../series/_styles.php';
$rental = $tour->rental;
$list = static function (array $material) use ($tour): void {
    $rows = [];
    foreach ($material as $articleId => $quantity) {
        $rows[$tour->articleName((string) $articleId)] = $quantity;
    }
    ksort($rows, SORT_NATURAL | SORT_FLAG_CASE);
    if ($rows === []) {
        echo '<p class="text-sm text-slate-400">Kein Material.</p>';

        return;
    }
    echo '<table class="w-full text-sm"><tbody class="divide-y divide-slate-100 dark:divide-slate-800">';
    foreach ($rows as $name => $quantity) {
        echo '<tr><td class="w-6 py-1"><span class="inline-block h-3.5 w-3.5 rounded-full border border-slate-400"></span></td><td class="w-14 py-1 text-right font-mono">' . (int) $quantity . '×</td><td class="py-1 pl-3">' . e((string) $name) . '</td></tr>';
    }
    echo '</tbody></table>';
};
?>
<div class="mb-6 print:mb-2">
    <a href="/veranstaltungen/<?= e($rental->id) ?>/tour" class="text-sm text-slate-500 hover:underline print:hidden dark:text-slate-400">← Zurück zur Tour</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Ladelisten: <?= e($rental->eventName) ?></h1>
    <p class="text-sm text-slate-500"><?= e($rental->startDate->format('d.m.Y')) ?> – <?= e($rental->endDate->format('d.m.Y')) ?></p>
    <div class="mt-3 flex gap-2 print:hidden">
        <button type="button" onclick="window.print()" class="<?= $secondaryButton ?>">Drucken</button>
        <a href="/veranstaltungen/<?= e($rental->id) ?>/tour/ladeliste.pdf" target="_blank" class="<?= $secondaryButton ?>">PDF</a>
    </div>
</div>

<section class="<?= $card ?> mb-4 break-inside-avoid">
    <h2 class="mb-2 font-semibold">Beladung zum Tourstart</h2>
    <?php $list($tour->startLoad); ?>
</section>

<?php foreach ($tour->entries as $entry): ?>
    <?php $stop = $entry->stop; ?>
    <?php if (!$stop->isStop()): ?>
        <p class="mb-4 ml-4 text-sm italic text-slate-500">↳ <?= e($stop->title()) ?><?= $stop->arriveAt !== null ? ' · ' . e($stop->arriveAt->format('d.m.Y H:i')) : '' ?></p>
        <?php continue; ?>
    <?php endif; ?>
    <section class="<?= $card ?> mb-4 break-inside-avoid">
        <h2 class="font-semibold">Tourstop <?= (int) $entry->number ?>: <?= e($stop->title()) ?></h2>
        <p class="mb-3 text-xs text-slate-500"><?= $stop->eventStartAt !== null ? e($stop->eventStartAt->format('d.m.Y H:i')) : '' ?><?= $stop->addressLine() !== '' ? ' · ' . e($stop->addressLine()) : '' ?></p>
        <?php if ($entry->changes !== []): ?>
            <h3 class="text-xs font-semibold text-slate-600 dark:text-slate-400">Gegenüber <?= $entry->number === 1 ? 'der Grundliste' : 'Tourstop ' . ($entry->number - 1) ?></h3>
            <ul class="mb-3 text-sm">
                <?php foreach ($entry->changes as $change): ?>
                    <?php $diff = $change['after'] - $change['before']; ?>
                    <li class="<?= $diff > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' ?>">
                        <?= $diff > 0 ? '+' : '−' ?><?= abs($diff) ?>× <?= e($tour->articleName($change['articleId'])) ?> <span class="text-xs text-slate-500">(<?= $diff > 0 ? 'zusätzlich laden' : 'abladen' ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <h3 class="text-xs font-semibold text-slate-600 dark:text-slate-400">Benötigt an diesem Tourstop</h3>
        <?php $list($entry->material); ?>
    </section>
<?php endforeach; ?>
