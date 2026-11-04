<?php
/**
 * Hinweis auf Ersatzsicherungen (Migration 129): Stromverteiler mit
 * Neozed-/Diazed-Sicherungen in dieser Veranstaltung.
 *
 * Außerdem: Stromanschlüsse des Veranstaltungsorts mit Neozed/Diazed –
 * Ersatz muss der Veranstaltungsort stellen (vorher anfragen).
 *
 * @var list<array{name: string, fuses: array<string, int>}> $spareFuseHints
 * @var \App\Domain\Venue\PowerConnection[] $venueFuseConnections
 */

use function App\Infrastructure\Http\e;

$venueFuseConnections ??= [];
if (($spareFuseHints ?? []) === [] && $venueFuseConnections === []) {
    return;
}
?>
<?php if ($venueFuseConnections !== []): ?>
<div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200" role="note">
    <p class="font-semibold">Ersatzsicherungen beim Veranstaltungsort anfragen</p>
    <p class="mt-0.5 text-xs"><?= e(\App\Domain\Venue\PowerConnection::SPARE_FUSE_HINT) ?></p>
    <ul class="mt-1 list-disc pl-5 text-xs">
        <?php foreach ($venueFuseConnections as $connection): ?>
            <li><?= e($connection->type->value) ?> · <?= e($connection->protectionLabel()) ?><?= $connection->location !== null ? ' · ' . e($connection->location) : '' ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<?php if (($spareFuseHints ?? []) === []) { return; } ?>
<div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200" role="note">
    <p class="font-semibold">Ersatzsicherungen mitnehmen</p>
    <p class="mt-0.5 text-xs">In diesen Stromverteilern stecken Schmelzsicherungen (Neozed/Diazed). Bitte passende Ersatzsicherungen einpacken:</p>
    <ul class="mt-1 list-disc pl-5 text-xs">
        <?php foreach ($spareFuseHints as $hint): ?>
            <li><span class="font-medium"><?= e($hint['name']) ?>:</span> <?= e(implode(', ', array_map(static fn ($name, $count) => $count . ' × ' . $name, array_keys($hint['fuses']), $hint['fuses']))) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
