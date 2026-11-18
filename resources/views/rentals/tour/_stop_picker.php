<?php
/**
 * Stop checkboxes with quick selections (bulk actions of the tour page).
 *
 * @var \App\Application\Tour\Query\TourOverview $tour
 * @var string|null $focusStop
 * @var string $pickerId
 */

use function App\Infrastructure\Http\e;

$stops = $tour->stops();
$reference = $focusStop !== null && $focusStop !== '' ? $focusStop : null;
$now = new \DateTimeImmutable();
?>
<fieldset data-stop-picker="<?= e($pickerId) ?>">
    <legend class="text-xs font-medium text-slate-600 dark:text-slate-400">Stops</legend>
    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
        <label class="flex items-center gap-1">ab Stop
            <select data-picker-ref class="rounded-md border border-slate-300 px-1.5 py-1 text-xs dark:border-slate-700 dark:bg-slate-900">
                <?php foreach ($stops as $entry): ?>
                    <option value="<?= e($entry->stop->id) ?>"<?= $entry->stop->id === $reference ? ' selected' : '' ?>><?= (int) $entry->number ?>: <?= e($entry->stop->title()) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="button" data-pick="only" class="rounded border border-slate-300 px-2 py-1 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">nur dieser</button>
        <button type="button" data-pick="following" class="rounded border border-slate-300 px-2 py-1 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">dieser und alle folgenden</button>
        <button type="button" data-pick="open" class="rounded border border-slate-300 px-2 py-1 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">alle nicht begonnenen</button>
        <button type="button" data-pick="all" class="rounded border border-slate-300 px-2 py-1 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">alle</button>
    </div>
    <div class="mt-2 grid gap-1 text-sm sm:grid-cols-2">
        <?php foreach ($stops as $i => $entry): ?>
            <label class="flex items-center gap-2<?= $entry->locked ? ' text-slate-400' : '' ?>">
                <input type="checkbox" name="stops[]" value="<?= e($entry->stop->id) ?>" data-index="<?= $i ?>" data-locked="<?= $entry->locked ? '1' : '0' ?>" data-started="<?= ($entry->stop->occupiedFrom() ?? $now) < $now ? '1' : '0' ?>"
                    <?= $entry->locked ? ' disabled' : '' ?><?= !$entry->locked && $entry->stop->id === $reference ? ' checked' : '' ?>>
                <span><?= (int) $entry->number ?>. <?= e($entry->stop->title()) ?><?= $entry->stop->eventStartAt !== null ? ' · ' . e($entry->stop->eventStartAt->format('d.m.')) : '' ?><?= $entry->locked ? ' (vorbei)' : '' ?></span>
            </label>
        <?php endforeach; ?>
    </div>
</fieldset>
