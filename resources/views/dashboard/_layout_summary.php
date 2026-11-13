<?php
/**
 * Kurzfassung einer Anordnung: je Bereich die Namen der Kacheln.
 *
 * @var array<string, \App\Application\Dashboard\DashboardTile> $tiles
 * @var \App\Domain\Dashboard\DashboardLayout $layout nur Schlüssel aus $tiles
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>
<dl class="mt-3 space-y-1.5 text-xs">
    <?php foreach ($layout->zones() as $zone => $keys): ?>
        <?php if ($keys !== []): ?>
            <div>
                <dt class="font-medium text-slate-600 dark:text-slate-300"><?= e(t('dashboard.editor.zone.' . $zone)) ?></dt>
                <dd class="text-slate-500 dark:text-slate-400"><?= e(implode(' · ', array_map(static fn (string $key) => $tiles[$key]->label, $keys))) ?></dd>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</dl>
