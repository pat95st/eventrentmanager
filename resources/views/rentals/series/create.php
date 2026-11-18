<?php
/**
 * Step 1 of "Wiederkehrend machen": rhythm and end of the series.
 *
 * @var \App\Domain\Rental\Rental $rental
 * @var array<string, mixed> $rule
 * @var string $nameTemplate
 * @var array<string, string> $errors
 */

use function App\Infrastructure\Http\e;

require __DIR__ . '/_styles.php';
?>
<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Zurück zur Veranstaltung</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Wiederkehrend machen</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        „<?= e($rental->eventName) ?>“ am <?= e($rental->startDate->format('d.m.Y')) ?> wird der erste Termin der Serie.
        Die weiteren Termine werden aus dieser Veranstaltung übernommen; im nächsten Schritt lassen sie sich einzeln anpassen.
    </p>
</div>

<?php require __DIR__ . '/_errors.php'; ?>

<form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/serie/vorschau" class="<?= $card ?> space-y-5">
    <?= $csrf ?>
    <?php $showEnd = true; require __DIR__ . '/_rule_fields.php'; ?>

    <div>
        <label class="<?= $label ?>" for="series-name">Name der Termine (optional)</label>
        <input id="series-name" type="text" name="name_template" maxlength="255" value="<?= e($nameTemplate) ?>" placeholder="leer = „<?= e($rental->eventName) ?>“" class="<?= $input ?>">
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Platzhalter: <code>{datum}</code>, <code>{nr}</code> (laufende Nummer), <code>{jahr}</code> – z. B. „Konzertreihe #{nr}“.</p>
    </div>

    <div class="flex justify-end gap-2">
        <a href="/veranstaltungen/<?= e($rental->id) ?>" class="<?= $secondaryButton ?>">Abbrechen</a>
        <button type="submit" class="<?= $primaryButton ?>">Weiter zur Vorschau</button>
    </div>
</form>
