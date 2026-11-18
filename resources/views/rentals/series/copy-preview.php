<?php
/**
 * Before/after preview of taking data over between occurrences.
 *
 * @var \App\Application\RentalSeries\Query\RentalCopyPreview $preview
 * @var string $rentalId rental the user started from
 * @var string $direction
 */

use function App\Infrastructure\Http\e;

require __DIR__ . '/_styles.php';
$source = $preview->source;
$changeable = array_filter($preview->targets, static fn ($t) => !$t['started']);
?>
<div class="mb-6">
    <a href="/veranstaltungen/<?= e($rentalId) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Zurück zur Veranstaltung</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Vorschau: Daten übernehmen</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Quelle: „<?= e($source->eventName) ?>“ (<?= e($source->startDate->format('d.m.Y')) ?>) ·
        Bereiche: <?= e(implode(', ', array_map(static fn ($s) => $s->label(), $preview->scopes))) ?>
    </p>
</div>

<div class="space-y-4">
    <?php foreach ($preview->targets as $target): ?>
        <div class="<?= $card ?>">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">
                <?= e($target['rental']->startDate->format('d.m.Y')) ?> – <?= e($target['rental']->eventName) ?>
            </h2>
            <?php if ($target['started']): ?>
                <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">Wird übersprungen: Für diesen Termin wurde bereits Material ausgegeben.</p>
            <?php elseif ($target['changes'] === []): ?>
                <p class="mt-2 text-sm text-slate-500">Keine Änderungen.</p>
            <?php else: ?>
                <table class="mt-2 w-full text-sm">
                    <thead class="text-left text-xs text-slate-500 dark:text-slate-400">
                        <tr><th class="py-1 pr-2 font-medium">Bereich</th><th class="py-1 pr-2 font-medium">Vorher</th><th class="py-1 font-medium">Nachher</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($target['changes'] as $change): ?>
                            <tr>
                                <td class="py-1 pr-2 text-slate-600 dark:text-slate-400"><?= e($change['area']) ?></td>
                                <td class="py-1 pr-2 text-red-700 dark:text-red-400"><?= e($change['before']) ?></td>
                                <td class="py-1 text-emerald-700 dark:text-emerald-400"><?= e($change['after']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<form method="post" action="/veranstaltungen/<?= e($rentalId) ?>/serie/uebernehmen" class="mt-6 flex justify-end gap-2">
    <?= $csrf ?>
    <input type="hidden" name="richtung" value="<?= e($direction) ?>">
    <?php foreach ($preview->scopes as $scope): ?>
        <input type="hidden" name="scopes[]" value="<?= e($scope->value) ?>">
    <?php endforeach; ?>
    <?php foreach ($preview->targets as $target): ?>
        <input type="hidden" name="targets[]" value="<?= e($target['rental']->id) ?>">
    <?php endforeach; ?>
    <a href="/veranstaltungen/<?= e($rentalId) ?>" class="<?= $secondaryButton ?>">Abbrechen</a>
    <?php if ($changeable !== []): ?>
        <button type="submit" class="<?= $primaryButton ?>">Jetzt übernehmen</button>
    <?php endif; ?>
</form>
