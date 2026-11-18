<?php
/**
 * "Aus vorherigem Termin übernehmen" / "Auf folgende Termine übertragen":
 * choose areas (and target occurrences).
 *
 * @var \App\Domain\Rental\Rental $rental
 * @var \App\Application\RentalSeries\Query\RentalSeriesContext $context
 * @var string $direction 'vorher' | 'folgende'
 * @var string[] $scopes
 * @var string[] $targets
 * @var array<string, string> $errors
 */

use function App\Infrastructure\Http\e;

require __DIR__ . '/_styles.php';
$fromPrevious = $direction === 'vorher';
?>
<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Zurück zur Veranstaltung</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $fromPrevious ? 'Aus vorherigem Termin übernehmen' : 'Auf folgende Termine übertragen' ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?php if ($fromPrevious && $context->previous !== null): ?>
            Die gewählten Bereiche von „<?= e($rental->eventName) ?>“ (<?= e($rental->startDate->format('d.m.Y')) ?>) werden durch die Daten von
            „<?= e($context->previous->eventName) ?>“ (<?= e($context->previous->startDate->format('d.m.Y')) ?>) ersetzt.
        <?php else: ?>
            Die gewählten Bereiche der ausgewählten Termine werden durch die Daten von „<?= e($rental->eventName) ?>“ (<?= e($rental->startDate->format('d.m.Y')) ?>) ersetzt.
        <?php endif; ?>
        Vor dem Speichern siehst du eine Vorschau.
    </p>
</div>

<?php require __DIR__ . '/_errors.php'; ?>

<form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/serie/uebernehmen/vorschau" class="<?= $card ?> max-w-3xl space-y-5">
    <?= $csrf ?>
    <input type="hidden" name="richtung" value="<?= e($direction) ?>">
    <?php require __DIR__ . '/_scopes.php'; ?>

    <?php if (!$fromPrevious): ?>
        <fieldset>
            <legend class="text-sm font-semibold text-slate-700 dark:text-slate-300">Termine</legend>
            <div class="mt-2 space-y-1 text-sm">
                <?php foreach ($context->following as $occurrence): ?>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="targets[]" value="<?= e($occurrence->id) ?>"<?= in_array($occurrence->id, $targets, true) ? ' checked' : '' ?>>
                        <span><?= e($occurrence->startDate->format('d.m.Y')) ?> – <?= e($occurrence->eventName) ?><?= $occurrence->eventLocation !== null ? ' · ' . e($occurrence->eventLocation) : '' ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endif; ?>

    <div class="flex justify-end gap-2">
        <a href="/veranstaltungen/<?= e($rental->id) ?>" class="<?= $secondaryButton ?>">Abbrechen</a>
        <button type="submit" class="<?= $primaryButton ?>">Vorschau anzeigen</button>
    </div>
</form>
