<?php
/**
 * Anordnungs-Editor für die Startseite, eingebettet in ein <form> des
 * Aufrufers (eigene Anordnung unter /profil/dashboard, Vorlagen unter
 * /einstellungen/dashboard-vorlagen). Felder siehe
 * App\Infrastructure\Http\DashboardLayoutForm; Ziehen und die Pfeile
 * ergänzt public/assets/js/dashboard-layout-editor.js, das Auswahlfeld je
 * Kachel funktioniert auch ohne JavaScript.
 *
 * @var array<string, \App\Application\Dashboard\DashboardTile> $tiles Kacheln, die zur Auswahl stehen
 * @var \App\Domain\Dashboard\DashboardLayout $layout aktuelle Anordnung (nur Schlüssel aus $tiles)
 * @var bool $showPermissions das je Kachel nötige Recht anzeigen (Vorlagen-Editor)
 */

use App\Application\Dashboard\DashboardTile;
use App\Domain\Dashboard\DashboardLayout;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$showPermissions = $showPermissions ?? false;
$hidden = array_values(array_diff(array_keys($tiles), $layout->keys()));
$editorZones = [
    DashboardLayout::ZONE_MAIN => ['keys' => $layout->zone(DashboardLayout::ZONE_MAIN), 'class' => 'lg:col-span-2', 'grid' => false],
    DashboardLayout::ZONE_SIDE => ['keys' => $layout->zone(DashboardLayout::ZONE_SIDE), 'class' => '', 'grid' => false],
    DashboardLayout::ZONE_BOTTOM => ['keys' => $layout->zone(DashboardLayout::ZONE_BOTTOM), 'class' => 'lg:col-span-3', 'grid' => true],
    'hidden' => ['keys' => $hidden, 'class' => 'lg:col-span-3', 'grid' => true],
];
?>
<div data-layout-editor>
    <p class="mb-3 text-sm text-slate-500 dark:text-slate-400"><?= e(t('dashboard.editor.hint')) ?></p>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <?php foreach ($editorZones as $zone => $config): ?>
            <section class="<?= $config['class'] ?> rounded-lg border <?= $zone === 'hidden' ? 'border-dashed border-slate-300 dark:border-slate-700' : 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950/40' ?> p-3" aria-labelledby="zone-<?= e($zone) ?>-title">
                <h3 id="zone-<?= e($zone) ?>-title" class="text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e(t('dashboard.editor.zone.' . $zone)) ?></h3>
                <p class="text-xs text-slate-500 dark:text-slate-400"><?= e(t('dashboard.editor.zone.' . $zone . '.hint')) ?></p>
                <?php // Ohne Leerraum zwischen <ul> und den Einträgen, damit :empty für die Ablage-Markierung greift. ?>
                <ul data-zone="<?= e($zone) ?>" data-empty-label="<?= e(t('dashboard.editor.empty')) ?>" class="mt-3 grid min-h-[3.5rem] grid-cols-1 gap-2 <?= $config['grid'] ? 'sm:grid-cols-2 lg:grid-cols-3' : '' ?>"><?php foreach ($config['keys'] as $key): ?>
                        <?php $tile = $tiles[$key]; ?>
                        <li data-tile draggable="true" class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                            <input type="hidden" name="order[]" value="<?= e($key) ?>">
                            <span data-grip hidden class="cursor-grab select-none text-slate-400 dark:text-slate-500" aria-hidden="true">⠿</span>
                            <span class="min-w-0 flex-1 basis-40">
                                <span class="flex flex-wrap items-center gap-x-2 text-sm font-medium text-slate-900 dark:text-slate-100">
                                    <?= e($tile->label) ?>
                                    <?php if ($tile->kind === DashboardTile::KIND_CHART): ?>
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-normal text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e(t('dashboard.editor.kind.chart')) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400"><?= e($tile->description) ?></span>
                                <?php if ($showPermissions): ?>
                                    <span class="mt-0.5 block text-xs text-amber-700 dark:text-amber-400"><?= e(t('dashboard.editor.requires', ['permission' => $tile->requiredPermission->label()])) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="flex items-center gap-1">
                                <select name="zone[<?= e($key) ?>]" aria-label="<?= e(t('dashboard.editor.position', ['name' => $tile->label])) ?>"
                                        class="rounded-md border border-slate-300 py-1 pl-2 pr-7 text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                                    <?php foreach (array_keys($editorZones) as $option): ?>
                                        <option value="<?= e($option) ?>" <?= $option === $zone ? 'selected' : '' ?>><?= e(t('dashboard.editor.zone.' . $option)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" data-move="up" hidden aria-label="<?= e(t('dashboard.editor.move_up', ['name' => $tile->label])) ?>"
                                        class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100 disabled:opacity-30 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">↑</button>
                                <button type="button" data-move="down" hidden aria-label="<?= e(t('dashboard.editor.move_down', ['name' => $tile->label])) ?>"
                                        class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-100 disabled:opacity-30 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">↓</button>
                            </span>
                        </li>
                    <?php endforeach; ?></ul>
            </section>
        <?php endforeach; ?>
    </div>
</div>
<style>
    [data-layout-editor] [data-zone]:empty::after { content: attr(data-empty-label); display: flex; align-items: center; justify-content: center; grid-column: 1 / -1; min-height: 3.5rem; border: 1px dashed rgb(148 163 184 / .6); border-radius: .375rem; font-size: .75rem; color: rgb(148 163 184); }
    [data-layout-editor] section.is-drop-target { outline: 2px solid rgb(var(--color-brand-500)); outline-offset: 1px; }
    [data-layout-editor] [data-tile].is-dragging { opacity: .4; }
</style>
<script src="/assets/js/dashboard-layout-editor.js" defer></script>
