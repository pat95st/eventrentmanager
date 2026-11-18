<?php
/**
 * Change the rhythm of a series from a given date on.
 *
 * @var \App\Application\RentalSeries\Query\RentalSeriesOverview $overview
 * @var string $firstDate
 * @var array<string, mixed> $rule
 * @var array<string, string> $errors
 */

use function App\Infrastructure\Http\e;

require __DIR__ . '/_styles.php';
$series = $overview->series;
?>
<div class="mb-6">
    <a href="/serien/<?= e($series->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Zurück zur Serie</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Rhythmus ändern</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Bisher: <?= e($series->rule->describe()) ?>.
        Termine ab dem gewählten Datum werden auf die neuen Daten verschoben und behalten ihre Daten (Material, Techniker …).
        Termine mit bereits ausgegebenem Material oder von Hand geändertem Datum bleiben, wie sie sind.
        Fehlende Termine werden aus dem jeweils vorherigen Termin erzeugt, überzählige gelöscht.
    </p>
</div>

<?php require __DIR__ . '/_errors.php'; ?>

<form method="post" action="/serien/<?= e($series->id) ?>/rhythmus" class="<?= $card ?> max-w-2xl space-y-5"
      data-confirm-key="rentals.series.change_rule" data-confirm="Rhythmus jetzt ändern? Termine werden verschoben, angelegt oder gelöscht.">
    <?= $csrf ?>
    <div>
        <label class="<?= $label ?>" for="series-first-date">Erster Termin nach neuem Rhythmus</label>
        <input id="series-first-date" type="date" name="first_date" value="<?= e($firstDate) ?>" class="<?= $input ?> max-w-[12rem]">
    </div>
    <?php $showEnd = true; require __DIR__ . '/_rule_fields.php'; ?>
    <div class="flex justify-end gap-2">
        <a href="/serien/<?= e($series->id) ?>" class="<?= $secondaryButton ?>">Abbrechen</a>
        <button type="submit" class="<?= $primaryButton ?>">Rhythmus ändern</button>
    </div>
</form>
