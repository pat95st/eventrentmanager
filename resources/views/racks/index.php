<?php
/** @var \App\Application\Rack\Query\RackDetail[] $racks */
/** @var string $search */

use function App\Infrastructure\Http\e;
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Racks</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Racks mit Ausstattung und eingebauten Geräten.</p>
    </div>
    <a href="/racks/neu" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">+ Neues Rack</a>
</div>

<form method="get" action="/racks" class="mb-4 flex gap-2">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Bezeichnung, Inventarnummer oder Farbe…" class="w-full max-w-sm rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
    <button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Suchen</button>
</form>

<?php if ($racks === []): ?>
    <div class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
        <?= $search !== '' ? 'Keine Racks gefunden.' : 'Noch keine Racks angelegt.' ?>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-medium uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2.5">Bezeichnung</th>
                    <th class="px-4 py-2.5">Inventarnummer</th>
                    <th class="px-4 py-2.5">Breite</th>
                    <th class="px-4 py-2.5">HE (belegt)</th>
                    <th class="px-4 py-2.5">Einbautiefe</th>
                    <th class="px-4 py-2.5">Ausstattung</th>
                    <th class="px-4 py-2.5">Fest zugeordnet</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($racks as $detail): ?>
                    <?php $rack = $detail->rack; ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3">
                            <a href="/racks/<?= e($rack->id) ?>" class="font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e($rack->name) ?></a>
                            <?php if ($detail->manufacturer !== null): ?><div class="text-xs text-slate-500 dark:text-slate-400"><?= e($detail->manufacturer->name) ?></div><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-400"><?= e($rack->inventoryNumber) ?></td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= e($rack->width->label()) ?></td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400" data-sort-value="<?= $rack->heightUnits ?>"><?= $rack->heightUnits ?> HE (<?= $detail->usedUnits() ?>)</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?= $rack->installDepthCm !== null ? e(rtrim(rtrim(number_format((float) $rack->installDepthCm, 1, ',', ''), '0'), ',')) . ' cm' : '–' ?></td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                            <?= e(implode(' · ', array_filter([
                                $rack->railType === \App\Domain\Rack\RackRailType::FrontBack ? 'Schienen v+h' : 'Schiene vorne',
                                $rack->hasShockMount ? 'Shockmount' : null,
                                $rack->hasWheels ? 'Rollen' : null,
                                $rack->color,
                            ]))) ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                            <?php if ($detail->fixedDevice !== null): ?>
                                <span class="font-mono"><?= e($detail->fixedDevice['number']) ?></span>
                                <?php if ($detail->issuedIn !== null): ?><div class="text-amber-700 dark:text-amber-400">unterwegs: <?= e($detail->issuedIn['eventName']) ?></div><?php endif; ?>
                            <?php else: ?>–<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
