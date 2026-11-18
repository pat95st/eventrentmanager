<?php
/**
 * Step 2 of "Wiederkehrend machen": proposed occurrences, editable per row.
 *
 * @var \App\Application\RentalSeries\Query\RentalSeriesPreview $preview
 * @var array<string, mixed> $ruleInput
 * @var string $nameTemplate
 * @var string[] $scopes
 * @var list<array{start: string, end: string, location: string, include: bool, default_start: string, default_end: string}>|null $rowsInput
 * @var array<string, string> $errors
 */

use function App\Infrastructure\Http\e;

require __DIR__ . '/_styles.php';
$source = $preview->source;
// Keep what the user changed when the form comes back with errors.
$inputByDefault = [];
foreach ($rowsInput ?? [] as $row) {
    $inputByDefault[$row['default_start']] = $row;
}
$cell = 'rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900';
?>
<div class="mb-6">
    <a href="/veranstaltungen/<?= e($source->id) ?>/serie/neu" class="text-sm text-slate-500 hover:underline dark:text-slate-400">← Rhythmus ändern</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Vorschau der Termine</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(ucfirst($preview->rule->describe())) ?> · erster Termin: „<?= e($source->eventName) ?>“ am <?= e($source->startDate->format('d.m.Y')) ?>.
        Datum, Ende und Ort lassen sich pro Termin anpassen; abgewählte Termine werden nicht angelegt.
    </p>
</div>

<?php require __DIR__ . '/_errors.php'; ?>

<form method="post" action="/veranstaltungen/<?= e($source->id) ?>/serie" class="space-y-4">
    <?= $csrf ?>
    <?php foreach ($ruleInput as $key => $value): ?>
        <?php if (is_array($value)): ?>
            <?php foreach ($value as $item): ?>
                <input type="hidden" name="<?= e($key) ?>[]" value="<?= e((string) $item) ?>">
            <?php endforeach; ?>
        <?php else: ?>
            <input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <input type="hidden" name="name_template" value="<?= e($nameTemplate) ?>">

    <div class="<?= $card ?> overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <thead class="text-left text-xs text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="py-2 pr-2 font-medium">Anlegen</th>
                    <th class="py-2 pr-2 font-medium">Name</th>
                    <th class="py-2 pr-2 font-medium">Start</th>
                    <th class="py-2 pr-2 font-medium">Ende</th>
                    <th class="py-2 pr-2 font-medium">Ort</th>
                    <th class="py-2 font-medium">Hinweise</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr class="text-slate-500 dark:text-slate-400">
                    <td class="py-2 pr-2">✓</td>
                    <td class="py-2 pr-2"><?= e($source->eventName) ?> <span class="text-xs">(erster Termin)</span></td>
                    <td class="py-2 pr-2"><?= e($source->startDate->format('d.m.Y')) ?></td>
                    <td class="py-2 pr-2"><?= e($source->endDate->format('d.m.Y')) ?></td>
                    <td class="py-2 pr-2"><?= e($source->eventLocation ?? '–') ?></td>
                    <td class="py-2"></td>
                </tr>
                <?php foreach ($preview->rows as $i => $row): ?>
                    <?php
                    $defaultStart = $row->startDate->format('Y-m-d');
                    $defaultEnd = $row->endDate->format('Y-m-d');
                    $old = $inputByDefault[$defaultStart] ?? null;
                    $include = $old !== null ? $old['include'] : !$row->suggestSkip;
                    ?>
                    <tr class="align-top">
                        <td class="py-2 pr-2">
                            <input type="hidden" name="rows[<?= $i ?>][include]" value="0">
                            <input type="checkbox" name="rows[<?= $i ?>][include]" value="1"<?= $include ? ' checked' : '' ?> aria-label="Termin anlegen">
                            <input type="hidden" name="rows[<?= $i ?>][default_start]" value="<?= e($defaultStart) ?>">
                            <input type="hidden" name="rows[<?= $i ?>][default_end]" value="<?= e($defaultEnd) ?>">
                        </td>
                        <td class="py-2 pr-2"><?= e($row->name) ?></td>
                        <td class="py-2 pr-2"><input type="date" name="rows[<?= $i ?>][start]" value="<?= e($old['start'] ?? $defaultStart) ?>" class="<?= $cell ?>"></td>
                        <td class="py-2 pr-2"><input type="date" name="rows[<?= $i ?>][end]" value="<?= e($old['end'] ?? $defaultEnd) ?>" class="<?= $cell ?>"></td>
                        <td class="py-2 pr-2"><input type="text" name="rows[<?= $i ?>][location]" value="<?= e($old['location'] ?? $row->location) ?>" class="<?= $cell ?> w-full min-w-[10rem]"></td>
                        <td class="py-2 text-xs">
                            <?php foreach ($row->warnings as $warning): ?>
                                <p class="text-amber-700 dark:text-amber-400">⚠ <?= e($warning) ?></p>
                            <?php endforeach; ?>
                            <?php foreach ($row->conflicts as $conflict): ?>
                                <p class="text-amber-700 dark:text-amber-400">⚠ <?= e($conflict->describe()) ?></p>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Material-Hinweise sind nur Warnungen – die Termine werden trotzdem angelegt. Termine im Betriebsurlaub oder in gesperrten Zeiträumen sind vorab abgewählt.</p>
    </div>

    <div class="<?= $card ?>">
        <?php require __DIR__ . '/_scopes.php'; ?>
    </div>

    <div class="flex justify-end gap-2">
        <a href="/veranstaltungen/<?= e($source->id) ?>" class="<?= $secondaryButton ?>">Abbrechen</a>
        <button type="submit" class="<?= $primaryButton ?>">Serie anlegen</button>
    </div>
</form>
